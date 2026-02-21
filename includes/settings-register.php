<?php
if (!defined('ABSPATH')) exit;

function ha_powerflow_register_settings() {

    // Base fields (no rot/x/y)
    $base_fields = [
        'ha_url',
        'ha_token',

        // Toggles
        'enable_solar',
        'enable_battery',
        'enable_ev',

        // Custom image URL
        'image_url'
    ];

    // Entities that require rot, x_pos, y_pos
    $positionable_fields = [
        'grid_power',
        'grid_energy_in',
        'grid_energy_out',
        'load_power',
        'load_energy',
        'pv_power',
        'pv_energy',
        'battery_power',
        'battery_energy_in',
        'battery_energy_out',
        'battery_soc',
        'ev_power',
        'ev_soc'
    ];

    // Register base fields
    foreach ($base_fields as $field) {
        register_setting(
            'ha_powerflow_settings_group',
            'ha_powerflow_' . $field,
            ['sanitize_callback' => 'sanitize_text_field']
        );
    }

    // Register rot, x_pos, y_pos for each positionable field
    foreach ($positionable_fields as $field) {

        $subfields = ['rot', 'x_pos', 'y_pos'];

        foreach ($subfields as $sub) {
            register_setting(
                'ha_powerflow_settings_group',
                'ha_powerflow_' . $field . '_' . $sub,
                ['sanitize_callback' => 'sanitize_text_field']
            );
        }
    }
}
add_action('admin_init', 'ha_powerflow_register_settings');
