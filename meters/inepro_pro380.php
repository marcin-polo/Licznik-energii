<?php

/**
 * inepro PRO380-Mod (MID) – 3-fazowy, RS485, popularny przy ładowarkach EV.
 * Rejestry holding (FC03), float32 big-endian, moce w kW -> skalowane do W.
 * Mapa z dokumentacji producenta i projektów open source (evcc), NIE sprawdzona na urządzeniu.
 * Opis pól rejestru: patrz meters/orno_we517.php.
 */
return [
    'name' => 'inepro PRO380-Mod',
    'description' => '3-fazowy, RS485, FC03 float32 – ładowarki EV',
    'verified' => false,
    'function' => 3,
    'word_order' => 'big',
    'max_block' => 40,
    'max_gap' => 4,
    'registers' => [
        'voltage_l1' => ['address' => 0x5002, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L1', 'group' => 'Napięcie', 'phase' => 1, 'precision' => 1],
        'voltage_l2' => ['address' => 0x5004, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L2', 'group' => 'Napięcie', 'phase' => 2, 'precision' => 1],
        'voltage_l3' => ['address' => 0x5006, 'type' => 'float32', 'unit' => 'V', 'label' => 'Napięcie L3', 'group' => 'Napięcie', 'phase' => 3, 'precision' => 1],
        'current_l1' => ['address' => 0x500C, 'type' => 'float32', 'unit' => 'A', 'label' => 'Prąd L1', 'group' => 'Prąd', 'phase' => 1, 'precision' => 2],
        'current_l2' => ['address' => 0x500E, 'type' => 'float32', 'unit' => 'A', 'label' => 'Prąd L2', 'group' => 'Prąd', 'phase' => 2, 'precision' => 2],
        'current_l3' => ['address' => 0x5010, 'type' => 'float32', 'unit' => 'A', 'label' => 'Prąd L3', 'group' => 'Prąd', 'phase' => 3, 'precision' => 2],
        'power_active_total' => ['address' => 0x5012, 'type' => 'float32', 'scale' => 1000, 'unit' => 'W', 'label' => 'Moc czynna', 'group' => 'Moc czynna', 'phase' => 'total', 'precision' => 0],
        'power_active_l1' => ['address' => 0x5014, 'type' => 'float32', 'scale' => 1000, 'unit' => 'W', 'label' => 'Moc czynna L1', 'group' => 'Moc czynna', 'phase' => 1, 'precision' => 0],
        'power_active_l2' => ['address' => 0x5016, 'type' => 'float32', 'scale' => 1000, 'unit' => 'W', 'label' => 'Moc czynna L2', 'group' => 'Moc czynna', 'phase' => 2, 'precision' => 0],
        'power_active_l3' => ['address' => 0x5018, 'type' => 'float32', 'scale' => 1000, 'unit' => 'W', 'label' => 'Moc czynna L3', 'group' => 'Moc czynna', 'phase' => 3, 'precision' => 0],
        'frequency' => ['address' => 0x5008, 'type' => 'float32', 'unit' => 'Hz', 'label' => 'Częstotliwość', 'group' => 'Sieć', 'precision' => 2],
        'energy_active_import' => ['address' => 0x600C, 'type' => 'float32', 'unit' => 'kWh', 'label' => 'Energia czynna pobrana', 'group' => 'Energia', 'precision' => 2],
        'energy_active_export' => ['address' => 0x6018, 'type' => 'float32', 'unit' => 'kWh', 'label' => 'Energia czynna oddana', 'group' => 'Energia', 'precision' => 2],
    ],
];
