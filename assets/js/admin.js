jQuery(document).ready(function ($) {
    var pfUpdatePreview; // High-level ref to allow cross-function access

    // ── Colour picker ──────────────────────────────────────────────────────
    $('.ha-pf-color-picker').wpColorPicker();

    function syncAllModules() {
        var modules = Object.keys(haPowerflow.modules || {});
        modules.push('weather');

        modules.forEach(function (key) {
            var $cb = $('#ha-pf-toggle-' + key);
            var $section = $('#ha-pf-section-' + key);
            $section.toggle($cb.is(':checked'));
            
            // Sync rows inside the sections if they are core modules
            if (haPowerflow.modules && haPowerflow.modules[key]) {
                var prefix = haPowerflow.modules[key].prefix;
                var on = $cb.is(':checked');
                $('#ha-pf-' + prefix + '-line-row').toggle(on);
                $('#ha-pf-' + prefix + '-label-row').toggle(on);
                $('#ha-pf-' + prefix + '-color-row').toggle(on);
                $('.ha-pf-limit-row-' + key).toggle(on);
            }
        });

        syncLoadLine();
        syncPlaceholder();
    }

    function anyModuleEnabled() {
        var coreModules = Object.keys(haPowerflow.modules || {});
        return coreModules.some(function (k) {
            return $('#ha-pf-toggle-' + k).is(':checked');
        });
    }

    function syncLoadLine() {
        var on = anyModuleEnabled();
        $('#ha-pf-load-line-row').toggle(on);
        $('#ha-pf-load-color-row').toggle(on);
    }

    function syncPlaceholder() {
        var anyVisible = $('.ha-pf-module-card:visible').length > 0;
        $('#ha-pf-no-modules').toggle(!anyVisible);
    }

    // Initialize toggles
    var allKeys = Object.keys(haPowerflow.modules || {});
    allKeys.push('weather');

    allKeys.forEach(function (key) {
        var $cb      = $('#ha-pf-toggle-' + key);
        var $section = $('#ha-pf-section-' + key);

        $cb.on('change', function () {
            if ($cb.is(':checked')) {
                $section.slideDown(200);
            } else {
                $section.slideUp(200);
            }
            setTimeout(function() {
                syncLoadLine();
                syncPlaceholder();
                
                if (haPowerflow.modules && haPowerflow.modules[key]) {
                    var prefix = haPowerflow.modules[key].prefix;
                    var on = $cb.is(':checked');
                    $('#ha-pf-' + prefix + '-line-row').toggle(on);
                    $('#ha-pf-' + prefix + '-label-row').toggle(on);
                    $('#ha-pf-' + prefix + '-color-row').toggle(on);
                    $('.ha-pf-limit-row-' + key).toggle(on);
                }
            }, 250);
        });
    });

    syncAllModules();

    // ── Test Connection ────────────────────────────────────────────────────
    var $testBtn    = $('#ha-pf-test-btn');
    var $testResult = $('#ha-pf-test-result');

    $testBtn.on('click', function () {
        var url   = $('#ha_url').val().trim();
        var token = $('#ha_token').val().trim();

        if (!url || !token) {
            $testResult
                .css({ background: '#fef2f2', border: '1px solid #f87171', color: '#b91c1c' })
                .text('⚠ Please enter both a Home Assistant URL and Access Token first.')
                .show();
            return;
        }

        $testBtn.prop('disabled', true).text('Testing…');
        $testResult.hide();

        $.ajax({
            url    : haPfAdmin.ajaxUrl,
            method : 'POST',
            data   : {
                action : 'ha_pf_test_connection',
                nonce  : haPfAdmin.nonce,
                ha_url : url,
                ha_token: token,
            },
            success: function (res) {
                if (res.success) {
                    $testResult
                        .css({ background: '#f0fdf4', border: '1px solid #4ade80', color: '#15803d' })
                        .text('✓ ' + res.data);
                } else {
                    $testResult
                        .css({ background: '#fef2f2', border: '1px solid #f87171', color: '#b91c1c' })
                        .text('✗ ' + res.data);
                }
                $testResult.show();
            },
            error: function () {
                $testResult
                    .css({ background: '#fef2f2', border: '1px solid #f87171', color: '#b91c1c' })
                    .text('✗ Request failed — check your browser console for details.')
                    .show();
            },
            complete: function () {
                $testBtn.prop('disabled', false).text('Test Connection');
            }
        });
    });

    // Hide result when form is saved
    $('form').on('submit', function () {
        $testResult.hide();
    });

    // ── Media Uploader ─────────────────────────────────────────────────────
    var mediaFrame;

    $('#ha-pf-media-btn').on('click', function (e) {
        e.preventDefault();

        if (mediaFrame) {
            mediaFrame.open();
            return;
        }

        mediaFrame = wp.media({
            title    : haPfAdmin.selectImage,
            button   : { text: haPfAdmin.useImage },
            multiple : false,
            library  : { type: 'image' },
        });

        mediaFrame.on('select', function () {
            var attachment = mediaFrame.state().get('selection').first().toJSON();
            var url        = attachment.url;

            $('#bg_image').val(url);

            var $preview = $('#ha-pf-img-preview');
            $preview.find('img').attr('src', url);
            $preview.show();
        });

        mediaFrame.open();
    });

    // Live preview when URL is typed manually
    $('#bg_image').on('input', function () {
        var url = $(this).val().trim();
        var $preview = $('#ha-pf-img-preview');
        if (url) {
            $preview.find('img').attr('src', url);
            $preview.show();
        } else {
            $preview.hide();
        }
    });

    // ── Restore & Backup ──────────────────────────────────────────────────
    var $status = $('#ha-pf-restore-status');

    function showStatus(msg, isError) {
        $status.stop(true, true).hide()
            .css({ color: isError ? '#b91c1c' : '#15803d', display: 'block' })
            .text(msg)
            .fadeOut(5000);
    }

    $('#ha-pf-restore-btn').on('click', function () {
        var file = $('#ha-pf-snapshot-select').val();
        if (!file) return;

        if (!confirm('Are you sure you want to restore settings from ' + file + '? Current settings will be overwritten.')) return;

        var $btn = $(this);
        $btn.prop('disabled', true);

        $.post(haPfAdmin.ajaxUrl, {
            action: 'ha_powerflow_restore_snapshot',
            nonce: haPfAdmin.nonce,
            filename: file
        }, function (res) {
            if (res.success) {
                showStatus(res.data, false);
                setTimeout(function () { location.reload(); }, 1500);
            } else {
                showStatus(res.data, true);
            }
        }).always(function () { $btn.prop('disabled', false); });
    });

    $('#ha-pf-snapshot-btn').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Saving...');

        $.post(haPfAdmin.ajaxUrl, {
            action: 'ha_powerflow_create_snapshot',
            nonce: haPfAdmin.nonce
        }, function (res) {
            if (res.success) {
                showStatus(res.data, false);
                // Reload after a delay to show the new snapshot in the dropdown
                setTimeout(function () { location.reload(); }, 1500);
            } else {
                showStatus(res.data, true);
            }
        }).always(function () { $btn.prop('disabled', false).text('Take Snapshot Now'); });
    });

    $('#ha-pf-download-btn').on('click', function () {
        var file = $('#ha-pf-snapshot-select').val();
        if (!file) return;
        window.location.href = haPfAdmin.ajaxUrl + '?action=ha_powerflow_download_snapshot&nonce=' + haPfAdmin.nonce + '&filename=' + file;
    });

    $('#ha-pf-upload-btn').on('click', function () {
        var fileInput = document.getElementById('ha-pf-upload-file');
        if (!fileInput.files.length) {
            alert('Please select a .yaml file to upload.');
            return;
        }

        var formData = new FormData();
        formData.append('action', 'ha_powerflow_upload_snapshot');
        formData.append('nonce', haPfAdmin.nonce);
        formData.append('snapshot', fileInput.files[0]);

        var $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url: haPfAdmin.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (res) {
                if (res.success) {
                    showStatus(res.data, false);
                    setTimeout(function () { location.reload(); }, 1500);
                } else {
                    showStatus(res.data, true);
                }
            },
            error: function () { showStatus('Upload failed.', true); },
            complete: function () { $btn.prop('disabled', false); }
        });
    });

    // ── Custom Entities Table ──────────────────────────────────────────────
    $('#ha-pf-add-entity').on('click', function() {
        var $tbody = $('#ha-pf-custom-entities-table tbody');
        var index  = $tbody.find('tr').length;
        
        var row = `
            <tr data-index="${index}">
                <td><input type="text" name="ha_powerflow_options[custom_entities][${index}][label]" class="widefat" placeholder="e.g. Temp" /></td>
                <td><input type="text" name="ha_powerflow_options[custom_entities][${index}][entity]" class="widefat" placeholder="sensor.xyz" /></td>
                <td>
                    <div class="ha-pf-xy-group" style="display:flex;align-items:center;gap:5px;">
                        <input type="number" name="ha_powerflow_options[custom_entities][${index}][x]" value="0" class="small-text" min="0" max="1000" />
                        <input type="number" name="ha_powerflow_options[custom_entities][${index}][y]" value="0" class="small-text" min="0" max="700" />
                        <button type="button" class="ha-pf-coord-picker-btn" title="Pick position from image">🎯</button>
                    </div>
                </td>
                <td>
                    <label class="ha-pf-toggle-label ha-pf-toggle-sm">
                        <input type="checkbox" name="ha_powerflow_options[custom_entities][${index}][visible]" value="1" checked />
                        <span class="ha-pf-slider"></span>
                    </label>
                </td>
                <td><button type="button" class="button ha-pf-remove-entity">×</button></td>
            </tr>
        `;
        $tbody.append(row);
    });

    $(document).on('click', '.ha-pf-remove-entity', function() {
        $(this).closest('tr').remove();
        // Shift indexes to prevent gaps
        $('#ha-pf-custom-entities-table tbody tr').each(function(idx) {
            $(this).attr('data-index', idx);
            $(this).find('input').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    $(this).attr('name', name.replace(/\[custom_entities\]\[\d+\]/, '[custom_entities][' + idx + ']'));
                }
            });
        });
    });

    // ── Visual Coordinate Selector ─────────────────────────────────────────
    var $overlay = $('<div class="ha-pf-picker-overlay">' +
        '<div class="ha-pf-picker-container">' +
            '<img src="" class="ha-pf-picker-img" />' +
            '<div class="ha-pf-picker-crosshair"></div>' +
            '<div class="ha-pf-picker-hint">Click to pick coordinates | ESC to Cancel</div>' +
        '</div>' +
    '</div>').appendTo('body');

    var activeInputX, activeInputY;

    $(document).on('click', '.ha-pf-coord-picker-btn', function(e) {
        e.preventDefault();
        var $parent = $(this).closest('.ha-pf-xy-group');
        activeInputX = $parent.find('input[name*="_x"]');
        activeInputY = $parent.find('input[name*="_y"]');
        
        // Fallback or custom entity search
        if (!activeInputX.length) {
            var $row = $(this).closest('tr');
            activeInputX = $row.find('input[name*="][x]"]');
            activeInputY = $row.find('input[name*="][y]"]');
        }

        var bgUrl = $('#bg_image').val() || haPfAdmin.defaultBg || '';
        $overlay.find('.ha-pf-picker-img').attr('src', bgUrl);
        $overlay.addClass('active');
    });

    $overlay.on('click', '.ha-pf-picker-img', function(e) {
        var offset = $(this).offset();
        var width  = $(this).width();
        var height = $(this).height();
        
        var x = Math.round(((e.pageX - offset.left) / width) * 1000);
        var y = Math.round(((e.pageY - offset.top) / height) * 700);

        activeInputX.val(x);
        activeInputY.val(y).trigger('change');

        $overlay.removeClass('active');
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') $overlay.removeClass('active');
    });

    $overlay.on('click', function(e) {
        if ($(e.target).hasClass('ha-pf-picker-overlay')) {
        }
    });

    // ── Entity Auto-Discovery ──────────────────────────────────────────────
    $('#ha-pf-discover-btn').on('click', function() {
        var $btn = $(this);
        var $res = $('#ha-pf-discover-results');
        
        $btn.prop('disabled', true).text('Scanning...');
        $res.empty().hide();

        $.post(haPfAdmin.ajaxUrl, {
            action: 'ha_powerflow_discover_entities',
            nonce: haPfAdmin.nonce
        }, function(res) {
            if (res.success && res.data.length) {
                $res.show();
                res.data.forEach(function(ent) {
                    var $item = $(`<div style="padding:5px; border-bottom:1px solid #eee; border-radius:0; border-left:0; border-right:0; display:flex; justify-content:space-between; align-items:center;">
                        <span><strong>${ent.entity_id}</strong><br/><small>${ent.attributes.friendly_name || ''}</small></span>
                        <button type="button" class="button button-small ha-pf-use-ent" data-id="${ent.entity_id}">Use</button>
                    </div>`);
                    $res.append($item);
                });
            } else {
                alert('No relevant sensors found or error connecting to HA.');
            }
        }).always(function() {
            $btn.prop('disabled', false).text('Scan for Entities');
        });
    });

    $(document).on('click', '.ha-pf-use-ent', function() {
        var id = $(this).data('id');
        alert('Entity ID ' + id + ' copied! Please paste it into the appropriate field.');
    });

    // ── Diagnostics Dashboard ─────────────────────────────────────────────
    function refreshDiags() {
        var $log = $('#ha-pf-diag-log');
        var $status = $('#ha-pf-diag-status');
        var $time = $('#ha-pf-diag-time');

        $log.append('<div>[' + new Date().toLocaleTimeString() + '] Pinging HA...</div>');

        $.post(haPfAdmin.ajaxUrl, {
            action: 'ha_pf_test_connection',
            nonce: haPfAdmin.nonce,
            ha_url: $('#ha_url').val(),
            ha_token: $('#ha_token').val()
        }, function(res) {
            if (res.success) {
                $status.text('Connected').css('color', '#10b981');
                $log.append('<div style="color:#10b981;">✓ ' + res.data + '</div>');
            } else {
                $status.text('Error').css('color', '#ef4444');
                $log.append('<div style="color:#ef4444;">✗ ' + res.data + '</div>');
            }
            $time.text(new Date().toLocaleString());
            $log.scrollTop($log[0].scrollHeight);
        });
    }

    $('#ha-pf-diag-refresh').on('click', refreshDiags);
    if ($('#ha-pf-diag-panel').length) setInterval(refreshDiags, 30000);

    // ── Live Preview Logic ───────────────────────────────────────────────
    function initLivePreview() {
        var $preview = $('#ha-pf-admin-preview-container .ha-powerflow-widget');
        if (!$preview.length) return;

        // Populate preview with some dummy data for realism
        $preview.find('#ha-pf-flow-main').text('EXPORTING');
        $preview.find('#ha-pf-flow-sub').text('2.4 kW');
        $preview.find('#ha-pf-grid-power').text('2.4 kW');
        $preview.find('#ha-pf-load-power').text('1.2 kW');
        $preview.find('#ha-pf-pv-power').text('3.6 kW');
        $preview.find('#ha-pf-battery-soc').text('85%');
        $preview.find('#ha-pf-ev-soc').text('60%');
        $preview.find('#ha-pf-heatpump-power').text('1.8 kW');
        $preview.find('#ha-pf-heatpump-efficiency').text('3.2 COP');
        $preview.find('#ha-pf-status').html('✓ Connected (Preview)');
        
        // Dummy Weather
        $preview.find('#ha-pf-weather').text('SUNNY');
        $preview.find('#ha-pf-weather-icon').html('<circle cx="0" cy="0" r="10" /><g class="ha-pf-rotate"><line x1="0" y1="-14" x2="0" y2="-18" /><line x1="0" y1="14" x2="0" y2="18" /><line x1="-14" y1="0" x2="-18" y2="0" /><line x1="14" y1="0" x2="18" y2="0" /><line x1="-10" y1="-10" x2="-13" y2="-13" /><line x1="10" y1="10" x2="13" y2="13" /><line x1="-10" y1="10" x2="-13" y2="13" /><line x1="10" y1="-10" x2="13" y2="-13" /></g>');

        function updatePreview() {
            var options = {};
            // Gather all options from the form
            $('#ha-pf-settings-form').serializeArray().forEach(function(item) {
                var name = item.name.replace('ha_powerflow_options[', '').replace(']', '');
                options[name] = item.value;
            });

            // Sync Background
            var bgUrl = $('#bg_image').val() || haPfAdmin.defaultBg || '';
            $preview.css('background-image', 'url(' + bgUrl + ')');
            updateImageAnalysis(bgUrl);

            // Base Colors
            var line_color = options['line_color'] || '#4a90d9';
            var cssVars = {
                '--ha-pf-title-color': options['title_color'] || '#8899bb',
                '--ha-pf-power-color': options['power_color'] || '#f0a500',
                '--ha-pf-energy-color': options['energy_color'] || '#6677aa',
                '--ha-pf-grid-color': options['grid_color'] || line_color,
                '--ha-pf-load-color': options['load_color'] || line_color
            };

            // Dynamic Module Colors & Visibility
            var anyModule = false;
            if (haPowerflow.modules) {
                Object.keys(haPowerflow.modules).forEach(function(key) {
                    var m = haPowerflow.modules[key];
                    var prefix = m.prefix;
                    var enabled = $('#ha-pf-toggle-' + key).is(':checked');
                    if (enabled) anyModule = true;

                    // Color
                    cssVars['--ha-pf-' + prefix + '-color'] = options[prefix + '_color'] || line_color;

                    // Visibility
                    $preview.find('#ha-pf-' + prefix + '-line, #ha-pf-' + prefix + '-path, #ha-pf-' + prefix + '-group').toggle(enabled);
                    
                    // Positions
                    var x = options[prefix + '_label_x'];
                    var y = options[prefix + '_label_y'];
                    if (x !== undefined && y !== undefined) {
                        var $group = $preview.find('#ha-pf-' + prefix + '-group');
                        $group.find('text').each(function() {
                            var $el = $(this);
                            $el.attr('x', x);
                            $el.find('tspan').attr('x', x);

                            var selector = $el.attr('id') || '';
                            var baseOffset = 0;
                            if (selector.includes('power')) baseOffset = 24;
                            else if (selector.includes('soc') || selector.includes('energy') || selector.includes('efficiency')) baseOffset = 44;
                            
                            $el.attr('y', parseInt(y) + baseOffset);
                        });
                    }

                    // Path data
                    var d = options[prefix + '_line'];
                    if (d) {
                        $preview.find('#ha-pf-' + prefix + '-line').attr('d', d);
                        $preview.find('#ha-pf-' + prefix + '-path').attr('d', d);
                    }

                    // Max Capacity
                    var mc = options[prefix + '_max_capacity'];
                    if (mc) {
                        $preview.find('#ha-pf-' + prefix + '-path').attr('data-max-capacity', mc);
                    }
                });
            }

            // Weather
            var weatherEnabled = $('#ha-pf-toggle-weather').is(':checked');
            $preview.find('#ha-pf-weather, #ha-pf-weather-icon-group').toggle(weatherEnabled);
            if (weatherEnabled) {
                $preview.find('#ha-pf-weather').css('font-size', (options['weather_font_size'] || 13) + 'px');
                var wx = options['weather_x'];
                var wy = options['weather_y'];
                if (wx !== undefined && wy !== undefined) {
                    $preview.find('#ha-pf-weather').attr('x', wx).attr('y', dy = parseInt(wy) + 5);
                    $preview.find('#ha-pf-weather-icon-group').attr('transform', `translate(${wx}, ${parseInt(wy) - 30})`);
                }
            }

            $preview.css(cssVars);

            // Sync Opacity
            var opacity = options['line_opacity'] || 1.0;
            $preview.find('path[id$="-line"]').attr('opacity', opacity);

            $preview.find('#ha-pf-load-line, #ha-pf-load-path').toggle(anyModule);

            // Sync Core Positions (Grid, Load, Status)
            var corePos = {
                'grid_label': ['#ha-pf-grid-title', '#ha-pf-grid-power', '#ha-pf-grid-energy', '#ha-pf-grid-energy-out', '#ha-pf-grid-price-in', '#ha-pf-grid-price-out'],
                'load_label': ['#ha-pf-home-label', '#ha-pf-load-power', '#ha-pf-load-energy'],
                'status': ['#ha-pf-flow-label']
            };

            for (var key in corePos) {
                var x = options[key + '_x'];
                var y = options[key + '_y'];
                if (x !== undefined && y !== undefined) {
                    corePos[key].forEach(function(selector) {
                        var $el = $preview.find(selector);
                        $el.attr('x', x);
                        $el.find('tspan').attr('x', x);
                        
                        var baseOffset = 0;
                        if (selector.includes('power')) baseOffset = 24;
                        else if (selector.includes('energy') || selector.includes('soc')) baseOffset = 44;
                        else if (selector.includes('energy-out')) baseOffset = 58;
                        else if (selector.includes('price-in')) baseOffset = 72;
                        else if (selector.includes('price-out')) baseOffset = 86;
                        else if (selector.includes('flow-sub')) baseOffset = 18;

                        $el.attr('y', parseInt(y) + baseOffset);
                    });
                }
            }

            // Core Paths
            if (options['grid_line']) {
                $preview.find('#ha-pf-grid-line, #ha-pf-grid-path').attr('d', options['grid_line']);
            }
            if (options['grid_max_capacity']) {
                $preview.find('#ha-pf-grid-path').attr('data-max-capacity', options['grid_max_capacity']);
            }
            if (options['load_line']) {
                $preview.find('#ha-pf-load-line, #ha-pf-load-path').attr('d', options['load_line']);
            }
            if (options['house_max_capacity']) {
                $preview.find('#ha-pf-load-path').attr('data-max-capacity', options['house_max_capacity']);
            }

            // Sync Custom Entities
            $('#ha-pf-custom-entities-table tbody tr').each(function() {
                var idx = $(this).data('index');
                var label = $(this).find('input[name*="[label]"]').val();
                var cx = $(this).find('input[name*="[x]"]').val();
                var cy = $(this).find('input[name*="[y]"]').val();
                var visible = $(this).find('input[name*="[visible]"]').is(':checked');

                var $cent = $preview.find('#ha-pf-custom-' + idx);
                if ($cent.length) {
                    $cent.toggle(visible);
                    $cent.attr('transform', `translate(${cx}, ${cy})`);
                    $cent.find('text:first-child').text(label.toUpperCase());
                }
            });
        }

        // Hook into form inputs
        $('#ha-pf-settings-form').on('input change', 'input, select, textarea', updatePreview);

        // Hook into WP Color Picker
        $('.ha-pf-color-picker').each(function() {
            var $cp = $(this);
            var iris = $cp.data('wpWpColorPicker');
            if (iris) {
                var originalChange = iris.options.change;
                iris.options.change = function(event, ui) {
                    if (originalChange) originalChange(event, ui);
                    setTimeout(updatePreview, 10);
                };
                var originalClear = iris.options.clear;
                iris.options.clear = function(event) {
                    if (originalClear) originalClear(event);
                    setTimeout(updatePreview, 10);
                };
            }
        });

        // Initialize
        updatePreview();
        pfUpdatePreview = updatePreview;
    }

    // ── Preview Toggle ───────────────────────────────────────────────────
    function initPreviewToggle() {
        var $toggle = $('#ha-pf-toggle-preview');
        var $container = $('.ha-pf-admin-container');
        var storageKey = 'ha_pf_preview_enabled';

        function applyPreviewMode(enabled) {
            if (enabled) {
                $container.removeClass('ha-pf-preview-disabled');
                $toggle.prop('checked', true);
            } else {
                $container.addClass('ha-pf-preview-disabled');
                $toggle.prop('checked', false);
            }
            localStorage.setItem(storageKey, enabled ? '1' : '0');
        }

        // Load preference - default to OFF (0)
        var saved = localStorage.getItem(storageKey);
        if (saved === '1') {
            applyPreviewMode(true);
        } else {
            applyPreviewMode(false);
        }

        $toggle.on('change', function() {
            applyPreviewMode($(this).is(':checked'));
        });
    }

    // ── Preview Size Selector ────────────────────────────────────────────
    function initPreviewSizeSelector() {
        var $select = $('#ha-pf-preview-size-select');
        var $container = $('.ha-pf-admin-container');
        var storageKey = 'ha_pf_preview_size';

        function applySize(size) {
            // Update the CSS variable on the root document for maximum scope
            document.documentElement.style.setProperty('--hapf-preview-width', size);
            $select.val(size);
            localStorage.setItem(storageKey, size);
            
            // Trigger a preview sync if available
            if (typeof pfUpdatePreview === 'function') {
                pfUpdatePreview();
            }
        }

        // Load saved size or default to 550px
        var saved = localStorage.getItem(storageKey) || '550px';
        applySize(saved);

        $select.on('change', function() {
            applySize($(this).val());
        });
    }

    // ── Image Analysis ──────────────────────────────────────────────────
    function updateImageAnalysis(url) {
        var $analysis = $('#ha-pf-image-analysis');
        var $badge = $('#ha-pf-image-size-badge');
        var $tips = $('#ha-pf-image-tips');

        if (!url) {
            $analysis.hide();
            return;
        }

        $analysis.show();

        // Use a HEAD request to efficiently get file size
        $.ajax({
            type: "HEAD",
            url: url,
            success: function(data, textStatus, jqXHR) {
                var size = jqXHR.getResponseHeader('Content-Length');
                if (!size) {
                    $badge.text('Unknown');
                    $tips.html('Could not determine file size.');
                    return;
                }

                var kb = Math.round(size / 1024);
                $badge.text(kb + ' KB');

                var tip = '';
                var color = '#475569'; // Default slate

                if (kb > 800) {
                    tip = '⚠️ <strong>Very Large:</strong> This image will slow down your site, especially on mobile. <br/><em>Optimal: Aim for under 300KB using WebP format.</em>';
                    color = '#e11d48'; // Rose/Red
                } else if (kb >= 40 && kb <= 400) {
                    tip = '✅ <strong>Ideal:</strong> This image is perfectly balanced for quality and speed.';
                    color = '#16a34a'; // Green
                } else if (kb < 15) {
                    tip = '⚠️ <strong>Low Quality:</strong> This may look blurry on high-resolution screens. <br/><em>Optimal: Use an image with at least 1000px width.</em>';
                    color = '#ca8a04'; // Yellow/Orange
                } else {
                    tip = 'ℹ️ <strong>Acceptable:</strong> Good size, but check that it looks sharp on all devices.';
                }

                $tips.html(tip).css('color', color);
                $badge.css('background', color).css('color', '#fff');
            },
            error: function() {
                $badge.text('Error');
                $tips.html('Could not reach image file for analysis.');
            }
        });
    }

    // ── Tab Switching ──────────────────────────────────────────────────────
    function initTabs() {
        var $tabs = $('.ha-pf-tab-btn');
        var $contents = $('.ha-pf-tab-content');
        var storageKey = 'ha_pf_active_tab';

        function switchTab(tabId) {
            $tabs.removeClass('active');
            $contents.removeClass('active');

            $('.ha-pf-tab-btn[data-tab="' + tabId + '"]').addClass('active');
            $('#ha-pf-tab-' + tabId).addClass('active');

            localStorage.setItem(storageKey, tabId);
        }

        $tabs.on('click', function() {
            var tabId = $(this).data('tab');
            switchTab(tabId);
        });

        // Load last active tab or default to 'connection'
        var activeTab = localStorage.getItem(storageKey) || 'connection';
        // Ensure the tab actually exists (in case of renames)
        if ($('#ha-pf-tab-' + activeTab).length === 0) activeTab = 'connection';
        switchTab(activeTab);
    }

    // ── Theme Presets ─────────────────────────────────────────────────────
    function initThemePresets() {
        var $presetSelect = $('#ha-pf-theme-preset');
        var presets = {
            cyberpunk: {
                line_color: '#00f3ff',
                grid_color: '#ff00ff',
                load_color: '#00f3ff',
                pv_color: '#f0ff00',
                battery_color: '#00ff9f',
                ev_color: '#bd00ff',
                heatpump_color: '#ff003c',
                title_color: '#00f3ff',
                power_color: '#f0ff00',
                energy_color: '#ff00ff',
                line_opacity: '0.8'
            },
            high_contrast: {
                line_color: '#ffffff',
                grid_color: '#ff3e3e',
                load_color: '#3e9cff',
                pv_color: '#ffde34',
                battery_color: '#00e676',
                ev_color: '#d500f9',
                heatpump_color: '#ff1744',
                title_color: '#ffffff',
                power_color: '#ffffff',
                energy_color: '#ffffff',
                line_opacity: '1.0'
            },
            minimalist: {
                line_color: '#94a3b8',
                grid_color: '#64748b',
                load_color: '#94a3b8',
                pv_color: '#cbd5e1',
                battery_color: '#94a3b8',
                ev_color: '#94a3b8',
                heatpump_color: '#e2e8f0',
                title_color: '#475569',
                power_color: '#334155',
                energy_color: '#64748b',
                line_opacity: '0.4'
            },
            solaredge: {
                line_color: '#deff00',
                grid_color: '#deff00',
                load_color: '#ffffff',
                pv_color: '#deff00',
                battery_color: '#10b981',
                ev_color: '#3b82f6',
                heatpump_color: '#ef4444',
                title_color: '#ffffff',
                power_color: '#deff00',
                energy_color: '#ffffff',
                line_opacity: '0.9'
            },
            tesla: {
                line_color: '#e2e8f0',
                grid_color: '#cc0000',
                load_color: '#e2e8f0',
                pv_color: '#ffffff',
                battery_color: '#e2e8f0',
                ev_color: '#cc0000',
                heatpump_color: '#475569',
                title_color: '#f8fafc',
                power_color: '#cc0000',
                energy_color: '#94a3b8',
                line_opacity: '0.8'
            },
            midnight: {
                line_color: '#6366f1',
                grid_color: '#818cf8',
                load_color: '#6366f1',
                pv_color: '#f59e0b',
                battery_color: '#10b981',
                ev_color: '#a855f7',
                heatpump_color: '#ec4899',
                title_color: '#e0e7ff',
                power_color: '#fbbf24',
                energy_color: '#c7d2fe',
                line_opacity: '0.7'
            },
            forest: {
                line_color: '#166534',
                grid_color: '#15803d',
                load_color: '#166534',
                pv_color: '#fde047',
                battery_color: '#22c55e',
                ev_color: '#4ade80',
                heatpump_color: '#854d0e',
                title_color: '#f0fdf4',
                power_color: '#facc15',
                energy_color: '#bbf7d0',
                line_opacity: '0.9'
            },
            sunset: {
                line_color: '#991b1b',
                grid_color: '#dc2626',
                load_color: '#991b1b',
                pv_color: '#fcd34d',
                battery_color: '#f97316',
                ev_color: '#fb7185',
                heatpump_color: '#be123c',
                title_color: '#fff1f2',
                power_color: '#fbbf24',
                energy_color: '#fecaca',
                line_opacity: '0.8'
            },
            matrix: {
                line_color: '#00ff41',
                grid_color: '#008f11',
                load_color: '#00ff41',
                pv_color: '#003b00',
                battery_color: '#00ff41',
                ev_color: '#008f11',
                heatpump_color: '#003b00',
                title_color: '#00ff41',
                power_color: '#00ff41',
                energy_color: '#008f11',
                line_opacity: '1.0'
            },
            ocean: {
                line_color: '#075985',
                grid_color: '#0ea5e9',
                load_color: '#075985',
                pv_color: '#f0f9ff',
                battery_color: '#2dd4bf',
                ev_color: '#38bdf8',
                heatpump_color: '#1e40af',
                title_color: '#f0f9ff',
                power_color: '#38bdf8',
                energy_color: '#bae6fd',
                line_opacity: '0.8'
            }
        };

        $presetSelect.on('change', function() {
            var id = $(this).val();
            if (id === 'custom') return;

            var theme = presets[id];
            if (!theme) return;

            // Apply theme to pickers and inputs
            for (var key in theme) {
                var $input = $('[name="ha_powerflow_options[' + key + ']"]');
                if ($input.length) {
                    $input.val(theme[key]).trigger('change');
                    
                    // If it's a color picker, update the iris UI
                    if ($input.hasClass('wp-color-picker')) {
                        $input.wpColorPicker('color', theme[key]);
                    }
                    
                    // Update range value display if exists
                    if ($input.attr('type') === 'range') {
                        $input.next('.ha-pf-range-val').text(theme[key]);
                    }
                }
            }

            // Force a preview update
            if (typeof pfUpdatePreview === 'function') {
                pfUpdatePreview();
            }
        });

        // If any visual setting is changed manually, switch preset to "custom"
        var visualSettings = [
            'line_color', 'grid_color', 'load_color', 'pv_color', 'battery_color', 
            'ev_color', 'heatpump_color', 'title_color', 'power_color', 
            'energy_color', 'line_opacity'
        ];

        visualSettings.forEach(function(key) {
            $('[name="ha_powerflow_options[' + key + ']"]').on('change input', function(e) {
                // If the event was NOT triggered by code (human interaction)
                if (e.originalEvent) {
                    $presetSelect.val('custom');
                }
            });
        });
    }

    function initHealthDashboard() {
        const $status = $('#ha-pf-health-status-value');
        const $latency = $('#ha-pf-health-latency');
        const $rate = $('#ha-pf-health-rate');
        const $lastSeen = $('#ha-pf-health-last-seen');
        const $count = $('#ha-pf-health-count');
        const $refreshBtn = $('#ha-pf-refresh-health');

        if (!$status.length) return;

        function updateHealth() {
            $refreshBtn.prop('disabled', true).text('Updating...');
            $.post(haPfAdmin.ajaxUrl, {
                action: 'ha_pf_get_health',
                nonce: haPfAdmin.nonce
            }, function(response) {
                if (response.success) {
                    const data = response.data;
                    $status.text(data.status).removeClass('healthy degraded disconnected').addClass(data.status.toLowerCase());
                    $latency.text(data.avg_latency + ' ms');
                    $rate.text(data.success_rate + ' %');
                    
                    if (data.last_success > 0) {
                        const date = new Date(data.last_success * 1000);
                        $lastSeen.text(date.toLocaleTimeString());
                    } else {
                        $lastSeen.text('Never');
                    }
                    
                    $count.text(data.count + ' checks tracked');
                }
                $refreshBtn.prop('disabled', false).text('Refresh Now');
            });
        }

        // Auto-refresh when maintenance tab is clicked
        $('.ha-pf-tab-btn[data-tab="maintenance"]').on('click', function() {
            updateHealth();
        });

        $refreshBtn.on('click', function() {
            updateHealth();
        });

        // Initialize if already on maintenance tab
        if ($('#ha-pf-tab-maintenance').hasClass('active')) {
            updateHealth();
        }
    }

    initPreviewToggle();
    initLivePreview();
    initPreviewSizeSelector();
    initTabs();
    initThemePresets();
    initHealthDashboard();

});
