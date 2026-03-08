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
                                        ?>
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
