<?php

/**
 * PHP Email Form Class - Simplified & Reliable
 * Supports SMTP with fallback to mail()
 * 
 * A clean, secure email form handler for contact forms.
 * Supports traditional form submissions and direct SMTP delivery.
 */
class PHP_Email_Form
{
    /**
     * Recipient's email address
     * @var string
     */
    public string $to = '';

    /**
     * Sender's name (from form input)
     * @var string
     */
    public string $from_name = '';

    /**
     * Sender's email address (from form input)
     * @var string
     */
    public string $from_email = '';

    /**
     * Email subject line
     * @var string
     */
    public string $subject = '';

    /**
     * Flag indicating AJAX request (for frontend validation)
     * @var bool
     */
    public bool $ajax = false;

    /**
     * SMTP configuration array
     * @var array
     */
    public array $smtp = [];

    /**
     * Message storage with label, content, and priority
     * @var array
     */
    private array $messages = [];

    /**
     * Add a message field to the email body
     * 
     * Messages are stored with priority; higher priority messages appear later
     * Typical usage: add_message($_POST['name'], 'From')
     *
     * @param string $content The field content/value
     * @param string $label The field label/name
     * @param int $priority Message priority for ordering (default: 1)
     * @return void
     */
    public function add_message(string $content, string $label, int $priority = 1): void
    {
        // Validate inputs
        if (empty($content) || empty($label)) {
            return;
        }

        $this->messages[] = [
            'label' => trim($label),
            'content' => trim($content),
            'priority' => (int) $priority
        ];
    }

    /**
     * Send the compiled email
     */
    public function send(): string
    {
        if (empty($this->to) || empty($this->from_email) || empty($this->subject)) {
            return 'Missing required fields.';
        }

        $body = $this->compile_body();
        if (empty($body)) {
            return 'No message content provided.';
        }

        $to = $this->sanitize_email($this->to);
        $from_email = $this->sanitize_email($this->from_email);
        $subject = $this->sanitize_subject($this->subject);
        $from_name = $this->sanitize_name($this->from_name);

        $sent = false;
        
        // Try SMTP first if configured
        if (!empty($this->smtp) && !empty($this->smtp['host'])) {
            $sent = $this->send_smtp($to, $from_email, $from_name, $subject, $body);
        }
        
        // Native mail is used only when SMTP has not been configured.
        if (!$sent && empty($this->smtp)) {
            $sent = $this->send_mail($to, $from_email, $from_name, $subject, $body);
        }

        if ($sent) {
            return "OK";
        }

        return 'Error: Email could not be sent. Please try again later.';
    }

    /**
     * Send email via native PHP mail() function
     *
     * @param string $to Recipient email
     * @param string $from_email Sender email
     * @param string $from_name Sender name
     * @param string $subject Email subject
     * @param string $body Email body
     * @return bool True on success, false otherwise
     */
    private function send_mail(string $to, string $from_email, string $from_name, string $subject, string $body): bool
    {
        $headers = $this->build_headers($from_name, $from_email);
        return @mail($to, $subject, $body, $headers);
    }

    /**
     * Send via SMTP
     */
    private function send_smtp(string $to, string $from_email, string $from_name, string $subject, string $body): bool
    {
        $host = $this->smtp['host'] ?? null;
        $port = (int) ($this->smtp['port'] ?? 587);
        $username = $this->smtp['username'] ?? null;
        $password = $this->smtp['password'] ?? null;

        if (!$host || !$username || !$password) {
            return false;
        }

        try {
            $protocol = ($port === 465) ? 'ssl://' : 'tcp://';
            $socket = @stream_socket_client("{$protocol}{$host}:{$port}", $errno, $errstr, 30);
            
            if (!$socket) {
                return false;
            }

            stream_set_timeout($socket, 30);
            if (!$this->smtp_response_is($socket, [220])) {
                fclose($socket);
                return false;
            }

            if (!$this->smtp_command($socket, 'EHLO ' . (gethostname() ?: 'localhost'), [250])) {
                fclose($socket);
                return false;
            }

            if ($port === 587) {
                if (!$this->smtp_command($socket, 'STARTTLS', [220])) {
                    fclose($socket);
                    return false;
                }
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)
                    || !$this->smtp_command($socket, 'EHLO ' . (gethostname() ?: 'localhost'), [250])) {
                    fclose($socket);
                    return false;
                }
            }

            if (!$this->smtp_command($socket, 'AUTH LOGIN', [334])
                || !$this->smtp_command($socket, base64_encode($username), [334])
                || !$this->smtp_command($socket, base64_encode($password), [235])
                || !$this->smtp_command($socket, "MAIL FROM:<{$from_email}>", [250])
                || !$this->smtp_command($socket, "RCPT TO:<{$to}>", [250, 251])
                || !$this->smtp_command($socket, 'DATA', [354])) {
                fclose($socket);
                return false;
            }

            $from_header = $from_name ? "{$from_name} <{$from_email}>" : $from_email;
            $message = "From: {$from_header}\r\nTo: {$to}\r\nSubject: {$subject}\r\n";
            $message .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n";
            $normalizedBody = str_replace(["\r\n", "\r"], "\n", $body);
            $normalizedBody = preg_replace('/^\./m', '..', $normalizedBody) ?? $normalizedBody;
            $message .= str_replace("\n", "\r\n", $normalizedBody) . "\r\n.\r\n";

            fwrite($socket, $message);
            $sent = $this->smtp_response_is($socket, [250]);
            $this->smtp_command($socket, 'QUIT', [221]);
            fclose($socket);

            return $sent;

        } catch (Throwable $e) {
            return false;
        }
    }

    /** @param resource $socket */
    private function smtp_command($socket, string $command, array $expectedCodes): bool
    {
        if (fwrite($socket, $command . "\r\n") === false) {
            return false;
        }

        return $this->smtp_response_is($socket, $expectedCodes);
    }

    /** @param resource $socket */
    private function smtp_response_is($socket, array $expectedCodes): bool
    {
        $lastLine = '';
        while (($line = fgets($socket, 1024)) !== false) {
            $lastLine = $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }

        $code = (int) substr($lastLine, 0, 3);
        return in_array($code, $expectedCodes, true);
    }



    /**
     * Compile all messages into formatted email body
     * 
     * Sorts messages by priority (ascending), formats as key-value pairs.
     *
     * @return string The formatted email body
     */
    private function compile_body(): string
    {
        if (empty($this->messages)) {
            return '';
        }

        // Sort messages by priority
        usort($this->messages, function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });

        $body = "New Contact Form Submission\n";
        $body .= str_repeat("=", 50) . "\n\n";

        foreach ($this->messages as $message) {
            $label = $message['label'];
            $content = $message['content'];
            $body .= "{$label}: {$content}\n";
        }

        $body .= "\n" . str_repeat("=", 50) . "\n";
        $body .= "Sent from: " . ($_SERVER['HTTP_HOST'] ?? 'Website') . "\n";
        $body .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";

        return $body;
    }

    /**
     * Build RFC 2822 compliant email headers
     *
     * @param string $from_name Sender's display name
     * @param string $from_email Sender's email address
     * @return string Formatted headers
     */
    private function build_headers(string $from_name, string $from_email): string
    {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        // Format From header
        $from = $from_name ? "{$from_name} <{$from_email}>" : $from_email;
        $headers .= "From: {$from}\r\n";
        $headers .= "Reply-To: {$from_email}\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

        return $headers;
    }

    /**
     * Sanitize email addresses to prevent header injection
     *
     * @param string $email The email address to sanitize
     * @return string Sanitized email address
     */
    private function sanitize_email(string $email): string
    {
        // Remove CRLF and potentially dangerous characters
        $email = str_replace(["\r", "\n", "%0a", "%0d"], '', $email);
        $email = str_ireplace(['bcc:', 'cc:', 'to:', 'subject:'], '', $email);
        
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }

    /**
     * Sanitize subject line to prevent header injection
     *
     * @param string $subject The subject line
     * @return string Sanitized subject
     */
    private function sanitize_subject(string $subject): string
    {
        // Remove CRLF characters that could inject headers
        $subject = str_replace(["\r", "\n", "%0a", "%0d"], '', $subject);
        
        return substr(trim($subject), 0, 100);
    }

    /**
     * Sanitize sender name for safe header inclusion
     *
     * @param string $name The sender's name
     * @return string Sanitized name
     */
    private function sanitize_name(string $name): string
    {
        // Remove CRLF and trim
        $name = str_replace(["\r", "\n", "%0a", "%0d"], '', $name);
        
        return substr(htmlspecialchars(trim($name), ENT_QUOTES, 'UTF-8'), 0, 100);
    }
}
