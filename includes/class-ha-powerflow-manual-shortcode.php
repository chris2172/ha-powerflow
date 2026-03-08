<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class HA_Powerflow_Manual_Shortcode {
    public static function init() {
        add_shortcode( 'ha_powerflow_manual', [ __CLASS__, 'render' ] );
    }

    public static function render( $atts ) {
        ob_start();
        include HA_POWERFLOW_DIR . 'includes/manual-content.php';
        return ob_get_clean();
    }
}
