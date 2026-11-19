<?php

declare(strict_types=1);

/**
 * Średnia moc w okresach rozliczeniowych (domyślnie 15 min), szczyty i bilans energii
 * dziś / w miesiącu. Wyniki są dokładane do odczytów jako wartości „wirtualne”,
 * więc działają na ekranach, w progach i powiadomieniach jak zwykłe wartości licznika.
 */
final class Demand
{
    private ?int $start = null;
    private int $samples = 0;
    private float $weight = 0.0;      // suma czasów próbek [s] – średnie są ważone czasem
    private float $sum = 0.0;
    private ?float $lastT = null;
    private array $phaseSum = [0.0, 0.0, 0.0];
    private ?float $min = null;
    private ?float $max = null;
    private ?array $startCounters = null;
    private ?array $lastCounters = null;
    private ?array $firstCounters = null;

    private ?float $previous = null;
    private ?array $peakDay = null;
    private ?array $peakMonth = null;
    private string $day = '';
    private string $month = '';
    private ?array $baseDay = null;
    private ?array $baseMonth = null;

    public function __construct(private readonly EnergyStore $store, private readonly string $meter)
    {
        $last = $store->rowBefore(PHP_INT_MAX);
        $now = time();
        if ($last && $last['ts'] >= $now - 2 * $store->period) {
            $this->previous = $last['p_avg'];
        }
    }

    public static function registers(int $minutes): array
    {
        $p = "{$minutes}-min";
        $power = fn (string $label) => ['label' => $label, 'unit' => 'W', 'group' => "Moc $p", 'phase' => null, 'precision' => 0];
        $energy = fn (string $label) => ['label' => $label, 'unit' => 'kWh', 'group' => 'Bilans energii', 'phase' => null, 'precision' => 2];
        return [
            'demand_current' => $power("Moc $p – bieżący okres"),
            'demand_forecast' => $power("Moc $p – prognoza okresu"),
            'demand_previous' => $power("Moc $p – poprzedni okres"),
            'demand_peak_day' => $power("Szczyt $p dziś"),
            'demand_peak_month' => $power("Szczyt $p w miesiącu"),
            'energy_import_today' => $energy('Pobrane dziś'),
            'energy_export_today' => $energy('Oddane dziś'),
            'energy_import_month' => $energy('Pobrane w miesiącu'),
            'energy_export_month' => $energy('Oddane w miesiącu'),
        ];
    }

    /** Dodaje odczyt i zwraca wartości wirtualne. $interval – ustawiony interwał odczytu. */
    public function update(float $t, array $values, float $interval): array
    {
        // Waga próbki = czas od poprzedniego odczytu (przerwy w komunikacji obcięte do 10 s).
        $dt = $this->lastT === null ? $interval : max(0.05, min(10.0, $t - $this->lastT));
        $this->lastT = $t;

        $period = $this->store->period;
        $start = intdiv((int) $t, $period) * $period;
        if ($this->start !== null && $start !== $this->start) {
            $this->finish();
        }
        $counters = EnergyStore::counters($values);
        if ($this->start !== $start) {
            $this->start = $start;
            $this->samples = 0;
            $this->weight = 0.0;
            $this->sum = 0.0;
            $this->phaseSum = [0.0, 0.0, 0.0];
            $this->min = $this->max = null;
            // Ciągłość z poprzednim okresem – energia między okresami nie ginie.
            $this->startCounters = $this->lastCounters ?? $counters;
        }

        $p = $values['power_active_total'] ?? null;
        if ($p !== null) {
            $this->samples++;
            $this->weight += $dt;
            $this->sum += $p * $dt;
            $this->min = min($this->min ?? $p, $p);
            $this->max = max($this->max ?? $p, $p);
            foreach ([1, 2, 3] as $i) {
                $this->phaseSum[$i - 1] += ($values["power_active_l$i"] ?? 0) * $dt;
            }
        }
        foreach ($counters as $col => $v) {
            if ($v !== null) {
                $this->lastCounters[$col] = $v;
                $this->firstCounters[$col] ??= $v;
            }
        }
        $this->refresh((int) $t);

        $avg = $this->weight > 0 ? $this->sum / $this->weight : null;
        $elapsed = min($period, max(1.0, $t - $start));
        $forecast = $avg !== null && $p !== null ? ($avg * $elapsed + $p * ($period - $elapsed)) / $period : null;
        $since = fn (?array $base, string $col) => isset($base[$col], $this->lastCounters[$col])
            ? round(max(0, $this->lastCounters[$col] - $base[$col]), 3) : null;

        return [
            'demand_current' => $avg === null ? null : round($avg),
            'demand_forecast' => $forecast === null ? null : round($forecast),
            'demand_previous' => $this->previous === null ? null : round($this->previous),
            'demand_peak_day' => $this->peakDay ? round($this->peakDay['p_avg']) : null,
            'demand_peak_month' => $this->peakMonth ? round($this->peakMonth['p_avg']) : null,
            'energy_import_today' => $since($this->baseDay, 'e_imp'),
            'energy_export_today' => $since($this->baseDay, 'e_exp'),
            'energy_import_month' => $since($this->baseMonth, 'e_imp'),
            'energy_export_month' => $since($this->baseMonth, 'e_exp'),
        ];
    }

    /** Bieżący, niezamknięty okres – do raportu „na teraz”. */
    public function liveRow(): ?array
    {
        return $this->start === null ? null : $this->row();
    }

    public function meta(): array
    {
        return [
            'period' => $this->store->period,
            'start' => $this->start,
            'peak_day_ts' => $this->peakDay['ts'] ?? null,
            'peak_month_ts' => $this->peakMonth['ts'] ?? null,
        ];
    }

    /** Zamyka bieżący (niepełny) okres – przy zmianie licznika. */
    public function flush(): void
    {
        $this->finish();
        $this->start = null;
    }

    private function finish(): void
    {
        if ($this->samples === 0) {
            return;
        }
        $row = $this->row();
        $this->store->insert($row);
        $this->previous = $row['p_avg'];
        $this->day = '';   // przelicz szczyty i bazy
    }

    private function row(): array
    {
        $n = $this->weight > 0 ? $this->weight : 1.0;
        $row = [
            'ts' => $this->start,
            'meter' => $this->meter,
            'period' => $this->store->period,
            'covered_s' => min($this->store->period, $this->weight),
            'samples' => $this->samples,
            'p_avg' => $this->weight > 0 ? $this->sum / $n : null,
            'p_min' => $this->min,
            'p_max' => $this->max,
            'p1_avg' => $this->phaseSum[0] / $n,
            'p2_avg' => $this->phaseSum[1] / $n,
            'p3_avg' => $this->phaseSum[2] / $n,
        ];
        foreach (['e_imp' => 'd_imp', 'e_exp' => 'd_exp', 'q_imp' => 'd_qimp', 'q_exp' => 'd_qexp'] as $col => $d) {
            $row[$col] = $this->lastCounters[$col] ?? null;
            $row[$d] = isset($row[$col], $this->startCounters[$col]) ? $row[$col] - $this->startCounters[$col] : null;
        }
        return $row;
    }

    /** Szczyty i stany liczników na początek dnia / miesiąca – po zmianie doby lub zamknięciu okresu. */
    private function refresh(int $t): void
    {
        $day = date('Y-m-d', $t);
        if ($day === $this->day) {
            return;
        }
        $dayStart = strtotime($day);
        $monthStart = strtotime(date('Y-m-01', $t));
        if (date('Y-m', $t) !== $this->month || $this->baseMonth === null) {
            $this->month = date('Y-m', $t);
            $this->baseMonth = $this->store->baseline($monthStart, $this->meter) ?? $this->firstCounters;
        }
        $this->day = $day;
        $this->baseDay = $this->store->baseline($dayStart, $this->meter) ?? $this->firstCounters;
        $this->peakDay = $this->store->peak($dayStart, strtotime("+1 day", $dayStart));
        $this->peakMonth = $this->store->peak($monthStart, strtotime('+1 month', $monthStart));
    }
}
