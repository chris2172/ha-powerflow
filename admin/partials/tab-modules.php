            <div id="ha-pf-tab-modules" class="ha-pf-tab-content">
                <div class="ha-pf-columns">
                    <?php
                    $all_modules = HA_Powerflow_Modules::get_all();
                    $keys = array_keys( $all_modules );
                    $half = ceil( count($keys) / 2 );
                    $col_left = array_slice( $keys, 0, $half );
                    $col_right = array_slice( $keys, $half );

                    foreach ( [ 'left' => $col_left, 'right' => $col_right ] as $col => $m_ids ) : ?>
                        <div class="ha_pf-col ha-pf-col-<?php echo $col; ?>">
                            <?php foreach ( $m_ids as $id ) : 
                                $m = $all_modules[$id];
                                if ( ! empty( $m['is_weather'] ) ) continue; // Weather has its own card usually, or we can include it
                                ?>
                                <div id="ha-pf-section-<?php echo $id; ?>" class="ha-pf-card ha-pf-module-card" data-module="<?php echo $id; ?>">
                                    <h2><?php echo $m['icon'] . ' ' . $m['label']; ?></h2>
                                    <table class="form-table form-table-sm">
                                        <?php 
                                        $id_prefix = $m['id_prefix'];
                                        ha_pf_entity( $o, $id_prefix . '_power', 'Power Entity', 'sensor.' . $id_prefix . '_power' );
                                        
                                        if ( ! empty($m['has_energy']) ) {
                                            if ( $id === 'battery' ) {
                                                ha_pf_entity( $o, 'battery_in_energy', 'Energy In', 'sensor.battery_energy_in' );
                                                ha_pf_entity( $o, 'battery_out_energy', 'Energy Out', 'sensor.battery_energy_out' );
                                            } else {
                                                ha_pf_entity( $o, $id_prefix . '_energy', 'Energy Today', 'sensor.' . $id_prefix . '_energy' );
                                            }
                                        }

                                        if ( ! empty($m['has_soc']) ) {
                                            ha_pf_entity( $o, $id_prefix . '_soc', 'SOC Entity', 'sensor.' . $id_prefix . '_soc' );
                                        }

                                        if ( ! empty($m['has_eff']) ) {
                                            ha_pf_entity( $o, $id_prefix . '_efficiency', 'Efficiency (COP)', 'sensor.' . $id_prefix . '_cop' );
                                        }

                                        // ── EV-specific extra fields ───────────────────────────────
                                        if ( $id === 'ev' ) :
                                            $ev_fields = [
                                                'ev_charge_added' => [ 'label' => 'Charge Added',    'placeholder' => 'sensor.ev_charge_added',  'vis_key' => 'ev_charge_added_vis'  ],
                                                'ev_plug_status'  => [ 'label' => 'Plug Status',     'placeholder' => 'sensor.ev_plug_status',   'vis_key' => 'ev_plug_status_vis'   ],
                                                'ev_charge_mode'  => [ 'label' => 'Charge Mode',     'placeholder' => 'sensor.ev_charge_mode',   'vis_key' => 'ev_charge_mode_vis'   ],
                                                'ev_charger_cost' => [ 'label' => 'Co Charger Cost', 'placeholder' => 'sensor.ev_charger_cost',  'vis_key' => 'ev_charger_cost_vis'  ],
                                            ];
                                            foreach ( $ev_fields as $ef_key => $ef ) : ?>
                                            <tr>
                                                <th scope="row" style="padding-top:8px;">
                                                    <label><?php echo esc_html( $ef['label'] ); ?></label>
                                                </th>
                                                <td style="padding-top:8px;">
                                                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                                                        <input type="text"
                                                               name="ha_powerflow_options[<?php echo esc_attr( $ef_key ); ?>]"
                                                               value="<?php echo esc_attr( $o[ $ef_key ] ?? '' ); ?>"
                                                               class="regular-text"
                                                               placeholder="<?php echo esc_attr( $ef['placeholder'] ); ?>" />
                                                        <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:#64748b;cursor:pointer;">
                                                            <label class="ha-pf-toggle-label ha-pf-toggle-sm" style="margin:0;">
                                                                <input type="checkbox"
                                                                       name="ha_powerflow_options[<?php echo esc_attr( $ef['vis_key'] ); ?>]"
                                                                       value="1"
                                                                       <?php checked( ! empty( $o[ $ef['vis_key'] ] ) ); ?> />
                                                                <span class="ha-pf-slider"></span>
                                                            </label>
                                                            <span>Visible</span>
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach;
                                        endif;
                                        ?>
                                         <?php if ( $id === 'solar' ) : ?>
                                            <tr>
                                                <th scope="row"><label for="ha-pf-solar-show-forecast">Show Forecast Comparison</label></th>
                                                <td>
                                                    <label class="ha-pf-toggle-label ha-pf-toggle-sm">
                                                        <input type="checkbox"
                                                               id="ha-pf-solar-show-forecast"
                                                               name="ha_powerflow_options[solar_show_forecast]"
                                                               value="1" <?php checked( ! empty( $o['solar_forecast_vis'] ) ); ?>/>
                                                        <span class="ha-pf-slider"></span>
                                                    </label>
                                                    <span class="description"> Requires 'Energy Today' and 'Solar Forecast Entity' sensors.</span>
                                                </td>
                                            </tr>
                                         <?php endif; ?>

                                         <tr id="ha-pf-<?php echo $id_prefix; ?>-line-row">
                                            <th>Path</th>
                                            <td><input type="text" name="ha_powerflow_options[<?php echo $id_prefix; ?>_line]" value="<?php echo esc_attr( $o[$id_prefix . '_line'] ?? '' ); ?>" class="widefat" placeholder="SVG Path..."/></td>
                                        </tr>
                                        <tr id="ha-pf-<?php echo $id_prefix; ?>-label-row">
                                            <th>Position</th>
                                            <td><?php ha_pf_xy( $o, $id_prefix . '_label_x', $id_prefix . '_label_y', $m['default_pos']['x'], $m['default_pos']['y'] ); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            <?php endforeach; ?>

                            <?php if ( $col === 'right' ) : 
                                $wm = $all_modules['weather']; ?>
                                <div id="ha-pf-section-weather" class="ha-pf-card ha-pf-module-card" data-module="weather">
                                    <h2>☁️ Weather</h2>
                                    <table class="form-table form-table-sm">
                                        <?php ha_pf_entity( $o, 'weather_entity', 'Weather Entity', 'weather.home' ); ?>
                                        <tr><th>Font Size</th><td><input type="number" name="ha_powerflow_options[weather_font_size]" value="<?php echo (int)($o['weather_font_size'] ?? 13); ?>" class="small-text" /> px</td></tr>
                                        <tr><th>Position</th><td><?php ha_pf_xy( $o, 'weather_x', 'weather_y', 500, 80 ); ?></td></tr>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
