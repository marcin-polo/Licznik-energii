<?php

/**
 * Carlo Gavazzi EM340 (także EM24 / EM330 – ta sama mapa podstawowych wartości), RS485.
 * Rejestry holding (FC03), liczby całkowite ze skalą (np. V×10, A×1000, W×10),
 * int32 w kolejności słów LSW→MSW (word_order 'little').
 * Mapa z dokumentacji producenta („EM24/EM340 communication protocol”), NIE sprawdzona na urządzeniu.
 * Opis pól rejestru: patrz meters/orno_we517.php.
 */
return [
    'name' => 'Carlo Gavazzi EM340 / EM24',
    'description' => '3-fazowy, RS485, FC03 int32 (LSW first)',
    'verified' => false,
    'function' => 3,
    'word_order' => 'little',
    'max_block' => 50,
    'max_gap' => 4,
    'registers' => [
        'voltage_l1' => ['address' => 0x0000, 'type' => 'int32', 'scale' => 0.1, 'unit' => 'V', 'label' => 'Napięcie L1', 'group' => 'Napięcie', 'phase' => 1, 'precision' => 1],
        'voltage_l2' => ['address' => 0x0002, 'type' => 'int32', 'scale' => 0.1, 'unit' => 'V', 'label' => 'Napięcie L2', 'group' => 'Napięcie', 'phase' => 2, 'precision' => 1],
        'voltage_l3' => ['address' => 0x0004, 'type' => 'int32', 'scale' => 0.1, 'unit' => 'V', 'label' => 'Napięcie L3', 'group' => 'Napięcie', 'phase' => 3, 'precision' => 1],
        'voltage_avg' => ['address' => 0x0024, 'type' => 'int32', 'scale' => 0.1, 'unit' => 'V', 'label' => 'Napięcie średnie L-N', 'group' => 'Napięcie', 'phase' => 'total', 'precision' => 1],
        'voltage_l12' => ['address' => 0x0006, 'type' => 'int32', 'scale' => 0.1, 'unit' => 'V', 'label' => 'Napięcie L1-L2', 'group' => 'Napięcie L-L', 'phase' => 1, 'precision' => 1],
        'voltage_l23' => ['address' => 0x0008, 'type' => 'int32', 'scale' => 0.1, 'unit' => 'V', 'label' => 'Napięcie L2-L3', 'group' => 'Napięcie L-L', 'phase' => 2, 'precision' => 1],
        'voltage_l31' => ['address' => 0x000A, 'type' => 'int32', 'scale' => 0.1, 'unit' => 'V', 'label' => 'Napięcie L3-L1', 'group' => 'Napięcie L-L', 'phase' => 3, 'precision' => 1],
        'voltage_ll_avg' => ['address' => 0x0026, 'type' => 'int32', 'scale' => 0.1, 'unit' => 'V', 'label' => 'Napięcie średnie L-L', 'group' => 'Napięcie L-L', 'phase' => 'total', 'precision' => 1],
        'current_l1' => ['address' => 0x000C, 'type' => 'int32', 'scale' => 0.001, 'unit' => 'A', 'label' => 'Prąd L1', 'group' => 'Prąd', 'phase' => 1, 'precision' => 2],
        'current_l2' => ['address' => 0x000E, 'type' => 'int32', 'scale' => 0.001, 'unit' => 'A', 'label' => 'Prąd L2', 'group' => 'Prąd', 'phase' => 2, 'precision' => 2],
        'current_l3' => ['address' => 0x0010, 'type' => 'int32', 'scale' => 0.001, 'unit' => 'A', 'label' => 'Prąd L3', 'group' => 'Prąd', 'phase' => 3, 'precision' => 2],
        'power_active_l1' => ['address' => 0x0012, 'type' => 'int32', 'scale' => 0.1, 'unit' => 'W', 'label' => 'Moc czynna L1', 'group' => 'Moc czynna', 'phase' => 1, 'precision' => 0],
        'power_active_l2' => ['address' => 0x0014, 'type' => 'int32', 'scale' => 0.1, 'unit' => 'W', 'label' => 'Moc czynna L2', 'group' => 'Moc czynna', 'phase' => 2, 'precision' => 0],
        'power_active_l3' => ['address' => 0x0016, 'type' => 'int32', 'scale' => 0.1, 'unit' => 'W', 'label' => 'Moc czynna L3', 'group' => 'Moc czynna', 'phase' => 3, 'precision' => 0],
        'power_active_total' => ['address' => 0x0028, 'type' => 'int32', 'scale' => 0.1, 'unit' => 'W', 'label' => 'Moc czynna', 'group' => 'Moc czynna', 'phase' => 'total', 'precision' => 0],
        'power_apparent_l1' => ['address' => 0x0018, 'type' => 'int32', 'scale' => 0.1, 'unit' => 'VA', 'label' => 'Moc pozorna L1', 'group' => 'Moc pozorna', 'phase' => 1, 'precision' => 0],
        'power_apparent_l2' => ['address' => 0x001A, 'type' => 'int32', 'scale' => 0.1, 'unit' => 'VA', 'label' => 'Moc pozorna L2', 'group' => 'Moc pozorna', 'phase' => 2, 'precision' => 0],
        'power_apparent_l3' => ['address' => 0x001C, 'type' => 'int32', 'scale' => 0.1, 'unit' => 'VA', 'label' => 'Moc pozorna L3', 'group' => 'Moc pozorna', 'phase' => 3, 'precision' => 0],
        'power_apparent_total' => ['address' => 0x002A, 'type' => 'int32', 'scale' => 0.1, 'unit' => 'VA', 'label' => 'Moc pozorna', 'group' => 'Moc pozorna', 'phase' => 'total', 'precision' => 0],
        'power_reactive_l1' => ['address' => 0x001E, 'type' => 'int32', 'scale' => 0.1, 'unit' => 'var', 'label' => 'Moc bierna L1', 'group' => 'Moc bierna', 'phase' => 1, 'precision' => 0],
        'power_reactive_l2' => ['address' => 0x0020, 'type' => 'int32', 'scale' => 0.1, 'unit' => 'var', 'label' => 'Moc bierna L2', 'group' => 'Moc bierna', 'phase' => 2, 'precision' => 0],
        'power_reactive_l3' => ['address' => 0x0022, 'type' => 'int32', 'scale' => 0.1, 'unit' => 'var', 'label' => 'Moc bierna L3', 'group' => 'Moc bierna', 'phase' => 3, 'precision' => 0],
        'power_reactive_total' => ['address' => 0x002C, 'type' => 'int32', 'scale' => 0.1, 'unit' => 'var', 'label' => 'Moc bierna', 'group' => 'Moc bierna', 'phase' => 'total', 'precision' => 0],
        'pf_l1' => ['address' => 0x002E, 'type' => 'int16', 'scale' => 0.001, 'unit' => '', 'label' => 'Współczynnik mocy L1', 'group' => 'cos φ', 'phase' => 1, 'precision' => 3],
        'pf_l2' => ['address' => 0x002F, 'type' => 'int16', 'scale' => 0.001, 'unit' => '', 'label' => 'Współczynnik mocy L2', 'group' => 'cos φ', 'phase' => 2, 'precision' => 3],
        'pf_l3' => ['address' => 0x0030, 'type' => 'int16', 'scale' => 0.001, 'unit' => '', 'label' => 'Współczynnik mocy L3', 'group' => 'cos φ', 'phase' => 3, 'precision' => 3],
        'pf_total' => ['address' => 0x0031, 'type' => 'int16', 'scale' => 0.001, 'unit' => '', 'label' => 'Współczynnik mocy', 'group' => 'cos φ', 'phase' => 'total', 'precision' => 3],
        'frequency' => ['address' => 0x0033, 'type' => 'int16', 'scale' => 0.1, 'unit' => 'Hz', 'label' => 'Częstotliwość', 'group' => 'Sieć', 'precision' => 1],
        'energy_active_import' => ['address' => 0x0034, 'type' => 'int32', 'scale' => 0.1, 'unit' => 'kWh', 'label' => 'Energia czynna pobrana', 'group' => 'Energia', 'precision' => 2],
        'energy_reactive_import' => ['address' => 0x0036, 'type' => 'int32', 'scale' => 0.1, 'unit' => 'kvarh', 'label' => 'Energia bierna pobrana', 'group' => 'Energia', 'precision' => 2],
        'energy_active_export' => ['address' => 0x004E, 'type' => 'int32', 'scale' => 0.1, 'unit' => 'kWh', 'label' => 'Energia czynna oddana', 'group' => 'Energia', 'precision' => 2],
        'energy_reactive_export' => ['address' => 0x0050, 'type' => 'int32', 'scale' => 0.1, 'unit' => 'kvarh', 'label' => 'Energia bierna oddana', 'group' => 'Energia', 'precision' => 2],
    ],
];
