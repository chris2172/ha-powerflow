            <div id="ha-pf-tab-connection" class="ha-pf-tab-content active">
                <div class="ha-pf-columns">
                    <div class="ha-pf-col ha-pf-col-left">
                        <div class="ha-pf-card">
                            <h2>🔌 Connection & Refresh</h2>
                            <table class="form-table form-table-sm" role="presentation">
                                <tr>
                                    <th><label for="ha_url">HA URL</label></th>
                                    <td>
                                        <input type="url" id="ha_url" name="ha_powerflow_options[ha_url]"
                                               value="<?php echo esc_attr( $o['ha_url'] ?? '' ); ?>"
                                               class="widefat" placeholder="http://homeassistant.local:8123"/>
                                        <p class="description">Include port, e.g. <code>http://192.168.1.10:8123</code></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="ha_token">Access Token</label></th>
                                    <td>
                                        <input type="password" id="ha_token" name="ha_powerflow_options[ha_token]"
                                               value="<?php echo esc_attr( $o['ha_token'] ?? '' ); ?>"
                                               class="widefat"/>
                                        <p class="description">HA → Profile → Long-Lived Access Tokens.</p>
                                        <button type="button" id="ha-pf-test-btn" class="button" style="margin-top:8px;">
                                            Test Connection
                                        </button>
                                        <div id="ha-pf-test-result" style="display:none;margin-top:8px;padding:8px 12px;border-radius:4px;font-size:13px;font-weight:600;"></div>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="refresh_rate">Refresh Rate</label></th>
                                    <td>
                                        <?php
                                        $refresh = (int) ( $o['refresh_rate'] ?? 5000 );
                                        $rates   = [
                                            5000  => '5 seconds (default)',
                                            10000 => '10 seconds ★ recommended',
                                            15000 => '15 seconds',
                                            30000 => '30 seconds',
                                            60000 => '1 minute',
                                        ];
                                        ?>
                                        <select id="refresh_rate" name="ha_powerflow_options[refresh_rate]">
                                            <?php foreach ( $rates as $ms => $label ) : ?>
                                                <option value="<?php echo $ms; ?>" <?php selected( $refresh, $ms ); ?>>
                                                    <?php echo esc_html( $label ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="description">How often the widget polls Home Assistant for new data.</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div class="ha-pf-col ha-pf-col-right">
                        <div class="ha-pf-card">
                            <h2>📡 Connection Diagnostics</h2>
                            <div id="ha-pf-diag-panel" style="font-size:13px; line-height:1.6;">
                                <p><strong>Status:</strong> <span id="ha-pf-diag-status">Checking...</span></p>
                                <p><strong>Last Response:</strong> <span id="ha-pf-diag-time">—</span></p>
                                <div id="ha-pf-diag-log" style="margin-top:10px; font-family:monospace; background:#1a202c; color:#a0aec0; padding:12px; border-radius:8px; height:150px; overflow-y:auto;">
                                    [System Ready]
                                </div>
                            </div>
                            <button type="button" id="ha-pf-diag-refresh" class="button" style="margin-top:10px;">Refresh Diagnostics</button>
                        </div>
                    </div>
                </div>
            </div>
