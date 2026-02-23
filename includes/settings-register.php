<?php
/**
 * includes/settings-register.php
 *
 * Registers every plugin option with WordPress using appropriate
 * sanitization callbacks. This means WordPress validates input
 * before saving it, rather than relying solely on output escaping.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_init', 'ha_pf_register_settings' );

function ha_pf_register_settings() {

    $group = 'ha_pf_settings_group';

    // --------------------------------------------------
    // Connection
    // --------------------------------------------------
    register_setting( $group, 'ha_powerflow_ha_url',   [ 'sanitize_callback' => 'esc_url_raw' ] );
    register_setting( $group, 'ha_powerflow_ha_token', [ 'sanitize_callback' => 'sanitize_text_field' ] );

    // --------------------------------------------------
    // Feature toggles
    // --------------------------------------------------
    foreach ( [ 'enable_solar', 'enable_battery', 'enable_ev' ] as $toggle ) {
        register_setting( $group, 'ha_powerflow_' . $toggle, [
            'sanitize_callback' => 'ha_pf_sanitize_checkbox',
        ] );
    }

    // --------------------------------------------------
    // Image URL
    // --------------------------------------------------
    register_setting( $group, 'ha_powerflow_image_url', [ 'sanitize_callback' => 'esc_url_raw' ] );

    // --------------------------------------------------
    // Delete-uploads preference (also saved via settings form)
    // --------------------------------------------------
    register_setting( $group, 'ha_powerflow_delete_uploads', [
        'sanitize_callback' => 'ha_pf_sanitize_checkbox',
    ] );

    // --------------------------------------------------
    // Click-to-coordinate debug tool
    // --------------------------------------------------
    register_setting( $group, 'ha_powerflow_debug_click', [
        'sanitize_callback' => 'ha_pf_sanitize_checkbox',
    ] );

    // --------------------------------------------------
    // Battery gauge widget
    // --------------------------------------------------
    register_setting( $group, 'ha_powerflow_battery_gauge_enable', [
        'sanitize_callback' => 'ha_pf_sanitize_checkbox',
    ] );
    register_setting( $group, 'ha_powerflow_battery_gauge_x', [
        'sanitize_callback' => 'ha_pf_sanitize_absint',
    ] );
    register_setting( $group, 'ha_powerflow_battery_gauge_y', [
        'sanitize_callback' => 'ha_pf_sanitize_absint',
    ] );

    // --------------------------------------------------
    // Custom entities (stored as a JSON blob)
    // --------------------------------------------------
    register_setting( $group, 'ha_powerflow_custom_entities', [
        'sanitize_callback' => 'ha_pf_sanitize_custom_entities',
    ] );

    // --------------------------------------------------
    // EV gauge widget
    // --------------------------------------------------
    register_setting( $group, 'ha_powerflow_ev_gauge_enable', [
        'sanitize_callback' => 'ha_pf_sanitize_checkbox',
    ] );
    register_setting( $group, 'ha_powerflow_ev_gauge_x', [
        'sanitize_callback' => 'ha_pf_sanitize_absint',
    ] );
    register_setting( $group, 'ha_powerflow_ev_gauge_y', [
        'sanitize_callback' => 'ha_pf_sanitize_absint',
    ] );

    // --------------------------------------------------
    // Colour settings
    // --------------------------------------------------
    foreach ( [ 'text_colour', 'line_colour', 'dot_colour' ] as $colour ) {
        register_setting( $group, 'ha_powerflow_' . $colour, [
            'sanitize_callback' => 'ha_pf_sanitize_colour',
        ] );
    }

    // --------------------------------------------------
    // Flow path overrides (SVG path strings)
    // --------------------------------------------------
    $flows = [ 'grid', 'load', 'pv', 'battery', 'ev' ];
    foreach ( $flows as $flow ) {
        register_setting( $group, 'ha_powerflow_' . $flow . '_flow_forward', [
            'sanitize_callback' => 'ha_pf_sanitize_svg_path',
        ] );
        register_setting( $group, 'ha_powerflow_' . $flow . '_flow_reverse', [
            'sanitize_callback' => 'ha_pf_sanitize_svg_path',
        ] );
    }

    // --------------------------------------------------
    // Entity IDs + positionable label settings
    // Each entity has: entity_id, rot, x_pos, y_pos
    // --------------------------------------------------
    $entities = [
        'grid_power', 'grid_energy_in', 'grid_energy_out',
        'load_power', 'load_energy',
        'pv_power',   'pv_energy',
        'battery_power', 'battery_energy_in', 'battery_energy_out', 'battery_soc',
        'ev_power',   'ev_soc',
    ];

    foreach ( $entities as $entity ) {
        // Entity ID (e.g. "sensor.grid_power")
        register_setting( $group, 'ha_powerflow_' . $entity, [
            'sanitize_callback' => 'sanitize_text_field',
        ] );
        // Label rotation (degrees, can be negative)
        register_setting( $group, 'ha_powerflow_' . $entity . '_rot', [
            'sanitize_callback' => 'ha_pf_sanitize_int',
        ] );
        // Label X position (SVG units, positive integer)
        register_setting( $group, 'ha_powerflow_' . $entity . '_x_pos', [
            'sanitize_callback' => 'ha_pf_sanitize_absint',
        ] );
        // Label Y position (SVG units, positive integer)
        register_setting( $group, 'ha_powerflow_' . $entity . '_y_pos', [
            'sanitize_callback' => 'ha_pf_sanitize_absint',
        ] );
    }
}

// --------------------------------------------------
// Sanitization helpers
// --------------------------------------------------

/** Checkbox: returns '1' if checked, '0' otherwise. */
function ha_pf_sanitize_checkbox( $val ) {
    return ( $val === '1' || $val === 1 || $val === true ) ? '1' : '0';
}

/** Hex colour: validates format and returns default green if invalid. */
function ha_pf_sanitize_colour( $val ) {
    $clean = sanitize_hex_color( $val );
    return $clean ? $clean : '#5EC766';
}

/**
 * SVG path: must begin with M or m and contain only recognised
 * SVG path commands, numbers, spaces and punctuation.
 * Returns empty string (use default) if invalid.
 */
function ha_pf_sanitize_svg_path( $val ) {
    $val = trim( sanitize_text_field( $val ) );
    if ( $val === '' ) return '';
    if ( ! preg_match( '/^[Mm]/', $val ) ) return '';
    if ( ! preg_match( '/^[MmLlHhVvCcSsQqTtAaZz0-9\s,.\-]+$/', $val ) ) return '';
    return $val;
}

/** Signed integer (e.g. rotation can be negative). */
function ha_pf_sanitize_int( $val ) {
    return intval( $val );
}

/** Unsigned integer (x/y positions are always positive). */
function ha_pf_sanitize_absint( $val ) {
    return absint( $val );
}

/**
 * Custom entities: stored as a JSON array of objects.
 * Each object: { "id": string, "label": string, "entity_id": string,
 *               "unit": string, "rot": int, "x": int, "y": int, "visible": bool }
 * Sanitises every field before saving.
 */
function ha_pf_sanitize_custom_entities( $raw ) {
    if ( empty( $raw ) ) return '[]';

    $decoded = json_decode( $raw, true );
    if ( ! is_array( $decoded ) ) return '[]';

    $clean = [];
    foreach ( $decoded as $item ) {
        if ( ! is_array( $item ) ) continue;

        // id: alphanumeric + underscore only, max 40 chars
        $id = preg_replace( '/[^a-z0-9_]/', '', strtolower( sanitize_text_field( $item['id'] ?? '' ) ) );
        $id = substr( $id, 0, 40 );
        if ( $id === '' ) continue;   // skip items with no id

        $size = absint( $item['size'] ?? 14 );
        if ( $size < 6  ) $size = 6;
        if ( $size > 72 ) $size = 72;

        $clean[] = [
            'id'        => $id,
            'label'     => substr( sanitize_text_field( $item['label']     ?? '' ), 0, 60 ),
            'entity_id' => substr( sanitize_text_field( $item['entity_id'] ?? '' ), 0, 100 ),
            'unit'      => substr( sanitize_text_field( $item['unit']      ?? '' ), 0, 20 ),
            'size'      => $size,
            'rot'       => intval( $item['rot'] ?? 0 ),
            'x'         => absint( $item['x']   ?? 0 ),
            'y'         => absint( $item['y']   ?? 0 ),
            'visible'   => ! empty( $item['visible'] ),
        ];
    }

    return wp_json_encode( $clean );
}
