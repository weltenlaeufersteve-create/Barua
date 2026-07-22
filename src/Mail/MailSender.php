<?php

namespace Barua\Mail;

use Barua\Accounts\AccountRepository;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class MailSender
{
    /**
     * Send a message from the given account over SMTP.
     *
     * @param array $account  full account row (incl. encrypted smtp password)
     * @param array $msg      keys: to, cc, bcc, subject, body_html, body_plain, in_reply_to, references
     * @return array{ok:bool, error?:string}
     */
    public static function send(array $account, array $msg): array
    {
        $mailer = new PHPMailer(true);
        try {
            $mailer->isSMTP();
            $mailer->Host = $account['smtp_host'];
            $mailer->Port = (int) $account['smtp_port'];
            $mailer->SMTPAuth = true;
            $mailer->Username = $account['smtp_username'];
            $mailer->Password = AccountRepository::decryptSmtpPassword($account);

            switch ($account['smtp_encryption']) {
                case 'ssl':
                    $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // implicit TLS (465)
                    break;
                case 'tls':
                    $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // 587
                    break;
                default:
                    $mailer->SMTPSecure = false;
                    $mailer->SMTPAutoTLS = false;
            }

            $mailer->CharSet = 'UTF-8';
            $mailer->setFrom($account['email'], $account['label']);

            foreach (self::parseAddresses($msg['to'] ?? '') as $addr) {
                $mailer->addAddress($addr);
            }
            foreach (self::parseAddresses($msg['cc'] ?? '') as $addr) {
                $mailer->addCC($addr);
            }
            foreach (self::parseAddresses($msg['bcc'] ?? '') as $addr) {
                $mailer->addBCC($addr);
            }

            if (empty($mailer->getToAddresses()) && empty($mailer->getCcAddresses()) && empty($mailer->getBccAddresses())) {
                return ['ok' => false, 'error' => 'No recipients.'];
            }

            $mailer->Subject = $msg['subject'] ?? '';

            $html = trim($msg['body_html'] ?? '');
            $plain = trim($msg['body_plain'] ?? '');
            if ($html !== '') {
                $mailer->isHTML(true);
                $mailer->Body = $html;
                $mailer->AltBody = $plain !== '' ? $plain : HtmlMailRenderer::toText($html);
            } else {
                $mailer->isHTML(false);
                $mailer->Body = $plain;
            }

            // File attachments (each: path + name + mime), stored server-side while composing.
            foreach ($msg['attachments'] ?? [] as $att) {
                if (!empty($att['path']) && is_file($att['path'])) {
                    $mailer->addAttachment(
                        $att['path'],
                        $att['name'] ?? basename($att['path']),
                        PHPMailer::ENCODING_BASE64,
                        (string) ($att['mime'] ?? '')
                    );
                }
            }

            // Threading headers so replies stay in-thread for the recipient.
            if (!empty($msg['in_reply_to'])) {
                $mailer->addCustomHeader('In-Reply-To', $msg['in_reply_to']);
            }
            if (!empty($msg['references'])) {
                $mailer->addCustomHeader('References', $msg['references']);
            }

            $mailer->send();

            // Append a copy to the account's IMAP Sent folder so it shows in every client
            // (webmail, phone, …), not just Barua. Non-fatal if it fails — the mail is sent.
            $appended = self::appendToSent($account, $mailer->getSentMIMEMessage());

            return ['ok' => true, 'appended_to_sent' => $appended];
        } catch (PHPMailerException $e) {
            return ['ok' => false, 'error' => $mailer->ErrorInfo ?: $e->getMessage()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** APPEND the raw MIME to the account's IMAP Sent folder, flagged \Seen. Returns success. */
    private static function appendToSent(array $account, string $rawMime): bool
    {
        try {
            $client = SyncService::makeClient($account);
            $client->connect();
            $sent = FolderResolver::find($client, 'sent');
            if ($sent === null) {
                $client->disconnect();
                return false;
            }
            $sent->appendMessage($rawMime, ['Seen']);
            $client->disconnect();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Split a comma/semicolon separated address string into trimmed, non-empty parts. */
    public static function parseAddresses(string $raw): array
    {
        $parts = preg_split('/[,;]+/', $raw) ?: [];
        $parts = array_map('trim', $parts);
        return array_values(array_filter($parts, fn($p) => $p !== ''));
    }
}
