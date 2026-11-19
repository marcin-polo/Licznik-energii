<?php

/**
 * Schneider Electric PowerLogic PM5000 (PM5100–PM5500) – mierniki mocy, Modbus RTU / TCP.
 * Rejestry holding (FC03), float32. Adresy = numer rejestru z dokumentacji minus 1 (np. 3028 -> 3027).
 * Moce w kW -> skalowane do W. Współczynnik mocy pominięty (specjalne kodowanie 4-ćwiartkowe).
 * Większość tych adresów pasuje też do iEM3000 (bez energii). Mapa z dokumentacji, NIE sprawdzona na urządzeniu.
 * Opis pól rejestru: patrz meters/orno_we517.php.
 */
return [
    'name' => 'Schneider PowerLogic PM5000',
    'description' => 'miernik mocy, TCP/RTU, FC03 float32',
    'verified' => false,
    'function' => 3,
    'word_order' => 'big',
    'max_block' => 80,
    'max_gap' => 4,
    'registers' => [
        'current_l1' => ['address' => 0x0BB7, 'type' => 'float32', 'unit' => 'A', 'label' => 'Prąd L1', 'group' => 'Prąd', 'phase' => 1, 'precision' => 2],
        'current_l2' => ['address' => 0x0BB9, 'type' => 'float32', 'unit' => 'A', 'label' => 'Prąd L2', 'group' => 'Prąd', 'phase' => 2, 'precision' => 2],
        'current_l3' => ['address' => 0x0BBB, 'type' => 'float32', 'unit' => 'A', 'label' => 'Prąd L3', 'group' => 'Prąd', 'phase' => 3, 'precision' => 2],
        'current_avg' => ['address' => 0x0BC1, 'type' => 'float32', 'unit' => 'A', 'label' => 'Prąd średni', 'group' => 'Prąd', 'phase' => 'total', 'precision' => 2],
        'voltage_l12' => ['address' => 0x0BCB, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L1-L2', 'group' => 'Napięcie L-L', 'phase' => 1, 'precision' => 1],
        'voltage_l23' => ['address' => 0x0BCD, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L2-L3', 'group' => 'Napięcie L-L', 'phase' => 2, 'precision' => 1],
        'voltage_l31' => ['address' => 0x0BCF, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L3-L1', 'group' => 'Napięcie L-L', 'phase' => 3, 'precision' => 1],
        'voltage_ll_avg' => ['address' => 0x0BD1, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie średnie L-L', 'group' => 'Napięcie L-L', 'phase' => 'total', 'precision' => 1],
        'voltage_l1' => ['address' => 0x0BD3, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L1', 'group' => 'Napięcie', 'phase' => 1, 'precision' => 1],
        'voltage_l2' => ['address' => 0x0BD5, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L2', 'group' => 'Napięcie', 'phase' => 2, 'precision' => 1],
        'voltage_l3' => ['address' => 0x0BD7, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L3', 'group' => 'Napięcie', 'phase' => 3, 'precision' => 1],
        'voltage_avg' => ['address' => 0x0BDB, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie średnie L-N', 'group' => 'Napięcie', 'phase' => 'total', 'precision' => 1],
        'power_active_l1' => ['address' => 0x0BED, 'type' => 'float32', 'scale' => 1000, 'unit' => 'W', 'label' => 'Moc czynna L1', 'group' => 'Moc czynna', 'phase' => 1, 'precision' => 0],
        'power_active_l2' => ['address' => 0x0BEF, 'type' => 'float32', 'scale' => 1000, 'unit' => 'W', 'label' => 'Moc czynna L2', 'group' => 'Moc czynna', 'phase' => 2, 'precision' => 0],
        'power_active_l3' => ['address' => 0x0BF1, 'type' => 'float32', 'scale' => 1000, 'unit' => 'W', 'label' => 'Moc czynna L3', 'group' => 'Moc czynna', 'phase' => 3, 'precision' => 0],
        'power_active_total' => ['address' => 0x0BF3, 'type' => 'float32', 'scale' => 1000, 'unit' => 'W', 'label' => 'Moc czynna', 'group' => 'Moc czynna', 'phase' => 'total', 'precision' => 0],
        'power_reactive_l1' => ['address' => 0x0BF5, 'type' => 'float32', 'scale' => 1000, 'unit' => 'var', 'label' => 'Moc bierna L1', 'group' => 'Moc bierna', 'phase' => 1, 'precision' => 0],
        'power_reactive_l2' => ['address' => 0x0BF7, 'type' => 'float32', 'scale' => 1000, 'unit' => 'var', 'label' => 'Moc bierna L2', 'group' => 'Moc bierna', 'phase' => 2, 'precision' => 0],
        'power_reactive_l3' => ['address' => 0x0BF9, 'type' => 'float32', 'scale' => 1000, 'unit' => 'var', 'label' => 'Moc bierna L3', 'group' => 'Moc bierna', 'phase' => 3, 'precision' => 0],
        'power_reactive_total' => ['address' => 0x0BFB, 'type' => 'float32', 'scale' => 1000, 'unit' => 'var', 'label' => 'Moc bierna', 'group' => 'Moc bierna', 'phase' => 'total', 'precision' => 0],
        'power_apparent_l1' => ['address' => 0x0BFD, 'type' => 'float32', 'scale' => 1000, 'unit' => 'VA', 'label' => 'Moc pozorna L1', 'group' => 'Moc pozorna', 'phase' => 1, 'precision' => 0],
        'power_apparent_l2' => ['address' => 0x0BFF, 'type' => 'float32', 'scale' => 1000, 'unit' => 'VA', 'label' => 'Moc pozorna L2', 'group' => 'Moc pozorna', 'phase' => 2, 'precision' => 0],
        'power_apparent_l3' => ['address' => 0x0C01, 'type' => 'float32', 'scale' => 1000, 'unit' => 'VA', 'label' => 'Moc pozorna L3', 'group' => 'Moc pozorna', 'phase' => 3, 'precision' => 0],
        'power_apparent_total' => ['address' => 0x0C03, 'type' => 'float32', 'scale' => 1000, 'unit' => 'VA', 'label' => 'Moc pozorna', 'group' => 'Moc pozorna', 'phase' => 'total', 'precision' => 0],
        'frequency' => ['address' => 0x0C25, 'type' => 'float32', 'unit' => 'Hz', 'label' => 'Częstotliwość', 'group' => 'Sieć', 'precision' => 2],
        'energy_active_import' => ['address' => 0x0A8B, 'type' => 'float32', 'unit' => 'kWh', 'label' => 'Energia czynna pobrana', 'group' => 'Energia', 'precision' => 2],
        'energy_active_export' => ['address' => 0x0A8D, 'type' => 'float32', 'unit' => 'kWh', 'label' => 'Energia czynna oddana', 'group' => 'Energia', 'precision' => 2],
        'energy_reactive_import' => ['address' => 0x0A91, 'type' => 'float32', 'unit' => 'kvarh', 'label' => 'Energia bierna pobrana', 'group' => 'Energia', 'precision' => 2],
        'energy_reactive_export' => ['address' => 0x0A93, 'type' => 'float32', 'unit' => 'kvarh', 'label' => 'Energia bierna oddana', 'group' => 'Energia', 'precision' => 2],
    ],
];
