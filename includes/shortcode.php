<?php
if (!defined('ABSPATH')) exit;

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

    /* SECTION: Load Settings */
    $settings = [];
    $keys = [
        'ha_url','ha_token','pv_power','pv_energy','load_power','load_energy',
        'grid_power','grid_energy_in','grid_energy_out','battery_power',
        'battery_energy_in','battery_energy_out','battery_soc','ev_power','ev_soc'
    ];

    foreach ($keys as $key) {
        $settings[$key] = get_option('ha_powerflow_' . $key);
    }

    ob_start();
?>
<style>
    svg text {
        fill: #5EC766;
    }

    #ha-powerflow-wrapper-<?php echo esc_attr($instance_id); ?> {
        position: relative;
        width: 100%;
        max-width: 1000px;
        margin: 0 auto;
    }

    #ha-powerflow-svg-<?php echo esc_attr($instance_id); ?> {
        width: 100%;
        height: auto;
        display: block;
    }
</style>

<div id="ha-powerflow-wrapper-<?php echo esc_attr($instance_id); ?>">
<svg id="ha-powerflow-svg-<?php echo esc_attr($instance_id); ?>"
     viewBox="0 0 1000 750"
     preserveAspectRatio="xMidYMid meet">

<style>
    .flow-dot {
        fill: #5EC766;
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
        <stop offset="0%" stop-color="#5EC766" stop-opacity="0.9"/>
        <stop offset="100%" stop-color="#5EC766" stop-opacity="0.3"/>
    </linearGradient>
</defs>

<rect width="1000" height="750" fill="url(#grid-<?php echo esc_attr($instance_id); ?>)" opacity="0.25"></rect>

<!-- SECTION: Grid Paths -->
<path id="line_grid_forward_<?php echo esc_attr($instance_id); ?>"
      d="M 787 366 L 805 375 L 633 439"
      stroke="#5EC766" stroke-width="2" stroke-opacity="0.35"
      fill="none" stroke-linecap="round" stroke-linejoin="round" />

<path id="line_grid_reverse_<?php echo esc_attr($instance_id); ?>"
      d="M 633 439 L 805 375 L 787 366"
      stroke="#5EC766" stroke-width="2" stroke-opacity="0.35"
      fill="none" stroke-linecap="round" stroke-linejoin="round" />

<circle id="line_grid_dot_<?php echo esc_attr($instance_id); ?>"
        r="6" class="flow-dot" />

<?php if ($ev_enabled): ?>
<!-- SECTION: EV Paths -->
<path id="line_ev_forward_<?php echo esc_attr($instance_id); ?>"
      d="M 618 497 L 713 532 L 786 499"
      stroke="#5EC766" stroke-width="2" stroke-opacity="0.35"
      fill="none" stroke-linecap="round" stroke-linejoin="round" />

<path id="line_ev_reverse_<?php echo esc_attr($instance_id); ?>"
      d="M 786 499 L 713 532 L 618 497"
      stroke="#5EC766" stroke-width="2" stroke-opacity="0.35"
      fill="none" stroke-linecap="round" stroke-linejoin="round" />

<circle id="line_inverter_to_ev_dot_<?php echo esc_attr($instance_id); ?>"
        r="6" class="flow-dot" />
<?php endif; ?>

<?php if ($battery_enabled): ?>
<!-- SECTION: Battery Paths -->
<path id="line_battery_forward_<?php echo esc_attr($instance_id); ?>"
      d="M 532 500 L 364 563"
      stroke="#5EC766" stroke-width="2" stroke-opacity="0.35"
      fill="none" stroke-linecap="round" stroke-linejoin="round" />

<path id="line_battery_reverse_<?php echo esc_attr($instance_id); ?>"
      d="M 364 563 L 532 500"
      stroke="#5EC766" stroke-width="2" stroke-opacity="0.35"
      fill="none" stroke-linecap="round" stroke-linejoin="round" />

<circle id="line_inverter_to_battery_dot_<?php echo esc_attr($instance_id); ?>"
        r="6" class="flow-dot" />
<?php endif; ?>

<?php if ($solar_enabled): ?>
<!-- SECTION: PV Paths -->
<path id="line_pv_forward_<?php echo esc_attr($instance_id); ?>"
      d="M 331 417 L 510 486"
      stroke="#5EC766" stroke-width="2" stroke-opacity="0.35"
      fill="none" stroke-linecap="round" stroke-linejoin="round" />

<path id="line_pv_reverse_<?php echo esc_attr($instance_id); ?>"
      d="M 510 486 L 331 417"
      stroke="#5EC766" stroke-width="2" stroke-opacity="0.35"
      fill="none" stroke-linecap="round" stroke-linejoin="round" />

<circle id="line_pv_to_inverter_dot_<?php echo esc_attr($instance_id); ?>"
        r="6" class="flow-dot" />
<?php endif; ?>

<!-- SECTION: Load Paths -->
<path id="line_load_forward_<?php echo esc_attr($instance_id); ?>"
      d="M 590 427 L 673 396 L 612 369"
      stroke="#5EC766" stroke-width="2" stroke-opacity="0.35"
      fill="none" stroke-linecap="round" stroke-linejoin="round" />

<path id="line_load_reverse_<?php echo esc_attr($instance_id); ?>"
      d="M 590 427 L 673 396 L 612 369"
      stroke="#5EC766" stroke-width="2" stroke-opacity="0.35"
      fill="none" stroke-linecap="round" stroke-linejoin="round" />

<circle id="line_inverter_to_load_dot_<?php echo esc_attr($instance_id); ?>"
        r="6" class="flow-dot" />

</svg>
</div>

<script>
/* SECTION: Debug Logging */
console.log("Default image URL:", "<?php echo esc_js($default_image); ?>");

/* SECTION: Main JS Wrapper */
(function() {

    /* SECTION: Instance + SVG Reference */
    const instanceId = "<?php echo esc_js($instance_id); ?>";
    const svg = document.getElementById("ha-powerflow-svg-" + instanceId);

    /* SECTION: Home Assistant Fetch Helper */
    async function fetchHA(entity) {
        const url = "<?php echo esc_js($settings['ha_url']); ?>/api/states/" + entity;

        const response = await fetch(url, {
            headers: {
                "Authorization": "Bearer <?php echo esc_js($settings['ha_token']); ?>",
                "Content-Type": "application/json"
            }
        });

        if (!response.ok) return null;
        return await response.json();
    }

    /* SECTION: Entity Map */
    const haEntities = {
        <?php if ($solar_enabled): ?>
        pv_power: "<?php echo esc_js($settings['pv_power']); ?>",
        pv_energy: "<?php echo esc_js($settings['pv_energy']); ?>",
        <?php endif; ?>

        load_power: "<?php echo esc_js($settings['load_power']); ?>",
        load_energy: "<?php echo esc_js($settings['load_energy']); ?>",

        grid_power: "<?php echo esc_js($settings['grid_power']); ?>",
        grid_energy_in: "<?php echo esc_js($settings['grid_energy_in']); ?>",

        <?php if ($solar_enabled || $battery_enabled): ?>
        grid_energy_out: "<?php echo esc_js($settings['grid_energy_out']); ?>",
        <?php endif; ?>

        <?php if ($battery_enabled): ?>
        battery_power: "<?php echo esc_js($settings['battery_power']); ?>",
        battery_energy_in: "<?php echo esc_js($settings['battery_energy_in']); ?>",
        battery_energy_out: "<?php echo esc_js($settings['battery_energy_out']); ?>",
        battery_soc: "<?php echo esc_js($settings['battery_soc']); ?>",
        <?php endif; ?>

        <?php if ($ev_enabled): ?>
        ev_power: "<?php echo esc_js($settings['ev_power']); ?>",
        ev_soc: "<?php echo esc_js($settings['ev_soc']); ?>",
        <?php endif; ?>
    };

    /* SECTION: Label Map */
    const labelMap = {
        <?php if ($solar_enabled): ?>
        pv_power: "PV",
        pv_energy: "PV Energy",
        <?php endif; ?>

        load_power: "Load",
        load_energy: "Load Energy",

        grid_power: "Grid",
        grid_energy_in: "Grid In",
        grid_energy_out: "Grid Out",

        <?php if ($battery_enabled): ?>
        battery_power: "Battery",
        battery_energy_in: "Battery In",
        battery_energy_out: "Battery Out",
        battery_soc: "Battery SOC",
        <?php endif; ?>

        <?php if ($ev_enabled): ?>
        ev_power: "EV",
        ev_soc: "SOC"
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

            const label = (labelMap[key] || key).toUpperCase();
            t.textContent = label + ": ...";

            svg.appendChild(t);
            index++;
        });
    }

    /* SECTION: Position Text Elements */
    function positionElements() {

        <?php if ($ev_enabled): ?>
        const evPower = document.getElementById("txt_ev_power_" + instanceId);
        if (evPower) {
            evPower.setAttribute("x", 750);
            evPower.setAttribute("y", 460);
            evPower.setAttribute("transform", "rotate(15 750 470)");
        }

        const evSOC = document.getElementById("txt_ev_soc_" + instanceId);
        if (evSOC) {
            evSOC.setAttribute("x", 750);
            evSOC.setAttribute("y", 480);
            evSOC.setAttribute("transform", "rotate(15 750 490)");
        }
        <?php endif; ?>

        const loadPower = document.getElementById("txt_load_power_" + instanceId);
        if (loadPower) {
            loadPower.setAttribute("x", 360);
            loadPower.setAttribute("y", 180);
            loadPower.setAttribute("transform", "rotate(-6 360 190)");
        }

        const gridPower = document.getElementById("txt_grid_power_" + instanceId);
        if (gridPower) {
            gridPower.setAttribute("x", 740);
            gridPower.setAttribute("y", 210);
        }

        <?php if ($battery_enabled): ?>
        const batteryPower = document.getElementById("txt_battery_power_" + instanceId);
        if (batteryPower) {
            batteryPower.setAttribute("x", 360);
            batteryPower.setAttribute("y", 665);
        }

        const batterySOC = document.getElementById("txt_battery_soc_" + instanceId);
        if (batterySOC) {
            batterySOC.setAttribute("x", 360);
            batterySOC.setAttribute("y", 685);
        }

        const batteryOut = document.getElementById("txt_battery_energy_out_" + instanceId);
        if (batteryOut) {
            batteryOut.setAttribute("x", 14);
            batteryOut.setAttribute("y", 665);
        }

        const batteryIn = document.getElementById("txt_battery_energy_in_" + instanceId);
        if (batteryIn) {
            batteryIn.setAttribute("x", 14);
            batteryIn.setAttribute("y", 685);
        }
        <?php endif; ?>

        const gridIn = document.getElementById("txt_grid_energy_in_" + instanceId);
        if (gridIn) {
            gridIn.setAttribute("x", 740);
            gridIn.setAttribute("y", 70);
        }

        const gridOut = document.getElementById("txt_grid_energy_out_" + instanceId);
        if (gridOut) {
            gridOut.setAttribute("x", 740);
            gridOut.setAttribute("y", 90);
        }

        <?php if ($solar_enabled): ?>
        const pvPower = document.getElementById("txt_pv_power_" + instanceId);
        if (pvPower) {
            pvPower.setAttribute("x", 49);
            pvPower.setAttribute("y", 312);
            pvPower.setAttribute("transform", "rotate(-9 49 322)");
        }

        const pvEnergy = document.getElementById("txt_pv_energy_" + instanceId);
        if (pvEnergy) {
            pvEnergy.setAttribute("x", 14);
            pvEnergy.setAttribute("y", 70);
        }
        <?php endif; ?>

        const loadEnergy = document.getElementById("txt_load_energy_" + instanceId);
        if (loadEnergy) {
            loadEnergy.setAttribute("x", 360);
            loadEnergy.setAttribute("y", 70);
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

        if (watts === 0) {
            dot.style.animationPlayState = 'paused';
            return;
        } else if (watts <= 100) {
            duration = 12;
        } else if (watts <= 500) {
            duration = 8;
        } else if (watts <= 1000) {
            duration = 5;
        } else if (watts <= 2000) {
            duration = 3;
        } else {
            duration = 1.5;
        }

        const newDur = duration + 's';
        if (dot.style.animationDuration !== newDur) {
            dot.style.animationDuration = newDur;
        }
    }

    /* SECTION: Animation Functions */

    function animateGridLine(power) {
        setDotPath(
            "line_grid_dot_" + instanceId,
            "M 787 366 L 805 375 L 633 439",
            "M 633 439 L 805 375 L 787 366",
            power
        );
        updateDotSpeed("line_grid_dot_" + instanceId, power);
    }

    <?php if ($ev_enabled): ?>
    function animateEVLine(power) {
        setDotPath(
            "line_inverter_to_ev_dot_" + instanceId,
            "M 618 497 L 713 532 L 786 499",
            "M 786 499 L 713 532 L 618 497",
            power
        );
        updateDotSpeed("line_inverter_to_ev_dot_" + instanceId, power);
    }
    <?php endif; ?>

    <?php if ($battery_enabled): ?>
    function animateBatteryLine(power) {
        setDotPath(
            "line_inverter_to_battery_dot_" + instanceId,
            "M 532 500 L 364 563",
            "M 364 563 L 532 500",
            power
        );
        updateDotSpeed("line_inverter_to_battery_dot_" + instanceId, power);
    }
    <?php endif; ?>

    <?php if ($solar_enabled): ?>
    function animatePVLine(power) {
        setDotPath(
            "line_pv_to_inverter_dot_" + instanceId,
            "M 331 417 L 510 486",
            "M 510 486 L 331 417",
            power
        );
        updateDotSpeed("line_pv_to_inverter_dot_" + instanceId, power);
    }
    <?php endif; ?>

    function animateLoadLine(power) {
        const dotId = "line_inverter_to_load_dot_" + instanceId;

        if (power <= 0) {
            const dot = document.getElementById(dotId);
            if (dot) {
                dot.style.animationPlayState = 'paused';
                dot.style.offsetDistance = '0%';
            }
            return;
        }

        const forward = "M 590 427 L 673 396 L 612 369";

        const dot = document.getElementById(dotId);
        if (dot) {
            dot.style.offsetPath = `path("${forward}")`;
            dot.style.animationPlayState = 'running';
        }

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
                const unit = data.attributes.unit_of_measurement || "";
                const label = labelMap[key] || key;

                if (unit.toLowerCase() === "w") {
                    el.textContent = label + ": " + formatPower(value);
                } else {
                    el.textContent = label + ": " + value + " " + unit;
                }

                const num = parseFloat(value);

                if (key === "grid_power")   animateGridLine(num);
                if (key === "load_power")   animateLoadLine(num);

                <?php if ($solar_enabled): ?>
                if (key === "pv_power")     animatePVLine(num);
                <?php endif; ?>

                <?php if ($battery_enabled): ?>
                if (key === "battery_power") animateBatteryLine(num);
                <?php endif; ?>

                <?php if ($ev_enabled): ?>
                if (key === "ev_power")     animateEVLine(num);
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
        const pt = svg.createSVGPoint();
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

    if (num >= 1000 || num <= -1000) {
        return (num / 1000).toFixed(2) + " kW";
    }

    return num + " W";
}
</script>

<?php
    /* SECTION: Return Final Output */
    return ob_get_clean();
}

/* SECTION: Register Shortcode */
add_shortcode('ha_powerflow', 'ha_powerflow_shortcode');
