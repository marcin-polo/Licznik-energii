<?php

/**
 * Eastron SDM630 (Modbus v2, także SDM630MCT) – 3-fazowy, RS485 (zwykle przez bramkę TCP).
 * Rejestry input (FC04), float32 big-endian, jednostki podstawowe (V, A, W, kWh).
 * Mapa z dokumentacji producenta („SDM630 Modbus protocol”), NIE sprawdzona na urządzeniu.
 * Eastron czyta max 40 wartości (80 rejestrów) w jednym zapytaniu.
 * Opis pól rejestru: patrz meters/orno_we517.php.
 */
return [
    'name' => 'Eastron SDM630',
    'description' => '3-fazowy, RS485, FC04 float32',
    'verified' => false,
    'function' => 4,
    'word_order' => 'big',
    'max_block' => 80,
    'max_gap' => 20,
    'registers' => [
        'voltage_l1' => ['address' => 0x0000, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L1', 'group' => 'Napięcie', 'phase' => 1, 'precision' => 1],
        'voltage_l2' => ['address' => 0x0002, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L2', 'group' => 'Napięcie', 'phase' => 2, 'precision' => 1],
        'voltage_l3' => ['address' => 0x0004, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L3', 'group' => 'Napięcie', 'phase' => 3, 'precision' => 1],
        'voltage_avg' => ['address' => 0x002A, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie średnie L-N', 'group' => 'Napięcie', 'phase' => 'total', 'precision' => 1],
        'voltage_l12' => ['address' => 0x00C8, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L1-L2', 'group' => 'Napięcie L-L', 'phase' => 1, 'precision' => 1],
        'voltage_l23' => ['address' => 0x00CA, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L2-L3', 'group' => 'Napięcie L-L', 'phase' => 2, 'precision' => 1],
        'voltage_l31' => ['address' => 0x00CC, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L3-L1', 'group' => 'Napięcie L-L', 'phase' => 3, 'precision' => 1],
        'voltage_ll_avg' => ['address' => 0x00CE, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie średnie L-L', 'group' => 'Napięcie L-L', 'phase' => 'total', 'precision' => 1],
        'current_l1' => ['address' => 0x0006, 'type' => 'float32', 'unit' => 'A', 'label' => 'Prąd L1', 'group' => 'Prąd', 'phase' => 1, 'precision' => 2],
        'current_l2' => ['address' => 0x0008, 'type' => 'float32', 'unit' => 'A', 'label' => 'Prąd L2', 'group' => 'Prąd', 'phase' => 2, 'precision' => 2],
        'current_l3' => ['address' => 0x000A, 'type' => 'float32', 'unit' => 'A', 'label' => 'Prąd L3', 'group' => 'Prąd', 'phase' => 3, 'precision' => 2],
        'current_avg' => ['address' => 0x002E, 'type' => 'float32', 'unit' => 'A', 'label' => 'Prąd średni', 'group' => 'Prąd', 'phase' => 'total', 'precision' => 2],
        'current_n' => ['address' => 0x00E0, 'type' => 'float32', 'unit' => 'A', 'label' => 'Prąd w przewodzie N', 'group' => 'Prąd', 'precision' => 2],
        'power_active_l1' => ['address' => 0x000C, 'type' => 'float32', 'unit' => 'W', 'label' => 'Moc czynna L1', 'group' => 'Moc czynna', 'phase' => 1, 'precision' => 0],
        'power_active_l2' => ['address' => 0x000E, 'type' => 'float32', 'unit' => 'W', 'label' => 'Moc czynna L2', 'group' => 'Moc czynna', 'phase' => 2, 'precision' => 0],
        'power_active_l3' => ['address' => 0x0010, 'type' => 'float32', 'unit' => 'W', 'label' => 'Moc czynna L3', 'group' => 'Moc czynna', 'phase' => 3, 'precision' => 0],
        'power_active_total' => ['address' => 0x0034, 'type' => 'float32', 'unit' => 'W', 'label' => 'Moc czynna', 'group' => 'Moc czynna', 'phase' => 'total', 'precision' => 0],
        'power_apparent_l1' => ['address' => 0x0012, 'type' => 'float32', 'unit' => 'VA', 'label' => 'Moc pozorna L1', 'group' => 'Moc pozorna', 'phase' => 1, 'precision' => 0],
        'power_apparent_l2' => ['address' => 0x0014, 'type' => 'float32', 'unit' => 'VA', 'label' => 'Moc pozorna L2', 'group' => 'Moc pozorna', 'phase' => 2, 'precision' => 0],
        'power_apparent_l3' => ['address' => 0x0016, 'type' => 'float32', 'unit' => 'VA', 'label' => 'Moc pozorna L3', 'group' => 'Moc pozorna', 'phase' => 3, 'precision' => 0],
        'power_apparent_total' => ['address' => 0x0038, 'type' => 'float32', 'unit' => 'VA', 'label' => 'Moc pozorna', 'group' => 'Moc pozorna', 'phase' => 'total', 'precision' => 0],
        'power_reactive_l1' => ['address' => 0x0018, 'type' => 'float32', 'unit' => 'var', 'label' => 'Moc bierna L1', 'group' => 'Moc bierna', 'phase' => 1, 'precision' => 0],
        'power_reactive_l2' => ['address' => 0x001A, 'type' => 'float32', 'unit' => 'var', 'label' => 'Moc bierna L2', 'group' => 'Moc bierna', 'phase' => 2, 'precision' => 0],
        'power_reactive_l3' => ['address' => 0x001C, 'type' => 'float32', 'unit' => 'var', 'label' => 'Moc bierna L3', 'group' => 'Moc bierna', 'phase' => 3, 'precision' => 0],
        'power_reactive_total' => ['address' => 0x003C, 'type' => 'float32', 'unit' => 'var', 'label' => 'Moc bierna', 'group' => 'Moc bierna', 'phase' => 'total', 'precision' => 0],
        'pf_l1' => ['address' => 0x001E, 'type' => 'float32', 'unit' => '', 'label' => 'Współczynnik mocy L1', 'group' => 'cos φ', 'phase' => 1, 'precision' => 3],
        'pf_l2' => ['address' => 0x0020, 'type' => 'float32', 'unit' => '', 'label' => 'Współczynnik mocy L2', 'group' => 'cos φ', 'phase' => 2, 'precision' => 3],
        'pf_l3' => ['address' => 0x0022, 'type' => 'float32', 'unit' => '', 'label' => 'Współczynnik mocy L3', 'group' => 'cos φ', 'phase' => 3, 'precision' => 3],
        'pf_total' => ['address' => 0x003E, 'type' => 'float32', 'unit' => '', 'label' => 'Współczynnik mocy', 'group' => 'cos φ', 'phase' => 'total', 'precision' => 3],
        'thd_voltage_l1' => ['address' => 0x00EA, 'type' => 'float32', 'unit' => '%', 'label' => 'THD napięcia L1', 'group' => 'THD U', 'phase' => 1, 'precision' => 1],
        'thd_voltage_l2' => ['address' => 0x00EC, 'type' => 'float32', 'unit' => '%', 'label' => 'THD napięcia L2', 'group' => 'THD U', 'phase' => 2, 'precision' => 1],
        'thd_voltage_l3' => ['address' => 0x00EE, 'type' => 'float32', 'unit' => '%', 'label' => 'THD napięcia L3', 'group' => 'THD U', 'phase' => 3, 'precision' => 1],
        'thd_current_l1' => ['address' => 0x00F0, 'type' => 'float32', 'unit' => '%', 'label' => 'THD prądu L1', 'group' => 'THD I', 'phase' => 1, 'precision' => 1],
        'thd_current_l2' => ['address' => 0x00F2, 'type' => 'float32', 'unit' => '%', 'label' => 'THD prądu L2', 'group' => 'THD I', 'phase' => 2, 'precision' => 1],
        'thd_current_l3' => ['address' => 0x00F4, 'type' => 'float32', 'unit' => '%', 'label' => 'THD prądu L3', 'group' => 'THD I', 'phase' => 3, 'precision' => 1],
        'frequency' => ['address' => 0x0046, 'type' => 'float32', 'unit' => 'Hz', 'label' => 'Częstotliwość', 'group' => 'Sieć', 'precision' => 2],
        'energy_active_import' => ['address' => 0x0048, 'type' => 'float32', 'unit' => 'kWh', 'label' => 'Energia czynna pobrana', 'group' => 'Energia', 'precision' => 2],
        'energy_active_export' => ['address' => 0x004A, 'type' => 'float32', 'unit' => 'kWh', 'label' => 'Energia czynna oddana', 'group' => 'Energia', 'precision' => 2],
        'energy_reactive_import' => ['address' => 0x004C, 'type' => 'float32', 'unit' => 'kvarh', 'label' => 'Energia bierna pobrana', 'group' => 'Energia', 'precision' => 2],
        'energy_reactive_export' => ['address' => 0x004E, 'type' => 'float32', 'unit' => 'kvarh', 'label' => 'Energia bierna oddana', 'group' => 'Energia', 'precision' => 2],
    ],
];
