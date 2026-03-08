<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', 'ha_powerflow_admin_menu' );
function ha_powerflow_admin_menu() {
    $hook = add_menu_page(
        'HA Powerflow Settings',
        'HA Powerflow',
        'manage_options',
        'ha-powerflow',
        'ha_powerflow_settings_page',
        'dashicons-chart-area'
    );
    add_submenu_page(
        'ha-powerflow',
        'HA Powerflow Settings',
        'HA Powerflow',
        'manage_options',
        'ha-powerflow',
        'ha_powerflow_settings_page'
    );

    // Guarantee that the global $title is never null before admin-header.php renders it.
    add_action( "load-$hook", function() {
        global $title;
        if ( $title === null ) {
            $title = 'HA Powerflow Settings';
        }
    });
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
    // Enqueue frontend styles for the preview
    wp_enqueue_style( 'ha-powerflow-style', HA_POWERFLOW_URL . 'assets/css/style.css', [], HA_POWERFLOW_VERSION );
    wp_enqueue_script( 'ha-powerflow-admin', HA_POWERFLOW_URL . 'assets/js/admin.js', [ 'wp-color-picker', 'jquery' ], HA_POWERFLOW_VERSION, true );

    $upload_dir = wp_upload_dir();
    $all_modules = HA_Powerflow_Modules::get_all();
    $localized_modules = [];
    foreach ( $all_modules as $key => $m ) {
        if ( ! empty($m['is_weather']) ) continue;
        $localized_modules[$key] = [
            'prefix' => $m['id_prefix'],
            'label'  => $m['label']
        ];
    }

    wp_localize_script( 'ha-powerflow-admin', 'haPfAdmin', [
        'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
        'nonce'         => wp_create_nonce( 'ha_pf_test_connection' ),
        'uploadDirUrl'  => $upload_dir['baseurl'] . '/ha-powerflow/',
        'selectImage'   => __( 'Select Image', 'ha-powerflow' ),
        'useImage'      => __( 'Use this image', 'ha-powerflow' ),
        'defaultBg'     => HA_POWERFLOW_URL . 'assets/images/ha-powerflow.webp',
    ] );

    // Also localize haPowerflow object for the preview logic in admin.js
    wp_localize_script( 'ha-powerflow-admin', 'haPowerflow', [
        'modules' => $localized_modules
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

    $output = [
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
        'bg_image'            => esc_url_raw(        $input['bg_image']             ?? '' ),
        'grid_line'           => preg_replace( '/[^MmLlHhVvCcSsQqTtAaZz\d\s,.\-+]/', '', $input['grid_line']         ?? '' ),
        'load_line'           => preg_replace( '/[^MmLlHhVvCcSsQqTtAaZz\d\s,.\-+]/', '', $input['load_line']         ?? '' ),
        'line_color'          => $color,
        'grid_color'          => $grid_color,
        'load_color'          => $load_color,
        'line_opacity'        => $opacity,
        'title_color'         => $title_color,
        'power_color'         => $power_color,
        'energy_color'        => $energy_color,
        'grid_label_x'        => (int) ( $input['grid_label_x']   ?? 120 ),
        'grid_label_y'        => (int) ( $input['grid_label_y']   ?? 260 ),
        'load_label_x'        => (int) ( $input['load_label_x']   ?? 880 ),
        'load_label_y'        => (int) ( $input['load_label_y']   ?? 260 ),
        'status_x'            => (int) ( $input['status_x']       ?? 500 ),
        'status_y'            => (int) ( $input['status_y']       ?? 320 ),
        'debug'               => ! empty( $input['debug'] ) ? '1' : '',
    ];

    $all_modules = HA_Powerflow_Modules::get_all();
    foreach ( $all_modules as $key => $m ) {
        $prefix = $m['id_prefix'];
        $output['enable_' . $key] = ! empty( $input['enable_' . $key] ) ? '1' : '';
        
        if ( ! empty( $m['is_weather'] ) ) {
            $output['weather_entity']    = sanitize_text_field( $input['weather_entity']  ?? '' );
            $output['weather_x']         = (int) ( $input['weather_x']       ?? 500 );
            $output['weather_y']         = (int) ( $input['weather_y']       ?? 80 );
            $output['weather_font_size'] = (int) ( $input['weather_font_size'] ?? 13 );
            continue;
        }

        $output[$prefix . '_power']   = sanitize_text_field( $input[$prefix . '_power']  ?? '' );
        $output[$prefix . '_line']    = preg_replace( '/[^MmLlHhVvCcSsQqTtAaZz\d\s,.\-+]/', '', $input[$prefix . '_line']  ?? '' );
        $output[$prefix . '_color']   = sanitize_hex_color( $input[$prefix . '_color']  ?? '' ) ?: '';
        $output[$prefix . '_label_x'] = (int) ( $input[$prefix . '_label_x'] ?? $m['default_pos']['x'] );
        $output[$prefix . '_label_y'] = (int) ( $input[$prefix . '_label_y'] ?? $m['default_pos']['y'] );

        if ( ! empty( $m['has_energy'] ) ) {
            $output[$prefix . '_energy'] = sanitize_text_field( $input[$prefix . '_energy'] ?? '' );
        }
        if ( ! empty( $m['has_soc'] ) ) {
            $output[$prefix . '_soc'] = sanitize_text_field( $input[$prefix . '_soc'] ?? '' );
        }
        if ( ! empty( $m['has_eff'] ) ) {
            $output[$prefix . '_efficiency'] = sanitize_text_field( $input[$prefix . '_efficiency'] ?? '' );
        }
        if ( isset( $m['default_capacity'] ) ) {
            $output[$prefix . '_max_capacity'] = (int) ( $input[$prefix . '_max_capacity'] ?? $m['default_capacity'] );
        }

        // Special battery split energy
        if ( $key === 'battery' ) {
            $output['battery_in_energy']  = sanitize_text_field( $input['battery_in_energy']  ?? '' );
            $output['battery_out_energy'] = sanitize_text_field( $input['battery_out_energy'] ?? '' );
        }
    }
    $output['custom_entities'] = ! empty( $input['custom_entities'] ) && is_array( $input['custom_entities'] ) ? array_map( function( $item ) {
        return [
            'label'   => sanitize_text_field( $item['label']  ?? '' ),
            'entity'  => sanitize_text_field( $item['entity'] ?? '' ),
            'x'       => (int) ( $item['x'] ?? 0 ),
            'y'       => (int) ( $item['y'] ?? 0 ),
            'visible' => ! empty( $item['visible'] ) ? '1' : '',
        ];
    }, array_values( $input['custom_entities'] ) ) : [];

    $output['grid_max_capacity']  = (int) ( $input['grid_max_capacity'] ?? 10000 );
    $output['house_max_capacity'] = (int) ( $input['house_max_capacity'] ?? 8000 );

    $output['theme_preset'] = sanitize_text_field( $input['theme_preset'] ?? 'custom' );

    return $output;
}

// ── Helpers ────────────────────────────────────────────────────────────────
function ha_pf_xy( $o, $kx, $ky, $dx, $dy, $desc = '' ) { ?>
    <div class="ha-pf-xy-group" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <label style="font-weight:600;min-width:18px;">X</label>
        <input type="number" name="ha_powerflow_options[<?php echo $kx; ?>]"
               value="<?php echo esc_attr( $o[$kx] ?? $dx ); ?>"
               min="0" max="1000" step="1" />
        <label style="font-weight:600;min-width:18px;">Y</label>
        <input type="number" name="ha_powerflow_options[<?php echo $ky; ?>]"
               value="<?php echo esc_attr( $o[$ky] ?? $dy ); ?>"
               min="0" max="700" step="1" />
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
    $modules = HA_Powerflow_Modules::get_all();
    foreach ( $modules as $key => $m ) {
        if ( ! empty( $m['is_weather'] ) ) continue;
        if ( ! empty( $o['enable_' . $key] ) ) return true;
    }
    return false;
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

        <div class="ha-pf-admin-container ha-pf-preview-disabled">
            <div class="ha-pf-settings-column">
                <form method="post" action="options.php" id="ha-pf-settings-form">
            <?php settings_fields( 'ha_powerflow_group' ); ?>

            <div class="ha-pf-toggles-bar">
                <span class="ha-pf-toggles-title">Quick Modules:</span>
                <?php
                $modules = HA_Powerflow_Modules::get_all();
                foreach ( $modules as $key => $m ) :
                    $checked = ! empty( $o[ 'enable_' . $key ] ); ?>
                <label class="ha-pf-toggle-label">
                    <input type="checkbox" id="ha-pf-toggle-<?php echo $key; ?>" name="ha_powerflow_options[enable_<?php echo $key; ?>]" value="1" <?php checked( $checked ); ?>/>
                    <span class="ha-pf-slider"></span>
                    <span class="ha-pf-toggle-text"><?php echo esc_html( $m['label'] ); ?></span>
                </label>
                <?php endforeach; ?>
                <div style="width:1px; height:24px; background:rgba(0,0,0,0.1); margin:0 8px;"></div>
                <label class="ha-pf-toggle-label ha-pf-preview-toggle">
                    <input type="checkbox" id="ha-pf-toggle-preview" />
                    <span class="ha-pf-slider"></span>
                    <span class="ha-pf-toggle-text">Live Preview</span>
                </label>
            </div>

            <nav class="ha-pf-tabs-nav">
                <button type="button" class="ha-pf-tab-btn active" data-tab="connection">🔌 Connection</button>
                <button type="button" class="ha-pf-tab-btn" data-tab="sensors">🔍 Sensors</button>
                <button type="button" class="ha-pf-tab-btn" data-tab="appearance">🎨 Appearance</button>
                <button type="button" class="ha-pf-tab-btn" data-tab="modules">🧩 Modules</button>
                <button type="button" class="ha-pf-tab-btn" data-tab="maintenance">⚙️ Maintenance</button>
            </nav>

            <!-- ── Tab: Connection ────────────────────────── -->
            <div id="ha-pf-tab-connection" class="ha-pf-tab-content active">
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
            </div>

            <!-- ── Tab: Sensors ────────────────────────── -->
            <div id="ha-pf-tab-sensors" class="ha-pf-tab-content">
                <div class="ha-pf-columns">
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
            </div>

            <!-- ── Tab: Appearance ────────────────────────── -->
            <div id="ha-pf-tab-appearance" class="ha-pf-tab-content">
                <div class="ha-pf-columns">
                    <div class="ha-pf-col ha-pf-col-left">
                        <div class="ha-pf-card">
                            <h2>🎨 Global Appearance</h2>
                            <table class="form-table form-table-sm" role="presentation">
                                <tr>
                                    <th><label for="theme_preset">Theme Preset</label></th>
                                    <td>
                                        <?php
                                        $preset = $o['theme_preset'] ?? 'custom';
                                        $presets = [
                                            'custom'         => 'Custom (Manual)',
                                            'cyberpunk'      => 'Cyberpunk (Neon Blues/Pinks)',
                                            'high_contrast'  => 'High-Contrast (Bright/Bold)',
                                            'minimalist'     => 'Minimalist (Soft/Subtle)',
                                            'solaredge'      => 'SolarEdge Style (Green/Black)',
                                            'tesla'          => 'Tesla Style (White/Red/Slate)',
                                            'midnight'       => 'Midnight (Indigo/Amber)',
                                            'forest'         => 'Forest (Organic Greens)',
                                            'sunset'         => 'Sunset (Warm Oranges)',
                                            'matrix'         => 'Matrix (Digital Green)',
                                            'ocean'          => 'Ocean (Deep Teals/Blues)',
                                        ];
                                        ?>
                                        <select id="ha-pf-theme-preset" name="ha_powerflow_options[theme_preset]">
                                            <?php foreach ( $presets as $id => $label ) : ?>
                                                <option value="<?php echo $id; ?>" <?php selected( $preset, $id ); ?>>
                                                    <?php echo esc_html( $label ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="description">Choose a starting preset or customize every detail below.</p>
                                    </td>
                                </tr>
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
                                    <th><label for="grid_color">Grid Line Color</label></th>
                                    <td><input type="text" name="ha_powerflow_options[grid_color]" value="<?php echo esc_attr( $o['grid_color'] ?? '' ); ?>" class="ha-pf-color-picker" /></td>
                                </tr>
                                <tr id="ha-pf-load-color-row">
                                    <th><label for="load_color">House Line Color</label></th>
                                    <td><input type="text" name="ha_powerflow_options[load_color]" value="<?php echo esc_attr( $o['load_color'] ?? '' ); ?>" class="ha-pf-color-picker" /></td>
                                </tr>
                                <tr id="ha-pf-pv-color-row">
                                    <th><label for="pv_color">Solar Line Color</label></th>
                                    <td><input type="text" name="ha_powerflow_options[pv_color]" value="<?php echo esc_attr( $o['pv_color'] ?? '' ); ?>" class="ha-pf-color-picker" /></td>
                                </tr>
                                <tr id="ha-pf-battery-color-row">
                                    <th><label for="battery_color">Battery Line Color</label></th>
                                    <td><input type="text" name="ha_powerflow_options[battery_color]" value="<?php echo esc_attr( $o['battery_color'] ?? '' ); ?>" class="ha-pf-color-picker" /></td>
                                </tr>
                                <tr id="ha-pf-ev-color-row">
                                    <th><label for="ev_color">EV Line Color</label></th>
                                    <td><input type="text" name="ha_powerflow_options[ev_color]" value="<?php echo esc_attr( $o['ev_color'] ?? '' ); ?>" class="ha-pf-color-picker" /></td>
                                </tr>
                                <tr id="ha-pf-heatpump-color-row">
                                    <th><label for="heatpump_color">Heat Pump Line Color</label></th>
                                    <td><input type="text" name="ha_powerflow_options[heatpump_color]" value="<?php echo esc_attr( $o['heatpump_color'] ?? '' ); ?>" class="ha-pf-color-picker" /></td>
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
                            <h2>⚡ Power Limits (Physics 2.0)</h2>
                            <p class="description">Set the peak power (Watts) for each module. These determine "100%" thresholds for animation speed and line resonance.</p>
                            <table class="form-table form-table-sm">
                                <tr>
                                    <th>Grid Limit</th>
                                    <td><input type="number" name="ha_powerflow_options[grid_max_capacity]" value="<?php echo (int)($o['grid_max_capacity'] ?? 10000); ?>" class="small-text" /> W</td>
                                </tr>
                                <tr>
                                    <th>House Limit</th>
                                    <td><input type="number" name="ha_powerflow_options[house_max_capacity]" value="<?php echo (int)($o['house_max_capacity'] ?? 8000); ?>" class="small-text" /> W</td>
                                </tr>
                                <?php
                                $modules = HA_Powerflow_Modules::get_all();
                                foreach ( $modules as $key => $m ) :
                                    if ( ! empty($m['is_weather']) || ! isset($m['default_capacity']) ) continue;
                                    $prefix = $m['id_prefix'];
                                    $enabled = ! empty( $o['enable_' . $key] );
                                    ?>
                                    <tr class="ha-pf-limit-row-<?php echo $key; ?>" <?php echo $enabled ? '' : 'style="display:none;"'; ?>>
                                        <th><?php echo esc_html( $m['label'] ); ?> Limit</th>
                                        <td>
                                            <input type="number" name="ha_powerflow_options[<?php echo $prefix; ?>_max_capacity]" 
                                                   value="<?php echo (int)($o[$prefix . '_max_capacity'] ?? $m['default_capacity']); ?>" 
                                                   class="small-text" /> W
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                            <div class="ha-pf-hint" style="margin-top:10px;">
                                💡 Increasing a limit makes the flow look "slower" for the same amount of power.
                            </div>
                        </div>

                        <div class="ha-pf-card">
                            <h2>📐 Common Paths</h2>
                            <table class="form-table form-table-sm" role="presentation">
                                <tr><th>Grid Line</th><td><input type="text" name="ha_powerflow_options[grid_line]" value="<?php echo esc_attr( $o['grid_line'] ?? '' ); ?>" class="widefat" /></td></tr>
                                <tr id="ha-pf-load-line-row"><th>House Line</th><td><input type="text" name="ha_powerflow_options[load_line]" value="<?php echo esc_attr( $o['load_line'] ?? '' ); ?>" class="widefat" /></td></tr>
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
            </div>

            <!-- ── Tab: Modules ────────────────────────────── -->
            <div id="ha-pf-tab-modules" class="ha-pf-tab-content">
                <div class="ha-pf-columns">
                    <?php
                    $all_modules = HA_Powerflow_Modules::get_all();
                    $keys = array_keys( $all_modules );
                    $half = ceil( count($keys) / 2 );
                    $col_left = array_slice( $keys, 0, $half );
                    $col_right = array_slice( $keys, $half );

                    foreach ( [ 'left' => $col_left, 'right' => $col_right ] as $col => $m_ids ) : ?>
                        <div class="ha_pf-col ha-pf-col-<?php echo $col; ?>">
                            <?php foreach ( $m_ids as $id ) : 
                                $m = $all_modules[$id];
                                if ( ! empty( $m['is_weather'] ) ) continue; // Weather has its own card usually, or we can include it
                                ?>
                                <div id="ha-pf-section-<?php echo $id; ?>" class="ha-pf-card ha-pf-module-card" data-module="<?php echo $id; ?>">
                                    <h2><?php echo $m['icon'] . ' ' . $m['label']; ?></h2>
                                    <table class="form-table form-table-sm">
                                        <?php 
                                        $id_prefix = $m['id_prefix'];
                                        ha_pf_entity( $o, $id_prefix . '_power', 'Power Entity', 'sensor.' . $id_prefix . '_power' );
                                        
                                        if ( ! empty($m['has_energy']) ) {
                                            if ( $id === 'battery' ) {
                                                ha_pf_entity( $o, 'battery_in_energy', 'Energy In', 'sensor.battery_energy_in' );
                                                ha_pf_entity( $o, 'battery_out_energy', 'Energy Out', 'sensor.battery_energy_out' );
                                            } else {
                                                ha_pf_entity( $o, $id_prefix . '_energy', 'Energy Today', 'sensor.' . $id_prefix . '_energy' );
                                            }
                                        }

                                        if ( ! empty($m['has_soc']) ) {
                                            ha_pf_entity( $o, $id_prefix . '_soc', 'SOC Entity', 'sensor.' . $id_prefix . '_soc' );
                                        }

                                        if ( ! empty($m['has_eff']) ) {
                                            ha_pf_entity( $o, $id_prefix . '_efficiency', 'Efficiency (COP)', 'sensor.' . $id_prefix . '_cop' );
                                        }
                                        ?>
                                        <tr id="ha-pf-<?php echo $id_prefix; ?>-line-row">
                                            <th>Path</th>
                                            <td><input type="text" name="ha_powerflow_options[<?php echo $id_prefix; ?>_line]" value="<?php echo esc_attr( $o[$id_prefix . '_line'] ?? '' ); ?>" class="widefat" placeholder="SVG Path..."/></td>
                                        </tr>
                                        <tr id="ha-pf-<?php echo $id_prefix; ?>-label-row">
                                            <th>Position</th>
                                            <td><?php ha_pf_xy( $o, $id_prefix . '_label_x', $id_prefix . '_label_y', $m['default_pos']['x'], $m['default_pos']['y'] ); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            <?php endforeach; ?>

                            <?php if ( $col === 'right' ) : 
                                $wm = $all_modules['weather']; ?>
                                <div id="ha-pf-section-weather" class="ha-pf-card ha-pf-module-card" data-module="weather">
                                    <h2>☁️ Weather</h2>
                                    <table class="form-table form-table-sm">
                                        <?php ha_pf_entity( $o, 'weather_entity', 'Weather Entity', 'weather.home' ); ?>
                                        <tr><th>Font Size</th><td><input type="number" name="ha_powerflow_options[weather_font_size]" value="<?php echo (int)($o['weather_font_size'] ?? 13); ?>" class="small-text" /> px</td></tr>
                                        <tr><th>Position</th><td><?php ha_pf_xy( $o, 'weather_x', 'weather_y', 500, 80 ); ?></td></tr>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── Tab: Maintenance ────────────────────────── -->
            <div id="ha-pf-tab-maintenance" class="ha-pf-tab-content">
                <div class="ha-pf-columns">
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
                    </div>

                    <div class="ha-pf-col ha-pf-col-right">
                        <div class="ha-pf-card">
                            <h2>📡 Connection Health</h2>
                            <div id="ha-pf-health-dashboard">
                                <div class="ha-pf-health-hero">
                                    <div class="ha-pf-health-status" id="ha-pf-health-status-value">No data</div>
                                    <div class="ha-pf-health-label">API Status</div>
                                </div>
                                <div class="ha-pf-health-metrics">
                                    <div class="ha-pf-health-metric">
                                        <span class="label">Response Time</span>
                                        <span class="value" id="ha-pf-health-latency">-- ms</span>
                                    </div>
                                    <div class="ha-pf-health-metric">
                                        <span class="label">Success Rate</span>
                                        <span class="value" id="ha-pf-health-rate">-- %</span>
                                    </div>
                                    <div class="ha-pf-health-metric">
                                        <span class="label">Last Seen</span>
                                        <span class="value" id="ha-pf-health-last-seen">--</span>
                                    </div>
                                </div>
                                <div class="ha-pf-health-footer">
                                    <span id="ha-pf-health-count">0 checks tracked</span>
                                    <button type="button" id="ha-pf-refresh-health" class="button button-small">Refresh Now</button>
                                </div>
                            </div>
                        </div>
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
            </div><!-- .ha-pf-settings-column -->

            <div class="ha-pf-preview-column">
                <div class="ha-pf-preview-sticky">
                    <div class="ha-pf-card">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; padding-bottom:16px; border-bottom:2px solid #f1f5f9;">
                            <h2 style="margin:0; border:none; padding:0;">👁️ Live Preview</h2>
                            <select id="ha-pf-preview-size-select" style="font-size:12px; height:28px; padding:0 8px;">
                                <option value="350px">Small (350px)</option>
                                <option value="550px" selected>Medium (550px)</option>
                                <option value="750px">Large (750px)</option>
                                <option value="950px">Extra Large (950px)</option>
                            </select>
                        </div>
                        <div id="ha-pf-admin-preview-container">
                            <?php echo HA_Powerflow_Shortcode::render(); ?>
                        </div>
                        <p class="description" style="margin-top:15px; text-align:center;">
                            Changes shown here are <strong>instant</strong>. <br/>
                            Remember to <strong>Save Settings</strong> to apply to the frontend.
                        </p>
                        <div id="ha-pf-image-analysis" class="ha-pf-card" style="margin-top:15px; padding:12px; font-size:12px; background:#f8fafc; border:1px solid #e2e8f0; display:none;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                <strong style="color:#64748b;">🖼️ Image Analysis</strong>
                                <span id="ha-pf-image-size-badge" style="padding:2px 6px; border-radius:4px; background:#e2e8f0; font-weight:bold; font-family:monospace;">0 KB</span>
                            </div>
                            <div id="ha-pf-image-tips" style="color:#475569; line-height:1.4;">
                                Checking image quality...
                            </div>
                        </div>
                    </div>
                </div>
            </div><!-- .ha-pf-preview-column -->
        </div><!-- .ha-pf-admin-container -->

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
