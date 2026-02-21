<?php
/**
 * Plugin Name: HA PowerFlow
 * Plugin URI: https://chriswilmot.co.uk/ha-powerflow/
 * Description: A real-time Home Assistant power-flow dashboard with animated SVG and CSS motion paths.
 * Version: 1.0.0
 * Author: Christopher Wilmot
 * Author URI: https://chriswilmot.co.uk/
 * License: GPLv2 or later
 * Text Domain: ha-powerflow
 */

if (!defined('ABSPATH')) exit;

/* -------------------------------------------------------
   CREATE UPLOAD DIRECTORY ON ACTIVATION
------------------------------------------------------- */
register_activation_hook(__FILE__, 'ha_powerflow_create_upload_dir');

function ha_powerflow_create_upload_dir() {
    $upload_dir = wp_upload_dir();
    $path = $upload_dir['basedir'] . '/ha-powerflow';

    if (!file_exists($path)) {
        wp_mkdir_p($path);
    }
}

/* -------------------------------------------------------
   LOAD PLUGIN FILES
------------------------------------------------------- */

// Load settings registration
require_once plugin_dir_path(__FILE__) . 'includes/settings-register.php';

// Load admin settings page
require_once plugin_dir_path(__FILE__) . 'admin/settings-page.php';

// Load shortcode
require_once plugin_dir_path(__FILE__) . 'includes/shortcode.php';

/* -------------------------------------------------------
   ADD SETTINGS LINK
------------------------------------------------------- */
function ha_powerflow_add_settings_link($links) {
    $settings_link = '<a href="admin.php?page=ha-powerflow">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ha_powerflow_add_settings_link');

add_action('admin_enqueue_scripts', 'ha_powerflow_admin_scripts');
function ha_powerflow_admin_scripts() {
    wp_enqueue_script('jquery');
}
