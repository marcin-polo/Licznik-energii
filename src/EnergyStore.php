<?php

declare(strict_types=1);

/**
 * Baza SQLite: okresy rozliczeniowe (domyślnie 15 min) ze średnią mocą
 * i stanami liczników energii oraz dziennik zdarzeń (alarmy, powiadomienia).
 */
final class EnergyStore
{
    /** kolumna => klucz wartości licznika (sumowane są też taryfy: klucz_t1, klucz_t2…) */
    public const COUNTERS = [
        'e_imp' => 'energy_active_import',
        'e_exp' => 'energy_active_export',
        'q_imp' => 'energy_reactive_import',
        'q_exp' => 'energy_reactive_export',
    ];
    private const DELTAS = ['e_imp' => 'd_imp', 'e_exp' => 'd_exp', 'q_imp' => 'd_qimp', 'q_exp' => 'd_qexp'];

    private PDO $db;

    public function __construct(string $file, public readonly int $period)
    {
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }
        $this->db = new PDO('sqlite:' . $file, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->db->exec('PRAGMA journal_mode = WAL');
        $this->db->exec('PRAGMA busy_timeout = 3000');
        $this->db->exec('CREATE TABLE IF NOT EXISTS intervals (
            ts INTEGER PRIMARY KEY,          -- początek okresu (unix)
            period INTEGER NOT NULL,         -- długość okresu [s]
            covered_s REAL NOT NULL,         -- ile sekund okresu faktycznie odczytano
            samples INTEGER NOT NULL,
            p_avg REAL, p_min REAL, p_max REAL, p1_avg REAL, p2_avg REAL, p3_avg REAL,
            e_imp REAL, e_exp REAL, q_imp REAL, q_exp REAL,      -- stany liczników na koniec okresu
            d_imp REAL, d_exp REAL, d_qimp REAL, d_qexp REAL     -- przyrost w obrębie okresu
        )');
        $this->db->exec('CREATE TABLE IF NOT EXISTS events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ts INTEGER NOT NULL, key TEXT, level TEXT, message TEXT, delivery TEXT
        )');
        $this->db->exec('CREATE INDEX IF NOT EXISTS events_ts ON events (ts)');
        // Migracja: identyfikator licznika (typ@host:port/unit) – po zmianie licznika stany energii nie są porównywalne.
        $columns = array_column($this->db->query('PRAGMA table_info(intervals)')->fetchAll(), 'name');
        if (!in_array('meter', $columns, true)) {
            $this->db->exec('ALTER TABLE intervals ADD COLUMN meter TEXT');
        }
    }

    /** Wiersze sprzed migracji przypisuje do bieżącego licznika (wywołuje poller przy starcie). */
    public function claimUnassigned(string $meter): void
    {
        $this->db->prepare('UPDATE intervals SET meter = ? WHERE meter IS NULL')->execute([$meter]);
    }

    /** Stany liczników z bieżących wartości (null, gdy licznik ich nie ma). */
    public static function counters(array $values): array
    {
        $out = [];
        foreach (self::COUNTERS as $col => $key) {
            $sum = null;
            foreach ($values as $k => $v) {
                if ($v !== null && preg_match('/^' . $key . '(_t\d+)?$/', $k)) {
                    $sum = ($sum ?? 0) + $v;
                }
            }
            $out[$col] = $sum;
        }
        return $out;
    }

    public function insert(array $row): void
    {
        $cols = array_keys($row);
        $stmt = $this->db->prepare(sprintf(
            'INSERT OR REPLACE INTO intervals (%s) VALUES (%s)',
            implode(',', $cols),
            implode(',', array_map(fn ($c) => ":$c", $cols)),
        ));
        $stmt->execute($row);
    }

    public function rowBefore(int $ts, ?string $meter = null): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM intervals WHERE ts < ? AND (? IS NULL OR meter = ?) ORDER BY ts DESC LIMIT 1');
        $stmt->execute([$ts, $meter, $meter]);
        return $stmt->fetch() ?: null;
    }

    public function rows(int $from, int $to): array
    {
        $stmt = $this->db->prepare('SELECT * FROM intervals WHERE ts >= ? AND ts < ? ORDER BY ts');
        $stmt->execute([$from, $to]);
        return $stmt->fetchAll();
    }

    /** Stany liczników danego licznika na chwilę $ts (koniec ostatniego okresu przed $ts). */
    public function baseline(int $ts, ?string $meter = null): ?array
    {
        if ($row = $this->rowBefore($ts, $meter)) {
            return array_intersect_key($row, self::COUNTERS);
        }
        $stmt = $this->db->prepare('SELECT * FROM intervals WHERE ts >= ? AND (? IS NULL OR meter = ?) ORDER BY ts LIMIT 1');
        $stmt->execute([$ts, $meter, $meter]);
        if ($row = $stmt->fetch()) {
            $out = [];
            foreach (self::DELTAS as $col => $d) {
                $out[$col] = $row[$col] === null ? null : $row[$col] - ($row[$d] ?? 0);
            }
            return $out;
        }
        return null;
    }

    /** Najwyższa średnia moc pobierana w pełnym okresie z przedziału. */
    public function peak(int $from, int $to): ?array
    {
        $stmt = $this->db->prepare('SELECT ts, p_avg FROM intervals WHERE ts >= ? AND ts < ? AND covered_s >= period * 0.8 AND p_avg IS NOT NULL ORDER BY p_avg DESC LIMIT 1');
        $stmt->execute([$from, $to]);
        return $stmt->fetch() ?: null;
    }

    public function addEvent(int $ts, ?string $key, string $level, string $message, string $delivery): void
    {
        $this->db->prepare('INSERT INTO events (ts, key, level, message, delivery) VALUES (?, ?, ?, ?, ?)')
            ->execute([$ts, $key, $level, $message, $delivery]);
    }

    public function events(int $from, int $to, int $limit = 200): array
    {
        $stmt = $this->db->prepare('SELECT ts, key, level, message, delivery FROM events WHERE ts >= ? AND ts < ? ORDER BY ts DESC LIMIT ?');
        $stmt->execute([$from, $to, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Raport: 'day' (okresy 15-min), 'month' (dni), 'year' (miesiące).
     * Energia w kubełku = przyrost stanu licznika względem poprzedniego zapisu,
     * więc energia z czasu przerwy w odczytach trafia do pierwszego okresu po przerwie.
     * $live – bieżący, niezamknięty okres (z pollera), żeby raport obejmował „teraz”.
     */
    public function report(string $kind, string $date, ?array $live = null): array
    {
        [$from, $to, $slots, $bucketOf, $labelOf] = $this->range($kind, $date);

        $rows = $this->rows($from, $to);
        if ($live && $live['ts'] >= $from && $live['ts'] < $to && (!$rows || end($rows)['ts'] < $live['ts'])) {
            $rows[] = $live + ['live' => true];
        }

        $buckets = [];
        foreach ($slots as $slot) {
            $buckets[$slot] = ['key' => $slot, 'label' => $labelOf($slot), 'e_imp' => null, 'e_exp' => null, 'q_imp' => null, 'q_exp' => null, 'peak' => null, 'peak_ts' => null];
        }

        // Punkt odniesienia: ostatni zapis przed okresem. Bez niego / po zmianie licznika – przyrost z samego okresu (d_*).
        $before = $this->rowBefore($from);
        $prev = $before ? array_intersect_key($before, self::COUNTERS) : [];
        $prevMeter = $before['meter'] ?? null;
        $totals = ['e_imp' => null, 'e_exp' => null, 'q_imp' => null, 'q_exp' => null];
        $peak = null;
        foreach ($rows as $row) {
            $b = &$buckets[$bucketOf((int) $row['ts'])];
            $meter = $row['meter'] ?? null;
            if ($meter !== null && $prevMeter !== null && $meter !== $prevMeter) {
                $prev = [];
            }
            $prevMeter = $meter ?? $prevMeter;
            foreach (self::DELTAS as $col => $d) {
                if ($row[$col] === null) {
                    continue;
                }
                $delta = isset($prev[$col]) ? $row[$col] - $prev[$col] : ($row[$d] ?? 0);
                $delta = max(0.0, (float) $delta);   // wymiana licznika / reset
                $b[$col] = ($b[$col] ?? 0) + $delta;
                $totals[$col] = ($totals[$col] ?? 0) + $delta;
                $prev[$col] = $row[$col];
            }
            $complete = empty($row['live']) && $row['covered_s'] >= $row['period'] * 0.8;
            if ($complete && $row['p_avg'] !== null && ($b['peak'] === null || $row['p_avg'] > $b['peak'])) {
                $b['peak'] = $row['p_avg'];
                $b['peak_ts'] = (int) $row['ts'];
            }
            if ($kind === 'day') {
                foreach (['p_avg', 'p_min', 'p_max', 'p1_avg', 'p2_avg', 'p3_avg'] as $c) {
                    $b[$c] = $row[$c];
                }
                $b['live'] = !empty($row['live']);
            }
            if ($b['peak'] !== null && ($peak === null || $b['peak'] > $peak['w'])) {
                $peak = ['w' => $b['peak'], 'ts' => $b['peak_ts']];
            }
            unset($b);
        }

        // Zaokrąglenie szumu z odejmowania stanów liczników.
        $round = fn (?float $v, int $d) => $v === null ? null : round($v, $d);
        foreach ($buckets as &$b) {
            foreach (array_keys(self::COUNTERS) as $col) {
                $b[$col] = $round($b[$col], 4);
            }
            foreach (['peak', 'p_avg', 'p_min', 'p_max', 'p1_avg', 'p2_avg', 'p3_avg'] as $c) {
                if (array_key_exists($c, $b)) {
                    $b[$c] = $round($b[$c], 1);
                }
            }
        }
        unset($b);
        $totals = array_map(fn ($v) => $round($v, 4), $totals);
        if ($peak) {
            $peak['w'] = round($peak['w'], 1);
        }

        return ['kind' => $kind, 'date' => $date, 'from' => $from, 'to' => $to, 'period' => $this->period,
            'totals' => $totals, 'peak' => $peak, 'buckets' => array_values($buckets)];
    }

    private function range(string $kind, string $date): array
    {
        switch ($kind) {
            case 'day':
                $start = new DateTimeImmutable(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : 'today');
                $from = $start->getTimestamp();
                $to = $start->modify('+1 day')->getTimestamp();
                return [$from, $to, range($from, $to - 1, $this->period),
                    fn (int $ts) => intdiv($ts, $this->period) * $this->period,
                    fn (int $ts) => date('H:i', $ts)];
            case 'month':
                $start = new DateTimeImmutable((preg_match('/^\d{4}-\d{2}$/', $date) ? $date : date('Y-m')) . '-01');
                $end = $start->modify('+1 month');
                $slots = [];
                for ($d = $start; $d < $end; $d = $d->modify('+1 day')) {
                    $slots[] = $d->format('Y-m-d');
                }
                return [$start->getTimestamp(), $end->getTimestamp(), $slots,
                    fn (int $ts) => date('Y-m-d', $ts),
                    fn (string $d) => (string) (int) substr($d, 8)];
            case 'year':
                $year = preg_match('/^\d{4}$/', $date) ? $date : date('Y');
                $start = new DateTimeImmutable("$year-01-01");
                return [$start->getTimestamp(), $start->modify('+1 year')->getTimestamp(),
                    array_map(fn ($m) => sprintf('%s-%02d', $year, $m), range(1, 12)),
                    fn (int $ts) => date('Y-m', $ts),
                    fn (string $m) => ['sty', 'lut', 'mar', 'kwi', 'maj', 'cze', 'lip', 'sie', 'wrz', 'paź', 'lis', 'gru'][(int) substr($m, 5) - 1]];
        }
        throw new InvalidArgumentException("Nieznany rodzaj raportu: $kind");
    }
}
