<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class HA_Powerflow_Shortcode {

    public static function init() {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_assets' ] );
        add_shortcode( 'ha_powerflow', [ __CLASS__, 'render' ] );
    }

    public static function register_assets() {
        wp_register_style( 'ha-powerflow-fonts', 'https://fonts.googleapis.com/css2?family=Exo+2:wght@400;600;700&family=Orbitron:wght@400;700;900&display=swap', [], HA_POWERFLOW_VERSION );
        wp_register_style( 'ha-powerflow-style',  HA_POWERFLOW_URL . 'assets/css/style.css',     [ 'ha-powerflow-fonts' ], HA_POWERFLOW_VERSION );
        wp_register_script( 'ha-powerflow-script', HA_POWERFLOW_URL . 'assets/js/powerflow.js', [ 'jquery' ], HA_POWERFLOW_VERSION, true );
    }

    public static function render() {
        wp_enqueue_style( 'ha-powerflow-style' );
        wp_enqueue_script( 'ha-powerflow-script' );

        $o  = get_option( 'ha_powerflow_options', [] );

        $bg_image = ! empty( $o['bg_image'] )
                    ? esc_url( $o['bg_image'] )
                    : esc_url( HA_POWERFLOW_URL . 'assets/images/ha-powerflow.png' );
        $debug    = ! empty( $o['debug'] )    ? 'true' : 'false';

        $any_module = ( ! empty( $o['enable_solar'] )    ||
                        ! empty( $o['enable_battery'] )  ||
                        ! empty( $o['enable_ev'] )       ||
                        ! empty( $o['enable_heatpump'] ) ||
                        ! empty( $o['enable_weather'] ) );

        $grid_line = ( isset( $o['grid_line'] ) && $o['grid_line'] !== '' )
                     ? $o['grid_line'] : 'M 120,350 L 880,350';

        $load_line = ( isset( $o['load_line'] ) && $o['load_line'] !== '' )
                     ? $o['load_line'] : 'M 500,350 L 880,350';

        $pv_line   = ( isset( $o['pv_line'] ) && $o['pv_line'] !== '' )
                     ? $o['pv_line']   : 'M 500,150 L 500,350';

        $battery_line = ( isset( $o['battery_line'] ) && $o['battery_line'] !== '' )
                        ? $o['battery_line'] : 'M 500,350 L 500,550';

        $ev_line      = ( isset( $o['ev_line'] ) && $o['ev_line'] !== '' )
                        ? $o['ev_line']       : 'M 750,350 L 750,550';

        $heatpump_line = ( isset( $o['heatpump_line'] ) && $o['heatpump_line'] !== '' )
                         ? $o['heatpump_line'] : 'M 250,350 L 250,550';

        $line_color   = ! empty( $o['line_color'] )   ? $o['line_color'] : '#4a90d9';
        $line_opacity = isset( $o['line_opacity'] )   ? floatval( $o['line_opacity'] ) : 1.0;
        $line_opacity = max( 0, min( 1, $line_opacity ) );

        $title_color  = ! empty( $o['title_color'] )  ? $o['title_color']  : '#8899bb';
        $power_color  = ! empty( $o['power_color'] )  ? $o['power_color']  : '#f0a500';
        $grid_color     = ! empty( $o['grid_color'] ) ? $o['grid_color'] : $line_color;
        $load_color     = ! empty( $o['load_color'] ) ? $o['load_color'] : $line_color;
        $pv_color       = ! empty( $o['pv_color'] )   ? $o['pv_color']   : $line_color;
        $battery_color  = ! empty( $o['battery_color']) ? $o['battery_color'] : $line_color;
        $ev_color       = ! empty( $o['ev_color'] )   ? $o['ev_color']   : $line_color;
        $heatpump_color = ! empty( $o['heatpump_color'])? $o['heatpump_color']: $line_color;
        $energy_color = ! empty( $o['energy_color'] ) ? $o['energy_color'] : '#6677aa';

        // Label positions
        $gx = (int) ( $o['grid_label_x'] ?? 120 );
        $gy = (int) ( $o['grid_label_y'] ?? 260 );
        $hx = (int) ( $o['load_label_x'] ?? 880 );
        $hy = (int) ( $o['load_label_y'] ?? 260 );
        $px = (int) ( $o['pv_label_x']      ?? 500 );
        $py = (int) ( $o['pv_label_y']      ?? 150 );
        $bx = (int) ( $o['battery_label_x']  ?? 500 );
        $by = (int) ( $o['battery_label_y']  ?? 550 );
        $ex = (int) ( $o['ev_label_x']       ?? 750 );
        $ey = (int) ( $o['ev_label_y']       ?? 550 );
        $hx2 = (int) ( $o['heatpump_label_x'] ?? 250 );
        $hy2 = (int) ( $o['heatpump_label_y'] ?? 550 );

        // Status (flow label) position — fully user-controlled
        $sx = (int) ( $o['status_x'] ?? 500 );
        $sy = (int) ( $o['status_y'] ?? 320 );

        // Weather position
        $wx = (int) ( $o['weather_x'] ?? 500 );
        $wy = (int) ( $o['weather_y'] ?? 80 );

        wp_localize_script( 'ha-powerflow-script', 'haPowerflow', [
            'restUrl'         => esc_url_raw( rest_url( 'ha-powerflow/v1/data' ) ),
            'nonce'           => wp_create_nonce( 'ha_powerflow_nonce' ),
            'refreshInterval' => (int) ( $o['refresh_rate'] ?? 5000 ),
            'anyModule'       => $any_module ? 'true' : 'false',
            'lineColor'       => $line_color,
            'gridColor'       => $grid_color,
            'loadColor'       => $load_color,
            'pvColor'         => $pv_color,
            'batteryColor'    => $battery_color,
            'evColor'         => $ev_color,
            'heatpumpColor'   => $heatpump_color,
            'isCustomGrid'    => ! empty( $o['grid_color'] ) ? 'true' : 'false',
            'isCustomHouse'   => ! empty( $o['load_color'] ) ? 'true' : 'false',
            'isCustomPv'      => ! empty( $o['pv_color'] )   ? 'true' : 'false',
            'isCustomBattery' => ! empty( $o['battery_color']) ? 'true' : 'false',
            'isCustomEv'      => ! empty( $o['ev_color'] )    ? 'true' : 'false',
            'isCustomHp'      => ! empty( $o['heatpump_color'] ) ? 'true' : 'false',
            'lineOpacity'     => $line_opacity,
            'haUrl'           => ! empty( $o['ha_url'] )   ? 'set' : 'missing',
            'haToken'         => ! empty( $o['ha_token'] ) ? 'set' : 'missing',
            'gridPower'       => $o['grid_power']  ?? '',
            'loadPower'       => $o['load_power']  ?? '',
            'gridEnergy'      => $o['grid_energy'] ?? '',
            'gridEnergyOut'   => $o['grid_energy_out'] ?? '',
            'gridPriceIn'     => $o['grid_price_in'] ?? '',
            'gridPriceOut'    => $o['grid_price_out'] ?? '',
            'loadEnergy'      => $o['load_energy'] ?? '',
            'pvPower'         => $o['pv_power']          ?? '',
            'pvEnergy'        => $o['pv_energy']         ?? '',
            'enableSolar'     => ! empty( $o['enable_solar'] )   ? 'true' : 'false',
            'batteryPower'     => $o['battery_power']      ?? '',
            'batteryInEnergy'  => $o['battery_in_energy']  ?? '',
            'batteryOutEnergy' => $o['battery_out_energy'] ?? '',
            'batterySoc'       => $o['battery_soc']        ?? '',
            'enableBattery'    => ! empty( $o['enable_battery'] )  ? 'true' : 'false',
            'evPower'          => $o['ev_power']            ?? '',
            'evSoc'            => $o['ev_soc']              ?? '',
            'enableEv'         => ! empty( $o['enable_ev'] )       ? 'true' : 'false',
            'heatpumpPower'    => $o['heatpump_power']      ?? '',
            'heatpumpEnergy'   => $o['heatpump_energy']     ?? '',
            'heatpumpEfficiency'=> $o['heatpump_efficiency'] ?? '',
            'enableHeatpump'   => ! empty( $o['enable_heatpump'] ) ? 'true' : 'false',
            'enableWeather'    => ! empty( $o['enable_weather'] )  ? 'true' : 'false',
            'weatherFontSize'  => (int) ( $o['weather_font_size'] ?? 13 ),
            'customEntities'   => ! empty( $o['custom_entities'] ) ? $o['custom_entities'] : [],
        ] );

        ob_start(); ?>
        <div class="ha-powerflow-widget"
             data-debug="<?php echo esc_attr( $debug ); ?>"
             style="background-image:url(<?php echo $bg_image; ?>);
                    --ha-pf-title-color: <?php echo esc_attr($title_color); ?>;
                    --ha-pf-power-color: <?php echo esc_attr($power_color); ?>;
                    --ha-pf-energy-color: <?php echo esc_attr($energy_color); ?>;
                    --ha-pf-grid-color: <?php echo esc_attr($grid_color); ?>;
                    --ha-pf-load-color: <?php echo esc_attr($load_color); ?>;
                    --ha-pf-pv-color: <?php echo esc_attr($pv_color); ?>;
                    --ha-pf-battery-color: <?php echo esc_attr($battery_color); ?>;
                    --ha-pf-ev-color: <?php echo esc_attr($ev_color); ?>;
                    --ha-pf-heatpump-color: <?php echo esc_attr($heatpump_color); ?>;">

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
                      filter="url(#hapf-glow)" opacity="0"/>

                <?php if ( $any_module ) : ?>
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
                      filter="url(#hapf-glow)" opacity="0"/>
                <?php endif; ?>

                <?php if ( ! empty( $o['enable_solar'] ) ) : ?>
                <!-- ── PV Line (Solar→Inverter) — visible when solar is enabled ─ -->
                <path id="ha-pf-pv-line"
                      d="<?php echo esc_attr( $pv_line ); ?>"
                      fill="none"
                      stroke-width="2.0" stroke-linecap="round" stroke-linejoin="round"
                      opacity="<?php echo esc_attr( $line_opacity ); ?>"/>
                <path id="ha-pf-pv-path" class="ha-pf-laser"
                      d="<?php echo esc_attr( $pv_line ); ?>"
                      fill="none"
                      stroke-width="2.0" stroke-linecap="round"
                      filter="url(#hapf-glow)" opacity="0"/>
                <?php endif; ?>

                <?php if ( ! empty( $o['enable_battery'] ) ) : ?>
                <!-- ── Battery Line (Inverter↔Battery) — visible when battery is enabled ─ -->
                <path id="ha-pf-battery-line"
                      d="<?php echo esc_attr( $battery_line ); ?>"
                      fill="none"
                      stroke-width="2.0" stroke-linecap="round" stroke-linejoin="round"
                      opacity="<?php echo esc_attr( $line_opacity ); ?>"/>
                <path id="ha-pf-battery-path" class="ha-pf-laser"
                      d="<?php echo esc_attr( $battery_line ); ?>"
                      fill="none"
                      stroke-width="2.0" stroke-linecap="round"
                      filter="url(#hapf-glow)" opacity="0"/>
                <?php endif; ?>

                <?php if ( ! empty( $o['enable_ev'] ) ) : ?>
                <!-- ── EV Line (Inverter→EV) — visible when EV is enabled ─ -->
                <path id="ha-pf-ev-line"
                      d="<?php echo esc_attr( $ev_line ); ?>"
                      fill="none"
                      stroke-width="2.0" stroke-linecap="round" stroke-linejoin="round"
                      opacity="<?php echo esc_attr( $line_opacity ); ?>"/>
                <path id="ha-pf-ev-path" class="ha-pf-laser"
                      d="<?php echo esc_attr( $ev_line ); ?>"
                      fill="none"
                      stroke-width="2.0" stroke-linecap="round"
                      filter="url(#hapf-glow)" opacity="0"/>
                <?php endif; ?>

                <?php if ( ! empty( $o['enable_heatpump'] ) ) : ?>
                <!-- ── Heat Pump Line (Inverter→Heat Pump) — visible when Heat Pump is enabled ─ -->
                <path id="ha-pf-heatpump-line"
                      d="<?php echo esc_attr( $heatpump_line ); ?>"
                      fill="none"
                      stroke-width="2.0" stroke-linecap="round" stroke-linejoin="round"
                      opacity="<?php echo esc_attr( $line_opacity ); ?>"/>
                <path id="ha-pf-heatpump-path" class="ha-pf-laser"
                      d="<?php echo esc_attr( $heatpump_line ); ?>"
                      fill="none"
                      stroke-width="4.5" stroke-linecap="round"
                      filter="url(#hapf-glow)" opacity="0"/>
                <?php endif; ?>

                <?php if ( is_admin() || ! empty( $o['enable_weather'] ) ) : ?>
                <!-- ── Weather Icon ─────────────────────────────────── -->
                <g id="ha-pf-weather-icon-group" transform="translate(<?php echo $wx; ?>, <?php echo $wy - 30; ?>)" style="<?php echo empty($o['enable_weather']) ? 'display:none;' : ''; ?>">
                     <g id="ha-pf-weather-icon" 
                        fill="none" 
                        stroke-width="2.0" 
                        filter="url(#hapf-glow)"></g>
                </g>
                <?php endif; ?>

                <!-- ── Status label ──────────────────────────────────────────── -->
                <text id="ha-pf-flow-label"
                      x="<?php echo $sx; ?>" y="<?php echo $sy; ?>"
                      text-anchor="middle"
                      font-family="'Exo 2', sans-serif"
                      font-size="13" font-weight="700" letter-spacing="1.5"
                      filter="url(#hapf-shadow)">
                    <tspan id="ha-pf-flow-main" x="<?php echo $sx; ?>" dy="0"></tspan>
                    <tspan id="ha-pf-flow-sub" x="<?php echo $sx; ?>" dy="18" font-size="11" opacity="0.8"></tspan>
                </text>

                <!-- ── Grid label block ──────────────────────────────────────── -->
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

                <!-- ── Load label block ──────────────────────────────────────── -->
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

                <?php if ( ! empty( $o['enable_solar'] ) ) : ?>
                <!-- ── PV label block ────────────────────────────────────────── -->
                <text id="ha-pf-pv-title"
                      x="<?php echo $px; ?>" y="<?php echo $py; ?>"
                      text-anchor="middle"
                      font-family="'Exo 2', sans-serif"
                      font-size="11" font-weight="700" letter-spacing="2"
                      filter="url(#hapf-shadow)">SOLAR</text>
                <text id="ha-pf-pv-power"
                      x="<?php echo $px; ?>" y="<?php echo $py + 24; ?>"
                      text-anchor="middle"
                      font-family="Orbitron, sans-serif" font-size="19" font-weight="700"
                      filter="url(#hapf-shadow)">—</text>
                <text id="ha-pf-pv-energy"
                      x="<?php echo $px; ?>" y="<?php echo $py + 44; ?>"
                      text-anchor="middle"
                      font-family="'Exo 2', sans-serif" font-size="12"
                      filter="url(#hapf-shadow)">—</text>
                <?php endif; ?>

                <?php if ( ! empty( $o['enable_battery'] ) ) : ?>
                <!-- ── Battery label block ───────────────────────────────────── -->
                <text id="ha-pf-battery-title"
                      x="<?php echo $bx; ?>" y="<?php echo $by; ?>"
                      text-anchor="middle"
                      font-family="'Exo 2', sans-serif"
                      font-size="11" font-weight="700" letter-spacing="2"
                      filter="url(#hapf-shadow)">BATTERY</text>
                <text id="ha-pf-battery-power"
                      x="<?php echo $bx; ?>" y="<?php echo $by + 24; ?>"
                      text-anchor="middle"
                      font-family="Orbitron, sans-serif" font-size="19" font-weight="700"
                      filter="url(#hapf-shadow)">—</text>
                <text id="ha-pf-battery-soc"
                      x="<?php echo $bx; ?>" y="<?php echo $by + 44; ?>"
                      text-anchor="middle"
                      font-family="'Exo 2', sans-serif" font-size="12"
                      filter="url(#hapf-shadow)">—</text>
                <?php endif; ?>

                <?php if ( ! empty( $o['enable_ev'] ) ) : ?>
                <!-- ── EV label block ────────────────────────────────────────── -->
                <text id="ha-pf-ev-title"
                      x="<?php echo $ex; ?>" y="<?php echo $ey; ?>"
                      text-anchor="middle"
                      font-family="'Exo 2', sans-serif"
                      font-size="11" font-weight="700" letter-spacing="2"
                      filter="url(#hapf-shadow)">EV</text>
                <text id="ha-pf-ev-power"
                      x="<?php echo $ex; ?>" y="<?php echo $ey + 24; ?>"
                      text-anchor="middle"
                      font-family="Orbitron, sans-serif" font-size="19" font-weight="700"
                      filter="url(#hapf-shadow)">—</text>
                <text id="ha-pf-ev-soc"
                      x="<?php echo $ex; ?>" y="<?php echo $ey + 44; ?>"
                      text-anchor="middle"
                      font-family="'Exo 2', sans-serif" font-size="12"
                      filter="url(#hapf-shadow)">—</text>
                <?php endif; ?>

                <?php if ( ! empty( $o['enable_heatpump'] ) ) : ?>
                <!-- ── Heat Pump label block ─────────────────────────────────── -->
                <text id="ha-pf-heatpump-title"
                      x="<?php echo $hx2; ?>" y="<?php echo $hy2; ?>"
                      text-anchor="middle"
                      font-family="'Exo 2', sans-serif"
                      font-size="11" font-weight="700" letter-spacing="2"
                      filter="url(#hapf-shadow)">HEAT PUMP</text>
                <text id="ha-pf-heatpump-power"
                      x="<?php echo $hx2; ?>" y="<?php echo $hy2 + 24; ?>"
                      text-anchor="middle"
                      font-family="Orbitron, sans-serif" font-size="19" font-weight="700"
                      filter="url(#hapf-shadow)">—</text>
                <text id="ha-pf-heatpump-efficiency"
                      x="<?php echo $hx2; ?>" y="<?php echo $hy2 + 44; ?>"
                      text-anchor="middle"
                      font-family="'Exo 2', sans-serif" font-size="12"
                      filter="url(#hapf-shadow)">—</text>
                <?php endif; ?>

                <?php if ( is_admin() || ! empty( $o['enable_weather'] ) ) : ?>
                <text id="ha-pf-weather"
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
                        if ( empty( $item['visible'] ) ) continue;
                        $cx = (int) $item['x'];
                        $cy = (int) $item['y'];
                        $label = esc_html( $item['label'] );
                        ?>
                        <g class="ha-pf-custom-entity" id="ha-pf-custom-<?php echo $index; ?>" transform="translate(<?php echo $cx; ?>, <?php echo $cy; ?>)">
                            <text text-anchor="middle"
                                  font-family="'Exo 2', sans-serif" font-size="11" font-weight="700" letter-spacing="2"
                                  filter="url(#hapf-shadow)"><?php echo strtoupper($label); ?></text>
                            <text class="ha-pf-custom-value" dy="24" text-anchor="middle"
                                  font-family="Orbitron, sans-serif" font-size="19" font-weight="700"
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
