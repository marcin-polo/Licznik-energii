<?php

declare(strict_types=1);

/**
 * Pilnuje progów oznaczonych „powiadom” i braku komunikacji z licznikiem.
 * Powiadomienie idzie dopiero, gdy stan utrzymuje się przez zadany czas.
 */
final class AlertMonitor
{
    private const LEVEL_NAMES = ['alarm' => 'ALARM', 'warning' => 'Ostrzeżenie', 'normal' => 'W normie'];

    /** @var array<string, array{level:string, since:float, notified:string, sent:float}> */
    private array $keys = [];
    private ?float $commLostSince = null;
    private bool $commNotified = false;

    public function __construct(
        private readonly EnergyStore $store,
        private readonly string $meterName,
        private readonly Closure $log,
    ) {
    }

    /** Aktywne alarmy (do pokazania w stanie). */
    public function active(): array
    {
        $out = [];
        foreach ($this->keys as $key => $s) {
            if ($s['notified'] !== 'normal') {
                $out[$key] = $s['notified'];
            }
        }
        return $out;
    }

    public function check(float $now, bool $ok, ?array $values, array $registers, array $settings): void
    {
        $cfg = $settings['notifications'] ?? [];
        $enabled = !empty($cfg['enabled']);
        $notifier = new Notifier($cfg);
        $minSeverity = ($cfg['min_level'] ?? 'alarm') === 'warning' ? 1 : 2;
        $delay = (float) ($cfg['delay'] ?? 30);
        $repeat = (float) ($cfg['repeat'] ?? 0) * 60;

        $this->checkComm($now, $ok, $cfg, $enabled, $notifier);
        if (!$ok || $values === null) {
            return;
        }

        foreach ($settings['thresholds'] ?? [] as $key => $t) {
            if (empty($t['notify']) || !isset($registers[$key])) {
                unset($this->keys[$key]);
                continue;
            }
            $s = $this->keys[$key] ??= ['level' => 'normal', 'since' => $now, 'notified' => 'normal', 'sent' => 0.0];
            $value = $values[$key] ?? null;
            $level = Levels::level($value, $t, $s['level']);
            if ($level === 'export') {
                $level = 'normal';
            }
            if ($level !== $s['level']) {
                $s['level'] = $level;
                $s['since'] = $now;
            }
            $effective = Levels::SEVERITY[$level] >= $minSeverity ? $level : 'normal';
            $sustained = $now - $s['since'] >= $delay;

            if ($effective !== $s['notified'] && $sustained) {
                $recovery = $effective === 'normal';
                $s['notified'] = $effective;
                $s['sent'] = $now;
                if (!$recovery || !empty($cfg['recovery'])) {
                    $this->notify($enabled, $notifier, $key, $effective, $value, $registers[$key], $t);
                }
            } elseif ($effective !== 'normal' && $effective === $s['notified'] && $repeat > 0 && $now - $s['sent'] >= $repeat) {
                $s['sent'] = $now;
                $this->notify($enabled, $notifier, $key, $effective, $value, $registers[$key], $t, true);
            }
            $this->keys[$key] = $s;
        }
    }

    private function notify(bool $enabled, Notifier $notifier, string $key, string $level, float|int|null $value, array $reg, array $t, bool $reminder = false): void
    {
        $unit = $reg['unit'] !== '' ? ' ' . $reg['unit'] : '';
        $text = $value === null ? '–' : number_format((float) $value, $reg['precision'], ',', ' ') . $unit;
        $title = sprintf('[%s] %s: %s', $this->meterName, self::LEVEL_NAMES[$level], $reg['label']);
        $message = $level === 'normal'
            ? "{$reg['label']}: $text – w normie"
            : sprintf('%s: %s (próg %s%s)%s', $reg['label'], $text, Levels::describe((float) $value, $t, $level), $unit, $reminder ? ' – nadal trwa' : '');
        $this->emit($enabled, $notifier, $key, $level, $title, $message);
    }

    private function checkComm(float $now, bool $ok, array $cfg, bool $enabled, Notifier $notifier): void
    {
        if (empty($cfg['comm_loss'])) {
            $this->commLostSince = null;
            $this->commNotified = false;
            return;
        }
        if ($ok) {
            if ($this->commNotified) {
                $this->emit($enabled, $notifier, null, 'normal', "[{$this->meterName}] Komunikacja przywrócona", 'Licznik znów odpowiada.');
            }
            $this->commLostSince = null;
            $this->commNotified = false;
            return;
        }
        $this->commLostSince ??= $now;
        $limit = max(10, (float) ($cfg['comm_delay'] ?? 60));
        if (!$this->commNotified && $now - $this->commLostSince >= $limit) {
            $this->commNotified = true;
            $this->emit($enabled, $notifier, null, 'alarm', "[{$this->meterName}] Brak komunikacji z licznikiem", sprintf('Licznik nie odpowiada od %d s.', $now - $this->commLostSince));
        }
    }

    private function emit(bool $enabled, Notifier $notifier, ?string $key, string $level, string $title, string $message): void
    {
        $delivery = $enabled ? Notifier::summary($notifier->send($title, $message, $level)) : 'powiadomienia wyłączone';
        ($this->log)("Zdarzenie: $title – $message ($delivery)");
        try {
            $this->store->addEvent(time(), $key, $level, "$title – $message", $delivery);
        } catch (Throwable $e) {
            ($this->log)('Błąd zapisu zdarzenia: ' . $e->getMessage());
        }
    }
}
