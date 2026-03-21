<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class HA_Powerflow_Shortcode {

    public static function init() {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_assets' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'register_assets' ] );
        add_shortcode( 'ha_powerflow', [ __CLASS__, 'render' ] );
    }

    public static function register_assets() {
        wp_register_style( 'ha-powerflow-fonts', 'https://fonts.googleapis.com/css2?family=Exo+2:wght@400;600;700&family=Orbitron:wght@400;700;900&display=swap', [], HA_POWERFLOW_VERSION );
        wp_register_style( 'ha-powerflow-style',  HA_POWERFLOW_URL . 'assets/css/style.css',     [ 'ha-powerflow-fonts' ], HA_POWERFLOW_VERSION );
        
        // Register powerflow.js with global handle
        wp_register_script( 'ha-powerflow-script', HA_POWERFLOW_URL . 'assets/js/powerflow.js', [ 'jquery' ], HA_POWERFLOW_VERSION, true );
    }

    public static function render() {
        wp_enqueue_style( 'ha-powerflow-style' );
        wp_enqueue_script( 'ha-powerflow-script' );

        $o  = get_option( 'ha_powerflow_options', [] );
        $all_modules = HA_Powerflow_Modules::get_all();

        $bg_image = ! empty( $o['bg_image'] )
                    ? esc_url( $o['bg_image'] )
                    : esc_url( HA_POWERFLOW_URL . 'assets/images/ha-powerflow.webp' );
        $debug    = ! empty( $o['debug'] )    ? 'true' : 'false';

        $any_module = false;
        foreach ( $all_modules as $key => $m ) {
            if ( ! empty( $o['enable_' . $key] ) ) {
                $any_module = true;
                break;
            }
        }

        $grid_line = ( isset( $o['grid_line'] ) && $o['grid_line'] !== '' )
                     ? $o['grid_line'] : 'M 120,350 L 880,350';

        $load_line = ( isset( $o['load_line'] ) && $o['load_line'] !== '' )
                     ? $o['load_line'] : 'M 500,350 L 880,350';

        // Prepare module-specific data
        $module_data = [];
        foreach ( $all_modules as $key => $m ) {
            $id_prefix = $m['id_prefix'];
            if ( ! empty( $m['is_weather'] ) ) continue;

            $module_data[$key] = [
                'line' => ( isset( $o[$id_prefix . '_line'] ) && $o[$id_prefix . '_line'] !== '' )
                          ? $o[$id_prefix . '_line'] : $m['default_path'],
                'x'    => (int) ( $o[$id_prefix . '_label_x'] ?? $m['default_pos']['x'] ),
                'y'    => (int) ( $o[$id_prefix . '_label_y'] ?? $m['default_pos']['y'] ),
                'enabled' => ! empty( $o['enable_' . $key] )
            ];
        }

        $line_color   = ! empty( $o['line_color'] )   ? $o['line_color'] : '#4a90d9';
        $line_opacity = isset( $o['line_opacity'] )   ? floatval( $o['line_opacity'] ) : 1.0;
        $line_opacity = max( 0, min( 1, $line_opacity ) );

        $title_color  = ! empty( $o['title_color'] )  ? $o['title_color']  : '#8899bb';
        $power_color  = ! empty( $o['power_color'] )  ? $o['power_color']  : '#f0a500';
        $energy_color = ! empty( $o['energy_color'] ) ? $o['energy_color'] : '#6677aa';

        $grid_color     = ! empty( $o['grid_color'] ) ? $o['grid_color'] : $line_color;
        $load_color     = ! empty( $o['load_color'] ) ? $o['load_color'] : $line_color;

        // Label positions for core
        $gx = (int) ( $o['grid_label_x'] ?? 120 );
        $gy = (int) ( $o['grid_label_y'] ?? 260 );
        $hx = (int) ( $o['load_label_x'] ?? 880 );
        $hy = (int) ( $o['load_label_y'] ?? 260 );

        // Status (flow label) position
        $sx = (int) ( $o['status_x'] ?? 500 );
        $sy = (int) ( $o['status_y'] ?? 320 );

        // Weather position
        $wx = (int) ( $o['weather_x'] ?? 500 );
        $wy = (int) ( $o['weather_y'] ?? 80 );
;

        $localized_data = [
            'restUrl'         => esc_url_raw( rest_url( 'ha-powerflow/v1/data' ) ),
            'nonce'           => wp_create_nonce( 'ha_powerflow_nonce' ),
            'refreshInterval' => (int) ( $o['refresh_rate'] ?? 5000 ),
            'anyModule'       => $any_module ? 'true' : 'false',
            'lineColor'       => $line_color,
            'gridColor'       => $grid_color,
            'loadColor'       => $load_color,
            'lineOpacity'     => $line_opacity,
            'haUrl'           => ! empty( $o['ha_url'] )   ? 'set' : 'missing',
            'haToken'         => ! empty( $o['ha_token'] ) ? 'set' : 'missing',
            'gridPower'       => $o['grid_power']  ?? '',
            'loadPower'       => $o['load_power']  ?? '',
            'gridEnergy'      => $o['grid_energy'] ?? '',
            'gridEnergyOut'   => $o['grid_energy_out'] ?? '',
            'gridPriceIn'     => $o['grid_price_in'] ?? '',
            'gridPriceOut'    => $o['grid_price_out'] ?? '',
            'gridPriceCheap'  => floatval( $o['grid_price_cheap'] ?? 0.10 ),
            'gridPriceHigh'   => floatval( $o['grid_price_high'] ?? 0.30 ),
            'gridShowSavings' => ! empty( $o['grid_show_savings'] ) ? 'true' : 'false',
            'loadEnergy'      => $o['load_energy'] ?? '',
            'enableWeather'    => ! empty( $o['enable_weather'] )  ? 'true' : 'false',
            'weatherFontSize'  => (int) ( $o['weather_font_size'] ?? 13 ),
            'customEntities'   => ! empty( $o['custom_entities'] ) ? $o['custom_entities'] : [],
            'gridMaxCapacity'  => (int) ( $o['grid_max_capacity'] ?? 10000 ),
            'houseMaxCapacity' => (int) ( $o['house_max_capacity'] ?? 8000 ),
            'swUrl'            => HA_POWERFLOW_URL . 'assets/sw.js',
            'sessionLogUrl'    => esc_url_raw( rest_url( 'ha-powerflow/v1/ev-session' ) ),
            'modules'          => []
        ];

        $css_vars = "
            --ha-pf-title-color: " . esc_attr($title_color) . ";
            --ha-pf-power-color: " . esc_attr($power_color) . ";
            --ha-pf-energy-color: " . esc_attr($energy_color) . ";
            --ha-pf-grid-color: " . esc_attr($grid_color) . ";
            --ha-pf-load-color: " . esc_attr($load_color) . ";
        ";

        foreach ( $all_modules as $key => $m ) {
            if ( ! empty($m['is_weather']) ) continue;
            $prefix = $m['id_prefix'];
            $color = ! empty( $o[$prefix . '_color'] ) ? $o[$prefix . '_color'] : $line_color;
            
            $localized_data['modules'][$key] = [
                'prefix'  => $prefix,
                'enabled' => ! empty( $o['enable_' . $key] ) ? 'true' : 'false',
                'color'   => $color,
                'power'   => $o[$prefix . '_power'] ?? '',
                'soc'     => ! empty($m['has_soc']) ? ($o[$prefix . '_soc'] ?? '') : '',
                'energy'  => ! empty($m['has_energy']) ? ($o[$prefix . '_energy'] ?? '') : '',
                'eff'     => ! empty($m['has_eff']) ? ($o[$prefix . '_efficiency'] ?? '') : '',
                'maxCapacity' => (int) ( $o[$prefix . '_max_capacity'] ?? ($m['default_capacity'] ?? 0) ),
            ];

            // Special handling for battery energy split
            if ( $key === 'battery' ) {
                $localized_data['modules'][$key]['energyIn']  = $o['battery_in_energy'] ?? '';
                $localized_data['modules'][$key]['energyOut'] = $o['battery_out_energy'] ?? '';
                $localized_data['modules'][$key]['minDischarge'] = (int) ( $o['battery_min_discharge'] ?? 10 );
                $localized_data['modules'][$key]['capacityKwh']  = floatval( $o['battery_capacity_kwh'] ?? 13.50 );
            }

            // EV extra fields
            if ( $key === 'ev' ) {
                // Vis defaults to 'true' (visible) when the option has never been saved;
                // only becomes 'false' after an admin explicitly unchecks the toggle and saves.
                $localized_data['modules'][$key]['chargeAdded']    = $o['ev_charge_added']  ?? '';
                $localized_data['modules'][$key]['chargeAddedVis'] = isset( $o['ev_charge_added_vis'] )  ? ( $o['ev_charge_added_vis']  ? 'true' : 'false' ) : 'true';
                $localized_data['modules'][$key]['plugStatus']     = $o['ev_plug_status']   ?? '';
                $localized_data['modules'][$key]['plugStatusVis']  = isset( $o['ev_plug_status_vis'] )   ? ( $o['ev_plug_status_vis']   ? 'true' : 'false' ) : 'true';
                $localized_data['modules'][$key]['chargeMode']     = $o['ev_charge_mode']   ?? '';
                $localized_data['modules'][$key]['chargeModeVis']  = isset( $o['ev_charge_mode_vis'] )   ? ( $o['ev_charge_mode_vis']   ? 'true' : 'false' ) : 'true';
                $localized_data['modules'][$key]['chargerCost']    = $o['ev_charger_cost']  ?? '';
                $localized_data['modules'][$key]['chargerCostVis'] = isset( $o['ev_charger_cost_vis'] )  ? ( $o['ev_charger_cost_vis']  ? 'true' : 'false' ) : 'true';
                $localized_data['modules'][$key]['currencySymbol'] = $o['ev_currency_symbol'] ?? '£';
            }

            $css_vars .= " --ha-pf-{$prefix}-color: " . esc_attr($color) . ";";
        }

        wp_add_inline_style( 'ha-powerflow-style', ".ha-powerflow-widget { {$css_vars} }" );
        wp_localize_script( 'ha-powerflow-script', 'haPowerflow', $localized_data );

        ob_start(); ?>
        <div class="ha-powerflow-widget"
             data-debug="<?php echo esc_attr( $debug ); ?>"
             style="background-image:url(<?php echo $bg_image; ?>);">

            <div class="ha-pf-debug-bar" id="ha-pf-debug-bar">
                🐛 Debug Mode &nbsp;|&nbsp; Click anywhere on the widget to read SVG coordinates
            </div>
            <div class="ha-pf-coord-pin" id="ha-pf-coord-pin"></div>

            <svg id="ha-pf-svg" viewBox="0 0 1000 700"
                 xmlns="http://www.w3.org/2000/svg"
                 preserveAspectRatio="xMidYMid meet">

                <defs>
                    <filter id="hapf-glow" x="-100%" y="-100%" width="300%" height="300%">
                        <feGaussianBlur stdDeviation="2.5" result="blur"/>
                        <feComponentTransfer in="blur" result="brightBlur">
                            <feFuncA type="linear" slope="2.5"/>
                        </feComponentTransfer>
                        <feMerge>
                            <feMergeNode in="brightBlur"/>
                            <feMergeNode in="brightBlur"/>
                            <feMergeNode in="SourceGraphic"/>
                        </feMerge>
                    </filter>
                    <filter id="hapf-shadow">
                        <feDropShadow dx="0" dy="1.5" stdDeviation="4" flood-color="#000" flood-opacity="0.9"/>
                    </filter>
                    <filter id="hapf-glass">
                        <feFlood flood-color="white" flood-opacity="0.05" result="bg"/>
                        <feGaussianBlur in="SourceGraphic" stdDeviation="8" result="blur"/>
                        <feComposite in="bg" in2="blur" operator="over"/>
                    </filter>
                </defs>

                <!-- ── Grid Line (Grid→Home or Grid→Inverter) ───────────────── -->
                <path id="ha-pf-line"
                      d="<?php echo esc_attr( $grid_line ); ?>"
                      fill="none"
                      stroke-width="2.0" stroke-linecap="round" stroke-linejoin="round"
                      opacity="<?php echo esc_attr( $line_opacity ); ?>"/>
                <path id="ha-pf-path" class="ha-pf-laser"
                      d="<?php echo esc_attr( $grid_line ); ?>"
                      fill="none"
                      stroke-width="2.0" stroke-linecap="round"
                      filter="url(#hapf-glow)" opacity="0"
                      data-max-capacity="<?php echo (int)($o['grid_max_capacity'] ?? 10000); ?>"/>

                <?php if ( is_admin() || $any_module ) : ?>
                <!-- ── Load Line (Inverter→Home) — visible when modules active ─ -->
                <path id="ha-pf-load-line"
                      d="<?php echo esc_attr( $load_line ); ?>"
                      fill="none"
                      stroke-width="2.0" stroke-linecap="round" stroke-linejoin="round"
                      opacity="<?php echo esc_attr( $line_opacity ); ?>"/>
                <path id="ha-pf-load-path" class="ha-pf-laser"
                      d="<?php echo esc_attr( $load_line ); ?>"
                      fill="none"
                      stroke-width="2.0" stroke-linecap="round"
                      filter="url(#hapf-glow)" opacity="0"
                      data-max-capacity="<?php echo (int)($o['house_max_capacity'] ?? 8000); ?>"/>
                <?php endif; ?>

                <?php
                // Render Module Paths
                foreach ( $module_data as $key => $m ) :
                    $id_prefix = $all_modules[$key]['id_prefix'];
                    $visible = is_admin() || $m['enabled'];
                    $stroke_width = ($key === 'heatpump') ? '4.5' : '2.0';
                    if ( $visible ) : ?>
                    <path id="ha-pf-<?php echo $id_prefix; ?>-line"
                          d="<?php echo esc_attr( $m['line'] ); ?>"
                          fill="none"
                          stroke-width="2.0" stroke-linecap="round" stroke-linejoin="round"
                          opacity="<?php echo esc_attr( $line_opacity ); ?>"/>
                    <path id="ha-pf-<?php echo $id_prefix; ?>-path" class="ha-pf-laser"
                          d="<?php echo esc_attr( $m['line'] ); ?>"
                          fill="none"
                          stroke-width="<?php echo $stroke_width; ?>" stroke-linecap="round"
                          filter="url(#hapf-glow)" opacity="0"
                          data-max-capacity="<?php echo (int)($o[$id_prefix . '_max_capacity'] ?? ($all_modules[$key]['default_capacity'] ?? 0)); ?>"/>
                    <?php endif;
                endforeach; ?>

                <?php if ( is_admin() || ! empty( $o['enable_weather'] ) ) : ?>
                <!-- ── Weather Icon ─────────────────────────────────── -->
                <g id="ha-pf-weather-icon-group" class="ha-pf-draggable" transform="translate(<?php echo $wx; ?>, <?php echo $wy - 30; ?>)" style="<?php echo empty($o['enable_weather']) ? 'display:none;' : ''; ?>">
                     <g id="ha-pf-weather-icon" 
                        fill="none" 
                        stroke-width="2.0" 
                        filter="url(#hapf-glow)"></g>
                </g>
                <?php endif; ?>

                <!-- ── Status label ──────────────────────────────────────────── -->
                <text id="ha-pf-flow-label" class="ha-pf-draggable"
                      x="<?php echo $sx; ?>" y="<?php echo $sy; ?>"
                      text-anchor="middle"
                      font-family="'Exo 2', sans-serif"
                      font-size="13" font-weight="700" letter-spacing="1.5"
                      filter="url(#hapf-shadow)">
                    <tspan id="ha-pf-flow-main" x="<?php echo $sx; ?>" dy="0"></tspan>
                    <tspan id="ha-pf-flow-sub" x="<?php echo $sx; ?>" dy="18" font-size="11" opacity="0.8"></tspan>
                </text>

                <!-- ── Grid label block ──────────────────────────────────────── -->
                <g id="ha-pf-grid-group" class="ha-pf-draggable">
                    <text id="ha-pf-grid-title"
                          x="<?php echo $gx; ?>" y="<?php echo $gy; ?>"
                          text-anchor="middle"
                          font-family="'Exo 2', sans-serif"
                          font-size="11" font-weight="700" letter-spacing="2"
                          filter="url(#hapf-shadow)">GRID</text>
                    <text id="ha-pf-grid-power"
                          x="<?php echo $gx; ?>" y="<?php echo $gy + 24; ?>"
                          text-anchor="middle"
                          font-family="Orbitron, sans-serif" font-size="19" font-weight="700"
                          filter="url(#hapf-shadow)">—</text>
                    <text id="ha-pf-grid-energy"
                          x="<?php echo $gx; ?>" y="<?php echo $gy + 44; ?>"
                          text-anchor="middle"
                          font-family="'Exo 2', sans-serif" font-size="12"
                          filter="url(#hapf-shadow)">—</text>
                    <text id="ha-pf-grid-energy-out"
                          x="<?php echo $gx; ?>" y="<?php echo $gy + 58; ?>"
                          text-anchor="middle"
                          font-family="'Exo 2', sans-serif" font-size="12"
                          filter="url(#hapf-shadow)" style="display:none;">—</text>
                    <text id="ha-pf-grid-price-in"
                          x="<?php echo $gx; ?>" y="<?php echo $gy + 72; ?>"
                          text-anchor="middle"
                          font-family="'Exo 2', sans-serif" font-size="12"
                          filter="url(#hapf-shadow)">—</text>
                    <text id="ha-pf-grid-price-out"
                          x="<?php echo $gx; ?>" y="<?php echo $gy + 86; ?>"
                          text-anchor="middle"
                          font-family="'Exo 2', sans-serif" font-size="12"
                          filter="url(#hapf-shadow)" style="display:none;">—</text>
                </g>

                <!-- ── Load label block ──────────────────────────────────────── -->
                <g id="ha-pf-load-group" class="ha-pf-draggable">
                    <text id="ha-pf-home-label" x="<?php echo $hx; ?>" y="<?php echo $hy; ?>"
                          text-anchor="middle"
                          font-family="'Exo 2', sans-serif"
                          font-size="11" font-weight="700" letter-spacing="2"
                          filter="url(#hapf-shadow)">HOUSE</text>
                    <text id="ha-pf-load-power"
                          x="<?php echo $hx; ?>" y="<?php echo $hy + 24; ?>"
                          text-anchor="middle"
                          font-family="Orbitron, sans-serif" font-size="19" font-weight="700"
                          filter="url(#hapf-shadow)">—</text>
                    <text id="ha-pf-load-energy"
                          x="<?php echo $hx; ?>" y="<?php echo $hy + 44; ?>"
                          text-anchor="middle"
                          font-family="'Exo 2', sans-serif" font-size="12"
                          filter="url(#hapf-shadow)">—</text>
                </g>

                <?php
                // Render Module Labels
                foreach ( $module_data as $key => $m ) :
                    $id_prefix = $all_modules[$key]['id_prefix'];
                    $visible = is_admin() || $m['enabled'];
                    if ( $visible ) : ?>
                    <g id="ha-pf-<?php echo $id_prefix; ?>-group" class="ha-pf-draggable">
                        <text id="ha-pf-<?php echo $id_prefix; ?>-title"
                              x="<?php echo $m['x']; ?>" y="<?php echo $m['y']; ?>"
                              text-anchor="middle"
                              font-family="'Exo 2', sans-serif"
                              font-size="11" font-weight="700" letter-spacing="2"
                              filter="url(#hapf-shadow)"><?php echo strtoupper($all_modules[$key]['label']); ?></text>
                        <text id="ha-pf-<?php echo $id_prefix; ?>-power"
                              x="<?php echo $m['x']; ?>" y="<?php echo $m['y'] + 24; ?>"
                              text-anchor="middle"
                              font-family="Orbitron, sans-serif" font-size="19" font-weight="700"
                              filter="url(#hapf-shadow)">—</text>
                        <?php if ( ! empty($all_modules[$key]['has_soc']) ) : ?>
                            <text id="ha-pf-<?php echo $id_prefix; ?>-soc"
                                  x="<?php echo $m['x']; ?>" y="<?php echo $m['y'] + 44; ?>"
                                  text-anchor="middle"
                                  font-family="'Exo 2', sans-serif" font-size="12"
                                  filter="url(#hapf-shadow)">—</text>
                            <?php if ( $key === 'battery' ) : ?>
                                <text id="ha-pf-battery-time"
                                      x="<?php echo $m['x']; ?>" y="<?php echo $m['y'] + 60; ?>"
                                      text-anchor="middle"
                                      font-family="'Exo 2', sans-serif" font-size="10"
                                      fill="var(--ha-pf-energy-color)"
                                      filter="url(#hapf-shadow)">—</text>
                            <?php endif; ?>
                        <?php elseif ( ! empty($all_modules[$key]['has_energy']) && ! empty($all_modules[$key]['has_eff']) ) : ?>
                             <text id="ha-pf-<?php echo $id_prefix; ?>-efficiency"
                                  x="<?php echo $m['x']; ?>" y="<?php echo $m['y'] + 44; ?>"
                                  text-anchor="middle"
                                  font-family="'Exo 2', sans-serif" font-size="12"
                                  filter="url(#hapf-shadow)">—</text>
                        <?php elseif ( ! empty($all_modules[$key]['has_energy']) ) : ?>
                             <text id="ha-pf-<?php echo $id_prefix; ?>-energy"
                                   x="<?php echo $m['x']; ?>" y="<?php echo $m['y'] + 44; ?>"
                                   text-anchor="middle"
                                   font-family="'Exo 2', sans-serif" font-size="12"
                                   filter="url(#hapf-shadow)">—</text>

                             <?php if ( $key === 'solar' ) : ?>
                                <circle id="ha-pf-solar-progress-ring"
                                        cx="<?php echo $m['x']; ?>" cy="<?php echo $m['y'] + 17; ?>" r="42"
                                        class="ha-pf-solar-progress-ring"
                                        stroke-dasharray="0 264"
                                        transform="rotate(-90 <?php echo $m['x']; ?> <?php echo $m['y'] + 17; ?>)"
                                        style="<?php echo empty($o['solar_forecast_vis']) ? 'display:none;' : ''; ?>" />
                                <text id="ha-pf-solar-forecast"
                                      x="<?php echo $m['x']; ?>" y="<?php echo $m['y'] + 60; ?>"
                                      text-anchor="middle"
                                      font-family="'Exo 2', sans-serif" font-size="11"
                                      fill="#8899bb"
                                      filter="url(#hapf-shadow)"
                                      style="<?php echo empty($o['solar_forecast_vis']) ? 'display:none;' : ''; ?>">Forecast: —</text>
                             <?php endif; ?>
                             <?php if ( $key === 'grid' ) : ?>
                                <text id="ha-pf-grid-savings"
                                      x="<?php echo $m['x']; ?>" y="<?php echo $m['y'] + 60; ?>"
                                      text-anchor="middle"
                                      font-family="'Exo 2', sans-serif" font-size="11"
                                      fill="#22c55e"
                                      filter="url(#hapf-shadow)"
                                      style="<?php echo empty($o['grid_show_savings']) ? 'display:none;' : ''; ?>">Saving: —</text>
                             <?php endif; ?>
                        <?php endif; ?>
                        <?php if ( $key === 'ev' ) :
                            $ev_extras = [
                                'charge-added' => 58,
                                'plug-status'  => 71,
                                'charge-mode'  => 84,
                                'charger-cost' => 97,
                            ];
                            foreach ( $ev_extras as $slug => $y_off ) : ?>
                            <text id="ha-pf-ev-<?php echo $slug; ?>"
                                  x="<?php echo $m['x']; ?>" y="<?php echo $m['y'] + $y_off; ?>"
                                  text-anchor="middle"
                                  font-family="'Exo 2', sans-serif" font-size="11"
                                  filter="url(#hapf-shadow)">—</text>
                            <?php endforeach;
                        endif; ?>
                    </g>
                    <?php endif;
                endforeach; ?>

                <?php if ( is_admin() || ! empty( $o['enable_weather'] ) ) : ?>
                <text id="ha-pf-weather" class="ha-pf-draggable"
                      x="<?php echo $wx; ?>" y="<?php echo $wy + 5; ?>"
                      text-anchor="middle"
                      font-family="'Exo 2', sans-serif"
                      font-size="<?php echo (int) ( $o['weather_font_size'] ?? 13 ); ?>" font-weight="700" letter-spacing="1.5"
                      filter="url(#hapf-shadow)" style="<?php echo empty($o['enable_weather']) ? 'display:none;' : ''; ?>"></text>
                <?php endif; ?>

                <!-- ── Custom Entities ────────────────────────────────────────── -->
                <?php
                if ( ! empty( $o['custom_entities'] ) && is_array( $o['custom_entities'] ) ) {
                    foreach ( $o['custom_entities'] as $index => $item ) {
                        if ( ! is_admin() && empty( $item['visible'] ) ) continue;
                        $cx = (int) $item['x'];
                        $cy = (int) $item['y'];
                        $label = esc_html( $item['label'] );
                        ?>
                        <g class="ha-pf-custom-entity ha-pf-draggable" id="ha-pf-custom-<?php echo $index; ?>" transform="translate(<?php echo $cx; ?>, <?php echo $cy; ?>)">
                            <text text-anchor="middle"
                                  font-family="'Exo 2', sans-serif" font-size="11" font-weight="700" letter-spacing="2"
                                  filter="url(#hapf-shadow)"><?php echo strtoupper($label); ?></text>
                            <text class="ha-pf-custom-value" dy="24" text-anchor="middle"
                                  font-family="Orbitron, sans-serif" font-size="<?php echo (int)($item['font_size'] ?? 19); ?>" font-weight="700"
                                  filter="url(#hapf-shadow)">—</text>
                        </g>
                        <?php
                    }
                }
                ?>

            </svg>

            <div class="ha-pf-status" id="ha-pf-status">
                <!-- Added a spinner animation inline here to notify user while connecting -->
                <div class="ha-pf-spinner" style="display:inline-block; margin-right:6px; vertical-align:middle; width:12px; height:12px; border:2px solid rgba(255,255,255,0.3); border-top-color:#fff; border-radius:50%; animation:ha-pf-spin 1s linear infinite;"></div>
                <style>
                    @keyframes ha-pf-spin { to { transform: rotate(360deg); } }
                    .ha-pf-rotate { animation: ha-pf-spin 10s linear infinite; transform-origin: center; }
                    @keyframes ha-pf-rain-fall { from { stroke-dashoffset: 0; } to { stroke-dashoffset: 20; } }
                    .ha-pf-rain { stroke-dasharray: 4 4; animation: ha-pf-rain-fall 1s linear infinite; }
                </style>
                Connecting…
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
