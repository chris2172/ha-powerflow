<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div id="ha-pf-tab-ev-history" class="ha-pf-tab-content">
    <?php
    $sessions   = get_option( HA_Powerflow_EV_Session::OPTION_KEY, [] );
    $customers  = get_option( HA_Powerflow_EV_Session::CUSTOMERS_OPTION, [] );
    if ( ! is_array( $sessions ) )  $sessions  = [];
    if ( ! is_array( $customers ) ) $customers = [];

    $o        = get_option( 'ha_powerflow_options', [] );
    $currency = esc_html( $o['ev_currency_symbol'] ?? '£' );
    $surcharge = HA_Powerflow_EV_Session::CO_CHARGER_SURCHARGE;

    $completed = array_filter( $sessions, fn($s) => ($s['status'] ?? '') === 'completed' );
    $active    = array_filter( $sessions, fn($s) => ($s['status'] ?? '') === 'active' );

    // Build customer lookup map
    $customer_map = [];
    foreach ( $customers as $c ) { $customer_map[ $c['id'] ] = $c; }

    // Aggregate stats
    $total_kwh = $total_cost = $total_cocharger = $total_paid = $total_outstanding = 0;
    foreach ( $completed as $s ) {
        $pts = $s['data_points'] ?? [];
        $max_kwh = 0;
        foreach ( $pts as $p ) { if ( $p['kwh'] > $max_kwh ) $max_kwh = $p['kwh']; }
        $rate        = floatval( $s['cost_rate'] ?? 0 );
        $cost        = $max_kwh * $rate;
        $cocharger   = $cost * ( 1 + $surcharge );
        $total_kwh  += $max_kwh;
        $total_cost += $cost;
        $total_cocharger += $cocharger;
        if ( ! empty( $s['payment_received'] ) ) {
            $total_paid += $cocharger;
        } else {
            $total_outstanding += $cocharger;
        }
    }
    ?>

    <!-- ── Customers Card ────────────────────────────────────────────────── -->
    <div class="ha-pf-card" style="margin-bottom:24px;">
        <h2>👤 Customers</h2>
        <p class="description">Add customers here to assign them to charging sessions below.</p>

        <table class="wp-list-table widefat fixed striped" id="ha-pf-customers-table" style="margin-bottom:16px;">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Notes</th>
                    <th style="width:80px;">Sessions</th>
                    <th style="width:100px;"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ( empty( $customers ) ) : ?>
                <tr id="ha-pf-no-customers-row"><td colspan="5" style="color:#64748b; font-style:italic;">No customers yet. Add one below.</td></tr>
            <?php else : ?>
                <?php foreach ( $customers as $c ) :
                    $session_count = count( array_filter( $sessions, fn($s) => ($s['customer_id'] ?? '') === $c['id'] ) );
                ?>
                <tr data-customer-id="<?php echo esc_attr( $c['id'] ); ?>">
                    <td><strong><?php echo esc_html( $c['name'] ); ?></strong></td>
                    <td><?php echo esc_html( $c['email'] ?? '' ); ?></td>
                    <td><?php echo esc_html( $c['notes'] ?? '' ); ?></td>
                    <td><?php echo esc_html( $session_count ); ?></td>
                    <td>
                        <button type="button" class="button button-small ha-pf-edit-customer" data-customer-id="<?php echo esc_attr( $c['id'] ); ?>" data-name="<?php echo esc_attr( $c['name'] ); ?>" data-email="<?php echo esc_attr( $c['email'] ?? '' ); ?>" data-notes="<?php echo esc_attr( $c['notes'] ?? '' ); ?>">Edit</button>
                        <button type="button" class="button button-small ha-pf-delete-customer" data-customer-id="<?php echo esc_attr( $c['id'] ); ?>" style="color:#dc2626;">✕</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <!-- Add / Edit customer form -->
        <div id="ha-pf-customer-form" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px;">
            <strong id="ha-pf-customer-form-title" style="display:block; margin-bottom:12px; font-size:13px;">➕ Add New Customer</strong>
            <input type="hidden" id="ha-pf-customer-id-field" value="" />
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
                <div>
                    <label style="font-size:11px; font-weight:700; display:block; margin-bottom:4px; color:#64748b;">NAME *</label>
                    <input type="text" id="ha-pf-customer-name" class="regular-text" placeholder="e.g. Jane Smith" style="width:200px;" />
                </div>
                <div>
                    <label style="font-size:11px; font-weight:700; display:block; margin-bottom:4px; color:#64748b;">EMAIL</label>
                    <input type="email" id="ha-pf-customer-email" class="regular-text" placeholder="jane@example.com" style="width:200px;" />
                </div>
                <div>
                    <label style="font-size:11px; font-weight:700; display:block; margin-bottom:4px; color:#64748b;">NOTES</label>
                    <input type="text" id="ha-pf-customer-notes" class="regular-text" placeholder="e.g. Blue Nissan Leaf" style="width:200px;" />
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="button" id="ha-pf-save-customer" class="button button-primary">Save Customer</button>
                    <button type="button" id="ha-pf-cancel-customer-edit" class="button" style="display:none;">Cancel</button>
                </div>
            </div>
            <span id="ha-pf-customer-msg" style="display:block; margin-top:8px; font-size:12px;"></span>
        </div>
    </div>

    <!-- ── Session History Card ───────────────────────────────────────────── -->
    <div class="ha-pf-card">
        <h2>⚡ EV Charging History</h2>

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
                    <th style="width:75px;">Base Cost</th>
                    <th style="width:90px;">Co Charger</th>
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
                $base_cost   = $rate > 0 ? $kwh * $rate : null;
                $co_cost     = $base_cost !== null ? $base_cost * ( 1 + $surcharge ) : null;
                $avg_kw      = ! empty( $powers ) ? round( array_sum($powers)/count($powers)/1000, 2 ) : null;
                $peak_kw     = ! empty( $powers ) ? round( max($powers)/1000, 2 ) : null;
                $dur_secs    = $end_ts ? ( $end_ts - $start_ts ) : ( time() - $start_ts );
                $h = floor($dur_secs/3600); $m = floor(($dur_secs%3600)/60);
                $dur_str     = $h > 0 ? "{$h}h {$m}m" : "{$m}m";
            ?>
            <tr data-session-id="<?php echo esc_attr( $sid ); ?>" <?php if ($paid) echo 'class="ha-pf-row-paid"'; ?>>
                <td><?php echo esc_html( $num + 1 ); ?></td>
                <td>
                    <select class="ha-pf-assign-customer" data-session-id="<?php echo esc_attr( $sid ); ?>" style="width:100%; font-size:11px; max-width:120px;">
                        <option value="">— unassigned —</option>
                        <?php foreach ( $customers as $c ) : ?>
                        <option value="<?php echo esc_attr( $c['id'] ); ?>" <?php selected( $cust_id, $c['id'] ); ?>><?php echo esc_html( $c['name'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><?php echo $start_ts ? esc_html( date('D j M Y', $start_ts) ) : '—'; ?></td>
                <td><code><?php echo $start_ts ? esc_html( date('H:i', $start_ts) ) : '—'; ?></code></td>
                <td><code><?php echo $end_ts ? esc_html( date('H:i', $end_ts) ) : ( $status === 'active' ? '<em>Live</em>' : '—' ); ?></code></td>
                <td><?php echo esc_html( $dur_str ); ?></td>
                <td><strong><?php echo esc_html( $kwh ); ?></strong></td>
                <td><?php echo $base_cost !== null ? esc_html( $currency . number_format( $base_cost, 2 ) ) : '—'; ?></td>
                <td style="font-weight:700; color:#7c3aed;"><?php echo $co_cost !== null ? esc_html( $currency . number_format( $co_cost, 2 ) ) : '—'; ?></td>
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
