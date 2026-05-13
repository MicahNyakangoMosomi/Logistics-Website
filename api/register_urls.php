<?php

declare(strict_types=1);

require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Mpesa.php';

/**
 * -----------------------------------------------------
 * M-PESA C2B URL REGISTRATION ENDPOINT
 * -----------------------------------------------------
 * Purpose:
 * Registers Confirmation & Validation URLs
 * with Safaricom Daraja API.
 *
 * Only admins can access this endpoint.
 * -----------------------------------------------------
 */

/**
 * Allow only authenticated admins
 */
Auth::requireAdmin();

/**
 * All responses are JSON
 */
header('Content-Type: application/json');

try {

    /**
     * -------------------------------------------------
     * 1. Allow only POST requests
     * -------------------------------------------------
     */
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Only POST requests are allowed.');
    }

    /**
     * -------------------------------------------------
     * 2. Get URLs from request body
     * -------------------------------------------------
     */
    $confirmationUrl = trim($_POST['confirmation_url'] ?? '');

    /**
     * If validation URL is not provided,
     * use confirmation URL as fallback.
     */
    $validationUrl = trim(
        $_POST['validation_url'] ?? $confirmationUrl
    );

    /**
     * -------------------------------------------------
     * 3. Validate confirmation URL
     * -------------------------------------------------
     */
    if ($confirmationUrl === '') {
        throw new InvalidArgumentException(
            'Confirmation URL is required.'
        );
    }

    /**
     * Ensure confirmation URL is valid
     */
    if (!filter_var($confirmationUrl, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException(
            'Invalid confirmation URL.'
        );
    }

    /**
     * -------------------------------------------------
     * 4. Validate validation URL
     * -------------------------------------------------
     */
    if (!filter_var($validationUrl, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException(
            'Invalid validation URL.'
        );
    }

    /**
     * -------------------------------------------------
     * 5. Register URLs with Safaricom
     * -------------------------------------------------
     */
    $response = Mpesa::registerC2BUrls(
        $confirmationUrl,
        $validationUrl
    );

    /**
     * -------------------------------------------------
     * 6. Success response
     * -------------------------------------------------
     */
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'message' => 'C2B URLs registered successfully.',
        'data'    => $response
    ]);

} catch (Throwable $e) {

    /**
     * -------------------------------------------------
     * 7. Error response
     * -------------------------------------------------
     */
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}