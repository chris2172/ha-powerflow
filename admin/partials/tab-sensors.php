            <div id="ha-pf-tab-sensors" class="ha-pf-tab-content">
                <div class="ha-pf-columns">
                    <div class="ha-pf-col ha-pf-col-left">
                        <div class="ha-pf-card">
                            <h2>📡 Core Sensors</h2>
                            <table class="form-table form-table-sm" role="presentation">
                                <?php ha_pf_entity( $o, 'grid_power',  'Grid Power Entity',  'sensor.grid_power',  'Positive = import, negative = export.' ); ?>
                                <?php ha_pf_entity( $o, 'load_power',  'House Power Entity',  'sensor.load_power'  ); ?>
                                <?php ha_pf_entity( $o, 'grid_energy', 'Grid Energy Import', 'sensor.grid_energy_import' ); ?>
                                <?php ha_pf_entity( $o, 'grid_energy_out', 'Grid Energy Export', 'sensor.grid_energy_export' ); ?>
                                 <?php ha_pf_entity( $o, 'grid_price_in', 'Grid Price Import (£)', 'sensor.grid_price_import' ); ?>
                                 <?php ha_pf_entity( $o, 'grid_price_out', 'Grid Price Export (£)', 'sensor.grid_price_export' ); ?>
                                 <tr>
                                    <th scope="row"><label for="ha-pf-grid-price-cheap">Cheap Price Threshold</label></th>
                                    <td>
                                        <input type="number" step="0.01" id="ha-pf-grid-price-cheap" name="ha_powerflow_options[grid_price_cheap]" value="<?php echo esc_attr( $o['grid_price_cheap'] ?? '0.10' ); ?>" style="width:80px;" />
                                        <span class="description"> (£) Flow turns Green below this.</span>
                                    </td>
                                 </tr>
                                 <tr>
                                    <th scope="row"><label for="ha-pf-grid-price-high">High Price Threshold</label></th>
                                    <td>
                                        <input type="number" step="0.01" id="ha-pf-grid-price-high" name="ha_powerflow_options[grid_price_high]" value="<?php echo esc_attr( $o['grid_price_high'] ?? '0.30' ); ?>" style="width:80px;" />
                                        <span class="description"> (£) Flow turns Red above this.</span>
                                    </td>
                                 </tr>
                                 <tr>
                                    <th scope="row"><label>Show Savings Tracker</label></th>
                                    <td>
                                        <label class="ha-pf-toggle-label ha-pf-toggle-sm">
                                            <input type="checkbox" name="ha_powerflow_options[grid_show_savings]" value="1" <?php checked( ! empty( $o['grid_show_savings'] ) ); ?>/>
                                            <span class="ha-pf-slider"></span>
                                        </label>
                                        <span class="description"> Displays live savings (£/hr) under the Grid module.</span>
                                    </td>
                                 </tr>
                                 <tr>
                                    <th scope="row"><label for="ha-pf-battery-capacity">Battery Capacity (kWh)</label></th>
                                    <td>
                                        <input type="number" step="0.01" id="ha-pf-battery-capacity" name="ha_powerflow_options[battery_capacity_kwh]" value="<?php echo esc_attr( $o['battery_capacity_kwh'] ?? '13.50' ); ?>" style="width:100px;" />
                                        <span class="description"> kWh (used for duration 2 decimal places)</span>
                                    </td>
                                 </tr>
                                 <tr>
                                    <th scope="row"><label for="ha-pf-battery-min-discharge">Min Battery SOC (%)</label></th>
                                    <td>
                                        <input type="number" step="1" id="ha-pf-battery-min-discharge" name="ha_powerflow_options[battery_min_discharge]" value="<?php echo esc_attr( $o['battery_min_discharge'] ?? '10' ); ?>" style="width:80px;" />
                                        <span class="description"> % (stop discharge limit)</span>
                                    </td>
                                 </tr>
                                 <?php ha_pf_entity( $o, 'load_energy', 'House Energy Entity', 'sensor.load_energy' ); ?>
                                <?php ha_pf_entity( $o, 'solar_forecast', 'Solar Forecast Entity', 'sensor.solcast_forecast_today', 'Used for Expected vs Actual comparison.' ); ?>
                            </table>
                        </div>
                    </div>
                    <div class="ha-pf-col ha-pf-col-right">
                        <div class="ha-pf-card">
                            <h2>🔍 Smart Discovery</h2>
                            <p class="description">Scan your Home Assistant for relevant sensors.</p>
                            <button type="button" id="ha-pf-discover-btn" class="button">Scan for Entities</button>
                            <div id="ha-pf-discover-results" style="margin-top:10px; display:none; max-height:200px; overflow-y:auto; font-size:12px; border:1px solid #e2e8f0; border-radius:8px; padding:10px; background:#f8fafc;"></div>
                        </div>
                    </div>
                </div>

                <div class="ha-pf-columns" style="margin-top:32px;">
                    <div class="ha-pf-col ha-pf-col-full">
                        <div class="ha-pf-card">
                            <h2>⚙️ Defaults</h2>
                            <table class="form-table form-table-sm" role="presentation">
                                <tr>
                                    <th scope="row"><label for="ha-pf-currency-symbol">Currency Symbol</label></th>
                                    <td>
                                        <input type="text"
                                               id="ha-pf-currency-symbol"
                                               name="ha_powerflow_options[ev_currency_symbol]"
                                               value="<?php echo esc_attr( $o['ev_currency_symbol'] ?? '£' ); ?>"
                                               style="width:60px;text-align:center;"
                                               maxlength="3"
                                               placeholder="£" />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="ha-pf-miles-per-kwh">EV Efficiency (miles per kWh)</label></th>
                                    <td>
                                        <input type="number"
                                               id="ha-pf-miles-per-kwh"
                                               name="ha_powerflow_options[ev_miles_per_kwh]"
                                               value="<?php echo esc_attr( $o['ev_miles_per_kwh'] ?? '3.5' ); ?>"
                                               step="0.1" min="1" max="10"
                                               style="width:80px;" />
                                        <span class="description"> miles/kWh &nbsp;(used to estimate range added per session)</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="ha-pf-session-hours">Expected Session Length (hours)</label></th>
                                    <td>
                                        <input type="number"
                                               id="ha-pf-session-hours"
                                               name="ha_powerflow_options[ev_session_expected_hours]"
                                               value="<?php echo esc_attr( $o['ev_session_expected_hours'] ?? '4' ); ?>"
                                               step="0.5" min="0.5" max="24"
                                               style="width:80px;" />
                                        <span class="description"> hours &nbsp;(used for the session progress bar)</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="ha-pf-co2-factor">CO₂ Saved Factor (kg per kWh)</label></th>
                                    <td>
                                        <input type="number"
                                               id="ha-pf-co2-factor"
                                               name="ha_powerflow_options[ev_co2_factor]"
                                               value="<?php echo esc_attr( $o['ev_co2_factor'] ?? '0.5' ); ?>"
                                               step="0.01" min="0.01" max="5"
                                               style="width:80px;" />
                                        <span class="description"> kg CO₂ &nbsp;(saving vs petrol equivalent — UK average ≈ 0.5)</span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="ha-pf-columns" style="margin-top:32px;">
                    <div class="ha-pf-col ha-pf-col-full">
                        <div class="ha-pf-card">
                            <h2>✨ Additional HUD Entities</h2>
                            <p class="description">Add extra sensors to your HUD. They will inherit Title and Power text colors.</p>
                            
                            <table class="wp-list-table widefat fixed striped" id="ha-pf-custom-entities-table">
                                <thead>
                                    <tr>
                                        <th class="ha-pf-col-label">Label</th>
                                        <th class="ha-pf-col-entity">Entity ID</th>
                                        <th class="ha-pf-col-pos" style="width:240px;">Position (X, Y)</th>
                                        <th class="ha-pf-col-size" style="width:100px;">Size (px)</th>
                                        <th class="ha-pf-col-visible" style="width:100px;">Visible</th>
                                        <th style="width:50px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $custom = $o['custom_entities'] ?? [];
                                    if ( ! is_array( $custom ) ) $custom = [];
                                    foreach ( $custom as $index => $item ) { ?>
                                    <tr data-index="<?php echo $index; ?>">
                                        <td><input type="text" name="ha_powerflow_options[custom_entities][<?php echo $index; ?>][label]" value="<?php echo esc_attr( $item['label'] ?? '' ); ?>" class="widefat ha-pf-label-input" placeholder="e.g. Temp" /></td>
                                        <td><input type="text" name="ha_powerflow_options[custom_entities][<?php echo $index; ?>][entity]" value="<?php echo esc_attr( $item['entity'] ?? '' ); ?>" class="widefat" placeholder="sensor.xyz" /></td>
                                        <td>
                                            <div class="ha-pf-xy-group" style="display:flex;align-items:center;gap:5px;">
                                                <input type="number" name="ha_powerflow_options[custom_entities][<?php echo $index; ?>][x]" value="<?php echo esc_attr( $item['x'] ?? 0 ); ?>" class="small-text" min="0" max="1000" />
                                                <input type="number" name="ha_powerflow_options[custom_entities][<?php echo $index; ?>][y]" value="<?php echo esc_attr( $item['y'] ?? 0 ); ?>" class="small-text" min="0" max="700" />
                                                <button type="button" class="ha-pf-coord-picker-btn" title="Pick position from image">🎯</button>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="number" name="ha_powerflow_options[custom_entities][<?php echo $index; ?>][font_size]" value="<?php echo (int)( $item['font_size'] ?? 19 ); ?>" class="small-text" min="8" max="100" />
                                        </td>
                                        <td>
                                            <label class="ha-pf-toggle-label ha-pf-toggle-sm">
                                                <input type="checkbox" name="ha_powerflow_options[custom_entities][<?php echo $index; ?>][visible]" value="1" <?php checked( ! empty( $item['visible'] ) ); ?>/>
                                                <span class="ha-pf-slider"></span>
                                            </label>
                                        </td>
                                        <td><button type="button" class="button ha-pf-remove-entity">×</button></td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                            <div style="margin-top:15px;">
                                <button type="button" class="button" id="ha-pf-add-entity">+ Add Entity</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
