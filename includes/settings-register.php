<?php
if (!defined('ABSPATH')) exit;

function ha_powerflow_register_settings() {

    $fields = [
        'ha_url',
        'ha_token',

        // Mandatory
        'grid_power',
        'grid_energy_in',
        'grid_energy_out',
        'load_power',
        'load_energy',

        // Toggles
        'enable_solar',
        'enable_battery',
        'enable_ev',

        // Solar
        'pv_power',
        'pv_energy',

        // Battery
        'battery_power',
        'battery_energy_in',
        'battery_energy_out',
        'battery_soc',

        // EV
        'ev_power',
        'ev_soc',

        // Custom image URL
        'image_url'
    ];

    foreach ($fields as $field) {
        register_setting(
            'ha_powerflow_settings_group',
            'ha_powerflow_' . $field,
            [
                'sanitize_callback' => 'sanitize_text_field'
            ]
        );
    }
}
add_action('admin_init', 'ha_powerflow_register_settings');
