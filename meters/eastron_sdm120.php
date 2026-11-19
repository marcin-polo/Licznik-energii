<?php

/**
 * Eastron SDM120 (Modbus) – 1-fazowy, RS485.
 * Rejestry input (FC04), float32 big-endian. Wartości przypisane do fazy L1 i sumy (Σ),
 * żeby działały ekrany i progi zbudowane dla liczników 3-fazowych.
 * Mapa z dokumentacji producenta, NIE sprawdzona na urządzeniu.
 * Opis pól rejestru: patrz meters/orno_we517.php.
 */
return [
    'name' => 'Eastron SDM120',
    'description' => '1-fazowy, RS485, FC04 float32',
    'verified' => false,
    'function' => 4,
    'word_order' => 'big',
    'max_block' => 80,
    'max_gap' => 20,
    'registers' => [
        'voltage_l1' => ['address' => 0x0000, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie', 'group' => 'Napięcie', 'phase' => 1, 'precision' => 1],
        'current_l1' => ['address' => 0x0006, 'type' => 'float32', 'unit' => 'A', 'label' => 'Prąd', 'group' => 'Prąd', 'phase' => 1, 'precision' => 2],
        'power_active_l1' => ['address' => 0x000C, 'type' => 'float32', 'unit' => 'W', 'label' => 'Moc czynna L1', 'group' => 'Moc czynna', 'phase' => 1, 'precision' => 0],
        'power_active_total' => ['address' => 0x000C, 'type' => 'float32', 'unit' => 'W', 'label' => 'Moc czynna', 'group' => 'Moc czynna', 'phase' => 'total', 'precision' => 0],
        'power_apparent_total' => ['address' => 0x0012, 'type' => 'float32', 'unit' => 'VA', 'label' => 'Moc pozorna', 'group' => 'Moc pozorna', 'phase' => 'total', 'precision' => 0],
        'power_reactive_total' => ['address' => 0x0018, 'type' => 'float32', 'unit' => 'var', 'label' => 'Moc bierna', 'group' => 'Moc bierna', 'phase' => 'total', 'precision' => 0],
        'pf_total' => ['address' => 0x001E, 'type' => 'float32', 'unit' => '', 'label' => 'Współczynnik mocy', 'group' => 'cos φ', 'phase' => 'total', 'precision' => 3],
        'frequency' => ['address' => 0x0046, 'type' => 'float32', 'unit' => 'Hz', 'label' => 'Częstotliwość', 'group' => 'Sieć', 'precision' => 2],
        'energy_active_import' => ['address' => 0x0048, 'type' => 'float32', 'unit' => 'kWh', 'label' => 'Energia czynna pobrana', 'group' => 'Energia', 'precision' => 2],
        'energy_active_export' => ['address' => 0x004A, 'type' => 'float32', 'unit' => 'kWh', 'label' => 'Energia czynna oddana', 'group' => 'Energia', 'precision' => 2],
        'energy_reactive_import' => ['address' => 0x004C, 'type' => 'float32', 'unit' => 'kvarh', 'label' => 'Energia bierna pobrana', 'group' => 'Energia', 'precision' => 2],
        'energy_reactive_export' => ['address' => 0x004E, 'type' => 'float32', 'unit' => 'kvarh', 'label' => 'Energia bierna oddana', 'group' => 'Energia', 'precision' => 2],
    ],
];
