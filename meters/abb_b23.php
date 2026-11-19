<?php

/**
 * ABB B21 / B23 / B24 (EQ meters), RS485 lub TCP (B24 z interfejsem Ethernet).
 * Rejestry holding (FC03), liczby całkowite ze skalą (V×10, A×100, W×100), energie uint64 (kWh×100).
 * Mapa z dokumentacji producenta („B-series Modbus mapping”), NIE sprawdzona na urządzeniu.
 * Opis pól rejestru: patrz meters/orno_we517.php.
 */
return [
    'name' => 'ABB B21 / B23 / B24',
    'description' => '3-fazowy, FC03 int32/uint64 ze skalą',
    'verified' => false,
    'function' => 3,
    'word_order' => 'big',
    'max_block' => 60,
    'max_gap' => 4,
    'registers' => [
        'voltage_l1' => ['address' => 0x5B00, 'type' => 'uint32', 'scale' => 0.1, 'unit' => 'V', 'label' => 'Napięcie L1', 'group' => 'Napięcie', 'phase' => 1, 'precision' => 1],
        'voltage_l2' => ['address' => 0x5B02, 'type' => 'uint32', 'scale' => 0.1, 'unit' => 'V', 'label' => 'Napięcie L2', 'group' => 'Napięcie', 'phase' => 2, 'precision' => 1],
        'voltage_l3' => ['address' => 0x5B04, 'type' => 'uint32', 'scale' => 0.1, 'unit' => 'V', 'label' => 'Napięcie L3', 'group' => 'Napięcie', 'phase' => 3, 'precision' => 1],
        'voltage_l12' => ['address' => 0x5B06, 'type' => 'uint32', 'scale' => 0.1, 'unit' => 'V', 'label' => 'Napięcie L1-L2', 'group' => 'Napięcie L-L', 'phase' => 1, 'precision' => 1],
        'voltage_l23' => ['address' => 0x5B08, 'type' => 'uint32', 'scale' => 0.1, 'unit' => 'V', 'label' => 'Napięcie L2-L3', 'group' => 'Napięcie L-L', 'phase' => 2, 'precision' => 1],
        'voltage_l31' => ['address' => 0x5B0A, 'type' => 'uint32', 'scale' => 0.1, 'unit' => 'V', 'label' => 'Napięcie L3-L1', 'group' => 'Napięcie L-L', 'phase' => 3, 'precision' => 1],
        'current_l1' => ['address' => 0x5B0C, 'type' => 'uint32', 'scale' => 0.01, 'unit' => 'A', 'label' => 'Prąd L1', 'group' => 'Prąd', 'phase' => 1, 'precision' => 2],
        'current_l2' => ['address' => 0x5B0E, 'type' => 'uint32', 'scale' => 0.01, 'unit' => 'A', 'label' => 'Prąd L2', 'group' => 'Prąd', 'phase' => 2, 'precision' => 2],
        'current_l3' => ['address' => 0x5B10, 'type' => 'uint32', 'scale' => 0.01, 'unit' => 'A', 'label' => 'Prąd L3', 'group' => 'Prąd', 'phase' => 3, 'precision' => 2],
        'power_active_total' => ['address' => 0x5B14, 'type' => 'int32', 'scale' => 0.01, 'unit' => 'W', 'label' => 'Moc czynna', 'group' => 'Moc czynna', 'phase' => 'total', 'precision' => 0],
        'power_active_l1' => ['address' => 0x5B16, 'type' => 'int32', 'scale' => 0.01, 'unit' => 'W', 'label' => 'Moc czynna L1', 'group' => 'Moc czynna', 'phase' => 1, 'precision' => 0],
        'power_active_l2' => ['address' => 0x5B18, 'type' => 'int32', 'scale' => 0.01, 'unit' => 'W', 'label' => 'Moc czynna L2', 'group' => 'Moc czynna', 'phase' => 2, 'precision' => 0],
        'power_active_l3' => ['address' => 0x5B1A, 'type' => 'int32', 'scale' => 0.01, 'unit' => 'W', 'label' => 'Moc czynna L3', 'group' => 'Moc czynna', 'phase' => 3, 'precision' => 0],
        'power_reactive_total' => ['address' => 0x5B1C, 'type' => 'int32', 'scale' => 0.01, 'unit' => 'var', 'label' => 'Moc bierna', 'group' => 'Moc bierna', 'phase' => 'total', 'precision' => 0],
        'power_reactive_l1' => ['address' => 0x5B1E, 'type' => 'int32', 'scale' => 0.01, 'unit' => 'var', 'label' => 'Moc bierna L1', 'group' => 'Moc bierna', 'phase' => 1, 'precision' => 0],
        'power_reactive_l2' => ['address' => 0x5B20, 'type' => 'int32', 'scale' => 0.01, 'unit' => 'var', 'label' => 'Moc bierna L2', 'group' => 'Moc bierna', 'phase' => 2, 'precision' => 0],
        'power_reactive_l3' => ['address' => 0x5B22, 'type' => 'int32', 'scale' => 0.01, 'unit' => 'var', 'label' => 'Moc bierna L3', 'group' => 'Moc bierna', 'phase' => 3, 'precision' => 0],
        'power_apparent_total' => ['address' => 0x5B24, 'type' => 'int32', 'scale' => 0.01, 'unit' => 'VA', 'label' => 'Moc pozorna', 'group' => 'Moc pozorna', 'phase' => 'total', 'precision' => 0],
        'power_apparent_l1' => ['address' => 0x5B26, 'type' => 'int32', 'scale' => 0.01, 'unit' => 'VA', 'label' => 'Moc pozorna L1', 'group' => 'Moc pozorna', 'phase' => 1, 'precision' => 0],
        'power_apparent_l2' => ['address' => 0x5B28, 'type' => 'int32', 'scale' => 0.01, 'unit' => 'VA', 'label' => 'Moc pozorna L2', 'group' => 'Moc pozorna', 'phase' => 2, 'precision' => 0],
        'power_apparent_l3' => ['address' => 0x5B2A, 'type' => 'int32', 'scale' => 0.01, 'unit' => 'VA', 'label' => 'Moc pozorna L3', 'group' => 'Moc pozorna', 'phase' => 3, 'precision' => 0],
        'frequency' => ['address' => 0x5B2C, 'type' => 'uint16', 'scale' => 0.01, 'unit' => 'Hz', 'label' => 'Częstotliwość', 'group' => 'Sieć', 'precision' => 2],
        'pf_total' => ['address' => 0x5B3A, 'type' => 'int16', 'scale' => 0.001, 'unit' => '', 'label' => 'Współczynnik mocy', 'group' => 'cos φ', 'phase' => 'total', 'precision' => 3],
        'pf_l1' => ['address' => 0x5B3B, 'type' => 'int16', 'scale' => 0.001, 'unit' => '', 'label' => 'Współczynnik mocy L1', 'group' => 'cos φ', 'phase' => 1, 'precision' => 3],
        'pf_l2' => ['address' => 0x5B3C, 'type' => 'int16', 'scale' => 0.001, 'unit' => '', 'label' => 'Współczynnik mocy L2', 'group' => 'cos φ', 'phase' => 2, 'precision' => 3],
        'pf_l3' => ['address' => 0x5B3D, 'type' => 'int16', 'scale' => 0.001, 'unit' => '', 'label' => 'Współczynnik mocy L3', 'group' => 'cos φ', 'phase' => 3, 'precision' => 3],
        'energy_active_import' => ['address' => 0x5000, 'type' => 'uint64', 'scale' => 0.01, 'unit' => 'kWh', 'label' => 'Energia czynna pobrana', 'group' => 'Energia', 'precision' => 2],
        'energy_active_export' => ['address' => 0x5004, 'type' => 'uint64', 'scale' => 0.01, 'unit' => 'kWh', 'label' => 'Energia czynna oddana', 'group' => 'Energia', 'precision' => 2],
        'energy_reactive_import' => ['address' => 0x500C, 'type' => 'uint64', 'scale' => 0.01, 'unit' => 'kvarh', 'label' => 'Energia bierna pobrana', 'group' => 'Energia', 'precision' => 2],
        'energy_reactive_export' => ['address' => 0x5010, 'type' => 'uint64', 'scale' => 0.01, 'unit' => 'kvarh', 'label' => 'Energia bierna oddana', 'group' => 'Energia', 'precision' => 2],
    ],
];
