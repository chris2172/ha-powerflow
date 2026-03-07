<?php
/**
 * Plugin Name: HA Powerflow
 * Plugin URI:  https://github.com/
 * Description: Display live Home Assistant power flow on your WordPress site using [ha_powerflow]
 * Version:     1.18.7
 * Author:      HA Powerflow
 * License:     GPL2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'HA_POWERFLOW_VERSION', '1.23.1' );

// ── Activation: create uploads folder ────────────────────────────────────────
register_activation_hook( __FILE__, 'ha_powerflow_activate' );
function ha_powerflow_activate() {
    $upload_dir = wp_upload_dir();
    $pf_dir     = $upload_dir['basedir'] . '/ha-powerflow';
    if ( ! file_exists( $pf_dir ) ) {
        wp_mkdir_p( $pf_dir );
        // Drop an index.php to prevent directory listing
        @file_put_contents( $pf_dir . '/index.php', "<?php // Silence is golden.\n" );
    }
}
define( 'HA_POWERFLOW_DIR',     plugin_dir_path( __FILE__ ) );
define( 'HA_POWERFLOW_URL',     plugin_dir_url( __FILE__ ) );

require_once HA_POWERFLOW_DIR . 'admin/settings.php';

require_once HA_POWERFLOW_DIR . 'includes/class-ha-powerflow-shortcode.php';
require_once HA_POWERFLOW_DIR . 'includes/class-ha-powerflow-ajax.php';

HA_Powerflow_Shortcode::init();
HA_Powerflow_Ajax::init();

