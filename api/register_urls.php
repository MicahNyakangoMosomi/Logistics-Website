<?php

require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Mpesa.php';

Auth::requireAdmin();

header('Content-Type: application/json');

try {
    // Get the confirmation and validation URLs from the request, allowing them to be sent via either POST or GET
    // If validation_url is not provided, default it to the value of confirmation_url
    $confirmationUrl = trim($_POST['confirmation_url'] ?? $_GET['confirmation_url'] ?? '');
    $validationUrl = trim($_POST['validation_url'] ?? $_GET['validation_url'] ?? $confirmationUrl);

    // Ensure confirmation_url is provided, as it's required for M-Pesa to send transaction notifications

    if ($confirmationUrl === '') {
        throw new InvalidArgumentException('confirmation_url is required.');
    }

    echo json_encode(Mpesa::registerC2BUrls($confirmationUrl, $validationUrl));
    // Note: The registerC2BUrls method should handle the logic of sending the registration request to Safaricom's API and return the appropriate response or throw an exception if something goes wrong.
} catch (Throwable $error) {
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => $error->getMessage(),
    ]);
}
