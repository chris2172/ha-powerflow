<?php
/**
 * includes/shortcode.php
 *
 * Registers the [ha_powerflow] shortcode.
 * All output is sanitised / escaped before being written to HTML.
 * The HA token and URL are never passed to JavaScript.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'ha_powerflow', 'ha_pf_shortcode' );

// -------------------------------------------------------
// Helpers
// -------------------------------------------------------

/**
 * Return a saved option value, or $default if not set / empty.
 * Uses WordPress's built-in object cache automatically.
 */
function ha_pf_opt( $key, $default = '' ) {
    $val = get_option( HA_PF_OPT_PRE . $key );
    return ( $val !== false && $val !== '' ) ? $val : $default;
}

/**
 * Return a validated SVG path string from the database, or $default.
 * Must start with M/m and contain only legal SVG path characters.
 */
function ha_pf_path( $key, $default ) {
    $val = trim( (string) get_option( HA_PF_OPT_PRE . $key ) );

    if ( $val === '' ) {
        return $default;
    }

    // Must start with a Move-to command
    if ( ! preg_match( '/^[Mm]/', $val ) ) {
        return $default;
    }

    // Must contain only SVG path characters
    if ( ! preg_match( '/^[MmLlHhVvCcSsQqTtAaZz0-9\s,.\-]+$/', $val ) ) {
        return $default;
    }

    return $val;
}

/**
 * Return a validated integer option (for rotation, which can be negative).
 */
function ha_pf_int( $key, $default ) {
    $val = get_option( HA_PF_OPT_PRE . $key );
    return ( $val !== false && $val !== '' ) ? intval( $val ) : intval( $default );
}

/**
 * Return a validated positive integer option (for x/y positions).
 */
function ha_pf_pos( $key, $default ) {
    $val = get_option( HA_PF_OPT_PRE . $key );
    return ( $val !== false && $val !== '' ) ? absint( $val ) : absint( $default );
}

// -------------------------------------------------------
// Shortcode
// -------------------------------------------------------

function ha_pf_shortcode() {

    // Unique ID so multiple shortcodes on one page don't clash
    $uid = 'ha_pf_' . wp_unique_id();

    // Feature toggles
    $solar   = ( ha_pf_opt( 'enable_solar' )   === '1' );
    $battery = ( ha_pf_opt( 'enable_battery' ) === '1' );
    $ev      = ( ha_pf_opt( 'enable_ev' )      === '1' );

    // Battery gauge widget
    $bat_gauge        = ( ha_pf_opt( 'battery_gauge_enable' ) === '1' ) && $battery;
    $bat_gauge_x      = ha_pf_pos( 'battery_gauge_x', 500 );
    $bat_gauge_y      = ha_pf_pos( 'battery_gauge_y', 375 );

    // Background image
    $default_img = HA_PF_URL . 'assets/ha-powerflow.png';
    $image_url   = ha_pf_opt( 'image_url', $default_img );
    if ( ! $image_url ) {
        $image_url = $default_img;
    }

    // Colours (already validated in settings-register.php)
    $text_colour = ha_pf_opt( 'text_colour', '#5EC766' );
    $line_colour = ha_pf_opt( 'line_colour', '#5EC766' );
    $dot_colour  = ha_pf_opt( 'dot_colour',  '#5EC766' );

    // SVG flow paths — saved value or built-in default
    $paths = [
        'grid_fwd' => ha_pf_path( 'grid_flow_forward',    'M 787 366 L 805 375 L 633 439' ),
        'grid_rev' => ha_pf_path( 'grid_flow_reverse',    'M 633 439 L 805 375 L 787 366' ),
        'load_fwd' => ha_pf_path( 'load_flow_forward',    'M 590 427 L 673 396 L 612 369' ),
        'load_rev' => ha_pf_path( 'load_flow_reverse',    'M 590 427 L 673 396 L 612 369' ),
        'pv_fwd'   => ha_pf_path( 'pv_flow_forward',      'M 331 417 L 510 486' ),
        'pv_rev'   => ha_pf_path( 'pv_flow_reverse',      'M 510 486 L 331 417' ),
        'bat_fwd'  => ha_pf_path( 'battery_flow_forward', 'M 532 500 L 364 563' ),
        'bat_rev'  => ha_pf_path( 'battery_flow_reverse', 'M 364 563 L 532 500' ),
        'ev_fwd'   => ha_pf_path( 'ev_flow_forward',      'M 618 497 L 713 532 L 786 499' ),
        'ev_rev'   => ha_pf_path( 'ev_flow_reverse',      'M 786 499 L 713 532 L 618 497' ),
    ];

    // Entity IDs passed to JS (not the token — that stays server-side)
    $entities = [];

    $all_keys = [
        'grid_power', 'grid_energy_in', 'grid_energy_out',
        'load_power', 'load_energy',
    ];
    if ( $solar )   { $all_keys[] = 'pv_power';           $all_keys[] = 'pv_energy'; }
    if ( $battery ) { $all_keys[] = 'battery_power';      $all_keys[] = 'battery_energy_in';
                      $all_keys[] = 'battery_energy_out'; $all_keys[] = 'battery_soc'; }
    if ( $ev )      { $all_keys[] = 'ev_power';           $all_keys[] = 'ev_soc'; }

    foreach ( $all_keys as $key ) {
        $entities[ $key ] = ha_pf_opt( $key );
    }

    // Label text displayed next to each entity value
    $labels = [
        'grid_power'         => 'Grid',
        'grid_energy_in'     => 'Grid In',
        'grid_energy_out'    => 'Grid Out',
        'load_power'         => 'Load',
        'load_energy'        => 'Load Energy',
        'pv_power'           => 'PV',
        'pv_energy'          => 'PV Energy',
        'battery_power'      => 'Battery',
        'battery_energy_in'  => 'Battery In',
        'battery_energy_out' => 'Battery Out',
        'battery_soc'        => 'Battery SOC',
        'ev_power'           => 'EV',
        'ev_soc'             => 'SOC',
    ];

    // Text label position defaults [rot, x, y]
    $pos_defaults = [
        'grid_power'         => [  0, 740, 210 ],
        'grid_energy_in'     => [  0, 740,  70 ],
        'grid_energy_out'    => [  0, 740,  90 ],
        'load_power'         => [ -6, 360, 180 ],
        'load_energy'        => [  0, 360,  70 ],
        'pv_power'           => [ -9,  49, 312 ],
        'pv_energy'          => [  0,  14,  70 ],
        'battery_power'      => [  0, 360, 665 ],
        'battery_energy_in'  => [  0,  14, 685 ],
        'battery_energy_out' => [  0,  14, 665 ],
        'battery_soc'        => [  0, 360, 685 ],
        'ev_power'           => [ 15, 750, 460 ],
        'ev_soc'             => [ 15, 750, 480 ],
    ];

    // Nonce for the AJAX proxy (safe to expose — action-locked, 12hr expiry)
    $nonce    = wp_create_nonce( 'ha_pf_proxy' );
    $ajax_url = admin_url( 'admin-ajax.php' );

    // Build the position data array for JS — resolved from DB with defaults
    $positions = [];
    foreach ( $pos_defaults as $key => [ $def_rot, $def_x, $def_y ] ) {
        $positions[ $key ] = [
            'rot' => ha_pf_int( $key . '_rot',   $def_rot ),
            'x'   => ha_pf_pos( $key . '_x_pos', $def_x ),
            'y'   => ha_pf_pos( $key . '_y_pos', $def_y ),
        ];
    }

    ob_start();
    ?>
    <style>
        #ha-pf-wrapper-<?php echo esc_attr( $uid ); ?> {
            position: relative;
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
        }
        #ha-pf-svg-<?php echo esc_attr( $uid ); ?> {
            width: 100%;
            height: auto;
            display: block;
        }
        #ha-pf-svg-<?php echo esc_attr( $uid ); ?> text {
            fill: <?php echo esc_attr( $text_colour ); ?>;
            font-family: sans-serif;
        }
        #ha-pf-svg-<?php echo esc_attr( $uid ); ?> .ha-pf-line {
            stroke: <?php echo esc_attr( $line_colour ); ?>;
            stroke-width: 2;
            stroke-opacity: 0.35;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        #ha-pf-svg-<?php echo esc_attr( $uid ); ?> .ha-pf-dot {
            fill: <?php echo esc_attr( $dot_colour ); ?>;
            offset-rotate: auto;
            animation-name: ha-pf-flow-<?php echo esc_attr( $uid ); ?>;
            animation-timing-function: linear;
            animation-iteration-count: infinite;
            animation-play-state: paused;
            offset-distance: 0%;
        }
        @keyframes ha-pf-flow-<?php echo esc_attr( $uid ); ?> {
            from { offset-distance: 0%; }
            to   { offset-distance: 100%; }
        }
    </style>

    <div id="ha-pf-wrapper-<?php echo esc_attr( $uid ); ?>">
    <svg id="ha-pf-svg-<?php echo esc_attr( $uid ); ?>"
         viewBox="0 0 1000 750"
         preserveAspectRatio="xMidYMid meet">

        <!-- Background image -->
        <image
            href="<?php echo esc_url( $image_url ); ?>"
            x="0" y="0" width="1000" height="750" />

        <!-- Grid flow paths -->
        <path class="ha-pf-line" id="ha-pf-grid-fwd-<?php echo esc_attr( $uid ); ?>"
              d="<?php echo esc_attr( $paths['grid_fwd'] ); ?>" />
        <path class="ha-pf-line" id="ha-pf-grid-rev-<?php echo esc_attr( $uid ); ?>"
              d="<?php echo esc_attr( $paths['grid_rev'] ); ?>" />
        <circle class="ha-pf-dot" id="ha-pf-grid-dot-<?php echo esc_attr( $uid ); ?>" r="6" />

        <!-- Load flow paths -->
        <path class="ha-pf-line" id="ha-pf-load-fwd-<?php echo esc_attr( $uid ); ?>"
              d="<?php echo esc_attr( $paths['load_fwd'] ); ?>" />
        <path class="ha-pf-line" id="ha-pf-load-rev-<?php echo esc_attr( $uid ); ?>"
              d="<?php echo esc_attr( $paths['load_rev'] ); ?>" />
        <circle class="ha-pf-dot" id="ha-pf-load-dot-<?php echo esc_attr( $uid ); ?>" r="6" />

        <?php if ( $solar ) : ?>
        <!-- PV / Solar flow paths -->
        <path class="ha-pf-line" id="ha-pf-pv-fwd-<?php echo esc_attr( $uid ); ?>"
              d="<?php echo esc_attr( $paths['pv_fwd'] ); ?>" />
        <path class="ha-pf-line" id="ha-pf-pv-rev-<?php echo esc_attr( $uid ); ?>"
              d="<?php echo esc_attr( $paths['pv_rev'] ); ?>" />
        <circle class="ha-pf-dot" id="ha-pf-pv-dot-<?php echo esc_attr( $uid ); ?>" r="6" />
        <?php endif; ?>

        <?php if ( $battery ) : ?>
        <!-- Battery flow paths -->
        <path class="ha-pf-line" id="ha-pf-bat-fwd-<?php echo esc_attr( $uid ); ?>"
              d="<?php echo esc_attr( $paths['bat_fwd'] ); ?>" />
        <path class="ha-pf-line" id="ha-pf-bat-rev-<?php echo esc_attr( $uid ); ?>"
              d="<?php echo esc_attr( $paths['bat_rev'] ); ?>" />
        <circle class="ha-pf-dot" id="ha-pf-bat-dot-<?php echo esc_attr( $uid ); ?>" r="6" />
        <?php endif; ?>

        <?php if ( $ev ) : ?>
        <!-- EV flow paths -->
        <path class="ha-pf-line" id="ha-pf-ev-fwd-<?php echo esc_attr( $uid ); ?>"
              d="<?php echo esc_attr( $paths['ev_fwd'] ); ?>" />
        <path class="ha-pf-line" id="ha-pf-ev-rev-<?php echo esc_attr( $uid ); ?>"
              d="<?php echo esc_attr( $paths['ev_rev'] ); ?>" />
        <circle class="ha-pf-dot" id="ha-pf-ev-dot-<?php echo esc_attr( $uid ); ?>" r="6" />
        <?php endif; ?>

        <?php if ( $bat_gauge ) : ?>
        <!-- Battery gauge widget — rings built and updated by JS -->
        <g id="ha-pf-bat-gauge-<?php echo esc_attr( $uid ); ?>"></g>
        <?php endif; ?>

    </svg>
    </div>

    <script>
    (function () {
        'use strict';

        // -----------------------------------------------
        // Config — all values baked in server-side.
        // The HA token and URL are NOT here.
        // -----------------------------------------------
        const UID      = <?php echo wp_json_encode( $uid ); ?>;
        const AJAX_URL = <?php echo wp_json_encode( $ajax_url ); ?>;
        const NONCE    = <?php echo wp_json_encode( $nonce ); ?>;
        const SOLAR    = <?php echo $solar   ? 'true' : 'false'; ?>;
        const BATTERY  = <?php echo $battery ? 'true' : 'false'; ?>;
        const EV       = <?php echo $ev      ? 'true' : 'false'; ?>;
        const DEBUG_CLICK    = <?php echo ( ha_pf_opt( 'debug_click' ) === '1' ) ? 'true' : 'false'; ?>;
        const BATTERY_GAUGE  = <?php echo $bat_gauge ? 'true' : 'false'; ?>;
        const GAUGE_CX       = <?php echo (int) $bat_gauge_x; ?>;
        const GAUGE_CY       = <?php echo (int) $bat_gauge_y; ?>;

        const ENTITIES  = <?php echo wp_json_encode( $entities ); ?>;
        const LABELS    = <?php echo wp_json_encode( $labels ); ?>;
        const POSITIONS = <?php echo wp_json_encode( $positions ); ?>;

        const PATHS = {
            grid: { fwd: <?php echo wp_json_encode( $paths['grid_fwd'] ); ?>,
                    rev: <?php echo wp_json_encode( $paths['grid_rev'] ); ?> },
            load: { fwd: <?php echo wp_json_encode( $paths['load_fwd'] ); ?>,
                    rev: <?php echo wp_json_encode( $paths['load_rev'] ); ?> },
            pv:   { fwd: <?php echo wp_json_encode( $paths['pv_fwd'] ); ?>,
                    rev: <?php echo wp_json_encode( $paths['pv_rev'] ); ?> },
            bat:  { fwd: <?php echo wp_json_encode( $paths['bat_fwd'] ); ?>,
                    rev: <?php echo wp_json_encode( $paths['bat_rev'] ); ?> },
            ev:   { fwd: <?php echo wp_json_encode( $paths['ev_fwd'] ); ?>,
                    rev: <?php echo wp_json_encode( $paths['ev_rev'] ); ?> },
        };

        // -----------------------------------------------
        // DOM references
        // -----------------------------------------------
        const svg = document.getElementById( 'ha-pf-svg-' + UID );
        if ( ! svg ) return;   // shortcode not rendered properly

        // -----------------------------------------------
        // AJAX proxy fetch
        // The browser never talks directly to Home Assistant.
        // -----------------------------------------------
        async function fetchEntity( entityId ) {
            try {
                const res = await fetch( AJAX_URL, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body:    new URLSearchParams({
                        action: 'ha_pf_proxy',
                        nonce:  NONCE,
                        entity: entityId,
                    }).toString(),
                } );
                if ( ! res.ok ) return null;
                const json = await res.json();
                return json.success ? json.data : null;
            } catch ( e ) {
                return null;
            }
        }

        // -----------------------------------------------
        // Text label creation
        // -----------------------------------------------
        function createLabels() {
            Object.keys( ENTITIES ).forEach( key => {
                const t = document.createElementNS( 'http://www.w3.org/2000/svg', 'text' );
                t.setAttribute( 'id', 'ha-pf-txt-' + key + '-' + UID );
                t.setAttribute( 'font-size', '18' );
                t.textContent = ( LABELS[ key ] || key ).toUpperCase() + ': …';
                svg.appendChild( t );
            } );
        }

        // -----------------------------------------------
        // Text label positioning
        // -----------------------------------------------
        function positionLabels() {
            Object.keys( ENTITIES ).forEach( key => {
                const el = document.getElementById( 'ha-pf-txt-' + key + '-' + UID );
                if ( ! el ) return;

                const pos = POSITIONS[ key ];
                if ( ! pos ) return;

                el.setAttribute( 'x', pos.x );
                el.setAttribute( 'y', pos.y );
                if ( pos.rot !== 0 ) {
                    el.setAttribute( 'transform',
                        'rotate(' + pos.rot + ' ' + pos.x + ' ' + pos.y + ')' );
                }
            } );
        }

        // -----------------------------------------------
        // Dot animation helpers
        // -----------------------------------------------
        function setDot( dotId, fwdPath, revPath, power ) {
            const dot = document.getElementById( dotId );
            if ( ! dot ) return;

            dot.style.offsetPath = 'path("' + ( power >= 0 ? fwdPath : revPath ) + '")';

            if ( power === 0 ) {
                dot.style.animationPlayState = 'paused';
                dot.style.offsetDistance    = '0%';
            } else {
                dot.style.animationPlayState = 'running';
            }
        }

        function setDotSpeed( dotId, watts ) {
            const dot = document.getElementById( dotId );
            if ( ! dot ) return;

            const abs = Math.abs( watts );
            let dur;

            if      ( abs === 0    ) { dot.style.animationPlayState = 'paused'; return; }
            else if ( abs <= 100   ) { dur = 12;  }
            else if ( abs <= 500   ) { dur = 8;   }
            else if ( abs <= 1000  ) { dur = 5;   }
            else if ( abs <= 2000  ) { dur = 3;   }
            else                     { dur = 1.5; }

            const newDur = dur + 's';
            if ( dot.style.animationDuration !== newDur ) {
                dot.style.animationDuration = newDur;
            }
        }

        function animateLine( prefix, pathKey, power ) {
            const dotId = 'ha-pf-' + prefix + '-dot-' + UID;
            setDot( dotId, PATHS[ pathKey ].fwd, PATHS[ pathKey ].rev, power );
            setDotSpeed( dotId, power );
        }

        // Load only ever flows from inverter → load (never reverse)
        function animateLoad( power ) {
            const dotId = 'ha-pf-load-dot-' + UID;
            const dot   = document.getElementById( dotId );
            if ( ! dot ) return;

            if ( power <= 0 ) {
                dot.style.animationPlayState = 'paused';
                dot.style.offsetDistance    = '0%';
                return;
            }
            dot.style.offsetPath        = 'path("' + PATHS.load.fwd + '")';
            dot.style.animationPlayState = 'running';
            setDotSpeed( dotId, power );
        }

        // -----------------------------------------------
        // Battery gauge widget
        // Two-ring SVG gauge: outer ring = SOC arc, inner circle = power colour.
        // Only rendered when BATTERY_GAUGE is true.
        // -----------------------------------------------
        (function initBatteryGauge() {
            if ( ! BATTERY_GAUGE ) return;

            const g = document.getElementById( 'ha-pf-bat-gauge-' + UID );
            if ( ! g ) return;

            const R_OUTER = 52;   // radius of SOC ring
            const R_INNER = 36;   // radius of power fill circle
            const STROKE  = 8;    // ring stroke width
            const CX = GAUGE_CX;
            const CY = GAUGE_CY;

            // ── background track (full circle, dimmed) ──
            const track = document.createElementNS( 'http://www.w3.org/2000/svg', 'circle' );
            track.setAttribute( 'cx', CX );
            track.setAttribute( 'cy', CY );
            track.setAttribute( 'r',  R_OUTER );
            track.setAttribute( 'fill', 'none' );
            track.setAttribute( 'stroke', 'rgba(255,255,255,0.15)' );
            track.setAttribute( 'stroke-width', STROKE );
            g.appendChild( track );

            // ── SOC arc ──
            const circumference = 2 * Math.PI * R_OUTER;
            const arc = document.createElementNS( 'http://www.w3.org/2000/svg', 'circle' );
            arc.setAttribute( 'cx', CX );
            arc.setAttribute( 'cy', CY );
            arc.setAttribute( 'r',  R_OUTER );
            arc.setAttribute( 'fill', 'none' );
            arc.setAttribute( 'stroke', '#5EC766' );
            arc.setAttribute( 'stroke-width', STROKE );
            arc.setAttribute( 'stroke-linecap', 'round' );
            arc.setAttribute( 'stroke-dasharray', circumference );
            arc.setAttribute( 'stroke-dashoffset', circumference );     // starts empty
            arc.setAttribute( 'transform', 'rotate(-90 ' + CX + ' ' + CY + ')' );
            arc.style.transition = 'stroke-dashoffset 0.8s ease, stroke 0.4s ease';
            g.appendChild( arc );

            // ── inner power fill circle ──
            const inner = document.createElementNS( 'http://www.w3.org/2000/svg', 'circle' );
            inner.setAttribute( 'cx', CX );
            inner.setAttribute( 'cy', CY );
            inner.setAttribute( 'r',  R_INNER );
            inner.setAttribute( 'fill', 'rgba(0,0,0,0.55)' );
            inner.style.transition = 'fill 0.4s ease';
            g.appendChild( inner );

            // ── SOC label (outer) ──
            const socText = document.createElementNS( 'http://www.w3.org/2000/svg', 'text' );
            socText.setAttribute( 'id', 'ha-pf-gauge-soc-' + UID );
            socText.setAttribute( 'x', CX );
            socText.setAttribute( 'y', CY - 10 );
            socText.setAttribute( 'text-anchor', 'middle' );
            socText.setAttribute( 'font-size', '14' );
            socText.setAttribute( 'font-weight', 'bold' );
            socText.setAttribute( 'fill', '#ffffff' );
            socText.textContent = '–%';
            g.appendChild( socText );

            // ── power label (inner) ──
            const pwrText = document.createElementNS( 'http://www.w3.org/2000/svg', 'text' );
            pwrText.setAttribute( 'id', 'ha-pf-gauge-pwr-' + UID );
            pwrText.setAttribute( 'x', CX );
            pwrText.setAttribute( 'y', CY + 8 );
            pwrText.setAttribute( 'text-anchor', 'middle' );
            pwrText.setAttribute( 'font-size', '11' );
            pwrText.setAttribute( 'fill', '#cccccc' );
            pwrText.textContent = '–';
            g.appendChild( pwrText );

            // ── "BAT" label below the ring ──
            const label = document.createElementNS( 'http://www.w3.org/2000/svg', 'text' );
            label.setAttribute( 'x', CX );
            label.setAttribute( 'y', CY + R_OUTER + STROKE + 14 );
            label.setAttribute( 'text-anchor', 'middle' );
            label.setAttribute( 'font-size', '11' );
            label.setAttribute( 'fill', 'rgba(255,255,255,0.6)' );
            label.setAttribute( 'letter-spacing', '2' );
            label.textContent = 'BATTERY';
            g.appendChild( label );

            // Store references for the update function
            g._arc         = arc;
            g._inner       = inner;
            g._circumf     = circumference;
        }());

        function updateBatteryGauge( soc, watts ) {
            if ( ! BATTERY_GAUGE ) return;

            const g = document.getElementById( 'ha-pf-bat-gauge-' + UID );
            if ( ! g || ! g._arc ) return;

            const circumf = g._circumf;

            // SOC arc — clamp 0-100
            const pct    = Math.min( 100, Math.max( 0, parseFloat( soc ) || 0 ) );
            const offset = circumf * ( 1 - pct / 100 );
            g._arc.setAttribute( 'stroke-dashoffset', offset );

            // Arc colour: green above 20%, amber 10-20%, red below 10%
            const arcColour = pct >= 20 ? '#5EC766' : pct >= 10 ? '#f59e0b' : '#ef4444';
            g._arc.setAttribute( 'stroke', arcColour );

            // Inner fill: green = charging (positive), red = discharging (negative)
            const w = parseFloat( watts );
            if ( ! isNaN( w ) && w !== 0 ) {
                g._inner.setAttribute( 'fill', w > 0 ? 'rgba(94,199,102,0.30)' : 'rgba(239,68,68,0.30)' );
            } else {
                g._inner.setAttribute( 'fill', 'rgba(0,0,0,0.55)' );
            }

            // SOC text
            const socEl = document.getElementById( 'ha-pf-gauge-soc-' + UID );
            if ( socEl ) socEl.textContent = pct.toFixed( 0 ) + '%';

            // Power text
            const pwrEl = document.getElementById( 'ha-pf-gauge-pwr-' + UID );
            if ( pwrEl ) {
                if ( isNaN( w ) ) {
                    pwrEl.textContent = '–';
                } else {
                    pwrEl.textContent = ( w > 0 ? '+' : '' ) + formatPower( w );
                    pwrEl.setAttribute( 'fill', w > 0 ? '#5EC766' : '#ef4444' );
                }
            }
        }

        // -----------------------------------------------
        // Power formatting helper (inside IIFE — no global leak)
        // -----------------------------------------------
        function formatPower( value ) {
            const n = parseFloat( value );
            if ( isNaN( n ) ) return value;
            return ( Math.abs( n ) >= 1000 )
                ? ( n / 1000 ).toFixed( 2 ) + ' kW'
                : n + ' W';
        }

        // -----------------------------------------------
        // Main update loop
        // -----------------------------------------------
        async function updateAll() {
            let gaugeSoc   = null;
            let gaugeWatts = null;

            for ( const key of Object.keys( ENTITIES ) ) {
                const entityId = ENTITIES[ key ];
                if ( ! entityId ) continue;

                const data = await fetchEntity( entityId );
                const el   = document.getElementById( 'ha-pf-txt-' + key + '-' + UID );
                if ( ! data || ! el ) continue;

                const label = LABELS[ key ] || key;
                const unit  = ( data.unit || '' ).toLowerCase();

                el.textContent = label + ': ' + (
                    unit === 'w' ? formatPower( data.state ) : data.state + ' ' + data.unit
                );

                const watts = parseFloat( data.state );
                if ( isNaN( watts ) ) continue;

                if ( key === 'grid_power'    ) animateLine( 'grid', 'grid', watts );
                if ( key === 'load_power'    ) animateLoad( watts );
                if ( key === 'pv_power'    && SOLAR   ) animateLine( 'pv',  'pv',  watts );
                if ( key === 'battery_power' && BATTERY ) {
                    animateLine( 'bat', 'bat', watts );
                    gaugeWatts = watts;
                }
                if ( key === 'battery_soc'   && BATTERY ) gaugeSoc = data.state;
                if ( key === 'ev_power'      && EV      ) animateLine( 'ev',  'ev',  watts );
            }

            // Update battery gauge if both values were received
            if ( BATTERY_GAUGE && gaugeSoc !== null ) {
                updateBatteryGauge( gaugeSoc, gaugeWatts !== null ? gaugeWatts : 0 );
            }
        }

        // -----------------------------------------------
        // Boot
        // -----------------------------------------------
        createLabels();
        positionLabels();
        updateAll();
        setInterval( updateAll, 5000 );

        // -----------------------------------------------
        // Click-to-coordinate tool
        // Only active when enabled in Settings > Developer Tools.
        // Clicking anywhere on the SVG logs the SVG-space x/y to
        // the browser console and drops a temporary red marker dot
        // so you can see exactly where you clicked.
        // Use this to find the correct x_pos / y_pos values for
        // label positions, then disable it when setup is complete.
        // -----------------------------------------------
        if ( DEBUG_CLICK ) {
            const markers = [];

            svg.style.cursor = 'crosshair';

            svg.addEventListener( 'click', function ( evt ) {
                const pt  = svg.createSVGPoint();
                pt.x = evt.clientX;
                pt.y = evt.clientY;

                const svgP = pt.matrixTransform( svg.getScreenCTM().inverse() );
                const x    = Math.round( svgP.x );
                const y    = Math.round( svgP.y );

                console.log( '%c[HA PowerFlow] Clicked at x: ' + x + ', y: ' + y,
                    'background:#1a1a2e; color:#5EC766; padding:2px 6px; border-radius:3px;' );

                // Drop a temporary marker dot at the click point
                const circle = document.createElementNS( 'http://www.w3.org/2000/svg', 'circle' );
                circle.setAttribute( 'cx', x );
                circle.setAttribute( 'cy', y );
                circle.setAttribute( 'r',  5 );
                circle.setAttribute( 'fill', '#ff4444' );
                circle.setAttribute( 'opacity', '0.85' );
                circle.setAttribute( 'pointer-events', 'none' );
                svg.appendChild( circle );
                markers.push( circle );

                // Also add a small coordinate label next to the dot
                const label = document.createElementNS( 'http://www.w3.org/2000/svg', 'text' );
                label.setAttribute( 'x', x + 8 );
                label.setAttribute( 'y', y + 4 );
                label.setAttribute( 'font-size', '14' );
                label.setAttribute( 'fill', '#ff4444' );
                label.setAttribute( 'pointer-events', 'none' );
                label.textContent = x + ', ' + y;
                svg.appendChild( label );
                markers.push( label );
            } );

            // Double-click clears all markers
            svg.addEventListener( 'dblclick', function ( evt ) {
                evt.preventDefault();
                markers.forEach( function ( el ) {
                    if ( el.parentNode ) el.parentNode.removeChild( el );
                } );
                markers.length = 0;
                console.log( '%c[HA PowerFlow] Markers cleared.',
                    'background:#1a1a2e; color:#5EC766; padding:2px 6px; border-radius:3px;' );
            } );

            console.log( '%c[HA PowerFlow] Click-to-coordinate tool is ACTIVE. ' +
                'Click to place markers. Double-click to clear all markers.',
                'background:#1a1a2e; color:#5EC766; font-weight:bold; padding:4px 8px; border-radius:3px;' );
        }

    }());
    </script>
    <?php

    return ob_get_clean();
}
