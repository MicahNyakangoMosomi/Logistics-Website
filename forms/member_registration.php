<?php
/**
 * Mashirikiano SACCO
 * Member Registration Processing Script
 */

// Return JSON or Text based on the php-email-form JS validation script (it expects 'OK' as success)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullName = strip_tags(trim($_POST["full_name"] ?? ''));
    $email = filter_var(trim($_POST["email"] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone = strip_tags(trim($_POST["phone"] ?? ''));
    $payslipId = strip_tags(trim($_POST["payslip_id"] ?? ''));
    $joinMessage = strip_tags(trim($_POST["join_message"] ?? ''));

    // Validate textual data
    if (empty($fullName) || empty($email) || empty($phone) || empty($payslipId) || empty($joinMessage)) {
        http_response_code(400);
        echo "Please fill out all required physical details.";
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo "Please provide a valid email address.";
        exit;
    }

    // Set up secure upload directory
    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            http_response_code(500);
            echo "System error: Unable to create uploads directory.";
            exit;
        }
    }

    $uploadedFiles = [];
    $fileFields = [
        'id_passport_file' => 'ID or Passport',
        'passport_photo_file' => 'Passport Photo',
        'registration_form_file' => 'Registration Form',
        'kra_certificate_file' => 'KRA Certificate',
        'payslip_file' => 'Payslip'
    ];
    
    $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    $max_size = 10 * 1024 * 1024; // 10MB
    $timestamp = time();

    // Process each document securely
    foreach ($fileFields as $field => $label) {
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
            $uniqueName = $timestamp . '_' . $field . '_' . preg_replace("/[^a-zA-Z0-9.-]/", "_", $fileName);
            $destination = $uploadDir . $uniqueName;

            if (move_uploaded_file($tmpName, $destination)) {
                $uploadedFiles[$label] = $uniqueName;
            } else {
                http_response_code(500);
                echo "Error uploading $label document. Please try again.";
                exit;
            }
        } else {
            http_response_code(400);
            echo "Missing or invalid file for: $label.";
            exit;
        }
    }

    // Construct Email to SACCO Admin
    $to = "mashirikianosacco@gmail.com";
    $subject = "New Member Registration Request from $fullName";
    
    $emailContent = "A new member registration request has been submitted.\n\n";
    $emailContent .= "Applicant Details:\n";
    $emailContent .= "------------------\n";
    $emailContent .= "Name: $fullName\n";
    $emailContent .= "Email: $email\n";
    $emailContent .= "Phone: $phone\n";
    $emailContent .= "Payslip ID: $payslipId\n\n";
    $emailContent .= "Joining Message:\n";
    $emailContent .= "$joinMessage\n\n";
    
    $emailContent .= "Uploaded Documents (Saved securely in Server /uploads/ directory):\n";
    $emailContent .= "---------------------------------------------------------\n";
    foreach ($uploadedFiles as $label => $filename) {
        $emailContent .= "- $label: $filename\n";
    }

    // Basic headers
    $headers = "From: no-reply@mashirikianosacco.co.ke\r\n";
    $headers .= "Reply-To: $email\r\n";

    // Attempt delivery via basic PHP mail()
    if (mail($to, $subject, $emailContent, $headers)) {
        http_response_code(200);
        // "OK" signals success to the PHP Email Form validation library
        echo "OK";
    } else {
        http_response_code(500);
        echo "There was a problem dispatching the registration notification email, but your files were uploaded successfully.";
    }

} else {
    http_response_code(403);
    echo "There was a problem with your submission, please try again.";
}
?>
