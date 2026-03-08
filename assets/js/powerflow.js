/**
 * HA Powerflow – frontend script  v2.1.1
 */
(function ($) {
    'use strict';

    var $widget = $('.ha-powerflow-widget');
    if (!$widget.length) return;

    var isPreview     = $widget.closest('#ha-pf-admin-preview-container').length > 0;
    var debugMode     = $widget.attr('data-debug') === 'true';
    var lineOpacity   = parseFloat(haPowerflow.lineOpacity);
    if (isNaN(lineOpacity)) lineOpacity = 1.0;

    function isModOn(key) {
        if (!isPreview) {
            return (haPowerflow.modules && haPowerflow.modules[key] && haPowerflow.modules[key].enabled === 'true');
        }
        return $('#ha-pf-toggle-' + key).is(':checked');
    }

    // Dynamic enablement flags
    var anyModule = function() { return isModOn('solar') || isModOn('battery') || isModOn('ev') || isModOn('heatpump'); };
    var enableSolar = function() { return isModOn('solar'); };
    var enableBattery = function() { return isModOn('battery'); };
    var enableEv = function() { return isModOn('ev'); };
    var enableHeatpump = function() { return isModOn('heatpump'); };

    var lastData = null;
    try {
        var saved = sessionStorage.getItem('ha_pf_last_data');
        if (saved) lastData = JSON.parse(saved);
    } catch(e) {}

    var globalColor   = haPowerflow.lineColor    || '#4a90d9';
    var gridColor     = haPowerflow.gridColor    || globalColor;
    var loadColor     = haPowerflow.loadColor    || globalColor;

    var $debugBar = $('#ha-pf-debug-bar');
    var $coordPin = $('#ha-pf-coord-pin');
    var $status = $widget.find('#ha-pf-status');
    var svgEl = $widget.find('#ha-pf-svg')[0];

    // ── Grid line elements
    var lineEl = $widget.find('#ha-pf-line')[0];
    var laserEl = $widget.find('#ha-pf-path')[0];

    // ── Load line elements
    var loadLaserEl = $widget.find('#ha-pf-load-path')[0];

    if (isPreview) {
        $status.html('✓ Preview Ready');
    }
    
    // Module enablement and colors - mapped from new modules structure
    var mods = haPowerflow.modules || {};
    var pvColor        = (mods.solar   && mods.solar.color)   || globalColor;
    var batteryColor   = (mods.battery && mods.battery.color) || globalColor;
    var evColor        = (mods.ev      && mods.ev.color)      || globalColor;
    var heatpumpColor  = (mods.heatpump && mods.heatpump.color) || globalColor;

    // Static color refs (these are fine as they don't change without a refresh, 
    // though the preview handles their live update via CSS variables)
    var pvLaserEl      = $widget.find('#ha-pf-pv-path')[0];
    var batteryLaserEl = $widget.find('#ha-pf-battery-path')[0];
    var evLaserEl       = $widget.find('#ha-pf-ev-path')[0];
    var heatpumpLaserEl = $widget.find('#ha-pf-heatpump-path')[0];

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
        $widget.find('#' + id).text(val);
    }

    function dotSpeed(absW, maxCap) {
        if (!absW || isNaN(absW) || absW < 10) return null;
        if (!maxCap || isNaN(maxCap)) maxCap = 5000;

        // Calculate percentage of capacity (0.0 to 1.0+)
        var ratio = Math.min(1.8, absW / maxCap);
        
        // Duration curve: Precise inverse exponential for snappier high-power feel
        var dur = 0.4 + (7.6 * (1 - Math.pow(ratio, 0.35)));
        return Math.max(0.35, dur).toFixed(2) + 's';
    }

    // ── Dynamic Intensity ──────────────────────────────────────────────────
    function setIntensity(laserEl, absW) {
        if (!laserEl) return;
        
        // Only update if change is significant (> 15W) to prevent flickering jitter
        var lastW = parseFloat(laserEl.getAttribute('data-last-w') || 0);
        if (Math.abs(absW - lastW) < 15) return;
        laserEl.setAttribute('data-last-w', absW);

        // Detect base width if user has overridden it in PHP (e.g. 2.0)
        var baseWidth = parseFloat(laserEl.getAttribute('data-base-width'));
        if (isNaN(baseWidth)) {
            baseWidth = parseFloat(laserEl.getAttribute('stroke-width')) || 1.2;
            laserEl.setAttribute('data-base-width', baseWidth);
        }

        var maxCap = parseFloat(laserEl.getAttribute('data-max-capacity')) || 5000;
        var ratio = Math.min(2.0, absW / maxCap);

        // Premium Flow: Non-linear scaling for that "pulsing energy" look
        var scale = 1 + (Math.pow(ratio, 0.7) * 2.5); 
        var blur = 2.0 + (Math.pow(ratio, 0.5) * 6.5); 

        var newWidth = (baseWidth * scale).toFixed(1);
        if (laserEl.getAttribute('stroke-width') !== newWidth) {
            laserEl.setAttribute('stroke-width', newWidth);
        }
        
        var newFilter = 'drop-shadow(0 0 ' + blur.toFixed(1) + 'px currentColor)';
        if (laserEl.style.filter !== newFilter) {
            $(laserEl).css('filter', newFilter);
        }

        // Overload state toggle
        if (ratio > 0.95) {
            $(laserEl).addClass('overload');
        } else {
            $(laserEl).removeClass('overload');
        }
    }

    function formatWeather(state) {
        if (!state) return '';
        var map = {
            'clear-night':      'Clear',
            'cloudy':           'Cloudy',
            'fog':              'Fog',
            'hail':             'Hail',
            'lightning':        'Lightning',
            'lightning-rainy':  'Lightning, rainy',
            'partlycloudy':     'Partly cloudy',
            'pouring':          'Pouring',
            'rainy':            'Rainy',
            'snowy':            'Snowy',
            'snowy-rainy':      'Snowy, rainy',
            'sunny':            'Sunny',
            'windy':            'Windy',
            'windy-variant':    'Windy, cloudy',
            'exceptional':      'Exceptional'
        };
        // Normalize: if we have 'clear-night' but user provided 'Clear', we still check map
        var key = state.toLowerCase();
        return map[key] || state;
    }

    function setWeatherIcon(state) {
        var el = document.getElementById('ha-pf-weather-icon');
        if (!el) return;

        var key = (state || '').toLowerCase();
        var iconHtml = '';

        // Geometric Laser-Style Icon Definitions (Centered around 0,0)
        var icons = {
            'sunny': '<circle cx="0" cy="0" r="10" /><g class="ha-pf-rotate"><line x1="0" y1="-14" x2="0" y2="-18" /><line x1="0" y1="14" x2="0" y2="18" /><line x1="-14" y1="0" x2="-18" y2="0" /><line x1="14" y1="0" x2="18" y2="0" /><line x1="-10" y1="-10" x2="-13" y2="-13" /><line x1="10" y1="10" x2="13" y2="13" /><line x1="-10" y1="10" x2="-13" y2="13" /><line x1="10" y1="-10" x2="13" y2="-13" /></g>',
            'clear-night': '<path d="M-10,-5 A12,12 0 1,0 8,10 A9,9 0 0,1 -10,-5" />',
            'cloudy': '<path d="M-15,5 Q-15,-5 -5,-5 Q-2,-5 0,-2 Q2,-10 10,-10 Q18,-10 18,0 Q18,5 10,5 Z" transform="translate(-2, 0)" />',
            'partlycloudy': '<circle cx="-6" cy="-6" r="7" /><path d="M-10,5 Q-10,-2 -3,-2 Q0,-2 2,1 Q4,-5 10,-5 Q16,-5 16,3 Q16,8 10,8 Z" transform="translate(0, 2)" />',
            'rainy': '<path d="M-12,2 Q-12,-6 -4,-6 Q-1,-6 1,-3 Q3,-10 9,-10 Q16,-10 16,0 Q16,5 9,5 L-12,5 Z" transform="translate(-2, -3)" /><line x1="-8" y1="8" x2="-10" y2="14" class="ha-pf-rain" /><line x1="0" y1="8" x2="-2" y2="14" class="ha-pf-rain" /><line x1="8" y1="8" x2="6" y2="14" class="ha-pf-rain" />',
            'pouring': '<path d="M-12,2 Q-12,-6 -4,-6 Q-1,-6 1,-3 Q3,-10 9,-10 Q16,-10 16,0 Q16,5 9,5 L-12,5 Z" transform="translate(-2, -3)" /><line x1="-8" y1="8" x2="-11" y2="16" stroke-width="1.5" /><line x1="0" y1="8" x2="-3" y2="16" stroke-width="1.5" /><line x1="8" y1="8" x2="5" y2="16" stroke-width="1.5" />',
            'lightning': '<path d="M-12,2 Q-12,-6 -4,-6 Q-1,-6 1,-3 Q3,-10 9,-10 Q16,-10 16,0 Q16,5 9,5 L-12,5 Z" transform="translate(-2, -3)" /><path d="M0,6 L-4,12 L1,12 L-3,20" stroke-width="1.5" stroke-linejoin="round" />',
            'lightning-rainy': '<path d="M-12,2 Q-12,-6 -4,-6 Q-1,-6 1,-3 Q3,-10 9,-10 Q16,-10 16,0 Q16,5 9,5 L-12,5 Z" transform="translate(-2, -3)" /><path d="M4,6 L1,12 L6,12 L2,20" stroke-width="1.5" stroke-linejoin="round" /><line x1="-8" y1="8" x2="-10" y2="14" />',
            'snowy': '<path d="M-12,2 Q-12,-6 -4,-6 Q-1,-6 1,-3 Q3,-10 9,-10 Q16,-10 16,0 Q16,5 9,5 L-12,5 Z" transform="translate(-2, -3)" /><circle cx="-7" cy="11" r="1.5" fill="currentColor" stroke="none" /><circle cx="2" cy="14" r="1.5" fill="currentColor" stroke="none" /><circle cx="10" cy="11" r="1.5" fill="currentColor" stroke="none" />',
            'snowy-rainy': '<path d="M-12,2 Q-12,-6 -4,-6 Q-1,-6 1,-3 Q3,-10 9,-10 Q16,-10 16,0 Q16,5 9,5 L-12,5 Z" transform="translate(-2, -3)" /><circle cx="-7" cy="11" r="1.5" fill="currentColor" stroke="none" /><line x1="2" y1="10" x2="0" y2="16" /><circle cx="10" cy="11" r="1.5" fill="currentColor" stroke="none" />',
            'fog': '<line x1="-15" y1="-5" x2="15" y2="-5" /><line x1="-18" y1="2" x2="18" y2="2" /><line x1="-12" y1="9" x2="12" y2="9" />',
            'windy': '<path d="M-15,-5 Q-5,-15 5,-5 T25,-5" /><path d="M-20,5 Q-10,-5 0,5 T20,5" />',
            'windy-variant': '<path d="M-10,-5 Q-10,-12 -3,-12 Q0,-12 2,-9 Q4,-15 10,-15 Q16,-15 16,-7 Q16,-2 10,-2 Z" transform="translate(0, 0)" /><path d="M-20,5 Q-10,-5 0,5 T20,5" />',
            'hail': '<path d="M-12,2 Q-12,-6 -4,-6 Q-1,-6 1,-3 Q3,-10 9,-10 Q16,-10 16,0 Q16,5 9,5 L-12,5 Z" transform="translate(-2, -3)" /><circle cx="-7" cy="12" r="2" fill="currentColor" stroke="none" /><circle cx="2" cy="12" r="2" fill="currentColor" stroke="none" /><circle cx="10" cy="12" r="2" fill="currentColor" stroke="none" />',
            'exceptional': '<circle cx="0" cy="0" r="12" stroke-dasharray="4 2" />'
        };

        el.innerHTML = icons[key] || '';
    }

    function getPulseCount(absW) {
        if (absW <= 150) return 1;
        if (absW <= 600) return 2;
        if (absW <= 2000) return 3;
        if (absW <= 6000) return 4;
        return 5;
    }

    function animateLaser(laserEl, dur, forceRestart, reverse, colorOverride, pulseCount, absW) {
        if (!laserEl) return;

        pulseCount = pulseCount || 1;
        
        var targetColor = colorOverride || globalColor;
        if (laserEl.getAttribute('stroke') !== targetColor) {
            laserEl.setAttribute('stroke', targetColor);
        }
        
        if (laserEl.getAttribute('opacity') !== '1') {
            laserEl.setAttribute('opacity', '1'); 
        }

        // Apply intensity & overload state
        setIntensity(laserEl, absW);

        var oldDur = laserEl.getAttribute('data-dur');
        var oldPulses = laserEl.getAttribute('data-pulses');

        var totalLen = laserEl.getTotalLength() || 1000;
        
        // DYNAMIC STRETCHING: Dots get longer as speed increases
        var maxCap = parseFloat(laserEl.getAttribute('data-max-capacity')) || 5000;
        var ratio = Math.min(1.5, absW / maxCap);
        var stretchFactor = 1 + (ratio * 1.5); // 1x to 2.5x stretch
        
        var baseDashLen = Math.min(100, Math.max(10, totalLen * 0.12));
        var dashLen = (baseDashLen * stretchFactor);
        
        var segmentLen = totalLen / pulseCount;
        var gapLen = Math.max(dashLen + 10, segmentLen - dashLen);

        if (oldDur !== dur || forceRestart || laserEl.getAttribute('data-rev') !== String(reverse) || oldPulses !== String(pulseCount)) {
            laserEl.setAttribute('data-dur', dur);
            laserEl.setAttribute('data-rev', String(reverse));
            laserEl.setAttribute('data-pulses', String(pulseCount));

            var animName = getAnimationName(totalLen, reverse);

            laserEl.style.animation = 'none';
            laserEl.style.strokeDasharray = dashLen.toFixed(1) + 'px ' + gapLen.toFixed(1) + 'px';

            void laserEl.offsetWidth;
            
            // Re-apply animation (merging overload pulse if active)
            var baseAnim = animName + ' ' + dur + ' linear infinite';
            if (ratio > 0.95) {
                laserEl.style.animation = baseAnim + ', ha-pf-overload 0.2s linear infinite';
            } else {
                laserEl.style.animation = baseAnim;
            }
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
        var gridMax = parseFloat(laserEl ? laserEl.getAttribute('data-max-capacity') : 10000);
        var gridDur = dotSpeed(isNaN(gridPower) ? 0 : absGrid, gridMax);
        var gridPulses = getPulseCount(absGrid);

        if (!gridDur || absGrid < 50) {
            stopLaser(laserEl);
            
            // Calculate primary source (PV or Battery)
            var sources = [];
            if (enableSolar() && pvPower > 0) sources.push({ name: 'PV', val: pvPower });
            if (enableBattery() && batteryPower > 50) sources.push({ name: 'Battery', val: batteryPower });
            
            sources.sort(function(a, b) { return b.val - a.val; });

            // Calculate primary consumer
            var consumers = [
                { name: 'House', val: Math.abs(isNaN(loadPower) ? 0 : loadPower) }
            ];
            if (enableBattery() && batteryPower < -50) { // Battery is CHARGING (consuming)
                consumers.push({ name: 'Battery', val: Math.abs(batteryPower) });
            }
            if (enableEv() && evPower > 50) {
                consumers.push({ name: 'EV', val: Math.abs(evPower) });
            }
            if (enableHeatpump() && heatpumpPower > 50) {
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

            $widget[0].style.setProperty('--ha-pf-grid-color', gridColor);
        } else if (laserEl) {
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
                if (haPowerflow.isCustomGrid !== 'true') activeGridColor = '#1976d2'; // Default Importing Blue (Darker)
                
                $widget[0].style.setProperty('--ha-pf-grid-color', activeGridColor);

                // Calculate primary consumer
                var consumers = [
                    { name: 'House', val: Math.abs(isNaN(loadPower) ? 0 : loadPower) }
                ];
                if (enableBattery() && batteryPower < -50) { 
                    consumers.push({ name: 'Battery', val: Math.abs(batteryPower) });
                }
                if (enableEv() && evPower > 50) {
                    consumers.push({ name: 'EV', val: Math.abs(evPower) });
                }
                if (enableHeatpump() && heatpumpPower > 50) {
                    consumers.push({ name: 'Heat Pump', val: Math.abs(heatpumpPower) });
                }
                consumers.sort(function(a, b) { return b.val - a.val; });
                
                if (consumers.length > 0 && consumers[0].val > 50) {
                    svgText('ha-pf-flow-sub', 'To ' + consumers[0].name);
                } else {
                    svgText('ha-pf-flow-sub', '');
                }
                animateLaser(laserEl, gridDur, gridPathChanged, false, activeGridColor, gridPulses, absGrid);
            } else {
                svgText('ha-pf-flow-main', 'EXPORTING');
                svgText('ha-pf-flow-sub', '');
                
                // Smart Palette / Intensity Check for Grid
                var activeGridColor = gridColor;
                if (haPowerflow.isCustomGrid !== 'true') activeGridColor = '#2ecc71'; // Default Exporting Green
                
                $widget[0].style.setProperty('--ha-pf-grid-color', activeGridColor);
                
                animateLaser(laserEl, gridDur, gridPathChanged, false, activeGridColor, gridPulses, absGrid);
            }
        }

        // ── Load laser ──────────────────────────────────────────────────────
        if (anyModule() && loadLaserEl) {
            var loadVal = isNaN(loadPower) ? gridPower : loadPower;
            var absLoad = Math.abs(loadVal);
            var loadMax = parseFloat(loadLaserEl.getAttribute('data-max-capacity')) || 8000;
            var loadDur = dotSpeed(absLoad, loadMax);
            var loadPulses = getPulseCount(absLoad);
            
            // Smart Palette for House
            var activeHouseColor = loadColor;
            if (haPowerflow.isCustomHouse !== 'true') activeHouseColor = '#ffffff'; // Default House White

            if (loadDur) {
                $widget[0].style.setProperty('--ha-pf-load-color', activeHouseColor);
                animateLaser(loadLaserEl, loadDur, false, false, activeHouseColor, loadPulses, absLoad);
            } else {
                $widget[0].style.setProperty('--ha-pf-load-color', loadColor);
                stopLaser(loadLaserEl);
            }
        }

        // ── PV laser ────────────────────────────────────────────────────────
        if (enableSolar() && pvLaserEl) {
            var absPv = Math.abs(isNaN(pvPower) ? 0 : pvPower);
            var pvMax = parseFloat(pvLaserEl.getAttribute('data-max-capacity')) || 6000;
            var pvDur = dotSpeed(absPv, pvMax);
            var pvPulses = getPulseCount(absPv);
            
            // Smart Palette for PV
            var activePvColor = pvColor;
            if (haPowerflow.isCustomPv !== 'true') activePvColor = '#f1c40f'; // Default Solar Gold

            if (pvDur) {
                $widget[0].style.setProperty('--ha-pf-pv-color', activePvColor);
                animateLaser(pvLaserEl, pvDur, false, false, activePvColor, pvPulses, absPv);
            } else {
                $widget[0].style.setProperty('--ha-pf-pv-color', pvColor);
                stopLaser(pvLaserEl);
            }
        }

        // ── Battery laser ───────────────────────────────────────────────────
        if (enableBattery() && batteryLaserEl) {
            var absBat = Math.abs(isNaN(batteryPower) ? 0 : batteryPower);
            var batMax = parseFloat(batteryLaserEl.getAttribute('data-max-capacity')) || 5000;
            var batDur = dotSpeed(absBat, batMax);
            var batPulses = getPulseCount(absBat);

            // Smart Palette for Battery
            var activeBatColor = batteryColor;
            if (haPowerflow.isCustomBattery !== 'true') {
                if (batteryPower > 0) activeBatColor = '#2ecc71'; // Charging Green
                else activeBatColor = '#e67e22'; // Discharging Orange
            }

            if (batDur) {
                $widget[0].style.setProperty('--ha-pf-battery-color', activeBatColor);
                var batPath = (batteryPower > 0)
                    ? batteryPath
                    : reversePath(batteryPath);
                var oldBatPath = batteryLaserEl.getAttribute('d');
                var batPathChanged = false;

                if (oldBatPath !== batPath) {
                    batteryLaserEl.setAttribute('d', batPath);
                    batPathChanged = true;
                }
                animateLaser(batteryLaserEl, batDur, batPathChanged, false, activeBatColor, batPulses, absBat);
            } else {
            }
        }

        // ── EV laser ────────────────────────────────────────────────────────
        if (enableEv() && evLaserEl) {
            var absEv = Math.abs(isNaN(evPower) ? 0 : evPower);
            var evMax = parseFloat(evLaserEl.getAttribute('data-max-capacity')) || 7000;
            var evDur = dotSpeed(absEv, evMax);
            var evPulses = getPulseCount(absEv);

            // Smart Palette for EV
            var activeEvColor = evColor;
            if (haPowerflow.isCustomEv !== 'true') activeEvColor = '#349bef'; // Default EV Sky Blue

            if (evDur) {
                $widget[0].style.setProperty('--ha-pf-ev-color', activeEvColor);
                animateLaser(evLaserEl, evDur, false, false, activeEvColor, evPulses, absEv);
            } else {
                $widget[0].style.setProperty('--ha-pf-ev-color', evColor);
                stopLaser(evLaserEl);
            }
        }

        // ── Heat Pump laser ─────────────────────────────────────────────────
        if (enableHeatpump() && heatpumpLaserEl) {
            var absHp = Math.abs(isNaN(heatpumpPower) ? 0 : heatpumpPower);
            var hpMax = parseFloat(heatpumpLaserEl.getAttribute('data-max-capacity')) || 5000;
            var hpDur = dotSpeed(absHp, hpMax);
            var hpPulses = getPulseCount(absHp);

            // Smart Palette for Heat Pump
            var activeHpColor = heatpumpColor;
            if (haPowerflow.isCustomHp !== 'true') activeHpColor = '#9b59b6'; // Default Heat Pump Purple

            if (hpDur) {
                $widget[0].style.setProperty('--ha-pf-heatpump-color', activeHpColor);
                animateLaser(heatpumpLaserEl, hpDur, false, false, activeHpColor, hpPulses, absHp);
            } else {
                $widget[0].style.setProperty('--ha-pf-heatpump-color', heatpumpColor);
                stopLaser(heatpumpLaserEl);
            }
        }
    }

    // ── Fetch ──────────────────────────────────────────────────────────────
    function fetchData() {
        if (isPreview) {
            processResponse(simulateData());
            return;
        }

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
            url: haPowerflow.restUrl,
            method: 'GET',
            success: function(d) { processResponse(d, false); },
            error: function (xhr) {
                var msg = '⚠ API error';
                
                // For a 400 or 500 thrown by WordPress REST API (like WP_Error), grab the JSON error message
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg += ' — ' + xhr.responseJSON.message;
                } else if (xhr.status === 0) {
                    msg += ' — could not reach the server. Check your internet connection.';
                } else {
                    msg += ' (HTTP ' + xhr.status + '). Check browser console for details.';
                }
                
                if (lastData) {
                    processResponse(lastData, true);
                } else {
                    $status.addClass('ha-pf-error').text(msg);
                }
            }
        });
    }

    function simulateData() {
        var d = {
            grid_power: { state: (2000 + Math.random() * 500).toFixed(0), unit: 'W' },
            load_power: { state: (1200 + Math.random() * 300).toFixed(0), unit: 'W' },
            grid_energy: { state: '42.8', unit: 'kWh' },
            grid_energy_out: { state: '15.6', unit: 'kWh' },
            grid_price_in: { state: '0.28', unit: '£' },
            grid_price_out: { state: '0.15', unit: '£' },
            load_energy: { state: '24.5', unit: 'kWh' },
            pv_power: { state: (350 + Math.random() * 50).toFixed(0), unit: 'W' },
            pv_energy: { state: '18.2', unit: 'kWh' },
            pv1_power: { state: '180', unit: 'W' },
            pv2_power: { state: '170', unit: 'W' },
            battery_power: { state: ((-300) - Math.random() * 200).toFixed(0), unit: 'W' },
            battery_soc: { state: '85', unit: '%' },
            ev_power: { state: '2100', unit: 'W' },
            ev_soc: { state: '42', unit: '%' },
            heatpump_power: { state: '1650', unit: 'W' },
            heatpump_efficiency: { state: '3.4', unit: '' },
            weather: { state: 'sunny', unit: '' }
        };
        
        if (haPowerflow.customEntities && haPowerflow.customEntities.length) {
            haPowerflow.customEntities.forEach(function(ent, i) {
                var label = (ent.label || '').toUpperCase();
                if (label.indexOf('PV') !== -1 || label.indexOf('SOLAR') !== -1) {
                    d['custom_' + i] = { state: (1500 + Math.random() * 500).toFixed(0), unit: 'W' };
                } else {
                    d['custom_' + i] = { state: '22.5', unit: '°C' };
                }
            });
        }
        return d;
    }

    function processResponse(d, isCached) {
        if (isCached === undefined) isCached = false;
        
        // Return data is directly the entity object now, not wrapped in `data.data`
        // Ensure we handle case where WordPress might return WP_Error which technically could be 200 depending on server configuration (though we sent 400/500).
        if (d.code && d.message) {
            $status.addClass('ha-pf-error').text('⚠ ' + d.message);
            return;
        }

        // Robustness Check: Ensure HA returned a valid mapped object with required sensors
        if (typeof d !== 'object' || d === null || !d.grid_power || !d.load_power) {
            if (!isPreview) {
                $status.addClass('ha-pf-error').text('⚠ Unexpected response from Home Assistant. Ensure your configuration is correct.');
            }
            return;
        }

        // Module checks
        var hasSolar = isModOn('solar');
        var hasBat   = isModOn('battery');
        var hasEv    = isModOn('ev');
        var hasHP    = isModOn('heatpump');

        // Warn if individual entities are missing
        var missing = [];
        if (!haPowerflow.gridPower) missing.push('Grid');
        if (!haPowerflow.loadPower) missing.push('House');
        if (!haPowerflow.gridEnergy) missing.push('Grid Energy');
        if (!haPowerflow.loadEnergy) missing.push('House Energy');

        // Modules - use new nested structure
        var m = haPowerflow.modules || {};
        if (hasSolar && (!m.solar || !m.solar.power)) missing.push('Solar');
        if (hasBat && (!m.battery || !m.battery.power)) missing.push('Battery');
        if (hasEv && (!m.ev || !m.ev.power)) missing.push('EV');
        if (hasHP && (!m.heatpump || !m.heatpump.power)) missing.push('Heat Pump');

        svgText('ha-pf-grid-power', fmt(d.grid_power.state, d.grid_power.unit));
        
        // Grid Energy Import
        svgText('ha-pf-grid-energy', 'In: ' + fmt(d.grid_energy.state, d.grid_energy.unit));

        // Grid Energy Export (out) - Conditional Visibility
        var showExport = (hasSolar || hasBat || hasEv);
        var $gridEnergyOut = $widget.find('#ha-pf-grid-energy-out');
        if (showExport) {
            $gridEnergyOut.show();
            svgText('ha-pf-grid-energy-out', 'Out: ' + fmt(d.grid_energy_out.state, d.grid_energy_out.unit));
        } else {
            $gridEnergyOut.hide();
        }

        // Grid Prices
        svgText('ha-pf-grid-price-in', 'In Price: £' + (parseFloat(d.grid_price_in.state) || 0).toFixed(2));
        
        var $gridPriceOut = $widget.find('#ha-pf-grid-price-out');
        if (showExport) {
            $gridPriceOut.show();
            svgText('ha-pf-grid-price-out', 'Out Price: £' + (parseFloat(d.grid_price_out.state) || 0).toFixed(2));
        } else {
            $gridPriceOut.hide();
        }
        svgText('ha-pf-load-power', fmt(d.load_power.state, d.load_power.unit));
        svgText('ha-pf-load-energy', fmt(d.load_energy.state, d.load_energy.unit));

        if (hasSolar && d.pv_power) {
            svgText('ha-pf-pv-power', fmt(d.pv_power.state, d.pv_power.unit));
            if (d.pv_energy) svgText('ha-pf-pv-energy', fmt(d.pv_energy.state, d.pv_energy.unit));
            if (d.pv1_power) svgText('ha-pf-solar-pv1', fmt(d.pv1_power.state, d.pv1_power.unit));
            if (d.pv2_power) svgText('ha-pf-solar-pv2', fmt(d.pv2_power.state, d.pv2_power.unit));
        }

        var batPowerVal = NaN;
        if (hasBat && d.battery_power) {
            batPowerVal = parseFloat(d.battery_power.state);
            svgText('ha-pf-battery-power', fmt(d.battery_power.state, d.battery_power.unit));
        }
        if (hasBat && d.battery_soc) {
            var socVal = parseFloat(d.battery_soc.state);
            svgText('ha-pf-battery-soc', isNaN(socVal) ? 'N/A' : socVal.toFixed(0) + '\u202f%');
        }

        var evPowerVal = NaN;
        if (hasEv && d.ev_power) {
            evPowerVal = parseFloat(d.ev_power.state);
            svgText('ha-pf-ev-power', fmt(d.ev_power.state, d.ev_power.unit));
        }
        if (hasEv && d.ev_soc) {
            var evSocVal = parseFloat(d.ev_soc.state);
            svgText('ha-pf-ev-soc', isNaN(evSocVal) ? 'N/A' : evSocVal.toFixed(0) + '\u202f%');
        }

        var hpPowerVal = NaN;
        if (hasHP && d.heatpump_power) {
            hpPowerVal = parseFloat(d.heatpump_power.state);
            svgText('ha-pf-heatpump-power', fmt(d.heatpump_power.state, d.heatpump_power.unit));
        }
        if (hasHP && d.heatpump_efficiency) {
            var copVal = parseFloat(d.heatpump_efficiency.state);
            svgText('ha-pf-heatpump-efficiency', isNaN(copVal) ? 'N/A' : 'COP\u202f' + copVal.toFixed(1));
        }

        var pvPowerVal = NaN;
        if (hasSolar && d.pv_power) {
            pvPowerVal = parseFloat(d.pv_power.state);
        }

        var weatherOn = isModOn('weather');
        if (weatherOn && d.weather) {
            svgText('ha-pf-weather', formatWeather(d.weather.state));
            setWeatherIcon(d.weather.state);
        }

        setFlow({
            grid: parseFloat(d.grid_power.state),
            load: parseFloat(d.load_power.state),
            pv: pvPowerVal,
            battery: batPowerVal,
            ev: evPowerVal,
            heatpump: hpPowerVal,
        });

        // Update custom entities
        if (haPowerflow.customEntities && haPowerflow.customEntities.length) {
            haPowerflow.customEntities.forEach(function(item, index) {
                var entry = d['custom_' + index];
                if (entry) {
                    var val = fmt(entry.state, entry.unit);
                    var $group = $widget.find('#ha-pf-custom-' + index);
                    $group.find('.ha-pf-custom-value').text(val);
                }
            });
        }

        if (missing.length) {
            if (!isPreview) {
                $status.addClass('ha-pf-error').text('⚠ Missing entity IDs: ' + missing.join(', ') + ' — check Settings.');
            }
        } else if (
            d.grid_power.state === 'unavailable' ||
            d.grid_power.state === 'unknown' ||
            d.grid_power.state === 'N/A'
        ) {
            if (!isPreview) {
                $status.addClass('ha-pf-error').text('⚠ Grid Power sensor returned "' + d.grid_power.state + '" — check entity ID in Settings.');
            }
        } else {
            if (isCached) {
                $status.addClass('ha-pf-error').text('⚠ Connection Lost — Showing Last Known Data');
            } else {
                // Save successful data
                if (!isPreview) {
                    lastData = d;
                    try { sessionStorage.setItem('ha_pf_last_data', JSON.stringify(d)); } catch(e) {}
                    $status.removeClass('ha-pf-error').text('Updated ' + new Date().toLocaleTimeString());
                } else {
                    $status.removeClass('ha-pf-error').css({color:'#f1c40f',fontWeight:'800'}).html('⚡ PREVIEW MODE (SIMULATED DATA)');
                }
            }
        }
    }

    $(document).ready(function () {
        fetchData();
        var interval = isPreview ? 2000 : haPowerflow.refreshInterval;
        setInterval(fetchData, interval);

        // Register Service Worker for PWA
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                var swUrl = haPowerflow.swUrl;
                navigator.serviceWorker.register(swUrl).then(function(registration) {
                    if (debugMode) console.log('HA Powerflow: SW registered with scope: ', registration.scope);
                }, function(err) {
                    if (debugMode) console.log('HA Powerflow: SW registration failed: ', err);
                });
            });
        }
    });

})(jQuery);
