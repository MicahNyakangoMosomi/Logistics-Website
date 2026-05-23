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
        $sender_fullName = trim($data['FirstName'] . ' ' . $data['LastName']);
        $nationalId = $data['NationalID'];
        $tranId = $data['TranID'];
        $tranTime = $data['TranTime'] ?? date('Y-m-d H:i:s');

        // Fetch member phone number using NationalID for SMS notification
        $pdo = Database::connection();
        // Use prepared statement to prevent SQL injection to get member phone number and FirstName and LastName where NationalID = $nationalId
        $memberStmt = $pdo->prepare('SELECT PrimaryNumber, FirstName, LastName FROM members WHERE NationalID = :national_id LIMIT 1');

        // Execute the statement with the provided national ID
        $memberStmt->execute([':national_id' => $nationalId]);
        // place a varibale of user number, and name(first and last name) and combine them to a full name


        // Fetch the member data and extract phone number and full name
        $memberData = $memberStmt->fetch(PDO::FETCH_ASSOC);
        $memberPhone = trim((string)($memberData['PrimaryNumber'] ?? ''));
        $memberFirstName = trim((string)($memberData['FirstName'] ?? ''));
        $memberLastName = trim((string)($memberData['LastName'] ?? ''));
        $fullName = trim($memberFirstName . ' ' . $memberLastName);

        
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

        // Send SMS notification to member with transaction details and contribution summary(deposit and contribution amounts) and total contribution to date. 
        $allocation = [];
        if ($depositAmount > 0) {
            // If there is a deposit amount, include it in the allocation details. This way, if there are no deposits, we won't show "Deposit KES 0.00" in the SMS, which could be confusing for the member.
            $allocation[] = 'Deposit KES ' . number_format($depositAmount, 2);
        }
        if ($contributionAmount > 0) {
            // If there is a contribution amount, include it in the allocation details. This way, if there are no contributions, we won't show "Contribution KES 0.00" in the SMS, which could be confusing for the member. 
            $allocation[] = 'Contribution KES ' . number_format($contributionAmount, 2);
        }
        // If there are allocation details, include them in the message, otherwise skip that part of the message to avoid confusion if there are no allocation details.
        $allocationText = $allocation ? ' Allocation: ' . implode(', ', $allocation) . '.' : '';

        // auto generate the last sentence from a list of possible sentences to add some variation to the SMS messages sent to members. This will make the messages feel more personalized and less robotic, which can improve member engagement and satisfaction. We can use a simple array of sentence templates and randomly select one each time we send an SMS.
        $closingSentences = [
            "Thank you for being part of Mashirikiano SACCO. Explore more member benefits and services at https://mashirikianosacco.co.ke/ or call 0758500557 for support.",

            "Your contribution is building your financial future with Mashirikiano SACCO. Discover more services at https://mashirikianosacco.co.ke/ or reach us on 0758500557.",

            "Grow with us. Visit https://mashirikianosacco.co.ke/ to explore loans, savings, and more SACCO services. Need help? Call 0758500557.",

            "There’s more waiting for you at Mashirikiano SACCO. Learn about our savings and loan products at https://mashirikianosacco.co.ke/ or call 0758500557.",

            "Thank you for trusting Mashirikiano SACCO. Stay connected and explore member opportunities at https://mashirikianosacco.co.ke/ or 0758500557.",

            "Make the most of your membership. Visit https://mashirikianosacco.co.ke/ to discover our services and financial solutions.",

            "Your SACCO journey is growing stronger. See what’s new at https://mashirikianosacco.co.ke/ or talk to us on 0758500557.",
            
            "We’re building wealth together. Check out more SACCO services at https://mashirikianosacco.co.ke/ and stay engaged with us via 0758500557."
        ];
        // Randomly select a closing sentence from the array
        $closingSentence = $closingSentences[array_rand($closingSentences)];

        $smsMessage = "Dear {$fullName}, payment of KES {$amount} has been successfully received. Transaction Ref: {$tranId}. Time: {$tranTime}. {$allocationText}, Your Total contribution balance is KES {$totalContribution}. {$closingSentence}";

        //$smsMessage = "Confirmed. Payment of {$amount} sent to {$fullName} of ID {$nationalId} Ref {$tranId} at {$tranTime}.{$allocationText} Total Contribution is {$totalContribution}. {$closingSentence}";
        
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
