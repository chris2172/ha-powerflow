jQuery(document).ready(function ($) {

    // ── Colour picker ──────────────────────────────────────────────────────
    $('.ha-pf-color-picker').wpColorPicker();

    // ── Module toggles → show/hide right-column sections ──────────────────
    var modules = ['solar', 'battery', 'ev', 'heatpump'];

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

});
