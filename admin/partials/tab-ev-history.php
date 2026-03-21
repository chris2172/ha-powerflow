<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div id="ha-pf-tab-ev-history" class="ha-pf-tab-content">
    <?php
    $sessions   = get_option( HA_Powerflow_EV_Session::OPTION_KEY, [] );
    if ( ! is_array( $sessions ) )  $sessions  = [];

    // Fetch WordPress Users for session assignment
    $users = get_users([ 'fields' => ['ID', 'display_name', 'user_email'] ]);
    $user_map = [];
    foreach ( $users as $u ) { $user_map[ $u->ID ] = $u; }

    $o        = get_option( 'ha_powerflow_options', [] );
    $currency = esc_html( $o['ev_currency_symbol'] ?? '£' );

    $completed = array_filter( $sessions, function($s) { return ($s['status'] ?? '') === 'completed'; } );
    $active    = array_filter( $sessions, function($s) { return ($s['status'] ?? '') === 'active'; } );

    // Aggregate stats
    $total_kwh = $total_cost = $total_paid = $total_outstanding = 0;
    foreach ( $completed as $s ) {
        $pts = $s['data_points'] ?? [];
        $max_kwh = 0;
        foreach ( $pts as $p ) { if ( $p['kwh'] > $max_kwh ) $max_kwh = $p['kwh']; }
        $rate = floatval( $s['cost_rate'] ?? 0 );
        $cost = HA_Powerflow_EV_Session::calculate_session_cost( $pts, $rate, $o );
        $total_kwh     += $max_kwh;
        $total_cost    += $cost;
        if ( ! empty( $s['payment_received'] ) ) {
            $total_paid += $cost;
        } else {
            $total_outstanding += $cost;
        }
    }
    ?>

    <!-- ── Session History Card ───────────────────────────────────────────── -->
    <div class="ha-pf-card">
        <h2>⚡ EV Charging History</h2>
        <p class="description">Review charging sessions and assign them to WordPress Users.</p>
        
        <!-- Booking Configuration Section -->
        <div class="ha-pf-booking-config">
            <h3>📅 Booking & Fair Usage Settings</h3>
            <div class="ha-pf-config-grid">
                <div class="ha-pf-control" style="grid-column: span 2;">
                    <label>Admin Markup Ranges</label>
                    <table id="ha-pf-markup-ranges" class="widefat">
                        <thead>
                            <tr>
                                <th>Min Price (<?php echo $currency; ?>)</th>
                                <th>Max Price (<?php echo $currency; ?>)</th>
                                <th>Markup (%)</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $ranges = $o['ev_booking_markup_ranges'] ?? [];
                            if ( empty( $ranges ) ) {
                                // Default row if none
                                $ranges[] = [ 'min' => 0, 'max' => 9.99, 'pct' => 20 ];
                            }
                            foreach ( $ranges as $i => $r ) : ?>
                            <tr data-index="<?php echo $i; ?>">
                                <td><input type="number" step="0.01" class="ha-pf-markup-min" value="<?php echo esc_attr( $r['min'] ); ?>" /></td>
                                <td><input type="number" step="0.01" class="ha-pf-markup-max" value="<?php echo esc_attr( $r['max'] ); ?>" /></td>
                                <td><input type="number" step="0.1" class="ha-pf-markup-pct" value="<?php echo esc_attr( $r['pct'] ); ?>" /></td>
                                <td><button type="button" class="button ha-pf-remove-range">×</button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4"><button type="button" id="ha-pf-add-range" class="button">＋ Add Range</button></td>
                            </tr>
                        </tfoot>
                    </table>
                    <span class="description">Percentage markup based on the grid price at the time of booking.</span>
                </div>
                <div class="ha-pf-control">
                    <label>Max Duration (Hours)</label>
                    <input type="number" step="0.5" id="ha-pf-max-duration" value="<?php echo esc_attr( $o['ev_booking_max_duration'] ?? 4 ); ?>" />
                    <span class="description">Maximum session length allowed.</span>
                </div>
                <div class="ha-pf-control">
                    <label>Booking Buffer (Mins)</label>
                    <input type="number" step="5" id="ha-pf-buffer" value="<?php echo esc_attr( $o['ev_booking_buffer'] ?? 15 ); ?>" />
                    <span class="description">Gap between separate bookings.</span>
                </div>
                <div class="ha-pf-control">
                    <label>Max Active Bookings</label>
                    <input type="number" id="ha-pf-max-active" value="<?php echo esc_attr( $o['ev_booking_max_active'] ?? 2 ); ?>" />
                    <span class="description">Per user at any one time.</span>
                </div>
                <!-- Intelligent Octopus Go Settings -->
                <div class="ha-pf-control" style="grid-column: span 1; border-top: 1px solid #e2e8f0; padding-top: 15px;">
                    <label>Intelligent Octopus Go</label>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" id="ha-pf-intel-mode" <?php checked( ! empty( $o['ev_booking_intel_mode'] ) ); ?> />
                        <span class="description">Enable split peak/off-peak costing.</span>
                    </div>
                </div>
                <div class="ha-pf-control" style="grid-column: span 1; border-top: 1px solid #e2e8f0; padding-top: 15px;">
                    <label>Off-Peak Window</label>
                    <div style="display:flex; align-items:center; gap:5px;">
                        <input type="time" id="ha-pf-offpeak-start" value="<?php echo esc_attr( $o['ev_booking_offpeak_start'] ?? '23:30' ); ?>" style="padding:4px; font-size:12px;" />
                        <span>to</span>
                        <input type="time" id="ha-pf-offpeak-end" value="<?php echo esc_attr( $o['ev_booking_offpeak_end'] ?? '05:30' ); ?>" style="padding:4px; font-size:12px;" />
                    </div>
                </div>
                <div class="ha-pf-control" style="grid-column: span 1; border-top: 1px solid #e2e8f0; padding-top: 15px;">
                    <label>Peak Price Override (<?php echo $currency; ?>)</label>
                    <input type="number" step="0.0001" id="ha-pf-peak-price" value="<?php echo esc_attr( $o['ev_booking_peak_price'] ?? 0.30 ); ?>" />
                    <span class="description">Price used outside the off-peak window.</span>
                </div>
            </div>
            <button type="button" id="ha-pf-save-booking-settings" class="button button-primary">Save Booking Settings</button>
            <span id="ha-pf-booking-msg" style="margin-left:10px; font-size:12px;"></span>
        </div>

        <!-- Summary stats -->
        <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:12px; margin-bottom:28px;">
            <div class="ha-pf-history-stat">
                <span class="ha-pf-history-stat-label">Total Sessions</span>
                <span class="ha-pf-history-stat-value"><?php echo esc_html( count( $completed ) ); ?></span>
            </div>
            <div class="ha-pf-history-stat">
                <span class="ha-pf-history-stat-label">Total Energy</span>
                <span class="ha-pf-history-stat-value"><?php echo esc_html( number_format( $total_kwh, 2 ) ); ?> kWh</span>
            </div>
            <div class="ha-pf-history-stat">
                <span class="ha-pf-history-stat-label">Base Cost</span>
                <span class="ha-pf-history-stat-value"><?php echo esc_html( $currency . number_format( $total_cost, 2 ) ); ?></span>
            </div>
            <div class="ha-pf-history-stat ha-pf-history-stat--cocharger">
                <span class="ha-pf-history-stat-label">Co Charger Total</span>
                <span class="ha-pf-history-stat-value"><?php echo esc_html( $currency . number_format( $total_cocharger, 2 ) ); ?></span>
                <span style="font-size:10px; color:#64748b;">incl. <?php echo esc_html( $surcharge * 100 ); ?>% fee</span>
            </div>
            <div class="ha-pf-history-stat ha-pf-history-stat--paid">
                <span class="ha-pf-history-stat-label">✅ Received</span>
                <span class="ha-pf-history-stat-value"><?php echo esc_html( $currency . number_format( $total_paid, 2 ) ); ?></span>
            </div>
            <div class="ha-pf-history-stat ha-pf-history-stat--outstanding">
                <span class="ha-pf-history-stat-label">⏳ Outstanding</span>
                <span class="ha-pf-history-stat-value"><?php echo esc_html( $currency . number_format( $total_outstanding, 2 ) ); ?></span>
            </div>
            <?php if ( ! empty( $active ) ) : ?>
            <div class="ha-pf-history-stat ha-pf-history-stat--active">
                <span class="ha-pf-history-stat-label">Live Session</span>
                <span class="ha-pf-history-stat-value">● Active</span>
            </div>
            <?php endif; ?>
        </div>

        <?php if ( empty( $sessions ) ) : ?>
        <p style="color:#64748b; font-style:italic;">No charging sessions recorded yet. Sessions are captured automatically when your EV is plugged in while the <code>[ha_powerflow]</code> widget is active on a page.</p>
        <?php else : ?>
        <div style="overflow-x:auto;">
        <table class="wp-list-table widefat fixed striped" id="ha-pf-ev-history-table">
            <thead>
                <tr>
                    <th style="width:36px;">#</th>
                    <th style="width:120px;">Customer</th>
                    <th>Date</th>
                    <th style="width:60px;">Start</th>
                    <th style="width:60px;">End</th>
                    <th style="width:70px;">Duration</th>
                    <th style="width:70px;">kWh</th>
                    <th style="width:75px;">Grid Cost</th>
                    <th style="width:90px;">Total Cost</th>
                    <th style="width:70px;">Avg kW</th>
                    <th style="width:70px;">Peak kW</th>
                    <th style="width:70px;">Paid</th>
                    <th style="width:70px;">Status</th>
                    <th style="width:40px;"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $sessions as $num => $s ) :
                $pts      = $s['data_points'] ?? [];
                $status   = $s['status'] ?? 'completed';
                $start_ts = intval( $s['start_ts'] ?? 0 );
                $end_ts   = isset( $s['end_ts'] ) ? intval( $s['end_ts'] ) : null;
                $rate     = floatval( $s['cost_rate'] ?? 0 );
                $sid      = $s['id'] ?? $num;
                $cust_id  = $s['customer_id'] ?? '';
                $paid     = ! empty( $s['payment_received'] );

                $max_kwh = 0; $powers = [];
                foreach ( $pts as $p ) {
                    if ( $p['kwh'] > $max_kwh ) $max_kwh = $p['kwh'];
                    if ( $p['power'] > 0 ) $powers[] = $p['power'];
                }
                $kwh         = round( $max_kwh, 2 );
                $total_session_cost = HA_Powerflow_EV_Session::calculate_session_cost( $pts, $rate, $o );
                $grid_cost          = $rate > 0 ? $kwh * $rate : null;
                $avg_kw      = ! empty( $powers ) ? round( array_sum($powers)/count($powers)/1000, 2 ) : null;
                $peak_kw     = ! empty( $powers ) ? round( max($powers)/1000, 2 ) : null;
                $dur_secs    = $end_ts ? ( $end_ts - $start_ts ) : ( time() - $start_ts );
                $h = floor($dur_secs/3600); $m = floor(($dur_secs%3600)/60);
                $dur_str     = $h > 0 ? "{$h}h {$m}m" : "{$m}m";
            ?>
            <tr data-session-id="<?php echo esc_attr( $sid ); ?>" <?php if ($paid) echo 'class="ha-pf-row-paid"'; ?>>
                <td><?php echo esc_html( $num + 1 ); ?></td>
                <td>
                    <select class="ha_pf_assign_user" data-session-id="<?php echo esc_attr( $sid ); ?>" style="width:100%; font-size:11px; max-width:120px;">
                        <option value="">— unassigned —</option>
                        <?php foreach ( $users as $u ) : ?>
                        <option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( $cust_id, $u->ID ); ?>><?php echo esc_html( $u->display_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><?php echo $start_ts ? esc_html( date('D j M Y', $start_ts) ) : '—'; ?></td>
                <td><code><?php echo $start_ts ? esc_html( date('H:i', $start_ts) ) : '—'; ?></code></td>
                <td><code><?php echo $end_ts ? esc_html( date('H:i', $end_ts) ) : ( $status === 'active' ? '<em>Live</em>' : '—' ); ?></code></td>
                <td><?php echo esc_html( $dur_str ); ?></td>
                <td><strong><?php echo esc_html( $kwh ); ?></strong></td>
                <td><?php echo $grid_cost !== null ? esc_html( $currency . number_format( $grid_cost, 2 ) ) : '—'; ?></td>
                <td style="font-weight:700; color:#7c3aed;"><?php echo $total_session_cost !== null ? esc_html( $currency . number_format( $total_session_cost, 2 ) ) : '—'; ?></td>
                <td><?php echo $avg_kw !== null  ? esc_html( $avg_kw )  : '—'; ?></td>
                <td><?php echo $peak_kw !== null ? esc_html( $peak_kw ) : '—'; ?></td>
                <td style="text-align:center;">
                    <?php if ( $status !== 'active' ) : ?>
                    <input type="checkbox" class="ha-pf-payment-checkbox"
                           data-session-id="<?php echo esc_attr( $sid ); ?>"
                           <?php checked( $paid ); ?>
                           title="Mark payment received" />
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ( $status === 'active' ) : ?>
                    <span class="ha-pf-badge ha-pf-badge--green">Live</span>
                    <?php elseif ( $paid ) : ?>
                    <span class="ha-pf-badge ha-pf-badge--paid">Paid</span>
                    <?php else : ?>
                    <span class="ha-pf-badge ha-pf-badge--blue">Done</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ( $status !== 'active' ) : ?>
                    <button type="button" class="button button-small ha-pf-delete-session"
                            data-session-id="<?php echo esc_attr( $sid ); ?>"
                            title="Delete">✕</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <div style="margin-top:16px; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <button type="button" id="ha-pf-export-csv" class="button">⬇ Export CSV</button>
            <button type="button" id="ha-pf-clear-history" class="button" style="color:#dc2626; border-color:#dc2626;">🗑 Clear All History</button>
            <span id="ha-pf-history-msg" style="font-size:12px; color:#64748b;"></span>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.ha-pf-booking-config {
    background: #fff; border: 1px solid #7c3aed; border-radius: 12px;
    padding: 20px; margin-bottom: 28px; box-shadow: 0 4px 12px rgba(124, 58, 237, 0.08);
}
.ha-pf-booking-config h3 { margin-top: 0; color: #7c3aed; display: flex; align-items: center; gap: 8px; }
.ha-pf-config-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px; margin-bottom: 20px;
}
.ha-pf-control { display: flex; flex-direction: column; gap: 6px; }
.ha-pf-control label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; }
.ha-pf-control input { padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; }
.ha-pf-control .description { font-size: 11px; color: #94a3b8; }

.ha-pf-history-stat {
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
    padding: 12px 16px; display: flex; flex-direction: column; gap: 4px;
}
.ha-pf-history-stat--active      { border-color: #22c55e; background: #f0fdf4; }
.ha-pf-history-stat--cocharger   { border-color: #7c3aed; background: #faf5ff; }
.ha-pf-history-stat--paid        { border-color: #16a34a; background: #f0fdf4; }
.ha-pf-history-stat--outstanding { border-color: #f59e0b; background: #fffbeb; }
.ha-pf-history-stat-label { font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #94a3b8; }
.ha-pf-history-stat-value { font-size: 18px; font-weight: 700; color: #1e293b; }
.ha-pf-history-stat--active .ha-pf-history-stat-value      { color: #16a34a; }
.ha-pf-history-stat--cocharger .ha-pf-history-stat-value   { color: #7c3aed; }
.ha-pf-history-stat--paid .ha-pf-history-stat-value        { color: #16a34a; }
.ha-pf-history-stat--outstanding .ha-pf-history-stat-value { color: #b45309; }
.ha-pf-badge { display:inline-block; padding:3px 8px; border-radius:20px; font-size:10px; font-weight:700; letter-spacing:1px; text-transform:uppercase; }
.ha-pf-badge--green { background:#dcfce7; color:#16a34a; }
.ha-pf-badge--blue  { background:#dbeafe; color:#1d4ed8; }
.ha-pf-badge--paid  { background:#f3e8ff; color:#7c3aed; }
.ha-pf-row-paid td  { background: #f0fdf4 !important; }
</style>
