<?php

/**
 * Siemens SENTRON PAC3200 (Modbus TCP, wbudowany Ethernet).
 * Adresy = "Offset" z instrukcji PAC3200 (tabela "Measured variables").
 * Wartości chwilowe: float32, energie: float64 w Wh/varh -> skalowane do kWh/kvarh.
 * Opis pól rejestru: patrz meters/orno_we517.php.
 */
return [
    'name' => 'Siemens SENTRON PAC3200',
    'description' => '3-fazowy, Modbus TCP, FC03 float32/float64',
    'verified' => false,
    'function' => 3,
    'word_order' => 'big',
    'max_block' => 100,
    'max_gap' => 8, // rejestry pośrednie (np. taryfa T2 energii biernej) istnieją, można je czytać w jednym bloku
    'registers' => [
        'voltage_l1' => ['address' => 1, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L1-N', 'group' => 'Napięcie', 'phase' => 1, 'precision' => 1],
        'voltage_l2' => ['address' => 3, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L2-N', 'group' => 'Napięcie', 'phase' => 2, 'precision' => 1],
        'voltage_l3' => ['address' => 5, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L3-N', 'group' => 'Napięcie', 'phase' => 3, 'precision' => 1],
        'voltage_avg' => ['address' => 57, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie średnie L-N', 'group' => 'Napięcie', 'phase' => 'total', 'precision' => 1],

        'voltage_l12' => ['address' => 7, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L1-L2', 'group' => 'Napięcie L-L', 'phase' => 1, 'precision' => 1],
        'voltage_l23' => ['address' => 9, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L2-L3', 'group' => 'Napięcie L-L', 'phase' => 2, 'precision' => 1],
        'voltage_l31' => ['address' => 11, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L3-L1', 'group' => 'Napięcie L-L', 'phase' => 3, 'precision' => 1],
        'voltage_ll_avg' => ['address' => 59, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie średnie L-L', 'group' => 'Napięcie L-L', 'phase' => 'total', 'precision' => 1],

        'current_l1' => ['address' => 13, 'type' => 'float32', 'unit' => 'A', 'label' => 'Prąd L1', 'group' => 'Prąd', 'phase' => 1, 'precision' => 2],
        'current_l2' => ['address' => 15, 'type' => 'float32', 'unit' => 'A', 'label' => 'Prąd L2', 'group' => 'Prąd', 'phase' => 2, 'precision' => 2],
        'current_l3' => ['address' => 17, 'type' => 'float32', 'unit' => 'A', 'label' => 'Prąd L3', 'group' => 'Prąd', 'phase' => 3, 'precision' => 2],
        'current_avg' => ['address' => 61, 'type' => 'float32', 'unit' => 'A', 'label' => 'Prąd średni', 'group' => 'Prąd', 'phase' => 'total', 'precision' => 2],

        'power_active_l1' => ['address' => 25, 'type' => 'float32', 'unit' => 'W', 'label' => 'Moc czynna L1', 'group' => 'Moc czynna', 'phase' => 1, 'precision' => 0],
        'power_active_l2' => ['address' => 27, 'type' => 'float32', 'unit' => 'W', 'label' => 'Moc czynna L2', 'group' => 'Moc czynna', 'phase' => 2, 'precision' => 0],
        'power_active_l3' => ['address' => 29, 'type' => 'float32', 'unit' => 'W', 'label' => 'Moc czynna L3', 'group' => 'Moc czynna', 'phase' => 3, 'precision' => 0],
        'power_active_total' => ['address' => 65, 'type' => 'float32', 'unit' => 'W', 'label' => 'Moc czynna', 'group' => 'Moc czynna', 'phase' => 'total', 'precision' => 0],

        'power_reactive_l1' => ['address' => 31, 'type' => 'float32', 'unit' => 'var', 'label' => 'Moc bierna L1', 'group' => 'Moc bierna', 'phase' => 1, 'precision' => 0],
        'power_reactive_l2' => ['address' => 33, 'type' => 'float32', 'unit' => 'var', 'label' => 'Moc bierna L2', 'group' => 'Moc bierna', 'phase' => 2, 'precision' => 0],
        'power_reactive_l3' => ['address' => 35, 'type' => 'float32', 'unit' => 'var', 'label' => 'Moc bierna L3', 'group' => 'Moc bierna', 'phase' => 3, 'precision' => 0],
        'power_reactive_total' => ['address' => 67, 'type' => 'float32', 'unit' => 'var', 'label' => 'Moc bierna', 'group' => 'Moc bierna', 'phase' => 'total', 'precision' => 0],

        'power_apparent_l1' => ['address' => 19, 'type' => 'float32', 'unit' => 'VA', 'label' => 'Moc pozorna L1', 'group' => 'Moc pozorna', 'phase' => 1, 'precision' => 0],
        'power_apparent_l2' => ['address' => 21, 'type' => 'float32', 'unit' => 'VA', 'label' => 'Moc pozorna L2', 'group' => 'Moc pozorna', 'phase' => 2, 'precision' => 0],
        'power_apparent_l3' => ['address' => 23, 'type' => 'float32', 'unit' => 'VA', 'label' => 'Moc pozorna L3', 'group' => 'Moc pozorna', 'phase' => 3, 'precision' => 0],
        'power_apparent_total' => ['address' => 63, 'type' => 'float32', 'unit' => 'VA', 'label' => 'Moc pozorna', 'group' => 'Moc pozorna', 'phase' => 'total', 'precision' => 0],

        'pf_l1' => ['address' => 37, 'type' => 'float32', 'unit' => '', 'label' => 'Współczynnik mocy L1', 'group' => 'cos φ', 'phase' => 1, 'precision' => 3],
        'pf_l2' => ['address' => 39, 'type' => 'float32', 'unit' => '', 'label' => 'Współczynnik mocy L2', 'group' => 'cos φ', 'phase' => 2, 'precision' => 3],
        'pf_l3' => ['address' => 41, 'type' => 'float32', 'unit' => '', 'label' => 'Współczynnik mocy L3', 'group' => 'cos φ', 'phase' => 3, 'precision' => 3],
        'pf_total' => ['address' => 69, 'type' => 'float32', 'unit' => '', 'label' => 'Współczynnik mocy', 'group' => 'cos φ', 'phase' => 'total', 'precision' => 3],

        'thd_voltage_l1' => ['address' => 43, 'type' => 'float32', 'unit' => '%', 'label' => 'THD napięcia L1', 'group' => 'THD U', 'phase' => 1, 'precision' => 1],
        'thd_voltage_l2' => ['address' => 45, 'type' => 'float32', 'unit' => '%', 'label' => 'THD napięcia L2', 'group' => 'THD U', 'phase' => 2, 'precision' => 1],
        'thd_voltage_l3' => ['address' => 47, 'type' => 'float32', 'unit' => '%', 'label' => 'THD napięcia L3', 'group' => 'THD U', 'phase' => 3, 'precision' => 1],
        'thd_current_l1' => ['address' => 49, 'type' => 'float32', 'unit' => '%', 'label' => 'THD prądu L1', 'group' => 'THD I', 'phase' => 1, 'precision' => 1],
        'thd_current_l2' => ['address' => 51, 'type' => 'float32', 'unit' => '%', 'label' => 'THD prądu L2', 'group' => 'THD I', 'phase' => 2, 'precision' => 1],
        'thd_current_l3' => ['address' => 53, 'type' => 'float32', 'unit' => '%', 'label' => 'THD prądu L3', 'group' => 'THD I', 'phase' => 3, 'precision' => 1],

        'frequency' => ['address' => 55, 'type' => 'float32', 'unit' => 'Hz', 'label' => 'Częstotliwość', 'group' => 'Sieć', 'precision' => 2],
        'unbalance_voltage' => ['address' => 71, 'type' => 'float32', 'unit' => '%', 'label' => 'Asymetria napięć', 'group' => 'Sieć', 'precision' => 1],
        'unbalance_current' => ['address' => 73, 'type' => 'float32', 'unit' => '%', 'label' => 'Asymetria prądów', 'group' => 'Sieć', 'precision' => 1],

        'energy_active_import' => ['address' => 801, 'type' => 'float64', 'scale' => 0.001, 'unit' => 'kWh', 'label' => 'Energia czynna pobrana (T1)', 'group' => 'Energia', 'precision' => 2],
        'energy_active_import_t2' => ['address' => 805, 'type' => 'float64', 'scale' => 0.001, 'unit' => 'kWh', 'label' => 'Energia czynna pobrana (T2)', 'group' => 'Energia', 'precision' => 2],
        'energy_active_export' => ['address' => 809, 'type' => 'float64', 'scale' => 0.001, 'unit' => 'kWh', 'label' => 'Energia czynna oddana (T1)', 'group' => 'Energia', 'precision' => 2],
        'energy_active_export_t2' => ['address' => 813, 'type' => 'float64', 'scale' => 0.001, 'unit' => 'kWh', 'label' => 'Energia czynna oddana (T2)', 'group' => 'Energia', 'precision' => 2],
        'energy_reactive_import' => ['address' => 817, 'type' => 'float64', 'scale' => 0.001, 'unit' => 'kvarh', 'label' => 'Energia bierna pobrana (T1)', 'group' => 'Energia', 'precision' => 2],
        'energy_reactive_export' => ['address' => 825, 'type' => 'float64', 'scale' => 0.001, 'unit' => 'kvarh', 'label' => 'Energia bierna oddana (T1)', 'group' => 'Energia', 'precision' => 2],
        'energy_apparent' => ['address' => 833, 'type' => 'float64', 'scale' => 0.001, 'unit' => 'kVAh', 'label' => 'Energia pozorna (T1)', 'group' => 'Energia', 'precision' => 2],
    ],
];
