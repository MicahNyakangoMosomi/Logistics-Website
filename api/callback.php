<?php

declare(strict_types=1);

require_once __DIR__ . '/../classes/Mpesa.php';

/**
 * -------------------------------------------------
 * M-PESA C2B CONFIRMATION CALLBACK ENDPOINT
 * -------------------------------------------------
 * - Receives transaction data from Safaricom
 * - Logs raw payload for debugging
 * - Processes and stores transaction
 * - Always responds with HTTP 200
 * -------------------------------------------------
 */

header('Content-Type: application/json');

/**
 * 1. Read raw POST payload from Safaricom
 */
$rawPayload = file_get_contents('php://input') ?: '';

/**
 * 2. Setup logging directory
 */
$logDir = __DIR__ . '/../logs';

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

/**
 * 3. Log raw callback payload (for debugging / audit)
 */
file_put_contents(
    $logDir . '/mpesa-c2b-callbacks.log',
    '[' . date('c') . '] ' . $rawPayload . PHP_EOL,
    FILE_APPEND
);

try {

    /**
     * 4. Validate payload existence
     */
    if ($rawPayload === '') {
        throw new RuntimeException("Empty callback payload received");
    }

    /**
     * 5. Decode JSON payload safely
     */
    $payload = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($payload)) {
        throw new RuntimeException("Invalid callback payload format");
    }

    /**
     * 6. Process transaction via Mpesa service layer
     */
    $result = Mpesa::recordC2BCallback($payload);

    if (isset($result['status']) && $result['status'] === 'recorded' && isset($result['data'])) {
        $data = $result['data'];
        $amount = number_format($data['Amount'], 2);
        $fullName = trim($data['FirstName'] . ' ' . $data['LastName']);
        $nationalId = $data['NationalID'];
        $tranId = $data['TranID'];
        $tranTime = $data['TranTime'] ?? date('Y-m-d H:i:s');
        $pdo = Database::connection();
        $memberStmt = $pdo->prepare('SELECT PrimaryNumber FROM members WHERE NationalID = :national_id LIMIT 1');
        $memberStmt->execute([':national_id' => $nationalId]);
        $memberPhone = trim((string)($memberStmt->fetchColumn() ?: ''));

        $stmt = $pdo->prepare("SELECT SUM(Amount) FROM member_transactions WHERE NationalID = :national_id AND TransactionType = 'contribution'");
        $stmt->execute([':national_id' => $nationalId]);
        $totalContribution = number_format((float)$stmt->fetchColumn(), 2);

        $segments = $result['segments'] ?? [];
        $depositAmount = 0.00;
        $contributionAmount = 0.00;
        foreach ($segments as $segment) {
            if (($segment['type'] ?? '') === 'deposit') {
                $depositAmount += (float)$segment['amount'];
            }
            if (($segment['type'] ?? '') === 'contribution') {
                $contributionAmount += (float)$segment['amount'];
            }
        }

        $allocation = [];
        if ($depositAmount > 0) {
            $allocation[] = 'Deposit KES ' . number_format($depositAmount, 2);
        }
        if ($contributionAmount > 0) {
            $allocation[] = 'Contribution KES ' . number_format($contributionAmount, 2);
        }
        $allocationText = $allocation ? ' Allocation: ' . implode(', ', $allocation) . '.' : '';

        $smsMessage = "Confirmed. Payment of {$amount} to {$fullName} of ID {$nationalId} Ref {$tranId} at {$tranTime}.{$allocationText} Total Contribution is {$totalContribution}. For queries contact 0758500557.";
        
        require_once __DIR__ . '/../classes/SmsService.php';
        if ($memberPhone !== '') {
            SmsService::sendSms($memberPhone, $smsMessage);
        } else {
            file_put_contents(
                $logDir . '/mpesa-c2b-errors.log',
                '[' . date('c') . '] SMS skipped: no member phone found for NationalID ' . $nationalId . ' on transaction ' . $tranId . PHP_EOL,
                FILE_APPEND
            );
        }
    }

    /**
     * 7. Return success response to Safaricom
     * IMPORTANT: Always HTTP 200
     */
    http_response_code(200);

    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => $result['message'] ?? 'Accepted'
    ]);

} catch (Throwable $e) {

    /**
     * 8. Log error separately
     */
    file_put_contents(
        $logDir . '/mpesa-c2b-errors.log',
        '[' . date('c') . '] ' . $e->getMessage() . PHP_EOL,
        FILE_APPEND
    );

    /**
     * 9. STILL return HTTP 200 (important for Safaricom)
     * We do NOT want repeated retries due to HTTP errors
     */
    http_response_code(200);

    echo json_encode([
        'ResultCode' => 1,
        'ResultDesc' => 'Transaction processing failed'
    ]);
}
