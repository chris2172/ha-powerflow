            <div id="ha-pf-tab-appearance" class="ha-pf-tab-content">
                <div class="ha-pf-columns">
                    <div class="ha-pf-col ha-pf-col-left">
                        <div class="ha-pf-card">
                            <h2>🎨 Global Appearance</h2>
                            <table class="form-table form-table-sm" role="presentation">
                                <tr>
                                    <th><label for="theme_preset">Theme Preset</label></th>
                                    <td>
                                        <?php
                                        $preset = $o['theme_preset'] ?? 'custom';
                                        $presets = [
                                            'custom'         => 'Custom (Manual)',
                                            'cyberpunk'      => 'Cyberpunk (Neon Blues/Pinks)',
                                            'high_contrast'  => 'High-Contrast (Bright/Bold)',
                                            'minimalist'     => 'Minimalist (Soft/Subtle)',
                                            'solaredge'      => 'SolarEdge Style (Green/Black)',
                                            'tesla'          => 'Tesla Style (White/Red/Slate)',
                                            'midnight'       => 'Midnight (Indigo/Amber)',
                                            'forest'         => 'Forest (Organic Greens)',
                                            'sunset'         => 'Sunset (Warm Oranges)',
                                            'matrix'         => 'Matrix (Digital Green)',
                                            'ocean'          => 'Ocean (Deep Teals/Blues)',
                                        ];
                                        ?>
                                        <select id="ha-pf-theme-preset" name="ha_powerflow_options[theme_preset]">
                                            <?php foreach ( $presets as $id => $label ) : ?>
                                                <option value="<?php echo $id; ?>" <?php selected( $preset, $id ); ?>>
                                                    <?php echo esc_html( $label ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="description">Choose a starting preset or customize every detail below.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="bg_image">Background Image</label></th>
                                    <td>
                                        <div style="display:flex;gap:8px;align-items:center;">
                                            <input type="url" id="bg_image" name="ha_powerflow_options[bg_image]"
                                                   value="<?php echo esc_attr( $o['bg_image'] ?? '' ); ?>"
                                                   class="widefat" placeholder="URL..."/>
                                            <button type="button" id="ha-pf-media-btn" class="button">Select</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="line_color">Base Line Color</label></th>
                                    <td><input type="text" name="ha_powerflow_options[line_color]" value="<?php echo esc_attr( $o['line_color'] ?? '#4a90d9' ); ?>" class="ha-pf-color-picker" /></td>
                                </tr>
                                <tr>
                                    <th><label for="grid_color">Grid Line Color</label></th>
                                    <td><input type="text" name="ha_powerflow_options[grid_color]" value="<?php echo esc_attr( $o['grid_color'] ?? '' ); ?>" class="ha-pf-color-picker" /></td>
                                </tr>
                                <tr id="ha-pf-load-color-row">
                                    <th><label for="load_color">House Line Color</label></th>
                                    <td><input type="text" name="ha_powerflow_options[load_color]" value="<?php echo esc_attr( $o['load_color'] ?? '' ); ?>" class="ha-pf-color-picker" /></td>
                                </tr>
                                <tr id="ha-pf-pv-color-row">
                                    <th><label for="pv_color">Solar Line Color</label></th>
                                    <td><input type="text" name="ha_powerflow_options[pv_color]" value="<?php echo esc_attr( $o['pv_color'] ?? '' ); ?>" class="ha-pf-color-picker" /></td>
                                </tr>
                                <tr id="ha-pf-battery-color-row">
                                    <th><label for="battery_color">Battery Line Color</label></th>
                                    <td><input type="text" name="ha_powerflow_options[battery_color]" value="<?php echo esc_attr( $o['battery_color'] ?? '' ); ?>" class="ha-pf-color-picker" /></td>
                                </tr>
                                <tr id="ha-pf-ev-color-row">
                                    <th><label for="ev_color">EV Line Color</label></th>
                                    <td><input type="text" name="ha_powerflow_options[ev_color]" value="<?php echo esc_attr( $o['ev_color'] ?? '' ); ?>" class="ha-pf-color-picker" /></td>
                                </tr>
                                <tr id="ha-pf-heatpump-color-row">
                                    <th><label for="heatpump_color">Heat Pump Line Color</label></th>
                                    <td><input type="text" name="ha_powerflow_options[heatpump_color]" value="<?php echo esc_attr( $o['heatpump_color'] ?? '' ); ?>" class="ha-pf-color-picker" /></td>
                                </tr>
                                <tr>
                                    <th><label for="line_opacity">Line Opacity</label></th>
                                    <td>
                                        <input type="range" name="ha_powerflow_options[line_opacity]" min="0" max="1" step="0.05" value="<?php echo esc_attr( $o['line_opacity'] ?? 1.0 ); ?>" oninput="this.nextElementSibling.textContent=this.value"/>
                                        <span class="ha-pf-range-val"><?php echo $o['line_opacity'] ?? 1.0; ?></span>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="ha-pf-card">
                            <h2>⚡ Power Limits (Physics 2.0)</h2>
                            <p class="description">Set the peak power (Watts) for each module. These determine "100%" thresholds for animation speed and line resonance.</p>
                            <table class="form-table form-table-sm">
                                <tr>
                                    <th>Grid Limit</th>
                                    <td><input type="number" name="ha_powerflow_options[grid_max_capacity]" value="<?php echo (int)($o['grid_max_capacity'] ?? 10000); ?>" class="small-text" /> W</td>
                                </tr>
                                <tr>
                                    <th>House Limit</th>
                                    <td><input type="number" name="ha_powerflow_options[house_max_capacity]" value="<?php echo (int)($o['house_max_capacity'] ?? 8000); ?>" class="small-text" /> W</td>
                                </tr>
                                <?php
                                $modules = HA_Powerflow_Modules::get_all();
                                foreach ( $modules as $key => $m ) :
                                    if ( ! empty($m['is_weather']) || ! isset($m['default_capacity']) ) continue;
                                    $prefix = $m['id_prefix'];
                                    $enabled = ! empty( $o['enable_' . $key] );
                                    ?>
                                    <tr class="ha-pf-limit-row-<?php echo $key; ?>" <?php echo $enabled ? '' : 'style="display:none;"'; ?>>
                                        <th><?php echo esc_html( $m['label'] ); ?> Limit</th>
                                        <td>
                                            <input type="number" name="ha_powerflow_options[<?php echo $prefix; ?>_max_capacity]" 
                                                   value="<?php echo (int)($o[$prefix . '_max_capacity'] ?? $m['default_capacity']); ?>" 
                                                   class="small-text" /> W
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                            <div class="ha-pf-hint" style="margin-top:10px;">
                                💡 Increasing a limit makes the flow look "slower" for the same amount of power.
                            </div>
                        </div>

                        <div class="ha-pf-card">
                            <h2>📐 Common Paths</h2>
                            <table class="form-table form-table-sm" role="presentation">
                                <tr><th>Grid Line</th><td><input type="text" name="ha_powerflow_options[grid_line]" value="<?php echo esc_attr( $o['grid_line'] ?? '' ); ?>" class="widefat" /></td></tr>
                                <tr id="ha-pf-load-line-row"><th>House Line</th><td><input type="text" name="ha_powerflow_options[load_line]" value="<?php echo esc_attr( $o['load_line'] ?? '' ); ?>" class="widefat" /></td></tr>
                            </table>
                        </div>
                    </div>

                    <div class="ha-pf-col ha-pf-col-right">
                        <div class="ha-pf-card">
                            <h2>🏷 Core Label Positions</h2>
                            <table class="form-table form-table-sm" role="presentation">
                                <tr><th>Grid</th><td><?php ha_pf_xy( $o, 'grid_label_x', 'grid_label_y', 120, 260 ); ?></td></tr>
                                <tr><th>Home</th><td><?php ha_pf_xy( $o, 'load_label_x', 'load_label_y', 880, 260 ); ?></td></tr>
                                <tr><th>Status</th><td><?php ha_pf_xy( $o, 'status_x', 'status_y', 500, 320 ); ?></td></tr>
                            </table>
                        </div>
                        <div class="ha-pf-card">
                            <h2>🎨 Text Colors</h2>
                            <table class="form-table form-table-sm" role="presentation">
                                <tr><th>Title</th><td><input type="text" name="ha_powerflow_options[title_color]" value="<?php echo esc_attr( $o['title_color'] ?? '#8899bb' ); ?>" class="ha-pf-color-picker" /></td></tr>
                                <tr><th>Power</th><td><input type="text" name="ha_powerflow_options[power_color]" value="<?php echo esc_attr( $o['power_color'] ?? '#f0a500' ); ?>" class="ha-pf-color-picker" /></td></tr>
                                <tr><th>Energy</th><td><input type="text" name="ha_powerflow_options[energy_color]" value="<?php echo esc_attr( $o['energy_color'] ?? '#6677aa' ); ?>" class="ha-pf-color-picker" /></td></tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
