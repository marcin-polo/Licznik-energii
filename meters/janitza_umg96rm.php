<?php

/**
 * Janitza UMG 96RM / UMG 604 / UMG 509 – analizatory sieci, Modbus RTU lub TCP.
 * Wspólna lista rejestrów od adresu 19000 (holding FC03, float32, energie w Wh -> kWh).
 * Mapa z dokumentacji producenta, NIE sprawdzona na urządzeniu.
 * Opis pól rejestru: patrz meters/orno_we517.php.
 */
return [
    'name' => 'Janitza UMG 96RM / 604 / 509',
    'description' => 'analizator sieci, TCP/RTU, FC03 float32',
    'verified' => false,
    'function' => 3,
    'word_order' => 'big',
    'max_block' => 80,
    'max_gap' => 4,
    'registers' => [
        'voltage_l1' => ['address' => 0x4A38, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L1', 'group' => 'Napięcie', 'phase' => 1, 'precision' => 1],
        'voltage_l2' => ['address' => 0x4A3A, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L2', 'group' => 'Napięcie', 'phase' => 2, 'precision' => 1],
        'voltage_l3' => ['address' => 0x4A3C, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L3', 'group' => 'Napięcie', 'phase' => 3, 'precision' => 1],
        'voltage_l12' => ['address' => 0x4A3E, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L1-L2', 'group' => 'Napięcie L-L', 'phase' => 1, 'precision' => 1],
        'voltage_l23' => ['address' => 0x4A40, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L2-L3', 'group' => 'Napięcie L-L', 'phase' => 2, 'precision' => 1],
        'voltage_l31' => ['address' => 0x4A42, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L3-L1', 'group' => 'Napięcie L-L', 'phase' => 3, 'precision' => 1],
        'current_l1' => ['address' => 0x4A44, 'type' => 'float32', 'unit' => 'A', 'label' => 'Prąd L1', 'group' => 'Prąd', 'phase' => 1, 'precision' => 2],
        'current_l2' => ['address' => 0x4A46, 'type' => 'float32', 'unit' => 'A', 'label' => 'Prąd L2', 'group' => 'Prąd', 'phase' => 2, 'precision' => 2],
        'current_l3' => ['address' => 0x4A48, 'type' => 'float32', 'unit' => 'A', 'label' => 'Prąd L3', 'group' => 'Prąd', 'phase' => 3, 'precision' => 2],
        'current_sum' => ['address' => 0x4A4A, 'type' => 'float32', 'unit' => 'A', 'label' => 'Prąd – suma faz', 'group' => 'Prąd', 'precision' => 2],
        'power_active_l1' => ['address' => 0x4A4C, 'type' => 'float32', 'unit' => 'W', 'label' => 'Moc czynna L1', 'group' => 'Moc czynna', 'phase' => 1, 'precision' => 0],
        'power_active_l2' => ['address' => 0x4A4E, 'type' => 'float32', 'unit' => 'W', 'label' => 'Moc czynna L2', 'group' => 'Moc czynna', 'phase' => 2, 'precision' => 0],
        'power_active_l3' => ['address' => 0x4A50, 'type' => 'float32', 'unit' => 'W', 'label' => 'Moc czynna L3', 'group' => 'Moc czynna', 'phase' => 3, 'precision' => 0],
        'power_active_total' => ['address' => 0x4A52, 'type' => 'float32', 'unit' => 'W', 'label' => 'Moc czynna', 'group' => 'Moc czynna', 'phase' => 'total', 'precision' => 0],
        'power_apparent_l1' => ['address' => 0x4A54, 'type' => 'float32', 'unit' => 'VA', 'label' => 'Moc pozorna L1', 'group' => 'Moc pozorna', 'phase' => 1, 'precision' => 0],
        'power_apparent_l2' => ['address' => 0x4A56, 'type' => 'float32', 'unit' => 'VA', 'label' => 'Moc pozorna L2', 'group' => 'Moc pozorna', 'phase' => 2, 'precision' => 0],
        'power_apparent_l3' => ['address' => 0x4A58, 'type' => 'float32', 'unit' => 'VA', 'label' => 'Moc pozorna L3', 'group' => 'Moc pozorna', 'phase' => 3, 'precision' => 0],
        'power_apparent_total' => ['address' => 0x4A5A, 'type' => 'float32', 'unit' => 'VA', 'label' => 'Moc pozorna', 'group' => 'Moc pozorna', 'phase' => 'total', 'precision' => 0],
        'power_reactive_l1' => ['address' => 0x4A5C, 'type' => 'float32', 'unit' => 'var', 'label' => 'Moc bierna L1', 'group' => 'Moc bierna', 'phase' => 1, 'precision' => 0],
        'power_reactive_l2' => ['address' => 0x4A5E, 'type' => 'float32', 'unit' => 'var', 'label' => 'Moc bierna L2', 'group' => 'Moc bierna', 'phase' => 2, 'precision' => 0],
        'power_reactive_l3' => ['address' => 0x4A60, 'type' => 'float32', 'unit' => 'var', 'label' => 'Moc bierna L3', 'group' => 'Moc bierna', 'phase' => 3, 'precision' => 0],
        'power_reactive_total' => ['address' => 0x4A62, 'type' => 'float32', 'unit' => 'var', 'label' => 'Moc bierna', 'group' => 'Moc bierna', 'phase' => 'total', 'precision' => 0],
        'pf_l1' => ['address' => 0x4A64, 'type' => 'float32', 'unit' => '', 'label' => 'Współczynnik mocy L1', 'group' => 'cos φ', 'phase' => 1, 'precision' => 3],
        'pf_l2' => ['address' => 0x4A66, 'type' => 'float32', 'unit' => '', 'label' => 'Współczynnik mocy L2', 'group' => 'cos φ', 'phase' => 2, 'precision' => 3],
        'pf_l3' => ['address' => 0x4A68, 'type' => 'float32', 'unit' => '', 'label' => 'Współczynnik mocy L3', 'group' => 'cos φ', 'phase' => 3, 'precision' => 3],
        'frequency' => ['address' => 0x4A6A, 'type' => 'float32', 'unit' => 'Hz', 'label' => 'Częstotliwość', 'group' => 'Sieć', 'precision' => 2],
        'energy_active_import' => ['address' => 0x4A7C, 'type' => 'float32', 'scale' => 0.001, 'unit' => 'kWh', 'label' => 'Energia czynna pobrana', 'group' => 'Energia', 'precision' => 2],
        'energy_active_export' => ['address' => 0x4A84, 'type' => 'float32', 'scale' => 0.001, 'unit' => 'kWh', 'label' => 'Energia czynna oddana', 'group' => 'Energia', 'precision' => 2],
    ],
];
