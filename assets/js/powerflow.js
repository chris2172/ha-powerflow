/**
 * HA Powerflow – frontend script  v1.9.0
 */
(function ($) {
    'use strict';

    var $widget = $('.ha-powerflow-widget');
    if (!$widget.length) return;

    var debugMode     = $widget.attr('data-debug') === 'true';
    var anyModule     = haPowerflow.anyModule === 'true';
    var lineOpacity   = parseFloat(haPowerflow.lineOpacity);
    if (isNaN(lineOpacity)) lineOpacity = 1.0;

    var globalColor   = haPowerflow.lineColor    || '#4a90d9';
    var gridColor     = haPowerflow.gridColor    || globalColor;
    var loadColor     = haPowerflow.loadColor    || globalColor;
    var pvColor       = haPowerflow.pvColor      || globalColor;
    var batteryColor  = haPowerflow.batteryColor || globalColor;
    var evColor       = haPowerflow.evColor      || globalColor;
    var heatpumpColor = haPowerflow.heatpumpColor|| globalColor;

    var $debugBar = $('#ha-pf-debug-bar');
    var $coordPin = $('#ha-pf-coord-pin');
    var $status = $('#ha-pf-status');
    var svgEl = document.getElementById('ha-pf-svg');

    // ── Grid line elements
    var lineEl = document.getElementById('ha-pf-line');
    var laserEl = document.getElementById('ha-pf-path');

    // ── Load line elements
    var loadLaserEl = document.getElementById('ha-pf-load-path');

    // ── PV line elements
    var pvLaserEl = document.getElementById('ha-pf-pv-path');
    var enableSolar = haPowerflow.enableSolar === 'true';

    // ── Battery line elements
    var batteryLaserEl = document.getElementById('ha-pf-battery-path');
    var enableBattery = haPowerflow.enableBattery === 'true';

    // ── EV line elements
    var evLaserEl = document.getElementById('ha-pf-ev-path');
    var enableEv = haPowerflow.enableEv === 'true';

    // ── Heat Pump line elements
    var heatpumpLaserEl = document.getElementById('ha-pf-heatpump-path');
    var enableHeatpump = haPowerflow.enableHeatpump === 'true';

    var forwardPath = lineEl ? lineEl.getAttribute('d') : '';

    // Create a dynamic style block for our generated @keyframes
    var styleBlock = document.createElement('style');
    document.head.appendChild(styleBlock);
    var sheet = styleBlock.sheet;

    // Helper to inject a unique keyframe for a specific length & direction
    function getAnimationName(length, reverse) {
        var direction = reverse ? 'rev' : 'fwd';
        var name = 'hapf_dash_' + direction + '_' + Math.round(length);

        // Ensure we haven't already created this keyframe rule
        for (var i = 0; i < sheet.cssRules.length; i++) {
            if (sheet.cssRules[i].name === name) return name;
        }

        // The laser pattern is (dashLength + loopGap) 
        var dashLength = Math.min(100, Math.max(10, length * 0.15));
        
        var startOffset = reverse ? -length : dashLength;
        var endOffset = reverse ? dashLength : -length;

        // Keyframe rule for traveling light
        var rule = '@keyframes ' + name + ' { ' +
            'from { stroke-dashoffset: ' + startOffset + 'px; } ' +
            'to { stroke-dashoffset: ' + endOffset + 'px; } ' +
            '}';

        sheet.insertRule(rule, sheet.cssRules.length);
        return name;
    }

    // ── Debug mode ─────────────────────────────────────────────────────────
    if (debugMode) {
        $debugBar.show();
        $status.text('Debug mode active – live refresh paused.');
        $widget.on('click', function (e) {
            var svgRect = svgEl.getBoundingClientRect();
            var vb = svgEl.viewBox.baseVal;
            var svgX = Math.round((e.clientX - svgRect.left) * (vb.width / svgRect.width));
            var svgY = Math.round((e.clientY - svgRect.top) * (vb.height / svgRect.height));
            svgX = Math.max(0, Math.min(1000, svgX));
            svgY = Math.max(0, Math.min(700, svgY));
            var wr = $widget[0].getBoundingClientRect();
            $coordPin
                .text(svgX + ', ' + svgY)
                .css({ left: (e.clientX - wr.left) + 'px', top: (e.clientY - wr.top) + 'px' })
                .show();
            $debugBar.text('🐛 Debug  |  x=' + svgX + ', y=' + svgY + '  ← copy into settings');
        });
        return;
    }

    // ── Reverse M…L… path ─────────────────────────────────────────────────
    function reversePath(d) {
        var re = /[ML]\s*([-\d.]+),([-\d.]+)/gi, pts = [], m;
        while ((m = re.exec(d)) !== null) pts.push([parseFloat(m[1]), parseFloat(m[2])]);
        if (pts.length < 2) return d;
        pts.reverse();
        var out = 'M ' + pts[0][0] + ',' + pts[0][1];
        for (var i = 1; i < pts.length; i++) out += ' L ' + pts[i][0] + ',' + pts[i][1];
        return out;
    }

    function fmt(state, unit) {
        if (!state || state === 'N/A' || state === 'unavailable' || state === 'unknown') return 'N/A';
        var n = parseFloat(state);
        if (isNaN(n)) return state;

        // If HA already returns kW/kWh keep as-is with 2 dp
        var unitLower = (unit || '').toLowerCase();
        if (unitLower.charAt(0) === 'k') {
            return n.toFixed(2) + '\u202f' + unit;
        }

        // W / Wh — auto-promote to kW / kWh when value exceeds 999
        if (Math.abs(n) > 999) {
            var kUnit = (unit && unit.toUpperCase() === 'WH') ? 'kWh' : 'kW';
            return (n / 1000).toFixed(2) + '\u202f' + kUnit;
        }

        return n.toFixed(0) + '\u202f' + (unit || '');
    }

    function svgText(id, val) {
        var el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    function dotSpeed(absW) {
        if (absW === 0 || isNaN(absW)) return null;
        if (absW <= 100) return '6s';   
        if (absW <= 300) return '4s';   
        if (absW <= 500) return '2s';  
        if (absW <= 1500) return '1s';  
        return '0.5s';                  
    }

    function getPulseCount(absW) {
        if (absW <= 200) return 1;
        if (absW <= 800) return 2;
        if (absW <= 2500) return 3;
        return 4;
    }

    function animateLaser(laserEl, dur, forceRestart, reverse, colorOverride, pulseCount) {
        if (!laserEl) return;

        pulseCount = pulseCount || 1;
        laserEl.setAttribute('stroke', colorOverride || globalColor);
        laserEl.setAttribute('opacity', '1'); 

        var oldDur = laserEl.getAttribute('data-dur');
        var oldPulses = laserEl.getAttribute('data-pulses');

        var totalLen = laserEl.getTotalLength() || 1000;
        var dashLen = Math.min(100, Math.max(10, totalLen * 0.15));
        
        var segmentLen = totalLen / pulseCount;
        var gapLen = segmentLen - dashLen;

        if (oldDur !== dur || forceRestart || laserEl.getAttribute('data-rev') !== String(reverse) || oldPulses !== String(pulseCount)) {
            laserEl.setAttribute('data-dur', dur);
            laserEl.setAttribute('data-rev', String(reverse));
            laserEl.setAttribute('data-pulses', String(pulseCount));

            var animName = getAnimationName(totalLen, reverse);

            laserEl.style.animation = 'none';
            laserEl.style.strokeDasharray = dashLen + 'px ' + gapLen + 'px';

            void laserEl.offsetWidth;
            laserEl.style.animation = animName + ' ' + dur + ' linear infinite';
        }
    }

    function stopLaser(laserEl) {
        if (laserEl) {
            laserEl.setAttribute('opacity', '0');
            laserEl.style.animation = 'none';
            laserEl.removeAttribute('data-dur');
        }
    }

    var batteryPath = batteryLaserEl ? batteryLaserEl.getAttribute('d') : '';

    // ── Set flow for all lines ─────────────────────────────────────────────
    // powers = { grid, load, pv, battery, ev, heatpump }  — all may be NaN
    function setFlow(powers) {
        var gridPower = powers.grid;
        var loadPower = powers.load;
        var pvPower = powers.pv;
        var batteryPower = powers.battery;
        var evPower = powers.ev;
        var heatpumpPower = powers.heatpump;

        // ── Grid laser ──────────────────────────────────────────────────────
        var absGrid = Math.abs(gridPower);
        var gridDur = dotSpeed(isNaN(gridPower) ? 0 : absGrid);
        var gridPulses = getPulseCount(absGrid);

        if (!gridDur || absGrid < 50) {
            stopLaser(laserEl);
            
            // Calculate primary source (PV or Battery)
            var sources = [];
            if (enableSolar && pvPower > 0) sources.push({ name: 'PV', val: pvPower });
            if (enableBattery && batteryPower > 50) sources.push({ name: 'Battery', val: batteryPower });
            
            sources.sort(function(a, b) { return b.val - a.val; });

            // Calculate primary consumer
            var consumers = [
                { name: 'House', val: Math.abs(isNaN(loadPower) ? 0 : loadPower) }
            ];
            if (enableBattery && batteryPower < -50) { // Battery is CHARGING (consuming)
                consumers.push({ name: 'Battery', val: Math.abs(batteryPower) });
            }
            if (enableEv && evPower > 50) {
                consumers.push({ name: 'EV', val: Math.abs(evPower) });
            }
            if (enableHeatpump && heatpumpPower > 50) {
                consumers.push({ name: 'Heat Pump', val: Math.abs(heatpumpPower) });
            }
            consumers.sort(function(a, b) { return b.val - a.val; });

            if (sources.length > 0 && sources[0].val > 50) {
                svgText('ha-pf-flow-main', sources[0].name.toUpperCase());
            } else {
                svgText('ha-pf-flow-main', 'No grid flow');
            }

            if (consumers.length > 0 && consumers[0].val > 50) {
                svgText('ha-pf-flow-sub', 'To ' + consumers[0].name);
            } else {
                svgText('ha-pf-flow-sub', '');
            }
        } else {
            var newGridPath = (gridPower > 0) ? forwardPath : reversePath(forwardPath);
            var oldGridPath = laserEl.getAttribute('d');
            var gridPathChanged = false;

            if (oldGridPath !== newGridPath) {
                laserEl.setAttribute('d', newGridPath);
                gridPathChanged = true;
            }

            if (gridPower >= 50) {
                svgText('ha-pf-flow-main', 'IMPORTING');
                
                // Smart Palette / Intensity Check for Grid
                var activeGridColor = gridColor;
                if (haPowerflow.isCustomGrid !== 'true') activeGridColor = '#3498db'; // Default Importing Blue

                // Calculate primary consumer
                var consumers = [
                    { name: 'House', val: Math.abs(isNaN(loadPower) ? 0 : loadPower) }
                ];
                if (enableBattery && batteryPower < -50) { 
                    consumers.push({ name: 'Battery', val: Math.abs(batteryPower) });
                }
                if (enableEv && evPower > 50) {
                    consumers.push({ name: 'EV', val: Math.abs(evPower) });
                }
                if (enableHeatpump && heatpumpPower > 50) {
                    consumers.push({ name: 'Heat Pump', val: Math.abs(heatpumpPower) });
                }
                consumers.sort(function(a, b) { return b.val - a.val; });
                
                if (consumers.length > 0 && consumers[0].val > 50) {
                    svgText('ha-pf-flow-sub', 'To ' + consumers[0].name);
                } else {
                    svgText('ha-pf-flow-sub', '');
                }
                animateLaser(laserEl, gridDur, gridPathChanged, false, activeGridColor, gridPulses);
            } else {
                svgText('ha-pf-flow-main', 'EXPORTING');
                svgText('ha-pf-flow-sub', '');
                
                // Smart Palette / Intensity Check for Grid
                var activeGridColor = gridColor;
                if (haPowerflow.isCustomGrid !== 'true') activeGridColor = '#2ecc71'; // Default Exporting Green
                
                animateLaser(laserEl, gridDur, gridPathChanged, false, activeGridColor, gridPulses);
            }
        }

        // ── Load laser ──────────────────────────────────────────────────────
        if (anyModule && loadLaserEl) {
            var loadVal = isNaN(loadPower) ? gridPower : loadPower;
            var absLoad = Math.abs(loadVal);
            var loadDur = dotSpeed(absLoad);
            var loadPulses = getPulseCount(absLoad);
            
            // Smart Palette for House
            var activeHouseColor = loadColor;
            if (haPowerflow.isCustomHouse !== 'true') activeHouseColor = '#ffffff'; // Default House White

            if (loadDur) {
                animateLaser(loadLaserEl, loadDur, false, false, activeHouseColor, loadPulses);
            } else {
                stopLaser(loadLaserEl);
            }
        }

        // ── PV laser ────────────────────────────────────────────────────────
        if (enableSolar && pvLaserEl) {
            var absPv = Math.abs(isNaN(pvPower) ? 0 : pvPower);
            var pvDur = dotSpeed(absPv);
            var pvPulses = getPulseCount(absPv);
            
            // Smart Palette for PV
            var activePvColor = pvColor;
            if (haPowerflow.isCustomPv !== 'true') activePvColor = '#f1c40f'; // Default Solar Gold

            if (pvDur) {
                animateLaser(pvLaserEl, pvDur, false, false, activePvColor, pvPulses);
            } else {
                stopLaser(pvLaserEl);
            }
        }

        // ── Battery laser ───────────────────────────────────────────────────
        if (enableBattery && batteryLaserEl) {
            var absBat = Math.abs(isNaN(batteryPower) ? 0 : batteryPower);
            var batDur = dotSpeed(absBat);
            var batPulses = getPulseCount(absBat);

            // Smart Palette for Battery
            var activeBatColor = batteryColor;
            if (haPowerflow.isCustomBattery !== 'true') {
                if (batteryPower < 0) activeBatColor = '#2ecc71'; // Charging Green
                else activeBatColor = '#e67e22'; // Discharging Orange
            }

            if (batDur) {
                var batPath = (batteryPower > 0)
                    ? batteryPath
                    : reversePath(batteryPath);
                var oldBatPath = batteryLaserEl.getAttribute('d');
                var batPathChanged = false;

                if (oldBatPath !== batPath) {
                    batteryLaserEl.setAttribute('d', batPath);
                    batPathChanged = true;
                }
                animateLaser(batteryLaserEl, batDur, batPathChanged, false, activeBatColor, batPulses);
            } else {
                stopLaser(batteryLaserEl);
            }
        }

        // ── EV laser ────────────────────────────────────────────────────────
        if (enableEv && evLaserEl) {
            var absEv = Math.abs(isNaN(evPower) ? 0 : evPower);
            var evDur = dotSpeed(absEv);
            var evPulses = getPulseCount(absEv);

            // Smart Palette for EV
            var activeEvColor = evColor;
            if (haPowerflow.isCustomEv !== 'true') activeEvColor = '#349bef'; // Default EV Sky Blue

            if (evDur) {
                animateLaser(evLaserEl, evDur, false, false, activeEvColor, evPulses);
            } else {
                stopLaser(evLaserEl);
            }
        }

        // ── Heat Pump laser ─────────────────────────────────────────────────
        if (enableHeatpump && heatpumpLaserEl) {
            var absHp = Math.abs(isNaN(heatpumpPower) ? 0 : heatpumpPower);
            var hpDur = dotSpeed(absHp);
            var hpPulses = getPulseCount(absHp);

            // Smart Palette for Heat Pump
            var activeHpColor = heatpumpColor;
            if (haPowerflow.isCustomHp !== 'true') activeHpColor = '#9b59b6'; // Default Heat Pump Purple

            if (hpDur) {
                animateLaser(heatpumpLaserEl, hpDur, false, false, activeHpColor, hpPulses);
            } else {
                stopLaser(heatpumpLaserEl);
            }
        }
    }

    // ── Fetch ──────────────────────────────────────────────────────────────
    function fetchData() {

        // Pre-flight checks — catch common config issues immediately
        if (haPowerflow.haUrl === 'missing') {
            $status.text('⚠ No Home Assistant URL set — visit Settings → HA Powerflow.');
            return;
        }
        if (haPowerflow.haToken === 'missing') {
            $status.text('⚠ No Access Token set — visit Settings → HA Powerflow.');
            return;
        }

        $.ajax({
            url: haPowerflow.ajaxUrl,
            method: 'POST',
            data: { action: 'ha_powerflow_get_data', nonce: haPowerflow.nonce },
            success: function (res) {
                if (res.success) {
                    var d = res.data;

                    // Warn if individual entities are missing
                    var missing = [];
                    if (!haPowerflow.gridPower) missing.push('Grid Power Entity');
                    if (!haPowerflow.loadPower) missing.push('Load Power Entity');
                    if (!haPowerflow.gridEnergy) missing.push('Grid Energy Entity');
                    if (!haPowerflow.loadEnergy) missing.push('Load Energy Entity');
                    if (enableSolar && !haPowerflow.pvPower) missing.push('PV Power Entity');
                    if (enableSolar && !haPowerflow.pvEnergy) missing.push('PV Energy Entity');
                    if (enableBattery && !haPowerflow.batteryPower) missing.push('Battery Power Entity');
                    if (enableBattery && !haPowerflow.batteryInEnergy) missing.push('Battery Energy In Entity');
                    if (enableBattery && !haPowerflow.batteryOutEnergy) missing.push('Battery Energy Out Entity');
                    if (enableBattery && !haPowerflow.batterySoc) missing.push('Battery SOC Entity');
                    if (enableEv && !haPowerflow.evPower) missing.push('EV Power Entity');
                    if (enableEv && !haPowerflow.evSoc) missing.push('EV SOC Entity');
                    if (enableHeatpump && !haPowerflow.heatpumpPower) missing.push('Heat Pump Power Entity');
                    if (enableHeatpump && !haPowerflow.heatpumpEnergy) missing.push('Heat Pump Energy Entity');
                    if (enableHeatpump && !haPowerflow.heatpumpEfficiency) missing.push('Heat Pump Efficiency Entity');

                    svgText('ha-pf-grid-power', fmt(d.grid_power.state, d.grid_power.unit));
                    svgText('ha-pf-grid-energy', fmt(d.grid_energy.state, d.grid_energy.unit));
                    svgText('ha-pf-load-power', fmt(d.load_power.state, d.load_power.unit));
                    svgText('ha-pf-load-energy', fmt(d.load_energy.state, d.load_energy.unit));

                    if (enableSolar && d.pv_power && d.pv_energy) {
                        svgText('ha-pf-pv-power', fmt(d.pv_power.state, d.pv_power.unit));
                        svgText('ha-pf-pv-energy', fmt(d.pv_energy.state, d.pv_energy.unit));
                    }

                    var batPowerVal = NaN;
                    if (enableBattery && d.battery_power) {
                        batPowerVal = parseFloat(d.battery_power.state);
                        svgText('ha-pf-battery-power', fmt(d.battery_power.state, d.battery_power.unit));
                    }
                    if (enableBattery && d.battery_soc) {
                        var socVal = parseFloat(d.battery_soc.state);
                        svgText('ha-pf-battery-soc', isNaN(socVal) ? 'N/A' : socVal.toFixed(0) + '\u202f%');
                    }

                    var evPowerVal = NaN;
                    if (enableEv && d.ev_power) {
                        evPowerVal = parseFloat(d.ev_power.state);
                        svgText('ha-pf-ev-power', fmt(d.ev_power.state, d.ev_power.unit));
                    }
                    if (enableEv && d.ev_soc) {
                        var evSocVal = parseFloat(d.ev_soc.state);
                        svgText('ha-pf-ev-soc', isNaN(evSocVal) ? 'N/A' : evSocVal.toFixed(0) + '\u202f%');
                    }

                    var hpPowerVal = NaN;
                    if (enableHeatpump && d.heatpump_power) {
                        hpPowerVal = parseFloat(d.heatpump_power.state);
                        svgText('ha-pf-heatpump-power', fmt(d.heatpump_power.state, d.heatpump_power.unit));
                    }
                    if (enableHeatpump && d.heatpump_efficiency) {
                        var copVal = parseFloat(d.heatpump_efficiency.state);
                        svgText('ha-pf-heatpump-efficiency', isNaN(copVal) ? 'N/A' : 'COP\u202f' + copVal.toFixed(1));
                    }

                    var pvPowerVal = NaN;
                    if (enableSolar && d.pv_power) {
                        pvPowerVal = parseFloat(d.pv_power.state);
                    }

                    setFlow({
                        grid: parseFloat(d.grid_power.state),
                        load: parseFloat(d.load_power.state),
                        pv: pvPowerVal,
                        battery: batPowerVal,
                        ev: evPowerVal,
                        heatpump: hpPowerVal,
                    });

                    if (missing.length) {
                        $status.addClass('ha-pf-error').text('⚠ Missing entity IDs: ' + missing.join(', ') + ' — check Settings.');
                    } else if (
                        d.grid_power.state === 'unavailable' ||
                        d.grid_power.state === 'unknown' ||
                        d.grid_power.state === 'N/A'
                    ) {
                        $status.addClass('ha-pf-error').text('⚠ Grid Power sensor returned "' + d.grid_power.state + '" — check entity ID in Settings.');
                    } else {
                        $status.removeClass('ha-pf-error').text('Updated ' + new Date().toLocaleTimeString());
                    }
                } else {
                    $status.addClass('ha-pf-error').text('⚠ ' + res.data);
                }
            },
            error: function (xhr) {
                var msg = '⚠ WordPress AJAX error';
                if (xhr.status === 0) {
                    msg += ' — could not reach the server. Check your internet connection.';
                } else if (xhr.status === 403) {
                    msg += ' — forbidden (nonce expired?). Try refreshing the page.';
                } else {
                    msg += ' (HTTP ' + xhr.status + '). Check browser console for details.';
                }
                $status.addClass('ha-pf-error').text(msg);
            }
        });
    }

    $(document).ready(function () {
        fetchData();
        setInterval(fetchData, haPowerflow.refreshInterval);
    });

})(jQuery);
