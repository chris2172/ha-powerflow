<?php
/**
 * includes/config-export.php
 *
 * Saves the current plugin configuration as a YAML file every time
 * the settings form is submitted (hooked to 'options.php' redirect).
 *
 * FILE LOCATION
 * -------------
 *   wp-content/uploads/ha-powerflow/config/YYMMDD-hhmmss-config.yaml
 *
 * YAML STRUCTURE
 * --------------
 *   meta:            plugin version, export timestamp, site URL
 *   connection:      ha_url, ha_token (AES-encrypted)
 *   features:        solar/battery/ev toggles
 *   appearance:      image_url, colours
 *   flow_paths:      forward + reverse per flow
 *   entities:        entity_id, rot, x_pos, y_pos per entity
 *   preferences:     ssl_verify, delete_uploads
 *
 * PRUNING
 * -------
 *   After writing, only the 50 most recent files are kept.
 *
 * IMPORT (future)
 * ---------------
 *   ha_pf_import_config( $yaml_string ) is stubbed here so it is
 *   available to any future import UI without structural changes.
 *   It reads the YAML, decrypts the token, and calls update_option()
 *   for every key — overwriting everything (as requested).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// -------------------------------------------------------
// Hook: save config when our settings form is submitted.
//
// WHY NOT updated_option / shutdown?
// -----------------------------------
// updated_option only fires when the value actually changes — if you
// press Save without editing anything, no hook fires, no file is written.
// The shutdown hook is also unreliable after options.php because WordPress
// issues a wp_redirect() immediately after saving, and some server
// configurations allow PHP to terminate before shutdown callbacks run.
//
// RELIABLE APPROACH
// -----------------
// options.php calls wp_redirect() after processing. We intercept that
// redirect using the wp_redirect filter, which fires after all options
// have been committed to the database but before the browser is sent
// anywhere. We check that our settings group was what was submitted,
// write the YAML immediately (synchronously, no shutdown deferral),
// then let the redirect proceed normally.
// -------------------------------------------------------
add_filter( 'wp_redirect', 'ha_pf_intercept_options_redirect', 10, 2 );

function ha_pf_intercept_options_redirect( $location, $status ) {

    // Only act when our settings group was submitted via options.php
    if ( ! isset( $_POST['option_page'] ) ) {
        return $location;
    }
    if ( sanitize_text_field( wp_unslash( $_POST['option_page'] ) ) !== 'ha_pf_settings_group' ) {
        return $location;
    }

    // All options are now in the database — write the snapshot
    ha_pf_write_config_snapshot();

    // Return the redirect location unchanged so WordPress continues normally
    return $location;
}

// -------------------------------------------------------
// Build the YAML string from current options and write it
// -------------------------------------------------------
function ha_pf_write_config_snapshot() {

    // --------------------------------------------------
    // 1. Resolve the config directory
    // --------------------------------------------------
    $upload     = wp_upload_dir();
    $config_dir = $upload['basedir'] . '/ha-powerflow/config';

    if ( ! file_exists( $config_dir ) ) {
        wp_mkdir_p( $config_dir );
    }

    if ( ! is_writable( $config_dir ) ) {
        error_log( 'HA PowerFlow: config directory is not writable — ' . $config_dir );
        return;
    }

    // --------------------------------------------------
    // 2. Build the YAML
    // --------------------------------------------------
    $yaml = ha_pf_build_yaml();

    // --------------------------------------------------
    // 3. Write the file  (format: YYMMDD-hhmmss-config.yaml)
    // --------------------------------------------------
    $filename = date( 'ymd-His' ) . '-config.yaml';
    $filepath = $config_dir . '/' . $filename;

    $bytes = file_put_contents( $filepath, $yaml, LOCK_EX );

    if ( $bytes === false ) {
        error_log( 'HA PowerFlow: failed to write config snapshot — ' . $filepath );
        return;
    }

    // --------------------------------------------------
    // 4. Prune old files — keep the 50 most recent
    // --------------------------------------------------
    ha_pf_prune_config_dir( $config_dir, 50 );
}

// -------------------------------------------------------
// Assemble the full YAML string
// -------------------------------------------------------
function ha_pf_build_yaml() {

    // Shorthand helpers
    $opt = function( $key, $default = '' ) {
        $val = get_option( 'ha_powerflow_' . $key );
        return ( $val !== false && $val !== '' ) ? $val : $default;
    };

    // Encrypt the token — never store it in plain text in the file
    $raw_token       = get_option( 'ha_powerflow_ha_token', '' );
    $encrypted_token = ha_pf_encrypt( $raw_token );

    // All entity keys that have rot/x_pos/y_pos
    $entities = [
        'grid_power', 'grid_energy_in', 'grid_energy_out',
        'load_power', 'load_energy',
        'pv_power',   'pv_energy',
        'battery_power', 'battery_energy_in', 'battery_energy_out', 'battery_soc',
        'ev_power',   'ev_soc',
    ];

    // Flow names
    $flows = [ 'grid', 'load', 'pv', 'battery', 'ev' ];

    // --------------------------------------------------
    // Build the data structure then serialise to YAML
    // --------------------------------------------------

    $lines = [];

    // ---- meta ----------------------------------------
    $lines[] = '# HA PowerFlow configuration snapshot';
    $lines[] = '# Generated by HA PowerFlow v' . HA_PF_VERSION;
    $lines[] = '# Import: Settings > HA PowerFlow > Import Config';
    $lines[] = '#';
    $lines[] = '# The ha_token value is AES-256-CBC encrypted using this';
    $lines[] = '# site\'s AUTH_KEY. It will only decrypt correctly on the';
    $lines[] = '# same WordPress installation that created it.';
    $lines[] = '';
    $lines[] = 'meta:';
    $lines[] = '  plugin_version: ' . ha_pf_yaml_scalar( HA_PF_VERSION );
    $lines[] = '  exported_at: '    . ha_pf_yaml_scalar( date( 'Y-m-d H:i:s' ) );
    $lines[] = '  site_url: '       . ha_pf_yaml_scalar( get_site_url() );
    $lines[] = '';

    // ---- connection ----------------------------------
    $lines[] = 'connection:';
    $lines[] = '  ha_url: '   . ha_pf_yaml_scalar( get_option( 'ha_powerflow_ha_url', '' ) );
    $lines[] = '  ha_token: ' . ha_pf_yaml_scalar( $encrypted_token );
    $lines[] = '  ssl_verify: ' . ( $opt( 'ssl_verify', '1' ) === '1' ? 'true' : 'false' );
    $lines[] = '  refresh_interval: ' . (int) max( 5, min( 300, (int) $opt( 'refresh_interval', '5' ) ) );
    $lines[] = '';

    // ---- features ------------------------------------
    $lines[] = 'features:';
    $lines[] = '  enable_solar: '   . ( $opt( 'enable_solar' )   === '1' ? 'true' : 'false' );
    $lines[] = '  enable_battery: ' . ( $opt( 'enable_battery' ) === '1' ? 'true' : 'false' );
    $lines[] = '  enable_ev: '      . ( $opt( 'enable_ev' )      === '1' ? 'true' : 'false' );
    $lines[] = '';

    // ---- appearance ----------------------------------
    $lines[] = 'appearance:';
    $lines[] = '  image_url: '   . ha_pf_yaml_scalar( $opt( 'image_url' ) );
    $lines[] = '  text_colour: ' . ha_pf_yaml_scalar( $opt( 'text_colour', '#5EC766' ) );
    $lines[] = '  line_colour: ' . ha_pf_yaml_scalar( $opt( 'line_colour', '#5EC766' ) );
    $lines[] = '  dot_colour: '  . ha_pf_yaml_scalar( $opt( 'dot_colour',  '#5EC766' ) );
    $lines[] = '';

    // ---- flow_paths ----------------------------------
    $lines[] = 'flow_paths:';
    foreach ( $flows as $flow ) {
        $fwd = $opt( $flow . '_flow_forward' );
        $rev = $opt( $flow . '_flow_reverse' );
        $lines[] = '  ' . $flow . ':';
        $lines[] = '    forward: ' . ha_pf_yaml_scalar( $fwd );
        $lines[] = '    reverse: ' . ha_pf_yaml_scalar( $rev );
    }
    $lines[] = '';

    // ---- entities ------------------------------------
    $lines[] = 'entities:';
    foreach ( $entities as $key ) {
        $lines[] = '  ' . $key . ':';
        $lines[] = '    entity_id: ' . ha_pf_yaml_scalar( $opt( $key ) );
        $lines[] = '    rot: '       . intval( $opt( $key . '_rot',   '0' ) );
        $lines[] = '    x_pos: '     . absint( $opt( $key . '_x_pos', '0' ) );
        $lines[] = '    y_pos: '     . absint( $opt( $key . '_y_pos', '0' ) );
    }
    $lines[] = '';

    // ---- preferences ---------------------------------
    $lines[] = 'preferences:';
    $lines[] = '  delete_uploads: '  . ( $opt( 'delete_uploads' ) === '1' ? 'true' : 'false' );
    $lines[] = '  debug_click: '     . ( $opt( 'debug_click'    ) === '1' ? 'true' : 'false' );
    $lines[] = '';

    // ---- battery_gauge -------------------------------
    $lines[] = 'battery_gauge:';
    $lines[] = '  enable: ' . ( $opt( 'battery_gauge_enable' ) === '1' ? 'true' : 'false' );
    $lines[] = '  x: '     . absint( $opt( 'battery_gauge_x', '95'  ) );
    $lines[] = '  y: '     . absint( $opt( 'battery_gauge_y', '605' ) );
    $lines[] = '';

    // ---- ev_gauge ------------------------------------
    $lines[] = 'ev_gauge:';
    $lines[] = '  enable: ' . ( $opt( 'ev_gauge_enable' ) === '1' ? 'true' : 'false' );
    $lines[] = '  x: '     . absint( $opt( 'ev_gauge_x', '500' ) );
    $lines[] = '  y: '     . absint( $opt( 'ev_gauge_y', '375' ) );
    $lines[] = '';

    // ---- custom_entities -----------------------------
    $custom_raw  = get_option( 'ha_powerflow_custom_entities', '[]' );
    $custom_list = json_decode( $custom_raw ?: '[]', true );
    if ( ! is_array( $custom_list ) ) $custom_list = [];

    $lines[] = 'custom_entities:';
    if ( empty( $custom_list ) ) {
        $lines[] = '  []';
    } else {
        foreach ( $custom_list as $item ) {
            $lines[] = '  - id: '        . ha_pf_yaml_scalar( $item['id']        ?? '' );
            $lines[] = '    label: '     . ha_pf_yaml_scalar( $item['label']     ?? '' );
            $lines[] = '    entity_id: ' . ha_pf_yaml_scalar( $item['entity_id'] ?? '' );
            $lines[] = '    unit: '      . ha_pf_yaml_scalar( $item['unit']      ?? '' );
            $lines[] = '    size: '      . absint( $item['size'] ?? 14 );
            $lines[] = '    rot: '       . intval( $item['rot'] ?? 0 );
            $lines[] = '    x: '         . absint( $item['x']   ?? 0 );
            $lines[] = '    y: '         . absint( $item['y']   ?? 0 );
            $lines[] = '    visible: '   . ( ! empty( $item['visible'] ) ? 'true' : 'false' );
        }
    }

    return implode( "\n", $lines ) . "\n";
}

// -------------------------------------------------------
// YAML scalar serialiser
//
// Wraps values in double-quoted strings, escaping any
// characters that would break YAML parsing (backslash,
// double-quote, control characters).
// Empty values are written as empty quoted strings "".
// Booleans and numbers that are set explicitly elsewhere
// are NOT passed through here (they bypass quoting).
// -------------------------------------------------------
function ha_pf_yaml_scalar( $value ) {
    if ( $value === null || $value === false ) {
        return '""';
    }
    $value = (string) $value;

    // Escape backslashes first, then double-quotes, then newlines/tabs
    $value = str_replace( '\\', '\\\\', $value );
    $value = str_replace( '"',  '\\"',  $value );
    $value = str_replace( "\n", '\\n',  $value );
    $value = str_replace( "\r", '\\r',  $value );
    $value = str_replace( "\t", '\\t',  $value );

    return '"' . $value . '"';
}

// -------------------------------------------------------
// Prune the config directory
// Keeps the $keep most recent *.yaml files, deletes the rest.
// -------------------------------------------------------
function ha_pf_prune_config_dir( $dir, $keep = 50 ) {
    $pattern = $dir . '/*-config.yaml';
    $files   = glob( $pattern );

    if ( ! $files || count( $files ) <= $keep ) {
        return;
    }

    // glob() returns alphabetical order; our filenames start with
    // YYMMDD-hhmmss so alphabetical == chronological.
    sort( $files );

    $to_delete = array_slice( $files, 0, count( $files ) - $keep );

    foreach ( $to_delete as $old_file ) {
        @unlink( $old_file );
    }
}

// -------------------------------------------------------
// FUTURE IMPORT — stubbed and ready
//
// Call ha_pf_import_config( $yaml_string ) from an import UI.
// Returns true on success, WP_Error on failure.
// -------------------------------------------------------
function ha_pf_import_config( $yaml_string ) {

    $data = ha_pf_parse_yaml( $yaml_string );

    if ( is_wp_error( $data ) ) {
        return $data;
    }

    // Connection
    if ( isset( $data['connection'] ) ) {
        $conn = $data['connection'];

        if ( ! empty( $conn['ha_url'] ) ) {
            update_option( 'ha_powerflow_ha_url', esc_url_raw( $conn['ha_url'] ) );
        }

        if ( ! empty( $conn['ha_token'] ) ) {
            // Decrypt if it was encrypted with our scheme
            $token = ha_pf_is_encrypted( $conn['ha_token'] )
                ? ha_pf_decrypt( $conn['ha_token'] )
                : sanitize_text_field( $conn['ha_token'] );

            if ( $token !== '' ) {
                update_option( 'ha_powerflow_ha_token', $token );
            }
        }

        if ( isset( $conn['ssl_verify'] ) ) {
            update_option( 'ha_powerflow_ssl_verify', $conn['ssl_verify'] ? '1' : '0' );
        }
        if ( isset( $conn['refresh_interval'] ) ) {
            $ri = (int) $conn['refresh_interval'];
            if ( $ri < 5   ) $ri = 5;
            if ( $ri > 300 ) $ri = 300;
            update_option( 'ha_powerflow_refresh_interval', $ri );
        }
    }

    // Features
    if ( isset( $data['features'] ) ) {
        $feat = $data['features'];
        foreach ( [ 'enable_solar', 'enable_battery', 'enable_ev' ] as $key ) {
            if ( isset( $feat[ $key ] ) ) {
                update_option( 'ha_powerflow_' . $key, $feat[ $key ] ? '1' : '0' );
            }
        }
    }

    // Appearance
    if ( isset( $data['appearance'] ) ) {
        $app = $data['appearance'];
        if ( ! empty( $app['image_url'] ) )   update_option( 'ha_powerflow_image_url',   esc_url_raw( $app['image_url'] ) );
        if ( ! empty( $app['text_colour'] ) )  update_option( 'ha_powerflow_text_colour', sanitize_hex_color( $app['text_colour'] ) ?: '#5EC766' );
        if ( ! empty( $app['line_colour'] ) )  update_option( 'ha_powerflow_line_colour', sanitize_hex_color( $app['line_colour'] ) ?: '#5EC766' );
        if ( ! empty( $app['dot_colour'] ) )   update_option( 'ha_powerflow_dot_colour',  sanitize_hex_color( $app['dot_colour']  ) ?: '#5EC766' );
    }

    // Flow paths
    if ( isset( $data['flow_paths'] ) ) {
        $flows = [ 'grid', 'load', 'pv', 'battery', 'ev' ];
        foreach ( $flows as $flow ) {
            if ( ! isset( $data['flow_paths'][ $flow ] ) ) continue;
            $fp = $data['flow_paths'][ $flow ];
            if ( isset( $fp['forward'] ) ) update_option( 'ha_powerflow_' . $flow . '_flow_forward', ha_pf_sanitize_svg_path( $fp['forward'] ) );
            if ( isset( $fp['reverse'] ) ) update_option( 'ha_powerflow_' . $flow . '_flow_reverse', ha_pf_sanitize_svg_path( $fp['reverse'] ) );
        }
    }

    // Entities
    if ( isset( $data['entities'] ) ) {
        $entity_keys = [
            'grid_power', 'grid_energy_in', 'grid_energy_out',
            'load_power', 'load_energy',
            'pv_power',   'pv_energy',
            'battery_power', 'battery_energy_in', 'battery_energy_out', 'battery_soc',
            'ev_power',   'ev_soc',
        ];
        foreach ( $entity_keys as $key ) {
            if ( ! isset( $data['entities'][ $key ] ) ) continue;
            $e = $data['entities'][ $key ];
            if ( isset( $e['entity_id'] ) ) update_option( 'ha_powerflow_' . $key,           sanitize_text_field( $e['entity_id'] ) );
            if ( isset( $e['rot'] ) )       update_option( 'ha_powerflow_' . $key . '_rot',   intval( $e['rot'] ) );
            if ( isset( $e['x_pos'] ) )     update_option( 'ha_powerflow_' . $key . '_x_pos', absint( $e['x_pos'] ) );
            if ( isset( $e['y_pos'] ) )     update_option( 'ha_powerflow_' . $key . '_y_pos', absint( $e['y_pos'] ) );
        }
    }

    // Preferences
    if ( isset( $data['preferences'] ) ) {
        $prefs = $data['preferences'];
        if ( isset( $prefs['delete_uploads'] ) ) {
            update_option( 'ha_powerflow_delete_uploads', $prefs['delete_uploads'] ? '1' : '0' );
        }
        if ( isset( $prefs['debug_click'] ) ) {
            update_option( 'ha_powerflow_debug_click', $prefs['debug_click'] ? '1' : '0' );
        }
    }

    // Battery gauge
    if ( isset( $data['battery_gauge'] ) ) {
        $gauge = $data['battery_gauge'];
        if ( isset( $gauge['enable'] ) ) {
            update_option( 'ha_powerflow_battery_gauge_enable', $gauge['enable'] ? '1' : '0' );
        }
        if ( isset( $gauge['x'] ) ) {
            update_option( 'ha_powerflow_battery_gauge_x', absint( $gauge['x'] ) );
        }
        if ( isset( $gauge['y'] ) ) {
            update_option( 'ha_powerflow_battery_gauge_y', absint( $gauge['y'] ) );
        }
    }

    // EV gauge
    if ( isset( $data['ev_gauge'] ) ) {
        $gauge = $data['ev_gauge'];
        if ( isset( $gauge['enable'] ) ) {
            update_option( 'ha_powerflow_ev_gauge_enable', $gauge['enable'] ? '1' : '0' );
        }
        if ( isset( $gauge['x'] ) ) {
            update_option( 'ha_powerflow_ev_gauge_x', absint( $gauge['x'] ) );
        }
        if ( isset( $gauge['y'] ) ) {
            update_option( 'ha_powerflow_ev_gauge_y', absint( $gauge['y'] ) );
        }
    }

    // Custom entities
    if ( isset( $data['custom_entities'] ) && is_array( $data['custom_entities'] ) ) {
        $items = [];
        foreach ( $data['custom_entities'] as $item ) {
            if ( ! is_array( $item ) || empty( $item['id'] ) ) continue;
            $id = preg_replace( '/[^a-z0-9_]/', '', strtolower( sanitize_text_field( $item['id'] ) ) );
            if ( $id === '' ) continue;
            $items[] = [
                'id'        => $id,
                'label'     => substr( sanitize_text_field( $item['label']     ?? '' ), 0, 60 ),
                'entity_id' => substr( sanitize_text_field( $item['entity_id'] ?? '' ), 0, 100 ),
                'unit'      => substr( sanitize_text_field( $item['unit']      ?? '' ), 0, 20 ),
                'size'      => min( 72, max( 6, absint( $item['size'] ?? 14 ) ) ),
                'rot'       => intval( $item['rot'] ?? 0 ),
                'x'         => absint( $item['x']   ?? 0 ),
                'y'         => absint( $item['y']   ?? 0 ),
                'visible'   => ! empty( $item['visible'] ),
            ];
        }
        update_option( 'ha_powerflow_custom_entities', wp_json_encode( $items ) );
    }

    return true;
}

// -------------------------------------------------------
// AJAX handler: import a config file uploaded from the browser
// -------------------------------------------------------
add_action( 'wp_ajax_ha_pf_import_config_ajax', 'ha_pf_ajax_import_config' );

function ha_pf_ajax_import_config() {

    // --- 1. Verify nonce and capability ---
    if ( ! check_ajax_referer( 'ha_pf_import_config', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => 'Invalid security token. Please refresh the page and try again.' ] );
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'You do not have permission to import settings.' ] );
    }

    // --- 2. Check a file was actually uploaded ---
    if ( empty( $_FILES['config_file'] ) || $_FILES['config_file']['error'] !== UPLOAD_ERR_OK ) {
        $code = isset( $_FILES['config_file'] ) ? $_FILES['config_file']['error'] : -1;
        wp_send_json_error( [ 'message' => 'File upload failed (code ' . intval( $code ) . '). Check your server upload_max_filesize setting.' ] );
    }

    $file = $_FILES['config_file'];

    // --- 3. Sanity checks on the uploaded file ---
    if ( $file['size'] > 512 * 1024 ) {  // 512 KB max — a valid config is < 10 KB
        wp_send_json_error( [ 'message' => 'File is too large. A valid HA PowerFlow config file should be under 512 KB.' ] );
    }

    $ext = strtolower( pathinfo( sanitize_file_name( $file['name'] ), PATHINFO_EXTENSION ) );
    if ( ! in_array( $ext, [ 'yaml', 'yml' ], true ) ) {
        wp_send_json_error( [ 'message' => 'Only .yaml or .yml files are accepted.' ] );
    }

    // --- 4. Read the file ---
    $yaml = file_get_contents( $file['tmp_name'] );
    if ( $yaml === false ) {
        wp_send_json_error( [ 'message' => 'Could not read the uploaded file.' ] );
    }

    // Sanity-check it looks like one of our files
    if ( strpos( $yaml, 'meta:' ) === false || strpos( $yaml, 'connection:' ) === false ) {
        wp_send_json_error( [ 'message' => 'This does not appear to be a valid HA PowerFlow config file.' ] );
    }

    // --- 5. Import ---
    $result = ha_pf_import_config( $yaml );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    }

    // --- 6. Write a fresh snapshot so the restore is itself backed up ---
    ha_pf_write_config_snapshot();

    wp_send_json_success( [ 'message' => 'Settings restored successfully.' ] );
}

// -------------------------------------------------------
// Minimal YAML parser
//
// Handles the specific subset of YAML this plugin writes:
//   - Comments (#)
//   - Top-level keys (no indent)
//   - Two-level nested keys (2-space indent)
//   - Three-level nested keys (4-space indent)
//   - Quoted strings "value"
//   - Unquoted booleans: true / false
//   - Unquoted integers
//
// This avoids a dependency on a third-party YAML library
// while remaining fully compatible with the files we write.
// -------------------------------------------------------
function ha_pf_parse_yaml( $yaml_string ) {
    $lines           = explode( "\n", $yaml_string );
    $result          = [];
    $path            = [];   // current nesting path, e.g. ['connection'] or ['entities','grid_power']
    $list_depth      = -1;   // indent-depth at which the current list lives (-1 = not in a list)
    $list_item_index = -1;   // index of the current list item within that list

    foreach ( $lines as $raw ) {

        // Strip trailing whitespace; skip blank lines and comments
        $line = rtrim( $raw );
        if ( $line === '' || ltrim( $line )[0] === '#' ) continue;

        // Measure indent (spaces only — our writer uses 2-space indents)
        $indent  = strlen( $line ) - strlen( ltrim( $line ) );
        $trimmed = ltrim( $line );

        // ── Empty-list shorthand: "  []" ──────────────────────────────────
        // Written for sections with no items. The parent key was already set
        // to [] when its "key:" line was processed, so nothing to do here.
        if ( $trimmed === '[]' ) continue;

        // ── List item: line beginning with "- " ───────────────────────────
        if ( substr( $trimmed, 0, 2 ) === '- ' ) {
            $depth = intval( $indent / 2 );

            // Start a new item counter or advance the existing one
            if ( $list_depth !== $depth ) {
                $list_depth      = $depth;
                $list_item_index = 0;
            } else {
                $list_item_index++;
            }

            // Update $path so it points to this list item:
            // slice back to the parent level and append the numeric index.
            $path = array_slice( $path, 0, $depth );
            $path[] = $list_item_index;

            // Parse the  key: value  that follows the "- " on the same line
            $item_content = substr( $trimmed, 2 );
            if ( preg_match( '/^([a-zA-Z0-9_]+)\s*:\s*(.*)$/', $item_content, $m ) ) {
                $key     = $m[1];
                $decoded = ha_pf_yaml_decode_scalar( trim( $m[2] ) );
                ha_pf_yaml_set( $result, array_merge( $path, [ $key ] ), $decoded );
            }
            continue;
        }

        // ── Regular  key: value  or  key:  (parent) ───────────────────────
        if ( ! preg_match( '/^([a-zA-Z0-9_]+)\s*:\s*(.*)$/', $trimmed, $m ) ) continue;

        $key   = $m[1];
        $value = trim( $m[2] );

        if ( $value === '' ) {
            // Parent key — push onto path and reset list tracking
            $depth           = intval( $indent / 2 );
            $path            = array_slice( $path, 0, $depth );
            $path[]          = $key;
            $list_depth      = -1;
            $list_item_index = -1;
            ha_pf_yaml_set( $result, $path, [] );
            continue;
        }

        // Scalar value — write at current depth
        $decoded      = ha_pf_yaml_decode_scalar( $value );
        $depth        = intval( $indent / 2 );
        $current_path = array_slice( $path, 0, $depth );
        $current_path[] = $key;

        ha_pf_yaml_set( $result, $current_path, $decoded );
    }

    if ( empty( $result ) ) {
        return new WP_Error( 'yaml_parse_failed', 'Could not parse the YAML file.' );
    }

    return $result;
}

/** Set a value in a nested array using a path array. */
function ha_pf_yaml_set( &$array, $path, $value ) {
    $ref = &$array;
    foreach ( $path as $key ) {
        if ( ! isset( $ref[ $key ] ) || ! is_array( $ref[ $key ] ) ) {
            $ref[ $key ] = [];
        }
        $ref = &$ref[ $key ];
    }
    $ref = $value;
}

/** Decode a YAML scalar string to a PHP value. */
function ha_pf_yaml_decode_scalar( $raw ) {
    // Quoted string: strip quotes and unescape
    if ( strlen( $raw ) >= 2 && $raw[0] === '"' && substr( $raw, -1 ) === '"' ) {
        $inner = substr( $raw, 1, -1 );
        $inner = str_replace( '\\"',  '"',  $inner );
        $inner = str_replace( '\\\\', '\\', $inner );
        $inner = str_replace( '\\n',  "\n", $inner );
        $inner = str_replace( '\\r',  "\r", $inner );
        $inner = str_replace( '\\t',  "\t", $inner );
        return $inner;
    }

    // Boolean
    if ( $raw === 'true'  ) return true;
    if ( $raw === 'false' ) return false;

    // Integer
    if ( preg_match( '/^-?\d+$/', $raw ) ) return intval( $raw );

    // Fallback: return as-is
    return $raw;
}
