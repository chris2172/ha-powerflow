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

function ha_pf_encrypt_string( $string ) {
    if ( ! trim( $string ) ) return $string;
    
    // Fallback if WP constants don't exist
    $key = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'ha_powerflow_fallback_key';
    $salt = defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : 'ha_powerflow_fallback_salt';
    
    $method = 'aes-256-cbc';
    $iv_length = openssl_cipher_iv_length( $method );
    // Use the salt to generate a deterministic IV for consistency, or generate a random one and prepend it.
    // For simplicity and safety in export/import across the same WP instance, a random IV is best.
    $iv = openssl_random_pseudo_bytes( $iv_length );
    
    // Hash key to ensure it is 32 bytes for aes-256
    $encryption_key = hash( 'sha256', $key . $salt, true );
    
    $encrypted = openssl_encrypt( $string, $method, $encryption_key, 0, $iv );
    
    // Combine IV + Encrypted Data and base64 encode
    return 'ENC:' . base64_encode( $iv . $encrypted );
}

function ha_pf_create_snapshot( $old_val, $new_val = null ) {
    // If called via add_option, $old_val is actually the $new_val
    if ( $new_val === null ) {
        $new_val = $old_val;
    }

    if ( ! is_array( $new_val ) ) return;
    
    $timestamp = date( 'Y-m-d_H-i-s' );
    $filename  = HA_POWERFLOW_CONFIG_DIR . 'snapshot_' . $timestamp . '.yaml';
    
    // Keys to encrypt
    $secure_keys = [ 'ha_url', 'ha_token' ];

    // Simple YAML-like encoding for the options array
    $content = "# HA Powerflow Configuration Snapshot\n";
    $content .= "# Saved: " . date( 'Y-m-d H:i:s' ) . "\n\n";
    foreach ( $new_val as $k => $v ) {
        if ( is_array( $v ) ) {
            $v = json_encode( $v );
        }
        
        $value_to_save = $v;
        if ( in_array( $k, $secure_keys, true ) && ! empty( $value_to_save ) ) {
            $value_to_save = ha_pf_encrypt_string( (string) $value_to_save );
        }
        
        // Basic escaping/quoting for YAML compatibility
        $clean_v = str_replace( '"', '\"', (string) $value_to_save );
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

        // EV extra fields
        if ( $key === 'ev' ) {
            $output['ev_charge_added']     = sanitize_text_field( $input['ev_charge_added']     ?? '' );
            $output['ev_charge_added_vis'] = ! empty( $input['ev_charge_added_vis'] )  ? '1' : '';
            $output['ev_plug_status']      = sanitize_text_field( $input['ev_plug_status']      ?? '' );
            $output['ev_plug_status_vis']  = ! empty( $input['ev_plug_status_vis'] )   ? '1' : '';
            $output['ev_charge_mode']      = sanitize_text_field( $input['ev_charge_mode']      ?? '' );
            $output['ev_charge_mode_vis']  = ! empty( $input['ev_charge_mode_vis'] )   ? '1' : '';
            $output['ev_charger_cost']     = sanitize_text_field( $input['ev_charger_cost']     ?? '' );
            $output['ev_charger_cost_vis'] = ! empty( $input['ev_charger_cost_vis'] )  ? '1' : '';
            $output['ev_currency_symbol']  = sanitize_text_field( $input['ev_currency_symbol']  ?? '£' );
            $output['ev_miles_per_kwh']         = floatval( $input['ev_miles_per_kwh']           ?? 3.5 );
            $output['ev_session_expected_hours'] = floatval( $input['ev_session_expected_hours'] ?? 4.0 );
            $output['ev_co2_factor']             = floatval( $input['ev_co2_factor']             ?? 0.5 );
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
                <?php if ( ! empty( $o['enable_ev'] ) ) : ?>
                <button type="button" class="ha-pf-tab-btn" data-tab="ev-history">⚡ EV History</button>
                <?php endif; ?>
            </nav>

            <!-- ── Tabs ────────────────────────────────────────────────── -->
            <?php
            include HA_POWERFLOW_DIR . 'admin/partials/tab-connection.php';
            include HA_POWERFLOW_DIR . 'admin/partials/tab-sensors.php';
            include HA_POWERFLOW_DIR . 'admin/partials/tab-appearance.php';
            include HA_POWERFLOW_DIR . 'admin/partials/tab-modules.php';
            include HA_POWERFLOW_DIR . 'admin/partials/tab-maintenance.php';
            include HA_POWERFLOW_DIR . 'admin/partials/tab-ev-history.php';
            ?>

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
            <li>Tick <strong>Debug Mode</strong>, save, and use the Live Preview above to drag and drop your entities to their correct positions.</li>
            <li>Set your <strong>Grid Line</strong>, <strong>Label</strong> and <strong>Status</strong> positions, then untick Debug Mode.</li>
            <li>Enable optional modules to reveal the <strong>House Line</strong> and each module's settings panel.</li>
        </ol>
        <p>Shortcode (Automated Data): <code>[ha_powerflow]</code></p>
        <p>Shortcode (Manual Data Entry): <code>[ha_powerflow_manual]</code></p>
    </div>
    <?php
}
