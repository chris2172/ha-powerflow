<?php
/**
 * Plugin Name: HA PowerFlow
 * Plugin URI: https://chriswilmot.co.uk/ha-powerflow/
 * Description: A real-time Home Assistant power-flow dashboard with animated SVG and CSS motion paths.
 * Version: 1.2.0
 * Author: Christopher Wilmot
 * Author URI: https://chriswilmot.co.uk/
 * License: GPLv2 or later
 * Text Domain: ha-powerflow
 */

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'includes/ajax-copy-image.php';


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
add_action('admin_enqueue_scripts', 'ha_powerflow_admin_scripts');
function ha_powerflow_admin_scripts($hook) {

    // Correct hook for a top-level menu page
    if ($hook !== 'toplevel_page_ha-powerflow') {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_script('jquery');
}




