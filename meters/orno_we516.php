<?php

/**
 * ORNO OR-WE-516 – 3-fazowy, RS485 (zwykle przez bramkę TCP).
 * Ta sama mapa rejestrów co OR-WE-517 (holding FC03, float32, moce w kW -> W).
 * Mapa przeniesiona z WE-517 (sprawdzonego), na samym WE-516 NIE sprawdzona.
 * Opis pól rejestru: patrz meters/orno_we517.php.
 */
return [
    'name' => 'ORNO WE-516',
    'description' => '3-fazowy, RS485 przez bramkę TCP, FC03 float32',
    'verified' => false,
    'function' => 3,
    'word_order' => 'big',
    'max_block' => 60,
    'max_gap' => 0,
    'registers' => [
        'voltage_l1' => ['address' => 0x0E, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L1', 'group' => 'Napięcie', 'phase' => 1, 'precision' => 1],
        'voltage_l2' => ['address' => 0x10, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L2', 'group' => 'Napięcie', 'phase' => 2, 'precision' => 1],
        'voltage_l3' => ['address' => 0x12, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L3', 'group' => 'Napięcie', 'phase' => 3, 'precision' => 1],

        'frequency' => ['address' => 0x14, 'type' => 'float32', 'unit' => 'Hz', 'label' => 'Częstotliwość', 'group' => 'Sieć', 'precision' => 2],

        'current_l1' => ['address' => 0x16, 'type' => 'float32', 'unit' => 'A', 'label' => 'Prąd L1', 'group' => 'Prąd', 'phase' => 1, 'precision' => 2],
        'current_l2' => ['address' => 0x18, 'type' => 'float32', 'unit' => 'A', 'label' => 'Prąd L2', 'group' => 'Prąd', 'phase' => 2, 'precision' => 2],
        'current_l3' => ['address' => 0x1A, 'type' => 'float32', 'unit' => 'A', 'label' => 'Prąd L3', 'group' => 'Prąd', 'phase' => 3, 'precision' => 2],

        'power_active_total' => ['address' => 0x1C, 'type' => 'float32', 'scale' => 1000, 'unit' => 'W', 'label' => 'Moc czynna', 'group' => 'Moc czynna', 'phase' => 'total', 'precision' => 0],
        'power_active_l1' => ['address' => 0x1E, 'type' => 'float32', 'scale' => 1000, 'unit' => 'W', 'label' => 'Moc czynna L1', 'group' => 'Moc czynna', 'phase' => 1, 'precision' => 0],
        'power_active_l2' => ['address' => 0x20, 'type' => 'float32', 'scale' => 1000, 'unit' => 'W', 'label' => 'Moc czynna L2', 'group' => 'Moc czynna', 'phase' => 2, 'precision' => 0],
        'power_active_l3' => ['address' => 0x22, 'type' => 'float32', 'scale' => 1000, 'unit' => 'W', 'label' => 'Moc czynna L3', 'group' => 'Moc czynna', 'phase' => 3, 'precision' => 0],

        'power_reactive_total' => ['address' => 0x24, 'type' => 'float32', 'scale' => 1000, 'unit' => 'var', 'label' => 'Moc bierna', 'group' => 'Moc bierna', 'phase' => 'total', 'precision' => 0],
        'power_reactive_l1' => ['address' => 0x26, 'type' => 'float32', 'scale' => 1000, 'unit' => 'var', 'label' => 'Moc bierna L1', 'group' => 'Moc bierna', 'phase' => 1, 'precision' => 0],
        'power_reactive_l2' => ['address' => 0x28, 'type' => 'float32', 'scale' => 1000, 'unit' => 'var', 'label' => 'Moc bierna L2', 'group' => 'Moc bierna', 'phase' => 2, 'precision' => 0],
        'power_reactive_l3' => ['address' => 0x2A, 'type' => 'float32', 'scale' => 1000, 'unit' => 'var', 'label' => 'Moc bierna L3', 'group' => 'Moc bierna', 'phase' => 3, 'precision' => 0],

        'power_apparent_total' => ['address' => 0x2C, 'type' => 'float32', 'scale' => 1000, 'unit' => 'VA', 'label' => 'Moc pozorna', 'group' => 'Moc pozorna', 'phase' => 'total', 'precision' => 0],
        'power_apparent_l1' => ['address' => 0x2E, 'type' => 'float32', 'scale' => 1000, 'unit' => 'VA', 'label' => 'Moc pozorna L1', 'group' => 'Moc pozorna', 'phase' => 1, 'precision' => 0],
        'power_apparent_l2' => ['address' => 0x30, 'type' => 'float32', 'scale' => 1000, 'unit' => 'VA', 'label' => 'Moc pozorna L2', 'group' => 'Moc pozorna', 'phase' => 2, 'precision' => 0],
        'power_apparent_l3' => ['address' => 0x32, 'type' => 'float32', 'scale' => 1000, 'unit' => 'VA', 'label' => 'Moc pozorna L3', 'group' => 'Moc pozorna', 'phase' => 3, 'precision' => 0],

        'pf_total' => ['address' => 0x34, 'type' => 'float32', 'unit' => '', 'label' => 'Współczynnik mocy', 'group' => 'cos φ', 'phase' => 'total', 'precision' => 3],
        'pf_l1' => ['address' => 0x36, 'type' => 'float32', 'unit' => '', 'label' => 'Współczynnik mocy L1', 'group' => 'cos φ', 'phase' => 1, 'precision' => 3],
        'pf_l2' => ['address' => 0x38, 'type' => 'float32', 'unit' => '', 'label' => 'Współczynnik mocy L2', 'group' => 'cos φ', 'phase' => 2, 'precision' => 3],
        'pf_l3' => ['address' => 0x3A, 'type' => 'float32', 'unit' => '', 'label' => 'Współczynnik mocy L3', 'group' => 'cos φ', 'phase' => 3, 'precision' => 3],

        'energy_active_import' => ['address' => 0x0100, 'type' => 'float32', 'unit' => 'kWh', 'label' => 'Energia czynna pobrana', 'group' => 'Energia', 'precision' => 2],
        'energy_active_export' => ['address' => 0x0102, 'type' => 'float32', 'unit' => 'kWh', 'label' => 'Energia czynna oddana', 'group' => 'Energia', 'precision' => 2],
        'energy_reactive_import' => ['address' => 0x0104, 'type' => 'float32', 'unit' => 'kvarh', 'label' => 'Energia bierna pobrana', 'group' => 'Energia', 'precision' => 2],
        'energy_reactive_export' => ['address' => 0x0106, 'type' => 'float32', 'unit' => 'kvarh', 'label' => 'Energia bierna oddana', 'group' => 'Energia', 'precision' => 2],
    ],
];
