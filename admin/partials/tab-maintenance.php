            <div id="ha-pf-tab-maintenance" class="ha-pf-tab-content">
                <div class="ha-pf-columns">
                    <div class="ha-pf-col ha-pf-col-left">
                        <div class="ha-pf-card">
                            <h2>🔄 Snapshots & Restore</h2>
                            <div class="ha-pf-restore-box">
                                <p class="description">Automatic snapshots (last 50 kept).</p>
                                <div style="display:flex; gap:10px; margin-bottom:15px;">
                                    <select id="ha-pf-snapshot-select" style="flex:1;">
                                        <?php
                                        $files = glob( HA_POWERFLOW_CONFIG_DIR . 'snapshot_*.yaml' );
                                        if ( $files ) {
                                            rsort( $files );
                                            foreach ( $files as $f ) {
                                                $bn = basename( $f );
                                                echo '<option value="' . esc_attr( $bn ) . '">' . esc_html( str_replace(['snapshot_', '.yaml', '_'], ['', '', ' '], $bn) ) . '</option>';
                                            }
                                        } else { echo '<option value="">No snapshots found</option>'; }
                                        ?>
                                    </select>
                                    <button type="button" id="ha-pf-restore-btn" class="button">Restore</button>
                                    <button type="button" id="ha-pf-snapshot-btn" class="button">Take Snapshot Now</button>
                                </div>
                                <hr>
                                <p class="description">Upload .yaml backup.</p>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <input type="file" id="ha-pf-upload-file" accept=".yaml" />
                                    <button type="button" id="ha-pf-upload-btn" class="button">Upload & Restore</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ha-pf-col ha-pf-col-right">
                        <div class="ha-pf-card">
                            <h2>📡 Connection Health</h2>
                            <div id="ha-pf-health-dashboard">
                                <div class="ha-pf-health-hero">
                                    <div class="ha-pf-health-status" id="ha-pf-health-status-value">No data</div>
                                    <div class="ha-pf-health-label">API Status</div>
                                </div>
                                <div class="ha-pf-health-metrics">
                                    <div class="ha-pf-health-metric">
                                        <span class="label">Response Time</span>
                                        <span class="value" id="ha-pf-health-latency">-- ms</span>
                                    </div>
                                    <div class="ha-pf-health-metric">
                                        <span class="label">Success Rate</span>
                                        <span class="value" id="ha-pf-health-rate">-- %</span>
                                    </div>
                                    <div class="ha-pf-health-metric">
                                        <span class="label">Last Seen</span>
                                        <span class="value" id="ha-pf-health-last-seen">--</span>
                                    </div>
                                </div>
                                <div class="ha-pf-health-footer">
                                    <span id="ha-pf-health-count">0 checks tracked</span>
                                    <button type="button" id="ha-pf-refresh-health" class="button button-small">Refresh Now</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
