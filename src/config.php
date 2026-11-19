<?php

declare(strict_types=1);

date_default_timezone_set(getenv('TZ') ?: 'Europe/Warsaw');

/** Konfiguracja ze zmiennych środowiskowych (plik .env). */
return [
    'meter_type' => getenv('METER_TYPE') ?: 'orno_we517',
    'host' => getenv('METER_HOST') ?: '192.168.5.10',
    'port' => (int) (getenv('METER_PORT') ?: 502),
    'unit_id' => (int) (getenv('METER_SLAVE') ?: 1),
    'interval' => max(0.1, (float) (getenv('POLL_INTERVAL') ?: 1.0)),
    'timeout' => (float) (getenv('MODBUS_TIMEOUT') ?: 2.0),
    // Okres uśredniania mocy (musi dzielić godzinę).
    'demand_minutes' => in_array((int) getenv('DEMAND_MINUTES'), [1, 5, 10, 15, 30, 60], true) ? (int) getenv('DEMAND_MINUTES') : 15,
    'history_seconds' => 3600,
    'state_file' => getenv('STATE_FILE') ?: '/data/state.json',
    'db_file' => getenv('DB_FILE') ?: __DIR__ . '/../data/energy.sqlite',
];
