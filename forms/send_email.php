<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $subject = htmlspecialchars($_POST['subject']);
    $message = htmlspecialchars($_POST['message']);

    $to = "muhidinosman@gnail.com";
    $email_subject = "Message from Website: $subject";
    $email_body = "Name: $name\nEmail: $email\n\nMessage:\n$message";

    $headers = "From: $email\r\n";
    $headers .= "Reply-To: $email\r\n";

    if (mail($to, $email_subject, $email_body, $headers)) {
        echo "Your message has been sent. Thank you!";
    } else {
        echo "There was an error sending your message. Please try again later.";
    }
}
?>
