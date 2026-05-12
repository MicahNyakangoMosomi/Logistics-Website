<?php

require_once __DIR__ . '/../classes/Mpesa.php';
// No authentication required for this endpoint, as it's called by Safaricom's servers to send transaction notifications
header('Content-Type: application/json');

$rawPayload = file_get_contents('php://input') ?: '';
$logDir = __DIR__ . '/../logs';

// Ensure the logs directory exists before attempting to write logs
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Log the raw payload for debugging purposes. This can help in troubleshooting issues with the callback data received from Safaricom.
file_put_contents(
    $logDir . '/mpesa-c2b-callbacks.log',
    '[' . date('c') . '] ' . $rawPayload . PHP_EOL,
    FILE_APPEND
);

try {
    // Decode the JSON payload received from Safaricom. The JSON_THROW_ON_ERROR flag ensures that any issues with the JSON format will throw an exception, which we can catch and log.
    $payload = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
    // Process the callback data and record the transaction in the database. The recordC2BCallback method should handle the logic of validating the payload, extracting relevant information, and saving it to the database. It should return an array with a 'message' and 'status' that we can use in our response to Safaricom.
    $result = Mpesa::recordC2BCallback($payload);

    // Respond to Safaricom with a success message. The ResultCode of 0 indicates that the callback was processed successfully, while the ResultDesc can provide additional information about the processing result. The TransactionStatus can be used to indicate the status of the transaction (e.g., "Completed", "Failed", etc.).
    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => $result['message'],
        'TransactionStatus' => $result['status'],
    ]);\
    // Note: It's important to respond to Safaricom with the appropriate ResultCode and ResultDesc, as this informs their system whether the callback was processed successfully or if there were issues that need to be addressed. If an error occurs during processing, we catch it and log the error message for further investigation, while also responding with a non-zero ResultCode to indicate a failure in processing the callback.
} catch (Throwable $error) {
    http_response_code(400);

    file_put_contents(
        // Log the error message to a separate log file for easier troubleshooting of callback processing issues. This can help identify common errors or issues with the payload received from Safaricom.
        $logDir . '/mpesa-c2b-errors.log',
        '[' . date('c') . '] ' . $error->getMessage() . PHP_EOL,
        FILE_APPEND
    );

    echo json_encode([
        // Respond to Safaricom with an error message. The ResultCode of 1 indicates that there was an error processing the callback, and the ResultDesc provides details about the error. This informs Safaricom that there was an issue with processing the callback, which may prompt them to retry sending the callback data.
        'ResultCode' => 1,
        'ResultDesc' => $error->getMessage(),
    ]);
}
