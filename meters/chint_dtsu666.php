<?php

/**
 * CHINT DTSU666 (także Huawei DTSU666-H, licznik do falowników PV) – 3-fazowy, RS485.
 * Rejestry holding (FC03), float32 w jednostkach „surowych”: V×10, A×1000, W×10, Hz×100 – stąd skale.
 * Mapa z dokumentacji producenta i projektów open source (evcc), NIE sprawdzona na urządzeniu.
 * Opis pól rejestru: patrz meters/orno_we517.php.
 */
return [
    'name' => 'CHINT DTSU666 / Huawei DTSU666-H',
    'description' => '3-fazowy, RS485, FC03 float32 ze skalą – PV',
    'verified' => false,
    'function' => 3,
    'word_order' => 'big',
    'max_block' => 60,
    'max_gap' => 4,
    'registers' => [
        'voltage_l1' => ['address' => 0x2006, 'type' => 'float32', 'scale' => 0.1, 'unit' => 'V', 'label' => 'Napięcie L1', 'group' => 'Napięcie', 'phase' => 1, 'precision' => 1],
        'voltage_l2' => ['address' => 0x2008, 'type' => 'float32', 'scale' => 0.1, 'unit' => 'V', 'label' => 'Napięcie L2', 'group' => 'Napięcie', 'phase' => 2, 'precision' => 1],
        'voltage_l3' => ['address' => 0x200A, 'type' => 'float32', 'scale' => 0.1, 'unit' => 'V', 'label' => 'Napięcie L3', 'group' => 'Napięcie', 'phase' => 3, 'precision' => 1],
        'voltage_l12' => ['address' => 0x2000, 'type' => 'float32', 'scale' => 0.1, 'unit' => 'V', 'label' => 'Napięcie L1-L2', 'group' => 'Napięcie L-L', 'phase' => 1, 'precision' => 1],
        'voltage_l23' => ['address' => 0x2002, 'type' => 'float32', 'scale' => 0.1, 'unit' => 'V', 'label' => 'Napięcie L2-L3', 'group' => 'Napięcie L-L', 'phase' => 2, 'precision' => 1],
        'voltage_l31' => ['address' => 0x2004, 'type' => 'float32', 'scale' => 0.1, 'unit' => 'V', 'label' => 'Napięcie L3-L1', 'group' => 'Napięcie L-L', 'phase' => 3, 'precision' => 1],
        'current_l1' => ['address' => 0x200C, 'type' => 'float32', 'scale' => 0.001, 'unit' => 'A', 'label' => 'Prąd L1', 'group' => 'Prąd', 'phase' => 1, 'precision' => 2],
        'current_l2' => ['address' => 0x200E, 'type' => 'float32', 'scale' => 0.001, 'unit' => 'A', 'label' => 'Prąd L2', 'group' => 'Prąd', 'phase' => 2, 'precision' => 2],
        'current_l3' => ['address' => 0x2010, 'type' => 'float32', 'scale' => 0.001, 'unit' => 'A', 'label' => 'Prąd L3', 'group' => 'Prąd', 'phase' => 3, 'precision' => 2],
        'power_active_total' => ['address' => 0x2012, 'type' => 'float32', 'scale' => 0.1, 'unit' => 'W', 'label' => 'Moc czynna', 'group' => 'Moc czynna', 'phase' => 'total', 'precision' => 0],
        'power_active_l1' => ['address' => 0x2014, 'type' => 'float32', 'scale' => 0.1, 'unit' => 'W', 'label' => 'Moc czynna L1', 'group' => 'Moc czynna', 'phase' => 1, 'precision' => 0],
        'power_active_l2' => ['address' => 0x2016, 'type' => 'float32', 'scale' => 0.1, 'unit' => 'W', 'label' => 'Moc czynna L2', 'group' => 'Moc czynna', 'phase' => 2, 'precision' => 0],
        'power_active_l3' => ['address' => 0x2018, 'type' => 'float32', 'scale' => 0.1, 'unit' => 'W', 'label' => 'Moc czynna L3', 'group' => 'Moc czynna', 'phase' => 3, 'precision' => 0],
        'power_reactive_total' => ['address' => 0x201A, 'type' => 'float32', 'scale' => 0.1, 'unit' => 'var', 'label' => 'Moc bierna', 'group' => 'Moc bierna', 'phase' => 'total', 'precision' => 0],
        'power_reactive_l1' => ['address' => 0x201C, 'type' => 'float32', 'scale' => 0.1, 'unit' => 'var', 'label' => 'Moc bierna L1', 'group' => 'Moc bierna', 'phase' => 1, 'precision' => 0],
        'power_reactive_l2' => ['address' => 0x201E, 'type' => 'float32', 'scale' => 0.1, 'unit' => 'var', 'label' => 'Moc bierna L2', 'group' => 'Moc bierna', 'phase' => 2, 'precision' => 0],
        'power_reactive_l3' => ['address' => 0x2020, 'type' => 'float32', 'scale' => 0.1, 'unit' => 'var', 'label' => 'Moc bierna L3', 'group' => 'Moc bierna', 'phase' => 3, 'precision' => 0],
        'pf_total' => ['address' => 0x202A, 'type' => 'float32', 'scale' => 0.001, 'unit' => '', 'label' => 'Współczynnik mocy', 'group' => 'cos φ', 'phase' => 'total', 'precision' => 3],
        'pf_l1' => ['address' => 0x202C, 'type' => 'float32', 'scale' => 0.001, 'unit' => '', 'label' => 'Współczynnik mocy L1', 'group' => 'cos φ', 'phase' => 1, 'precision' => 3],
        'pf_l2' => ['address' => 0x202E, 'type' => 'float32', 'scale' => 0.001, 'unit' => '', 'label' => 'Współczynnik mocy L2', 'group' => 'cos φ', 'phase' => 2, 'precision' => 3],
        'pf_l3' => ['address' => 0x2030, 'type' => 'float32', 'scale' => 0.001, 'unit' => '', 'label' => 'Współczynnik mocy L3', 'group' => 'cos φ', 'phase' => 3, 'precision' => 3],
        'frequency' => ['address' => 0x2044, 'type' => 'float32', 'scale' => 0.01, 'unit' => 'Hz', 'label' => 'Częstotliwość', 'group' => 'Sieć', 'precision' => 2],
        'energy_active_import' => ['address' => 0x401E, 'type' => 'float32', 'unit' => 'kWh', 'label' => 'Energia czynna pobrana', 'group' => 'Energia', 'precision' => 2],
        'energy_active_export' => ['address' => 0x4028, 'type' => 'float32', 'unit' => 'kWh', 'label' => 'Energia czynna oddana', 'group' => 'Energia', 'precision' => 2],
    ],
];
