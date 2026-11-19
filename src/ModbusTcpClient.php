<?php

declare(strict_types=1);

/**
 * Minimalny klient Modbus TCP (FC03 / FC04) oparty na strumieniach PHP.
 * Utrzymuje jedno połączenie i odtwarza je po błędzie.
 */
final class ModbusTcpClient
{
    private const EXCEPTIONS = [
        1 => 'niedozwolona funkcja',
        2 => 'niedozwolony adres rejestru',
        3 => 'niedozwolona wartość danych',
        4 => 'błąd urządzenia',
        6 => 'urządzenie zajęte',
        10 => 'gateway: brak ścieżki',
        11 => 'gateway: urządzenie nie odpowiada',
    ];

    /** @var resource|null */
    private $socket = null;
    private int $transactionId = 0;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly float $timeout = 2.0,
    ) {
    }

    public function close(): void
    {
        if ($this->socket !== null) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * @return int[] wartości rejestrów 16-bit (bez znaku)
     */
    public function readRegisters(int $unitId, int $function, int $address, int $count): array
    {
        if ($function !== 3 && $function !== 4) {
            throw new InvalidArgumentException("Nieobsługiwana funkcja Modbus: $function");
        }
        if ($count < 1 || $count > 125) {
            throw new InvalidArgumentException("Liczba rejestrów poza zakresem 1-125: $count");
        }

        $this->socket ?? $this->connect();
        $this->transactionId = ($this->transactionId + 1) & 0xFFFF;

        $pdu = pack('Cnn', $function, $address, $count);
        $request = pack('nnnC', $this->transactionId, 0, strlen($pdu) + 1, $unitId) . $pdu;

        try {
            $this->write($request);
            // Odrzucamy ewentualne spóźnione odpowiedzi na wcześniejsze (przeterminowane) zapytania.
            do {
                $header = unpack('ntid/npid/nlen/Cunit', $this->readExact(7));
                $body = $this->readExact($header['len'] - 1);
            } while ($header['tid'] !== $this->transactionId);
        } catch (Throwable $e) {
            $this->close();
            throw $e;
        }

        $fc = ord($body[0]);
        if ($fc === ($function | 0x80)) {
            $code = ord($body[1] ?? "\0");
            $msg = self::EXCEPTIONS[$code] ?? 'nieznany błąd';
            throw new RuntimeException(sprintf('Wyjątek Modbus %d (%s) przy odczycie 0x%04X+%d', $code, $msg, $address, $count));
        }
        if ($fc !== $function || ord($body[1]) !== $count * 2 || strlen($body) !== 2 + $count * 2) {
            $this->close();
            throw new RuntimeException(sprintf('Nieprawidłowa odpowiedź przy odczycie 0x%04X+%d', $address, $count));
        }

        return array_values(unpack('n*', substr($body, 2)));
    }

    private function connect(): void
    {
        $socket = @stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $errstr, $this->timeout);
        if ($socket === false) {
            throw new RuntimeException("Brak połączenia z {$this->host}:{$this->port}: $errstr ($errno)");
        }
        stream_set_timeout($socket, (int) $this->timeout, (int) (fmod($this->timeout, 1.0) * 1e6));
        $this->socket = $socket;
    }

    private function write(string $data): void
    {
        while ($data !== '') {
            $written = @fwrite($this->socket, $data);
            if ($written === false || $written === 0) {
                throw new RuntimeException('Błąd zapisu do gniazda');
            }
            $data = substr($data, $written);
        }
    }

    private function readExact(int $length): string
    {
        $buffer = '';
        while (strlen($buffer) < $length) {
            $chunk = @fread($this->socket, $length - strlen($buffer));
            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($this->socket);
                throw new RuntimeException($meta['timed_out'] ? 'Przekroczony czas odpowiedzi licznika' : 'Połączenie zamknięte przez licznik');
            }
            $buffer .= $chunk;
        }
        return $buffer;
    }
}
