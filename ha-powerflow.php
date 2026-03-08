<?php
/**
 * Plugin Name: HA Powerflow
 * Plugin URI:  https://github.com/chris2172/ha-powerflow
 * Description: Display live Home Assistant power flow on your WordPress site using [ha_powerflow]
 * Version:     2.0.0
 * Author:      HA Powerflow
 * License:     GPL2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'HA_POWERFLOW_VERSION', '2.0.0' );

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
    $cfg_dir = $pf_dir . '/config';
    if ( ! file_exists( $cfg_dir ) ) {
        wp_mkdir_p( $cfg_dir );
        @file_put_contents( $cfg_dir . '/index.php', "<?php // Silence is golden.\n" );
    }
}
define( 'HA_POWERFLOW_DIR',     plugin_dir_path( __FILE__ ) );
define( 'HA_POWERFLOW_URL',     plugin_dir_url( __FILE__ ) );

$upload_dir = wp_upload_dir();
define( 'HA_POWERFLOW_CONFIG_DIR', $upload_dir['basedir'] . '/ha-powerflow/config/' );

require_once HA_POWERFLOW_DIR . 'admin/settings.php';

require_once HA_POWERFLOW_DIR . 'includes/class-ha-powerflow-shortcode.php';
require_once HA_POWERFLOW_DIR . 'includes/class-ha-powerflow-manual-shortcode.php';
require_once HA_POWERFLOW_DIR . 'includes/class-ha-powerflow-ajax.php';

HA_Powerflow_Shortcode::init();
HA_Powerflow_Manual_Shortcode::init();
HA_Powerflow_Ajax::init();

