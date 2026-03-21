<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class HA_Powerflow_EV_Session {

    const OPTION_KEY          = 'ha_pf_ev_sessions';
    const MAX_SESSIONS        = 50;
    const MAX_POINTS          = 2880; // 24h at 30s intervals

    public static function init() {
        // Ensure tables exist on init for already active users
        if ( ! get_option( 'ha_pf_ev_db_created' ) ) {
            if ( function_exists( 'ha_pf_create_tables' ) ) {
                ha_pf_create_tables();
                update_option( 'ha_pf_ev_db_created', true );
            }
        }

        add_action( 'rest_api_init',      [ __CLASS__, 'register_rest_routes' ] );
        add_shortcode( 'ev_charge_summary', [ __CLASS__, 'render_shortcode' ] );
        add_action( 'wp_enqueue_scripts',  [ __CLASS__, 'register_assets' ] );
        add_action( 'wp_ajax_ha_pf_delete_ev_session',   [ __CLASS__, 'ajax_delete_session' ] );
        add_action( 'wp_ajax_ha_pf_clear_ev_history',    [ __CLASS__, 'ajax_clear_history' ] );
        add_action( 'wp_ajax_ha_pf_toggle_payment',      [ __CLASS__, 'ajax_toggle_payment' ] );
        add_action( 'wp_ajax_ha_pf_assign_customer',     [ __CLASS__, 'ajax_assign_customer' ] );

        // Run migration if needed
        add_action( 'admin_init', [ __CLASS__, 'maybe_migrate_data' ] );
    }

    public static function maybe_migrate_data() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        
        $migrated = get_option( 'ha_pf_ev_migrated', false );
        if ( $migrated ) return;

        // Ensure tables exist before migrating
        if ( function_exists( 'ha_pf_create_tables' ) ) {
            ha_pf_create_tables();
        }
        $sessions_option = get_option( self::OPTION_KEY, [] );
        
        if ( ! empty( $sessions_option ) && is_array( $sessions_option ) ) {
            foreach ( $sessions_option as $s ) {
                $session_id = $s['id'] ?? ( 'sess_' . ( $s['start_ts'] ?? time() ) );
                
                // Insert session
                $wpdb->insert( HA_POWERFLOW_TABLE_SESSIONS, [
                    'id'               => $session_id,
                    'start_ts'         => $s['start_ts'] ?? time(),
                    'end_ts'           => $s['end_ts']   ?? null,
                    'status'           => $s['status']   ?? 'completed',
                    'currency'         => $s['currency'] ?? '£',
                    'cost_rate'        => $s['cost_rate'] ?? 0,
                    'last_mode'        => $s['last_mode'] ?? '',
                    'customer_id'      => $s['customer_id'] ?? null,
                    'payment_received' => ! empty( $s['payment_received'] ) ? 1 : 0
                ] );

                // Insert data points
                if ( ! empty( $s['data_points'] ) && is_array( $s['data_points'] ) ) {
                    foreach ( $s['data_points'] as $p ) {
                        $wpdb->insert( HA_POWERFLOW_TABLE_POINTS, [
                            'session_id' => $session_id,
                            'ts'         => $p['ts']    ?? time(),
                            'kwh'        => $p['kwh']   ?? 0,
                            'power'      => $p['power'] ?? 0,
                            'solar'      => $p['solar'] ?? 0
                        ] );
                    }
                }
            }
            // Clear the old option to save space, but keep it for a while just in case? 
            // Better to delete it if we are confident.
            // delete_option( self::OPTION_KEY );
        }
        
        update_option( 'ha_pf_ev_migrated', true );
    }

    public static function register_assets() {
        wp_register_style(
            'ha-pf-ev-session',
            HA_POWERFLOW_URL . 'assets/css/ev-session.css',
            [ 'ha-powerflow-fonts' ],
            HA_POWERFLOW_VERSION
        );
        wp_register_script(
            'ha-pf-chartjs',
            'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js',
            [],
            '4.4.1',
            true
        );
        wp_register_script(
            'ha-pf-ev-session',
            HA_POWERFLOW_URL . 'assets/js/ev-session.js',
            [ 'jquery', 'ha-pf-chartjs' ],
            HA_POWERFLOW_VERSION,
            true
        );
    }

    // ── REST: POST /ha-powerflow/v1/ev-session ──────────────────────────────
    public static function register_rest_routes() {
        register_rest_route( 'ha-powerflow/v1', '/ev-session', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'log_data_point' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public static function log_data_point( $request ) {
        // Rate limit: max 120 posts per minute per IP (one per poll cycle is ~12/min at 5s intervals)
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if ( $ip !== 'unknown' ) {
            $rl_key  = 'ha_pf_sess_rl_' . md5( $ip );
            $count   = (int) get_transient( $rl_key );
            if ( $count >= 120 ) {
                return new WP_Error( 'rate_limited', 'Too many requests.', [ 'status' => 429 ] );
            }
            set_transient( $rl_key, $count + 1, MINUTE_IN_SECONDS );
        }

        $plug_status  = sanitize_text_field( $request->get_param( 'plug_status' )  ?? '' );
        $charge_added = floatval( $request->get_param( 'charge_added' ) ?? 0 );
        $cost_rate    = floatval( $request->get_param( 'cost_rate' )    ?? 0 );
        $ev_power     = floatval( $request->get_param( 'ev_power' )     ?? 0 );
        $solar_kw     = floatval( $request->get_param( 'solar_kw' )     ?? 0 );
        $charge_mode  = sanitize_text_field( $request->get_param( 'charge_mode' )  ?? '' );
        $now          = time();

        // Reject any HA sensor non-values that should never affect session state
        $invalid = [ 'n/a', 'unavailable', 'unknown', 'none', '' ];
        if ( in_array( strtolower( $plug_status ), $invalid, true ) ) {
            return rest_ensure_response( [ 'status' => 'ignored' ] );
        }

        global $wpdb;
        $disconnected = ( stripos( $plug_status, 'disconnected' ) !== false );

        // Find the currently active session
        $active = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . HA_POWERFLOW_TABLE_SESSIONS . " WHERE status = %s LIMIT 1",
            'active'
        ), ARRAY_A );

        // ── EV Disconnected → end active session ───────────────────────────
        if ( $disconnected ) {
            if ( $active ) {
                $wpdb->update( HA_POWERFLOW_TABLE_SESSIONS, 
                    [ 'status' => 'completed', 'end_ts' => $now ], 
                    [ 'id' => $active['id'] ] 
                );
                return rest_ensure_response( [ 'status' => 'session_ended' ] );
            }
            return rest_ensure_response( [ 'status' => 'idle' ] );
        }

        // ── Connected / Charging → log data point ──────────────────────────
        $session_id = $active ? $active['id'] : null;

        if ( ! $session_id ) {
            // Start a brand new session
            $o        = get_option( 'ha_powerflow_options', [] );
            $currency = $o['ev_currency_symbol'] ?? '£';
            $session_id = 'sess_' . $now;
            
            // Auto-assign user from active booking
            $user_id = self::get_active_booking_user_id( $now );

            $wpdb->insert( HA_POWERFLOW_TABLE_SESSIONS, [
                'id'          => $session_id,
                'start_ts'    => $now,
                'status'      => 'active',
                'currency'    => sanitize_text_field( $currency ),
                'cost_rate'   => $cost_rate,
                'customer_id' => $user_id
            ] );

            // Enforce max session count (optional, but good to keep DB size in check)
            $session_count = $wpdb->get_var( "SELECT COUNT(*) FROM " . HA_POWERFLOW_TABLE_SESSIONS );
            if ( $session_count > self::MAX_SESSIONS ) {
                $to_delete = $wpdb->get_col( $wpdb->prepare(
                    "SELECT id FROM " . HA_POWERFLOW_TABLE_SESSIONS . " WHERE status != %s ORDER BY start_ts ASC LIMIT %d",
                    'active',
                    $session_count - self::MAX_SESSIONS
                ) );
                if ( ! empty( $to_delete ) ) {
                    $placeholders = implode( ',', array_fill( 0, count( $to_delete ), '%s' ) );
                    $wpdb->query( $wpdb->prepare( "DELETE FROM " . HA_POWERFLOW_TABLE_SESSIONS . " WHERE id IN ($placeholders)", $to_delete ) );
                    $wpdb->query( $wpdb->prepare( "DELETE FROM " . HA_POWERFLOW_TABLE_POINTS . " WHERE session_id IN ($placeholders)", $to_delete ) );
                }
            }
        }

        // Add data point (guard against unbounded growth)
        $point_count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . HA_POWERFLOW_TABLE_POINTS . " WHERE session_id = %s",
            $session_id
        ) );

        if ( $point_count < self::MAX_POINTS ) {
            $wpdb->insert( HA_POWERFLOW_TABLE_POINTS, [
                'session_id' => $session_id,
                'ts'         => $now,
                'kwh'        => round( $charge_added, 3 ),
                'power'      => round( $ev_power ),
                'solar'      => round( $solar_kw )
            ] );
        }

        // Update session meta if changed
        $update_meta = [];
        if ( $cost_rate > 0 && ( ! $active || $active['cost_rate'] != $cost_rate ) ) {
            $update_meta['cost_rate'] = $cost_rate;
        }
        if ( $charge_mode !== '' && ( ! $active || $active['last_mode'] !== $charge_mode ) ) {
            $update_meta['last_mode'] = $charge_mode;
        }
        if ( ! empty( $update_meta ) ) {
            $wpdb->update( HA_POWERFLOW_TABLE_SESSIONS, $update_meta, [ 'id' => $session_id ] );
        }

        return rest_ensure_response( [
            'status' => 'logged',
            'points' => (int) $point_count + 1
        ] );
    }

    /**
     * Helper to get the markup percentage for a given base rate.
     */
    public static function get_markup_pct_for_rate( $rate, $o ) {
        $ranges = $o['ev_booking_markup_ranges'] ?? [];
        if ( empty( $ranges ) ) return 0;
        
        $rate = floatval( $rate );
        foreach ( $ranges as $r ) {
            $min = floatval( $r['min'] ?? 0 );
            $max = floatval( $r['max'] ?? 0 );
            if ( $rate >= $min && $rate <= $max ) {
                return floatval( $r['pct'] ?? 0 );
            }
        }
        return 0;
    }

    public static function is_time_in_range( $time, $start, $end ) {
        if ( $start <= $end ) {
            return ( $time >= $start && $time <= $end );
        } else {
            return ( $time >= $start || $time <= $end );
        }
    }

    public static function get_effective_rate( $base_rate, $o, $ts = null ) {
        if ( empty( $o['ev_booking_intel_mode'] ) ) return floatval( $base_rate );
        
        $check_ts = $ts ?: time();
        $time_str = date( 'H:i', $check_ts );
        $offpeak_start = $o['ev_booking_offpeak_start'] ?? '23:30';
        $offpeak_end   = $o['ev_booking_offpeak_end']   ?? '05:30';
        
        if ( self::is_time_in_range( $time_str, $offpeak_start, $offpeak_end ) ) {
            return floatval( $base_rate );
        }
        
    // Outside off-peak window -> use peak price
        return floatval( $o['ev_booking_peak_price'] ?? $base_rate );
    }

    public static function calculate_session_cost( $pts, $session_base_rate, $o ) {
        if ( empty( $pts ) ) return 0;
        $total_cost = 0;
        $last_kwh = null;
        
        foreach ( $pts as $p ) {
            $cur_kwh = floatval( $p['kwh'] ?? 0 );
            if ( $last_kwh === null ) {
                $last_kwh = $cur_kwh;
                continue;
            }
            
            $diff = $cur_kwh - $last_kwh;
            if ( $diff > 0 ) {
                $rate = self::get_effective_rate( $session_base_rate, $o, $p['ts'] );
                $markup = self::get_markup_pct_for_rate( $rate, $o );
                $total_cost += $diff * $rate * ( 1 + ( $markup / 100 ) );
            }
            $last_kwh = $cur_kwh;
        }
        return round( $total_cost, 2 );
    }

    /**
     * Helper to get the current grid price from the configured HA sensor.
     */
    public static function get_current_cost_rate( $o ) {
        $sensor = $o['grid_price_in'] ?? '';
        if ( ! $sensor ) return 0;

        $ha_url   = isset( $o['ha_url'] )   ? rtrim( $o['ha_url'], '/' ) : '';
        $ha_token = isset( $o['ha_token'] ) ? $o['ha_token']             : '';
        if ( ! $ha_url || ! $ha_token ) return 0;

        $response = wp_remote_get( $ha_url . '/api/states/' . $sensor, [
            'headers' => [
                'Authorization' => 'Bearer ' . $ha_token,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 5
        ] );

        if ( is_wp_error( $response ) ) return 0;

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $val  = $body['state'] ?? 0;

        return is_numeric( $val ) ? floatval( $val ) : 0;
    }

    // ── Shortcode [ev_charge_summary] ───────────────────────────────────────
    public static function render_shortcode( $atts ) {
        $atts = shortcode_atts( [ 'refresh' => 0 ], $atts, 'ev_charge_summary' );
        $refresh = max( 0, intval( $atts['refresh'] ) );

        wp_enqueue_style( 'ha-powerflow-fonts' );
        wp_enqueue_style( 'ha-pf-ev-session' );
        wp_enqueue_script( 'ha-pf-chartjs' );
        wp_enqueue_script( 'ha-pf-ev-session' );

        global $wpdb;
        $active = $wpdb->get_row( "SELECT * FROM " . HA_POWERFLOW_TABLE_SESSIONS . " WHERE status = 'active' LIMIT 1", ARRAY_A );
        $history = $wpdb->get_results( "SELECT * FROM " . HA_POWERFLOW_TABLE_SESSIONS . " WHERE status != 'active' ORDER BY start_ts DESC", ARRAY_A );

        if ( $active ) {
            $active['data_points'] = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . HA_POWERFLOW_TABLE_POINTS . " WHERE session_id = %s ORDER BY ts ASC", $active['id'] ), ARRAY_A );
        }
        foreach ( $history as &$s ) {
            // Load data points only for the first history session (previous session)
            // The others in the list will load them via AJAX or we can thin them here if needed.
            // For now, let's load all for the first one.
            if ( ! empty( $history[0] ) && $s['id'] === $history[0]['id'] ) {
                 $s['data_points'] = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . HA_POWERFLOW_TABLE_POINTS . " WHERE session_id = %s ORDER BY ts ASC", $s['id'] ), ARRAY_A );
            }
        }

        // Auto-refresh only when a session is active and a refresh interval is set
        if ( $active && $refresh > 0 ) {
            $refresh_val = $refresh;
            add_action( 'wp_head', function() use ( $refresh_val ) {
                echo '<meta http-equiv="refresh" content="' . esc_attr( $refresh_val ) . '">' . "\n";
            } );
        }

        // Most recent completed session — always shown as "Previous Session"
        $previous = $history[0] ?? null;
        // Older sessions go into the collapsible history list
        $past     = array_slice( $history, 1 );

        // Config for calculations
        $o       = get_option( 'ha_powerflow_options', [] );
        $config  = [
            'currency'         => $o['ev_currency_symbol']        ?? '£',
            'miles_per_kwh'    => floatval( $o['ev_miles_per_kwh']           ?? 3.5 ),
            'expected_hours'   => floatval( $o['ev_session_expected_hours']  ?? 4.0 ),
            'co2_factor'       => floatval( $o['ev_co2_factor']              ?? 0.5 ),
            'battery_min_discharge' => floatval( $o['battery_min_discharge'] ?? 10 ),
            'battery_capacity_kwh'  => floatval( $o['battery_capacity_kwh']  ?? 13.5 ),
            'options'               => $o,
        ];

        ob_start();
        ?>
        <div class="evcs-wrap" id="evcs-wrap">

            <?php if ( $active ) : ?>
            <div class="evcs-live-banner">
                <span class="evcs-live-dot"></span>
                <span>Live Charging Session in Progress</span>
                <span class="evcs-live-time" id="evcs-live-elapsed" data-start="<?php echo esc_attr( $active['start_ts'] ); ?>"></span>
            </div>
            <div class="evcs-featured-card">
                <?php self::render_session_card( $active, 0, true, $config ); ?>
            </div>

            <?php if ( $previous ) : ?>
            <div class="evcs-previous-section">
                <h2 class="evcs-history-title evcs-history-title--previous">Previous Session</h2>
                <div class="evcs-featured-card">
                    <?php self::render_session_card( $previous, 1, true, $config ); ?>
                </div>
            </div>
            <?php endif; ?>

            <?php elseif ( $previous ) : ?>
            <div class="evcs-previous-section">
                <h2 class="evcs-history-title evcs-history-title--previous">Previous Session</h2>
                <div class="evcs-featured-card">
                    <?php self::render_session_card( $previous, 0, true, $config ); ?>
                </div>
            </div>

            <?php else : ?>
            <div class="evcs-empty">
                <div class="evcs-empty-icon">🔌</div>
                <p>No charging sessions recorded yet.</p>
                <p class="evcs-empty-sub">Sessions will appear here once the EV module is active and your EV begins charging.</p>
            </div>
            <?php endif; ?>

            <?php if ( ! empty( $past ) ) : ?>
            <div class="evcs-history">
                <h2 class="evcs-history-title">Session History</h2>
                <div class="evcs-history-list">
                    <?php
                    $chart_offset = $active ? 2 : 1;
                    foreach ( $past as $idx => $session ) :
                        self::render_session_card( $session, $idx + $chart_offset, false, $config );
                    endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }

    // ── Render a single session card ────────────────────────────────────────
    private static function render_session_card( $session, $chart_idx, $featured, $config = [] ) {
        $pts      = $session['data_points'] ?? [];
        $currency = esc_html( $config['currency']  ?? ( $session['currency'] ?? '£' ) );
        $o        = $config['options'] ?? get_option( 'ha_powerflow_options', [] );
        
        $base_rate    = floatval( $session['cost_rate'] ?? 0 );
        $effective_rate = self::get_effective_rate( $base_rate, $o, $session['start_ts'] ?? null );
        $markup_pct   = self::get_markup_pct_for_rate( $effective_rate, $o );
        
        $display_rate = $effective_rate * ( 1 + ( $markup_pct / 100 ) );
        $start_ts = intval( $session['start_ts'] ?? 0 );
        $end_ts   = isset( $session['end_ts'] ) ? intval( $session['end_ts'] ) : null;
        $active   = $session['status'] === 'active';
        $now      = time();

        // Config values
        $miles_per_kwh  = floatval( $config['miles_per_kwh']  ?? 3.5 );
        $expected_hours = floatval( $config['expected_hours'] ?? 4.0 );
        $co2_factor     = floatval( $config['co2_factor']     ?? 0.5 );

        // ── Core stats ────────────────────────────────────────────────────
        $max_kwh = 0;
        $powers  = [];
        $solar_w = [];
        foreach ( $pts as $p ) {
            if ( $p['kwh'] > $max_kwh ) $max_kwh = $p['kwh'];
            if ( ( $p['power'] ?? 0 ) > 0 ) $powers[] = $p['power'];
            if ( isset( $p['solar'] ) && $p['solar'] > 0 ) $solar_w[] = $p['solar'];
        }
        $total_kwh  = round( $max_kwh, 2 );
        $total_cost = self::calculate_session_cost( $pts, $base_rate, $o );
        $peak_kw    = ! empty( $powers ) ? round( max( $powers ) / 1000, 2 ) : null;
        $avg_kw     = ! empty( $powers ) ? round( ( array_sum( $powers ) / count( $powers ) ) / 1000, 2 ) : null;

        $duration_secs  = $end_ts ? ( $end_ts - $start_ts ) : ( $now - $start_ts );
        $duration_str   = self::format_duration( $duration_secs );
        $duration_hours = $duration_secs / 3600;

        // ── Current live values (latest data point) ───────────────────────
        $latest_power_w = 0;
        $last_mode      = esc_html( $session['last_mode'] ?? '' );
        if ( ! empty( $pts ) ) {
            $last_pt        = end( $pts );
            $latest_power_w = $last_pt['power'] ?? 0;
        }
        $current_kw       = round( $latest_power_w / 1000, 2 );
        $cost_per_hour    = ( $display_rate > 0 && $latest_power_w > 0 )
                            ? round( ( $latest_power_w / 1000 ) * $display_rate, 2 ) : null;

        // ── Energy this hour (rolling 60-min window) ──────────────────────
        $hour_ago    = $now - 3600;
        $kwh_start   = null;
        $kwh_end_val = 0;
        foreach ( $pts as $p ) {
            if ( $p['ts'] >= $hour_ago ) {
                if ( $kwh_start === null ) $kwh_start = $p['kwh'];
                $kwh_end_val = $p['kwh'];
            }
        }
        $kwh_this_hour = ( $kwh_start !== null ) ? round( max( 0, $kwh_end_val - $kwh_start ), 2 ) : null;

        // ── Projected totals (active sessions only) ───────────────────────
        $projected_kwh  = null;
        $projected_cost = null;
        if ( $active && $duration_hours > 0 && $duration_hours < $expected_hours && $total_kwh > 0 ) {
            $rate_kwh_per_hour = $total_kwh / $duration_hours;
            $projected_kwh     = round( $rate_kwh_per_hour * $expected_hours, 1 );
            $projected_cost    = $display_rate > 0 ? round( $projected_kwh * $display_rate, 2 ) : null;
        }

        // ── Miles added ───────────────────────────────────────────────────
        $miles_added = $miles_per_kwh > 0 ? round( $total_kwh * $miles_per_kwh, 0 ) : null;

        // ── CO₂ saved vs petrol ───────────────────────────────────────────
        $co2_saved = $co2_factor > 0 ? round( $total_kwh * $co2_factor, 2 ) : null;

        // ── Solar contribution ─────────────────────────────────────────────
        $solar_pct  = null;
        $avg_solar_w = 0;
        if ( ! empty( $solar_w ) && ! empty( $powers ) ) {
            $avg_solar_w = array_sum( $solar_w ) / count( $solar_w );
            $avg_ev_w    = array_sum( $powers )  / count( $powers );
            if ( $avg_ev_w > 0 ) {
                $solar_pct = round( min( 100, ( $avg_solar_w / $avg_ev_w ) * 100 ) );
            }
        }

        // ── Battery Remaining Time (active only) ──────────────────────────
        $battery_remaining_str = null;
        if ( $active ) {
            $bat_soc_eid   = $o['battery_soc']   ?? '';
            $bat_power_eid = $o['battery_power'] ?? '';
            
            if ( $bat_soc_eid && $bat_power_eid ) {
                $ha_data = HA_Powerflow_Ajax::fetch_ha_data([
                    'soc'   => $bat_soc_eid,
                    'power' => $bat_power_eid
                ]);
                
                if ( ! is_wp_error( $ha_data ) ) {
                    $curr_soc   = floatval( $ha_data['soc']['state']   ?? 0 );
                    $curr_power = floatval( $ha_data['power']['state'] ?? 0 ); // positive = discharge, negative = charge
                    
                    $min_soc      = floatval( $o['battery_min_discharge'] ?? 10 );
                    $capacity_kwh = floatval( $o['battery_capacity_kwh']  ?? 13.5 );
                    
                    if ( $curr_power < -100 && $curr_soc > $min_soc ) {
                        // Usable energy in kWh
                        $usable_kwh = ( ( $curr_soc - $min_soc ) / 100 ) * $capacity_kwh;
                        // Discharge rate in kW
                        $discharge_kw = abs( $curr_power ) / 1000;
                        // Remaining hours
                        $rem_hours = $usable_kwh / $discharge_kw;
                        $rem_secs  = $rem_hours * 3600;
                        $battery_remaining_str = self::format_duration( $rem_secs );
                    }
                }
            }
        }

        // ── Timeline progress ─────────────────────────────────────────────
        $timeline_pct = min( 100, round( ( $duration_hours / $expected_hours ) * 100 ) );

        // ── Chart data ────────────────────────────────────────────────────
        $chart_pts  = self::thin_points( $pts, 120 );
        $chart_json = wp_json_encode( $chart_pts );

        $start_label = $start_ts ? date( 'D j M Y', $start_ts ) : '—';
        $time_range  = $start_ts ? date( 'H:i', $start_ts ) : '—';
        if ( $end_ts ) $time_range .= ' – ' . date( 'H:i', $end_ts );

        $card_class     = $featured ? 'evcs-card evcs-card--featured' : 'evcs-card evcs-card--history';
        if ( $active ) $card_class .= ' evcs-card--active';
        $collapsed_attr = $featured ? '' : 'data-collapsed="true"';
        ?>
        <div class="<?php echo esc_attr( $card_class ); ?>" <?php echo $collapsed_attr; ?>>

            <!-- ── Header ─────────────────────────────────────────────── -->
            <div class="evcs-card-header" <?php if ( ! $featured ) echo 'role="button" tabindex="0"'; ?>>
                <div class="evcs-card-date">
                    <span class="evcs-card-day"><?php echo esc_html( $start_label ); ?></span>
                    <span class="evcs-card-time"><?php echo esc_html( $time_range ); ?></span>
                </div>
                <div class="evcs-card-headline">
                    <span class="evcs-headline-kwh"><?php echo esc_html( $total_kwh ); ?> <span class="evcs-unit">kWh</span></span>
                    <?php if ( $total_cost !== null ) : ?>
                    <span class="evcs-headline-cost"><?php echo esc_html( $currency . number_format( $total_cost, 2 ) ); ?></span>
                    <?php endif; ?>
                    <?php if ( $miles_added !== null && $miles_added > 0 ) : ?>
                    <span class="evcs-headline-miles">~<?php echo esc_html( $miles_added ); ?> mi</span>
                    <?php endif; ?>
                </div>
                <div class="evcs-card-badge">
                    <?php if ( $active ) : ?>
                    <span class="evcs-badge evcs-badge--active">
                        <?php echo $last_mode ? esc_html( $last_mode ) : 'Charging'; ?>
                    </span>
                    <?php else : ?>
                    <span class="evcs-badge evcs-badge--done">Completed</span>
                    <?php endif; ?>
                </div>

                <?php if ( current_user_can( 'manage_options' ) && ! empty( $session['customer_id'] ) ) : 
                    $u = get_userdata( $session['customer_id'] );
                    if ( $u ) : ?>
                    <div class="evcs-attributed-user">
                        👤 Booked by: <strong><?php echo esc_html( $u->display_name ); ?></strong>
                    </div>
                <?php endif; endif; ?>

                <?php if ( ! $featured ) : ?>
                <span class="evcs-chevron">▾</span>
                <?php endif; ?>
            </div>

            <!-- ── Body ───────────────────────────────────────────────── -->
            <div class="evcs-card-body">

                <?php if ( $active && $current_kw > 0 ) : ?>
                <!-- Live stats row -->
                <div class="evcs-live-stats">
                    <div class="evcs-live-stat">
                        <span class="evcs-live-stat-val"><?php echo esc_html( $current_kw ); ?> kW</span>
                        <span class="evcs-live-stat-lbl">Current Power</span>
                    </div>
                    <?php if ( $cost_per_hour !== null ) : ?>
                    <div class="evcs-live-stat">
                        <span class="evcs-live-stat-val"><?php echo esc_html( $currency . number_format( $cost_per_hour, 2 ) ); ?>/hr</span>
                        <span class="evcs-live-stat-lbl">Cost per Hour</span>
                    </div>
                    <?php endif; ?>
                    <?php if ( $last_mode ) : ?>
                    <div class="evcs-live-stat">
                        <span class="evcs-live-stat-val"><?php echo esc_html( $last_mode ); ?></span>
                        <span class="evcs-live-stat-lbl">Charge Mode</span>
                    </div>
                    <?php endif; ?>
                    <?php if ( $kwh_this_hour !== null ) : ?>
                    <div class="evcs-live-stat">
                        <span class="evcs-live-stat-val"><?php echo esc_html( $kwh_this_hour ); ?> kWh</span>
                        <span class="evcs-live-stat-lbl">Last 60 Minutes</span>
                    </div>
                    <?php endif; ?>
                    <?php if ( $battery_remaining_str ) : ?>
                    <div class="evcs-live-stat evcs-live-stat--battery">
                        <span class="evcs-live-stat-val"><?php echo esc_html( $battery_remaining_str ); ?></span>
                        <span class="evcs-live-stat-lbl">Battery Remaining</span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ( $active ) : ?>
                <!-- Session progress bar -->
                <div class="evcs-timeline">
                    <div class="evcs-timeline-label">
                        <span>Session Progress</span>
                        <span><?php echo esc_html( self::format_duration( $duration_secs ) ); ?> of ~<?php echo esc_html( $expected_hours ); ?>h expected</span>
                    </div>
                    <div class="evcs-timeline-bar">
                        <div class="evcs-timeline-fill" style="width:<?php echo esc_attr( $timeline_pct ); ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Core stats grid -->
                <div class="evcs-stats-grid">
                    <div class="evcs-stat">
                        <span class="evcs-stat-label">Duration</span>
                        <span class="evcs-stat-value"><?php echo esc_html( $duration_str ); ?></span>
                    </div>
                    <div class="evcs-stat">
                        <span class="evcs-stat-label">Energy Added</span>
                        <span class="evcs-stat-value"><?php echo esc_html( $total_kwh ); ?> kWh</span>
                    </div>
                    <?php if ( $total_cost !== null ) : ?>
                    <div class="evcs-stat">
                        <span class="evcs-stat-label">Total Cost</span>
                        <span class="evcs-stat-value evcs-stat-value--cost"><?php echo esc_html( $currency . number_format( $total_cost, 2 ) ); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ( $miles_added !== null && $miles_added > 0 ) : ?>
                    <div class="evcs-stat">
                        <span class="evcs-stat-label">Range Added</span>
                        <span class="evcs-stat-value evcs-stat-value--miles">~<?php echo esc_html( $miles_added ); ?> miles</span>
                    </div>
                    <?php endif; ?>
                    <?php if ( $avg_kw !== null ) : ?>
                    <div class="evcs-stat">
                        <span class="evcs-stat-label">Avg Charge Rate</span>
                        <span class="evcs-stat-value"><?php echo esc_html( $avg_kw ); ?> kW</span>
                    </div>
                    <?php endif; ?>
                    <?php if ( $peak_kw !== null ) : ?>
                    <div class="evcs-stat">
                        <span class="evcs-stat-label">Peak Rate</span>
                        <span class="evcs-stat-value"><?php echo esc_html( $peak_kw ); ?> kW</span>
                    </div>
                    <?php endif; ?>
                    <?php if ( $base_rate > 0 ) : ?>
                    <div class="evcs-stat">
                        <span class="evcs-stat-label">Rate per kWh</span>
                        <span class="evcs-stat-value"><?php echo esc_html( $currency . number_format( $display_rate, 4 ) ); ?></span>
                        <span class="evcs-stat-footnote" style="font-size:10px; display:block; opacity:0.7;"><?php echo $markup_pct > 0 ? esc_html( $markup_pct . '% markup included' ) : 'No markup applied'; ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ( $projected_kwh !== null ) : ?>
                    <div class="evcs-stat evcs-stat--projected">
                        <span class="evcs-stat-label">Projected Total</span>
                        <span class="evcs-stat-value"><?php echo esc_html( $projected_kwh ); ?> kWh</span>
                    </div>
                    <?php endif; ?>
                    <?php if ( $projected_cost !== null ) : ?>
                    <div class="evcs-stat evcs-stat--projected">
                        <span class="evcs-stat-label">Projected Cost</span>
                        <span class="evcs-stat-value evcs-stat-value--cost"><?php echo esc_html( $currency . number_format( $projected_cost, 2 ) ); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ( $co2_saved !== null && $co2_saved > 0 ) : ?>
                    <div class="evcs-stat evcs-stat--green">
                        <span class="evcs-stat-label">CO₂ Saved vs Petrol</span>
                        <span class="evcs-stat-value evcs-stat-value--green"><?php echo esc_html( $co2_saved ); ?> kg</span>
                    </div>
                    <?php endif; ?>
                    <?php if ( $solar_pct !== null ) : ?>
                    <div class="evcs-stat evcs-stat--solar">
                        <span class="evcs-stat-label">☀️ Solar Powered</span>
                        <span class="evcs-stat-value evcs-stat-value--solar"><?php echo esc_html( $solar_pct ); ?>%</span>
                    </div>
                    <?php endif; ?>
                    <div class="evcs-stat">
                        <span class="evcs-stat-label">Started</span>
                        <span class="evcs-stat-value"><?php echo esc_html( date( 'H:i:s', $start_ts ) ); ?></span>
                    </div>
                    <?php if ( $end_ts ) : ?>
                    <div class="evcs-stat">
                        <span class="evcs-stat-label">Ended</span>
                        <span class="evcs-stat-value"><?php echo esc_html( date( 'H:i:s', $end_ts ) ); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ( ! empty( $chart_pts ) ) : ?>
                <div class="evcs-chart-wrap">
                    <canvas class="evcs-chart"
                            id="evcs-chart-<?php echo esc_attr( $chart_idx ); ?>"
                            data-points="<?php echo esc_attr( $chart_json ); ?>"
                            data-currency="<?php echo esc_attr( $currency ); ?>"
                            data-rate="<?php echo esc_attr( $rate ); ?>"
                            data-miles-per-kwh="<?php echo esc_attr( $miles_per_kwh ); ?>"
                            data-active="<?php echo $active ? 'true' : 'false'; ?>">
                    </canvas>
                </div>
                <?php endif; ?>

            </div><!-- /.evcs-card-body -->
        </div>
        <?php
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private static function format_duration( $secs ) {
        $secs = max( 0, intval( $secs ) );
        $h    = floor( $secs / 3600 );
        $m    = floor( ( $secs % 3600 ) / 60 );
        $s    = $secs % 60;
        if ( $h > 0 ) return sprintf( '%dh %02dm', $h, $m );
        if ( $m > 0 ) return sprintf( '%dm %02ds', $m, $s );
        return sprintf( '%ds', $s );
    }

    /** Thin an array of data points to at most $max evenly-spaced entries */
    private static function thin_points( $pts, $max ) {
        $count = count( $pts );
        if ( $count <= $max ) return $pts;
        $step   = $count / $max;
        $result = [];
        for ( $i = 0; $i < $max; $i++ ) {
            $result[] = $pts[ (int) round( $i * $step ) ];
        }
        return $result;
    }

    // ── Admin AJAX: delete a single session ─────────────────────────────────
    public static function ajax_delete_session() {
        check_ajax_referer( 'ha_pf_test_connection', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorised.' );

        global $wpdb;
        $session_id = sanitize_text_field( $_POST['session_id'] ?? '' );
        
        $wpdb->delete( HA_POWERFLOW_TABLE_SESSIONS, [ 'id' => $session_id ] );
        $wpdb->delete( HA_POWERFLOW_TABLE_POINTS,   [ 'session_id' => $session_id ] );

        wp_send_json_success( 'Session deleted.' );
    }

    // ── Admin AJAX: clear all completed sessions ─────────────────────────────
    public static function ajax_clear_history() {
        check_ajax_referer( 'ha_pf_test_connection', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorised.' );

        global $wpdb;

        $wpdb->query( "DELETE FROM " . HA_POWERFLOW_TABLE_SESSIONS . " WHERE status != 'active'" );
        // Delete points that don't belong to active sessions
        $wpdb->query( "DELETE FROM " . HA_POWERFLOW_TABLE_POINTS . " WHERE session_id NOT IN (SELECT id FROM " . HA_POWERFLOW_TABLE_SESSIONS . " WHERE status = 'active')" );

        wp_send_json_success( 'History cleared.' );
    }

    // ── Admin AJAX: toggle payment received ─────────────────────────────────
    public static function ajax_toggle_payment() {
        check_ajax_referer( 'ha_pf_test_connection', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorised.' );

        global $wpdb;
        $session_id = sanitize_text_field( $_POST['session_id'] ?? '' );
        
        $current = $wpdb->get_var( $wpdb->prepare( "SELECT payment_received FROM " . HA_POWERFLOW_TABLE_SESSIONS . " WHERE id = %s", $session_id ) );
        $new_val = empty( $current ) ? 1 : 0;
        
        $wpdb->update( HA_POWERFLOW_TABLE_SESSIONS, [ 'payment_received' => $new_val ], [ 'id' => $session_id ] );
        
        wp_send_json_success( [ 'payment_received' => (bool)$new_val ] );
    }

    // ── Admin AJAX: assign customer to session ───────────────────────────────
    public static function ajax_assign_customer() {
        check_ajax_referer( 'ha_pf_test_connection', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorised.' );

        global $wpdb;
        $session_id  = sanitize_text_field( $_POST['session_id']  ?? '' );
        $customer_id = sanitize_text_field( $_POST['customer_id'] ?? '' );

        $wpdb->update( HA_POWERFLOW_TABLE_SESSIONS, [ 'customer_id' => $customer_id ], [ 'id' => $session_id ] );
        
        wp_send_json_success( 'Customer assigned.' );
    }

    private static function get_active_booking_user_id( $ts ) {
        global $wpdb;
        $mysql_now = date( 'Y-m-d H:i:s', $ts );
        
        // Find a booking that covers the current timestamp
        $user_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT user_id FROM " . HA_POWERFLOW_TABLE_BOOKINGS . " 
             WHERE start_time <= %s AND end_time > %s 
             ORDER BY created_at DESC LIMIT 1",
            $mysql_now, $mysql_now
        ));
        
        return $user_id ? (int)$user_id : null;
    }
}
