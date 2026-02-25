<?php
/**
 * includes/config-manager.php
 *
 * Named configuration slots for [ha_powerflow config="slug"].
 *
 * STORAGE
 * -------
 * A single WP option — ha_powerflow_named_configs — holds a JSON object
 * whose keys are slugs (e.g. "solar-only") and whose values are config
 * objects with the same shape as the global settings, minus connection
 * credentials (HA URL / token are always shared across all instances).
 *
 * Each config object contains:
 *   label            string   Human-readable name shown in the admin UI
 *   enable_solar     "0"|"1"
 *   enable_battery   "0"|"1"
 *   enable_ev        "0"|"1"
 *   image_url        string
 *   text_colour      string   hex
 *   line_colour      string   hex
 *   dot_colour       string   hex
 *   refresh_interval int      5–300
 *   <flow>_flow_forward/reverse  string   SVG path or ""
 *   <entity>         string   HA entity ID
 *   <entity>_rot     int
 *   <entity>_x_pos   int
 *   <entity>_y_pos   int
 *   battery_gauge_enable  "0"|"1"
 *   battery_gauge_x  int
 *   battery_gauge_y  int
 *   ev_gauge_enable  "0"|"1"
 *   ev_gauge_x       int
 *   ev_gauge_y       int
 *   custom_entities  JSON string  (same format as global option)
 *   thresholds       JSON string  (same format as global option)
 *
 * AJAX HANDLERS  (admin-only, nonce: ha_pf_named_configs)
 * --------------------------------------------------------
 *   ha_pf_nc_list    — return all named config slugs + labels
 *   ha_pf_nc_get     — return a single config by slug
 *   ha_pf_nc_save    — create or overwrite a config
 *   ha_pf_nc_delete  — delete a config
 *   ha_pf_nc_clone_global — create a new config pre-filled from global settings
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// -------------------------------------------------------
// Option key and slug validation
// -------------------------------------------------------
define( 'HA_PF_NAMED_CONFIGS_OPT', 'ha_powerflow_named_configs' );

/**
 * A valid config slug: lowercase letters, digits, hyphens.
 * Max 40 chars. Must not be "default" (reserved for global).
 */
function ha_pf_valid_slug( string $slug ): bool {
    if ( $slug === 'default' ) return false;
    return (bool) preg_match( '/^[a-z0-9][a-z0-9\-]{0,38}$/', $slug );
}

// -------------------------------------------------------
// Low-level read/write helpers
// -------------------------------------------------------

/** Return all named configs as an associative array, or []. */
function ha_pf_nc_all(): array {
    $raw = get_option( HA_PF_NAMED_CONFIGS_OPT, '{}' );
    $data = json_decode( $raw ?: '{}', true );
    return is_array( $data ) ? $data : [];
}

/** Return one config by slug, or null. */
function ha_pf_nc_get_config( string $slug ): ?array {
    $all = ha_pf_nc_all();
    return isset( $all[ $slug ] ) && is_array( $all[ $slug ] ) ? $all[ $slug ] : null;
}

/** Persist the full configs array back to the database. */
function ha_pf_nc_save_all( array $configs ): void {
    update_option( HA_PF_NAMED_CONFIGS_OPT, wp_json_encode( $configs ), false );
}

// -------------------------------------------------------
// Sanitise a config object coming in from JS
// -------------------------------------------------------
function ha_pf_nc_sanitise( array $raw ): array {

    $entities = [
        'grid_power', 'grid_energy_in', 'grid_energy_out',
        'load_power', 'load_energy',
        'pv_power',   'pv_energy',
        'battery_power', 'battery_energy_in', 'battery_energy_out', 'battery_soc',
        'ev_power',   'ev_soc',
    ];
    $flows = [ 'grid', 'load', 'pv', 'battery', 'ev' ];

    $out = [];

    // Label — used in admin UI only
    $out['label'] = substr( sanitize_text_field( $raw['label'] ?? 'Untitled' ), 0, 60 );

    // Feature toggles
    foreach ( [ 'enable_solar', 'enable_battery', 'enable_ev' ] as $t ) {
        $out[ $t ] = ( ( $raw[ $t ] ?? '0' ) === '1' || $raw[ $t ] === true ) ? '1' : '0';
    }

    // Colours
    foreach ( [ 'text_colour', 'line_colour', 'dot_colour' ] as $c ) {
        $out[ $c ] = sanitize_hex_color( $raw[ $c ] ?? '' ) ?: '#5EC766';
    }

    // Image URL
    $out['image_url'] = esc_url_raw( $raw['image_url'] ?? '' );

    // Refresh interval
    $ri = intval( $raw['refresh_interval'] ?? 5 );
    $out['refresh_interval'] = max( 5, min( 300, $ri ) );

    // Flow paths
    foreach ( $flows as $flow ) {
        foreach ( [ 'forward', 'reverse' ] as $dir ) {
            $key       = $flow . '_flow_' . $dir;
            $out[ $key ] = ha_pf_sanitize_svg_path( $raw[ $key ] ?? '' );
        }
    }

    // Entity IDs + position fields
    foreach ( $entities as $entity ) {
        $out[ $entity ]              = sanitize_text_field( $raw[ $entity ]              ?? '' );
        $out[ $entity . '_rot'   ]   = intval(  $raw[ $entity . '_rot'   ] ?? 0 );
        $out[ $entity . '_x_pos' ]   = absint(  $raw[ $entity . '_x_pos' ] ?? 0 );
        $out[ $entity . '_y_pos' ]   = absint(  $raw[ $entity . '_y_pos' ] ?? 0 );
    }

    // Gauges
    $out['battery_gauge_enable'] = ( ( $raw['battery_gauge_enable'] ?? '0' ) === '1' ) ? '1' : '0';
    $out['battery_gauge_x']      = absint( $raw['battery_gauge_x'] ?? 95  );
    $out['battery_gauge_y']      = absint( $raw['battery_gauge_y'] ?? 605 );
    $out['ev_gauge_enable']      = ( ( $raw['ev_gauge_enable'] ?? '0' ) === '1' ) ? '1' : '0';
    $out['ev_gauge_x']           = absint( $raw['ev_gauge_x'] ?? 500 );
    $out['ev_gauge_y']           = absint( $raw['ev_gauge_y'] ?? 375 );

    // Custom entities and thresholds — re-run through the same sanitisers used
    // for the global option so the data is guaranteed to be in the same shape.
    $out['custom_entities'] = ha_pf_sanitize_custom_entities( $raw['custom_entities'] ?? '[]' );
    $out['thresholds']      = ha_pf_sanitize_thresholds(      $raw['thresholds']      ?? '[]' );

    return $out;
}

// -------------------------------------------------------
// Build a config object from the current global settings.
// Used when creating a new named config — it starts as a
// snapshot of whatever is configured globally right now.
// -------------------------------------------------------
function ha_pf_nc_snapshot_global( string $label = '' ): array {

    $entities = [
        'grid_power', 'grid_energy_in', 'grid_energy_out',
        'load_power', 'load_energy',
        'pv_power',   'pv_energy',
        'battery_power', 'battery_energy_in', 'battery_energy_out', 'battery_soc',
        'ev_power',   'ev_soc',
    ];
    $flows = [ 'grid', 'load', 'pv', 'battery', 'ev' ];

    $g = function( $key, $default = '' ) {
        $v = get_option( 'ha_powerflow_' . $key );
        return ( $v !== false && $v !== '' ) ? $v : $default;
    };

    $out = [];
    $out['label'] = $label ?: 'New Config';

    foreach ( [ 'enable_solar', 'enable_battery', 'enable_ev' ] as $t ) {
        $out[ $t ] = $g( $t, '0' );
    }
    foreach ( [ 'text_colour', 'line_colour', 'dot_colour' ] as $c ) {
        $out[ $c ] = $g( $c, '#5EC766' );
    }

    $out['image_url']        = $g( 'image_url' );
    $out['refresh_interval'] = (int) max( 5, min( 300, (int) $g( 'refresh_interval', 5 ) ) );

    foreach ( $flows as $flow ) {
        $out[ $flow . '_flow_forward' ] = $g( $flow . '_flow_forward' );
        $out[ $flow . '_flow_reverse' ] = $g( $flow . '_flow_reverse' );
    }

    foreach ( $entities as $entity ) {
        $out[ $entity ]              = $g( $entity );
        $out[ $entity . '_rot'   ]   = intval( $g( $entity . '_rot',   '0' ) );
        $out[ $entity . '_x_pos' ]   = absint(  $g( $entity . '_x_pos', '0' ) );
        $out[ $entity . '_y_pos' ]   = absint(  $g( $entity . '_y_pos', '0' ) );
    }

    $out['battery_gauge_enable'] = $g( 'battery_gauge_enable', '0' );
    $out['battery_gauge_x']      = absint( $g( 'battery_gauge_x', 95  ) );
    $out['battery_gauge_y']      = absint( $g( 'battery_gauge_y', 605 ) );
    $out['ev_gauge_enable']      = $g( 'ev_gauge_enable', '0' );
    $out['ev_gauge_x']           = absint( $g( 'ev_gauge_x', 500 ) );
    $out['ev_gauge_y']           = absint( $g( 'ev_gauge_y', 375 ) );

    $out['custom_entities'] = get_option( 'ha_powerflow_custom_entities', '[]' ) ?: '[]';
    $out['thresholds']      = get_option( 'ha_powerflow_thresholds',      '[]' ) ?: '[]';

    return $out;
}

// -------------------------------------------------------
// Nonce action shared by all named-config AJAX handlers
// -------------------------------------------------------
define( 'HA_PF_NC_NONCE', 'ha_pf_named_configs' );

// -------------------------------------------------------
// AJAX: list all named config slugs + labels
// -------------------------------------------------------
add_action( 'wp_ajax_ha_pf_nc_list', 'ha_pf_ajax_nc_list' );

function ha_pf_ajax_nc_list() {
    if ( ! check_ajax_referer( HA_PF_NC_NONCE, 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorised.' ], 403 );
    }

    $all  = ha_pf_nc_all();
    $list = [];
    foreach ( $all as $slug => $cfg ) {
        $list[] = [
            'slug'  => $slug,
            'label' => $cfg['label'] ?? $slug,
        ];
    }

    wp_send_json_success( [ 'configs' => $list ] );
}

// -------------------------------------------------------
// AJAX: get a single named config by slug
// -------------------------------------------------------
add_action( 'wp_ajax_ha_pf_nc_get', 'ha_pf_ajax_nc_get' );

function ha_pf_ajax_nc_get() {
    if ( ! check_ajax_referer( HA_PF_NC_NONCE, 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorised.' ], 403 );
    }

    $slug = sanitize_key( $_POST['slug'] ?? '' );
    if ( ! ha_pf_valid_slug( $slug ) ) {
        wp_send_json_error( [ 'message' => 'Invalid slug.' ], 400 );
    }

    $cfg = ha_pf_nc_get_config( $slug );
    if ( $cfg === null ) {
        wp_send_json_error( [ 'message' => 'Config not found.' ], 404 );
    }

    wp_send_json_success( [ 'slug' => $slug, 'config' => $cfg ] );
}

// -------------------------------------------------------
// AJAX: create or overwrite a named config
// -------------------------------------------------------
add_action( 'wp_ajax_ha_pf_nc_save', 'ha_pf_ajax_nc_save' );

function ha_pf_ajax_nc_save() {
    if ( ! check_ajax_referer( HA_PF_NC_NONCE, 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorised.' ], 403 );
    }

    $slug = sanitize_key( $_POST['slug'] ?? '' );
    if ( ! ha_pf_valid_slug( $slug ) ) {
        wp_send_json_error( [ 'message' => 'Invalid slug. Use lowercase letters, digits and hyphens only (e.g. solar-only).' ], 400 );
    }

    // Config data arrives as a JSON string in $_POST['config']
    $raw_json = wp_unslash( $_POST['config'] ?? '{}' );
    $raw      = json_decode( $raw_json, true );
    if ( ! is_array( $raw ) ) {
        wp_send_json_error( [ 'message' => 'Invalid config data.' ], 400 );
    }

    // Enforce max 20 named configs to keep the option size reasonable
    $all = ha_pf_nc_all();
    if ( ! isset( $all[ $slug ] ) && count( $all ) >= 20 ) {
        wp_send_json_error( [ 'message' => 'Maximum of 20 named configs reached. Delete one before creating another.' ], 400 );
    }

    $all[ $slug ] = ha_pf_nc_sanitise( $raw );
    ha_pf_nc_save_all( $all );

    wp_send_json_success( [
        'slug'  => $slug,
        'label' => $all[ $slug ]['label'],
    ] );
}

// -------------------------------------------------------
// AJAX: delete a named config by slug
// -------------------------------------------------------
add_action( 'wp_ajax_ha_pf_nc_delete', 'ha_pf_ajax_nc_delete' );

function ha_pf_ajax_nc_delete() {
    if ( ! check_ajax_referer( HA_PF_NC_NONCE, 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorised.' ], 403 );
    }

    $slug = sanitize_key( $_POST['slug'] ?? '' );
    if ( ! ha_pf_valid_slug( $slug ) ) {
        wp_send_json_error( [ 'message' => 'Invalid slug.' ], 400 );
    }

    $all = ha_pf_nc_all();
    if ( ! isset( $all[ $slug ] ) ) {
        wp_send_json_error( [ 'message' => 'Config not found.' ], 404 );
    }

    unset( $all[ $slug ] );
    ha_pf_nc_save_all( $all );

    wp_send_json_success( [ 'slug' => $slug ] );
}

// -------------------------------------------------------
// AJAX: create a new config pre-filled from global settings
// -------------------------------------------------------
add_action( 'wp_ajax_ha_pf_nc_clone_global', 'ha_pf_ajax_nc_clone_global' );

function ha_pf_ajax_nc_clone_global() {
    if ( ! check_ajax_referer( HA_PF_NC_NONCE, 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorised.' ], 403 );
    }

    $slug  = sanitize_key( $_POST['slug']  ?? '' );
    $label = sanitize_text_field( wp_unslash( $_POST['label'] ?? 'New Config' ) );

    if ( ! ha_pf_valid_slug( $slug ) ) {
        wp_send_json_error( [ 'message' => 'Invalid slug. Use lowercase letters, digits and hyphens only.' ], 400 );
    }

    $all = ha_pf_nc_all();

    if ( isset( $all[ $slug ] ) ) {
        wp_send_json_error( [ 'message' => 'A config with that slug already exists.' ], 409 );
    }

    if ( count( $all ) >= 20 ) {
        wp_send_json_error( [ 'message' => 'Maximum of 20 named configs reached.' ], 400 );
    }

    $all[ $slug ] = ha_pf_nc_snapshot_global( $label );
    ha_pf_nc_save_all( $all );

    wp_send_json_success( [
        'slug'   => $slug,
        'label'  => $label,
        'config' => $all[ $slug ],
    ] );
}
