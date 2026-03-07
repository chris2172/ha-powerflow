<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', 'ha_powerflow_admin_menu' );
function ha_powerflow_admin_menu() {
    add_menu_page(
        'HA Powerflow Settings',
        'HA Powerflow',
        'manage_options',
        'ha-powerflow',
        'ha_powerflow_settings_page',
        'dashicons-chart-area'
    );
}

add_action( 'admin_init', 'ha_powerflow_register_settings' );
function ha_powerflow_register_settings() {
    register_setting( 'ha_powerflow_group', 'ha_powerflow_options', 'ha_powerflow_sanitize' );
}

/**
 * Handle automatic snapshot on save
 */
add_action( 'update_option_ha_powerflow_options', 'ha_pf_create_snapshot', 10, 3 );
add_action( 'add_option_ha_powerflow_options',    'ha_pf_create_snapshot', 10, 2 );

function ha_pf_create_snapshot( $old_val, $new_val = null ) {
    // If called via add_option, $old_val is actually the $new_val
    if ( $new_val === null ) {
        $new_val = $old_val;
    }

    if ( ! is_array( $new_val ) ) return;
    
    $timestamp = date( 'Y-m-d_H-i-s' );
    $filename  = HA_POWERFLOW_CONFIG_DIR . 'snapshot_' . $timestamp . '.yaml';
    
    // Simple YAML-like encoding for the options array
    $content = "# HA Powerflow Configuration Snapshot\n";
    $content .= "# Saved: " . date( 'Y-m-d H:i:s' ) . "\n\n";
    foreach ( $new_val as $k => $v ) {
        if ( is_array( $v ) ) {
            $v = json_encode( $v );
        }
        // Basic escaping/quoting for YAML compatibility
        $clean_v = str_replace( '"', '\"', (string) $v );
        $content .= "$k: \"$clean_v\"\n";
    }
    
    @file_put_contents( $filename, $content );
    
    // Prune settings to stay at 50
    $files = glob( HA_POWERFLOW_CONFIG_DIR . 'snapshot_*.yaml' );
    if ( count( $files ) > 50 ) {
        sort( $files );
        $to_delete = count( $files ) - 50;
        for ( $i = 0; $i < $to_delete; $i++ ) {
            @unlink( $files[$i] );
        }
    }
}

add_action( 'admin_enqueue_scripts', 'ha_powerflow_admin_assets' );
function ha_powerflow_admin_assets( $hook ) {
    if ( $hook !== 'toplevel_page_ha-powerflow' ) return;
    wp_enqueue_media();
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_style( 'ha-powerflow-admin-style', HA_POWERFLOW_URL . 'assets/css/admin.css', [], HA_POWERFLOW_VERSION );
    wp_enqueue_script( 'ha-powerflow-admin', HA_POWERFLOW_URL . 'assets/js/admin.js', [ 'wp-color-picker', 'jquery' ], HA_POWERFLOW_VERSION, true );

    $upload_dir = wp_upload_dir();
    wp_localize_script( 'ha-powerflow-admin', 'haPfAdmin', [
        'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
        'nonce'         => wp_create_nonce( 'ha_pf_test_connection' ),
        'uploadDirUrl'  => $upload_dir['baseurl'] . '/ha-powerflow/',
        'selectImage'   => __( 'Select Image', 'ha-powerflow' ),
        'useImage'      => __( 'Use this image', 'ha-powerflow' ),
        'defaultBg'     => HA_POWERFLOW_URL . 'assets/images/default_bg.png',
    ] );
}

function ha_powerflow_sanitize( $input ) {
    $color = sanitize_hex_color( $input['line_color'] ?? '#4a90d9' );
    if ( ! $color ) $color = '#4a90d9';
    $opacity = max( 0.0, min( 1.0, round( floatval( $input['line_opacity'] ?? 1.0 ), 2 ) ) );

    $title_color  = sanitize_hex_color( $input['title_color'] ?? '#8899bb' ) ?: '#8899bb';
    $power_color  = sanitize_hex_color( $input['power_color'] ?? '#f0a500' ) ?: '#f0a500';
    $energy_color = sanitize_hex_color( $input['energy_color'] ?? '#6677aa' ) ?: '#6677aa';

    $grid_color     = sanitize_hex_color( $input['grid_color'] ?? '' ) ?: '';
    $load_color     = sanitize_hex_color( $input['load_color'] ?? '' ) ?: '';
    $pv_color       = sanitize_hex_color( $input['pv_color'] ?? '' ) ?: '';
    $battery_color  = sanitize_hex_color( $input['battery_color'] ?? '' ) ?: '';
    $ev_color       = sanitize_hex_color( $input['ev_color'] ?? '' ) ?: '';
    $heatpump_color = sanitize_hex_color( $input['heatpump_color'] ?? '' ) ?: '';

    return [
        'ha_url'          => esc_url_raw(        $input['ha_url']          ?? '' ),
        'ha_token'        => sanitize_text_field( $input['ha_token']        ?? '' ),
        'refresh_rate'    => (int) ( $input['refresh_rate'] ?? 5000 ),
        'grid_power'          => sanitize_text_field( $input['grid_power']          ?? '' ),
        'load_power'          => sanitize_text_field( $input['load_power']          ?? '' ),
        'grid_energy'         => sanitize_text_field( $input['grid_energy']         ?? '' ),
        'grid_energy_out'     => sanitize_text_field( $input['grid_energy_out']     ?? '' ),
        'grid_price_in'       => sanitize_text_field( $input['grid_price_in']       ?? '' ),
        'grid_price_out'      => sanitize_text_field( $input['grid_price_out']      ?? '' ),
        'load_energy'         => sanitize_text_field( $input['load_energy']         ?? '' ),
        'pv_power'            => sanitize_text_field( $input['pv_power']            ?? '' ),
        'pv_energy'           => sanitize_text_field( $input['pv_energy']           ?? '' ),
        'battery_power'       => sanitize_text_field( $input['battery_power']       ?? '' ),
        'battery_in_energy'   => sanitize_text_field( $input['battery_in_energy']   ?? '' ),
        'battery_out_energy'  => sanitize_text_field( $input['battery_out_energy']  ?? '' ),
        'battery_soc'         => sanitize_text_field( $input['battery_soc']         ?? '' ),
        'ev_power'            => sanitize_text_field( $input['ev_power']            ?? '' ),
        'ev_soc'              => sanitize_text_field( $input['ev_soc']              ?? '' ),
        'heatpump_power'      => sanitize_text_field( $input['heatpump_power']      ?? '' ),
        'heatpump_energy'     => sanitize_text_field( $input['heatpump_energy']     ?? '' ),
        'heatpump_efficiency' => sanitize_text_field( $input['heatpump_efficiency'] ?? '' ),
        'bg_image'            => esc_url_raw(        $input['bg_image']             ?? '' ),
        'grid_line'           => preg_replace( '/[^MmLlHhVvCcSsQqTtAaZz\d\s,.\-+]/', '', $input['grid_line']         ?? '' ),
        'load_line'           => preg_replace( '/[^MmLlHhVvCcSsQqTtAaZz\d\s,.\-+]/', '', $input['load_line']         ?? '' ),
        'pv_line'             => preg_replace( '/[^MmLlHhVvCcSsQqTtAaZz\d\s,.\-+]/', '', $input['pv_line']           ?? '' ),
        'battery_line'        => preg_replace( '/[^MmLlHhVvCcSsQqTtAaZz\d\s,.\-+]/', '', $input['battery_line']      ?? '' ),
        'ev_line'             => preg_replace( '/[^MmLlHhVvCcSsQqTtAaZz\d\s,.\-+]/', '', $input['ev_line']           ?? '' ),
        'heatpump_line'       => preg_replace( '/[^MmLlHhVvCcSsQqTtAaZz\d\s,.\-+]/', '', $input['heatpump_line']     ?? '' ),
        'line_color'      => $color,
        'grid_color'      => $grid_color,
        'load_color'      => $load_color,
        'pv_color'        => $pv_color,
        'battery_color'   => $battery_color,
        'ev_color'        => $ev_color,
        'heatpump_color'  => $heatpump_color,
        'line_opacity'    => $opacity,
        'title_color'     => $title_color,
        'power_color'     => $power_color,
        'energy_color'    => $energy_color,
        'grid_label_x'    => (int) ( $input['grid_label_x']   ?? 120 ),
        'grid_label_y'    => (int) ( $input['grid_label_y']   ?? 260 ),
        'load_label_x'    => (int) ( $input['load_label_x']   ?? 880 ),
        'load_label_y'    => (int) ( $input['load_label_y']   ?? 260 ),
        'pv_label_x'      => (int) ( $input['pv_label_x']      ?? 500 ),
        'pv_label_y'      => (int) ( $input['pv_label_y']      ?? 150 ),
        'battery_label_x'    => (int) ( $input['battery_label_x']    ?? 500 ),
        'battery_label_y'    => (int) ( $input['battery_label_y']    ?? 550 ),
        'ev_label_x'         => (int) ( $input['ev_label_x']         ?? 750 ),
        'ev_label_y'         => (int) ( $input['ev_label_y']         ?? 550 ),
        'heatpump_label_x'   => (int) ( $input['heatpump_label_x']   ?? 250 ),
        'heatpump_label_y'   => (int) ( $input['heatpump_label_y']   ?? 550 ),
        'status_x'        => (int) ( $input['status_x']       ?? 500 ),
        'status_y'        => (int) ( $input['status_y']       ?? 320 ),
        'enable_solar'    => ! empty( $input['enable_solar'] )    ? '1' : '',
        'enable_battery'  => ! empty( $input['enable_battery'] )  ? '1' : '',
        'enable_ev'       => ! empty( $input['enable_ev'] )       ? '1' : '',
        'enable_heatpump' => ! empty( $input['enable_heatpump'] ) ? '1' : '',
        'enable_weather'  => ! empty( $input['enable_weather'] )  ? '1' : '',
        'weather_entity'  => sanitize_text_field( $input['weather_entity']  ?? '' ),
        'weather_x'       => (int) ( $input['weather_x']       ?? 500 ),
        'weather_y'       => (int) ( $input['weather_y']       ?? 80 ),
        'weather_font_size' => (int) ( $input['weather_font_size'] ?? 13 ),
        'debug'           => ! empty( $input['debug'] ) ? '1' : '',
        'custom_entities' => ! empty( $input['custom_entities'] ) && is_array( $input['custom_entities'] ) ? array_map( function( $item ) {
            return [
                'label'   => sanitize_text_field( $item['label']  ?? '' ),
                'entity'  => sanitize_text_field( $item['entity'] ?? '' ),
                'x'       => (int) ( $item['x'] ?? 0 ),
                'y'       => (int) ( $item['y'] ?? 0 ),
                'visible' => ! empty( $item['visible'] ) ? '1' : '',
            ];
        }, array_values( $input['custom_entities'] ) ) : [],
    ];
}

// ── Helpers ────────────────────────────────────────────────────────────────
function ha_pf_xy( $o, $kx, $ky, $dx, $dy, $desc = '' ) { ?>
    <div class="ha-pf-xy-group" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <label style="font-weight:600;min-width:18px;">X</label>
        <input type="number" name="ha_powerflow_options[<?php echo $kx; ?>]"
               value="<?php echo esc_attr( $o[$kx] ?? $dx ); ?>"
               min="0" max="1000" step="1" style="width:70px;"/>
        <label style="font-weight:600;min-width:18px;">Y</label>
        <input type="number" name="ha_powerflow_options[<?php echo $ky; ?>]"
               value="<?php echo esc_attr( $o[$ky] ?? $dy ); ?>"
               min="0" max="700" step="1" style="width:70px;"/>
        <button type="button" class="ha-pf-coord-picker-btn" title="Pick position from image">🎯</button>
    </div>
    <?php if ( $desc ) echo '<p class="description">' . $desc . '</p>';
}

function ha_pf_entity( $o, $key, $label, $placeholder, $desc = '' ) { ?>
    <tr>
        <th scope="row"><label><?php echo esc_html( $label ); ?></label></th>
        <td>
            <input type="text" name="ha_powerflow_options[<?php echo esc_attr( $key ); ?>]"
                   value="<?php echo esc_attr( $o[$key] ?? '' ); ?>"
                   class="regular-text" placeholder="<?php echo esc_attr( $placeholder ); ?>"/>
            <?php if ( $desc ) echo '<p class="description">' . $desc . '</p>'; ?>
        </td>
    </tr>
<?php }

// ── Check if any module is enabled ─────────────────────────────────────────
function ha_pf_any_module( $o ) {
    return ! empty( $o['enable_solar'] )    ||
           ! empty( $o['enable_battery'] )  ||
           ! empty( $o['enable_ev'] )       ||
           ! empty( $o['enable_heatpump'] ) ||
           ! empty( $o['enable_weather'] );
}

function ha_powerflow_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $o            = get_option( 'ha_powerflow_options', [] );
    $color        = $o['line_color']   ?? '#4a90d9';
    $opacity      = isset( $o['line_opacity'] ) ? floatval( $o['line_opacity'] ) : 1.0;
    $load_visible = ha_pf_any_module( $o ); // whether load line row starts visible
    ?>
    <div class="wrap ha-pf-wrap">
        <h1>⚡ HA Powerflow <span class="ha-pf-version">v<?php echo HA_POWERFLOW_VERSION; ?></span></h1>

        <form method="post" action="options.php" id="ha-pf-settings-form">
            <?php settings_fields( 'ha_powerflow_group' ); ?>

            <div class="ha-pf-toggles-bar">
                <span class="ha-pf-toggles-title">Quick Modules:</span>
                <?php
                $modules = [ 'solar' => 'Solar', 'battery' => 'Battery', 'ev' => 'EV', 'heatpump' => 'Heat Pump', 'weather' => 'Weather' ];
                foreach ( $modules as $key => $label ) :
                    $checked = ! empty( $o[ 'enable_' . $key ] ); ?>
                <label class="ha-pf-toggle-label">
                    <input type="checkbox" id="ha-pf-toggle-<?php echo $key; ?>" name="ha_powerflow_options[enable_<?php echo $key; ?>]" value="1" <?php checked( $checked ); ?>/>
                    <span class="ha-pf-slider"></span>
                    <span class="ha-pf-toggle-text"><?php echo esc_html( $label ); ?></span>
                </label>
                <?php endforeach; ?>
            </div>

            <!-- ── Section 1: Connection & Sensors ────────────────────────── -->
            <div class="ha-pf-columns">
                <div class="ha-pf-col ha-pf-col-left">
                    <div class="ha-pf-card">
                        <h2>🔌 Connection & Refresh</h2>
                        <table class="form-table form-table-sm" role="presentation">
                            <tr>
                                <th><label for="ha_url">HA URL</label></th>
                                <td>
                                    <input type="url" id="ha_url" name="ha_powerflow_options[ha_url]"
                                           value="<?php echo esc_attr( $o['ha_url'] ?? '' ); ?>"
                                           class="widefat" placeholder="http://homeassistant.local:8123"/>
                                    <p class="description">Include port, e.g. <code>http://192.168.1.10:8123</code></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="ha_token">Access Token</label></th>
                                <td>
                                    <input type="password" id="ha_token" name="ha_powerflow_options[ha_token]"
                                           value="<?php echo esc_attr( $o['ha_token'] ?? '' ); ?>"
                                           class="widefat"/>
                                    <p class="description">HA → Profile → Long-Lived Access Tokens.</p>
                                    <button type="button" id="ha-pf-test-btn" class="button" style="margin-top:8px;">
                                        Test Connection
                                    </button>
                                    <div id="ha-pf-test-result" style="display:none;margin-top:8px;padding:8px 12px;border-radius:4px;font-size:13px;font-weight:600;"></div>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="refresh_rate">Refresh Rate</label></th>
                                <td>
                                    <?php
                                    $refresh = (int) ( $o['refresh_rate'] ?? 5000 );
                                    $rates   = [
                                        5000  => '5 seconds (default)',
                                        10000 => '10 seconds ★ recommended',
                                        15000 => '15 seconds',
                                        30000 => '30 seconds',
                                        60000 => '1 minute',
                                    ];
                                    ?>
                                    <select id="refresh_rate" name="ha_powerflow_options[refresh_rate]">
                                        <?php foreach ( $rates as $ms => $label ) : ?>
                                            <option value="<?php echo $ms; ?>" <?php selected( $refresh, $ms ); ?>>
                                                <?php echo esc_html( $label ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">How often the widget polls Home Assistant for new data.</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="ha-pf-col ha-pf-col-right">
                    <div class="ha-pf-card">
                        <h2>🔍 Smart Discovery</h2>
                        <p class="description">Scan your Home Assistant for relevant sensors.</p>
                        <button type="button" id="ha-pf-discover-btn" class="button">Scan for Entities</button>
                        <div id="ha-pf-discover-results" style="margin-top:10px; display:none; max-height:200px; overflow-y:auto; font-size:12px; border:1px solid #e2e8f0; border-radius:8px; padding:10px; background:#f8fafc;"></div>
                    </div>
                </div>
            </div>

            <div class="ha-pf-columns" style="margin-top:32px;">
                <div class="ha-pf-col ha-pf-col-left">
                    <div class="ha-pf-card">
                        <h2>📡 Core Sensors</h2>
                        <table class="form-table form-table-sm" role="presentation">
                            <?php ha_pf_entity( $o, 'grid_power',  'Grid Power Entity',  'sensor.grid_power',  'Positive = import, negative = export.' ); ?>
                            <?php ha_pf_entity( $o, 'load_power',  'House Power Entity',  'sensor.load_power'  ); ?>
                            <?php ha_pf_entity( $o, 'grid_energy', 'Grid Energy Import', 'sensor.grid_energy_import' ); ?>
                            <?php ha_pf_entity( $o, 'grid_energy_out', 'Grid Energy Export', 'sensor.grid_energy_export' ); ?>
                            <?php ha_pf_entity( $o, 'grid_price_in', 'Grid Price Import (£)', 'sensor.grid_price_import' ); ?>
                            <?php ha_pf_entity( $o, 'grid_price_out', 'Grid Price Export (£)', 'sensor.grid_price_export' ); ?>
                            <?php ha_pf_entity( $o, 'load_energy', 'House Energy Entity', 'sensor.load_energy' ); ?>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ── Section 2: Layout & Appearance ────────────────────────── -->
            <div class="ha-pf-columns" style="margin-top:32px;">
                <div class="ha-pf-col ha-pf-col-left">
                    <div class="ha-pf-card">
                        <h2>🎨 Global Appearance</h2>
                        <table class="form-table form-table-sm" role="presentation">
                            <tr>
                                <th><label for="bg_image">Background Image</label></th>
                                <td>
                                    <div style="display:flex;gap:8px;align-items:center;">
                                        <input type="url" id="bg_image" name="ha_powerflow_options[bg_image]"
                                               value="<?php echo esc_attr( $o['bg_image'] ?? '' ); ?>"
                                               class="widefat" placeholder="URL..."/>
                                        <button type="button" id="ha-pf-media-btn" class="button">Select</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="line_color">Base Line Color</label></th>
                                <td><input type="text" name="ha_powerflow_options[line_color]" value="<?php echo esc_attr( $o['line_color'] ?? '#4a90d9' ); ?>" class="ha-pf-color-picker" /></td>
                            </tr>
                            <tr>
                                <th><label for="line_opacity">Line Opacity</label></th>
                                <td>
                                    <input type="range" name="ha_powerflow_options[line_opacity]" min="0" max="1" step="0.05" value="<?php echo esc_attr( $o['line_opacity'] ?? 1.0 ); ?>" oninput="this.nextElementSibling.textContent=this.value"/>
                                    <span class="ha-pf-range-val"><?php echo $o['line_opacity'] ?? 1.0; ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="ha-pf-card">
                        <h2>📐 Common Paths</h2>
                        <table class="form-table form-table-sm" role="presentation">
                            <tr><th>Grid Line</th><td><input type="text" name="ha_powerflow_options[grid_line]" value="<?php echo esc_attr( $o['grid_line'] ?? '' ); ?>" class="widefat" /></td></tr>
                            <tr><th>House Line</th><td><input type="text" name="ha_powerflow_options[load_line]" value="<?php echo esc_attr( $o['load_line'] ?? '' ); ?>" class="widefat" /></td></tr>
                        </table>
                    </div>
                </div>

                <div class="ha-pf-col ha-pf-col-right">
                    <div class="ha-pf-card">
                        <h2>🏷 Core Label Positions</h2>
                        <table class="form-table form-table-sm" role="presentation">
                            <tr><th>Grid</th><td><?php ha_pf_xy( $o, 'grid_label_x', 'grid_label_y', 120, 260 ); ?></td></tr>
                            <tr><th>Home</th><td><?php ha_pf_xy( $o, 'load_label_x', 'load_label_y', 880, 260 ); ?></td></tr>
                            <tr><th>Status</th><td><?php ha_pf_xy( $o, 'status_x', 'status_y', 500, 320 ); ?></td></tr>
                        </table>
                    </div>
                    <div class="ha-pf-card">
                        <h2>🎨 Text Colors</h2>
                        <table class="form-table form-table-sm" role="presentation">
                            <tr><th>Title</th><td><input type="text" name="ha_powerflow_options[title_color]" value="<?php echo esc_attr( $o['title_color'] ?? '#8899bb' ); ?>" class="ha-pf-color-picker" /></td></tr>
                            <tr><th>Power</th><td><input type="text" name="ha_powerflow_options[power_color]" value="<?php echo esc_attr( $o['power_color'] ?? '#f0a500' ); ?>" class="ha-pf-color-picker" /></td></tr>
                            <tr><th>Energy</th><td><input type="text" name="ha_powerflow_options[energy_color]" value="<?php echo esc_attr( $o['energy_color'] ?? '#6677aa' ); ?>" class="ha-pf-color-picker" /></td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ── Section 3: Module Settings ────────────────────────────── -->
            <div class="ha-pf-columns" style="margin-top:32px;">
                <div class="ha-pf-col ha-pf-col-left">
                    <div id="ha-pf-section-solar" class="ha-pf-card ha-pf-module-card" data-module="solar">
                        <h2>☀️ Solar</h2>
                        <table class="form-table form-table-sm">
                            <?php ha_pf_entity( $o, 'pv_power', 'PV Power', 'sensor.pv_power' ); ?>
                            <?php ha_pf_entity( $o, 'pv_energy', 'PV Today', 'sensor.pv_energy' ); ?>
                            <tr><th>Line Color</th><td><input type="text" name="ha_powerflow_options[pv_color]" value="<?php echo esc_attr( $o['pv_color'] ?? '' ); ?>" class="ha-pf-color-picker" /></td></tr>
                            <tr><th>PV Line Path</th><td><input type="text" name="ha_powerflow_options[pv_line]" value="<?php echo esc_attr( $o['pv_line'] ?? '' ); ?>" class="widefat" placeholder="SVG Path..."/></td></tr>
                            <tr><th>Position</th><td><?php ha_pf_xy( $o, 'pv_label_x', 'pv_label_y', 500, 150 ); ?></td></tr>
                        </table>
                    </div>
                    <div id="ha-pf-section-ev" class="ha-pf-card ha-pf-module-card" data-module="ev">
                        <h2>🚗 EV</h2>
                        <table class="form-table form-table-sm">
                            <?php ha_pf_entity( $o, 'ev_power', 'EV Power', 'sensor.ev_power' ); ?>
                            <?php ha_pf_entity( $o, 'ev_soc',   'EV SOC',   'sensor.ev_soc' ); ?>
                            <tr><th>EV Line Path</th><td><input type="text" name="ha_powerflow_options[ev_line]" value="<?php echo esc_attr( $o['ev_line'] ?? '' ); ?>" class="widefat" placeholder="SVG Path..."/></td></tr>
                            <tr><th>Position</th><td><?php ha_pf_xy( $o, 'ev_label_x', 'ev_label_y', 750, 550 ); ?></td></tr>
                        </table>
                    </div>
                </div>
                <div class="ha-pf-col ha-pf-col-right">
                    <div id="ha-pf-section-battery" class="ha-pf-card ha-pf-module-card" data-module="battery">
                        <h2>🔋 Battery</h2>
                        <table class="form-table form-table-sm">
                            <?php ha_pf_entity( $o, 'battery_power', 'Power', 'sensor.battery_power' ); ?>
                            <?php ha_pf_entity( $o, 'battery_in_energy', 'Energy In', 'sensor.battery_energy_in' ); ?>
                            <?php ha_pf_entity( $o, 'battery_out_energy', 'Energy Out', 'sensor.battery_energy_out' ); ?>
                            <?php ha_pf_entity( $o, 'battery_soc', 'SOC', 'sensor.battery_soc' ); ?>
                            <tr><th>Battery Line Path</th><td><input type="text" name="ha_powerflow_options[battery_line]" value="<?php echo esc_attr( $o['battery_line'] ?? '' ); ?>" class="widefat" placeholder="SVG Path..."/></td></tr>
                            <tr><th>Position</th><td><?php ha_pf_xy( $o, 'battery_label_x', 'battery_label_y', 500, 550 ); ?></td></tr>
                        </table>
                    </div>
                    <div id="ha-pf-section-heatpump" class="ha-pf-card ha-pf-module-card" data-module="heatpump">
                        <h2>♨️ Heat Pump</h2>
                        <table class="form-table form-table-sm">
                            <?php ha_pf_entity( $o, 'heatpump_power', 'Power', 'sensor.heat_pump_power' ); ?>
                            <?php ha_pf_entity( $o, 'heatpump_energy', 'Energy Today', 'sensor.heat_pump_energy' ); ?>
                            <?php ha_pf_entity( $o, 'heatpump_efficiency', 'Efficiency (COP)', 'sensor.heat_pump_cop' ); ?>
                            <tr><th>HP Line Path</th><td><input type="text" name="ha_powerflow_options[heatpump_line]" value="<?php echo esc_attr( $o['heatpump_line'] ?? '' ); ?>" class="widefat" placeholder="SVG Path..."/></td></tr>
                            <tr><th>Position</th><td><?php ha_pf_xy( $o, 'heatpump_label_x', 'heatpump_label_y', 250, 550 ); ?></td></tr>
                        </table>
                    </div>
                    <div id="ha-pf-section-weather" class="ha-pf-card ha-pf-module-card" data-module="weather">
                        <h2>☁️ Weather</h2>
                        <table class="form-table form-table-sm">
                            <?php ha_pf_entity( $o, 'weather_entity', 'Weather Entity', 'weather.home' ); ?>
                            <tr><th>Font Size</th><td><input type="number" name="ha_powerflow_options[weather_font_size]" value="<?php echo (int)($o['weather_font_size'] ?? 13); ?>" class="small-text" /> px</td></tr>
                            <tr><th>Position</th><td><?php ha_pf_xy( $o, 'weather_x', 'weather_y', 500, 80 ); ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ── Section 4: Custom HUD ────────────────────────────────── -->
            <div class="ha-pf-columns" style="margin-top:32px;">
                <div class="ha-pf-col ha-pf-col-full">
                    <div class="ha-pf-card">
                        <h2>✨ Additional HUD Entities</h2>
                        <p class="description">Add extra sensors to your HUD. They will inherit Title and Power text colors.</p>
                        
                        <table class="wp-list-table widefat fixed striped" id="ha-pf-custom-entities-table">
                            <thead>
                                <tr>
                                    <th>Label</th>
                                    <th>Entity ID</th>
                                    <th style="width:180px;">Position (X, Y)</th>
                                    <th style="width:80px;">Visible</th>
                                    <th style="width:50px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $custom = $o['custom_entities'] ?? [];
                                if ( ! is_array( $custom ) ) $custom = [];
                                foreach ( $custom as $index => $item ) { ?>
                                <tr data-index="<?php echo $index; ?>">
                                    <td><input type="text" name="ha_powerflow_options[custom_entities][<?php echo $index; ?>][label]" value="<?php echo esc_attr( $item['label'] ?? '' ); ?>" class="widefat" placeholder="e.g. Temp" /></td>
                                    <td><input type="text" name="ha_powerflow_options[custom_entities][<?php echo $index; ?>][entity]" value="<?php echo esc_attr( $item['entity'] ?? '' ); ?>" class="widefat" placeholder="sensor.xyz" /></td>
                                    <td>
                                        <div class="ha-pf-xy-group" style="display:flex;align-items:center;gap:5px;">
                                            <input type="number" name="ha_powerflow_options[custom_entities][<?php echo $index; ?>][x]" value="<?php echo esc_attr( $item['x'] ?? 0 ); ?>" class="small-text" min="0" max="1000" />
                                            <input type="number" name="ha_powerflow_options[custom_entities][<?php echo $index; ?>][y]" value="<?php echo esc_attr( $item['y'] ?? 0 ); ?>" class="small-text" min="0" max="700" />
                                            <button type="button" class="ha-pf-coord-picker-btn" title="Pick position from image">🎯</button>
                                        </div>
                                    </td>
                                    <td>
                                        <label class="ha-pf-toggle-label ha-pf-toggle-sm">
                                            <input type="checkbox" name="ha_powerflow_options[custom_entities][<?php echo $index; ?>][visible]" value="1" <?php checked( ! empty( $item['visible'] ) ); ?>/>
                                            <span class="ha-pf-slider"></span>
                                        </label>
                                    </td>
                                    <td><button type="button" class="button ha-pf-remove-entity">×</button></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                        <div style="margin-top:15px;">
                            <button type="button" class="button" id="ha-pf-add-entity">+ Add Entity</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Section 5: Maintenance ────────────────────────────────── -->
            <div class="ha-pf-columns" style="margin-top:32px;">
                <div class="ha-pf-col ha-pf-col-left">
                    <div class="ha-pf-card">
                        <h2>🔄 Snapshots & Restore</h2>
                        <div class="ha-pf-restore-box">
                            <p class="description">Automatic snapshots (last 50 kept).</p>
                            <div style="display:flex; gap:10px; margin-bottom:15px;">
                                <select id="ha-pf-snapshot-select" style="flex:1;">
                                    <?php
                                    $files = glob( HA_POWERFLOW_CONFIG_DIR . 'snapshot_*.yaml' );
                                    if ( $files ) {
                                        rsort( $files );
                                        foreach ( $files as $f ) {
                                            $bn = basename( $f );
                                            echo '<option value="' . esc_attr( $bn ) . '">' . esc_html( str_replace(['snapshot_', '.yaml', '_'], ['', '', ' '], $bn) ) . '</option>';
                                        }
                                    } else { echo '<option value="">No snapshots found</option>'; }
                                    ?>
                                </select>
                                <button type="button" id="ha-pf-restore-btn" class="button">Restore</button>
                                <button type="button" id="ha-pf-snapshot-btn" class="button">Take Snapshot Now</button>
                            </div>
                            <hr>
                            <p class="description">Upload .yaml backup.</p>
                            <div style="display:flex; gap:10px; align-items:center;">
                                <input type="file" id="ha-pf-upload-file" accept=".yaml" />
                                <button type="button" id="ha-pf-upload-btn" class="button">Upload & Restore</button>
                            </div>
                        </div>
                    </div>

                    <div class="ha-pf-card" style="margin-top:24px;">
                        <h2>📡 Connection Diagnostics</h2>
                        <div id="ha-pf-diag-panel" style="font-size:13px; line-height:1.6;">
                            <p><strong>Status:</strong> <span id="ha-pf-diag-status">Checking...</span></p>
                            <p><strong>Last Response:</strong> <span id="ha-pf-diag-time">—</span></p>
                            <div id="ha-pf-diag-log" style="margin-top:10px; font-family:monospace; background:#1a202c; color:#a0aec0; padding:12px; border-radius:8px; height:150px; overflow-y:auto;">
                                [System Ready]
                            </div>
                        </div>
                        <button type="button" id="ha-pf-diag-refresh" class="button" style="margin-top:10px;">Refresh Diagnostics</button>
                    </div>
                </div>
            </div>

            <div class="ha-pf-sticky-save-bar">
                <div style="display:flex; align-items:center; gap:20px;">
                    <label class="ha-pf-toggle-label" style="padding: 0;">
                        <input type="checkbox" name="ha_powerflow_options[debug]"
                               value="1" <?php checked( '1', $o['debug'] ?? '' ); ?>/>
                        <span class="ha-pf-slider"></span>
                        <span class="ha-pf-toggle-text" style="font-size: 14px;">🐛 Debug Mode</span>
                    </label>
                    <div style="width:1px; height:24px; background: rgba(0,0,0,0.1);"></div>
                    <?php submit_button( 'Save Settings', 'primary', 'submit', false ); ?>
                </div>
            </div>
        </form>

        <hr>
        <h2>Quick Start</h2>
        <ol style="line-height:2">
            <li>Fill in <strong>Connection</strong> details and <strong>Sensor</strong> entity IDs.</li>
            <li>Upload your background image and paste its URL under <strong>Appearance</strong>.</li>
            <li>Tick <strong>Debug Mode</strong>, save, visit the page with <code>[ha_powerflow]</code>, and click to find coordinates.</li>
            <li>Set your <strong>Grid Line</strong>, <strong>Label</strong> and <strong>Status</strong> positions, then untick Debug Mode.</li>
            <li>Enable optional modules to reveal the <strong>House Line</strong> and each module's settings panel.</li>
        </ol>
        <p>Shortcode: <code>[ha_powerflow]</code></p>
    </div>
    <?php
}
