<?php

/**
 * Hiking DDS238-2 ZN/S – tani 1-fazowy licznik na szynę DIN, RS485.
 * Rejestry holding (FC03), liczby całkowite ze skalą (V×10, A×100, kWh×100).
 * Mapa z dokumentacji producenta i projektów open source (Tasmota), NIE sprawdzona na urządzeniu.
 * Opis pól rejestru: patrz meters/orno_we517.php.
 */
return [
    'name' => 'Hiking DDS238-2 ZN/S',
    'description' => '1-fazowy, RS485, FC03 int16/uint32 ze skalą',
    'verified' => false,
    'function' => 3,
    'word_order' => 'big',
    'max_block' => 20,
    'max_gap' => 4,
    'registers' => [
        'voltage_l1' => ['address' => 0x000C, 'type' => 'uint16', 'scale' => 0.1, 'unit' => 'V', 'label' => 'Napięcie', 'group' => 'Napięcie', 'phase' => 1, 'precision' => 1],
        'current_l1' => ['address' => 0x000D, 'type' => 'uint16', 'scale' => 0.01, 'unit' => 'A', 'label' => 'Prąd', 'group' => 'Prąd', 'phase' => 1, 'precision' => 2],
        'power_active_l1' => ['address' => 0x000E, 'type' => 'int16', 'unit' => 'W', 'label' => 'Moc czynna L1', 'group' => 'Moc czynna', 'phase' => 1, 'precision' => 0],
        'power_active_total' => ['address' => 0x000E, 'type' => 'int16', 'unit' => 'W', 'label' => 'Moc czynna', 'group' => 'Moc czynna', 'phase' => 'total', 'precision' => 0],
        'power_reactive_total' => ['address' => 0x000F, 'type' => 'int16', 'unit' => 'var', 'label' => 'Moc bierna', 'group' => 'Moc bierna', 'phase' => 'total', 'precision' => 0],
        'pf_total' => ['address' => 0x0010, 'type' => 'uint16', 'scale' => 0.001, 'unit' => '', 'label' => 'Współczynnik mocy', 'group' => 'cos φ', 'phase' => 'total', 'precision' => 3],
        'frequency' => ['address' => 0x0011, 'type' => 'uint16', 'scale' => 0.01, 'unit' => 'Hz', 'label' => 'Częstotliwość', 'group' => 'Sieć', 'precision' => 2],
        'energy_active_export' => ['address' => 0x0008, 'type' => 'uint32', 'scale' => 0.01, 'unit' => 'kWh', 'label' => 'Energia czynna oddana', 'group' => 'Energia', 'precision' => 2],
        'energy_active_import' => ['address' => 0x000A, 'type' => 'uint32', 'scale' => 0.01, 'unit' => 'kWh', 'label' => 'Energia czynna pobrana', 'group' => 'Energia', 'precision' => 2],
    ],
];
