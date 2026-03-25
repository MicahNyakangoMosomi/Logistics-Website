<?php
/**
 * Mashirikiano SACCO
 * Member Registration Processing Script via MailerSend
 */

// Return "OK" for success as expected by php-email-form frontend
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullName = strip_tags(trim($_POST["full_name"] ?? ''));
    $email = filter_var(trim($_POST["email"] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone = strip_tags(trim($_POST["phone"] ?? ''));
    $payslipId = strip_tags(trim($_POST["payslip_id"] ?? '')); // Optional
    $joinMessage = strip_tags(trim($_POST["join_message"] ?? '')); // Optional

    // Validate required physical details
    if (empty($fullName) || empty($email) || empty($phone)) {
        http_response_code(400);
        echo "Please fill out all required details (Name, Email, Phone).";
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo "Please provide a valid email address.";
        exit;
    }

    $requiredFiles = [
        'id_passport_file' => 'ID or Passport',
        'passport_photo_file' => 'Passport Photo',
        'registration_form_file' => 'Registration Form',
        'kra_certificate_file' => 'KRA Certificate'
    ];
    
    $optionalFiles = [
        'payslip_file' => 'Payslip'
    ];

    $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    $max_size = 10 * 1024 * 1024; // 10MB
    $attachments = [];

    // Helper to process files for MailerSend
    function processFileForAttachment($field, $label, $isRequired, &$attachments, $allowed_extensions, $max_size) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            if ($_FILES[$field]['size'] > $max_size) {
                http_response_code(400);
                echo "The file for $label is too large. Maximum size is 10MB.";
                exit;
            }

            $fileName = basename($_FILES[$field]['name']);
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_extensions)) {
                http_response_code(400);
                echo "Invalid file type for $label. Allowed: pdf, jpg, jpeg, png, doc, docx.";
                exit;
            }

            $tmpName = $_FILES[$field]['tmp_name'];
            $fileData = file_get_contents($tmpName);
            $base64Body = base64_encode($fileData);
            
            // MailerSend expects the raw base64 string
            $attachments[] = [
                'content' => $base64Body,
                'filename' => $field . '_' . $fileName,
                'disposition' => 'attachment'
            ];
        } else if ($isRequired) {
            http_response_code(400);
            echo "Missing or invalid required file: $label.";
            exit;
        }
    }

    foreach ($requiredFiles as $field => $label) {
        processFileForAttachment($field, $label, true, $attachments, $allowed_extensions, $max_size);
    }
    foreach ($optionalFiles as $field => $label) {
        processFileForAttachment($field, $label, false, $attachments, $allowed_extensions, $max_size);
    }

    // Construct Email Content
    $emailHtml = "<h3>New Member Registration Request</h3>";
    $emailHtml .= "<p><strong>Name:</strong> {$fullName}</p>";
    $emailHtml .= "<p><strong>Email:</strong> {$email}</p>";
    $emailHtml .= "<p><strong>Phone:</strong> {$phone}</p>";
    if (!empty($payslipId)) {
        $emailHtml .= "<p><strong>Payslip ID:</strong> {$payslipId}</p>";
    }
    if (!empty($joinMessage)) {
        $emailHtml .= "<p><strong>Message:</strong><br/>" . nl2br($joinMessage) . "</p>";
    }
    $emailHtml .= "<p><em>The required documents have been attached to this email.</em></p>";

    $emailText = strip_tags(str_replace(['<br/>', '</p>'], "\n", $emailHtml));

    // Prepare JSON payload for MailerSend
    $mailersendToken = 'mlsn.b0eec52127937d7022b8558cd1227b3f3047d9be645e246a2c9463ae42113245';
    
    // Note: MailerSend requires the 'from.email' to match a verified domain in their dashboard
    $payload = json_encode([
        'from' => [
            'email' => 'info@mashirikianosacco.co.ke', 
            'name' => 'Mashirikiano SACCO'
        ],
        'to' => [
            [
                'email' => 'nyakangomicah4@gmail.com',
                'name' => 'Mashirikiano Admin'
            ]
        ],
        'subject' => 'requestion to join',
        'html' => $emailHtml,
        'text' => $emailText,
        'attachments' => $attachments
    ]);

    $ch = curl_init('https://api.mailersend.com/v1/email');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Requested-With: XMLHttpRequest',
        'Authorization: Bearer ' . $mailersendToken
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200 || $httpCode == 202) {
        http_response_code(200);
        echo "OK";
    } else {
        http_response_code(500);
        $errorMsg = json_decode($response, true);
        if (isset($errorMsg['message'])) {
            // Note: if there's a domain verify error, it usually returns 403 or 422 with a message
            echo "Email Provider Error: " . $errorMsg['message'] . ". Ensure the domain mashirikianosacco.co.ke is verified on MailerSend.";
        } else {
            echo "There was a problem dispatching the registration notification email via MailerSend. HTTP Code: " . $httpCode;
        }
    }

} else {
    http_response_code(403);
    echo "There was a problem with your submission, please try again.";
}
?>
