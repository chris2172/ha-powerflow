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
                                <?php ha_pf_entity( $o, 'load_energy', 'House Energy Entity', 'sensor.load_energy' ); ?>
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
                            <h2>✨ Additional HUD Entities</h2>
                            <p class="description">Add extra sensors to your HUD. They will inherit Title and Power text colors.</p>
                            
                            <table class="wp-list-table widefat fixed striped" id="ha-pf-custom-entities-table">
                                <thead>
                                    <tr>
                                        <th class="ha-pf-col-label">Label</th>
                                        <th class="ha-pf-col-entity">Entity ID</th>
                                        <th class="ha-pf-col-pos" style="width:240px;">Position (X, Y)</th>
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
