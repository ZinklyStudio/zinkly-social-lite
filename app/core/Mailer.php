<?php
/**
 * Zinkly Social Lite - e-mail.
 * Two ways to send: SMTP (recommended: your mailbox or a mail service; plain sockets, no library needed)
 * or PHP mail() (works on many shared hosts, but often lands in spam).
 */
final class Mailer
{
    private const TIMEOUT = 15;

    /** @var resource|null */
    private $sock = null;
    private array $log = [];

    public static function send(string $to, string $subject, string $text): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $cfg = self::config();
        try {
            if ($cfg['method'] === 'off') {
                throw new RuntimeException(t('mail.err_off'));
            }
            if (!filter_var($cfg['from'], FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException(t('mail.err_from') . ' (' . $cfg['from'] . ')');
            }
            if ($cfg['method'] === 'smtp') {
                (new self())->deliver($cfg, $to, $subject, $text);
            } elseif (!@mail($to, self::encode($subject), $text, implode("\r\n", self::headers($cfg, false)), self::envelope($cfg['from']))) {
                throw new RuntimeException(t('mail.err_php'));
            }
            setting_set('mail_last_error', null);
            setting_set('mail_last_ok', (string) time());
            return true;
        } catch (Throwable $e) {
            setting_set('mail_last_error', date('Y-m-d H:i') . ' - ' . mb_substr($e->getMessage(), 0, 400));
            @file_put_contents(ZL_ROOT . '/data/error.log', date('c') . ' [mail] to ' . $to . ': ' . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
            return false;
        }
    }

    /** The saved settings, or the ones typed in Admin › Settings when testing before saving. */
    public static function config(?array $in = null): array
    {
        $g = fn (string $k, string $def = '') => $in !== null && array_key_exists($k, $in) ? trim((string) $in[$k]) : (string) setting($k, $def);
        $from = $g('mail_from');
        $auto = $from === '';
        if ($auto) {
            $host = preg_replace('/^www\./', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $from = 'no-reply@' . preg_replace('/:\d+$/', '', $host);
        }
        $enc = $g('smtp_encryption', 'tls');
        $pass = $g('smtp_pass');
        if ($in !== null && $pass === '') {
            $pass = (string) setting('smtp_pass', ''); // the field stays empty on the page; empty means "keep the saved one"
        }
        return [
            'method' => in_array($g('mail_method', 'php'), ['smtp', 'php', 'off'], true) ? $g('mail_method', 'php') : 'php',
            'host' => strtolower($g('smtp_host')),
            'port' => (int) $g('smtp_port', '587') ?: ($enc === 'ssl' ? 465 : 587),
            'enc' => in_array($enc, ['tls', 'ssl', 'none'], true) ? $enc : 'tls',
            'user' => $g('smtp_user'),
            'pass' => $pass,
            'from' => $from,
            'from_auto' => $auto,
            'name' => str_replace(["\r", "\n", '"'], '', $g('mail_from_name') !== '' ? $g('mail_from_name') : site_name()),
        ];
    }

    /**
     * Checks the settings step by step without sending anything (or sends a real test email when $to is given).
     * Returns ['ok' => bool, 'message' => string, 'checks' => [[label, ok|warn|fail, info], ...]].
     */
    public static function test(array $cfg, ?string $to = null): array
    {
        $checks = [];
        $done = function (bool $ok, string $msg) use (&$checks) {
            return ['ok' => $ok, 'message' => $msg, 'checks' => $checks];
        };
        if (!filter_var($cfg['from'], FILTER_VALIDATE_EMAIL)) {
            $checks[] = [t('mail.chk_from'), 'fail', $cfg['from']];
            return $done(false, !empty($cfg['from_auto']) ? t('mail.err_from_auto', ['from' => $cfg['from']]) : t('mail.err_from'));
        }
        $checks[] = [t('mail.chk_from'), 'ok', $cfg['name'] . ' <' . $cfg['from'] . '>'];

        if ($cfg['method'] === 'off') {
            $checks[] = [t('mail.chk_method'), 'warn', t('mail.method_off')];
            return $done(false, t('mail.err_off'));
        }
        if ($cfg['method'] === 'php') {
            $fn = function_exists('mail') && !in_array('mail', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true);
            $checks[] = [t('mail.chk_php'), $fn ? 'ok' : 'fail', $fn ? (ini_get('sendmail_path') ?: 'mail()') : t('mail.php_disabled')];
            if (!$fn) {
                return $done(false, t('mail.err_php_disabled'));
            }
            if ($to === null) {
                $checks[] = [t('mail.chk_delivery'), 'warn', t('mail.php_unknown')];
                return $done(true, t('mail.php_ready'));
            }
            $ok = @mail($to, self::encode(t('mail.test_subject', ['site' => site_name()])), t('mail.test_body', ['site' => site_name(), 'method' => 'PHP mail()']),
                implode("\r\n", self::headers($cfg, false)), self::envelope($cfg['from']));
            $checks[] = [t('mail.chk_sent'), $ok ? 'ok' : 'fail', $to];
            return $done($ok, $ok ? t('mail.sent_php', ['to' => $to]) : t('mail.err_php'));
        }

        // SMTP, one step at a time, so the admin sees exactly where it stops
        $m = new self();
        try {
            $m->steps($cfg, $checks, $to);
        } catch (MailStepError $e) {
            $checks[] = [$e->step, 'fail', $e->getMessage()];
            $m->quit();
            return $done(false, $e->hint);
        }
        return $done(true, $to === null ? t('mail.smtp_ready') : t('mail.sent_smtp', ['to' => $to]));
    }

    /* ---------- SMTP ---------- */

    private function deliver(array $cfg, string $to, string $subject, string $text): void
    {
        $checks = [];
        try {
            $this->steps($cfg, $checks, $to, $subject, $text);
        } catch (MailStepError $e) {
            $this->quit();
            throw new RuntimeException($e->step . ': ' . $e->getMessage() . ' ' . $e->hint);
        }
    }

    private function steps(array $cfg, array &$checks, ?string $to, ?string $subject = null, ?string $text = null): void
    {
        if ($cfg['host'] === '') {
            throw new MailStepError(t('mail.chk_server'), t('mail.no_host'), t('mail.hint_host'));
        }
        if (!extension_loaded('openssl') && $cfg['enc'] !== 'none') {
            throw new MailStepError(t('mail.chk_tls'), t('mail.no_openssl'), t('mail.hint_openssl'));
        }
        // 1. the name exists
        $ip = filter_var($cfg['host'], FILTER_VALIDATE_IP) ? $cfg['host'] : gethostbyname($cfg['host']);
        if ($ip === $cfg['host'] && !filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new MailStepError(t('mail.chk_server'), t('mail.dns_failed', ['host' => $cfg['host']]), t('mail.hint_dns'));
        }
        $checks[] = [t('mail.chk_server'), 'ok', $cfg['host'] . ' (' . $ip . ')'];

        // 2. the port answers
        $remote = ($cfg['enc'] === 'ssl' ? 'ssl://' : 'tcp://') . $cfg['host'] . ':' . $cfg['port'];
        $ctx = stream_context_create(['ssl' => ['peer_name' => $cfg['host'], 'SNI_enabled' => true, 'verify_peer' => true, 'verify_peer_name' => true]]);
        $t0 = microtime(true);
        $sock = @stream_socket_client($remote, $errno, $errstr, self::TIMEOUT, STREAM_CLIENT_CONNECT, $ctx);
        if (!$sock) {
            $err = trim((string) $errstr);
            $slow = microtime(true) - $t0 >= self::TIMEOUT - 1 || stripos($err, 'timed out') !== false;
            if ($cfg['enc'] === 'ssl' && stripos($err, 'ssl') !== false || stripos($err, 'certificate') !== false) {
                throw new MailStepError(t('mail.chk_tls'), preg_replace('/^[a-z_]+\(\):\s*/', '', $err), t('mail.hint_cert'));
            }
            if ($cfg['enc'] === 'ssl' && !$slow && ($err === '' || $errno === 0)) {
                throw new MailStepError(t('mail.chk_tls'), t('mail.ssl_failed', ['port' => $cfg['port']]), t('mail.hint_try_starttls'));
            }
            throw new MailStepError(t('mail.chk_connect'), ($err !== '' ? $err : 'error ' . $errno) . ' (port ' . $cfg['port'] . ')',
                $slow ? t('mail.hint_blocked', ['port' => $cfg['port']]) : t('mail.hint_refused', ['port' => $cfg['port']]));
        }
        $this->sock = $sock;
        stream_set_timeout($sock, self::TIMEOUT);
        $ms = (int) round((microtime(true) - $t0) * 1000);
        stream_set_timeout($sock, 8);
        $greet = $this->read();
        stream_set_timeout($sock, self::TIMEOUT);
        if ($greet[0] !== 220) {
            // silence on a plain connection usually means the port wants SSL/TLS first (465)
            throw new MailStepError(t('mail.chk_connect'), $greet[1], $greet[0] === 0 && $cfg['enc'] !== 'ssl' ? t('mail.hint_try_ssl', ['port' => $cfg['port']]) : t('mail.hint_not_smtp'));
        }
        $checks[] = [t('mail.chk_connect'), 'ok', t('mail.connected', ['port' => $cfg['port'], 'ms' => $ms])];

        // 3. hello + encryption
        $me = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
        $ehlo = $this->cmd('EHLO ' . $me);
        if ($ehlo[0] !== 250) {
            $ehlo = $this->cmd('HELO ' . $me);
            if ($ehlo[0] !== 250) {
                throw new MailStepError(t('mail.chk_connect'), $ehlo[1], t('mail.hint_not_smtp'));
            }
        }
        if ($cfg['enc'] === 'tls') {
            if (stripos($ehlo[1], 'STARTTLS') === false) {
                throw new MailStepError(t('mail.chk_tls'), t('mail.no_starttls'), t('mail.hint_starttls'));
            }
            $r = $this->cmd('STARTTLS');
            if ($r[0] !== 220) {
                throw new MailStepError(t('mail.chk_tls'), $r[1], t('mail.hint_starttls'));
            }
            $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }
            if (!@stream_socket_enable_crypto($sock, true, $crypto)) {
                $e = error_get_last();
                throw new MailStepError(t('mail.chk_tls'), preg_replace('/^[a-z_]+\(\):\s*/', '', (string) ($e['message'] ?? 'TLS')), t('mail.hint_cert'));
            }
            $ehlo = $this->cmd('EHLO ' . $me);
        }
        $checks[] = [t('mail.chk_tls'), $cfg['enc'] === 'none' ? 'warn' : 'ok', $cfg['enc'] === 'none' ? t('mail.enc_none_warn') : strtoupper($cfg['enc'] === 'ssl' ? 'SSL/TLS' : 'STARTTLS')];

        // 4. log in
        if ($cfg['user'] !== '') {
            $caps = strtoupper($ehlo[1]);
            if (str_contains($caps, 'AUTH') && !str_contains($caps, 'LOGIN') && str_contains($caps, 'PLAIN')) {
                $r = $this->cmd('AUTH PLAIN ' . base64_encode("\0" . $cfg['user'] . "\0" . $cfg['pass']));
            } else {
                $r = $this->cmd('AUTH LOGIN');
                if ($r[0] === 334) {
                    $r = $this->cmd(base64_encode($cfg['user']));
                    if ($r[0] === 334) {
                        $r = $this->cmd(base64_encode($cfg['pass']), true);
                    }
                }
            }
            if ($r[0] !== 235) {
                $gmail = (bool) preg_match('/gmail|google|outlook|office365|hotmail|yahoo|icloud|zoho/', $cfg['host']);
                throw new MailStepError(t('mail.chk_login'), $r[1], $gmail ? t('mail.hint_app_password') : t('mail.hint_login'));
            }
            $checks[] = [t('mail.chk_login'), 'ok', $cfg['user']];
        } else {
            $checks[] = [t('mail.chk_login'), 'warn', t('mail.no_login')];
        }

        // 5. the server accepts our sender (and the receiver, when sending)
        $r = $this->cmd('MAIL FROM:<' . $cfg['from'] . '>');
        if ($r[0] !== 250) {
            throw new MailStepError(t('mail.chk_sender'), $r[1], $r[0] === 530 ? t('mail.hint_need_login') : t('mail.hint_sender', ['from' => $cfg['from']]));
        }
        $checks[] = [t('mail.chk_sender'), 'ok', $cfg['from']];
        if ($to === null) {
            $this->cmd('RSET');
            $this->quit();
            return;
        }
        $r = $this->cmd('RCPT TO:<' . $to . '>');
        if ($r[0] !== 250 && $r[0] !== 251) {
            throw new MailStepError(t('mail.chk_sent'), $r[1], t('mail.hint_rcpt'));
        }
        $r = $this->cmd('DATA');
        if ($r[0] !== 354) {
            throw new MailStepError(t('mail.chk_sent'), $r[1], t('mail.hint_rejected'));
        }
        $subject ??= t('mail.test_subject', ['site' => site_name()]);
        $text ??= t('mail.test_body', ['site' => site_name(), 'method' => 'SMTP ' . $cfg['host'] . ':' . $cfg['port']]);
        $head = array_merge(self::headers($cfg, true), [
            'To: <' . $to . '>',
            'Subject: ' . self::encode($subject),
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . substr(strrchr($cfg['from'], '@'), 1) . '>',
        ]);
        $body = chunk_split(base64_encode(str_replace(["\r\n", "\r"], "\n", $text)), 76, "\r\n");
        $r = $this->cmd(implode("\r\n", $head) . "\r\n\r\n" . $body . '.');
        if ($r[0] !== 250) {
            throw new MailStepError(t('mail.chk_sent'), $r[1], t('mail.hint_rejected'));
        }
        $checks[] = [t('mail.chk_sent'), 'ok', $to];
        $this->quit();
    }

    /** @return array{0:int,1:string} reply code and text (all lines) */
    private function read(): array
    {
        $text = '';
        while (($line = fgets($this->sock, 1024)) !== false) {
            $text .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        if ($text === '') {
            $meta = stream_get_meta_data($this->sock);
            return [0, !empty($meta['timed_out']) ? t('mail.timeout') : t('mail.closed')];
        }
        $this->log[] = 'S: ' . trim($text);
        return [(int) substr($text, 0, 3), trim(preg_replace('/^\d{3}[ -]/m', '', $text))];
    }

    private function cmd(string $line, bool $secret = false): array
    {
        $this->log[] = 'C: ' . ($secret ? '********' : mb_substr($line, 0, 200));
        if (@fwrite($this->sock, $line . "\r\n") === false) {
            return [0, t('mail.closed')];
        }
        return $this->read();
    }

    private function quit(): void
    {
        if ($this->sock) {
            @fwrite($this->sock, "QUIT\r\n");
            @fclose($this->sock);
            $this->sock = null;
        }
    }

    /* ---------- shared ---------- */

    private static function headers(array $cfg, bool $smtp): array
    {
        return [
            'From: ' . self::encodeName($cfg['name']) . ' <' . $cfg['from'] . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: ' . ($smtp ? 'base64' : '8bit'),
            'X-Mailer: Zinkly Social Lite',
        ];
    }

    private static function encode(string $s): string
    {
        return '=?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], '', $s)) . '?=';
    }

    private static function encodeName(string $s): string
    {
        return preg_match('/[^\x20-\x7e]/', $s) ? self::encode($s) : '"' . addcslashes($s, '"\\') . '"';
    }

    /** "-f sender" for mail(), only for plain addresses (it goes to the sendmail command line). */
    private static function envelope(string $from): string
    {
        return preg_match('/^[A-Za-z0-9._+-]+@[A-Za-z0-9.-]+$/', $from) ? '-f' . $from : '';
    }
}

/** A failed SMTP step: what failed, what the server said, and what to do about it. */
final class MailStepError extends RuntimeException
{
    public function __construct(public string $step, string $said, public string $hint)
    {
        parent::__construct($said);
    }
}
