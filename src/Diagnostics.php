<?php

declare(strict_types=1);

/**
 * Odczyt dowolnych rejestrów na potrzeby zakładki „Diagnostyka”.
 * Dla licznika z konfiguracji zlecenie przekazywane jest pollerowi przez pliki
 * we wspólnym katalogu stanu (bramka może przyjmować tylko jedno połączenie).
 */
final class Diagnostics
{
    private const REQUEST = 'diag_request.json';

    /**
     * Zlecenie: surowe rejestry (function/address/count) albo test całego licznika (type) –
     * odczyt wszystkich wartości z pliku definicji, żeby przed zapisaniem sprawdzić, czy mapa pasuje.
     */
    public static function execute(ModbusTcpClient $client, array $r, bool $close): array
    {
        $t = microtime(true);
        try {
            if (isset($r['type'])) {
                $definition = Meter::load($r['type']);
                $values = (new Meter($definition, $client, $r['unit']))->read();
                return ['ok' => true, 'meter' => $definition['name'], 'registers' => Meter::describeDefinition($definition),
                    'values' => $values, 'ms' => round((microtime(true) - $t) * 1000, 1)];
            }
            $registers = $client->readRegisters($r['unit'], $r['function'], $r['address'], $r['count']);
            return ['ok' => true, 'registers' => $registers, 'ms' => round((microtime(true) - $t) * 1000, 1)];
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        } finally {
            if ($close) {
                $client->close();
            }
        }
    }

    /** Strona WWW: zleca odczyt pollerowi i czeka na wynik. */
    public static function viaPoller(string $dir, array $request): array
    {
        $response = "$dir/diag_response_{$request['id']}.json";
        file_put_contents("$dir/" . self::REQUEST . '.tmp', json_encode($request));
        rename("$dir/" . self::REQUEST . '.tmp', "$dir/" . self::REQUEST);
        $deadline = microtime(true) + 10;
        while (microtime(true) < $deadline) {
            usleep(100_000);
            if (is_file($response)) {
                $data = json_decode((string) file_get_contents($response), true);
                @unlink($response);
                return ($data ?? ['error' => 'Nieczytelna odpowiedź pollera']) + ['via' => 'poller'];
            }
        }
        @unlink("$dir/" . self::REQUEST);
        return ['error' => 'Poller nie odpowiedział w ciągu 10 s – czy działa? (docker compose logs poller)'];
    }

    /** Poller: wykonuje oczekujące zlecenie (raz na cykl). */
    public static function serve(string $dir, ModbusTcpClient $client): void
    {
        $file = "$dir/" . self::REQUEST;
        if (!is_file($file)) {
            return;
        }
        $request = json_decode((string) file_get_contents($file), true);
        @unlink($file);
        if (!is_array($request) || !preg_match('/^[0-9a-f]{16}$/', $request['id'] ?? '')) {
            return;
        }
        $result = self::execute($client, $request, false);
        file_put_contents("$dir/diag_response_{$request['id']}.json", json_encode($result));
    }
}
