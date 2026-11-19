<?php

declare(strict_types=1);

/**
 * Cykliczny odczyt licznika i zapis stanu do pliku JSON czytanego przez WWW.
 */

require __DIR__ . '/../src/ModbusTcpClient.php';
require __DIR__ . '/../src/Meter.php';
require __DIR__ . '/../src/Settings.php';
require __DIR__ . '/../src/Levels.php';
require __DIR__ . '/../src/EnergyStore.php';
require __DIR__ . '/../src/Demand.php';
require __DIR__ . '/../src/Notifier.php';
require __DIR__ . '/../src/AlertMonitor.php';
require __DIR__ . '/../src/Diagnostics.php';

$config = require __DIR__ . '/../src/config.php';

$log = fn (string $msg) => fwrite(STDOUT, date('Y-m-d H:i:s') . " $msg\n");

$writeJson = function (string $file, array $data): void {
    file_put_contents("$file.tmp", json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    rename("$file.tmp", $file);
};
$writeState = fn (array $state) => $writeJson($config['state_file'], $state);

// Ustawienia (połączenie, interwał, progi, powiadomienia) – przeładowywane po zmianie pliku w panelu admina.
$settings = Settings::defaults();
$settingsCheckedAt = 0.0;
$settingsSignature = null;
$reloadSettings = function (bool $force = false) use (&$settings, &$settingsCheckedAt, &$settingsSignature, $log): void {
    if (!$force && microtime(true) - $settingsCheckedAt < 5) {
        return;
    }
    $settingsCheckedAt = microtime(true);
    $file = (getenv('CONFIG_DIR') ?: __DIR__ . '/../config') . '/settings.json';
    clearstatcache(true, $file);
    $signature = is_file($file) ? filemtime($file) . ':' . filesize($file) : 'defaults';
    if ($signature === $settingsSignature) {
        return;
    }
    try {
        $settings = Settings::load();
        $settingsSignature = $signature;
    } catch (Throwable $e) {
        $log('Błąd ustawień: ' . $e->getMessage());
    }
};
$reloadSettings(true);

$store = new EnergyStore($config['db_file'], $config['demand_minutes'] * 60);

$state = [
    'meter' => null,
    'interval' => $config['interval'],
    'registers' => [],
    'values' => null,
    'updated_at' => null,
    'ok' => false,
    'error' => null,
    'read_ms' => null,
];

// Połączenie z licznikiem (typ, adres) – z panelu albo z .env; zmiana w panelu przełącza licznik w locie.
$connection = null;
$client = $meter = $demand = $alerts = null;
$connect = function (array $conn) use (&$connection, &$client, &$meter, &$demand, &$alerts, &$state, $store, $config, $log): bool {
    try {
        $definition = Meter::load($conn['type']);
        $newClient = new ModbusTcpClient($conn['host'], $conn['port'], $conn['timeout']);
        $newMeter = new Meter($definition, $newClient, $conn['unit']);
    } catch (Throwable $e) {
        $log('Nie można przełączyć licznika: ' . $e->getMessage());
        return false;
    }
    if ($connection === null) {
        $store->claimUnassigned($conn['id']);
    }
    $demand?->flush();
    $client?->close();
    $connection = $conn;
    $client = $newClient;
    $meter = $newMeter;
    $demand = new Demand($store, $conn['id']);
    $alerts = new AlertMonitor($store, $definition['name'], Closure::fromCallable($log));
    $state['meter'] = ['type' => $conn['type'], 'name' => $definition['name'], 'host' => $conn['host'], 'port' => $conn['port'],
        'unit' => $conn['unit'], 'verified' => !empty($definition['verified'])];
    $state['registers'] = $meter->describe() + Demand::registers($config['demand_minutes']);
    $state['values'] = null;
    $state['ok'] = false;
    $state['error'] = 'łączenie…';
    $log(sprintf('Licznik: %s @ %s:%d (unit %d), %d zapytań/cykl', $definition['name'], $conn['host'], $conn['port'], $conn['unit'], $meter->blockCount()));
    return true;
};
if (!$connect(Settings::connection($settings, $config))) {
    $log('Używam licznika z .env');
    $connect(Settings::connection([], $config)) or exit(1);
}
$failedConnection = null;
$reconnectIfChanged = function () use (&$connection, &$settings, &$failedConnection, $config, $connect): bool {
    $wanted = Settings::connection($settings, $config);
    if ($wanted == $connection || $wanted == $failedConnection) {
        return false;
    }
    $ok = $connect($wanted);
    $failedConnection = $ok ? null : $wanted;
    return $ok;
};

// Historia do wykresów (w pamięci, zapisywana co ~5 s do pliku obok stanu).
// Rozdzielczość co najmniej 0,5 s – przy szybszym odczycie nie puchnie pamięć ani plik.
$historyFile = dirname($config['state_file']) . '/history.json';
$historyStep = 0.5;
$historyMax = (int) ceil($config['history_seconds'] / $historyStep);
$history = ['t' => [], 'v' => []];
$historyLast = 0.0;
$historySaved = 0.0;

// Interwał: z panelu (settings.json) albo POLL_INTERVAL z .env; zmiana działa w locie.
$interval = $config['interval'];
$applyInterval = function () use (&$interval, &$state, &$settings, $config, $log): void {
    $wanted = $settings['poll_interval'] ?? $config['interval'];
    if (abs($wanted - $interval) > 1e-9) {
        $log(sprintf('Interwał odczytu: %.1f s -> %.1f s', $interval, $wanted));
        $interval = $wanted;
        $state['interval'] = $wanted;
    }
};

$lastError = null;
$failures = 0;
$next = microtime(true);
while (true) {
    $start = microtime(true);
    try {
        $values = $meter->read();
        try {
            $values += $demand->update($start, $values, $interval);
        } catch (Throwable $e) {
            $log('Błąd modułu energii: ' . $e->getMessage());
        }
        $state['values'] = $values;
        $state['updated_at'] = (int) round($start * 1000);
        $state['ok'] = true;
        $state['error'] = null;
        if ($lastError !== null) {
            $log('Odczyt przywrócony');
            $lastError = null;
        }
    } catch (Throwable $e) {
        $state['ok'] = false;
        $state['error'] = $e->getMessage();
        // Logujemy tylko zmianę błędu, żeby nie zalewać logów co sekundę.
        if ($e->getMessage() !== $lastError) {
            $log('Błąd: ' . $e->getMessage());
            $lastError = $e->getMessage();
        }
    }
    $state['read_ms'] = round((microtime(true) - $start) * 1000, 1);
    $state['demand'] = $demand->meta() + ['live_row' => $demand->liveRow()];

    $reloadSettings();
    $applyInterval();
    $reconnectIfChanged();
    try {
        $alerts->check($start, $state['ok'], $state['values'], $state['registers'], $settings);
    } catch (Throwable $e) {
        $log('Błąd modułu alarmów: ' . $e->getMessage());
    }
    $state['alerts'] = $alerts->active();
    $writeState($state);

    if ($state['ok'] && $start - $historyLast >= $historyStep * 0.95) {
        $historyLast = $start;
        $history['t'][] = $state['updated_at'];
        foreach ($state['values'] as $key => $value) {
            $history['v'][$key][] = $value;
        }
        if (count($history['t']) > $historyMax) {
            $drop = count($history['t']) - $historyMax;
            array_splice($history['t'], 0, $drop);
            foreach ($history['v'] as &$series) {
                array_splice($series, 0, $drop);
            }
            unset($series);
        }
    }
    if ($start - $historySaved >= 5) {
        $historySaved = $start;
        $writeJson($historyFile, $history);
    }

    // Po błędach coraz rzadsze próby (2, 4, 6… do 10 s) – nie zasypujemy bramki połączeniami.
    $failures = $state['ok'] ? 0 : $failures + 1;
    $next += $state['ok'] ? $interval : max($interval, min(10.0, 2.0 * $failures));
    if ($next < microtime(true)) {
        $next = microtime(true);
    }

    // Czekanie w krótkich odcinkach: przy długim interwale nadal obsługujemy diagnostykę
    // i zmianę interwału z panelu (skrócenie działa od razu).
    do {
        Diagnostics::serve(dirname($config['state_file']), $client);
        $reloadSettings();
        if ($reconnectIfChanged()) {
            $next = microtime(true);
            $failures = 0;
        }
        $previous = $interval;
        $applyInterval();
        if ($interval < $previous && $state['ok']) {
            $next = min($next, $start + $interval);
        }
        $wait = $next - microtime(true);
        if ($wait > 0) {
            usleep((int) (min($wait, 0.25) * 1e6));
        }
    } while ($next > microtime(true));
}
