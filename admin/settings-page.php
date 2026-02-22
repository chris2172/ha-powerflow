<?php
/**
 * admin/settings-page.php
 *
 * Registers the top-level admin menu and renders the settings page.
 * All JavaScript and CSS are enqueued from separate files (admin.js / admin.css).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', 'ha_pf_admin_menu' );

function ha_pf_admin_menu() {
    add_menu_page(
        __( 'HA PowerFlow Settings', 'ha-powerflow' ),
        __( 'HA PowerFlow', 'ha-powerflow' ),
        'manage_options',
        'ha-powerflow',
        'ha_pf_settings_page',
        'dashicons-chart-area'
    );
}

function ha_pf_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    // Resolve current image for preview
    $default_img  = HA_PF_URL . 'assets/ha-powerflow.png';
    $current_img  = get_option( 'ha_powerflow_image_url', $default_img ) ?: $default_img;

    // Entity groups
    $mandatory_entities = [
        'grid_power'      => 'Grid Power',
        'grid_energy_in'  => 'Grid Energy In',
        'grid_energy_out' => 'Grid Energy Out',
        'load_power'      => 'Load Power',
        'load_energy'     => 'Load Energy',
    ];
    $solar_entities = [
        'pv_power'  => 'PV Power',
        'pv_energy' => 'PV Energy',
    ];
    $battery_entities = [
        'battery_power'      => 'Battery Power',
        'battery_energy_in'  => 'Battery Energy In',
        'battery_energy_out' => 'Battery Energy Out',
        'battery_soc'        => 'Battery SOC',
    ];
    $ev_entities = [
        'ev_power' => 'EV Power',
        'ev_soc'   => 'EV SOC',
    ];

    // Flow path defaults (shown as placeholder text)
    $flow_defaults = [
        'grid'    => [ 'M 787 366 L 805 375 L 633 439', 'M 633 439 L 805 375 L 787 366' ],
        'load'    => [ 'M 590 427 L 673 396 L 612 369', 'M 590 427 L 673 396 L 612 369' ],
        'pv'      => [ 'M 331 417 L 510 486',           'M 510 486 L 331 417'           ],
        'battery' => [ 'M 532 500 L 364 563',           'M 364 563 L 532 500'           ],
        'ev'      => [ 'M 618 497 L 713 532 L 786 499', 'M 786 499 L 713 532 L 618 497' ],
    ];

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'HA PowerFlow Settings', 'ha-powerflow' ); ?></h1>

        <!-- =============================================
             INSTRUCTIONS
             ============================================= -->
        <div class="ha-pf-notice">
            <h2><?php esc_html_e( 'Getting Started', 'ha-powerflow' ); ?></h2>
            <ul>
                <li><?php esc_html_e( 'Enter your Home Assistant URL and Long‑Lived Access Token below.', 'ha-powerflow' ); ?></li>
                <li><?php esc_html_e( 'Enable Solar, Battery, or EV using the toggles, then fill in the entity IDs for each.', 'ha-powerflow' ); ?></li>
                <li><?php esc_html_e( 'Place the shortcode [ha_powerflow] on any page or post.', 'ha-powerflow' ); ?></li>
                <li><?php esc_html_e( 'Entity IDs must match your Home Assistant setup exactly (e.g. sensor.grid_power).', 'ha-powerflow' ); ?></li>
                <li><?php esc_html_e( 'Full documents can be found ' ); ?><a href="https://chriswilmot.co.uk/ha-powerflow-document/" target="_blank" rel="noopener noreferrer">Here</a></li>
            </ul>
            <p><strong><?php esc_html_e( 'Note:', 'ha-powerflow' ); ?></strong>
            <?php esc_html_e( 'All requests to Home Assistant are made from your WordPress server — your token is never exposed to the browser.', 'ha-powerflow' ); ?></p>
        </div>

        <form method="post" action="options.php">
            <?php settings_fields( 'ha_pf_settings_group' ); ?>

            <!-- =============================================
                 FEATURE TOGGLES
                 ============================================= -->
            <div class="ha-pf-card">
                <h2><?php esc_html_e( 'Feature Toggles', 'ha-powerflow' ); ?></h2>

                <?php
                $toggles = [
                    'enable_solar'   => 'Enable Solar',
                    'enable_battery' => 'Enable Battery',
                    'enable_ev'      => 'Enable EV',
                ];
                foreach ( $toggles as $key => $label ) : ?>
                <div class="ha-pf-toggle-row">
                    <strong><?php echo esc_html( $label ); ?></strong>
                    <label class="ha-pf-switch">
                        <input type="hidden"   name="ha_powerflow_<?php echo esc_attr( $key ); ?>" value="0">
                        <input type="checkbox" name="ha_powerflow_<?php echo esc_attr( $key ); ?>"
                               id="ha_pf_<?php echo esc_attr( $key ); ?>"
                               class="ha-pf-feature-toggle"
                               value="1"
                               data-panel="ha-pf-panel-<?php echo esc_attr( str_replace( 'enable_', '', $key ) ); ?>"
                               <?php checked( get_option( 'ha_powerflow_' . $key ), '1' ); ?>>
                        <span class="ha-pf-slider"></span>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- =============================================
                 MAIN SETTINGS GRID
                 ============================================= -->
            <div class="ha-pf-grid">

                <!-- LEFT: Connection + Mandatory -->
                <div class="ha-pf-col">

                    <div class="ha-pf-panel open" id="ha-pf-panel-connection">
                        <div class="ha-pf-panel-header">
                            <?php esc_html_e( 'Connection', 'ha-powerflow' ); ?>
                            <span class="ha-pf-arrow">&#9660;</span>
                        </div>
                        <div class="ha-pf-panel-body">

                            <!-- Background image -->
                            <h3><?php esc_html_e( 'Background Image', 'ha-powerflow' ); ?></h3>
                            <div class="ha-pf-image-row">
                                <input type="text"
                                    id="ha_pf_image_url_field"
                                    name="ha_powerflow_image_url"
                                    value="<?php echo esc_attr( get_option( 'ha_powerflow_image_url' ) ); ?>"
                                    class="regular-text">
                                <button type="button" class="button" id="ha-pf-upload-btn">
                                    <?php esc_html_e( 'Select Image', 'ha-powerflow' ); ?>
                                </button>
                            </div>
                            <?php if ( $current_img ) : ?>
                            <img id="ha-pf-image-preview"
                                 src="<?php echo esc_url( $current_img ); ?>"
                                 alt="<?php esc_attr_e( 'Current background image', 'ha-powerflow' ); ?>"
                                 style="max-width:200px; margin-top:8px; border:1px solid #ccd0d4; display:block;">
                            <?php else : ?>
                            <img id="ha-pf-image-preview" src="" alt="" style="max-width:200px; margin-top:8px; display:none;">
                            <?php endif; ?>

                            <!-- HA connection details -->
                            <h3><?php esc_html_e( 'Home Assistant', 'ha-powerflow' ); ?></h3>
                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row">
                                        <label for="ha_pf_ha_url"><?php esc_html_e( 'HA URL', 'ha-powerflow' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="url"
                                            id="ha_pf_ha_url"
                                            name="ha_powerflow_ha_url"
                                            value="<?php echo esc_attr( get_option( 'ha_powerflow_ha_url' ) ); ?>"
                                            placeholder="https://homeassistant.local:8123"
                                            class="regular-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="ha_pf_ha_token"><?php esc_html_e( 'HA Token', 'ha-powerflow' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="password"
                                            id="ha_pf_ha_token"
                                            name="ha_powerflow_ha_token"
                                            value="<?php echo esc_attr( get_option( 'ha_powerflow_ha_token' ) ); ?>"
                                            class="regular-text"
                                            autocomplete="new-password">
                                        <p class="description">
                                            <?php esc_html_e( 'Stored server-side only. Never sent to the browser.', 'ha-powerflow' ); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'SSL Verify', 'ha-powerflow' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="hidden" name="ha_powerflow_ssl_verify" value="0">
                                            <input type="checkbox"
                                                name="ha_powerflow_ssl_verify"
                                                value="1"
                                                <?php checked( get_option( 'ha_powerflow_ssl_verify', '1' ), '1' ); ?>>
                                            <?php esc_html_e( 'Verify SSL certificate (uncheck only for self-signed / local installs)', 'ha-powerflow' ); ?>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Mandatory entities -->
                    <div class="ha-pf-panel open" id="ha-pf-panel-mandatory">
                        <div class="ha-pf-panel-header">
                            <?php esc_html_e( 'Grid &amp; Load Entities', 'ha-powerflow' ); ?>
                            <span class="ha-pf-arrow">&#9660;</span>
                        </div>
                        <div class="ha-pf-panel-body">
                            <?php ha_pf_entity_table( $mandatory_entities ); ?>
                        </div>
                    </div>

                </div><!-- /left col -->

                <!-- RIGHT: Optional feature panels -->
                <div class="ha-pf-col">

                    <div class="ha-pf-panel <?php echo get_option( 'ha_powerflow_enable_solar' ) === '1' ? 'open' : ''; ?>"
                         id="ha-pf-panel-solar">
                        <div class="ha-pf-panel-header">
                            <?php esc_html_e( 'Solar Entities', 'ha-powerflow' ); ?>
                            <span class="ha-pf-arrow">&#9660;</span>
                        </div>
                        <div class="ha-pf-panel-body">
                            <?php ha_pf_entity_table( $solar_entities ); ?>
                        </div>
                    </div>

                    <div class="ha-pf-panel <?php echo get_option( 'ha_powerflow_enable_battery' ) === '1' ? 'open' : ''; ?>"
                         id="ha-pf-panel-battery">
                        <div class="ha-pf-panel-header">
                            <?php esc_html_e( 'Battery Entities', 'ha-powerflow' ); ?>
                            <span class="ha-pf-arrow">&#9660;</span>
                        </div>
                        <div class="ha-pf-panel-body">
                            <?php ha_pf_entity_table( $battery_entities ); ?>
                        </div>
                    </div>

                    <div class="ha-pf-panel <?php echo get_option( 'ha_powerflow_enable_ev' ) === '1' ? 'open' : ''; ?>"
                         id="ha-pf-panel-ev">
                        <div class="ha-pf-panel-header">
                            <?php esc_html_e( 'EV Entities', 'ha-powerflow' ); ?>
                            <span class="ha-pf-arrow">&#9660;</span>
                        </div>
                        <div class="ha-pf-panel-body">
                            <?php ha_pf_entity_table( $ev_entities ); ?>
                        </div>
                    </div>

                </div><!-- /right col -->

            </div><!-- /grid -->

            <!-- =============================================
                 FLOW PATH SETTINGS
                 ============================================= -->
            <div class="ha-pf-panel open" id="ha-pf-panel-flows">
                <div class="ha-pf-panel-header">
                    <?php esc_html_e( 'Flow Path Settings', 'ha-powerflow' ); ?>
                    <span class="ha-pf-arrow">&#9660;</span>
                </div>
                <div class="ha-pf-panel-body">
                    <p class="description">
                        <?php esc_html_e( 'Override the SVG motion paths. Leave blank to use defaults. Must start with M and contain only SVG path commands and numbers.', 'ha-powerflow' ); ?>
                    </p>
                    <table class="form-table" role="presentation">
                    <?php foreach ( $flow_defaults as $flow => [ $def_fwd, $def_rev ] ) : ?>
                        <tr>
                            <th scope="row"><?php echo esc_html( ucfirst( $flow ) ); ?></th>
                            <td>
                                <label>
                                    <span class="ha-pf-path-label"><?php esc_html_e( 'Forward:', 'ha-powerflow' ); ?></span>
                                    <input type="text"
                                        name="ha_powerflow_<?php echo esc_attr( $flow ); ?>_flow_forward"
                                        value="<?php echo esc_attr( get_option( 'ha_powerflow_' . $flow . '_flow_forward' ) ); ?>"
                                        placeholder="<?php echo esc_attr( $def_fwd ); ?>"
                                        class="ha-pf-path-input">
                                </label>
                                <label>
                                    <span class="ha-pf-path-label"><?php esc_html_e( 'Reverse:', 'ha-powerflow' ); ?></span>
                                    <input type="text"
                                        name="ha_powerflow_<?php echo esc_attr( $flow ); ?>_flow_reverse"
                                        value="<?php echo esc_attr( get_option( 'ha_powerflow_' . $flow . '_flow_reverse' ) ); ?>"
                                        placeholder="<?php echo esc_attr( $def_rev ); ?>"
                                        class="ha-pf-path-input">
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </table>
                </div>
            </div>

            <!-- =============================================
                 COLOUR SETTINGS
                 ============================================= -->
            <div class="ha-pf-panel open" id="ha-pf-panel-colours">
                <div class="ha-pf-panel-header">
                    <?php esc_html_e( 'Colour Settings', 'ha-powerflow' ); ?>
                    <span class="ha-pf-arrow">&#9660;</span>
                </div>
                <div class="ha-pf-panel-body">
                    <table class="form-table" role="presentation">
                        <?php
                        $colours = [
                            'text_colour' => 'Text Colour',
                            'line_colour' => 'Line Colour',
                            'dot_colour'  => 'Dot Colour',
                        ];
                        foreach ( $colours as $key => $label ) : ?>
                        <tr>
                            <th scope="row">
                                <label for="ha_pf_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
                            </th>
                            <td class="ha-pf-colour-row">
                                <input type="color"
                                    id="ha_pf_<?php echo esc_attr( $key ); ?>"
                                    name="ha_powerflow_<?php echo esc_attr( $key ); ?>"
                                    value="<?php echo esc_attr( get_option( 'ha_powerflow_' . $key, '#5EC766' ) ); ?>">
                                <button type="button"
                                        class="button ha-pf-colour-reset"
                                        data-target="ha_pf_<?php echo esc_attr( $key ); ?>"
                                        data-default="#5EC766">
                                    <?php esc_html_e( 'Reset', 'ha-powerflow' ); ?>
                                </button>
                                <span class="description"><?php esc_html_e( 'Default: #5EC766', 'ha-powerflow' ); ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>

            <!-- =============================================
                 DEVELOPER TOOLS
                 ============================================= -->
            <div class="ha-pf-card">
                <h2><?php esc_html_e( 'Developer Tools', 'ha-powerflow' ); ?></h2>
                <label class="ha-pf-checkbox-label">
                    <input type="hidden" name="ha_powerflow_debug_click" value="0">
                    <input type="checkbox"
                           name="ha_powerflow_debug_click"
                           value="1"
                           <?php checked( get_option( 'ha_powerflow_debug_click' ), '1' ); ?>>
                    <span>
                        <?php esc_html_e( 'Enable click-to-coordinate tool', 'ha-powerflow' ); ?>
                        <span class="description">
                            &mdash; <?php esc_html_e( 'When enabled, clicking anywhere on the dashboard outputs the SVG x/y coordinates to the browser console and places a temporary marker dot. Use this to find the correct values for label positions. Disable when not needed so visitors are not affected.', 'ha-powerflow' ); ?>
                        </span>
                    </span>
                </label>
            </div>

            <!-- =============================================
                 UNINSTALL PREFERENCE
                 (inside the form so it gets saved with the rest)
                 ============================================= -->
            <div class="ha-pf-card">
                <h2><?php esc_html_e( 'Uninstall', 'ha-powerflow' ); ?></h2>
                <label class="ha-pf-checkbox-label">
                    <input type="hidden" name="ha_powerflow_delete_uploads" value="0">
                    <input type="checkbox"
                           name="ha_powerflow_delete_uploads"
                           value="1"
                           <?php checked( get_option( 'ha_powerflow_delete_uploads' ), '1' ); ?>>
                    <span><?php esc_html_e( 'Delete uploaded background images when the plugin is uninstalled', 'ha-powerflow' ); ?></span>
                </label>
            </div>

            <?php submit_button(); ?>

        </form>
    </div><!-- /wrap -->
    <?php
}

/**
 * Render a form-table of entity rows.
 * Each row has: entity ID text input, rotation number, X number, Y number.
 *
 * @param array $entities  key => label pairs
 */
function ha_pf_entity_table( array $entities ) {
    ?>
    <table class="form-table" role="presentation">
    <?php foreach ( $entities as $key => $label ) : ?>
        <tr>
            <th scope="row"><?php echo esc_html( $label ); ?></th>
            <td>
                <input type="text"
                    name="ha_powerflow_<?php echo esc_attr( $key ); ?>"
                    value="<?php echo esc_attr( get_option( 'ha_powerflow_' . $key ) ); ?>"
                    placeholder="sensor.<?php echo esc_attr( $key ); ?>"
                    style="width:220px;">

                <label style="margin-left:12px;">
                    <?php esc_html_e( 'Rot', 'ha-powerflow' ); ?>
                    <input type="number"
                        name="ha_powerflow_<?php echo esc_attr( $key ); ?>_rot"
                        value="<?php echo esc_attr( get_option( 'ha_powerflow_' . $key . '_rot' ) ); ?>"
                        style="width:70px;">
                </label>

                <label style="margin-left:8px;">
                    X
                    <input type="number" min="0"
                        name="ha_powerflow_<?php echo esc_attr( $key ); ?>_x_pos"
                        value="<?php echo esc_attr( get_option( 'ha_powerflow_' . $key . '_x_pos' ) ); ?>"
                        style="width:70px;">
                </label>

                <label style="margin-left:8px;">
                    Y
                    <input type="number" min="0"
                        name="ha_powerflow_<?php echo esc_attr( $key ); ?>_y_pos"
                        value="<?php echo esc_attr( get_option( 'ha_powerflow_' . $key . '_y_pos' ) ); ?>"
                        style="width:70px;">
                </label>
            </td>
        </tr>
    <?php endforeach; ?>
    </table>
    <?php
}
