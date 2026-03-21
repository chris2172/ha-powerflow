<?php
/**
 * Plugin Name: HA Powerflow
 * Plugin URI:  https://github.com/chris2172/ha-powerflow
 * Description: Display live Home Assistant power flow on your WordPress site using [ha_powerflow]
 * Version:     2.2.0
 * Author:      HA Powerflow
 * License:     GPL2
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'HA_POWERFLOW_VERSION', '2.2.0' );

// ── Activation: create uploads folder ────────────────────────────────────────
register_activation_hook( __FILE__, 'ha_powerflow_activate' );
function ha_powerflow_activate() {
    if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( 'This plugin requires PHP 7.4 or higher. Please upgrade your PHP version.' );
    }

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
    
    // Always ensure .htaccess exists to block direct access to YAML snapshots
    $htaccess_path = $cfg_dir . '/.htaccess';
    if ( ! file_exists( $htaccess_path ) ) {
        $htaccess_content = "# Protect HA Powerflow Snapshots\nOrder deny,allow\nDeny from all\n";
        @file_put_contents( $htaccess_path, $htaccess_content );
    }

    ha_pf_create_tables();
}

function ha_pf_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_sessions = $wpdb->prefix . 'ha_pf_ev_sessions';
    $sql_sessions = "CREATE TABLE $table_sessions (
        id varchar(50) NOT NULL,
        start_ts bigint(20) NOT NULL,
        end_ts bigint(20) DEFAULT NULL,
        status varchar(20) NOT NULL,
        currency varchar(10) DEFAULT '£' NOT NULL,
        cost_rate float DEFAULT 0 NOT NULL,
        last_mode varchar(50) DEFAULT '' NOT NULL,
        customer_id varchar(50) DEFAULT NULL,
        payment_received tinyint(1) DEFAULT 0 NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    $table_points = $wpdb->prefix . 'ha_pf_ev_data_points';
    $sql_points = "CREATE TABLE $table_points (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        session_id varchar(50) NOT NULL,
        ts bigint(20) NOT NULL,
        kwh float NOT NULL,
        power float NOT NULL,
        solar float DEFAULT 0 NOT NULL,
        PRIMARY KEY  (id),
        KEY session_id (session_id)
    ) $charset_collate;";

    $table_bookings = $wpdb->prefix . 'ha_pf_ev_bookings';
    $sql_bookings = "CREATE TABLE $table_bookings (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        start_time datetime NOT NULL,
        end_time datetime NOT NULL,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY start_time (start_time)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql_sessions );
    dbDelta( $sql_points );
    dbDelta( $sql_bookings );
}
define( 'HA_POWERFLOW_DIR',     plugin_dir_path( __FILE__ ) );
define( 'HA_POWERFLOW_URL',     plugin_dir_url( __FILE__ ) );

global $wpdb;
define( 'HA_POWERFLOW_TABLE_SESSIONS', $wpdb->prefix . 'ha_pf_ev_sessions' );
define( 'HA_POWERFLOW_TABLE_POINTS',   $wpdb->prefix . 'ha_pf_ev_data_points' );
define( 'HA_POWERFLOW_TABLE_BOOKINGS', $wpdb->prefix . 'ha_pf_ev_bookings' );

$upload_dir = wp_upload_dir();
define( 'HA_POWERFLOW_CONFIG_DIR', $upload_dir['basedir'] . '/ha-powerflow/config/' );

require_once HA_POWERFLOW_DIR . 'admin/settings.php';

require_once HA_POWERFLOW_DIR . 'includes/class-ha-powerflow-modules.php';
require_once HA_POWERFLOW_DIR . 'includes/class-ha-powerflow-shortcode.php';
require_once HA_POWERFLOW_DIR . 'includes/class-ha-powerflow-manual-shortcode.php';
require_once HA_POWERFLOW_DIR . 'includes/class-ha-powerflow-forecast-shortcode.php';
require_once HA_POWERFLOW_DIR . 'includes/class-ha-powerflow-ajax.php';
require_once HA_POWERFLOW_DIR . 'includes/class-ha-powerflow-ev-session.php';
require_once HA_POWERFLOW_DIR . 'includes/class-ha-powerflow-calendar-shortcode.php';

HA_Powerflow_Shortcode::init();
HA_Powerflow_Manual_Shortcode::init();
HA_Powerflow_Ajax::init();
HA_Powerflow_EV_Session::init();
HA_Powerflow_Calendar_Shortcode::init();

// ── PWA Support ─────────────────────────────────────────────────────────────
add_action( 'wp_head', 'ha_powerflow_pwa_headers' );
function ha_powerflow_pwa_headers() {
    ?>
    <link rel="manifest" href="<?php echo HA_POWERFLOW_URL; ?>assets/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Powerflow">
    <meta name="theme-color" content="#0f172a">
    <link rel="apple-touch-icon" href="<?php echo HA_POWERFLOW_URL; ?>assets/images/icons/pwa-icon-192.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo HA_POWERFLOW_URL; ?>assets/images/icons/pwa-icon-192.png">
    <link rel="apple-touch-icon" sizes="152x152" href="<?php echo HA_POWERFLOW_URL; ?>assets/images/icons/pwa-icon-192.png">
    <link rel="apple-touch-icon" sizes="167x167" href="<?php echo HA_POWERFLOW_URL; ?>assets/images/icons/pwa-icon-192.png">
    <?php
}

