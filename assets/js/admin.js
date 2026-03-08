jQuery(document).ready(function ($) {

    // ── Colour picker ──────────────────────────────────────────────────────
    $('.ha-pf-color-picker').wpColorPicker();

    function syncAllModules() {
        modules.forEach(function (key) {
            var $cb = $('#ha-pf-toggle-' + key);
            var $section = $('#ha-pf-section-' + key);
            $section.toggle($cb.is(':checked'));
        });
        syncLoadLine();
        syncPvRows();
        syncBatteryRows();
        syncEvRows();
        syncHeatpumpRows();
        syncPlaceholder();
    }

    // ── Module toggles → show/hide right-column sections ──────────────────
    var modules = ['solar', 'battery', 'ev', 'heatpump', 'weather'];

    function anyModuleEnabled() {
        var coreModules = ['solar', 'battery', 'ev', 'heatpump'];
        return coreModules.some(function (k) {
            return $('#ha-pf-toggle-' + k).is(':checked');
        });
    }

    function syncLoadLine() {
        if (anyModuleEnabled()) {
            $('#ha-pf-load-line-row').show();
            $('#ha-pf-load-color-row').show();
        } else {
            $('#ha-pf-load-line-row').hide();
            $('#ha-pf-load-color-row').hide();
        }
    }

    function syncPvRows() {
        var solarOn = $('#ha-pf-toggle-solar').is(':checked');
        if (solarOn) {
            $('#ha-pf-pv-line-row').show();
            $('#ha-pf-pv-label-row').show();
            $('#ha-pf-pv-color-row').show();
        } else {
            $('#ha-pf-pv-line-row').hide();
            $('#ha-pf-pv-label-row').hide();
            $('#ha-pf-pv-color-row').hide();
        }
    }

    function syncBatteryRows() {
        var batteryOn = $('#ha-pf-toggle-battery').is(':checked');
        if (batteryOn) {
            $('#ha-pf-battery-line-row').show();
            $('#ha-pf-battery-label-row').show();
            $('#ha-pf-battery-color-row').show();
        } else {
            $('#ha-pf-battery-line-row').hide();
            $('#ha-pf-battery-label-row').hide();
            $('#ha-pf-battery-color-row').hide();
        }
    }

    function syncEvRows() {
        var evOn = $('#ha-pf-toggle-ev').is(':checked');
        if (evOn) {
            $('#ha-pf-ev-line-row').show();
            $('#ha-pf-ev-label-row').show();
            $('#ha-pf-ev-color-row').show();
        } else {
            $('#ha-pf-ev-line-row').hide();
            $('#ha-pf-ev-label-row').hide();
            $('#ha-pf-ev-color-row').hide();
        }
    }

    function syncHeatpumpRows() {
        var hpOn = $('#ha-pf-toggle-heatpump').is(':checked');
        if (hpOn) {
            $('#ha-pf-heatpump-line-row').show();
            $('#ha-pf-heatpump-label-row').show();
            $('#ha-pf-heatpump-color-row').show();
        } else {
            $('#ha-pf-heatpump-line-row').hide();
            $('#ha-pf-heatpump-label-row').hide();
            $('#ha-pf-heatpump-color-row').hide();
        }
    }

    function syncModuleCards() {
        var solarOn   = $('#ha-pf-toggle-solar').is(':checked');
        var batteryOn = $('#ha-pf-toggle-battery').is(':checked');
        var evOn      = $('#ha-pf-toggle-ev').is(':checked');
        var hpOn      = $('#ha-pf-toggle-heatpump').is(':checked');
        var weatherOn = $('#ha-pf-toggle-weather').is(':checked');

        $('.ha-pf-module-card[data-module="solar"]').toggle(solarOn);
        $('.ha-pf-module-card[data-module="battery"]').toggle(batteryOn);
        $('.ha-pf-module-card[data-module="ev"]').toggle(evOn);
        $('.ha-pf-module-card[data-module="heatpump"]').toggle(hpOn);
        $('.ha-pf-module-card[data-module="weather"]').toggle(weatherOn);
    }

    function syncPlaceholder() {
        var anyVisible = modules.some(function (k) {
            return $('#ha-pf-section-' + k).is(':visible');
        });
        $('#ha-pf-no-modules').toggle(!anyVisible);
    }

    modules.forEach(function (key) {
        var $cb      = $('#ha-pf-toggle-' + key);
        var $section = $('#ha-pf-section-' + key);

        $section.toggle($cb.is(':checked'));

        $cb.on('change', function () {
            $section.slideToggle(200);
            setTimeout(syncPlaceholder, 250);
            syncLoadLine();
            if (key === 'solar')    syncPvRows();
            if (key === 'battery')  syncBatteryRows();
            if (key === 'ev')       syncEvRows();
            if (key === 'heatpump') syncHeatpumpRows();
        });
    });

    syncLoadLine();
    syncPvRows();
    syncBatteryRows();
    syncEvRows();
    syncHeatpumpRows();
    syncPlaceholder();

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
        $preview.find('#ha-pf-status').html('✓ Connected (Preview)');
        
        // Dummy Weather
        $preview.find('#ha-pf-weather').text('SUNNY');
        $preview.find('#ha-pf-weather-icon').html('<circle cx="0" cy="0" r="10" /><g class="ha-pf-rotate"><line x1="0" y1="-14" x2="0" y2="-18" /><line x1="0" y1="14" x2="0" y2="18" /><line x1="-14" y1="0" x2="-18" y2="0" /><line x1="14" y1="0" x2="18" y2="0" /><line x1="-10" y1="-10" x2="-13" y2="-13" /><line x1="10" y1="10" x2="13" y2="13" /><line x1="-10" y1="10" x2="-13" y2="13" /><line x1="10" y1="-10" x2="13" y2="-13" /></g>');

        function updatePreview() {
            var options = {};
            // Gather all options from the form
            $('#ha-pf-settings-form').serializeArray().forEach(function(item) {
                var name = item.name.replace('ha_powerflow_options[', '').replace(']', '');
                // Handle nested custom entities if needed, but for now focus on top-level
                options[name] = item.value;
            });

            // Sync Background
            var bgUrl = $('#bg_image').val() || haPfAdmin.defaultBg || '';
            $preview.css('background-image', 'url(' + bgUrl + ')');

            // Sync Colors
            $preview.css({
                '--ha-pf-title-color': options['title_color'] || '#8899bb',
                '--ha-pf-power-color': options['power_color'] || '#f0a500',
                '--ha-pf-energy-color': options['energy_color'] || '#6677aa',
                '--ha-pf-grid-color': options['grid_color'] || options['line_color'] || '#4a90d9',
                '--ha-pf-load-color': options['load_color'] || options['line_color'] || '#4a90d9',
                '--ha-pf-pv-color': options['pv_color'] || options['line_color'] || '#4a90d9',
                '--ha-pf-battery-color': options['battery_color'] || options['line_color'] || '#4a90d9',
                '--ha-pf-ev-color': options['ev_color'] || options['line_color'] || '#4a90d9',
                '--ha-pf-heatpump-color': options['heatpump_color'] || options['line_color'] || '#4a90d9'
            });

            // Sync Opacity
            var opacity = options['line_opacity'] || 1.0;
            $preview.find('path[id$="-line"]').attr('opacity', opacity);

            // Sync Visibility (Toggles)
            var modules = ['solar', 'battery', 'ev', 'heatpump', 'weather'];
            var anyModule = false;
            modules.forEach(function(m) {
                var enabled = $('#ha-pf-toggle-' + m).is(':checked');
                if (m !== 'weather' && enabled) anyModule = true;

                // Simple visibility toggles for the SVG elements
                if (m === 'solar') {
                    $preview.find('#ha-pf-pv-line, #ha-pf-pv-path, #ha-pf-pv-title, #ha-pf-pv-power, #ha-pf-pv-energy').toggle(enabled);
                } else if (m === 'battery') {
                    $preview.find('#ha-pf-battery-line, #ha-pf-battery-path, #ha-pf-battery-title, #ha-pf-battery-power, #ha-pf-battery-soc').toggle(enabled);
                } else if (m === 'ev') {
                    $preview.find('#ha-pf-ev-line, #ha-pf-ev-path, #ha-pf-ev-title, #ha-pf-ev-power, #ha-pf-ev-soc').toggle(enabled);
                } else if (m === 'heatpump') {
                    $preview.find('#ha-pf-heatpump-line, #ha-pf-heatpump-path, #ha-pf-heatpump-title, #ha-pf-heatpump-power, #ha-pf-heatpump-efficiency').toggle(enabled);
                } else if (m === 'weather') {
                    $preview.find('#ha-pf-weather, #ha-pf-weather-icon-group').toggle(enabled);
                    $preview.find('#ha-pf-weather').css('font-size', (options['weather_font_size'] || 13) + 'px');
                }
            });

            $preview.find('#ha-pf-load-line, #ha-pf-load-path').toggle(anyModule);

            // Sync Positions
            var posMap = {
                'grid_label': ['#ha-pf-grid-title', '#ha-pf-grid-power', '#ha-pf-grid-energy', '#ha-pf-grid-energy-out', '#ha-pf-grid-price-in', '#ha-pf-grid-price-out'],
                'load_label': ['#ha-pf-home-label', '#ha-pf-load-power', '#ha-pf-load-energy'],
                'status': ['#ha-pf-flow-label'],
                'pv_label': ['#ha-pf-pv-title', '#ha-pf-pv-power', '#ha-pf-pv-energy'],
                'battery_label': ['#ha-pf-battery-title', '#ha-pf-battery-power', '#ha-pf-battery-soc'],
                'ev_label': ['#ha-pf-ev-title', '#ha-pf-ev-power', '#ha-pf-ev-soc'],
                'heatpump_label': ['#ha-pf-heatpump-title', '#ha-pf-heatpump-power', '#ha-pf-heatpump-efficiency'],
                'weather': ['#ha-pf-weather']
            };

            for (var key in posMap) {
                var x = options[key + '_x'];
                var y = options[key + '_y'];
                if (x !== undefined && y !== undefined) {
                    posMap[key].forEach(function(selector) {
                        var $el = $preview.find(selector);
                        if ($el.is('text')) {
                            $el.attr('x', x);
                            // Some text elements have tspans which also need updating
                            $el.find('tspan').attr('x', x);
                            
                            // Adjust Y for different elements in the same group
                            var baseOffset = 0;
                            if (selector.includes('power')) baseOffset = 24;
                            else if (selector.includes('energy') || selector.includes('soc') || selector.includes('efficiency')) baseOffset = 44;
                            else if (selector.includes('energy-out')) baseOffset = 58;
                            else if (selector.includes('price-in')) baseOffset = 72;
                            else if (selector.includes('price-out')) baseOffset = 86;
                            else if (selector.includes('flow-sub')) baseOffset = 18;

                            $el.attr('y', parseInt(y) + baseOffset);
                        }
                    });
                }
            }
            
            // Weather icon group transform
            if (options['weather_x'] !== undefined && options['weather_y'] !== undefined) {
                $preview.find('#ha-pf-weather-icon-group').attr('transform', `translate(${options['weather_x']}, ${parseInt(options['weather_y']) - 30})`);
            }

            // Sync Path Data
            var paths = ['grid_line', 'load_line', 'pv_line', 'battery_line', 'ev_line', 'heatpump_line'];
            paths.forEach(function(p) {
                var d = options[p];
                if (d) {
                    var id = p.replace('_line', '');
                    if (id === 'grid') id = ''; else id = id + '-';
                    $preview.find('#ha-pf-' + id + 'line').attr('d', d);
                    $preview.find('#ha-pf-' + id + 'path').attr('d', d);
                }
            });

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
                } else if (visible) {
                    // If it doesn't exist in preview yet, we might want to append it, 
                    // but for simplicity we assume the starting count matches.
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

    initPreviewToggle();
    initLivePreview();

});
