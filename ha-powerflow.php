<?php
/**
 * Plugin Name: HA PowerFlow
 * Plugin URI:  https://chriswilmot.co.uk/ha-powerflow/
 * Description: A real-time Home Assistant power-flow dashboard with animated SVG and CSS motion paths.
 * Version:     2.2.0
 * Author:      Christopher Wilmot
 * Author URI:  https://chriswilmot.co.uk/
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP: 7.4
 * Domain Path: /languages
 * Text Domain: ha-powerflow
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// -------------------------------------------------------
// Plugin-wide constants
// -------------------------------------------------------
define( 'HA_PF_VERSION',  '2.2.0' );
define( 'HA_PF_DIR',      plugin_dir_path( __FILE__ ) );
define( 'HA_PF_URL',      plugin_dir_url( __FILE__ ) );
define( 'HA_PF_OPT_PRE',  'ha_powerflow_' );   // option name prefix

// -------------------------------------------------------
// Activation: create upload directory
// -------------------------------------------------------
register_activation_hook( __FILE__, 'ha_pf_activate' );

function ha_pf_activate() {
    $upload = wp_upload_dir();
    $base   = $upload['basedir'] . '/ha-powerflow';

    // Main upload directory
    if ( ! file_exists( $base ) ) {
        wp_mkdir_p( $base );
    }

    // Config snapshot directory
    $config_dir = $base . '/config';
    if ( ! file_exists( $config_dir ) ) {
        wp_mkdir_p( $config_dir );
    }
}

// -------------------------------------------------------
// Load core files (always needed)
// -------------------------------------------------------
require_once HA_PF_DIR . 'includes/settings-register.php';
require_once HA_PF_DIR . 'includes/config-crypto.php';   // AES encrypt/decrypt
require_once HA_PF_DIR . 'includes/config-export.php';   // YAML snapshot on save
require_once HA_PF_DIR . 'includes/ajax-proxy.php';
require_once HA_PF_DIR . 'includes/shortcode.php';

// -------------------------------------------------------
// Load admin-only files
// -------------------------------------------------------
if ( is_admin() ) {
    require_once HA_PF_DIR . 'includes/ajax-image.php';
    require_once HA_PF_DIR . 'admin/settings-page.php';
}

// -------------------------------------------------------
// Enqueue admin scripts/styles (settings page only)
// -------------------------------------------------------
add_action( 'admin_enqueue_scripts', 'ha_pf_admin_enqueue' );

function ha_pf_admin_enqueue( $hook ) {
    if ( $hook !== 'toplevel_page_ha-powerflow' ) {
        return;
    }
    wp_enqueue_media();
    wp_enqueue_script( 'jquery' );
    wp_enqueue_style(
        'ha-pf-admin',
        HA_PF_URL . 'admin/admin.css',
        [],
        HA_PF_VERSION
    );
    wp_enqueue_script(
        'ha-pf-admin',
        HA_PF_URL . 'admin/admin.js',
        [ 'jquery' ],
        HA_PF_VERSION,
        true
    );
    // Pass the nonce for the image-copy AJAX call to JS
    wp_localize_script( 'ha-pf-admin', 'haPfAdmin', [
        'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
        'copyImageNonce' => wp_create_nonce( 'ha_pf_copy_image' ),
    ] );
}

// -------------------------------------------------------
// Internationalisation — load translations if available
// -------------------------------------------------------
add_action( 'plugins_loaded', 'ha_pf_load_textdomain' );

function ha_pf_load_textdomain() {
    load_plugin_textdomain(
        'ha-powerflow',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );
}
