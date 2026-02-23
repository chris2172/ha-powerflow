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

    $default_img = HA_PF_URL . 'assets/ha-powerflow.png';
    $current_img = get_option( 'ha_powerflow_image_url', $default_img ) ?: $default_img;

    $mandatory_entities = [
        'grid_power'     => 'Grid Power',
        'grid_energy_in' => 'Grid Energy In',
        'load_power'     => 'Load Power',
        'load_energy'    => 'Load Energy',
    ];

    // Grid export only applies when solar or battery is enabled
    $show_grid_export = ( get_option( 'ha_powerflow_enable_solar' ) === '1' )
                     || ( get_option( 'ha_powerflow_enable_battery' ) === '1' );
    $solar_entities = [
        'pv_power'  => 'PV Power',
        'pv_energy' => 'PV Energy',
    ];
    $battery_entities = [
        'battery_power'      => 'Battery Power',
        'battery_energy_in'  => 'Battery In',
        'battery_energy_out' => 'Battery Out',
        'battery_soc'        => 'Battery SOC',
    ];
    $ev_entities = [
        'ev_power' => 'EV Power',
        'ev_soc'   => 'EV SOC',
    ];

    $flow_defaults = [
        'grid'    => [ 'M 787 366 L 805 375 L 633 439', 'M 633 439 L 805 375 L 787 366' ],
        'load'    => [ 'M 590 427 L 673 396 L 612 369', 'M 590 427 L 673 396 L 612 369' ],
        'pv'      => [ 'M 331 417 L 510 486',           'M 510 486 L 331 417'           ],
        'battery' => [ 'M 532 500 L 364 563',           'M 364 563 L 532 500'           ],
        'ev'      => [ 'M 618 497 L 713 532 L 786 499', 'M 786 499 L 713 532 L 618 497' ],
    ];

    $colours = [
        'text_colour' => 'Text',
        'line_colour' => 'Lines',
        'dot_colour'  => 'Dots',
    ];

    $ha_url_set = ! empty( get_option( 'ha_powerflow_ha_url' ) );

    ?>
    <div class="wrap">

        <!-- ══════════════════════════════════════════════
             DARK HEADER BAR
             ══════════════════════════════════════════════ -->
        <div class="ha-pf-page-header">
            <div class="ha-pf-logo-mark">
                <span class="dashicons dashicons-chart-area"></span>
            </div>
            <div class="ha-pf-page-header-text">
                <h1>HA PowerFlow</h1>
                <span class="ha-pf-version-badge">v<?php echo esc_html( HA_PF_VERSION ); ?></span>
            </div>
            <div class="ha-pf-status-chip">
                <span class="ha-pf-status-dot <?php echo $ha_url_set ? 'configured' : ''; ?>"></span>
                <?php echo $ha_url_set
                    ? esc_html__( 'Home Assistant configured', 'ha-powerflow' )
                    : esc_html__( 'Not yet configured', 'ha-powerflow' ); ?>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════
             GETTING STARTED NOTICE
             ══════════════════════════════════════════════ -->
        <div class="ha-pf-notice">
            <h2><?php esc_html_e( 'Getting Started', 'ha-powerflow' ); ?></h2>
            <ul>
                <li><?php esc_html_e( 'Enter your Home Assistant URL and Long-Lived Access Token in the Connection panel.', 'ha-powerflow' ); ?></li>
                <li><?php esc_html_e( 'Enable Solar, Battery, or EV using the toggles below, then fill in the entity IDs.', 'ha-powerflow' ); ?></li>
                <li><?php esc_html_e( 'Add the shortcode to any page or post:', 'ha-powerflow' ); ?>
                    <code>[ha_powerflow]</code></li>
                <li><?php esc_html_e( 'Entity IDs must match your Home Assistant setup exactly (e.g. sensor.grid_power).', 'ha-powerflow' ); ?></li>
            </ul>
            <p><strong><?php esc_html_e( 'Security:', 'ha-powerflow' ); ?></strong>
            <?php esc_html_e( 'All requests to Home Assistant are proxied through your WordPress server — your token is never sent to the browser.', 'ha-powerflow' ); ?></p>
        </div>

        <form method="post" action="options.php" id="ha-pf-form">
            <?php settings_fields( 'ha_pf_settings_group' ); ?>

            <!-- ══════════════════════════════════════════
                 FEATURE TOGGLES
                 ══════════════════════════════════════════ -->
            <p class="ha-pf-section-label"><?php esc_html_e( 'Features', 'ha-powerflow' ); ?></p>

            <div class="ha-pf-toggles-grid">

                <?php
                $toggles = [
                    'enable_solar'   => [ 'Solar',   'dashicons-superhero',   'PV generation & energy' ],
                    'enable_battery' => [ 'Battery', 'dashicons-battery-full', 'Storage & state of charge' ],
                    'enable_ev'      => [ 'EV',      'dashicons-car',          'Electric vehicle charging' ],
                ];
                foreach ( $toggles as $key => [ $label, $icon, $sub ] ) :
                    $enabled = get_option( 'ha_powerflow_' . $key ) === '1';
                    ?>
                <div class="ha-pf-toggle-card <?php echo $enabled ? 'is-enabled' : ''; ?>"
                     id="ha-pf-togglecard-<?php echo esc_attr( $key ); ?>">
                    <div class="ha-pf-toggle-card-left">
                        <div class="ha-pf-toggle-icon">
                            <span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
                        </div>
                        <div>
                            <div class="ha-pf-toggle-label"><?php echo esc_html( $label ); ?></div>
                            <div class="ha-pf-toggle-sublabel"><?php echo esc_html( $sub ); ?></div>
                        </div>
                    </div>
                    <label class="ha-pf-switch" aria-label="<?php echo esc_attr( "Enable $label" ); ?>">
                        <input type="hidden"   name="ha_powerflow_<?php echo esc_attr( $key ); ?>" value="0">
                        <input type="checkbox" name="ha_powerflow_<?php echo esc_attr( $key ); ?>"
                               id="ha_pf_<?php echo esc_attr( $key ); ?>"
                               class="ha-pf-feature-toggle"
                               value="1"
                               data-panel="ha-pf-panel-<?php echo esc_attr( str_replace( 'enable_', '', $key ) ); ?>"
                               data-card="ha-pf-togglecard-<?php echo esc_attr( $key ); ?>"
                               <?php checked( $enabled ); ?>>
                        <span class="ha-pf-slider"></span>
                    </label>
                </div>
                <?php endforeach; ?>

            </div>

            <!-- ══════════════════════════════════════════
                 MAIN SETTINGS GRID
                 ══════════════════════════════════════════ -->
            <p class="ha-pf-section-label"><?php esc_html_e( 'Configuration', 'ha-powerflow' ); ?></p>

            <div class="ha-pf-grid">

                <!-- LEFT COLUMN -->
                <div class="ha-pf-col">

                    <!-- Connection -->
                    <div class="ha-pf-panel open" id="ha-pf-panel-connection">
                        <div class="ha-pf-panel-header">
                            <div class="ha-pf-panel-header-left">
                                <div class="ha-pf-panel-header-icon">
                                    <span class="dashicons dashicons-admin-network"></span>
                                </div>
                                <?php esc_html_e( 'Connection', 'ha-powerflow' ); ?>
                            </div>
                            <span class="ha-pf-arrow">&#9660;</span>
                        </div>
                        <div class="ha-pf-panel-body">

                            <h3><?php esc_html_e( 'Background Image', 'ha-powerflow' ); ?></h3>
                            <div class="ha-pf-image-row">
                                <input type="text"
                                    id="ha_pf_image_url_field"
                                    name="ha_powerflow_image_url"
                                    value="<?php echo esc_attr( get_option( 'ha_powerflow_image_url' ) ); ?>"
                                    placeholder="https://…"
                                    class="regular-text">
                                <button type="button" class="button" id="ha-pf-upload-btn">
                                    <?php esc_html_e( 'Select Image', 'ha-powerflow' ); ?>
                                </button>
                            </div>
                            <div class="ha-pf-image-preview-wrap" <?php echo $current_img ? '' : 'style="display:none"'; ?>>
                                <img id="ha-pf-image-preview"
                                     src="<?php echo esc_url( $current_img ); ?>"
                                     alt="<?php esc_attr_e( 'Background image preview', 'ha-powerflow' ); ?>">
                            </div>

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
                                            <?php esc_html_e( 'Long-Lived Access Token. Stored server-side only, never sent to the browser.', 'ha-powerflow' ); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'SSL Verify', 'ha-powerflow' ); ?></th>
                                    <td>
                                        <label class="ha-pf-checkbox-label">
                                            <input type="hidden" name="ha_powerflow_ssl_verify" value="0">
                                            <input type="checkbox"
                                                name="ha_powerflow_ssl_verify"
                                                value="1"
                                                <?php checked( get_option( 'ha_powerflow_ssl_verify', '1' ), '1' ); ?>>
                                            <span>
                                                <span class="ha-pf-check-text"><?php esc_html_e( 'Verify SSL certificate', 'ha-powerflow' ); ?></span>
                                                <span class="description"><?php esc_html_e( 'Uncheck only for self-signed / local installs', 'ha-powerflow' ); ?></span>
                                            </span>
                                        </label>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row"><?php esc_html_e( 'Refresh Interval', 'ha-powerflow' ); ?></th>
                                    <td>
                                        <select name="ha_powerflow_refresh_interval" id="ha_pf_refresh_interval">
                                            <?php
                                            $saved_interval = (int) get_option( 'ha_powerflow_refresh_interval', 5 );
                                            foreach ( [ 5, 10, 15, 30, 60, 120, 300 ] as $secs ) :
                                                $label = $secs >= 60
                                                    ? ( $secs / 60 ) . ' ' . _n( 'minute', 'minutes', $secs / 60, 'ha-powerflow' )
                                                    : $secs . ' ' . _n( 'second', 'seconds', $secs, 'ha-powerflow' );
                                            ?>
                                            <option value="<?php echo esc_attr( $secs ); ?>"
                                                <?php selected( $saved_interval, $secs ); ?>>
                                                <?php echo esc_html( $label ); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="description"><?php esc_html_e( 'How often the dashboard fetches new data from Home Assistant.', 'ha-powerflow' ); ?></p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row"><?php esc_html_e( 'Test Connection', 'ha-powerflow' ); ?></th>
                                    <td>
                                        <button type="button" id="ha-pf-test-btn" class="button ha-pf-test-btn">
                                            <span class="dashicons dashicons-update ha-pf-test-spinner" style="display:none;"></span>
                                            <span class="dashicons dashicons-wifi ha-pf-test-icon"></span>
                                            <?php esc_html_e( 'Test Connection', 'ha-powerflow' ); ?>
                                        </button>
                                        <span id="ha-pf-test-result" class="ha-pf-test-result" aria-live="polite"></span>
                                        <p class="description"><?php esc_html_e( 'Tests the saved URL and token. Save Settings first if you have made changes.', 'ha-powerflow' ); ?></p>
                                    </td>
                                </tr>

                            </table>
                        </div>
                    </div>

                    <!-- Grid & Load Entities -->
                    <div class="ha-pf-panel open" id="ha-pf-panel-mandatory">
                        <div class="ha-pf-panel-header">
                            <div class="ha-pf-panel-header-left">
                                <div class="ha-pf-panel-header-icon">
                                    <span class="dashicons dashicons-chart-bar"></span>
                                </div>
                                <?php esc_html_e( 'Grid &amp; Load Entities', 'ha-powerflow' ); ?>
                            </div>
                            <span class="ha-pf-arrow">&#9660;</span>
                        </div>
                        <div class="ha-pf-panel-body">
                            <?php ha_pf_entity_table( $mandatory_entities ); ?>

                            <div id="ha-pf-grid-export-row"
                                 <?php if ( ! $show_grid_export ) echo 'style="display:none;"'; ?>>
                                <?php ha_pf_entity_table( [ 'grid_energy_out' => 'Grid Energy Out' ] ); ?>
                            </div>
                        </div>
                    </div>

                </div><!-- /left col -->

                <!-- RIGHT COLUMN -->
                <div class="ha-pf-col">

                    <div class="ha-pf-panel <?php echo get_option( 'ha_powerflow_enable_solar' ) === '1' ? 'open' : ''; ?>"
                         id="ha-pf-panel-solar">
                        <div class="ha-pf-panel-header">
                            <div class="ha-pf-panel-header-left">
                                <div class="ha-pf-panel-header-icon">
                                    <span class="dashicons dashicons-superhero"></span>
                                </div>
                                <?php esc_html_e( 'Solar Entities', 'ha-powerflow' ); ?>
                            </div>
                            <span class="ha-pf-arrow">&#9660;</span>
                        </div>
                        <div class="ha-pf-panel-body">
                            <?php ha_pf_entity_table( $solar_entities ); ?>
                        </div>
                    </div>

                    <div class="ha-pf-panel <?php echo get_option( 'ha_powerflow_enable_battery' ) === '1' ? 'open' : ''; ?>"
                         id="ha-pf-panel-battery">
                        <div class="ha-pf-panel-header">
                            <div class="ha-pf-panel-header-left">
                                <div class="ha-pf-panel-header-icon">
                                    <span class="dashicons dashicons-battery-full"></span>
                                </div>
                                <?php esc_html_e( 'Battery Entities', 'ha-powerflow' ); ?>
                            </div>
                            <span class="ha-pf-arrow">&#9660;</span>
                        </div>
                        <div class="ha-pf-panel-body">
                            <?php ha_pf_entity_table( $battery_entities ); ?>

                            <h3><?php esc_html_e( 'Battery Gauge Widget', 'ha-powerflow' ); ?></h3>
                            <p class="description" style="margin-bottom:12px;">
                                <?php esc_html_e( 'Displays a two-ring SOC/power gauge on the dashboard. Outer ring = state of charge. Inner circle = green when charging, red when discharging.', 'ha-powerflow' ); ?>
                            </p>

                            <div style="margin-bottom:12px;">
                                <label class="ha-pf-checkbox-label">
                                    <input type="hidden" name="ha_powerflow_battery_gauge_enable" value="0">
                                    <input type="checkbox"
                                           name="ha_powerflow_battery_gauge_enable"
                                           value="1"
                                           <?php checked( get_option( 'ha_powerflow_battery_gauge_enable' ), '1' ); ?>>
                                    <span>
                                        <span class="ha-pf-check-text"><?php esc_html_e( 'Show battery gauge', 'ha-powerflow' ); ?></span>
                                        <span class="description"><?php esc_html_e( 'Only visible when Battery is enabled above.', 'ha-powerflow' ); ?></span>
                                    </span>
                                </label>
                            </div>

                            <div class="ha-pf-entity-header" style="grid-template-columns:1fr 90px 90px;">
                                <span><?php esc_html_e( 'Gauge Position', 'ha-powerflow' ); ?></span>
                                <span>X</span>
                                <span>Y</span>
                            </div>
                            <div class="ha-pf-entity-row" style="grid-template-columns:1fr 90px 90px;">
                                <div class="ha-pf-entity-label"><?php esc_html_e( 'Centre point (SVG units)', 'ha-powerflow' ); ?></div>
                                <div class="ha-pf-coord-group">
                                    <span class="ha-pf-coord-label">X</span>
                                    <input type="number" min="0" max="1000"
                                        name="ha_powerflow_battery_gauge_x"
                                        value="<?php echo esc_attr( get_option( 'ha_powerflow_battery_gauge_x', '95' ) ); ?>"
                                        placeholder="95">
                                </div>
                                <div class="ha-pf-coord-group">
                                    <span class="ha-pf-coord-label">Y</span>
                                    <input type="number" min="0" max="750"
                                        name="ha_powerflow_battery_gauge_y"
                                        value="<?php echo esc_attr( get_option( 'ha_powerflow_battery_gauge_y', '605' ) ); ?>"
                                        placeholder="605">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ha-pf-panel <?php echo get_option( 'ha_powerflow_enable_ev' ) === '1' ? 'open' : ''; ?>"
                         id="ha-pf-panel-ev">
                        <div class="ha-pf-panel-header">
                            <div class="ha-pf-panel-header-left">
                                <div class="ha-pf-panel-header-icon">
                                    <span class="dashicons dashicons-car"></span>
                                </div>
                                <?php esc_html_e( 'EV Entities', 'ha-powerflow' ); ?>
                            </div>
                            <span class="ha-pf-arrow">&#9660;</span>
                        </div>
                        <div class="ha-pf-panel-body">
                            <?php ha_pf_entity_table( $ev_entities ); ?>

                            <h3><?php esc_html_e( 'EV Gauge Widget', 'ha-powerflow' ); ?></h3>
                            <p class="description" style="margin-bottom:12px;">
                                <?php esc_html_e( 'Displays a two-ring SOC/power gauge on the dashboard. Outer ring = state of charge. Inner circle = green when charging, red when discharging.', 'ha-powerflow' ); ?>
                            </p>

                            <div style="margin-bottom:12px;">
                                <label class="ha-pf-checkbox-label">
                                    <input type="hidden" name="ha_powerflow_ev_gauge_enable" value="0">
                                    <input type="checkbox"
                                           name="ha_powerflow_ev_gauge_enable"
                                           value="1"
                                           <?php checked( get_option( 'ha_powerflow_ev_gauge_enable' ), '1' ); ?>>
                                    <span>
                                        <span class="ha-pf-check-text"><?php esc_html_e( 'Show EV gauge', 'ha-powerflow' ); ?></span>
                                        <span class="description"><?php esc_html_e( 'Only visible when EV is enabled above.', 'ha-powerflow' ); ?></span>
                                    </span>
                                </label>
                            </div>

                            <div class="ha-pf-entity-header" style="grid-template-columns:1fr 90px 90px;">
                                <span><?php esc_html_e( 'Gauge Position', 'ha-powerflow' ); ?></span>
                                <span>X</span>
                                <span>Y</span>
                            </div>
                            <div class="ha-pf-entity-row" style="grid-template-columns:1fr 90px 90px;">
                                <div class="ha-pf-entity-label"><?php esc_html_e( 'Centre point (SVG units)', 'ha-powerflow' ); ?></div>
                                <div class="ha-pf-coord-group">
                                    <span class="ha-pf-coord-label">X</span>
                                    <input type="number" min="0" max="1000"
                                        name="ha_powerflow_ev_gauge_x"
                                        value="<?php echo esc_attr( get_option( 'ha_powerflow_ev_gauge_x', '500' ) ); ?>"
                                        placeholder="500">
                                </div>
                                <div class="ha-pf-coord-group">
                                    <span class="ha-pf-coord-label">Y</span>
                                    <input type="number" min="0" max="750"
                                        name="ha_powerflow_ev_gauge_y"
                                        value="<?php echo esc_attr( get_option( 'ha_powerflow_ev_gauge_y', '375' ) ); ?>"
                                        placeholder="375">
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /right col -->

            </div><!-- /grid -->

            <!-- ══════════════════════════════════════════
                 CUSTOM ENTITIES
                 ══════════════════════════════════════════ -->
            <p class="ha-pf-section-label"><?php esc_html_e( 'Custom Entities', 'ha-powerflow' ); ?></p>

            <div class="ha-pf-panel open" id="ha-pf-panel-custom-entities">
                <div class="ha-pf-panel-header">
                    <div class="ha-pf-panel-header-left">
                        <div class="ha-pf-panel-header-icon">
                            <span class="dashicons dashicons-plus-alt"></span>
                        </div>
                        <?php esc_html_e( 'Custom Entity Labels', 'ha-powerflow' ); ?>
                    </div>
                    <span class="ha-pf-arrow">&#9660;</span>
                </div>
                <div class="ha-pf-panel-body">

                    <p class="description" style="margin-bottom:16px;">
                        <?php esc_html_e( 'Add any Home Assistant entity to the dashboard with a custom display name and position. Each entry is fetched and displayed as a text label on the SVG.', 'ha-powerflow' ); ?>
                    </p>

                    <!-- Hidden field — JS serialises the list into this before submit -->
                    <input type="hidden"
                           name="ha_powerflow_custom_entities"
                           id="ha-pf-custom-entities-json"
                           value="<?php echo esc_attr( get_option( 'ha_powerflow_custom_entities', '[]' ) ); ?>">

                    <!-- Column headers -->
                    <div class="ha-pf-custom-entity-header">
                        <span><?php esc_html_e( 'Display Name', 'ha-powerflow' ); ?></span>
                        <span><?php esc_html_e( 'Entity ID', 'ha-powerflow' ); ?></span>
                        <span><?php esc_html_e( 'Unit', 'ha-powerflow' ); ?></span>
                        <span><?php esc_html_e( 'Size', 'ha-powerflow' ); ?></span>
                        <span><?php esc_html_e( 'Rot', 'ha-powerflow' ); ?></span>
                        <span>X</span>
                        <span>Y</span>
                        <span><?php esc_html_e( 'Visible', 'ha-powerflow' ); ?></span>
                        <span></span><!-- delete button column -->
                    </div>

                    <!-- Existing rows rendered from saved data -->
                    <div id="ha-pf-custom-entities-list">
                        <?php
                        $saved_custom = json_decode( get_option( 'ha_powerflow_custom_entities', '[]' ) ?: '[]', true );
                        if ( is_array( $saved_custom ) ) {
                            foreach ( $saved_custom as $item ) :
                                $cid     = esc_attr( $item['id']        ?? '' );
                                $clabel  = esc_attr( $item['label']     ?? '' );
                                $centity = esc_attr( $item['entity_id'] ?? '' );
                                $cunit   = esc_attr( $item['unit']       ?? '' );
                                $csize   = esc_attr( $item['size']      ?? 14 );
                                $crot    = esc_attr( $item['rot']       ?? 0 );
                                $cx      = esc_attr( $item['x']         ?? 0 );
                                $cy      = esc_attr( $item['y']         ?? 0 );
                                $cvis    = ! empty( $item['visible'] );
                        ?>
                        <div class="ha-pf-custom-entity-row" data-id="<?php echo $cid; ?>">
                            <input type="text"   class="ce-label"     placeholder="<?php esc_attr_e( 'Solar kWh', 'ha-powerflow' ); ?>" value="<?php echo $clabel; ?>">
                            <input type="text"   class="ce-entity-id" placeholder="sensor.solar_energy" value="<?php echo $centity; ?>">
                            <input type="text"   class="ce-unit"      placeholder="kWh" value="<?php echo $cunit; ?>" style="width:60px;">
                            <input type="number" class="ce-size"      placeholder="14"  value="<?php echo $csize; ?>" style="width:52px;" min="6" max="72">
                            <input type="number" class="ce-rot"       placeholder="0"   value="<?php echo $crot; ?>" style="width:56px;">
                            <input type="number" class="ce-x"         placeholder="0"   value="<?php echo $cx; ?>"   style="width:70px;" min="0">
                            <input type="number" class="ce-y"         placeholder="0"   value="<?php echo $cy; ?>"   style="width:70px;" min="0">
                            <label class="ha-pf-ce-visible">
                                <input type="checkbox" class="ce-visible" <?php checked( $cvis ); ?>>
                            </label>
                            <button type="button" class="ha-pf-ce-delete button" aria-label="<?php esc_attr_e( 'Remove row', 'ha-powerflow' ); ?>">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                        <?php endforeach; } ?>
                    </div>

                    <button type="button" id="ha-pf-add-custom-entity" class="button" style="margin-top:12px;">
                        <span class="dashicons dashicons-plus" style="margin-top:3px;"></span>
                        <?php esc_html_e( 'Add Entity', 'ha-powerflow' ); ?>
                    </button>

                </div>
            </div>

            <!-- ══════════════════════════════════════════
                 THRESHOLDS
                 ══════════════════════════════════════════ -->
            <p class="ha-pf-section-label"><?php esc_html_e( 'Thresholds', 'ha-powerflow' ); ?></p>

            <div class="ha-pf-panel open" id="ha-pf-panel-thresholds">
                <div class="ha-pf-panel-header">
                    <div class="ha-pf-panel-header-left">
                        <div class="ha-pf-panel-header-icon">
                            <span class="dashicons dashicons-flag"></span>
                        </div>
                        <?php esc_html_e( 'Label Colour Thresholds', 'ha-powerflow' ); ?>
                    </div>
                    <span class="ha-pf-arrow">&#9660;</span>
                </div>
                <div class="ha-pf-panel-body">

                    <p class="description" style="margin-bottom:16px;">
                        <?php esc_html_e( 'Change a label\'s colour when its value crosses a threshold. Rules are evaluated every refresh cycle — the last matching rule wins.', 'ha-powerflow' ); ?>
                    </p>

                    <input type="hidden"
                           name="ha_powerflow_thresholds"
                           id="ha-pf-thresholds-json"
                           value="<?php echo esc_attr( get_option( 'ha_powerflow_thresholds', '[]' ) ); ?>">

                    <div class="ha-pf-threshold-header">
                        <span><?php esc_html_e( 'Entity', 'ha-powerflow' ); ?></span>
                        <span><?php esc_html_e( 'When', 'ha-powerflow' ); ?></span>
                        <span><?php esc_html_e( 'Value', 'ha-powerflow' ); ?></span>
                        <span><?php esc_html_e( 'Colour', 'ha-powerflow' ); ?></span>
                        <span></span>
                    </div>

                    <div id="ha-pf-thresholds-list">
                        <?php
                        // Build entity options for PHP-rendered rows
                        $saved_custom_for_thresh = json_decode( get_option( 'ha_powerflow_custom_entities', '[]' ) ?: '[]', true );
                        if ( ! is_array( $saved_custom_for_thresh ) ) $saved_custom_for_thresh = [];

                        $builtin_groups = [
                            'Grid & Load' => [
                                'grid_power'         => 'Grid Power',
                                'grid_energy_in'     => 'Grid Energy In',
                                'grid_energy_out'    => 'Grid Energy Out',
                                'load_power'         => 'Load Power',
                                'load_energy'        => 'Load Energy',
                            ],
                            'Solar' => [
                                'pv_power'  => 'Solar Power',
                                'pv_energy' => 'Solar Energy',
                            ],
                            'Battery' => [
                                'battery_power'       => 'Battery Power',
                                'battery_energy_in'   => 'Battery In',
                                'battery_energy_out'  => 'Battery Out',
                                'battery_soc'         => 'Battery SOC',
                            ],
                            'EV' => [
                                'ev_power' => 'EV Power',
                                'ev_soc'   => 'EV SOC',
                            ],
                        ];

                        // Build the <select> options HTML (reused by PHP rows; JS uses haPfAdmin.entityLabels)
                        function ha_pf_threshold_entity_options( $selected_key, $builtin_groups, $custom_entities ) {
                            $html = '';
                            foreach ( $builtin_groups as $group_label => $keys ) {
                                $html .= '<optgroup label="' . esc_attr( $group_label ) . '">';
                                foreach ( $keys as $k => $l ) {
                                    $html .= '<option value="' . esc_attr( $k ) . '"' . selected( $selected_key, $k, false ) . '>' . esc_html( $l ) . '</option>';
                                }
                                $html .= '</optgroup>';
                            }
                            if ( ! empty( $custom_entities ) ) {
                                $html .= '<optgroup label="Custom">';
                                foreach ( $custom_entities as $ce ) {
                                    $k    = $ce['id']    ?? '';
                                    $l    = $ce['label'] ?? $k;
                                    $html .= '<option value="' . esc_attr( $k ) . '"' . selected( $selected_key, $k, false ) . '>' . esc_html( $l ) . '</option>';
                                }
                                $html .= '</optgroup>';
                            }
                            return $html;
                        }

                        $saved_thresholds = json_decode( get_option( 'ha_powerflow_thresholds', '[]' ) ?: '[]', true );
                        if ( is_array( $saved_thresholds ) ) :
                            foreach ( $saved_thresholds as $tr ) :
                                $tr_key    = $tr['key']      ?? 'grid_power';
                                $tr_op     = $tr['operator'] ?? '<';
                                $tr_val    = $tr['value']    ?? 0;
                                $tr_colour = $tr['colour']   ?? '#ef4444';
                        ?>
                        <div class="ha-pf-threshold-row">
                            <select class="tr-key">
                                <?php echo ha_pf_threshold_entity_options( $tr_key, $builtin_groups, $saved_custom_for_thresh ); ?>
                            </select>
                            <select class="tr-operator">
                                <?php foreach ( [ '<' => 'is below', '<=' => '≤', '>' => 'is above', '>=' => '≥', '==' => 'equals' ] as $op => $ol ) : ?>
                                <option value="<?php echo esc_attr( $op ); ?>" <?php selected( $tr_op, $op ); ?>><?php echo esc_html( $ol ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" class="tr-value" step="any"
                                   value="<?php echo esc_attr( $tr_val ); ?>"
                                   placeholder="0">
                            <input type="color" class="tr-colour"
                                   value="<?php echo esc_attr( $tr_colour ); ?>">
                            <button type="button" class="ha-pf-tr-delete button" aria-label="<?php esc_attr_e( 'Remove rule', 'ha-powerflow' ); ?>">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>

                    <button type="button" id="ha-pf-add-threshold" class="button" style="margin-top:12px;">
                        <span class="dashicons dashicons-plus" style="margin-top:3px;"></span>
                        <?php esc_html_e( 'Add Rule', 'ha-powerflow' ); ?>
                    </button>

                </div>
            </div>

            <!-- ══════════════════════════════════════════
                 APPEARANCE
                 ══════════════════════════════════════════ -->
            <p class="ha-pf-section-label"><?php esc_html_e( 'Appearance', 'ha-powerflow' ); ?></p>

            <div class="ha-pf-grid">

                <!-- Colours -->
                <div class="ha-pf-col">
                    <div class="ha-pf-panel open" id="ha-pf-panel-colours">
                        <div class="ha-pf-panel-header">
                            <div class="ha-pf-panel-header-left">
                                <div class="ha-pf-panel-header-icon">
                                    <span class="dashicons dashicons-art"></span>
                                </div>
                                <?php esc_html_e( 'Colours', 'ha-powerflow' ); ?>
                            </div>
                            <span class="ha-pf-arrow">&#9660;</span>
                        </div>
                        <div class="ha-pf-panel-body">
                            <div class="ha-pf-colour-swatches">
                                <?php foreach ( $colours as $key => $label ) :
                                    $val = get_option( 'ha_powerflow_' . $key, '#5EC766' );
                                    ?>
                                <div class="ha-pf-colour-item">
                                    <div class="ha-pf-colour-item-label"><?php echo esc_html( $label ); ?></div>
                                    <div class="ha-pf-colour-swatch-wrap">
                                        <input type="color"
                                            id="ha_pf_<?php echo esc_attr( $key ); ?>"
                                            name="ha_powerflow_<?php echo esc_attr( $key ); ?>"
                                            value="<?php echo esc_attr( $val ); ?>"
                                            class="ha-pf-colour-input">
                                    </div>
                                    <div class="ha-pf-colour-hex" id="ha-pf-hex-<?php echo esc_attr( $key ); ?>">
                                        <?php echo esc_html( strtoupper( $val ) ); ?>
                                    </div>
                                    <button type="button"
                                            class="button ha-pf-colour-reset ha-pf-colour-reset-btn"
                                            data-target="ha_pf_<?php echo esc_attr( $key ); ?>"
                                            data-hex="ha-pf-hex-<?php echo esc_attr( $key ); ?>"
                                            data-default="#5EC766">
                                        <?php esc_html_e( 'Reset', 'ha-powerflow' ); ?>
                                    </button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Flow Paths -->
                <div class="ha-pf-col">
                    <div class="ha-pf-panel open" id="ha-pf-panel-flows">
                        <div class="ha-pf-panel-header">
                            <div class="ha-pf-panel-header-left">
                                <div class="ha-pf-panel-header-icon">
                                    <span class="dashicons dashicons-randomize"></span>
                                </div>
                                <?php esc_html_e( 'Flow Paths', 'ha-powerflow' ); ?>
                            </div>
                            <span class="ha-pf-arrow">&#9660;</span>
                        </div>
                        <div class="ha-pf-panel-body">
                            <p class="description" style="margin-bottom:14px;">
                                <?php esc_html_e( 'SVG motion paths for each flow. Leave blank to use defaults. Must start with M.', 'ha-powerflow' ); ?>
                            </p>
                            <?php foreach ( $flow_defaults as $flow => [ $def_fwd, $def_rev ] ) : ?>
                            <div class="ha-pf-flow-group">
                                <div class="ha-pf-flow-name"><?php echo esc_html( ucfirst( $flow ) ); ?></div>
                                <div class="ha-pf-path-row">
                                    <span class="ha-pf-path-badge fwd">Fwd</span>
                                    <input type="text"
                                        name="ha_powerflow_<?php echo esc_attr( $flow ); ?>_flow_forward"
                                        value="<?php echo esc_attr( get_option( 'ha_powerflow_' . $flow . '_flow_forward' ) ); ?>"
                                        placeholder="<?php echo esc_attr( $def_fwd ); ?>"
                                        class="ha-pf-path-input">
                                </div>
                                <div class="ha-pf-path-row">
                                    <span class="ha-pf-path-badge rev">Rev</span>
                                    <input type="text"
                                        name="ha_powerflow_<?php echo esc_attr( $flow ); ?>_flow_reverse"
                                        value="<?php echo esc_attr( get_option( 'ha_powerflow_' . $flow . '_flow_reverse' ) ); ?>"
                                        placeholder="<?php echo esc_attr( $def_rev ); ?>"
                                        class="ha-pf-path-input">
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

            </div><!-- /appearance grid -->

            <!-- ══════════════════════════════════════════
                 DEVELOPER TOOLS & UNINSTALL
                 ══════════════════════════════════════════ -->
            <p class="ha-pf-section-label"><?php esc_html_e( 'Advanced', 'ha-powerflow' ); ?></p>

            <div class="ha-pf-grid">

                <div class="ha-pf-col">
                    <div class="ha-pf-card">
                        <div class="ha-pf-card-header">
                            <span class="dashicons dashicons-admin-tools"></span>
                            <?php esc_html_e( 'Developer Tools', 'ha-powerflow' ); ?>
                        </div>
                        <div class="ha-pf-card-body">
                            <label class="ha-pf-checkbox-label">
                                <input type="hidden" name="ha_powerflow_debug_click" value="0">
                                <input type="checkbox"
                                       name="ha_powerflow_debug_click"
                                       value="1"
                                       <?php checked( get_option( 'ha_powerflow_debug_click' ), '1' ); ?>>
                                <span>
                                    <span class="ha-pf-check-text"><?php esc_html_e( 'Enable click-to-coordinate tool', 'ha-powerflow' ); ?></span>
                                    <span class="description"><?php esc_html_e( 'Click anywhere on the dashboard to output SVG x/y coordinates to the browser console and place a temporary marker. Double-click to clear markers. Disable when your site is live.', 'ha-powerflow' ); ?></span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="ha-pf-col">
                    <div class="ha-pf-card">
                        <div class="ha-pf-card-header">
                            <span class="dashicons dashicons-trash"></span>
                            <?php esc_html_e( 'Uninstall', 'ha-powerflow' ); ?>
                        </div>
                        <div class="ha-pf-card-body">
                            <label class="ha-pf-checkbox-label">
                                <input type="hidden" name="ha_powerflow_delete_uploads" value="0">
                                <input type="checkbox"
                                       name="ha_powerflow_delete_uploads"
                                       value="1"
                                       <?php checked( get_option( 'ha_powerflow_delete_uploads' ), '1' ); ?>>
                                <span>
                                    <span class="ha-pf-check-text"><?php esc_html_e( 'Delete uploaded images on uninstall', 'ha-powerflow' ); ?></span>
                                    <span class="description"><?php esc_html_e( 'When enabled, background images in wp-content/uploads/ha-powerflow/ will be permanently deleted when the plugin is removed.', 'ha-powerflow' ); ?></span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

            </div><!-- /advanced grid -->

            <!-- Server Snapshots — full-width below the card grid -->
            <div class="ha-pf-card" id="ha-pf-card-snapshots" style="margin-top:16px;">
                <div class="ha-pf-card-header">
                    <span class="dashicons dashicons-backup"></span>
                    <?php esc_html_e( 'Restore from Server Snapshot', 'ha-powerflow' ); ?>
                </div>
                <div class="ha-pf-card-body">
                    <p class="description" style="margin-bottom:12px;">
                        <?php esc_html_e( 'Select a backup snapshot saved automatically by the plugin and restore it directly — no file download required.', 'ha-powerflow' ); ?>
                    </p>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <select id="ha-pf-snapshot-select" style="min-width:260px;">
                            <option value=""><?php esc_html_e( 'Loading snapshots\u2026', 'ha-powerflow' ); ?></option>
                        </select>
                        <button type="button" id="ha-pf-snapshot-restore-btn" class="button" disabled>
                            <span class="dashicons dashicons-image-rotate" style="margin-top:3px;margin-right:2px;"></span>
                            <?php esc_html_e( 'Restore Selected', 'ha-powerflow' ); ?>
                        </button>
                        <span id="ha-pf-snapshot-status" style="font-size:13px;color:#6b7280;"></span>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════════
                 STICKY SAVE BAR (inside the form so Save works)
                 ══════════════════════════════════════════ -->
            <div class="ha-pf-save-bar">
                <div class="ha-pf-save-bar-left">
                    <span class="ha-pf-dirty-badge" id="ha-pf-dirty-badge">
                        <span class="ha-pf-dirty-dot"></span>
                        <?php esc_html_e( 'Unsaved changes', 'ha-powerflow' ); ?>
                    </span>
                    <span id="ha-pf-config-note">
                        <?php esc_html_e( 'A config snapshot is saved automatically on every save.', 'ha-powerflow' ); ?>
                    </span>
                    <button type="button" id="ha-pf-restore-btn" class="button ha-pf-restore-btn">
                        <span class="dashicons dashicons-upload" style="margin-top:3px;margin-right:4px;"></span>
                        <?php esc_html_e( 'Restore from Backup&hellip;', 'ha-powerflow' ); ?>
                    </button>
                </div>
                <?php submit_button(
                    __( 'Save Settings', 'ha-powerflow' ),
                    'primary',
                    'ha-pf-save-btn',
                    false,
                    [ 'id' => 'ha-pf-save-btn' ]
                ); ?>
            </div>

        </form><!-- /options.php form -->

        <!-- File input is intentionally OUTSIDE the form so it never submits through options.php -->
        <input type="file"
               id="ha-pf-import-file"
               accept=".yaml,.yml"
               style="display:none;"
               aria-hidden="true">

    <!-- ══════════════════════════════════════════════
         CONFIG RESTORE MODAL
         ══════════════════════════════════════════════ -->
    <div id="ha-pf-restore-overlay" class="ha-pf-overlay" aria-hidden="true" role="dialog"
         aria-modal="true" aria-labelledby="ha-pf-restore-modal-title">
        <div class="ha-pf-modal">

            <div class="ha-pf-modal-icon">
                <span class="dashicons dashicons-warning"></span>
            </div>

            <h2 id="ha-pf-restore-modal-title">
                <?php esc_html_e( 'Restore Settings?', 'ha-powerflow' ); ?>
            </h2>

            <p class="ha-pf-modal-file" id="ha-pf-restore-filename"></p>

            <p class="ha-pf-modal-body">
                <?php esc_html_e(
                    'This will overwrite ALL current settings — connection details, entity IDs, positions, appearance, custom entities, and gauge configuration — with the values from the selected backup file.',
                    'ha-powerflow'
                ); ?>
            </p>

            <p class="ha-pf-modal-body">
                <strong><?php esc_html_e( 'This action cannot be undone.', 'ha-powerflow' ); ?></strong>
                <?php esc_html_e( 'Your current settings will be saved as a backup snapshot before the restore begins.', 'ha-powerflow' ); ?>
            </p>

            <div class="ha-pf-modal-actions">
                <button type="button" id="ha-pf-restore-cancel" class="button button-large">
                    <?php esc_html_e( 'Cancel', 'ha-powerflow' ); ?>
                </button>
                <button type="button" id="ha-pf-restore-confirm" class="button button-primary button-large ha-pf-restore-confirm-btn">
                    <span class="ha-pf-restore-confirm-icon dashicons dashicons-yes-alt"></span>
                    <span class="ha-pf-restore-confirm-text">
                        <?php esc_html_e( 'Yes, Restore Settings', 'ha-powerflow' ); ?>
                    </span>
                    <span class="ha-pf-restore-spinner spinner" style="display:none;float:none;vertical-align:middle;"></span>
                </button>
            </div>

        </div>
    </div>

    </div><!-- /wrap -->
    <?php
}

/**
 * Render entity rows using the new grid layout.
 */
function ha_pf_entity_table( array $entities ) {
    ?>
    <div class="ha-pf-entity-header">
        <span><?php esc_html_e( 'Entity ID', 'ha-powerflow' ); ?></span>
        <span><?php esc_html_e( 'Rot', 'ha-powerflow' ); ?></span>
        <span>X</span>
        <span>Y</span>
    </div>
    <?php foreach ( $entities as $key => $label ) : ?>
    <div class="ha-pf-entity-row">

        <div>
            <div class="ha-pf-entity-label"><?php echo esc_html( $label ); ?></div>
            <input type="text"
                name="ha_powerflow_<?php echo esc_attr( $key ); ?>"
                value="<?php echo esc_attr( get_option( 'ha_powerflow_' . $key ) ); ?>"
                placeholder="sensor.<?php echo esc_attr( $key ); ?>">
        </div>

        <div class="ha-pf-coord-group">
            <span class="ha-pf-coord-label">Rot</span>
            <input type="number"
                name="ha_powerflow_<?php echo esc_attr( $key ); ?>_rot"
                value="<?php echo esc_attr( get_option( 'ha_powerflow_' . $key . '_rot' ) ); ?>"
                placeholder="0">
        </div>

        <div class="ha-pf-coord-group">
            <span class="ha-pf-coord-label">X</span>
            <input type="number" min="0"
                name="ha_powerflow_<?php echo esc_attr( $key ); ?>_x_pos"
                value="<?php echo esc_attr( get_option( 'ha_powerflow_' . $key . '_x_pos' ) ); ?>"
                placeholder="0">
        </div>

        <div class="ha-pf-coord-group">
            <span class="ha-pf-coord-label">Y</span>
            <input type="number" min="0"
                name="ha_powerflow_<?php echo esc_attr( $key ); ?>_y_pos"
                value="<?php echo esc_attr( get_option( 'ha_powerflow_' . $key . '_y_pos' ) ); ?>"
                placeholder="0">
        </div>

    </div>
    <?php endforeach; ?>
    <?php
}
