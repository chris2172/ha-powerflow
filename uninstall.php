<?php
/**
 * uninstall.php
 *
 * Runs when the plugin is deleted via the WordPress admin.
 * Cleans up all options and, if the admin opted in, removes
 * uploaded background images from wp-content/uploads/ha-powerflow/.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

// -------------------------------------------------------
// Optionally delete uploaded images
// -------------------------------------------------------
if ( get_option( 'ha_powerflow_delete_uploads' ) === '1' ) {
    $upload = wp_upload_dir();
    $dir    = $upload['basedir'] . '/ha-powerflow';

    if ( is_dir( $dir ) ) {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ( $iter as $file ) {
            if ( $file->isDir() ) {
                rmdir( $file->getRealPath() );
            } else {
                unlink( $file->getRealPath() );
            }
        }

        rmdir( $dir );
    }
}

// -------------------------------------------------------
// Delete all plugin options from wp_options
// -------------------------------------------------------

// Simple options (no sub-keys)
$simple_options = [
    'ha_powerflow_ha_url',
    'ha_powerflow_ha_token',
    'ha_powerflow_ssl_verify',
    'ha_powerflow_enable_solar',
    'ha_powerflow_enable_battery',
    'ha_powerflow_enable_ev',
    'ha_powerflow_image_url',
    'ha_powerflow_delete_uploads',
    'ha_powerflow_text_colour',
    'ha_powerflow_line_colour',
    'ha_powerflow_dot_colour',
    'ha_powerflow_grid_flow_forward',
    'ha_powerflow_grid_flow_reverse',
    'ha_powerflow_load_flow_forward',
    'ha_powerflow_load_flow_reverse',
    'ha_powerflow_pv_flow_forward',
    'ha_powerflow_pv_flow_reverse',
    'ha_powerflow_battery_flow_forward',
    'ha_powerflow_battery_flow_reverse',
    'ha_powerflow_ev_flow_forward',
    'ha_powerflow_ev_flow_reverse',
    'ha_powerflow_debug_click',
    'ha_powerflow_battery_gauge_enable',
    'ha_powerflow_battery_gauge_x',
    'ha_powerflow_battery_gauge_y',
];

foreach ( $simple_options as $option ) {
    delete_option( $option );
}

// Entity options — each has entity_id, _rot, _x_pos, _y_pos
$entities = [
    'grid_power', 'grid_energy_in', 'grid_energy_out',
    'load_power', 'load_energy',
    'pv_power',   'pv_energy',
    'battery_power', 'battery_energy_in', 'battery_energy_out', 'battery_soc',
    'ev_power',   'ev_soc',
];

foreach ( $entities as $entity ) {
    delete_option( 'ha_powerflow_' . $entity );
    delete_option( 'ha_powerflow_' . $entity . '_rot' );
    delete_option( 'ha_powerflow_' . $entity . '_x_pos' );
    delete_option( 'ha_powerflow_' . $entity . '_y_pos' );
}
