<?php
namespace App\Services;

/**
 * Minimal zero-dependency SMTP mailer.
 *
 * Reads configuration from environment variables (set via .env):
 *   MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD,
 *   MAIL_FROM, MAIL_FROM_NAME, MAIL_ENCRYPTION (tls|ssl|none)
 *
 * Supports STARTTLS (port 587) and direct SSL (port 465).
 * On failure, logs to storage/logs/mail.log and returns false — never throws.
 */
class MailService
{
    public static function send(
        string $to,
        string $toName,
        string $subject,
        string $htmlBody
    ): bool {
        $host       = $_ENV['MAIL_HOST']       ?? '';
        $port       = (int) ($_ENV['MAIL_PORT']       ?? 587);
        $username   = $_ENV['MAIL_USERNAME']   ?? '';
        $password   = $_ENV['MAIL_PASSWORD']   ?? '';
        $from       = $_ENV['MAIL_FROM']       ?? $username;
        $fromName   = $_ENV['MAIL_FROM_NAME']  ?? 'SSDACMIS';
        $encryption = strtolower($_ENV['MAIL_ENCRYPTION'] ?? 'tls');

        if ($host === '' || $username === '' || $password === '') {
            self::log("MAIL_* env vars not configured — skipped sending to {$to}.");
            return false;
        }

        try {
            $socket = self::connect($host, $port, $encryption);

            self::read($socket);

            $ehlo = gethostname() ?: 'localhost';
            self::cmd($socket, "EHLO {$ehlo}", '250');

            if ($encryption === 'tls') {
                self::cmd($socket, 'STARTTLS', '220');
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('STARTTLS negotiation failed.');
                }
                self::cmd($socket, "EHLO {$ehlo}", '250');
            }

            self::cmd($socket, 'AUTH LOGIN', '334');
            self::cmd($socket, base64_encode($username), '334');
            self::cmd($socket, base64_encode($password), '235');

            self::cmd($socket, "MAIL FROM:<{$from}>", '250');
            self::cmd($socket, "RCPT TO:<{$to}>", '250');
            self::cmd($socket, 'DATA', '354');

            $body = self::buildRaw($from, $fromName, $to, $toName, $subject, $htmlBody);
            fwrite($socket, $body . "\r\n.\r\n");
            self::read($socket, '250');

            fwrite($socket, "QUIT\r\n");
            fclose($socket);

            self::log("OK  to={$to} subject=\"{$subject}\"");
            return true;

        } catch (\Throwable $e) {
            self::log("ERR to={$to} subject=\"{$subject}\" error=" . $e->getMessage());
            return false;
        }
    }

    /* ------------------------------------------------------------------ */

    /** @return resource */
    private static function connect(string $host, int $port, string $encryption)
    {
        $address = $encryption === 'ssl'
            ? "ssl://{$host}:{$port}"
            : "tcp://{$host}:{$port}";

        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false,
            ],
        ]);

        $socket = @stream_socket_client($address, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $ctx);
        if (!$socket) {
            throw new \RuntimeException("Cannot connect to {$address}: {$errstr} ({$errno})");
        }
        stream_set_timeout($socket, 30);
        return $socket;
    }

    /** Write a command and verify the expected reply code. */
    private static function cmd($socket, string $command, string $expect): string
    {
        fwrite($socket, $command . "\r\n");
        return self::read($socket, $expect);
    }

    /** Read a (possibly multi-line) SMTP response, optionally asserting a code. */
    private static function read($socket, string $expect = ''): string
    {
        $response = '';
        while (!feof($socket)) {
            $line = fgets($socket, 512);
            if ($line === false) break;
            $response .= $line;
            // RFC 5321: last continuation line has a space at position 3, not '-'
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        if ($expect !== '' && !str_starts_with(trim($response), $expect)) {
            throw new \RuntimeException("SMTP unexpected response (wanted {$expect}): {$response}");
        }
        return $response;
    }

    /** Build a simple multipart/alternative MIME message. */
    private static function buildRaw(
        string $from,
        string $fromName,
        string $to,
        string $toName,
        string $subject,
        string $htmlBody
    ): string {
        $boundary = '----=_Part_' . md5(uniqid((string) mt_rand(), true));
        $plain    = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody) ?? $htmlBody);

        $lines   = [];
        $lines[] = 'MIME-Version: 1.0';
        $lines[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $lines[] = 'From: ' . self::encodeHeader($fromName) . ' <' . $from . '>';
        $lines[] = 'To: '   . self::encodeHeader($toName)   . ' <' . $to   . '>';
        $lines[] = 'Subject: ' . self::encodeHeader($subject);
        $lines[] = 'Date: ' . date('r');
        $lines[] = 'Message-ID: <' . uniqid('', true) . '@' . (gethostname() ?: 'localhost') . '>';
        $lines[] = '';
        $lines[] = '--' . $boundary;
        $lines[] = 'Content-Type: text/plain; charset=UTF-8';
        $lines[] = 'Content-Transfer-Encoding: base64';
        $lines[] = '';
        $lines[] = chunk_split(base64_encode($plain));
        $lines[] = '--' . $boundary;
        $lines[] = 'Content-Type: text/html; charset=UTF-8';
        $lines[] = 'Content-Transfer-Encoding: base64';
        $lines[] = '';
        $lines[] = chunk_split(base64_encode($htmlBody));
        $lines[] = '--' . $boundary . '--';

        // RFC 5321 dot-stuffing: lines starting with '.' must be doubled
        $raw = implode("\r\n", $lines);
        $raw = preg_replace('/^\.$/m', '..', $raw) ?? $raw;

        return $raw;
    }

    private static function encodeHeader(string $value): string
    {
        if (mb_detect_encoding($value, 'ASCII', true)) {
            return $value;
        }
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function log(string $msg): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents(
            $dir . '/mail.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
