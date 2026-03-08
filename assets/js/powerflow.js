/**
 * HA Powerflow – frontend script  v1.9.0
 */
(function ($) {
    'use strict';

    var $widget = $('.ha-powerflow-widget');
    if (!$widget.length) return;

    var isPreview     = $widget.closest('#ha-pf-admin-preview-container').length > 0;
    var debugMode     = $widget.attr('data-debug') === 'true';
    var anyModule     = haPowerflow.anyModule === 'true';
    var lineOpacity   = parseFloat(haPowerflow.lineOpacity);
    if (isNaN(lineOpacity)) lineOpacity = 1.0;

    if (isPreview) {
        $status.html('✓ Preview Ready (Real-time Config Active)');
        return;
    }

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
        if (absW <= 1500) return '1.2s';  
        return '0.7s';                  
    }

    // ── Dynamic Intensity ──────────────────────────────────────────────────
    function setIntensity(laserEl, absW) {
        if (!laserEl) return;
        
        // Only update if change is significant (> 20W) to prevent flickering jitter
        var lastW = parseFloat(laserEl.getAttribute('data-last-w') || 0);
        if (Math.abs(absW - lastW) < 20) return;
        laserEl.setAttribute('data-last-w', absW);

        // Detect base width if user has overridden it in PHP (e.g. 2.0)
        var baseWidth = parseFloat(laserEl.getAttribute('data-base-width'));
        if (isNaN(baseWidth)) {
            baseWidth = parseFloat(laserEl.getAttribute('stroke-width')) || 1.2;
            laserEl.setAttribute('data-base-width', baseWidth);
        }

        var scale = Math.min(3, 1 + (absW / 5000)); 
        var blur = Math.min(6, 2.5 + (absW / 2000));

        var newWidth = (baseWidth * scale).toFixed(1);
        if (laserEl.getAttribute('stroke-width') !== newWidth) {
            laserEl.setAttribute('stroke-width', newWidth);
        }
        
        var newFilter = 'drop-shadow(0 0 ' + blur.toFixed(1) + 'px currentColor)';
        if (laserEl.style.filter !== newFilter) {
            $(laserEl).css('filter', newFilter);
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
        if (absW <= 200) return 1;
        if (absW <= 800) return 2;
        if (absW <= 2500) return 3;
        return 4;
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

        // Apply intensity
        setIntensity(laserEl, absW);

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

            $widget[0].style.setProperty('--ha-pf-grid-color', gridColor);
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
                if (haPowerflow.isCustomGrid !== 'true') activeGridColor = '#1976d2'; // Default Importing Blue (Darker)
                
                $widget[0].style.setProperty('--ha-pf-grid-color', activeGridColor);

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
        if (anyModule && loadLaserEl) {
            var loadVal = isNaN(loadPower) ? gridPower : loadPower;
            var absLoad = Math.abs(loadVal);
            var loadDur = dotSpeed(absLoad);
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
        if (enableSolar && pvLaserEl) {
            var absPv = Math.abs(isNaN(pvPower) ? 0 : pvPower);
            var pvDur = dotSpeed(absPv);
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
        if (enableBattery && batteryLaserEl) {
            var absBat = Math.abs(isNaN(batteryPower) ? 0 : batteryPower);
            var batDur = dotSpeed(absBat);
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
                $widget[0].style.setProperty('--ha-pf-battery-color', batteryColor);
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
                $widget[0].style.setProperty('--ha-pf-ev-color', activeEvColor);
                animateLaser(evLaserEl, evDur, false, false, activeEvColor, evPulses, absEv);
            } else {
                $widget[0].style.setProperty('--ha-pf-ev-color', evColor);
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
            success: function (d) {
                // Return data is directly the entity object now, not wrapped in `data.data`
                // Ensure we handle case where WordPress might return WP_Error which technically could be 200 depending on server configuration (though we sent 400/500).
                if (d.code && d.message) {
                    $status.addClass('ha-pf-error').text('⚠ ' + d.message);
                    return;
                }

                // Robustness Check: Ensure HA returned a valid mapped object with required sensors
                if (typeof d !== 'object' || d === null || !d.grid_power || !d.load_power) {
                    $status.addClass('ha-pf-error').text('⚠ Unexpected response from Home Assistant. Ensure your configuration is correct.');
                    return;
                }

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
                
                // Grid Energy Import
                svgText('ha-pf-grid-energy', 'In: ' + fmt(d.grid_energy.state, d.grid_energy.unit));

                // Grid Energy Export (out) - Conditional Visibility
                var showExport = (enableSolar || enableBattery || enableEv);
                var $gridEnergyOut = $('#ha-pf-grid-energy-out');
                if (showExport) {
                    $gridEnergyOut.show();
                    svgText('ha-pf-grid-energy-out', 'Out: ' + fmt(d.grid_energy_out.state, d.grid_energy_out.unit));
                } else {
                    $gridEnergyOut.hide();
                }

                // Grid Prices
                svgText('ha-pf-grid-price-in', 'In Price: £' + (parseFloat(d.grid_price_in.state) || 0).toFixed(2));
                
                var $gridPriceOut = $('#ha-pf-grid-price-out');
                if (showExport) {
                    $gridPriceOut.show();
                    svgText('ha-pf-grid-price-out', 'Out Price: £' + (parseFloat(d.grid_price_out.state) || 0).toFixed(2));
                } else {
                    $gridPriceOut.hide();
                }
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

                if (haPowerflow.enableWeather === 'true' && d.weather) {
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
                            var $group = $('#ha-pf-custom-' + index);
                            $group.find('.ha-pf-custom-value').text(val);
                        }
                    });
                }

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
            },
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
                $status.addClass('ha-pf-error').text(msg);
            }
        });
    }

    $(document).ready(function () {
        fetchData();
        setInterval(fetchData, haPowerflow.refreshInterval);
    });

})(jQuery);
