<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';
require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/captcha_helper.php';

// SMTP configuration.
const MAILTRAP_HOST = 'mail.thenovaai.com';
const MAILTRAP_PORT = 587;
const MAILTRAP_USERNAME = 'info@thenovaai.com';
const MAILTRAP_PASSWORD = 'G1d@%YWgft9y';
const MAILTRAP_FROM_EMAIL = 'info@thenovaai.com';
const MAILTRAP_FROM_NAME = 'The Nova AI';

function sendMailToUser($toEmail, $toName, $subject, $bodyHtml, $bodyText)
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = MAILTRAP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAILTRAP_USERNAME;
        $mail->Password = MAILTRAP_PASSWORD;
        $mail->Port = MAILTRAP_PORT;
        $mail->SMTPSecure = 2;

        $mail->setFrom(MAILTRAP_FROM_EMAIL, MAILTRAP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo(MAILTRAP_FROM_EMAIL, MAILTRAP_FROM_NAME);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $bodyHtml;
        $mail->AltBody = $bodyText;

        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $mail->ErrorInfo,
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'errors' => ['general' => 'Method not allowed.']]);
    exit;
}

$errors = [];

$postData = $_POST;
if (empty($postData)) {
    $rawInput = file_get_contents('php://input');
    parse_str($rawInput, $postData);
    if (empty($postData)) {
        $postData = json_decode($rawInput, true) ?? [];
    }
}

$formType = $postData['form_type'] ?? '';
$captchaInput = trim($postData['captcha'] ?? '');

$targetEmail = 'info@thenovaai.com';
$targetName = 'Admin';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'];
$scriptDir = dirname($_SERVER['PHP_SELF']);
$baseUrl = $protocol . $domainName . rtrim($scriptDir, '/');

$allowedFormTypes = ['contact', 'project', 'schedule_demo'];

if (!in_array($formType, $allowedFormTypes, true)) {
    $errors['general'] = 'Invalid form type.';
}

if ($formType === 'contact' && empty($errors)) {
    $fullName = trim($postData['fullName'] ?? '');
    $email = trim($postData['email'] ?? '');
    $companyName = trim($postData['companyName'] ?? '');
    $phone = trim($postData['phone'] ?? '');
    $message = trim($postData['message'] ?? '');

    if ($fullName === '') $errors['fullName'] = 'Full name is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Valid email is required.';
    if ($companyName === '') $errors['companyName'] = 'Company name is required.';
    if ($phone === '') $errors['phone'] = 'Phone number is required.';
    elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) $errors['phone'] = 'Please enter a valid phone number.';
    if ($message === '') $errors['message'] = 'Message is required.';
    if ($captchaInput === '') $errors['captcha'] = 'Please enter the captcha code.';
    elseif (!captchaCheck($formType, $captchaInput)) {
        $errors['captcha'] = 'Invalid captcha. Please try again.';
        captchaClearCode($formType);
    }

    if (empty($errors)) {
        $subject = 'New Contact Us Message - ' . $fullName;

        $bodyHtml = file_get_contents(__DIR__ . '/email-template/contact_email.html');
        $bodyHtml = str_replace('{{BASE_URL}}', $baseUrl, $bodyHtml);
        $bodyHtml = str_replace('{{FULL_NAME}}', htmlspecialchars($fullName), $bodyHtml);
        $bodyHtml = str_replace('{{EMAIL}}', htmlspecialchars($email), $bodyHtml);
        $bodyHtml = str_replace('{{COMPANY}}', htmlspecialchars($companyName), $bodyHtml);
        $bodyHtml = str_replace('{{PHONE}}', htmlspecialchars($phone), $bodyHtml);
        $bodyHtml = str_replace('{{MESSAGE}}', nl2br(htmlspecialchars($message)), $bodyHtml);

        $bodyText = strip_tags($bodyHtml);

        $mailResult = sendMailToUser($targetEmail, $targetName, $subject, $bodyHtml, $bodyText);
        if ($mailResult['success']) {
            // send thank-you email to the submitter if template available and valid email provided
            $thankTemplate = __DIR__ . '/email-template/thank_you_email.html';
            if (file_exists($thankTemplate) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $userBodyHtml = file_get_contents($thankTemplate);
                $userBodyHtml = str_replace('{{BASE_URL}}', $baseUrl, $userBodyHtml);
                $userBodyHtml = str_replace('{{FULL_NAME}}', htmlspecialchars($fullName), $userBodyHtml);
                $userBodyText = strip_tags($userBodyHtml);
                $userSubject = 'Thank you for contacting The Nova AI';
                $userMailResult = sendMailToUser($email, $fullName, $userSubject, $userBodyHtml, $userBodyText);
            }

            captchaClearCode($formType);
        }
    }
} elseif ($formType === 'project' && empty($errors)) {
    $fullName = trim($postData['fullName'] ?? '');
    $email = trim($postData['email'] ?? '');
    $projectName = trim($postData['projectName'] ?? '');
    $category = trim($postData['category'] ?? '');
    $description = trim($postData['description'] ?? '');
    $projectType = trim($postData['projectType'] ?? '');

    if ($fullName === '') $errors['fullName'] = 'Full name is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Valid email is required.';
    if ($projectName === '') $errors['projectName'] = 'Project name is required.';
    if ($category === '') $errors['category'] = 'Category is required.';
    if ($description === '') $errors['description'] = 'Description is required.';
    if ($projectType === '') $errors['projectType'] = 'Project type is required.';
    if ($captchaInput === '') $errors['captcha'] = 'Please enter the captcha code.';
    elseif (!captchaCheck($formType, $captchaInput)) {
        $errors['captcha'] = 'Invalid captcha. Please try again.';
        captchaClearCode($formType);
    }

    if (empty($errors)) {
        $subject = 'New Project Request - ' . $projectName;

        $bodyHtml = file_get_contents(__DIR__ . '/email-template/project_email.html');
        $bodyHtml = str_replace('{{BASE_URL}}', $baseUrl, $bodyHtml);
        $bodyHtml = str_replace('{{PROJECT_NAME}}', htmlspecialchars($projectName), $bodyHtml);
        $bodyHtml = str_replace('{{CATEGORY}}', htmlspecialchars($category), $bodyHtml);
        $bodyHtml = str_replace('{{PROJECT_TYPE}}', htmlspecialchars($projectType), $bodyHtml);
        $bodyHtml = str_replace('{{DESCRIPTION}}', nl2br(htmlspecialchars($description)), $bodyHtml);
        // include submitter details if template supports them
        $bodyHtml = str_replace('{{FULL_NAME}}', htmlspecialchars($fullName), $bodyHtml);
        $bodyHtml = str_replace('{{EMAIL}}', htmlspecialchars($email), $bodyHtml);

        $bodyText = strip_tags($bodyHtml);

        $mailResult = sendMailToUser($targetEmail, $targetName, $subject, $bodyHtml, $bodyText);
        if ($mailResult['success']) {
            // send thank-you email to the submitter if email exists and template available
            $thankTemplate = __DIR__ . '/email-template/thank_you_email.html';
            if (file_exists($thankTemplate) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $userBodyHtml = file_get_contents($thankTemplate);
                $userBodyHtml = str_replace('{{BASE_URL}}', $baseUrl, $userBodyHtml);
                $userBodyHtml = str_replace('{{FULL_NAME}}', htmlspecialchars($fullName), $userBodyHtml);
                $userBodyText = strip_tags($userBodyHtml);
                $userSubject = 'Thank you for contacting The Nova AI';
                // send to user (do not overwrite $mailResult for admin send)
                $userMailResult = sendMailToUser($email, $fullName, $userSubject, $userBodyHtml, $userBodyText);
            }

            captchaClearCode($formType);
        }
    }
} elseif ($formType === 'schedule_demo' && empty($errors)) {
    $fullName = trim($postData['fullName'] ?? '');
    $workEmail = trim($postData['workEmail'] ?? '');
    $phone = trim($postData['phone'] ?? '');
    $companyName = trim($postData['companyName'] ?? '');
    $requirements = trim($postData['requirements'] ?? '');

    if ($fullName === '') $errors['fullName'] = 'Full name is required.';
    if ($workEmail === '' || !filter_var($workEmail, FILTER_VALIDATE_EMAIL)) $errors['workEmail'] = 'Valid work email is required.';
    if ($phone === '') $errors['phone'] = 'Phone number is required.';
    elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) $errors['phone'] = 'Please enter a valid phone number.';
    if ($companyName === '') $errors['companyName'] = 'Company name is required.';
    if ($requirements === '') $errors['requirements'] = 'Requirements are required.';
    if ($captchaInput === '') $errors['captcha'] = 'Please enter the captcha code.';
    elseif (!captchaCheck($formType, $captchaInput)) {
        $errors['captcha'] = 'Invalid captcha. Please try again.';
        captchaClearCode($formType);
    }

    if (empty($errors)) {
        $subject = 'New Demo Request - ' . $companyName;

        $bodyHtml = file_get_contents(__DIR__ . '/email-template/schedule_demo_email.html');
        $bodyHtml = str_replace('{{BASE_URL}}', $baseUrl, $bodyHtml);
        $bodyHtml = str_replace('{{FULL_NAME}}', htmlspecialchars($fullName), $bodyHtml);
        $bodyHtml = str_replace('{{WORK_EMAIL}}', htmlspecialchars($workEmail), $bodyHtml);
        $bodyHtml = str_replace('{{PHONE}}', htmlspecialchars($phone), $bodyHtml);
        $bodyHtml = str_replace('{{COMPANY}}', htmlspecialchars($companyName), $bodyHtml);
        $bodyHtml = str_replace('{{REQUIREMENTS}}', nl2br(htmlspecialchars($requirements)), $bodyHtml);

        $bodyText = strip_tags($bodyHtml);

        $mailResult = sendMailToUser($targetEmail, $targetName, $subject, $bodyHtml, $bodyText);
        if ($mailResult['success']) {
            // send thank-you email to the submitter if template available and work email valid
            $thankTemplate = __DIR__ . '/email-template/thank_you_email.html';
            if (file_exists($thankTemplate) && filter_var($workEmail, FILTER_VALIDATE_EMAIL)) {
                $userBodyHtml = file_get_contents($thankTemplate);
                $userBodyHtml = str_replace('{{BASE_URL}}', $baseUrl, $userBodyHtml);
                $userBodyHtml = str_replace('{{FULL_NAME}}', htmlspecialchars($fullName), $userBodyHtml);
                $userBodyText = strip_tags($userBodyHtml);
                $userSubject = 'Thank you for scheduling a demo with The Nova AI';
                $userMailResult = sendMailToUser($workEmail, $fullName, $userSubject, $userBodyHtml, $userBodyText);
            }

            captchaClearCode($formType);
        }
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

if (isset($mailResult) && $mailResult['success']) {
    echo json_encode(['success' => true, 'message' => 'Your message has been sent successfully!']);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'errors' => ['general' => 'Unable to send your message right now. Please try again later.'],
        'debug' => $mailResult['error'] ?? ''
    ]);
}
