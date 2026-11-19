<?php

declare(strict_types=1);

require __DIR__ . '/../src/Settings.php';
require __DIR__ . '/../src/EnergyStore.php';

$config = require __DIR__ . '/../src/config.php';

header('Cache-Control: no-store');

$output = function (array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    exit;
};
$readState = fn () => json_decode((string) @file_get_contents($config['state_file']), true);
$store = fn () => new EnergyStore($config['db_file'], $config['demand_minutes'] * 60);

// Historia: /api.php?history&seconds=300&keys=a,b
if (isset($_GET['history'])) {
    $history = json_decode((string) @file_get_contents(dirname($config['state_file']) . '/history.json'), true) ?: ['t' => [], 'v' => []];
    $since = round(microtime(true) * 1000) - 1000 * min(Settings::MAX_WINDOW, max(10, (int) ($_GET['seconds'] ?? 300)));
    $from = 0;
    while ($from < count($history['t']) && $history['t'][$from] < $since) {
        $from++;
    }
    $keys = isset($_GET['keys']) ? explode(',', $_GET['keys']) : array_keys($history['v']);
    $values = [];
    foreach ($keys as $key) {
        if (isset($history['v'][$key])) {
            $values[$key] = array_slice($history['v'][$key], $from);
        }
    }
    $output(['t' => array_slice($history['t'], $from), 'v' => (object) $values]);
}

// Raport energii: /api.php?report=day|month|year&date=2026-11-19|2026-11|2026[&format=csv]
if (isset($_GET['report'])) {
    try {
        $live = $readState()['demand']['live_row'] ?? null;
        $report = $store()->report((string) $_GET['report'], (string) ($_GET['date'] ?? ''), $live);
    } catch (Throwable $e) {
        $output(['error' => $e->getMessage()], 400);
    }
    if (($_GET['format'] ?? '') === 'csv') {
        $num = fn ($v, $d = 3) => $v === null ? '' : number_format((float) $v, $d, ',', '');
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"energia-{$report['kind']}-{$report['date']}.csv\"");
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM – Excel poprawnie czyta polskie znaki
        $day = $report['kind'] === 'day';
        fputcsv($out, array_merge(['Okres', 'Pobrana czynna [kWh]', 'Oddana czynna [kWh]', 'Pobrana bierna [kvarh]', 'Oddana bierna [kvarh]', 'Szczyt mocy [W]', 'Szczyt – czas'],
            $day ? ['Średnia moc [W]', 'Min [W]', 'Max [W]', 'Średnia L1 [W]', 'Średnia L2 [W]', 'Średnia L3 [W]'] : []), ';');
        foreach ($report['buckets'] as $b) {
            fputcsv($out, array_merge(
                [$day ? date('Y-m-d H:i', $b['key']) : $b['key'], $num($b['e_imp']), $num($b['e_exp']), $num($b['q_imp']), $num($b['q_exp']),
                    $num($b['peak'], 0), $b['peak_ts'] ? date('Y-m-d H:i', $b['peak_ts']) : ''],
                $day ? array_map(fn ($c) => $num($b[$c] ?? null, 0), ['p_avg', 'p_min', 'p_max', 'p1_avg', 'p2_avg', 'p3_avg']) : [],
            ), ';');
        }
        exit;
    }
    $output($report);
}

// Zdarzenia (alarmy, powiadomienia): /api.php?events&from=<unix>&to=<unix>&limit=100
if (isset($_GET['events'])) {
    $to = (int) ($_GET['to'] ?? time() + 1);
    $from = (int) ($_GET['from'] ?? $to - 30 * 86400);
    $output(['events' => $store()->events($from, $to, min(1000, max(1, (int) ($_GET['limit'] ?? 100))))]);
}

$state = $readState();
if (!$state) {
    $output(['ok' => false, 'error' => 'Brak danych z pollera'], 503);
}
$state['server_time'] = (int) round(microtime(true) * 1000);
unset($state['demand']['live_row']);

// Bez metadanych rejestrów: /api.php?values
if (isset($_GET['values'])) {
    $output(['ok' => $state['ok'], 'updated_at' => $state['updated_at'], 'interval' => $state['interval'],
        'read_ms' => $state['read_ms'], 'error' => $state['error'], 'meter' => $state['meter'], 'values' => $state['values']]);
}

try {
    $state['settings'] = Settings::public(Settings::load());
} catch (Throwable $e) {
    $state['settings'] = Settings::public(Settings::defaults());
    $state['settings_error'] = $e->getMessage();
}

$output($state);
