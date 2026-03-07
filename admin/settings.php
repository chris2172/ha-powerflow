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
        'grid_power'      => sanitize_text_field( $input['grid_power']      ?? '' ),
        'load_power'      => sanitize_text_field( $input['load_power']      ?? '' ),
        'grid_energy'     => sanitize_text_field( $input['grid_energy']     ?? '' ),
        'load_energy'     => sanitize_text_field( $input['load_energy']     ?? '' ),
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
        'debug'           => ! empty( $input['debug'] ) ? '1' : '',
    ];
}

// ── Helpers ────────────────────────────────────────────────────────────────
function ha_pf_xy( $o, $kx, $ky, $dx, $dy, $desc = '' ) { ?>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <label style="font-weight:600;min-width:18px;">X</label>
        <input type="number" name="ha_powerflow_options[<?php echo $kx; ?>]"
               value="<?php echo esc_attr( $o[$kx] ?? $dx ); ?>"
               min="0" max="1000" step="1" style="width:90px;"/>
        <label style="font-weight:600;min-width:18px;">Y</label>
        <input type="number" name="ha_powerflow_options[<?php echo $ky; ?>]"
               value="<?php echo esc_attr( $o[$ky] ?? $dy ); ?>"
               min="0" max="700" step="1" style="width:90px;"/>
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
           ! empty( $o['enable_heatpump'] );
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

        <form method="post" action="options.php">
            <?php settings_fields( 'ha_powerflow_group' ); ?>

            <!-- ═══ MODULE TOGGLES ════════════════════════════════════════ -->
            <div class="ha-pf-toggles-bar">
                <span class="ha-pf-toggles-title">Optional Modules</span>
                <?php
                $modules = [
                    'solar'    => 'Solar',
                    'battery'  => 'Battery',
                    'ev'       => 'EV',
                    'heatpump' => 'Heat Pump',
                ];
                foreach ( $modules as $key => $label ) :
                    $checked = ! empty( $o[ 'enable_' . $key ] ); ?>
                <label class="ha-pf-toggle-label">
                    <input type="checkbox"
                           id="ha-pf-toggle-<?php echo $key; ?>"
                           name="ha_powerflow_options[enable_<?php echo $key; ?>]"
                           value="1" <?php checked( $checked ); ?>/>
                    <span class="ha-pf-slider"></span>
                    <span class="ha-pf-toggle-text"><?php echo $label; ?></span>
                </label>
                <?php endforeach; ?>
            </div>

            <!-- ═══ TWO-COLUMN LAYOUT ═════════════════════════════════════ -->
            <div class="ha-pf-columns">

                <!-- ── LEFT: mandatory settings ─────────────────────────── -->
                <div class="ha-pf-col ha-pf-col-left">

                    <div class="ha-pf-card">
                        <h2>🔌 Connection</h2>
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
                                    <p class="description">
                                        How often the widget polls Home Assistant for new data.<br>
                                        <strong>10 seconds</strong> is a good balance — frequent enough to feel live without hammering your HA instance.
                                        Use 5 s only if you need near-real-time monitoring; use 30 s or more if your HA is on a slow connection or remote server.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="ha-pf-card">
                        <h2>📡 Sensors</h2>
                        <table class="form-table form-table-sm" role="presentation">
                            <?php ha_pf_entity( $o, 'grid_power',  'Grid Power Entity',  'sensor.grid_power',  'Positive = import, negative = export.' ); ?>
                            <?php ha_pf_entity( $o, 'load_power',  'House Power Entity',  'sensor.load_power'  ); ?>
                            <?php ha_pf_entity( $o, 'grid_energy', 'Grid Energy Entity', 'sensor.grid_energy' ); ?>
                            <?php ha_pf_entity( $o, 'load_energy', 'House Energy Entity', 'sensor.load_energy' ); ?>
                        </table>
                    </div>

                    <div class="ha-pf-card">
                        <h2>🎨 Appearance</h2>
                        <table class="form-table form-table-sm" role="presentation">
                            <tr>
                                <th><label for="bg_image">Background Image URL</label></th>
                                <td>
                                    <div style="display:flex;gap:8px;align-items:center;">
                                        <input type="url" id="bg_image" name="ha_powerflow_options[bg_image]"
                                               value="<?php echo esc_attr( $o['bg_image'] ?? '' ); ?>"
                                               class="widefat" placeholder="https://yoursite.com/wp-content/uploads/ha-powerflow/bg.jpg"
                                               style="flex:1;"/>
                                        <button type="button" id="ha-pf-media-btn" class="button">
                                            📁 Select Image
                                        </button>
                                    </div>
                                    <p class="description">
                                        Canvas is <strong>1000 × 700 px</strong>. Leave blank to use the default background.<br>
                                        Images uploaded via <em>Select Image</em> are saved to <code>/wp-content/uploads/ha-powerflow/</code>.
                                    </p>
                                    <div id="ha-pf-img-preview" style="margin-top:10px;<?php echo empty( $o['bg_image'] ) ? 'display:none;' : ''; ?>">
                                        <img src="<?php echo esc_url( $o['bg_image'] ?? '' ); ?>"
                                             style="max-width:260px;max-height:150px;border:1px solid #ddd;border-radius:4px;display:block;"/>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="line_color">Global Line &amp; Dot Colour</label></th>
                                <td>
                                    <input type="text" id="line_color" name="ha_powerflow_options[line_color]"
                                           value="<?php echo esc_attr( $color ); ?>"
                                           class="ha-pf-color-picker" data-default-color="#4a90d9" />
                                </td>
                            </tr>
                            <tr>
                                <th><label for="grid_color">↳ Grid Line</label></th>
                                <td>
                                    <input type="text" id="grid_color" name="ha_powerflow_options[grid_color]"
                                        value="<?php echo esc_attr( $o['grid_color'] ?? '' ); ?>"
                                        class="ha-pf-color-picker" data-default-color="<?php echo esc_attr( $o['line_color'] ?? '#4a90d9' ); ?>" />
                                    <span class="description" style="margin-left: 10px;">Unique color (leave blank for default)</span>
                                </td>
                            </tr>
                            <tr id="ha-pf-load-color-row" <?php if ( ! $load_visible ) echo 'style="display:none;"'; ?>>
                                <th><label for="load_color">↳ House Line</label></th>
                                <td>
                                    <input type="text" id="load_color" name="ha_powerflow_options[load_color]"
                                        value="<?php echo esc_attr( $o['load_color'] ?? '' ); ?>"
                                        class="ha-pf-color-picker" data-default-color="<?php echo esc_attr( $o['line_color'] ?? '#4a90d9' ); ?>" />
                                    <span class="description" style="margin-left: 10px;">Unique color (leave blank for default)</span>
                                </td>
                            </tr>
                            <tr id="ha-pf-pv-color-row" <?php if ( empty( $o['enable_solar'] ) ) echo 'style="display:none;"'; ?>>
                                <th><label for="pv_color">↳ PV Line</label></th>
                                <td>
                                    <input type="text" id="pv_color" name="ha_powerflow_options[pv_color]"
                                        value="<?php echo esc_attr( $o['pv_color'] ?? '' ); ?>"
                                        class="ha-pf-color-picker" data-default-color="<?php echo esc_attr( $o['line_color'] ?? '#4a90d9' ); ?>" />
                                    <span class="description" style="margin-left: 10px;">Unique color (leave blank for default)</span>
                                </td>
                            </tr>
                            <tr id="ha-pf-battery-color-row" <?php if ( empty( $o['enable_battery'] ) ) echo 'style="display:none;"'; ?>>
                                <th><label for="battery_color">↳ Battery Line</label></th>
                                <td>
                                    <input type="text" id="battery_color" name="ha_powerflow_options[battery_color]"
                                        value="<?php echo esc_attr( $o['battery_color'] ?? '' ); ?>"
                                        class="ha-pf-color-picker" data-default-color="<?php echo esc_attr( $o['line_color'] ?? '#4a90d9' ); ?>" />
                                    <span class="description" style="margin-left: 10px;">Unique color (leave blank for default)</span>
                                </td>
                            </tr>
                            <tr id="ha-pf-ev-color-row" <?php if ( empty( $o['enable_ev'] ) ) echo 'style="display:none;"'; ?>>
                                <th><label for="ev_color">↳ EV Line</label></th>
                                <td>
                                    <input type="text" id="ev_color" name="ha_powerflow_options[ev_color]"
                                        value="<?php echo esc_attr( $o['ev_color'] ?? '' ); ?>"
                                        class="ha-pf-color-picker" data-default-color="<?php echo esc_attr( $o['line_color'] ?? '#4a90d9' ); ?>" />
                                    <span class="description" style="margin-left: 10px;">Unique color (leave blank for default)</span>
                                </td>
                            </tr>
                            <tr id="ha-pf-heatpump-color-row" <?php if ( empty( $o['enable_heatpump'] ) ) echo 'style="display:none;"'; ?>>
                                <th><label for="heatpump_color">↳ Heat Pump Line</label></th>
                                <td>
                                    <input type="text" id="heatpump_color" name="ha_powerflow_options[heatpump_color]"
                                        value="<?php echo esc_attr( $o['heatpump_color'] ?? '' ); ?>"
                                        class="ha-pf-color-picker" data-default-color="<?php echo esc_attr( $o['line_color'] ?? '#4a90d9' ); ?>" />
                                    <span class="description" style="margin-left: 10px;">Unique color (leave blank for default)</span>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="line_opacity">Opacity</label></th>
                                <td>
                                    <div style="display:flex;align-items:center;gap:12px;">
                                        <input type="range" id="line_opacity" name="ha_powerflow_options[line_opacity]"
                                               min="0" max="1" step="0.05"
                                               value="<?php echo esc_attr( $opacity ); ?>"
                                               style="width:160px;"
                                               oninput="document.getElementById('ha-pf-opval').textContent=parseFloat(this.value).toFixed(2)"/>
                                        <span id="ha-pf-opval" style="font-weight:600;"><?php echo number_format( $opacity, 2 ); ?></span>
                                    </div>
                                    <p class="description">0 = transparent &nbsp;|&nbsp; 1 = opaque</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Line Paths ──────────────────────────────────────── -->
                    <div class="ha-pf-card">
                        <h2>📐 Line Paths</h2>
                        <table class="form-table form-table-sm" role="presentation">

                            <tr>
                                <th><label for="grid_line">Grid Line</label></th>
                                <td>
                                    <input type="text" id="grid_line" name="ha_powerflow_options[grid_line]"
                                           value="<?php echo esc_attr( $o['grid_line'] ?? '' ); ?>"
                                           class="widefat" placeholder="M 120,350 L 500,350"/>
                                    <p class="description" style="margin-top: 10px;">Runs from <strong>Grid → Home</strong> (or to Inverter if Solar/Battery active).</p>
                                </td>
                            </tr>

                            <!-- House Line — hidden until a module is enabled -->
                            <tr id="ha-pf-load-line-row" <?php if ( ! $load_visible ) echo 'style="display:none;"'; ?>>
                                <th>
                                    <label for="load_line">House Line</label>
                                    <span class="ha-pf-module-status" style="display:block;margin-top:6px;text-align:center;">Modules active</span>
                                </th>
                                <td>
                                    <input type="text" id="load_line" name="ha_powerflow_options[load_line]"
                                           value="<?php echo esc_attr( $o['load_line'] ?? '' ); ?>"
                                           class="widefat" placeholder="M 500,350 L 880,350"/>
                                    <p class="description" style="margin-top: 10px;">
                                        Visible only when Solar, Battery, or EV are enabled.<br>
                                        Runs from <strong>Inverter → House</strong>.
                                    </p>
                                </td>
                            </tr>

                            <!-- PV Line — hidden until Solar module is enabled -->
                            <tr id="ha-pf-pv-line-row" <?php if ( empty( $o['enable_solar'] ) ) echo 'style="display:none;"'; ?>>
                                <th>
                                    <label for="pv_line">PV Line</label>
                                    <span class="ha-pf-module-status" style="display:block;margin-top:6px;text-align:center;">Solar active</span>
                                </th>
                                <td>
                                    <input type="text" id="pv_line" name="ha_powerflow_options[pv_line]"
                                           value="<?php echo esc_attr( $o['pv_line'] ?? '' ); ?>"
                                           class="widefat" placeholder="M 500,150 L 500,350"/>
                                    <p class="description">
                                        Visible only when Solar is enabled. Runs from <strong>Solar → Inverter</strong>.<br>
                                        Set the end point to match the midpoint of your Grid Line.
                                    </p>
                                </td>
                            </tr>

                            <!-- Battery Line — hidden until Battery module is enabled -->
                            <tr id="ha-pf-battery-line-row" <?php if ( empty( $o['enable_battery'] ) ) echo 'style="display:none;"'; ?>>
                                <th>
                                    <label for="battery_line">Battery Line</label>
                                    <span class="ha-pf-module-status" style="display:block;margin-top:6px;text-align:center;">Battery active</span>
                                </th>
                                <td>
                                    <input type="text" id="battery_line" name="ha_powerflow_options[battery_line]"
                                           value="<?php echo esc_attr( $o['battery_line'] ?? '' ); ?>"
                                           class="widefat" placeholder="M 500,350 L 500,550"/>
                                    <p class="description" style="margin-top: 10px;">
                                        Visible only when Battery is enabled. Runs from <strong>Inverter ↔ Battery</strong>.<br>
                                        Direction reverses automatically based on charge / discharge state.
                                    </p>
                                </td>
                            </tr>

                            <!-- EV Line — hidden until EV module is enabled -->
                            <tr id="ha-pf-ev-line-row" <?php if ( empty( $o['enable_ev'] ) ) echo 'style="display:none;"'; ?>>
                                <th>
                                    <label for="ev_line">EV Line</label>
                                    <span class="ha-pf-module-status" style="display:block;margin-top:6px;text-align:center;">EV active</span>
                                </th>
                                <td>
                                    <input type="text" id="ev_line" name="ha_powerflow_options[ev_line]"
                                           value="<?php echo esc_attr( $o['ev_line'] ?? '' ); ?>"
                                           class="widefat" placeholder="M 750,350 L 750,550"/>
                                    <p class="description" style="margin-top: 10px;">
                                        Visible only when EV is enabled. Runs from <strong>Inverter → EV</strong>.
                                    </p>
                                </td>
                            </tr>

                            <!-- Heat Pump Line — hidden until Heat Pump module is enabled -->
                            <tr id="ha-pf-heatpump-line-row" <?php if ( empty( $o['enable_heatpump'] ) ) echo 'style="display:none;"'; ?>>
                                <th>
                                    <label for="heatpump_line">Heat Pump Line</label>
                                    <span class="ha-pf-module-status" style="display:block;margin-top:6px;text-align:center;">Heat Pump active</span>
                                </th>
                                <td>
                                    <input type="text" id="heatpump_line" name="ha_powerflow_options[heatpump_line]"
                                           value="<?php echo esc_attr( $o['heatpump_line'] ?? '' ); ?>"
                                           class="widefat" placeholder="M 250,350 L 250,550"/>
                                    <p class="description" style="margin-top: 10px;">
                                        Visible only when Heat Pump is enabled. Runs from <strong>Inverter → Heat Pump</strong>.
                                    </p>
                                </td>
                            </tr>

                        </table>
                        <div class="ha-pf-hint">
                            <strong>Path commands:</strong>
                            <code>M x,y</code> start &nbsp;·&nbsp; <code>L x,y</code> segment — repeat <code>L</code> for each bend.<br>
                            Canvas is <strong>1000 × 700</strong>. Leave blank to use the defaults shown in each field.<br>
                            Example with two bends: <code>M 120,350 L 500,350 L 500,150 L 880,150</code>
                        </div>
                    </div>

                    <!-- Label & Status Positions ─────────────────────────── -->
                    <div class="ha-pf-card">
                        <h2>🏷 Label &amp; Status Positions &amp; Colors</h2>
                        <p class="description" style="margin:0 0 12px;">
                            Customize text colors for the labels below (useful if your background image clashes).
                        </p>
                        <table class="form-table form-table-sm" role="presentation" style="margin-bottom: 20px;">
                            <tr>
                                <th><label for="title_color">Title Text Color</label></th>
                                <td>
                                    <input type="text" id="title_color" name="ha_powerflow_options[title_color]"
                                           value="<?php echo esc_attr( $o['title_color'] ?? '#8899bb' ); ?>"
                                           class="ha-pf-color-picker" data-default-color="#8899bb" />
                                    <p class="description">Color for "GRID", "HOME", etc.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="power_color">Power Text Color</label></th>
                                <td>
                                    <input type="text" id="power_color" name="ha_powerflow_options[power_color]"
                                           value="<?php echo esc_attr( $o['power_color'] ?? '#f0a500' ); ?>"
                                           class="ha-pf-color-picker" data-default-color="#f0a500" />
                                    <p class="description">Color for live power (W/kW).</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="energy_color">Energy/SOC Text Color</label></th>
                                <td>
                                    <input type="text" id="energy_color" name="ha_powerflow_options[energy_color]"
                                           value="<?php echo esc_attr( $o['energy_color'] ?? '#6677aa' ); ?>"
                                           class="ha-pf-color-picker" data-default-color="#6677aa" />
                                    <p class="description">Color for total energy (kWh) or battery/EV %. </p>
                                </td>
                            </tr>
                        </table>

                        <p class="description" style="margin:0 0 12px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
                            All positions are centre-point (x, y) coordinates on the 1000 × 700 canvas.
                            Use <strong>Debug Mode</strong> to click your image and find exact values.
                        </p>
                        <table class="form-table form-table-sm" role="presentation">
                            <tr>
                                <th>Grid Label</th>
                                <td><?php ha_pf_xy( $o, 'grid_label_x', 'grid_label_y', 120, 260, 'GRID name · power · energy block. Default X&nbsp;<code>120</code> Y&nbsp;<code>260</code>' ); ?></td>
                            </tr>
                            <tr>
                                <th>Load Label</th>
                                <td><?php ha_pf_xy( $o, 'load_label_x', 'load_label_y', 880, 260, 'HOME name · power · energy block. Default X&nbsp;<code>880</code> Y&nbsp;<code>260</code>' ); ?></td>
                            </tr>
                            <tr id="ha-pf-pv-label-row" <?php if ( empty( $o['enable_solar'] ) ) echo 'style="display:none;"'; ?>>
                                <th>PV Label</th>
                                <td><?php ha_pf_xy( $o, 'pv_label_x', 'pv_label_y', 500, 150, 'SOLAR name · power · energy block. Default X&nbsp;<code>500</code> Y&nbsp;<code>150</code>' ); ?></td>
                            </tr>
                            <tr id="ha-pf-battery-label-row" <?php if ( empty( $o['enable_battery'] ) ) echo 'style="display:none;"'; ?>>
                                <th>Battery Label</th>
                                <td><?php ha_pf_xy( $o, 'battery_label_x', 'battery_label_y', 500, 550, 'BATTERY name · power · SOC block. Default X&nbsp;<code>500</code> Y&nbsp;<code>550</code>' ); ?></td>
                            </tr>
                            <tr id="ha-pf-ev-label-row" <?php if ( empty( $o['enable_ev'] ) ) echo 'style="display:none;"'; ?>>
                                <th>EV Label</th>
                                <td><?php ha_pf_xy( $o, 'ev_label_x', 'ev_label_y', 750, 550, 'EV name · power · SOC block. Default X&nbsp;<code>750</code> Y&nbsp;<code>550</code>' ); ?></td>
                            </tr>
                            <tr id="ha-pf-heatpump-label-row" <?php if ( empty( $o['enable_heatpump'] ) ) echo 'style="display:none;"'; ?>>
                                <th>Heat Pump Label</th>
                                <td><?php ha_pf_xy( $o, 'heatpump_label_x', 'heatpump_label_y', 250, 550, 'HEAT PUMP name · power · efficiency block. Default X&nbsp;<code>250</code> Y&nbsp;<code>550</code>' ); ?></td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td><?php ha_pf_xy( $o, 'status_x', 'status_y', 500, 320, 'IMPORTING / EXPORTING / No flow label. Default X&nbsp;<code>500</code> Y&nbsp;<code>320</code>' ); ?></td>
                            </tr>
                        </table>
                    </div>


                </div><!-- /left column -->

                <!-- ── RIGHT: optional module sections ──────────────────── -->
                <div class="ha-pf-col ha-pf-col-right">

                    <div id="ha-pf-section-solar" class="ha-pf-card ha-pf-module-card" data-module="solar" style="display:none;">
                        <h2>
                            <span class="ha-pf-module-badge ha-pf-badge-solar">☀️ Solar</span>
                            <span class="ha-pf-module-status">Enabled</span>
                        </h2>
                        <p class="description" style="margin:0 0 12px;">Entity IDs for your solar PV system.</p>
                        <table class="form-table form-table-sm" role="presentation">
                            <?php ha_pf_entity( $o, 'pv_power',  'PV Power Entity',  'sensor.pv_power',  'Current solar power output (W / kW).' ); ?>
                            <?php ha_pf_entity( $o, 'pv_energy', 'PV Energy Entity', 'sensor.pv_energy', 'Total solar energy produced today (kWh).' ); ?>
                        </table>
                    </div>

                    <div id="ha-pf-section-battery" class="ha-pf-card ha-pf-module-card" data-module="battery" style="display:none;">
                        <h2>
                            <span class="ha-pf-module-badge ha-pf-badge-battery">🔋 Battery</span>
                            <span class="ha-pf-module-status">Enabled</span>
                        </h2>
                        <p class="description" style="margin:0 0 12px;">Entity IDs for your battery storage system.</p>
                        <table class="form-table form-table-sm" role="presentation">
                            <?php ha_pf_entity( $o, 'battery_power',      'Battery Power Entity',        'sensor.battery_power',      'Current charge/discharge power (W / kW). Positive = charging, negative = discharging.' ); ?>
                            <?php ha_pf_entity( $o, 'battery_in_energy',  'Battery Energy In Entity',    'sensor.battery_in_energy',  'Total energy charged into battery today (kWh).' ); ?>
                            <?php ha_pf_entity( $o, 'battery_out_energy', 'Battery Energy Out Entity',   'sensor.battery_out_energy', 'Total energy discharged from battery today (kWh).' ); ?>
                            <?php ha_pf_entity( $o, 'battery_soc',        'Battery SOC Entity',          'sensor.battery_soc',        'State of charge (%). Displayed as a percentage.' ); ?>
                        </table>
                    </div>

                    <div id="ha-pf-section-ev" class="ha-pf-card ha-pf-module-card" data-module="ev" style="display:none;">
                        <h2>
                            <span class="ha-pf-module-badge ha-pf-badge-ev">🚗 EV</span>
                            <span class="ha-pf-module-status">Enabled</span>
                        </h2>
                        <p class="description" style="margin:0 0 12px;">Entity IDs for your electric vehicle charger.</p>
                        <table class="form-table form-table-sm" role="presentation">
                            <?php ha_pf_entity( $o, 'ev_power', 'EV Power Entity', 'sensor.ev_power', 'Current EV charging power (W / kW).' ); ?>
                            <?php ha_pf_entity( $o, 'ev_soc',   'EV SOC Entity',   'sensor.ev_soc',   'EV battery state of charge (%).' ); ?>
                        </table>
                    </div>

                    <div id="ha-pf-section-heatpump" class="ha-pf-card ha-pf-module-card" data-module="heatpump" style="display:none;">
                        <h2>
                            <span class="ha-pf-module-badge ha-pf-badge-heatpump">♨️ Heat Pump</span>
                            <span class="ha-pf-module-status">Enabled</span>
                        </h2>
                        <p class="description" style="margin:0 0 12px;">Entity IDs for your heat pump.</p>
                        <table class="form-table form-table-sm" role="presentation">
                            <?php ha_pf_entity( $o, 'heatpump_power',      'Heat Pump Power Entity',      'sensor.heat_pump_power',            'Current heat pump power consumption (W / kW).' ); ?>
                            <?php ha_pf_entity( $o, 'heatpump_energy',     'Heat Pump Energy Entity',     'sensor.heat_pump_energy',           'Total heat pump energy consumed today (kWh).' ); ?>
                            <?php ha_pf_entity( $o, 'heatpump_efficiency', 'Heat Pump Efficiency Entity', 'sensor.heat_pump_efficiency_energy', 'Heat pump COP / efficiency (e.g. 3.5).' ); ?>
                        </table>
                    </div>

                    <div class="ha-pf-card ha-pf-placeholder" id="ha-pf-no-modules">
                        <p>Enable one or more <strong>Optional Modules</strong> above to see their settings here.</p>
                    </div>

                </div><!-- /right column -->

            </div><!-- /columns -->

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
