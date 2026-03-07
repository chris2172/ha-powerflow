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
        return modules.some(function (k) {
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

});
