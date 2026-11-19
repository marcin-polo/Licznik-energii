<?php

declare(strict_types=1);

/**
 * Licznik opisany definicją z katalogu meters/. Grupuje sąsiednie rejestry
 * w bloki, żeby jeden cykl odczytu wymagał jak najmniej zapytań.
 */
final class Meter
{
    private const SIZES = [
        'int16' => 1, 'uint16' => 1,
        'int32' => 2, 'uint32' => 2, 'float32' => 2,
        'int64' => 4, 'uint64' => 4, 'float64' => 4,
    ];

    /** @var list<array{function:int, start:int, count:int, registers:array<string,array>}> */
    private array $blocks;

    public function __construct(
        private readonly array $definition,
        private readonly ModbusTcpClient $client,
        private readonly int $unitId,
    ) {
        $this->blocks = $this->planBlocks();
    }

    /** @return array<string, array{name:string, description:string, verified:bool}> */
    public static function available(): array
    {
        $out = [];
        foreach (glob(__DIR__ . '/../meters/*.php') as $file) {
            $type = basename($file, '.php');
            try {
                $def = self::load($type);
                $out[$type] = ['name' => $def['name'], 'description' => $def['description'] ?? '', 'verified' => !empty($def['verified']), 'registers' => count($def['registers'])];
            } catch (Throwable $e) {
                $out[$type] = ['name' => "$type (błąd pliku)", 'description' => $e->getMessage(), 'verified' => false, 'registers' => 0];
            }
        }
        uasort($out, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        return $out;
    }

    public static function load(string $type): array
    {
        if (!preg_match('/^[a-z0-9_]+$/', $type) || !is_file($file = __DIR__ . "/../meters/$type.php")) {
            $available = array_map(fn ($f) => basename($f, '.php'), glob(__DIR__ . '/../meters/*.php'));
            throw new InvalidArgumentException("Nieznany typ licznika '$type'. Dostępne: " . implode(', ', $available));
        }
        return require $file;
    }

    /** Opis rejestrów (bez adresów) dla interfejsu WWW. */
    public function describe(): array
    {
        return self::describeDefinition($this->definition);
    }

    public static function describeDefinition(array $definition): array
    {
        $out = [];
        foreach ($definition['registers'] as $key => $reg) {
            $out[$key] = [
                'label' => $reg['label'],
                'unit' => $reg['unit'] ?? '',
                'group' => $reg['group'] ?? 'Inne',
                'phase' => $reg['phase'] ?? null,
                'precision' => $reg['precision'] ?? 2,
            ];
        }
        return $out;
    }

    public function blockCount(): int
    {
        return count($this->blocks);
    }

    /** @return array<string, float|int|null> */
    public function read(): array
    {
        $wordOrder = $this->definition['word_order'] ?? 'big';
        $values = [];
        foreach ($this->blocks as $block) {
            $raw = $this->client->readRegisters($this->unitId, $block['function'], $block['start'], $block['count']);
            foreach ($block['registers'] as $key => $reg) {
                $words = array_slice($raw, $reg['address'] - $block['start'], self::SIZES[$reg['type']]);
                $value = self::decode($words, $reg['type'], $wordOrder);
                if ($value !== null) {
                    $value = $value * ($reg['scale'] ?? 1);
                }
                $values[$key] = $value;
            }
        }
        return $values;
    }

    private function planBlocks(): array
    {
        $maxBlock = min(125, $this->definition['max_block'] ?? 60);
        $maxGap = $this->definition['max_gap'] ?? 0;
        $defaultFunction = $this->definition['function'] ?? 3;

        $registers = $this->definition['registers'];
        foreach ($registers as $key => $reg) {
            if (!isset(self::SIZES[$reg['type']])) {
                throw new InvalidArgumentException("Rejestr '$key': nieznany typ '{$reg['type']}'");
            }
        }
        uasort($registers, fn ($a, $b) => [$a['function'] ?? $defaultFunction, $a['address']] <=> [$b['function'] ?? $defaultFunction, $b['address']]);

        $blocks = [];
        $current = null;
        foreach ($registers as $key => $reg) {
            $function = $reg['function'] ?? $defaultFunction;
            $end = $reg['address'] + self::SIZES[$reg['type']];
            $fits = $current !== null
                && $current['function'] === $function
                && $reg['address'] <= $current['start'] + $current['count'] + $maxGap
                && $end - $current['start'] <= $maxBlock;

            if (!$fits) {
                if ($current !== null) {
                    $blocks[] = $current;
                }
                $current = ['function' => $function, 'start' => $reg['address'], 'count' => 0, 'registers' => []];
            }
            $current['count'] = max($current['count'], $end - $current['start']);
            $current['registers'][$key] = $reg;
        }
        if ($current !== null) {
            $blocks[] = $current;
        }
        return $blocks;
    }

    private static function decode(array $words, string $type, string $wordOrder): float|int|null
    {
        if ($wordOrder === 'little' && count($words) > 1) {
            $words = array_reverse($words);
        }
        $bytes = pack('n*', ...$words);

        $value = match ($type) {
            'uint16' => unpack('n', $bytes)[1],
            'int16' => unpack('n', $bytes)[1] - (($words[0] & 0x8000) ? 0x10000 : 0),
            'uint32' => unpack('N', $bytes)[1],
            'int32' => unpack('N', $bytes)[1] - (($words[0] & 0x8000) ? 0x100000000 : 0),
            // float32 ma ~7 cyfr znaczących; obcinamy szum z konwersji do double.
            'float32' => (float) sprintf('%.7g', unpack('G', $bytes)[1]),
            'float64' => unpack('E', $bytes)[1],
            // PHP ma tylko int ze znakiem; uint64 > 2^63 nie występuje w praktyce (liczniki energii).
            'int64', 'uint64' => unpack('J', $bytes)[1],
        };

        // NaN/Inf nie da się zapisać w JSON.
        return is_float($value) && !is_finite($value) ? null : $value;
    }
}
