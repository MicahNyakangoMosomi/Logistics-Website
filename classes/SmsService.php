<?php

declare(strict_types=1);

/**
 * Class SmsService
 * Handles sending SMS messages via the MobileSasa API.
 */
class SmsService
{
    private static ?array $config = null;

    private static function config(): array
    {
        if (self::$config === null) {
            self::$config = require __DIR__ . '/../config/config.php';
        }
        return self::$config;
    }

    /**
     * Send an SMS using MobileSasa API
     * 
     * @param string $phone The recipient phone number
     * @param string $message The SMS text
     * @return bool True if successful, false otherwise
     */
    public static function sendSms(string $phone, string $message): bool
    {
        $mobilesasa = self::config()['mobilesasa'] ?? [];
        $apiKey = $mobilesasa['api_key'] ?? '';
        $senderId = $mobilesasa['sender_id'] ?? '';

        if (empty($apiKey)) {
            self::logError("SMS skipped: Oracom API key is not configured. Phone: $phone, Message: $message");
            return false;
        }

        $url = 'https://vas.oramobile.co.ke/api/v2/send/message';

        $data = [
            'phone'    => self::normalizePhone($phone),
            'message'  => $message,
            'senderId' => $senderId,
            'trackingId' => self::generateTrackingId()
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            self::logError("MobileSasa API Error (HTTP $httpCode): " . ($error ?: $response) . " Payload: " . json_encode($data));
            return false;
        }

        return true;
    }

    /* generate a unique tracking ID for each message */
    private static function generateTrackingId(): string
    {        
        // Using random_bytes for better uniqueness and security
        return bin2hex(random_bytes(16));
    }


    /**
     * Normalize phone number to +254 format required by MobileSasa
     */
    private static function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', $phone);
        
        if (str_starts_with($phone, '0')) {
            return '+254' . substr($phone, 1);
        }
        
        if (str_starts_with($phone, '254')) {
            return '+' . $phone;
        }
        
        if (!str_starts_with($phone, '+')) {
            if (strlen($phone) === 9) {
                return '+254' . $phone;
            }
        }
        
        return $phone;
    }

    /**
     * Log SMS errors gracefully without breaking the app
     */
    private static function logError(string $message): void
    {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        @file_put_contents(
            $logDir . '/sms-errors.log',
            '[' . date('c') . '] ' . $message . PHP_EOL,
            FILE_APPEND
        );
    }
}
