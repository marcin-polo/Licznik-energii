<?php

declare(strict_types=1);

/**
 * Ustawienia wyglądu (kolory, progi, ekrany) w config/settings.json
 * oraz hasło administratora (hash) w config/admin_password.txt.
 */
final class Settings
{
    public const DEFAULT_PASSWORD = 'admin';
    public const CELL_TYPES = ['value', 'chart', 'clock'];
    public const LEVELS = ['normal', 'export', 'warning', 'alarm'];
    public const MAX_WINDOW = 3600;
    public const INTERVAL_MIN = 0.1;
    public const INTERVAL_MAX = 60.0;
    public const DEFAULT_COLORS = ['normal' => '#ffffff', 'export' => '#22c55e', 'warning' => '#facc15', 'alarm' => '#ef4444'];

    private static function dir(): string
    {
        return getenv('CONFIG_DIR') ?: __DIR__ . '/../config';
    }

    public static function load(): array
    {
        $file = self::dir() . '/settings.json';
        if (!is_file($file)) {
            return self::defaults();
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            throw new RuntimeException('config/settings.json: nieprawidłowy JSON (' . json_last_error_msg() . ')');
        }
        return self::sanitize($data);
    }

    public static function save(array $input): array
    {
        $settings = self::sanitize($input);
        self::writeAtomic(self::dir() . '/settings.json', json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) . "\n");
        return $settings;
    }

    public static function checkPassword(string $password): bool
    {
        $file = self::dir() . '/admin_password.txt';
        if (!is_file($file)) {
            return hash_equals(self::DEFAULT_PASSWORD, $password);
        }
        return password_verify($password, trim((string) file_get_contents($file)));
    }

    public static function isDefaultPassword(): bool
    {
        return !is_file(self::dir() . '/admin_password.txt');
    }

    public static function setPassword(string $password): void
    {
        $file = self::dir() . '/admin_password.txt';
        self::writeAtomic($file, password_hash($password, PASSWORD_DEFAULT) . "\n");
        chmod($file, 0600);
    }

    /** Walidacja całego obiektu ustawień – wszystko, co nie pasuje, jest odrzucane lub poprawiane. */
    public static function sanitize(array $in): array
    {
        $colors = [];
        foreach (self::LEVELS as $level) {
            $colors[$level] = self::color($in['colors'][$level] ?? null) ?? self::DEFAULT_COLORS[$level];
        }

        $thresholds = [];
        foreach ((array) ($in['thresholds'] ?? []) as $key => $t) {
            if (!self::isKey($key) || !is_array($t)) {
                continue;
            }
            $clean = [];
            foreach (['alarm_low', 'warning_low', 'warning', 'alarm'] as $field) {
                if (isset($t[$field]) && is_numeric($t[$field])) {
                    $clean[$field] = (float) $t[$field];
                }
            }
            if (isset($t['hysteresis']) && is_numeric($t['hysteresis']) && $t['hysteresis'] > 0) {
                $clean['hysteresis'] = (float) $t['hysteresis'];
            }
            foreach (['export', 'notify'] as $flag) {
                if (!empty($t[$flag])) {
                    $clean[$flag] = true;
                }
            }
            foreach (self::LEVELS as $level) {
                if ($c = self::color($t['colors'][$level] ?? null)) {
                    $clean['colors'][$level] = $c;
                }
            }
            if ($clean) {
                $thresholds[$key] = $clean;
            }
        }

        $screens = [];
        foreach ((array) ($in['screens'] ?? []) as $name => $screen) {
            if (!is_string($name) || !preg_match('/^[a-z0-9_-]{1,40}$/', $name) || !is_array($screen)) {
                continue;
            }
            $rows = [];
            foreach (array_slice((array) ($screen['rows'] ?? []), 0, 6) as $row) {
                $cells = [];
                foreach (array_slice((array) ($row['cells'] ?? []), 0, 6) as $cell) {
                    if ($cell = self::cell((array) $cell)) {
                        $cells[] = $cell;
                    }
                }
                if ($cells) {
                    $rows[] = ['height' => self::weight($row['height'] ?? 1), 'cells' => $cells];
                }
            }
            if ($rows) {
                $screens[$name] = ['title' => self::text($screen['title'] ?? '', 100), 'rows' => $rows];
            }
        }

        // Interwał odczytu [s]; null = wartość POLL_INTERVAL z .env.
        $interval = isset($in['poll_interval']) && is_numeric($in['poll_interval'])
            ? round(max(self::INTERVAL_MIN, min(self::INTERVAL_MAX, (float) $in['poll_interval'])), 1) : null;

        $c = (array) ($in['connection'] ?? []);
        $num = fn (mixed $v, float $min, float $max) => is_numeric($v) ? max($min, min($max, (float) $v)) : null;
        $connection = [
            'type' => is_string($c['type'] ?? null) && preg_match('/^[a-z0-9_]{1,60}$/', $c['type']) ? $c['type'] : null,
            'host' => is_string($c['host'] ?? null) && preg_match('/^[A-Za-z0-9.-]{1,253}$/', trim($c['host'])) ? trim($c['host']) : null,
            'port' => ($v = $num($c['port'] ?? null, 1, 65535)) === null ? null : (int) $v,
            'unit' => ($v = $num($c['unit'] ?? null, 0, 255)) === null ? null : (int) $v,
            'timeout' => ($v = $num($c['timeout'] ?? null, 0.2, 10)) === null ? null : round($v, 1),
        ];

        return ['connection' => $connection, 'poll_interval' => $interval, 'colors' => $colors, 'thresholds' => $thresholds, 'screens' => $screens,
            'notifications' => self::notifications((array) ($in['notifications'] ?? []))];
    }

    /**
     * Połączenie z licznikiem: ustawienia z panelu, a gdzie puste – wartości z .env.
     * @return array{type:string, host:string, port:int, unit:int, timeout:float, id:string}
     */
    public static function connection(array $settings, array $config): array
    {
        $c = $settings['connection'] ?? [];
        $out = [
            'type' => $c['type'] ?? $config['meter_type'],
            'host' => $c['host'] ?? $config['host'],
            'port' => $c['port'] ?? $config['port'],
            'unit' => $c['unit'] ?? $config['unit_id'],
            'timeout' => $c['timeout'] ?? $config['timeout'],
        ];
        // Identyfikator licznika w bazie – zmiana = inny licznik (inne stany liczników energii).
        $out['id'] = "{$out['type']}@{$out['host']}:{$out['port']}/{$out['unit']}";
        return $out;
    }

    /** Ustawienia bez danych dostępowych (dla api.php, czytanego bez logowania). */
    public static function public(array $settings): array
    {
        unset($settings['notifications']);
        return $settings;
    }

    private static function notifications(array $n): array
    {
        $str = fn (mixed $v, int $max = 200) => is_string($v) ? mb_substr(trim(str_replace(["\r", "\n"], '', $v)), 0, $max) : '';
        $int = fn (mixed $v, int $min, int $max, int $default) => is_numeric($v) ? max($min, min($max, (int) $v)) : $default;
        $email = fn (mixed $v) => is_string($v) && filter_var(trim($v), FILTER_VALIDATE_EMAIL) ? trim($v) : '';

        $ntfy = (array) ($n['ntfy'] ?? []);
        $tg = (array) ($n['telegram'] ?? []);
        $mail = (array) ($n['email'] ?? []);
        $url = $str($ntfy['url'] ?? '', 300);

        return [
            'enabled' => !empty($n['enabled']),
            'min_level' => ($n['min_level'] ?? '') === 'warning' ? 'warning' : 'alarm',
            'delay' => $int($n['delay'] ?? null, 0, 3600, 30),
            'repeat' => $int($n['repeat'] ?? null, 0, 1440, 0),
            'recovery' => ($n['recovery'] ?? true) !== false,
            'comm_loss' => !empty($n['comm_loss']),
            'comm_delay' => $int($n['comm_delay'] ?? null, 10, 3600, 60),
            'ntfy' => [
                'enabled' => !empty($ntfy['enabled']),
                'url' => preg_match('#^https?://[^\s]+$#', $url) ? $url : '',
                'token' => $str($ntfy['token'] ?? ''),
            ],
            'telegram' => [
                'enabled' => !empty($tg['enabled']),
                'token' => preg_match('/^\d+:[\w-]+$/', $str($tg['token'] ?? '')) ? $str($tg['token']) : '',
                'chat_id' => preg_match('/^(-?\d+|@\w+)$/', $str($tg['chat_id'] ?? '')) ? $str($tg['chat_id']) : '',
            ],
            'email' => [
                'enabled' => !empty($mail['enabled']),
                'host' => preg_match('/^[\w.-]+$/', $str($mail['host'] ?? '')) ? $str($mail['host']) : '',
                'port' => $int($mail['port'] ?? null, 1, 65535, 587),
                'security' => in_array($mail['security'] ?? '', ['starttls', 'ssl', 'none'], true) ? $mail['security'] : 'starttls',
                'user' => $str($mail['user'] ?? ''),
                'password' => is_string($mail['password'] ?? null) ? $mail['password'] : '',
                'from' => $email($mail['from'] ?? ''),
                'to' => implode(', ', array_filter(array_map($email, explode(',', (string) ($mail['to'] ?? ''))))),
            ],
        ];
    }

    private static function cell(array $c): ?array
    {
        $type = in_array($c['type'] ?? null, self::CELL_TYPES, true) ? $c['type'] : null;
        if ($type === null) {
            return null;
        }
        $out = ['type' => $type, 'width' => self::weight($c['width'] ?? 1), 'label' => self::text($c['label'] ?? '', 60)];

        if ($type === 'value') {
            if (!self::isKey($c['key'] ?? null)) {
                return null;
            }
            $out += ['key' => $c['key'], 'kw' => !empty($c['kw']), 'bar' => ($c['bar'] ?? true) !== false];
        } elseif ($type === 'chart') {
            $keys = array_values(array_filter((array) ($c['keys'] ?? []), [self::class, 'isKey']));
            if (!$keys) {
                return null;
            }
            $window = (int) ($c['window'] ?? 300);
            $out += ['keys' => array_slice(array_unique($keys), 0, 8), 'window' => max(60, min(self::MAX_WINDOW, $window)), 'kw' => !empty($c['kw'])];
        }
        return $out;
    }

    private static function isKey(mixed $key): bool
    {
        return is_string($key) && preg_match('/^[a-z0-9_]{1,40}$/', $key) === 1;
    }

    private static function color(mixed $c): ?string
    {
        return is_string($c) && preg_match('/^(#[0-9a-fA-F]{3,8}|[a-zA-Z]{3,20})$/', $c) ? $c : null;
    }

    private static function weight(mixed $w): float
    {
        return is_numeric($w) ? max(0.5, min(10.0, round((float) $w, 1))) : 1.0;
    }

    private static function text(mixed $s, int $max): string
    {
        return is_string($s) ? mb_substr(trim($s), 0, $max) : '';
    }

    private static function writeAtomic(string $file, string $content): void
    {
        $tmp = $file . '.tmp';
        if (file_put_contents($tmp, $content) === false || !rename($tmp, $file)) {
            throw new RuntimeException("Nie można zapisać $file");
        }
    }

    public static function defaults(): array
    {
        $value = fn (string $key, array $extra = []) => ['type' => 'value', 'key' => $key] + $extra;
        $phases = fn (string $prefix, array $extra = []) => array_map(fn ($p) => $value("{$prefix}_l$p", $extra), [1, 2, 3]);
        $power = ['warning' => 2500, 'alarm' => 3500, 'hysteresis' => 100, 'export' => true];
        $current = ['warning' => 16, 'alarm' => 20];
        $voltage = ['warning_low' => 215, 'alarm_low' => 207, 'warning' => 245, 'alarm' => 253]; // PN-EN 50160: 230 V ±10%

        return self::sanitize([
            'colors' => self::DEFAULT_COLORS,
            'thresholds' => [
                'power_active_total' => ['warning' => 6000, 'alarm' => 9000, 'hysteresis' => 200, 'export' => true],
                'demand_forecast' => ['warning' => 5000, 'alarm' => 7000],
                'power_active_l1' => $power, 'power_active_l2' => $power, 'power_active_l3' => $power,
                'current_l1' => $current, 'current_l2' => $current, 'current_l3' => $current,
                'voltage_l1' => $voltage, 'voltage_l2' => $voltage, 'voltage_l3' => $voltage,
                'frequency' => ['warning_low' => 49.8, 'alarm_low' => 49.5, 'warning' => 50.2, 'alarm' => 50.5],
            ],
            'screens' => [
                'moc' => ['title' => 'Pobór mocy', 'rows' => [['cells' => [$value('power_active_total')]]]],
                'fazy' => ['title' => 'Moc na fazach', 'rows' => [['cells' => $phases('power_active')]]],
                'przeglad' => ['title' => 'Przegląd', 'rows' => [
                    ['height' => 2, 'cells' => [
                        $value('power_active_total', ['kw' => true]),
                        ['type' => 'chart', 'width' => 2, 'keys' => ['power_active_total', 'power_active_l1', 'power_active_l2', 'power_active_l3'], 'window' => 900, 'kw' => true],
                    ]],
                    ['height' => 1, 'cells' => $phases('power_active', ['kw' => true])],
                ]],
                'bilans' => ['title' => 'Moc 15-min i bilans', 'rows' => [
                    ['height' => 2, 'cells' => [$value('demand_forecast', ['kw' => true]), $value('demand_current', ['kw' => true])]],
                    ['height' => 1, 'cells' => [
                        $value('demand_peak_day', ['kw' => true, 'bar' => false]),
                        $value('energy_import_today', ['bar' => false]),
                        $value('energy_export_today', ['bar' => false]),
                    ]],
                ]],
                'napiecia' => ['title' => 'Napięcia', 'rows' => [['cells' => $phases('voltage')]]],
                'prady' => ['title' => 'Prądy', 'rows' => [['cells' => $phases('current')]]],
            ],
        ]);
    }
}
