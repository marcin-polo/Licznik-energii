<?php

declare(strict_types=1);

require __DIR__ . '/../src/Settings.php';
require __DIR__ . '/../src/Meter.php';
require __DIR__ . '/../src/ModbusTcpClient.php';
require __DIR__ . '/../src/Demand.php';
require __DIR__ . '/../src/Notifier.php';
require __DIR__ . '/../src/Diagnostics.php';

$config = require __DIR__ . '/../src/config.php';

session_name('meter_admin');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'path' => '/']);
session_start();

$action = $_GET['action'] ?? null;
$loggedIn = !empty($_SESSION['admin']);
$error = null;

function respond(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'login') {
        if (Settings::checkPassword((string) ($_POST['password'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            header('Location: admin.php');
            exit;
        }
        sleep(1); // spowolnienie zgadywania hasła
        $error = 'Nieprawidłowe hasło';
    } elseif ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        header('Location: admin.php');
        exit;
    } else {
        if (!$loggedIn) {
            respond(['error' => 'Sesja wygasła – zaloguj się ponownie'], 401);
        }
        if (!hash_equals($_SESSION['csrf'] ?? '', $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
            respond(['error' => 'Nieprawidłowy token – odśwież stronę'], 403);
        }
        $body = json_decode((string) file_get_contents('php://input'), true) ?? [];
        // Urządzenie, z którym poller jest połączony, czytamy przez poller (bramki RS485 często przyjmują jedno połączenie).
        $onPollerConnection = function (string $host, int $port) use ($config): bool {
            $meter = json_decode((string) @file_get_contents($config['state_file']), true)['meter'] ?? null;
            return $meter && $meter['host'] === $host && (int) $meter['port'] === $port;
        };
        try {
            if ($action === 'save') {
                // Ochrona przed przypadkowym nadpisaniem wszystkiego domyślnymi (np. pusty lub ucięty zapis).
                if (!is_array($body['settings'] ?? null) || !isset($body['settings']['screens'], $body['settings']['thresholds'], $body['settings']['colors'])) {
                    respond(['error' => 'Niepełne ustawienia – zapis odrzucony'], 400);
                }
                $type = $body['settings']['connection']['type'] ?? null;
                if ($type !== null && $type !== '' && !isset(Meter::available()[$type])) {
                    respond(['error' => "Nieznany typ licznika: $type"], 400);
                }
                respond(['ok' => true, 'settings' => Settings::save((array) ($body['settings'] ?? []))]);
            }
            if ($action === 'test_meter') {
                $conn = Settings::connection(['connection' => Settings::sanitize(['connection' => (array) ($body['connection'] ?? [])])['connection']], $config);
                if (!isset(Meter::available()[$conn['type']])) {
                    respond(['error' => 'Nieznany typ licznika'], 400);
                }
                $request = ['id' => bin2hex(random_bytes(8)), 'type' => $conn['type'], 'unit' => $conn['unit']];
                respond($onPollerConnection($conn['host'], $conn['port'])
                    ? Diagnostics::viaPoller(dirname($config['state_file']), $request)
                    : Diagnostics::execute(new ModbusTcpClient($conn['host'], $conn['port'], $conn['timeout']), $request, true));
            }
            if ($action === 'password') {
                if (!Settings::checkPassword((string) ($body['current'] ?? ''))) {
                    sleep(1);
                    respond(['error' => 'Aktualne hasło jest nieprawidłowe'], 400);
                }
                $new = (string) ($body['new'] ?? '');
                if (mb_strlen($new) < 6) {
                    respond(['error' => 'Nowe hasło musi mieć co najmniej 6 znaków'], 400);
                }
                Settings::setPassword($new);
                respond(['ok' => true]);
            }
            if ($action === 'test_notify') {
                // Test na bieżących (także niezapisanych) ustawieniach kanału.
                $cfg = Settings::sanitize(['notifications' => (array) ($body['notifications'] ?? [])])['notifications'];
                $channel = (string) ($body['channel'] ?? '');
                if (!in_array($channel, ['ntfy', 'telegram', 'email'], true)) {
                    respond(['error' => 'Nieznany kanał'], 400);
                }
                $error = (new Notifier($cfg))->sendVia($channel, 'Test powiadomienia', 'To jest wiadomość testowa z panelu licznika (' . date('Y-m-d H:i:s') . ').', 'warning');
                respond($error === null ? ['ok' => true] : ['error' => $error], $error === null ? 200 : 502);
            }
            if ($action === 'modbus_read') {
                $host = trim((string) ($body['host'] ?? ''));
                if (!preg_match('/^[\w.-]{1,253}$/', $host)) {
                    respond(['error' => 'Nieprawidłowy host'], 400);
                }
                $request = [
                    'id' => bin2hex(random_bytes(8)),
                    'host' => $host,
                    'port' => max(1, min(65535, (int) ($body['port'] ?? 502))),
                    'unit' => max(0, min(255, (int) ($body['unit'] ?? 1))),
                    'function' => (int) ($body['function'] ?? 3) === 4 ? 4 : 3,
                    'address' => max(0, min(65535, (int) ($body['address'] ?? 0))),
                    'count' => max(1, min(125, (int) ($body['count'] ?? 10))),
                ];
                // Licznik z konfiguracji czyta poller na swoim połączeniu – bramki RS485 często przyjmują tylko jedno połączenie TCP.
                if ($onPollerConnection($request['host'], $request['port'])) {
                    respond(Diagnostics::viaPoller(dirname($config['state_file']), $request));
                }
                respond(Diagnostics::execute(new ModbusTcpClient($request['host'], $request['port'], 3.0), $request, true));
            }
        } catch (Throwable $e) {
            respond(['error' => $e->getMessage()], 500);
        }
        respond(['error' => 'Nieznana akcja'], 400);
    }
}

$h = fn (string $s) => htmlspecialchars($s, ENT_QUOTES);
?>
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Konfiguracja</title>
<style>
  :root {
    --bg: #f4f5f7; --card: #fff; --text: #1d2330; --muted: #6b7280; --line: #e3e6eb; --soft: #f0f2f5;
    --accent: #2563eb; --accent-text: #fff; --ok: #16a34a; --err: #dc2626; --warn-bg: #fef3c7; --warn-text: #92400e;
  }
  @media (prefers-color-scheme: dark) {
    :root {
      --bg: #11141a; --card: #1a1f28; --text: #e6e8ec; --muted: #9aa3af; --line: #2a313d; --soft: #222833;
      --accent: #60a5fa; --accent-text: #0b1220; --warn-bg: #3b2f0a; --warn-text: #fcd34d;
    }
  }
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--bg); color: var(--text); font: 15px/1.45 system-ui, -apple-system, "Segoe UI", sans-serif; }
  main { max-width: 1200px; margin: 0 auto; padding: 16px 16px 90px; }
  header { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 16px; margin-bottom: 16px; }
  h1 { font-size: 20px; margin: 0; flex: 1; }
  h2 { font-size: 16px; margin: 0 0 12px; }
  h3 { font-size: 14px; margin: 0; color: var(--muted); font-weight: 500; }
  a { color: var(--accent); }
  .card { background: var(--card); border: 1px solid var(--line); border-radius: 10px; padding: 16px; margin-bottom: 16px; }
  .banner { background: var(--warn-bg); color: var(--warn-text); border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; }
  input, select, button { font: inherit; color: var(--text); }
  input[type=text], input[type=number], input[type=password], select {
    background: var(--card); border: 1px solid var(--line); border-radius: 6px; padding: 5px 8px; min-width: 0;
  }
  input[type=number] { width: 90px; }
  input:focus, select:focus { outline: 2px solid var(--accent); outline-offset: -1px; }
  input.invalid { border-color: var(--err); }
  button { background: var(--soft); border: 1px solid var(--line); border-radius: 6px; padding: 5px 12px; cursor: pointer; }
  button:hover { border-color: var(--accent); }
  button.primary { background: var(--accent); color: var(--accent-text); border-color: var(--accent); font-weight: 600; }
  button.danger:hover { border-color: var(--err); color: var(--err); }
  button.icon { padding: 2px 8px; }
  button:disabled { opacity: .45; cursor: default; }
  label { display: inline-flex; align-items: center; gap: 6px; }
  .tabs { display: flex; gap: 4px; margin-bottom: 16px; border-bottom: 1px solid var(--line); }
  .tabs button { border: none; background: none; border-bottom: 2px solid transparent; border-radius: 0; padding: 8px 14px; color: var(--muted); }
  .tabs button.active { color: var(--text); border-bottom-color: var(--accent); }
  .layout { display: grid; grid-template-columns: 220px 1fr; gap: 16px; align-items: start; }
  @media (max-width: 760px) { .layout { grid-template-columns: 1fr; } }
  .screen-list { display: flex; flex-direction: column; gap: 4px; }
  .screen-list button { text-align: left; }
  .screen-list button.active { border-color: var(--accent); background: color-mix(in srgb, var(--accent) 12%, var(--card)); }
  .screen-list small { color: var(--muted); display: block; font-size: 12px; }
  .fields { display: flex; flex-wrap: wrap; gap: 10px 16px; align-items: end; margin-bottom: 14px; }
  .field { display: flex; flex-direction: column; align-items: stretch; gap: 3px; font-size: 13px; color: var(--muted); }
  .field input[type=number] { align-self: flex-start; }
  .field input, .field select { color: var(--text); }
  .preview { background: #000; border-radius: 8px; aspect-ratio: 16 / 9; max-height: 260px; width: 100%; display: flex; flex-direction: column; gap: 2px; padding: 2px; margin-bottom: 16px; }
  .preview .prow { display: flex; gap: 2px; min-height: 0; }
  .preview .pcell { background: #111; border-radius: 3px; color: #ddd; font-size: 12px; padding: 4px 6px; overflow: hidden; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; min-width: 0; }
  .preview .pcell b { font-size: 11px; color: #888; font-weight: 500; }
  .row-card { border: 1px solid var(--line); border-radius: 8px; padding: 10px; margin-bottom: 10px; background: var(--soft); }
  .row-head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
  .row-head .spacer { flex: 1; }
  .cells { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 10px; align-items: start; }
  .cell-card { background: var(--card); border: 1px solid var(--line); border-radius: 8px; padding: 10px; display: flex; flex-direction: column; gap: 8px; }
  .cell-card .top { display: flex; gap: 6px; align-items: center; }
  .cell-card .top select { flex: 1; }
  .cell-card .field select, .cell-card .field input[type=text] { width: 100%; }
  .checks { max-height: 150px; overflow: auto; border: 1px solid var(--line); border-radius: 6px; padding: 4px 8px; display: flex; flex-direction: column; gap: 2px; font-size: 13px; }
  .checks .grp { color: var(--muted); font-size: 11px; text-transform: uppercase; margin-top: 4px; }
  .add-cell { border: 1px dashed var(--line); background: none; border-radius: 8px; min-height: 60px; color: var(--muted); }
  table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
  th, td { padding: 5px 8px; border-bottom: 1px solid var(--line); text-align: left; white-space: nowrap; }
  th { color: var(--muted); font-weight: 500; font-size: 13px; }
  td.grp { color: var(--muted); font-size: 12px; text-transform: uppercase; padding-top: 14px; }
  .wrap { overflow-x: auto; }
  .live { display: inline-block; min-width: 90px; padding: 2px 8px; border-radius: 5px; background: #000; color: #fff; text-align: right; font-weight: 600; }
  .colors { display: flex; flex-wrap: wrap; gap: 14px 28px; }
  .colors label { flex-direction: column; align-items: flex-start; gap: 4px; font-size: 13px; color: var(--muted); }
  .colors input[type=color] { width: 56px; height: 34px; border: 1px solid var(--line); border-radius: 6px; background: none; padding: 2px; }
  .swatch { display: flex; gap: 2px; background: #000; padding: 10px 14px; border-radius: 8px; font-weight: 700; font-size: 24px; margin-top: 14px; width: fit-content; }
  .swatch span { padding: 0 10px; }
  .savebar { position: fixed; left: 0; right: 0; bottom: 0; background: var(--card); border-top: 1px solid var(--line); padding: 10px 16px; display: flex; justify-content: center; }
  .savebar > div { max-width: 1200px; width: 100%; display: flex; align-items: center; gap: 12px; }
  .savebar .msg { flex: 1; color: var(--muted); }
  .savebar .msg.err { color: var(--err); } .savebar .msg.ok { color: var(--ok); }
  .login { max-width: 360px; margin: 12vh auto; }
  .login form { display: flex; flex-direction: column; gap: 12px; }
  .error { color: var(--err); }
  .hint { color: var(--muted); font-size: 13px; }
  .channels { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; }
  .channels .card { margin-bottom: 0; display: flex; flex-direction: column; gap: 10px; }
  .channels .field input, .channels .field select { width: 100%; }
  .channel-head { display: flex; justify-content: space-between; align-items: center; }
  .events { margin: 0; padding-left: 18px; } .events li { margin-bottom: 4px; }
  .events time { color: var(--muted); font-variant-numeric: tabular-nums; margin-right: 8px; }
  .lvl-alarm { color: var(--err); } .lvl-warning { color: #b45309; }
  #diag-table td, #diag-table th { text-align: right; font-family: ui-monospace, monospace; font-size: 13px; }
  #diag-table td:first-child, #diag-table th:first-child { text-align: left; }
  .channels { margin-bottom: 16px; }
  .presets { display: flex; flex-wrap: wrap; gap: 6px; }
  .presets button.active { border-color: var(--accent); background: color-mix(in srgb, var(--accent) 12%, var(--card)); }
  dl.info { display: grid; grid-template-columns: max-content 1fr; gap: 4px 16px; margin: 16px 0 0; font-size: 14px; }
  dl.info dt { color: var(--muted); } dl.info dd { margin: 0; }
  .warn { color: #b45309; }
  .badge { display: inline-block; font-size: 11px; padding: 1px 7px; border-radius: 99px; border: 1px solid var(--line); color: var(--muted); }
  .badge.ok { color: var(--ok); border-color: var(--ok); }
  #conn-fields select { min-width: 280px; }
  #meter-test td, #meter-test th { padding: 4px 8px; }
  #meter-test td:nth-child(2) { text-align: right; font-variant-numeric: tabular-nums; }
  [hidden] { display: none !important; }
</style>
</head>
<body>
<?php if (!$loggedIn): ?>
<main>
  <div class="card login">
    <h2>Konfiguracja – logowanie</h2>
    <form method="post" action="admin.php?action=login">
      <input type="password" name="password" placeholder="Hasło" autofocus required>
      <?php if ($error): ?><div class="error"><?= $h($error) ?></div><?php endif ?>
      <button class="primary" type="submit">Zaloguj</button>
      <a href="./" class="hint">← strona główna</a>
    </form>
  </div>
</main>
<?php else:
    try {
        $settingsForMeter = Settings::load();
    } catch (Throwable $e) {
        $settingsForMeter = [];
    }
    $activeConnection = Settings::connection($settingsForMeter, $config);
    try {
        $definition = Meter::load($activeConnection['type']);
    } catch (Throwable $e) {
        $definition = Meter::load($config['meter_type']);
    }
    try {
        $settings = Settings::load();
        $settingsError = null;
    } catch (Throwable $e) {
        $settings = Settings::defaults();
        $settingsError = $e->getMessage();
    }
    $boot = [
        'settings' => $settings,
        'registers' => Meter::describeDefinition($definition) + Demand::registers($config['demand_minutes']),
        'modbus' => ['host' => $activeConnection['host'], 'port' => $activeConnection['port'], 'unit' => $activeConnection['unit'], 'type' => $activeConnection['type']],
        'envConnection' => ['type' => $config['meter_type'], 'host' => $config['host'], 'port' => $config['port'], 'unit' => $config['unit_id'], 'timeout' => $config['timeout']],
        'meters' => Meter::available(),
        'envInterval' => $config['interval'],
        'intervalRange' => [Settings::INTERVAL_MIN, Settings::INTERVAL_MAX],
        'demandMinutes' => $config['demand_minutes'],
        'meter' => $definition['name'],
        'csrf' => $_SESSION['csrf'],
        'defaultPassword' => Settings::isDefaultPassword(),
        'maxWindow' => Settings::MAX_WINDOW,
    ];
?>
<main>
  <header>
    <h1>Konfiguracja · <?= $h($definition['name']) ?></h1>
    <a href="./">Strona główna</a>
    <form method="post" action="admin.php?action=logout"><button type="submit">Wyloguj</button></form>
  </header>
  <div class="banner" id="pw-banner" <?= $boot['defaultPassword'] ? '' : 'hidden' ?>>
    Używasz domyślnego hasła <b><?= Settings::DEFAULT_PASSWORD ?></b> – zmień je w zakładce „Hasło”.
  </div>
  <?php if ($settingsError): ?><div class="banner">Błąd pliku ustawień, wczytano domyślne: <?= $h($settingsError) ?></div><?php endif ?>

  <nav class="tabs">
    <button data-tab="screens" class="active">Ekrany</button>
    <button data-tab="thresholds">Progi i kolory</button>
    <button data-tab="notifications">Powiadomienia</button>
    <button data-tab="reading">Odczyt</button>
    <button data-tab="diagnostics">Diagnostyka</button>
    <button data-tab="password">Hasło</button>
  </nav>

  <section data-panel="screens">
    <div class="layout">
      <div class="card screen-list" id="screen-list"></div>
      <div class="card" id="screen-editor"></div>
    </div>
  </section>

  <section data-panel="thresholds" hidden>
    <div class="card">
      <h2>Kolory</h2>
      <div class="colors" id="colors"></div>
      <div class="swatch" id="swatch"></div>
    </div>
    <div class="card">
      <h2>Progi</h2>
      <p class="hint">Puste pole = brak progu. Górne progi działają dla wartości ≥ progu, dolne dla ≤ progu.
        „Oddawanie” koloruje wartości ujemne (np. moc oddawana do sieci). Kolumna „Teraz” pokazuje bieżącą wartość w kolorze wynikającym z ustawień.</p>
      <div class="wrap"><table id="thresholds"></table></div>
    </div>
  </section>

  <section data-panel="notifications" hidden>
    <div class="card" id="notify-general"></div>
    <div class="channels" id="notify-channels"></div>
    <div class="card">
      <h2>Ostatnie zdarzenia</h2>
      <ul class="events" id="events"></ul>
    </div>
  </section>

  <section data-panel="reading" hidden>
    <div class="card" id="connection"></div>
    <div class="card" id="reading"></div>
  </section>

  <section data-panel="diagnostics" hidden>
    <div class="card">
      <h2>Odczyt rejestrów Modbus</h2>
      <p class="hint">Bezpośredni odczyt z dowolnego urządzenia – np. do sprawdzenia mapy rejestrów nowego licznika przed przełączeniem
        <code>METER_TYPE</code>. Adres jak w pliku licznika (od 0; dziesiętnie lub 0x…). Przy bramkach RS485 odczyt idzie równolegle z pollerem.</p>
      <form id="diag-form" class="fields">
        <label class="field">Host<input type="text" name="host" required></label>
        <label class="field">Port<input type="number" name="port" min="1" max="65535" required></label>
        <label class="field">Unit ID<input type="number" name="unit" min="0" max="255" required></label>
        <label class="field">Funkcja<select name="function"><option value="3">03 – holding</option><option value="4">04 – input</option></select></label>
        <label class="field">Adres<input type="text" name="address" value="0" required style="width:90px"></label>
        <label class="field">Liczba rejestrów<input type="number" name="count" value="20" min="1" max="125" required></label>
        <button class="primary" type="submit">Odczytaj</button>
      </form>
      <div id="diag-msg" class="hint"></div>
      <div class="wrap"><table id="diag-table"></table></div>
    </div>
  </section>

  <section data-panel="password" hidden>
    <div class="card" style="max-width:420px">
      <h2>Zmiana hasła</h2>
      <form id="pw-form" style="display:flex;flex-direction:column;gap:10px">
        <input type="password" name="current" placeholder="Aktualne hasło" required autocomplete="current-password">
        <input type="password" name="new" placeholder="Nowe hasło (min. 6 znaków)" required minlength="6" autocomplete="new-password">
        <input type="password" name="repeat" placeholder="Powtórz nowe hasło" required minlength="6" autocomplete="new-password">
        <button class="primary" type="submit">Zmień hasło</button>
        <div id="pw-msg" class="hint"></div>
      </form>
      <p class="hint">Hasło jest zapisywane jako hash w pliku <code>config/admin_password.txt</code>.
        Usunięcie tego pliku przywraca hasło domyślne.</p>
    </div>
  </section>
</main>

<div class="savebar">
  <div>
    <span class="msg" id="save-msg">Brak zmian</span>
    <button id="revert" disabled>Cofnij zmiany</button>
    <button id="save" class="primary" disabled>Zapisz</button>
  </div>
</div>

<script>const BOOT = <?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PRESERVE_ZERO_FRACTION) ?>;</script>
<script src="common.js"></script>
<script src="admin.js"></script>
<?php endif ?>
</body>
</html>
