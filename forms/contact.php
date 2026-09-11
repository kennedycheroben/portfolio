<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

verify_csrf();
$startedAt = filter_input(INPUT_POST, 'started_at', FILTER_VALIDATE_INT);
if (input_string($_POST, 'website') !== '' || !$startedAt || time() - $startedAt < 3) {
    redirect(site_url('contact.php?status=sent'));
}
if (!rate_limit('contact', 5, 3600)) {
    http_response_code(429);
    exit('Too many messages. Please try again later.');
}

$name = input_string($_POST, 'name');
$email = input_string($_POST, 'email');
$subject = input_string($_POST, 'subject');
$message = input_string($_POST, 'message');
$allowedSubjects = ['Full-stack web application', 'Responsive website', 'UI/UX design', 'Maintenance or improvements', 'Something else'];

if ($name === '' || text_length($name) > 80
    || !filter_var($email, FILTER_VALIDATE_EMAIL) || text_length($email) > 160
    || !in_array($subject, $allowedSubjects, true)
    || text_length($message) < 20 || text_length($message) > 3000
    || preg_match('/[\r\n]/', $email)) {
    redirect(site_url('contact.php?status=error'));
}

try {
    define('DB_OPTIONAL', true);
    require __DIR__ . '/../includes/db.php';
    $stored = false;
    $notified = false;
    if ($pdo instanceof PDO) {
        $statement = $pdo->prepare('INSERT INTO contacts (name, email, subject, message) VALUES (?, ?, ?, ?)');
        $statement->execute([$name, $email, $subject, $message]);
        $stored = true;
    }

    $smtpPassword = (string) (getenv('SMTP_PASS') ?: getenv('SMTP_PASSWORD') ?: '');
    if (getenv('SMTP_HOST') && getenv('SMTP_USER') && $smtpPassword !== '' && getenv('CONTACT_TO')) {
        require_once __DIR__ . '/../assets/vendor/php-email-form/php-email-form.php';
        $form = new PHP_Email_Form();
        $form->to = (string) getenv('CONTACT_TO');
        $form->from_name = 'Kennedy Cheroben Portfolio';
        $form->from_email = (string) getenv('SMTP_USER');
        $form->subject = 'Portfolio enquiry: ' . $subject;
        $form->smtp = ['host' => getenv('SMTP_HOST'), 'port' => (int) (getenv('SMTP_PORT') ?: 587), 'username' => getenv('SMTP_USER'), 'password' => $smtpPassword];
        $form->add_message($name, 'Name');
        $form->add_message($email, 'Reply email');
        $form->add_message($message, 'Message');
        if ($form->send() === 'OK') {
            $notified = true;
        } else {
            error_log('Portfolio SMTP notification failed. The contact was stored.');
        }
    }

    if (!$stored && !$notified) {
        throw new RuntimeException('No contact delivery service is currently available.');
    }
} catch (Throwable $exception) {
    error_log('Portfolio contact submission failed: ' . $exception->getMessage());
    redirect(site_url('contact.php?status=error'));
}

redirect(site_url('contact.php?status=sent'));
