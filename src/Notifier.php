<?php

declare(strict_types=1);

/** Wysyłka powiadomień: ntfy, Telegram, e-mail (SMTP). */
final class Notifier
{
    private const TIMEOUT = 8;

    public function __construct(private readonly array $config)
    {
    }

    /**
     * Wysyła przez wszystkie włączone kanały.
     * @return array<string, string|null> kanał => komunikat błędu (null = wysłano)
     */
    public function send(string $title, string $message, string $level): array
    {
        $result = [];
        foreach (['ntfy', 'telegram', 'email'] as $channel) {
            if (!empty($this->config[$channel]['enabled'])) {
                $result[$channel] = $this->sendVia($channel, $title, $message, $level);
            }
        }
        return $result;
    }

    public function sendVia(string $channel, string $title, string $message, string $level): ?string
    {
        $c = $this->config[$channel] ?? [];
        $required = ['ntfy' => ['url'], 'telegram' => ['token', 'chat_id'], 'email' => ['host', 'from', 'to']][$channel] ?? [];
        $missing = array_filter($required, fn ($f) => ($c[$f] ?? '') === '');
        if ($missing) {
            return 'uzupełnij poprawnie: ' . implode(', ', $missing);
        }
        try {
            match ($channel) {
                'ntfy' => $this->ntfy($c, $title, $message, $level),
                'telegram' => $this->telegram($c, $title, $message),
                'email' => $this->email($c, $title, $message),
            };
            return null;
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    public static function summary(array $result): string
    {
        if (!$result) {
            return 'brak włączonych kanałów';
        }
        return implode(', ', array_map(fn ($ch, $err) => $err === null ? "$ch: OK" : "$ch: $err", array_keys($result), $result));
    }

    private function ntfy(array $c, string $title, string $message, string $level): void
    {
        $priority = ['alarm' => 5, 'warning' => 4, 'normal' => 2][$level] ?? 3;
        $tags = ['alarm' => 'rotating_light', 'warning' => 'warning', 'normal' => 'white_check_mark'][$level] ?? 'zap';
        $url = $c['url'] . (str_contains($c['url'], '?') ? '&' : '?') . http_build_query(['title' => $title, 'priority' => $priority, 'tags' => $tags]);
        $headers = ['Content-Type: text/plain; charset=utf-8'];
        if (($c['token'] ?? '') !== '') {
            $headers[] = 'Authorization: Bearer ' . $c['token'];
        }
        $this->http($url, $message, $headers);
    }

    private function telegram(array $c, string $title, string $message): void
    {
        $this->http(
            "https://api.telegram.org/bot{$c['token']}/sendMessage",
            http_build_query(['chat_id' => $c['chat_id'], 'text' => "$title\n$message"]),
            ['Content-Type: application/x-www-form-urlencoded'],
        );
    }

    private function http(string $url, string $body, array $headers): void
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => self::TIMEOUT,
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            throw new RuntimeException($error ?: 'błąd połączenia');
        }
        if ($code >= 300) {
            $detail = json_decode((string) $response, true);
            throw new RuntimeException("HTTP $code" . (isset($detail['description']) ? ": {$detail['description']}" : (isset($detail['error']) ? ": {$detail['error']}" : '')));
        }
    }

    /** Minimalny klient SMTP (STARTTLS / SSL / bez szyfrowania, AUTH LOGIN). */
    private function email(array $c, string $subject, string $body): void
    {
        $security = $c['security'] ?? 'starttls';
        $remote = ($security === 'ssl' ? 'ssl://' : 'tcp://') . $c['host'] . ':' . $c['port'];
        $socket = @stream_socket_client($remote, $errno, $errstr, self::TIMEOUT);
        if (!$socket) {
            throw new RuntimeException("SMTP {$c['host']}:{$c['port']}: $errstr");
        }
        stream_set_timeout($socket, self::TIMEOUT);

        $expect = function (array $codes) use ($socket): string {
            $reply = '';
            while (($line = fgets($socket, 1024)) !== false) {
                $reply .= $line;
                if (strlen($line) < 4 || $line[3] === ' ') {
                    break;
                }
            }
            if (!in_array((int) substr($reply, 0, 3), $codes, true)) {
                throw new RuntimeException('SMTP: ' . trim($reply ?: 'brak odpowiedzi'));
            }
            return $reply;
        };
        $cmd = function (string $line, array $codes) use ($socket, $expect): string {
            fwrite($socket, $line . "\r\n");
            return $expect($codes);
        };

        try {
            $expect([220]);
            $cmd('EHLO meter.local', [250]);
            if ($security === 'starttls') {
                $cmd('STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('SMTP: nie udało się włączyć TLS');
                }
                $cmd('EHLO meter.local', [250]);
            }
            if (($c['user'] ?? '') !== '') {
                $cmd('AUTH LOGIN', [334]);
                $cmd(base64_encode($c['user']), [334]);
                $cmd(base64_encode($c['password'] ?? ''), [235]);
            }
            $recipients = array_filter(array_map('trim', explode(',', $c['to'])));
            $cmd("MAIL FROM:<{$c['from']}>", [250]);
            foreach ($recipients as $to) {
                $cmd("RCPT TO:<$to>", [250, 251]);
            }
            $cmd('DATA', [354]);
            $headers = [
                "From: {$c['from']}",
                'To: ' . implode(', ', $recipients),
                'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
                'Date: ' . date(DATE_RFC2822),
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
            ];
            $text = preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], "\r\n", $body));
            $cmd(implode("\r\n", $headers) . "\r\n\r\n" . $text . "\r\n.", [250]);
            $cmd('QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }
}
