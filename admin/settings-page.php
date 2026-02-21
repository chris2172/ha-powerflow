<?php
if (!defined('ABSPATH')) exit;

/* SECTION: Admin Menu */
function ha_powerflow_add_admin_menu() {
    add_menu_page(
        'HA Powerflow Settings',
        'HA Powerflow',
        'manage_options',
        'ha-powerflow',
        'ha_powerflow_settings_page',
        'dashicons-chart-area'
    );
}
add_action('admin_menu', 'ha_powerflow_add_admin_menu');

/* SECTION: Settings Page */
function ha_powerflow_settings_page() { ?>

<div class="wrap">
    <h1>HA Powerflow Settings</h1>

    <!-- Instructions Box -->
    <div style="border:1px solid #ccd0d4; background:#fff; padding:15px 20px; border-radius:6px; margin:20px 0;">
        <h2 style="margin-top:0;">Instructions</h2>
        <p>This plugin connects to your Home Assistant instance and displays a live animated powerflow diagram.</p>

        <ul style="margin-left:20px; list-style:disc;">
            <li>Enter your <strong>Home Assistant URL</strong> and <strong>Long‑Lived Access Token</strong> in the Mandatory Settings panel.</li>
            <li>Enable Solar, Battery, or EV using the toggles below.</li>
            <li>Ensure all entity IDs match your Home Assistant setup exactly.</li>
            <li>Your token must have permission to read all selected entities.</li>
            <li>Use the shortcode <code>[ha_powerflow]</code> on any page or post.</li>
            <li>You can upload new PNG images to <code>wp-content/plugins/ha-powerflow/assets</code>.</li>
        </ul>

        <h3 style="margin-top:25px; color:#b32d2e;">NOTE</h3>
        <p><strong>This plugin must connect to Home Assistant over HTTPS.</strong></p>

        <p>Add this to your <code>configuration.yaml</code>:</p>

        <pre style="background:#f6f7f7; padding:12px; border-radius:6px; border:1px solid #ccd0d4;">
http:
    cors_allowed_origins:
        - https://YOUR-WEBSITE-URL.com
        </pre>

        <p>Replace <code>YOUR-WEBSITE-URL.com</code> with your actual WordPress URL.</p>
    </div>

    <!-- Permission to delete uploaded images when plugin is uninstalled -->
    <h2 style="font-weight:700; color:#b32d2e; margin-bottom:8px;">
        Delete uploaded images on uninstall?
    </h2>

    <p class="ha-switch-row">
        <label class="ha-switch">
            <input type="hidden" name="ha_powerflow_delete_uploads" value="0">
            <input type="checkbox"
                name="ha_powerflow_delete_uploads"
                value="1"
                <?php checked(get_option('ha_powerflow_delete_uploads'), '1'); ?>>
            <span class="ha-slider"></span>
        </label>
    </p>

<!-- Separator line -->
<div style="border-bottom:1px solid #ccd0d4; margin:20px 0;"></div>




    
    <form method="post" action="options.php">
        <?php settings_fields('ha_powerflow_settings_group'); ?>

        <!-- SECTION: Feature Toggles -->
        <h2>Feature Toggles</h2>

        <p class="ha-switch-row">
            <span><strong>Enable Solar</strong></span>
            <label class="ha-switch">
                <input type="hidden" name="ha_powerflow_enable_solar" value="0">
                <input type="checkbox"
                       id="ha_powerflow_enable_solar"
                       class="ha-toggle"
                       name="ha_powerflow_enable_solar"
                       value="1"
                       <?php checked(get_option('ha_powerflow_enable_solar'), '1'); ?>>
                <span class="ha-slider"></span>
            </label>
        </p>

        <p class="ha-switch-row">
            <span><strong>Enable Battery</strong></span>
            <label class="ha-switch">
                <input type="hidden" name="ha_powerflow_enable_battery" value="0">
                <input type="checkbox"
                       id="ha_powerflow_enable_battery"
                       class="ha-toggle"
                       name="ha_powerflow_enable_battery"
                       value="1"
                       <?php checked(get_option('ha_powerflow_enable_battery'), '1'); ?>>
                <span class="ha-slider"></span>
            </label>
        </p>

        <p class="ha-switch-row">
            <span><strong>Enable EV</strong></span>
            <label class="ha-switch">
                <input type="hidden" name="ha_powerflow_enable_ev" value="0">
                <input type="checkbox"
                       id="ha_powerflow_enable_ev"
                       class="ha-toggle"
                       name="ha_powerflow_enable_ev"
                       value="1"
                       <?php checked(get_option('ha_powerflow_enable_ev'), '1'); ?>>
                <span class="ha-slider"></span>
            </label>
        </p>

        <!-- SECTION: Two Column Layout -->
        <div class="ha-settings-grid">

            <!-- LEFT COLUMN -->
            <div class="ha-left">

                <div class="ha-panel open" id="mandatory-panel">
                    <div class="ha-panel-header">
                        <span>Mandatory Settings</span>
                        <span class="ha-arrow">▼</span>
                    </div>

                    <div class="ha-panel-body" id="mandatory-section">

                        <?php
                        $assets_path = plugin_dir_path(dirname(__FILE__)) . 'assets/';
                        $assets_url  = plugin_dir_url(dirname(__FILE__)) . 'assets/';
                        $png_files   = glob($assets_path . '*.png');

                        $current_image = get_option(
                            'ha_powerflow_image_url',
                            $assets_url . 'ha-powerflow.png'
                        );
                        ?>

                        <!-- Image Selector -->
                        <h2>Upload Custom Image</h2>

                        <p>
                            <input type="text"
                                id="ha_powerflow_image_url"
                                name="ha_powerflow_image_url"
                                value="<?php echo esc_attr(get_option('ha_powerflow_image_url')); ?>"
                                style="width: 70%; max-width: 500px;" />

                            <button type="button" class="button" id="ha_powerflow_upload_button">
                                Upload / Select Image
                            </button>
                        </p>

                        <?php if ($img = get_option('ha_powerflow_image_url')) : ?>
                            <p><strong>Preview:</strong></p>
                            <img id="ha_powerflow_image_preview"
                                src="<?php echo esc_url($img); ?>"
                                style="max-width:200px; border:1px solid #ccc;">
                        <?php else : ?>
                            <img id="ha_powerflow_image_preview"
                                src=""
                                style="max-width:200px; border:1px solid #ccc; display:none;">
                        <?php endif; ?>


                        <!-- Mandatory Fields -->
                        <?php
                        $mandatory = [
                            'ha_url'          => 'HA URL',
                            'ha_token'        => 'HA Token',
                            'grid_power'      => 'Grid Power Entity',
                            'grid_energy_in'  => 'Grid Energy In Entity',
                            'grid_energy_out' => 'Grid Energy Out Entity',
                            'load_power'      => 'Load Power Entity',
                            'load_energy'     => 'Load Energy Entity'
                        ];

                        foreach ($mandatory as $key => $label):
                            $value = get_option('ha_powerflow_' . $key);
                        ?>
                            <table class="form-table">

                                <tr>
                                    <th scope="row"><?php echo esc_html($label); ?></th>
                                    <td>
                                        <!-- Entity ID -->
                                        <input type="text"
                                            name="ha_powerflow_<?php echo esc_attr($key); ?>"
                                            value="<?php echo esc_attr(get_option('ha_powerflow_' . $key)); ?>"
                                            placeholder="Entity ID"
                                            style="width:200px;">

                                        <!-- Rotation -->
                                        <input type="number"
                                            name="ha_powerflow_<?php echo esc_attr($key); ?>_rot"
                                            value="<?php echo esc_attr(get_option('ha_powerflow_' . $key . '_rot')); ?>"
                                            placeholder="Rot"
                                            style="width:80px; margin-left:10px;">

                                        <!-- X Position -->
                                        <input type="number"
                                            name="ha_powerflow_<?php echo esc_attr($key); ?>_x_pos"
                                            value="<?php echo esc_attr(get_option('ha_powerflow_' . $key . '_x_pos')); ?>"
                                            placeholder="X"
                                            style="width:80px; margin-left:10px;">

                                        <!-- Y Position -->
                                        <input type="number"
                                            name="ha_powerflow_<?php echo esc_attr($key); ?>_y_pos"
                                            value="<?php echo esc_attr(get_option('ha_powerflow_' . $key . '_y_pos')); ?>"
                                            placeholder="Y"
                                            style="width:80px; margin-left:10px;">
                                    </td>
                                </tr>

                        </table>
                        <?php endforeach; ?>

                    </div>
                </div>

            </div> <!-- END LEFT COLUMN -->

            <!-- RIGHT COLUMN -->
            <div class="ha-right">

                <!-- Solar Panel -->
                <div class="ha-panel" id="solar-panel">
                    <div class="ha-panel-header">
                        <span>Solar Settings</span>
                        <span class="ha-arrow">▼</span>
                    </div>
                    <div class="ha-panel-body" id="solar-section">
                        <?php
                        $solar = [
                            'pv_power'  => 'PV Power Entity',
                            'pv_energy' => 'PV Energy Entity'
                        ];

                        foreach ($solar as $key => $label):
                            $value = get_option('ha_powerflow_' . $key);
                        ?>
                            <table class="form-table">
                            <?php foreach ($solar as $key => $label): ?>
                                <tr>
                                    <th scope="row"><?php echo esc_html($label); ?></th>
                                    <td>

                                        <!-- Entity ID -->
                                        <input type="text"
                                            name="ha_powerflow_<?php echo esc_attr($key); ?>"
                                            value="<?php echo esc_attr(get_option('ha_powerflow_' . $key)); ?>"
                                            placeholder="Entity ID"
                                            style="width:200px;">

                                        <!-- Rotation -->
                                        <input type="number"
                                            name="ha_powerflow_<?php echo esc_attr($key); ?>_rot"
                                            value="<?php echo esc_attr(get_option('ha_powerflow_' . $key . '_rot')); ?>"
                                            placeholder="Rot"
                                            style="width:80px; margin-left:10px;">

                                        <!-- X Position -->
                                        <input type="number"
                                            name="ha_powerflow_<?php echo esc_attr($key); ?>_x_pos"
                                            value="<?php echo esc_attr(get_option('ha_powerflow_' . $key . '_x_pos')); ?>"
                                            placeholder="X"
                                            style="width:80px; margin-left:10px;">

                                        <!-- Y Position -->
                                        <input type="number"
                                            name="ha_powerflow_<?php echo esc_attr($key); ?>_y_pos"
                                            value="<?php echo esc_attr(get_option('ha_powerflow_' . $key . '_y_pos')); ?>"
                                            placeholder="Y"
                                            style="width:80px; margin-left:10px;">

                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </table>

                        <?php endforeach; ?>
                    </div>
                </div>

                    <!-- Battery Panel -->
                    <div class="ha-panel" id="battery-panel">
                        <div class="ha-panel-header">
                            <span>Battery Settings</span>
                            <span class="ha-arrow">▼</span>
                        </div>

                        <div class="ha-panel-body" id="battery-section">

                            <table class="form-table">
                            <?php
                            $battery = [
                                'battery_power'      => 'Battery Power Entity',
                                'battery_energy_in'  => 'Battery Energy In Entity',
                                'battery_energy_out' => 'Battery Energy Out Entity',
                                'battery_soc'        => 'Battery SOC Entity'
                            ];

                            foreach ($battery as $key => $label): ?>
                                <tr>
                                    <th scope="row"><?php echo esc_html($label); ?></th>
                                    <td>

                                        <!-- Entity ID -->
                                        <input type="text"
                                            name="ha_powerflow_<?php echo esc_attr($key); ?>"
                                            value="<?php echo esc_attr(get_option('ha_powerflow_' . $key)); ?>"
                                            placeholder="Entity ID"
                                            style="width:200px;">

                                        <!-- Rotation -->
                                        <input type="number"
                                            name="ha_powerflow_<?php echo esc_attr($key); ?>_rot"
                                            value="<?php echo esc_attr(get_option('ha_powerflow_' . $key . '_rot')); ?>"
                                            placeholder="Rot"
                                            style="width:80px; margin-left:10px;">

                                        <!-- X Position -->
                                        <input type="number"
                                            name="ha_powerflow_<?php echo esc_attr($key); ?>_x_pos"
                                            value="<?php echo esc_attr(get_option('ha_powerflow_' . $key . '_x_pos')); ?>"
                                            placeholder="X"
                                            style="width:80px; margin-left:10px;">

                                        <!-- Y Position -->
                                        <input type="number"
                                            name="ha_powerflow_<?php echo esc_attr($key); ?>_y_pos"
                                            value="<?php echo esc_attr(get_option('ha_powerflow_' . $key . '_y_pos')); ?>"
                                            placeholder="Y"
                                            style="width:80px; margin-left:10px;">

                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </table>

                        </div>
                    </div>


                <!-- EV Panel -->
                <div class="ha-panel" id="ev-panel">
                    <div class="ha-panel-header">
                        <span>EV Settings</span>
                        <span class="ha-arrow">▼</span>
                    </div>

                    <div class="ha-panel-body" id="ev-section">

                        <table class="form-table">
                        <?php
                        $ev = [
                            'ev_power' => 'EV Power Entity',
                            'ev_soc'   => 'EV SOC Entity'
                        ];

                        foreach ($ev as $key => $label): ?>
                            <tr>
                                <th scope="row"><?php echo esc_html($label); ?></th>
                                <td>

                                    <!-- Entity ID -->
                                    <input type="text"
                                        name="ha_powerflow_<?php echo esc_attr($key); ?>"
                                        value="<?php echo esc_attr(get_option('ha_powerflow_' . $key)); ?>"
                                        placeholder="Entity ID"
                                        style="width:200px;">

                                    <!-- Rotation -->
                                    <input type="number"
                                        name="ha_powerflow_<?php echo esc_attr($key); ?>_rot"
                                        value="<?php echo esc_attr(get_option('ha_powerflow_' . $key . '_rot')); ?>"
                                        placeholder="Rot"
                                        style="width:80px; margin-left:10px;">

                                    <!-- X Position -->
                                    <input type="number"
                                        name="ha_powerflow_<?php echo esc_attr($key); ?>_x_pos"
                                        value="<?php echo esc_attr(get_option('ha_powerflow_' . $key . '_x_pos')); ?>"
                                        placeholder="X"
                                        style="width:80px; margin-left:10px;">

                                    <!-- Y Position -->
                                    <input type="number"
                                        name="ha_powerflow_<?php echo esc_attr($key); ?>_y_pos"
                                        value="<?php echo esc_attr(get_option('ha_powerflow_' . $key . '_y_pos')); ?>"
                                        placeholder="Y"
                                        style="width:80px; margin-left:10px;">

                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </table>

                    </div>
                </div>


            </div> <!-- END RIGHT COLUMN -->

        </div> <!-- END GRID -->

        <?php submit_button(); ?>

    </form>
</div>

<!-- ============================
     TWO-COLUMN + PANEL STYLES
============================= -->
<style>
.ha-switch-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 400px;
    padding: 8px 0;
}

.ha-switch {
    position: relative;
    display: inline-block;
    width: 52px;
    height: 28px;
}

.ha-switch input[type="checkbox"] {
    opacity: 0;
    width: 0;
    height: 0;
}

.ha-slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0;
    right: 0; bottom: 0;
    background-color: #ccc;
    transition: 0.3s;
    border-radius: 34px;
}

.ha-slider:before {
    position: absolute;
    content: "";
    height: 22px;
    width: 22px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

.ha-switch input:checked + .ha-slider {
    background-color: #2271b1;
}

.ha-switch input:checked + .ha-slider:before {
    transform: translateX(24px);
}

.ha-settings-grid {
    display: flex;
    gap: 30px;
    align-items: flex-start;
}

.ha-left, .ha-right {
    flex: 1;
    min-width: 350px;
}

.ha-panel {
    border: 1px solid #ccd0d4;
    border-radius: 6px;
    margin-bottom: 15px;
    background: #fff;
}

.ha-panel-header {
    padding: 12px 15px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f6f7f7;
    border-bottom: 1px solid #e2e4e7;
}

.ha-panel-body {
    padding: 15px;
    display: none;
}

.ha-panel.open .ha-panel-body {
    display: block;
}

.ha-arrow {
    transition: transform 0.2s ease;
}

.ha-panel.open .ha-arrow {
    transform: rotate(180deg);
}
</style>

<!-- ============================
     PANEL + TOGGLE LOGIC
============================= -->
<script>

// <!-- process to upload new image or select existing image  -->
jQuery(document).ready(function($){

    let frame;

    $('#ha_powerflow_upload_button').on('click', function(e){
        e.preventDefault();

        // If the media frame already exists, reopen it.
        if (frame) {
            frame.open();
            return;
        }

        // Create the media frame.
        frame = wp.media({
            title: 'Select or Upload Image',
            button: { text: 'Use this image' },
            multiple: false
        });

        // When an image is selected, run a callback.
        frame.on('select', function(){
            const attachment = frame.state().get('selection').first().toJSON();

            // Send attachment ID to AJAX
            $.post(ajaxurl, {
                action: 'ha_powerflow_copy_image',
                attachment_id: attachment.id
            }, function(response){
            if (response.success) {
                const newUrl = response.data.url;

                // Update the text field
                $('#ha_powerflow_image_url').val(newUrl);

                // Update the preview image live
                $('#ha_powerflow_image_preview')
                    .attr('src', newUrl)
                    .show();
            }
            });
        });


        // Finally, open the modal
        frame.open();
    });

});

document.addEventListener("DOMContentLoaded", function () {
    const select = document.querySelector("select[name='ha_powerflow_image_url']");
    const preview = document.querySelector("#ha-image-preview");

    if (select && preview) {
        select.addEventListener("change", function () {
            preview.src = this.value;
        });
    }
});

document.addEventListener('DOMContentLoaded', function () {

    function toggleSections() {
        const solarEnabled   = document.getElementById('ha_powerflow_enable_solar').checked;
        const batteryEnabled = document.getElementById('ha_powerflow_enable_battery').checked;
        const evEnabled      = document.getElementById('ha_powerflow_enable_ev').checked;

        togglePanel('solar-panel', solarEnabled);
        togglePanel('battery-panel', batteryEnabled);
        togglePanel('ev-panel', evEnabled);
    }

    function togglePanel(panelId, enabled) {
        const panel = document.getElementById(panelId);
        if (!panel) return;

        if (enabled) {
            panel.style.display = 'block';
            panel.classList.add('open');
        } else {
            panel.style.display = 'none';
            panel.classList.remove('open');
        }
    }

    document.querySelectorAll('.ha-panel-header').forEach(header => {
        header.addEventListener('click', function () {
            const panel = this.parentElement;

            if (panel.style.display === 'none') return;

            panel.classList.toggle('open');
        });
    });

    toggleSections();

    document.querySelectorAll(".ha-toggle").forEach(cb => {
        cb.addEventListener('change', toggleSections);
    });

});
</script>

<?php } 
