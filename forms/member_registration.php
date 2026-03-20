<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed.';
    exit;
}

$full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$payslip_id = isset($_POST['payslip_id']) ? trim($_POST['payslip_id']) : '';
$join_message = isset($_POST['join_message']) ? trim($_POST['join_message']) : '';

if ($full_name === '' || $email === '' || $phone === '' || $payslip_id === '' || $join_message === '') {
    http_response_code(400);
    echo 'Please fill all required fields.';
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo 'Please provide a valid email address.';
    exit;
}

$required_files = array(
    'id_passport_file' => 'National ID or Passport',
    'passport_photo_file' => 'Passport Photo',
    'registration_form_file' => 'Completed Membership Registration Form',
    'kra_certificate_file' => 'KRA Certificate',
    'payslip_file' => 'Current Payslip'
);

$allowed_extensions = array('pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx');
$max_size = 10 * 1024 * 1024;

foreach ($required_files as $key => $label) {
    if (!isset($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo 'Missing file: ' . $label;
        exit;
    }

    if ($_FILES[$key]['size'] > $max_size) {
        http_response_code(400);
        echo $label . ' is too large. Maximum allowed size is 10MB.';
        exit;
    }

    $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_extensions)) {
        http_response_code(400);
        echo 'Invalid file type for ' . $label . '.';
        exit;
    }
}

$to = 'mashirikianosacco@gmail.com';
$subject = 'New Member Registration Request - Mashirikiano SACCO';

$body_text = "New member registration request received.\n\n";
$body_text .= "Full Name: " . $full_name . "\n";
$body_text .= "Email: " . $email . "\n";
$body_text .= "Phone: " . $phone . "\n";
$body_text .= "Current Payslip ID/Reference: " . $payslip_id . "\n\n";
$body_text .= "Message:\n" . $join_message . "\n\n";
$body_text .= "Attached Files:\n";
foreach ($required_files as $key => $label) {
    $body_text .= "- " . $label . ": " . $_FILES[$key]['name'] . "\n";
}

$boundary = md5((string)microtime(true));
$headers = "From: " . $email . "\r\n";
$headers .= "Reply-To: " . $email . "\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/mixed; boundary=\"" . $boundary . "\"\r\n";

$message = "--" . $boundary . "\r\n";
$message .= "Content-Type: text/plain; charset=\"UTF-8\"\r\n";
$message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
$message .= $body_text . "\r\n";

foreach ($required_files as $key => $label) {
    $tmp_name = $_FILES[$key]['tmp_name'];
    $file_name = basename($_FILES[$key]['name']);
    $file_data = chunk_split(base64_encode(file_get_contents($tmp_name)));
    $mime = function_exists('mime_content_type') ? mime_content_type($tmp_name) : 'application/octet-stream';

    $message .= "--" . $boundary . "\r\n";
    $message .= "Content-Type: " . $mime . "; name=\"" . $file_name . "\"\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n";
    $message .= "Content-Disposition: attachment; filename=\"" . $file_name . "\"\r\n\r\n";
    $message .= $file_data . "\r\n";
}

$message .= "--" . $boundary . "--";

if (mail($to, $subject, $message, $headers)) {
    echo 'Registration submitted successfully. Thank you for applying to join Mashirikiano SACCO.';
} else {
    http_response_code(500);
    echo 'There was an error sending your registration. Please try again later.';
}
?>

