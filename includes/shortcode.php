<?php
if (!defined('ABSPATH')) exit;

// Helper: return saved value or default
function ha_pf_value($key, $default) {
    $val = get_option('ha_powerflow_' . $key);
    return ($val !== '' && $val !== null) ? $val : $default;
}

/**
 * Validate an SVG path 'd' attribute value.
 * Must start with M/m and contain only valid SVG path characters.
 * Returns the value if valid, or the default if not.
 */
function ha_pf_path($key, $default) {
    $val = get_option('ha_powerflow_' . $key);

    if ($val === '' || $val === null || $val === false) {
        return $default;
    }

    $val = trim($val);

    if (!preg_match('/^[Mm]/', $val)) {
        return $default;
    }

    if (!preg_match('/^[MmLlHhVvCcSsQqTtAaZz0-9\s,.\-]+$/', $val)) {
        return $default;
    }

    return $val;
}

/* SECTION: Shortcode Wrapper */
function ha_powerflow_shortcode() {

    /* SECTION: Instance + Feature Toggles */
    $instance_id = uniqid('ha_pf_');

    $solar_enabled   = get_option('ha_powerflow_enable_solar') === '1';
    $battery_enabled = get_option('ha_powerflow_enable_battery') === '1';
    $ev_enabled      = get_option('ha_powerflow_enable_ev') === '1';

    /* SECTION: Image Handling */
    $default_image = plugin_dir_url(dirname(__FILE__)) . 'assets/ha-powerflow.png';
    $image_url = get_option('ha_powerflow_image_url', $default_image);
    if (!$image_url) {
        $image_url = $default_image;
    }

    /* SECTION: Load Entity Settings
     * NOTE: ha_url and ha_token are intentionally excluded here.
     * They are only used server-side in ajax-proxy.php and are
     * never passed to JavaScript or rendered in page HTML.
     */
    $settings = [];
    $keys = [
        'pv_power','pv_energy','load_power','load_energy',
        'grid_power','grid_energy_in','grid_energy_out','battery_power',
        'battery_energy_in','battery_energy_out','battery_soc','ev_power','ev_soc'
    ];

    foreach ($keys as $key) {
        $settings[$key] = get_option('ha_powerflow_' . $key);
    }

    /* SECTION: Colour Settings */
    $text_colour = sanitize_hex_color(get_option('ha_powerflow_text_colour', '#5EC766')) ?: '#5EC766';
    $line_colour = sanitize_hex_color(get_option('ha_powerflow_line_colour', '#5EC766')) ?: '#5EC766';
    $dot_colour  = sanitize_hex_color(get_option('ha_powerflow_dot_colour',  '#5EC766')) ?: '#5EC766';

    /* SECTION: Flow Path Defaults + Settings Resolution */
    $paths = [
        'grid_forward'    => ha_pf_path('grid_flow_forward',    'M 787 366 L 805 375 L 633 439'),
        'grid_reverse'    => ha_pf_path('grid_flow_reverse',    'M 633 439 L 805 375 L 787 366'),
        'ev_forward'      => ha_pf_path('ev_flow_forward',      'M 618 497 L 713 532 L 786 499'),
        'ev_reverse'      => ha_pf_path('ev_flow_reverse',      'M 786 499 L 713 532 L 618 497'),
        'battery_forward' => ha_pf_path('battery_flow_forward', 'M 532 500 L 364 563'),
        'battery_reverse' => ha_pf_path('battery_flow_reverse', 'M 364 563 L 532 500'),
        'pv_forward'      => ha_pf_path('pv_flow_forward',      'M 331 417 L 510 486'),
        'pv_reverse'      => ha_pf_path('pv_flow_reverse',      'M 510 486 L 331 417'),
        'load_forward'    => ha_pf_path('load_flow_forward',    'M 590 427 L 673 396 L 612 369'),
        'load_reverse'    => ha_pf_path('load_flow_reverse',    'M 590 427 L 673 396 L 612 369'),
    ];

    /* SECTION: Generate Nonce for AJAX Proxy
     * This nonce is safe to expose in HTML — it is tied to a specific
     * action ('ha_powerflow_proxy') and expires after 12 hours.
     * It cannot be used to access anything other than the proxy endpoint.
     */
    $nonce = wp_create_nonce('ha_powerflow_proxy');

    ob_start();
?>
<style>
    svg text {
        fill: <?php echo esc_attr($text_colour); ?>;
    }

    #ha-powerflow-wrapper-<?php echo esc_attr($instance_id); ?> {
        position: relative;
        width: 100%;
        max-width: 1000px;
        margin: 0 auto;
        --ha-pf-line-colour: <?php echo esc_attr($line_colour); ?>;
    }

    #ha-powerflow-svg-<?php echo esc_attr($instance_id); ?> {
        width: 100%;
        height: auto;
        display: block;
    }

    #ha-powerflow-svg-<?php echo esc_attr($instance_id); ?> .ha-flow-path {
        stroke: var(--ha-pf-line-colour);
    }
</style>

<div id="ha-powerflow-wrapper-<?php echo esc_attr($instance_id); ?>">
<svg id="ha-powerflow-svg-<?php echo esc_attr($instance_id); ?>"
     viewBox="0 0 1000 750"
     preserveAspectRatio="xMidYMid meet">

<style>
    .flow-dot {
        fill: <?php echo esc_attr($dot_colour); ?>;
        offset-rotate: auto;
        animation-name: flowMove;
        animation-timing-function: linear;
        animation-iteration-count: infinite;
        animation-play-state: paused;
        offset-distance: 0%;
    }

    @keyframes flowMove {
        from { offset-distance: 0%; }
        to   { offset-distance: 100%; }
    }
</style>

<image 
    href="<?php echo esc_url($image_url); ?>"
    xlink:href="<?php echo esc_url($image_url); ?>"
    x="0" y="0" width="1000" height="750" 
/>

<defs>
    <filter id="energyGlow" x="-50%" y="-50%" width="200%" height="200%">
        <feGaussianBlur in="SourceGraphic" stdDeviation="4" result="blur"/>
        <feMerge>
            <feMergeNode in="blur"/>
            <feMergeNode in="SourceGraphic"/>
        </feMerge>
    </filter>

    <linearGradient id="energyFlowGradient"
                    x1="787" y1="366"
                    x2="633" y2="439"
                    gradientUnits="userSpaceOnUse">
        <stop offset="0%" stop-color="<?php echo esc_attr($line_colour); ?>" stop-opacity="0.9"/>
        <stop offset="100%" stop-color="<?php echo esc_attr($line_colour); ?>" stop-opacity="0.3"/>
    </linearGradient>
</defs>

<rect width="1000" height="750" fill="url(#grid-<?php echo esc_attr($instance_id); ?>)" opacity="0.25"></rect>

<!-- SECTION: Grid Paths -->
<path id="line_grid_forward_<?php echo esc_attr($instance_id); ?>"
      d="<?php echo esc_attr($paths['grid_forward']); ?>"
      class="ha-flow-path" stroke-width="2" stroke-opacity="0.35"
      fill="none" stroke-linecap="round" stroke-linejoin="round" />

<path id="line_grid_reverse_<?php echo esc_attr($instance_id); ?>"
      d="<?php echo esc_attr($paths['grid_reverse']); ?>"
      class="ha-flow-path" stroke-width="2" stroke-opacity="0.35"
      fill="none" stroke-linecap="round" stroke-linejoin="round" />

<circle id="line_grid_dot_<?php echo esc_attr($instance_id); ?>"
        r="6" class="flow-dot" />

<?php if ($ev_enabled): ?>
<!-- SECTION: EV Paths -->
<path id="line_ev_forward_<?php echo esc_attr($instance_id); ?>"
      d="<?php echo esc_attr($paths['ev_forward']); ?>"
      class="ha-flow-path" stroke-width="2" stroke-opacity="0.35"
      fill="none" stroke-linecap="round" stroke-linejoin="round" />

<path id="line_ev_reverse_<?php echo esc_attr($instance_id); ?>"
      d="<?php echo esc_attr($paths['ev_reverse']); ?>"
      class="ha-flow-path" stroke-width="2" stroke-opacity="0.35"
      fill="none" stroke-linecap="round" stroke-linejoin="round" />

<circle id="line_inverter_to_ev_dot_<?php echo esc_attr($instance_id); ?>"
        r="6" class="flow-dot" />
<?php endif; ?>

<?php if ($battery_enabled): ?>
<!-- SECTION: Battery Paths -->
<path id="line_battery_forward_<?php echo esc_attr($instance_id); ?>"
      d="<?php echo esc_attr($paths['battery_forward']); ?>"
      class="ha-flow-path" stroke-width="2" stroke-opacity="0.35"
      fill="none" stroke-linecap="round" stroke-linejoin="round" />

<path id="line_battery_reverse_<?php echo esc_attr($instance_id); ?>"
      d="<?php echo esc_attr($paths['battery_reverse']); ?>"
      class="ha-flow-path" stroke-width="2" stroke-opacity="0.35"
      fill="none" stroke-linecap="round" stroke-linejoin="round" />

<circle id="line_inverter_to_battery_dot_<?php echo esc_attr($instance_id); ?>"
        r="6" class="flow-dot" />
<?php endif; ?>

<?php if ($solar_enabled): ?>
<!-- SECTION: PV Paths -->
<path id="line_pv_forward_<?php echo esc_attr($instance_id); ?>"
      d="<?php echo esc_attr($paths['pv_forward']); ?>"
      class="ha-flow-path" stroke-width="2" stroke-opacity="0.35"
      fill="none" stroke-linecap="round" stroke-linejoin="round" />

<path id="line_pv_reverse_<?php echo esc_attr($instance_id); ?>"
      d="<?php echo esc_attr($paths['pv_reverse']); ?>"
      class="ha-flow-path" stroke-width="2" stroke-opacity="0.35"
      fill="none" stroke-linecap="round" stroke-linejoin="round" />

<circle id="line_pv_to_inverter_dot_<?php echo esc_attr($instance_id); ?>"
        r="6" class="flow-dot" />
<?php endif; ?>

<!-- SECTION: Load Paths -->
<path id="line_load_forward_<?php echo esc_attr($instance_id); ?>"
      d="<?php echo esc_attr($paths['load_forward']); ?>"
      class="ha-flow-path" stroke-width="2" stroke-opacity="0.35"
      fill="none" stroke-linecap="round" stroke-linejoin="round" />

<path id="line_load_reverse_<?php echo esc_attr($instance_id); ?>"
      d="<?php echo esc_attr($paths['load_reverse']); ?>"
      class="ha-flow-path" stroke-width="2" stroke-opacity="0.35"
      fill="none" stroke-linecap="round" stroke-linejoin="round" />

<circle id="line_inverter_to_load_dot_<?php echo esc_attr($instance_id); ?>"
        r="6" class="flow-dot" />

</svg>
</div>

<script>
/* SECTION: Main JS Wrapper */
(function() {

    /* SECTION: Instance + SVG Reference */
    const instanceId = "<?php echo esc_js($instance_id); ?>";
    const svg = document.getElementById("ha-powerflow-svg-" + instanceId);

    /* SECTION: Flow Paths */
    const flowPaths = {
        grid_forward:    "<?php echo esc_js($paths['grid_forward']); ?>",
        grid_reverse:    "<?php echo esc_js($paths['grid_reverse']); ?>",
        ev_forward:      "<?php echo esc_js($paths['ev_forward']); ?>",
        ev_reverse:      "<?php echo esc_js($paths['ev_reverse']); ?>",
        battery_forward: "<?php echo esc_js($paths['battery_forward']); ?>",
        battery_reverse: "<?php echo esc_js($paths['battery_reverse']); ?>",
        pv_forward:      "<?php echo esc_js($paths['pv_forward']); ?>",
        pv_reverse:      "<?php echo esc_js($paths['pv_reverse']); ?>",
        load_forward:    "<?php echo esc_js($paths['load_forward']); ?>",
        load_reverse:    "<?php echo esc_js($paths['load_reverse']); ?>",
    };

    /* SECTION: AJAX Proxy Fetch Helper
     *
     * Previously this called Home Assistant directly from the browser,
     * which meant the HA URL and token were visible in the page source.
     *
     * Now the browser only sends an entity ID + a short-lived nonce to
     * WordPress's admin-ajax.php. WordPress makes the authenticated
     * request to Home Assistant server-side and returns just the
     * state value. The token never leaves the server.
     */
    async function fetchHA(entity) {
        const body = new URLSearchParams({
            action: 'ha_powerflow_proxy',
            nonce:  '<?php echo esc_js($nonce); ?>',
            entity: entity,
        });

        const response = await fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>', {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    body.toString(),
        });

        if (!response.ok) return null;

        const json = await response.json();
        if (!json.success) return null;

        // Return in the same shape as the old direct HA response
        // so nothing else in this file needs to change
        return {
            state: json.data.state,
            attributes: { unit_of_measurement: json.data.unit },
        };
    }

    /* SECTION: Entity Map */
    const haEntities = {
        <?php if ($solar_enabled): ?>
        pv_power:  "<?php echo esc_js($settings['pv_power']); ?>",
        pv_energy: "<?php echo esc_js($settings['pv_energy']); ?>",
        <?php endif; ?>

        load_power:  "<?php echo esc_js($settings['load_power']); ?>",
        load_energy: "<?php echo esc_js($settings['load_energy']); ?>",

        grid_power:      "<?php echo esc_js($settings['grid_power']); ?>",
        grid_energy_in:  "<?php echo esc_js($settings['grid_energy_in']); ?>",

        <?php if ($solar_enabled || $battery_enabled): ?>
        grid_energy_out: "<?php echo esc_js($settings['grid_energy_out']); ?>",
        <?php endif; ?>

        <?php if ($battery_enabled): ?>
        battery_power:      "<?php echo esc_js($settings['battery_power']); ?>",
        battery_energy_in:  "<?php echo esc_js($settings['battery_energy_in']); ?>",
        battery_energy_out: "<?php echo esc_js($settings['battery_energy_out']); ?>",
        battery_soc:        "<?php echo esc_js($settings['battery_soc']); ?>",
        <?php endif; ?>

        <?php if ($ev_enabled): ?>
        ev_power: "<?php echo esc_js($settings['ev_power']); ?>",
        ev_soc:   "<?php echo esc_js($settings['ev_soc']); ?>",
        <?php endif; ?>
    };

    /* SECTION: Label Map */
    const labelMap = {
        <?php if ($solar_enabled): ?>
        pv_power:  "PV",
        pv_energy: "PV Energy",
        <?php endif; ?>

        load_power:  "Load",
        load_energy: "Load Energy",

        grid_power:      "Grid",
        grid_energy_in:  "Grid In",
        grid_energy_out: "Grid Out",

        <?php if ($battery_enabled): ?>
        battery_power:      "Battery",
        battery_energy_in:  "Battery In",
        battery_energy_out: "Battery Out",
        battery_soc:        "Battery SOC",
        <?php endif; ?>

        <?php if ($ev_enabled): ?>
        ev_power: "EV",
        ev_soc:   "SOC"
        <?php endif; ?>
    };

    /* SECTION: Create Text Elements */
    function createTextElements() {
        let index = 0;
        const lineHeight = 28;
        const startX = 20;
        const startY = 40;

        Object.keys(haEntities).forEach(key => {
            const t = document.createElementNS("http://www.w3.org/2000/svg", "text");
            t.setAttribute("id", "txt_" + key + "_" + instanceId);
            t.setAttribute("x", startX);
            t.setAttribute("y", startY + index * lineHeight);
            t.setAttribute("font-size", "18");
            t.textContent = (labelMap[key] || key).toUpperCase() + ": ...";
            svg.appendChild(t);
            index++;
        });
    }

    /* SECTION: Position Text Elements */
    function positionElements() {

        <?php if ($ev_enabled): ?>
        const evPower = document.getElementById("txt_ev_power_" + instanceId);
        if (evPower) {
            const evX = <?php echo ha_pf_value('ev_power_x_pos', 750); ?>;
            const evY = <?php echo ha_pf_value('ev_power_y_pos', 460); ?>;
            evPower.setAttribute("x", evX);
            evPower.setAttribute("y", evY);
            evPower.setAttribute("transform", "rotate(" + <?php echo ha_pf_value('ev_power_rot', 15); ?> + " " + evX + " " + evY + ")");
        }
        const evSOC = document.getElementById("txt_ev_soc_" + instanceId);
        if (evSOC) {
            const evSocX = <?php echo ha_pf_value('ev_soc_x_pos', 750); ?>;
            const evSocY = <?php echo ha_pf_value('ev_soc_y_pos', 480); ?>;
            evSOC.setAttribute("x", evSocX);
            evSOC.setAttribute("y", evSocY);
            evSOC.setAttribute("transform", "rotate(" + <?php echo ha_pf_value('ev_soc_rot', 15); ?> + " " + evSocX + " " + evSocY + ")");
        }
        <?php endif; ?>

        const loadPower = document.getElementById("txt_load_power_" + instanceId);
        if (loadPower) {
            const loadX = <?php echo ha_pf_value('load_power_x_pos', 360); ?>;
            const loadY = <?php echo ha_pf_value('load_power_y_pos', 180); ?>;
            loadPower.setAttribute("x", loadX);
            loadPower.setAttribute("y", loadY);
            loadPower.setAttribute("transform", "rotate(" + <?php echo ha_pf_value('load_power_rot', -6); ?> + " " + loadX + " " + loadY + ")");
        }

        const gridPower = document.getElementById("txt_grid_power_" + instanceId);
        if (gridPower) {
            const gridX = <?php echo ha_pf_value('grid_power_x_pos', 740); ?>;
            const gridY = <?php echo ha_pf_value('grid_power_y_pos', 210); ?>;
            gridPower.setAttribute("x", gridX);
            gridPower.setAttribute("y", gridY);
            gridPower.setAttribute("transform", "rotate(" + <?php echo ha_pf_value('grid_power_rot', 0); ?> + " " + gridX + " " + gridY + ")");
        }

        <?php if ($battery_enabled): ?>
        const batteryPower = document.getElementById("txt_battery_power_" + instanceId);
        if (batteryPower) {
            const battX = <?php echo ha_pf_value('battery_power_x_pos', 360); ?>;
            const battY = <?php echo ha_pf_value('battery_power_y_pos', 665); ?>;
            batteryPower.setAttribute("x", battX);
            batteryPower.setAttribute("y", battY);
            batteryPower.setAttribute("transform", "rotate(" + <?php echo ha_pf_value('battery_power_rot', 0); ?> + " " + battX + " " + battY + ")");
        }
        const batterySOC = document.getElementById("txt_battery_soc_" + instanceId);
        if (batterySOC) {
            const battSocX = <?php echo ha_pf_value('battery_soc_x_pos', 360); ?>;
            const battSocY = <?php echo ha_pf_value('battery_soc_y_pos', 685); ?>;
            batterySOC.setAttribute("x", battSocX);
            batterySOC.setAttribute("y", battSocY);
            batterySOC.setAttribute("transform", "rotate(" + <?php echo ha_pf_value('battery_soc_rot', 0); ?> + " " + battSocX + " " + battSocY + ")");
        }
        const batteryOut = document.getElementById("txt_battery_energy_out_" + instanceId);
        if (batteryOut) {
            const battOutX = <?php echo ha_pf_value('battery_energy_out_x_pos', 14); ?>;
            const battOutY = <?php echo ha_pf_value('battery_energy_out_y_pos', 665); ?>;
            batteryOut.setAttribute("x", battOutX);
            batteryOut.setAttribute("y", battOutY);
            batteryOut.setAttribute("transform", "rotate(" + <?php echo ha_pf_value('battery_energy_out_rot', 0); ?> + " " + battOutX + " " + battOutY + ")");
        }
        const batteryIn = document.getElementById("txt_battery_energy_in_" + instanceId);
        if (batteryIn) {
            const battInX = <?php echo ha_pf_value('battery_energy_in_x_pos', 14); ?>;
            const battInY = <?php echo ha_pf_value('battery_energy_in_y_pos', 685); ?>;
            batteryIn.setAttribute("x", battInX);
            batteryIn.setAttribute("y", battInY);
            batteryIn.setAttribute("transform", "rotate(" + <?php echo ha_pf_value('battery_energy_in_rot', 0); ?> + " " + battInX + " " + battInY + ")");
        }
        <?php endif; ?>

        const gridIn = document.getElementById("txt_grid_energy_in_" + instanceId);
        if (gridIn) {
            const gridInX = <?php echo ha_pf_value('grid_energy_in_x_pos', 740); ?>;
            const gridInY = <?php echo ha_pf_value('grid_energy_in_y_pos', 70); ?>;
            gridIn.setAttribute("x", gridInX);
            gridIn.setAttribute("y", gridInY);
            gridIn.setAttribute("transform", "rotate(" + <?php echo ha_pf_value('grid_energy_in_rot', 0); ?> + " " + gridInX + " " + gridInY + ")");
        }
        const gridOut = document.getElementById("txt_grid_energy_out_" + instanceId);
        if (gridOut) {
            const gridOutX = <?php echo ha_pf_value('grid_energy_out_x_pos', 740); ?>;
            const gridOutY = <?php echo ha_pf_value('grid_energy_out_y_pos', 90); ?>;
            gridOut.setAttribute("x", gridOutX);
            gridOut.setAttribute("y", gridOutY);
            gridOut.setAttribute("transform", "rotate(" + <?php echo ha_pf_value('grid_energy_out_rot', 0); ?> + " " + gridOutX + " " + gridOutY + ")");
        }

        <?php if ($solar_enabled): ?>
        const pvPower = document.getElementById("txt_pv_power_" + instanceId);
        if (pvPower) {
            const pvX = <?php echo ha_pf_value('pv_power_x_pos', 49); ?>;
            const pvY = <?php echo ha_pf_value('pv_power_y_pos', 312); ?>;
            pvPower.setAttribute("x", pvX);
            pvPower.setAttribute("y", pvY);
            pvPower.setAttribute("transform", "rotate(" + <?php echo ha_pf_value('pv_power_rot', -9); ?> + " " + pvX + " " + pvY + ")");
        }
        const pvEnergy = document.getElementById("txt_pv_energy_" + instanceId);
        if (pvEnergy) {
            const pvEnergyX = <?php echo ha_pf_value('pv_energy_x_pos', 14); ?>;
            const pvEnergyY = <?php echo ha_pf_value('pv_energy_y_pos', 70); ?>;
            pvEnergy.setAttribute("x", pvEnergyX);
            pvEnergy.setAttribute("y", pvEnergyY);
            pvEnergy.setAttribute("transform", "rotate(" + <?php echo ha_pf_value('pv_energy_rot', 0); ?> + " " + pvEnergyX + " " + pvEnergyY + ")");
        }
        <?php endif; ?>

        const loadEnergy = document.getElementById("txt_load_energy_" + instanceId);
        if (loadEnergy) {
            const loadEnergyX = <?php echo ha_pf_value('load_energy_x_pos', 360); ?>;
            const loadEnergyY = <?php echo ha_pf_value('load_energy_y_pos', 70); ?>;
            loadEnergy.setAttribute("x", loadEnergyX);
            loadEnergy.setAttribute("y", loadEnergyY);
            loadEnergy.setAttribute("transform", "rotate(" + <?php echo ha_pf_value('load_energy_rot', 0); ?> + " " + loadEnergyX + " " + loadEnergyY + ")");
        }
    }

    /* SECTION: Motion Path Helpers */
    function setDotPath(dotId, forwardPath, reversePath, power) {
        const dot = document.getElementById(dotId);
        if (!dot) return;

        const d = (power >= 0) ? forwardPath : reversePath;
        dot.style.offsetPath = `path("${d}")`;

        if (Math.abs(power) === 0) {
            dot.style.animationPlayState = 'paused';
            dot.style.offsetDistance = '0%';
        } else {
            dot.style.animationPlayState = 'running';
        }
    }

    function updateDotSpeed(dotId, power) {
        const dot = document.getElementById(dotId);
        if (!dot) return;

        const watts = Math.abs(parseFloat(power));
        let duration;

        if (watts === 0)         { dot.style.animationPlayState = 'paused'; return; }
        else if (watts <= 100)   { duration = 12; }
        else if (watts <= 500)   { duration = 8;  }
        else if (watts <= 1000)  { duration = 5;  }
        else if (watts <= 2000)  { duration = 3;  }
        else                     { duration = 1.5; }

        const newDur = duration + 's';
        if (dot.style.animationDuration !== newDur) {
            dot.style.animationDuration = newDur;
        }
    }

    /* SECTION: Animation Functions */
    function animateGridLine(power) {
        setDotPath("line_grid_dot_" + instanceId, flowPaths.grid_forward, flowPaths.grid_reverse, power);
        updateDotSpeed("line_grid_dot_" + instanceId, power);
    }

    <?php if ($ev_enabled): ?>
    function animateEVLine(power) {
        setDotPath("line_inverter_to_ev_dot_" + instanceId, flowPaths.ev_forward, flowPaths.ev_reverse, power);
        updateDotSpeed("line_inverter_to_ev_dot_" + instanceId, power);
    }
    <?php endif; ?>

    <?php if ($battery_enabled): ?>
    function animateBatteryLine(power) {
        setDotPath("line_inverter_to_battery_dot_" + instanceId, flowPaths.battery_forward, flowPaths.battery_reverse, power);
        updateDotSpeed("line_inverter_to_battery_dot_" + instanceId, power);
    }
    <?php endif; ?>

    <?php if ($solar_enabled): ?>
    function animatePVLine(power) {
        setDotPath("line_pv_to_inverter_dot_" + instanceId, flowPaths.pv_forward, flowPaths.pv_reverse, power);
        updateDotSpeed("line_pv_to_inverter_dot_" + instanceId, power);
    }
    <?php endif; ?>

    function animateLoadLine(power) {
        const dotId = "line_inverter_to_load_dot_" + instanceId;
        const dot = document.getElementById(dotId);
        if (!dot) return;

        if (power <= 0) {
            dot.style.animationPlayState = 'paused';
            dot.style.offsetDistance = '0%';
            return;
        }

        dot.style.offsetPath = `path("${flowPaths.load_forward}")`;
        dot.style.animationPlayState = 'running';
        updateDotSpeed(dotId, power);
    }

    /* SECTION: Update Loop */
    async function updateValues() {
        for (const key in haEntities) {
            const entity = haEntities[key];
            if (!entity) continue;

            const data = await fetchHA(entity);
            const el = document.getElementById("txt_" + key + "_" + instanceId);

            if (data && el) {
                const value = data.state;
                const unit  = data.attributes.unit_of_measurement || "";
                const label = labelMap[key] || key;

                el.textContent = unit.toLowerCase() === "w"
                    ? label + ": " + formatPower(value)
                    : label + ": " + value + " " + unit;

                const num = parseFloat(value);

                if (key === "grid_power")    animateGridLine(num);
                if (key === "load_power")    animateLoadLine(num);

                <?php if ($solar_enabled): ?>
                if (key === "pv_power")      animatePVLine(num);
                <?php endif; ?>

                <?php if ($battery_enabled): ?>
                if (key === "battery_power") animateBatteryLine(num);
                <?php endif; ?>

                <?php if ($ev_enabled): ?>
                if (key === "ev_power")      animateEVLine(num);
                <?php endif; ?>
            }
        }
    }

    /* SECTION: Initialisation */
    createTextElements();
    positionElements();
    updateValues();
    setInterval(updateValues, 5000);

    /* SECTION: Debug Click Helper */
    svg.addEventListener("click", function (evt) {
        const pt  = svg.createSVGPoint();
        pt.x = evt.clientX;
        pt.y = evt.clientY;

        const svgP = pt.matrixTransform(svg.getScreenCTM().inverse());
        console.log(`Clicked at x: ${Math.round(svgP.x)}, y: ${Math.round(svgP.y)}`);

        const marker = document.createElementNS("http://www.w3.org/2000/svg", "circle");
        marker.setAttribute("cx", svgP.x);
        marker.setAttribute("cy", svgP.y);
        marker.setAttribute("r", 4);
        marker.setAttribute("fill", "red");
        marker.setAttribute("opacity", "0.7");
        svg.appendChild(marker);
    });

})(); // end IIFE

/* SECTION: Power Formatting Helper */
function formatPower(value) {
    const num = parseFloat(value);
    if (isNaN(num)) return value;
    return (num >= 1000 || num <= -1000)
        ? (num / 1000).toFixed(2) + " kW"
        : num + " W";
}
</script>

<?php
    return ob_get_clean();
}

add_shortcode('ha_powerflow', 'ha_powerflow_shortcode');
