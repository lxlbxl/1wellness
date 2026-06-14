<?php

class Mailer
{
    private $db;
    private $settings;
    private $useSmtp;
    private $host;
    private $port;
    private $username;
    private $password;
    private $fromEmail;
    private $fromName;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->loadSettings();
    }

    private function loadSettings()
    {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%'");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $config = [];
        foreach ($rows as $row) {
            $config[$row['setting_key']] = $row['setting_value'];
        }

        $this->host = $config['smtp_host'] ?? '';
        $this->port = $config['smtp_port'] ?? 587;
        $this->username = $config['smtp_username'] ?? '';
        $this->password = $config['smtp_password'] ?? '';
        $this->fromEmail = $config['smtp_from_email'] ?? 'noreply@1wellness.club';
        $this->fromName = $config['smtp_from_name'] ?? '1wellness';

        $this->useSmtp = !empty($this->host) && !empty($this->username);
    }

    public function setConfig($config)
    {
        $this->host = $config['smtp_host'] ?? $this->host;
        $this->port = $config['smtp_port'] ?? $this->port;
        $this->username = $config['smtp_username'] ?? $this->username;
        $this->password = $config['smtp_password'] ?? $this->password;
        $this->fromEmail = $config['smtp_from_email'] ?? $this->fromEmail;
        $this->fromName = $config['smtp_from_name'] ?? $this->fromName;

        $this->useSmtp = !empty($this->host) && !empty($this->username);
    }

    public function send($to, $subject, $body, $isHtml = true, $attachmentPath = null, $attachmentName = null)
    {
        if ($this->useSmtp && $this->dkimConfigured()) {
            return $this->sendViaPhpMailer($to, $subject, $body, $isHtml, $attachmentPath, $attachmentName);
        }
        if ($this->useSmtp) {
            return $this->sendSmtp($to, $subject, $body, $isHtml, $attachmentPath, $attachmentName);
        }
        return $this->sendNative($to, $subject, $body, $isHtml, $attachmentPath, $attachmentName);
    }

    private function dkimConfigured(): bool
    {
        $keyPath = defined('DKIM_PRIVATE_KEY_PATH') ? DKIM_PRIVATE_KEY_PATH : '';
        return $keyPath !== '' && file_exists($keyPath) && defined('DKIM_DOMAIN') && DKIM_DOMAIN !== '';
    }

    private function sendViaPhpMailer($to, $subject, $body, $isHtml, $attachmentPath, $attachmentName): bool
    {
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (!file_exists($autoload)) {
            return $this->sendSmtp($to, $subject, $body, $isHtml, $attachmentPath, $attachmentName);
        }
        require_once $autoload;

        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $this->host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->username;
            $mail->Password   = $this->password;
            $mail->SMTPSecure = ($this->port == 465)
                ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) $this->port;

            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($to);
            $mail->Subject = $subject;

            if ($isHtml) {
                $mail->isHTML(true);
                $mail->Body    = $body;
                $mail->AltBody = strip_tags($body);
            } else {
                $mail->Body = $body;
            }

            if ($attachmentPath && file_exists($attachmentPath)) {
                $mail->addAttachment($attachmentPath, $attachmentName ?: basename($attachmentPath));
            }

            // DKIM signing
            $mail->DKIM_domain     = DKIM_DOMAIN;
            $mail->DKIM_private    = DKIM_PRIVATE_KEY_PATH;
            $mail->DKIM_selector   = defined('DKIM_SELECTOR') ? DKIM_SELECTOR : 'mail';
            $mail->DKIM_passphrase = '';
            $mail->DKIM_identity   = $this->fromEmail;

            foreach ($this->extraHeaders as $header) {
                [$name, $value] = explode(':', $header, 2);
                $mail->addCustomHeader(trim($name), trim($value));
            }

            return $mail->send();
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            error_log('Mailer DKIM send error: ' . $e->getMessage());
            return false;
        }
    }

    private function sendNative($to, $subject, $body, $isHtml, $attachmentPath, $attachmentName)
    {
        $headers = "From: {$this->fromName} <{$this->fromEmail}>\r\n";
        $headers .= "Reply-To: {$this->fromEmail}\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        if ($attachmentPath && file_exists($attachmentPath)) {
            $boundary = md5(time());
            $headers .= "\r\nMIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";

            // Message Body
            $message = "--{$boundary}\r\n";
            $message .= "Content-Type: " . ($isHtml ? "text/html" : "text/plain") . "; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $message .= $body . "\r\n";

            // Attachment
            $filename = $attachmentName ?: basename($attachmentPath);
            $fileContent = chunk_split(base64_encode(file_get_contents($attachmentPath)));
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: application/octet-stream; name=\"{$filename}\"\r\n";
            $message .= "Content-Description: {$filename}\r\n";
            $message .= "Content-Disposition: attachment; filename=\"{$filename}\"; size=" . filesize($attachmentPath) . ";\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $message .= $fileContent . "\r\n";
            $message .= "--{$boundary}--";

            return mail($to, $subject, $message, $headers);
        } else {
            if ($isHtml) {
                $headers .= "\r\nMIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            }
            return mail($to, $subject, $body, $headers);
        }
    }

    private $lastError = '';
    private $extraHeaders = [];

    public function getLastError()
    {
        return $this->lastError;
    }

    /** Add an RFC 2822 header (name: value) included in every send call. */
    public function addHeader(string $name, string $value): void
    {
        $this->extraHeaders[] = rtrim($name, ':') . ': ' . $value;
    }

    private function sendSmtp($to, $subject, $body, $isHtml, $attachmentPath, $attachmentName)
    {
        // Simple Socket-based SMTP Client to avoid dependencies
        try {
            $host = $this->host;
            // Handle Implicit SSL for port 465
            if ($this->port == 465) {
                $host = "ssl://" . $this->host;
            }

            $socket = fsockopen($host, $this->port, $errno, $errstr, 30);
            if (!$socket) {
                $this->lastError = "SMTP Connect Failed: $errstr ($errno)";
                error_log($this->lastError);
                return false;
            }

            if (!$this->serverCmd($socket, "220")) {
                $this->lastError = "SMTP Greeting Failed (Expected 220)";
                return false;
            }

            $this->putCmd($socket, "EHLO " . $_SERVER['SERVER_NAME']);
            if (!$this->serverCmd($socket, "250")) {
                $this->lastError = "EHLO Failed";
                return false;
            }

            // Explicit SSL (TLS) for port 587
            if ($this->port == 587) {
                $this->putCmd($socket, "STARTTLS");
                if ($this->serverCmd($socket, "220")) {
                    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                        $this->lastError = "TLS Negotiation Failed";
                        return false;
                    }
                    $this->putCmd($socket, "EHLO " . $_SERVER['SERVER_NAME']);
                    if (!$this->serverCmd($socket, "250")) {
                        $this->lastError = "EHLO after TLS Failed";
                        return false;
                    }
                } else {
                    $this->lastError = "STARTTLS Failed (Expected 220)";
                    return false;
                }
            }

            $this->putCmd($socket, "AUTH LOGIN");
            if (!$this->serverCmd($socket, "334")) {
                $this->lastError = "AUTH LOGIN Failed";
                return false;
            }

            $this->putCmd($socket, base64_encode($this->username));
            if (!$this->serverCmd($socket, "334")) {
                $this->lastError = "Username Rejected";
                return false;
            }

            $this->putCmd($socket, base64_encode($this->password));
            if (!$this->serverCmd($socket, "235")) {
                $this->lastError = "Password Rejected / Auth Failed";
                return false;
            }

            $this->putCmd($socket, "MAIL FROM: <{$this->fromEmail}>");
            if (!$this->serverCmd($socket, "250")) {
                $this->lastError = "MAIL FROM Rejected";
                return false;
            }

            $this->putCmd($socket, "RCPT TO: <$to>");
            if (!$this->serverCmd($socket, "250")) {
                $this->lastError = "RCPT TO Rejected";
                return false;
            }

            $this->putCmd($socket, "DATA");
            if (!$this->serverCmd($socket, "354")) {
                $this->lastError = "DATA Command Rejected";
                return false;
            }

            $headerStr = "Date: " . date("r") . "\r\n";
            $headerStr .= "To: <$to>\r\n";
            $headerStr .= "From: {$this->fromName} <{$this->fromEmail}>\r\n";
            $headerStr .= "Subject: $subject\r\n";
            $headerStr .= "MIME-Version: 1.0\r\n";

            if ($attachmentPath && file_exists($attachmentPath)) {
                $boundary = md5(time());
                $headerStr .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n";

                $content = "--{$boundary}\r\n";
                $content .= "Content-Type: " . ($isHtml ? "text/html" : "text/plain") . "; charset=UTF-8\r\n";
                $content .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
                $content .= $body . "\r\n";

                $filename = $attachmentName ?: basename($attachmentPath);
                $fileContent = chunk_split(base64_encode(file_get_contents($attachmentPath)));
                $content .= "--{$boundary}\r\n";
                $content .= "Content-Type: application/octet-stream; name=\"{$filename}\"\r\n";
                $content .= "Content-Description: {$filename}\r\n";
                $content .= "Content-Disposition: attachment; filename=\"{$filename}\"; size=" . filesize($attachmentPath) . ";\r\n";
                $content .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $content .= $fileContent . "\r\n";
                $content .= "--{$boundary}--";
            } else {
                if ($isHtml) {
                    $headerStr .= "Content-Type: text/html; charset=UTF-8\r\n";
                }
                $headerStr .= "\r\n";
                $content = $body;
            }

            $this->putCmd($socket, $headerStr . $content . "\r\n.\r\n");
            if (!$this->serverCmd($socket, "250")) {
                $this->lastError = "Message Body Rejected";
                return false;
            }

            $this->putCmd($socket, "QUIT");
            fclose($socket);

            return true;

        } catch (Exception $e) {
            $this->lastError = "SMTP Exception: " . $e->getMessage();
            error_log($this->lastError);
            return false;
        }
    }

    private function putCmd($socket, $cmd)
    {
        fwrite($socket, $cmd . "\r\n");
    }

    private function serverCmd($socket, $expected)
    {
        $response = "";
        while ($str = fgets($socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == " ") {
                break;
            }
        }

        if (substr($response, 0, 3) != $expected) {
            // error_log("SMTP Unexpected: $response (Expected $expected)"); // Verbose debug
            return false;
        }
        return true;
    }
}
