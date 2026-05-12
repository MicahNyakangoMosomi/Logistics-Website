<?php

require_once __DIR__ . '/Database.php';

/**
 * Class Mpesa
 * Handles M-Pesa Daraja API operations:
 * - Access token generation
 * - C2B URL registration
 * - Callback processing & storage
 */
class Mpesa
{
    /**
     * Cached config to avoid reloading file multiple times
     */
    private static ?array $config = null;

    /**
     * Load config once (singleton-style)
     */
    private static function config(): array
    {
        if (self::$config === null) {
            self::$config = require __DIR__ . '/../config/config.php';
        }
        return self::$config;
    }

    /**
     * Get M-Pesa base URL depending on environment
     */
    private static function baseUrl(): string
    {
        $env = self::config()['mpesa']['environment'];

        return strtolower($env) === 'sandbox'
            ? 'https://sandbox.safaricom.co.ke'
            : 'https://api.safaricom.co.ke';
    }

    /**
     * Generate OAuth access token
     */
    public static function accessToken(): string
    {
        $mpesa = self::config()['mpesa'];

        if (empty($mpesa['consumer_key']) || empty($mpesa['consumer_secret'])) {
            throw new RuntimeException("M-Pesa credentials missing.");
        }

        $credentials = base64_encode(
            $mpesa['consumer_key'] . ':' . $mpesa['consumer_secret']
        );

        $response = self::request(
            self::baseUrl() . '/oauth/v1/generate?grant_type=client_credentials',
            'GET',
            [
                'Authorization: Basic ' . $credentials
            ]
        );

        if (empty($response['access_token'])) {
            throw new RuntimeException("Failed to generate access token.");
        }

        return $response['access_token'];
    }

    /**
     * Register C2B confirmation & validation URLs
     */
    public static function registerC2BUrls(
        string $confirmationUrl,
        string $validationUrl,
        string $responseType = 'Completed'
    ): array {

        $mpesa = self::config()['mpesa'];

        if (empty($mpesa['shortcode'])) {
            throw new RuntimeException("Shortcode is required.");
        }

        return self::request(
            self::baseUrl() . '/mpesa/c2b/v1/registerurl',
            'POST',
            [
                'Authorization: Bearer ' . self::accessToken(),
                'Content-Type: application/json'
            ],
            [
                'ShortCode' => $mpesa['shortcode'],
                'ResponseType' => $responseType,
                'ConfirmationURL' => $confirmationUrl,
                'ValidationURL' => $validationUrl
            ]
        );
    }

    /**
     * Handle and store C2B callback data
     */
    public static function recordC2BCallback(array $payload): array
    {
        $data = self::normalizeC2BPayload($payload);
        self::validateC2BData($data);

        $pdo = Database::connection();

        // Prevent duplicate transaction insertion
        $check = $pdo->prepare("SELECT TranID FROM transactions WHERE TranID = :id LIMIT 1");
        $check->execute([':id' => $data['TranID']]);

        if ($check->fetch()) {
            return [
                'status' => 'duplicate',
                'message' => 'Transaction already exists',
                'tran_id' => $data['TranID']
            ];
        }

        // Try match member by National ID
        $member = self::findMemberByNationalId($data['NationalID']);
        $memberId = $member ? (int)$member['MemberID'] : null;

        // Insert transaction
        $stmt = $pdo->prepare("
            INSERT INTO transactions
            (TranID, MemberID, NationalID, FirstName, LastName, MSISDN, Amount, TranTime)
            VALUES
            (:tran_id, :member_id, :national_id, :first_name, :last_name, :msisdn, :amount, :tran_time)
        ");

        $stmt->execute([
            ':tran_id' => $data['TranID'],
            ':member_id' => $memberId,
            ':national_id' => $data['NationalID'],
            ':first_name' => $data['FirstName'],
            ':last_name' => $data['LastName'],
            ':msisdn' => $data['MSISDN'],
            ':amount' => $data['Amount'],
            ':tran_time' => $data['TranTime']
        ]);

        return [
            'status' => 'recorded',
            'message' => $memberId
                ? 'Transaction linked to member'
                : 'Transaction stored without match',
            'tran_id' => $data['TranID'],
            'member_id' => $memberId
        ];
    }

    /**
     * Normalize raw M-Pesa callback payload
     */
    private static function normalizeC2BPayload(array $payload): array
    {
        return [
            'NationalID' => self::cleanString($payload['BillRefNumber'] ?? ''),
            'TranID'     => self::cleanString($payload['TransID'] ?? ''),
            'Amount'     => (float)($payload['TransAmount'] ?? 0),
            'MSISDN'     => self::normalizePhone($payload['MSISDN'] ?? ''),
            'FirstName'  => self::cleanString($payload['FirstName'] ?? ''),
            'LastName'   => self::cleanString($payload['LastName'] ?? ''),
            'TranTime'   => self::formatTime($payload['TransTime'] ?? '')
        ];
    }

    /**
     * Basic validation for required fields
     */
    private static function validateC2BData(array $data): void
    {
        if ($data['TranID'] === '') {
            throw new InvalidArgumentException("Missing transaction ID");
        }

        if ($data['NationalID'] === '') {
            throw new InvalidArgumentException("Missing National ID");
        }

        if ($data['Amount'] <= 0) {
            throw new InvalidArgumentException("Invalid amount");
        }

        if ($data['MSISDN'] === '') {
            throw new InvalidArgumentException("Missing phone number");
        }
    }

    /**
     * Find member by National ID
     */
    private static function findMemberByNationalId(string $id): ?array
    {
        $stmt = Database::connection()->prepare("
            SELECT * FROM members WHERE NationalID = :id LIMIT 1
        ");

        $stmt->execute([':id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Normalize phone number to 254 format
     */
    private static function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', $phone);

        if (str_starts_with($phone, '0')) {
            return '254' . substr($phone, 1);
        }

        if (str_starts_with($phone, '+')) {
            return substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Convert M-Pesa timestamp to MySQL format
     */
    private static function formatTime(string $time): ?string
    {
        if ($time === '') return null;

        $date = DateTime::createFromFormat('YmdHis', $time);

        return $date ? $date->format('Y-m-d H:i:s') : null;
    }

    /**
     * Clean string input
     */
    private static function cleanString($value): string
    {
        return trim((string)$value);
    }

    /**
     * Generic HTTP request handler (cURL)
     */
    private static function request(
        string $url,
        string $method,
        array $headers = [],
        ?array $payload = null
    ): array {

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER      => $headers,
            CURLOPT_TIMEOUT         => 30,
            CURLOPT_CONNECTTIMEOUT  => 10
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $response = curl_exec($ch);
        $error     = curl_error($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        // Network failure
        if ($response === false || $httpCode === 0) {
            throw new RuntimeException("Request failed: " . $error);
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new RuntimeException("Invalid JSON response from M-Pesa");
        }

        // API error response
        if ($httpCode >= 400) {
            $msg = $decoded['errorMessage']
                ?? $decoded['ResponseDescription']
                ?? 'M-Pesa request failed';

            throw new RuntimeException($msg);
        }

        return $decoded;
    }
}