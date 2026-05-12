<?php

require_once __DIR__ . '/Database.php';

class Mpesa
{
    public static function accessToken(): string
    {
        $config = require __DIR__ . '/../config/config.php';
        $mpesa = $config['mpesa'];

        if ($mpesa['consumer_key'] === '' || $mpesa['consumer_secret'] === '') {
            throw new RuntimeException('M-Pesa consumer key and secret are required.');
        }

        $baseUrl = self::baseUrl($mpesa['environment']);
        $credentials = base64_encode($mpesa['consumer_key'] . ':' . $mpesa['consumer_secret']);
        $response = self::request(
            $baseUrl . '/oauth/v1/generate?grant_type=client_credentials',
            'GET',
            ['Authorization: Basic ' . $credentials]
        );

        if (empty($response['access_token'])) {
            throw new RuntimeException('Could not retrieve M-Pesa access token.');
        }

        return $response['access_token'];
    }

    public static function registerC2BUrls(string $confirmationUrl, string $validationUrl, string $responseType = 'Completed'): array
    {
        $config = require __DIR__ . '/../config/config.php';
        $mpesa = $config['mpesa'];

        if ($mpesa['shortcode'] === '') {
            throw new RuntimeException('M-Pesa shortcode is required.');
        }

        $baseUrl = self::baseUrl($mpesa['environment']);
        $token = self::accessToken();

        return self::request(
            $baseUrl . '/mpesa/c2b/v1/registerurl',
            'POST',
            [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            [
                'ShortCode' => $mpesa['shortcode'],
                'ResponseType' => $responseType,
                'ConfirmationURL' => $confirmationUrl,
                'ValidationURL' => $validationUrl,
            ]
        );
    }

    public static function recordC2BCallback(array $payload): array
    {
        $data = self::normalizeC2BPayload($payload);
        self::validateC2BData($data);

        $pdo = Database::connection();

        $existing = $pdo->prepare('SELECT TranID FROM transactions WHERE TranID = :tran_id LIMIT 1');
        $existing->execute([':tran_id' => $data['TranID']]);
        if ($existing->fetch()) {
            return [
                'status' => 'duplicate',
                'message' => 'Transaction already recorded.',
                'tran_id' => $data['TranID'],
            ];
        }

        $member = self::findMemberByNationalId($data['NationalID']);
        $memberId = $member ? (int) $member['MemberID'] : null;

        $stmt = $pdo->prepare(
            'INSERT INTO transactions
                (TranID, MemberID, NationalID, FirstName, LastName, MSISDN, Amount, TranTime)
             VALUES
                (:tran_id, :member_id, :national_id, :first_name, :last_name, :msisdn, :amount, :tran_time)'
        );
        $stmt->execute([
            ':tran_id' => $data['TranID'],
            ':member_id' => $memberId,
            ':national_id' => $data['NationalID'],
            ':first_name' => $data['FirstName'],
            ':last_name' => $data['LastName'],
            ':msisdn' => $data['MSISDN'],
            ':amount' => $data['Amount'],
            ':tran_time' => $data['TranTime'],
        ]);

        return [
            'status' => 'recorded',
            'message' => $memberId ? 'Transaction linked to member.' : 'Transaction recorded without member match.',
            'tran_id' => $data['TranID'],
            'member_id' => $memberId,
        ];
    }

    public static function normalizeC2BPayload(array $payload): array
    {
        $billRef = self::stringValue($payload['BillRefNumber'] ?? $payload['BillRefNo'] ?? '');
        $amount = $payload['TransAmount'] ?? $payload['Amount'] ?? 0;

        return [
            'NationalID' => $billRef,
            'TranID' => self::stringValue($payload['TransID'] ?? $payload['TransactionId'] ?? ''),
            'Amount' => is_numeric($amount) ? (float) $amount : 0,
            'MSISDN' => self::stringValue($payload['MSISDN'] ?? $payload['PhoneNumber'] ?? ''),
            'FirstName' => self::stringValue($payload['FirstName'] ?? ''),
            'LastName' => self::stringValue($payload['LastName'] ?? ''),
            'TranTime' => self::formatMpesaTime(self::stringValue($payload['TransTime'] ?? '')),
        ];
    }

    private static function validateC2BData(array $data): void
    {
        if ($data['NationalID'] === '') {
            throw new InvalidArgumentException('BillRefNumber/NationalID is required.');
        }

        if ($data['TranID'] === '') {
            throw new InvalidArgumentException('TransID is required.');
        }

        if ($data['Amount'] <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        if ($data['MSISDN'] === '') {
            throw new InvalidArgumentException('MSISDN is required.');
        }
    }

    private static function findMemberByNationalId(string $nationalId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM members WHERE NationalID = :national_id LIMIT 1');
        $stmt->execute([':national_id' => $nationalId]);
        $member = $stmt->fetch();

        return $member ?: null;
    }

    private static function formatMpesaTime(string $transTime): ?string
    {
        if ($transTime === '') {
            return null;
        }

        $date = DateTime::createFromFormat('YmdHis', $transTime);
        if ($date instanceof DateTime) {
            return $date->format('Y-m-d H:i:s');
        }

        $timestamp = strtotime($transTime);
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    private static function stringValue($value): string
    {
        return trim((string) $value);
    }

    private static function baseUrl(string $environment): string
    {
        return strtolower($environment) === 'sandbox'
            ? 'https://sandbox.safaricom.co.ke'
            : 'https://api.safaricom.co.ke';
    }

    private static function request(string $url, string $method, array $headers = [], ?array $payload = null): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $rawResponse = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($rawResponse === false) {
            throw new RuntimeException('M-Pesa request failed: ' . $error);
        }

        $decoded = json_decode($rawResponse, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('M-Pesa returned an invalid JSON response.');
        }

        if ($status >= 400) {
            $message = $decoded['errorMessage'] ?? $decoded['ResponseDescription'] ?? 'M-Pesa request failed.';
            throw new RuntimeException($message);
        }

        return $decoded;
    }
}
