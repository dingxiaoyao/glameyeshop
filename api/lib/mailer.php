<?php
// ============================================================
// Mailer - 轻量邮件发送
// 优先级:配了 SMTP relay → 直连 SMTP socket;否则降级 mail();都失败则 error_log。
// site_settings 里读取(全部可选,留空就用 mail()):
//   smtp_host, smtp_port (默认 587), smtp_user, smtp_pass,
//   smtp_secure (tls / ssl / 空), email_from_address, email_from_name,
//   admin_email (新留言通知发到哪里)
// 也在 email_log 表里记一份(若表存在),方便排查投递问题。
// ============================================================

class Mailer
{
    /** 读 site_settings 缓存到内存 */
    private static ?array $cfg = null;

    public static function loadConfig(): array
    {
        if (self::$cfg !== null) return self::$cfg;
        $defaults = [
            'smtp_host' => '', 'smtp_port' => '587', 'smtp_user' => '', 'smtp_pass' => '',
            'smtp_secure' => 'tls',  // tls / ssl / ''
            'email_from_address' => 'noreply@glameyeshop.com',
            'email_from_name'    => 'GlamEye Support',
            'admin_email'        => '',
        ];
        try {
            $db = getDb();
            $stmt = $db->query("SELECT `key`, `value` FROM site_settings
                                WHERE `key` IN ('smtp_host','smtp_port','smtp_user','smtp_pass','smtp_secure',
                                                 'email_from_address','email_from_name','admin_email')");
            while ($r = $stmt->fetch()) {
                if ($r['value'] !== '' && $r['value'] !== null) $defaults[$r['key']] = $r['value'];
            }
        } catch (Throwable $e) {
            error_log('[mailer] config load: ' . $e->getMessage());
        }
        return self::$cfg = $defaults;
    }

    /**
     * 发邮件。html 自动转 text 兜底。
     * @return array{ok:bool, mode:string, error?:string}
     */
    public static function send(string $toEmail, string $toName, string $subject, string $htmlBody, ?string $replyTo = null): array
    {
        $toEmail = trim($toEmail);
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'mode' => 'none', 'error' => 'invalid recipient'];
        }
        $cfg = self::loadConfig();
        $mode = $cfg['smtp_host'] ? 'smtp' : 'mail';

        $textBody = self::htmlToText($htmlBody);
        $fromAddr = $cfg['email_from_address'];
        $fromName = $cfg['email_from_name'];

        $result = ['ok' => false, 'mode' => $mode];
        try {
            if ($mode === 'smtp') {
                $result = self::sendViaSmtp($cfg, $toEmail, $toName, $subject, $htmlBody, $textBody, $replyTo);
            } else {
                $result = self::sendViaMailFunction($fromAddr, $fromName, $toEmail, $toName, $subject, $htmlBody, $textBody, $replyTo);
            }
        } catch (Throwable $e) {
            $result = ['ok' => false, 'mode' => $mode, 'error' => $e->getMessage()];
        }

        // 记 email_log(可选)
        try {
            $db = getDb();
            $db->prepare(
                "INSERT INTO email_log (to_email, subject, mode, ok, error, created_at)
                 VALUES (:to, :sub, :mode, :ok, :err, NOW())"
            )->execute([
                ':to' => $toEmail, ':sub' => $subject, ':mode' => $result['mode'],
                ':ok' => $result['ok'] ? 1 : 0, ':err' => $result['error'] ?? null,
            ]);
        } catch (Throwable $e) {
            // 表没建也不报错,fallback 到 error_log
            if (!$result['ok']) {
                error_log("[mailer] FAIL to=$toEmail subject=$subject mode={$result['mode']} err=" . ($result['error'] ?? ''));
            }
        }
        return $result;
    }

    private static function sendViaMailFunction(string $fromAddr, string $fromName, string $to, string $toName, string $subject, string $html, string $text, ?string $replyTo): array
    {
        $boundary = '_b_' . bin2hex(random_bytes(8));
        $headers   = [];
        $headers[] = 'From: ' . self::formatAddress($fromName, $fromAddr);
        if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $headers[] = 'Reply-To: ' . $replyTo;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $headers[] = 'X-Mailer: GlamEye/1.0';

        $body  = "--$boundary\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $text . "\r\n\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $html . "\r\n\r\n";
        $body .= "--$boundary--\r\n";

        $ok = @mail($to, self::encodeSubject($subject), $body, implode("\r\n", $headers));
        return ['ok' => (bool)$ok, 'mode' => 'mail', 'error' => $ok ? null : 'mail() returned false'];
    }

    /**
     * 直连 SMTP socket。支持:
     *  - 587 + STARTTLS(默认)
     *  - 465 + implicit TLS(smtp_secure=ssl)
     *  - 25 + 不加密(只在内网 / 开发用)
     */
    private static function sendViaSmtp(array $cfg, string $to, string $toName, string $subject, string $html, string $text, ?string $replyTo): array
    {
        $host = $cfg['smtp_host'];
        $port = (int)($cfg['smtp_port'] ?: 587);
        $user = $cfg['smtp_user']; $pass = $cfg['smtp_pass'];
        $secure = strtolower($cfg['smtp_secure'] ?? '');
        $fromAddr = $cfg['email_from_address']; $fromName = $cfg['email_from_name'];

        $hostPrefix = ($secure === 'ssl') ? 'ssl://' : '';
        $errno = 0; $errstr = '';
        $sock = @stream_socket_client($hostPrefix . $host . ':' . $port, $errno, $errstr, 12);
        if (!$sock) return ['ok' => false, 'mode' => 'smtp', 'error' => "connect failed: $errstr"];
        stream_set_timeout($sock, 12);

        $expect = function (int $code) use (&$sock) {
            $line = ''; $resp = '';
            while (!feof($sock)) {
                $line = fgets($sock, 1024);
                if ($line === false) break;
                $resp .= $line;
                if (preg_match('/^\d{3} /', $line)) break;
            }
            $actual = (int)substr(ltrim($resp), 0, 3);
            return [$actual === $code, $resp];
        };
        $send = function (string $cmd) use (&$sock) { fwrite($sock, $cmd . "\r\n"); };

        // 接欢迎 220
        [$ok, $resp] = $expect(220);
        if (!$ok) { fclose($sock); return ['ok' => false, 'mode' => 'smtp', 'error' => "banner: $resp"]; }

        $localHost = gethostname() ?: 'glameyeshop.com';
        $send("EHLO $localHost"); [$ok, $resp] = $expect(250);
        if (!$ok) { fclose($sock); return ['ok' => false, 'mode' => 'smtp', 'error' => "EHLO: $resp"]; }

        // STARTTLS(587)
        if ($secure === 'tls' && $port !== 465) {
            $send('STARTTLS'); [$ok, $resp] = $expect(220);
            if (!$ok) { fclose($sock); return ['ok' => false, 'mode' => 'smtp', 'error' => "STARTTLS: $resp"]; }
            if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($sock); return ['ok' => false, 'mode' => 'smtp', 'error' => 'TLS handshake failed'];
            }
            $send("EHLO $localHost"); [$ok, $resp] = $expect(250);
            if (!$ok) { fclose($sock); return ['ok' => false, 'mode' => 'smtp', 'error' => "EHLO after TLS: $resp"]; }
        }

        // AUTH LOGIN
        if ($user && $pass) {
            $send('AUTH LOGIN'); [$ok, $resp] = $expect(334);
            if (!$ok) { fclose($sock); return ['ok' => false, 'mode' => 'smtp', 'error' => "AUTH: $resp"]; }
            $send(base64_encode($user)); [$ok, $resp] = $expect(334);
            if (!$ok) { fclose($sock); return ['ok' => false, 'mode' => 'smtp', 'error' => "AUTH user: $resp"]; }
            $send(base64_encode($pass)); [$ok, $resp] = $expect(235);
            if (!$ok) { fclose($sock); return ['ok' => false, 'mode' => 'smtp', 'error' => "AUTH pass: $resp"]; }
        }

        $send("MAIL FROM:<$fromAddr>"); [$ok, $resp] = $expect(250);
        if (!$ok) { fclose($sock); return ['ok' => false, 'mode' => 'smtp', 'error' => "MAIL FROM: $resp"]; }
        $send("RCPT TO:<$to>"); [$ok, $resp] = $expect(250);
        if (!$ok) { fclose($sock); return ['ok' => false, 'mode' => 'smtp', 'error' => "RCPT TO: $resp"]; }
        $send('DATA'); [$ok, $resp] = $expect(354);
        if (!$ok) { fclose($sock); return ['ok' => false, 'mode' => 'smtp', 'error' => "DATA: $resp"]; }

        $boundary = '_b_' . bin2hex(random_bytes(8));
        $headers  = "From: " . self::formatAddress($fromName, $fromAddr) . "\r\n";
        $headers .= "To: " . self::formatAddress($toName, $to) . "\r\n";
        if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $headers .= "Reply-To: <$replyTo>\r\n";
        $headers .= "Subject: " . self::encodeSubject($subject) . "\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "Message-ID: <" . bin2hex(random_bytes(12)) . "@glameyeshop.com>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";

        $body  = "--$boundary\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $text . "\r\n\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $html . "\r\n\r\n";
        $body .= "--$boundary--\r\n";

        // 转义首字符 .(SMTP 协议规定)
        $payload = $headers . "\r\n" . preg_replace('/^\./m', '..', $body);
        $send($payload);
        $send('.');
        [$ok, $resp] = $expect(250);
        $send('QUIT'); fclose($sock);
        return $ok ? ['ok' => true, 'mode' => 'smtp', 'error' => null]
                   : ['ok' => false, 'mode' => 'smtp', 'error' => "DATA end: $resp"];
    }

    private static function formatAddress(string $name, string $email): string
    {
        $name = trim($name);
        if ($name === '') return "<$email>";
        // RFC 2047 编码非 ASCII 名字
        if (preg_match('/[^\x20-\x7E]/', $name)) {
            $name = '=?UTF-8?B?' . base64_encode($name) . '?=';
        } else {
            $name = '"' . str_replace('"', '\"', $name) . '"';
        }
        return "$name <$email>";
    }

    private static function encodeSubject(string $s): string
    {
        if (preg_match('/[^\x20-\x7E]/', $s)) {
            return '=?UTF-8?B?' . base64_encode($s) . '?=';
        }
        return $s;
    }

    private static function htmlToText(string $html): string
    {
        $t = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $html);
        $t = preg_replace('/<\/p\s*>/i', "\n\n", $t);
        $t = preg_replace('/<a [^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/i', '$2 ($1)', $t);
        $t = strip_tags($t);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\n{3,}/', "\n\n", $t));
    }
}
