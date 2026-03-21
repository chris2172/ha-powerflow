<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class HA_Powerflow_Calendar_Shortcode {

    public static function init() {
        add_shortcode( 'ev_booking_calendar', [ __CLASS__, 'render' ] );
        
        // AJAX handlers
        add_action( 'wp_ajax_ha_pf_get_bookings',    [ __CLASS__, 'ajax_get_bookings' ] );
        add_action( 'wp_ajax_ha_pf_make_booking',    [ __CLASS__, 'ajax_make_booking' ] );
        add_action( 'wp_ajax_ha_pf_cancel_booking',  [ __CLASS__, 'ajax_cancel_booking' ] );
        add_action( 'wp_ajax_ha_pf_save_booking_settings', [ __CLASS__, 'ajax_save_booking_settings' ] );
        add_action( 'wp_ajax_ha_pf_download_ical',         [ __CLASS__, 'ajax_download_ical' ] );

        add_shortcode( 'my_ev_bookings', [ __CLASS__, 'render_user_dashboard' ] );

        // Ensure table exists (convenience for development)
        if ( is_admin() ) {
            self::maybe_create_table();
        }
    }

    private static function maybe_create_table() {
        global $wpdb;
        $table_name = HA_POWERFLOW_TABLE_BOOKINGS;
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
            if ( function_exists( 'ha_pf_create_tables' ) ) {
                ha_pf_create_tables();
            }
        }
    }

    public static function render( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '';
        }

        self::enqueue_assets();
        
        ob_start();
        ?>
        <div id="ha-pf-calendar-wrapper" class="ha-pf-calendar-container">
            <div class="ha-pf-calendar-header">
                <h2>📅 EV Charging Calendar</h2>
                <div class="ha-pf-calendar-nav">
                    <button type="button" id="ha-pf-prev-week" class="ha-pf-btn">&larr; Previous</button>
                    <span id="ha-pf-week-range">Current Week</span>
                    <button type="button" id="ha-pf-next-week" class="ha-pf-btn">Next &rarr;</button>
                </div>
            </div>

            <div id="ha-pf-calendar-grid" class="ha-pf-calendar-grid">
                <!-- Grid rendered via JS -->
                <div class="ha-pf-calendar-loading">Loading slots...</div>
            </div>

            <div class="ha-pf-calendar-legend">
                <span class="legend-item available"><span class="dot"></span> Available</span>
                <span class="legend-item mine"><span class="dot"></span> My Booking</span>
                <span class="legend-item booked"><span class="dot"></span> Booked</span>
            </div>
        </div>

        <!-- Booking Modal -->
        <div id="ha-pf-booking-modal" class="ha-pf-modal" style="display:none;">
            <div class="ha-pf-modal-content">
                <span class="ha-pf-close">&times;</span>
                <h3>Confirm Booking</h3>
                <p id="ha-pf-booking-details"></p>
                <div class="ha-pf-modal-field">
                    <label for="ha-pf-end-time">End Time:</label>
                    <select id="ha-pf-end-time" class="ha-pf-select"></select>
                </div>
                <div id="ha-pf-rate-display" style="margin: 10px 0; font-size: 14px; color: #64748b;">
                    Rate: <strong id="ha-pf-current-rate">—</strong>
                </div>
                <div class="ha-pf-modal-actions">
                    <button type="button" id="ha-pf-confirm-booking" class="ha-pf-btn ha-pf-btn--primary">Confirm</button>
                    <button type="button" id="ha-pf-close-modal" class="ha-pf-btn">Cancel</button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function enqueue_assets() {
        wp_enqueue_style( 'ha-pf-calendar-style', HA_POWERFLOW_URL . 'assets/css/calendar.css', [], HA_POWERFLOW_VERSION );
        wp_enqueue_style( 'ha-pf-user-dashboard', HA_POWERFLOW_URL . 'assets/css/user-dashboard.css', [], HA_POWERFLOW_VERSION );
        wp_enqueue_script( 'ha-pf-calendar-script', HA_POWERFLOW_URL . 'assets/js/calendar.js', [ 'jquery' ], HA_POWERFLOW_VERSION, true );
        
        $o = get_option( 'ha_powerflow_options', [] );
        $price = 0;
        if ( class_exists('HA_Powerflow_EV_Session') ) {
            $price = HA_Powerflow_EV_Session::get_current_cost_rate( $o );
        }

        wp_localize_script( 'ha-pf-calendar-script', 'haPfCalendar', [
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'ha_pf_calendar_nonce' ),
            'isAdmin'      => current_user_can( 'manage_options' ),
            'markupRanges'  => $o['ev_booking_markup_ranges'] ?? [],
            'bufferMins'    => intval( $o['ev_booking_buffer'] ?? 15 ),
            'intelMode'     => ! empty( $o['ev_booking_intel_mode'] ),
            'offpeakStart'  => $o['ev_booking_offpeak_start'] ?? '23:30',
            'offpeakEnd'    => $o['ev_booking_offpeak_end'] ?? '05:30',
            'peakPrice'     => floatval( $o['ev_booking_peak_price'] ?? 0.30 ),
            'gridPrice'     => $price,
            'currency'      => $o['ev_currency_symbol'] ?? '£',
            'chargeKw'      => floatval( $o['ev_max_capacity'] ?? 7 )
        ]);
    }

    // ── AJAX: Get Bookings ──────────────────────────────────────────────────
    public static function ajax_get_bookings() {
        check_ajax_referer( 'ha_pf_calendar_nonce', 'nonce' );
        
        global $wpdb;
        $start_date = sanitize_text_field( $_POST['start_date'] ?? date('Y-m-d') );
        $end_date   = date('Y-m-d', strtotime( $start_date . ' +7 days' ));
        
        $current_user_id = get_current_user_id();
        $is_admin        = current_user_can( 'manage_options' );

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT b.*, u.display_name 
             FROM " . HA_POWERFLOW_TABLE_BOOKINGS . " b
             LEFT JOIN {$wpdb->users} u ON b.user_id = u.ID
             WHERE b.start_time >= %s AND b.start_time < %s",
            $start_date, $end_date
        ), ARRAY_A );

        $bookings = [];
        foreach ( $results as $row ) {
            $booking = [
                'id'         => $row['id'],
                'start_time' => $row['start_time'],
                'end_time'   => $row['end_time'],
                'is_mine'    => ( (int)$row['user_id'] === $current_user_id ),
            ];
            
            if ( $is_admin ) {
                $booking['user_name'] = $row['display_name'];
            }
            
            $bookings[] = $booking;
        }

        wp_send_json_success([
            'bookings'   => $bookings,
            'week_range' => date('j M', strtotime($start_date)) . ' - ' . date('j M Y', strtotime($end_date . ' - 1 day'))
        ]);
    }

    // ── AJAX: Make Booking ──────────────────────────────────────────────────
    public static function ajax_make_booking() {
        check_ajax_referer( 'ha_pf_calendar_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( 'Please log in.' );

        $options = get_option( 'ha_powerflow_options', [] );
        $user_id = get_current_user_id();
        global $wpdb;

        $start_time = sanitize_text_field( $_POST['start_time'] );
        $end_time   = sanitize_text_field( $_POST['end_time'] );
        
        $start_ts = strtotime( $start_time );
        $end_ts   = strtotime( $end_time );
        $duration_hrs = ( $end_ts - $start_ts ) / 3600;

        // 1. Max Duration Check
        $max_dur = floatval( $options['ev_booking_max_duration'] ?? 4 );
        if ( $duration_hrs > $max_dur ) {
            wp_send_json_error( "Booking exceeds maximum allowed duration of $max_dur hours." );
        }

        // 2. Max Active Bookings Check
        $max_active = intval( $options['ev_booking_max_active'] ?? 2 );
        $active_count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . HA_POWERFLOW_TABLE_BOOKINGS . " WHERE user_id = %d AND start_time > %s",
            $user_id, current_time('mysql')
        ));
        if ( $active_count >= $max_active ) {
            wp_send_json_error( "You have reached the limit of $max_active active bookings." );
        }

        // 3. Overlap & Buffer Check
        $buffer_mins = intval( $options['ev_booking_buffer'] ?? 15 );
        $buffer_sec  = $buffer_mins * 60;
        
        // We check if (RequestedStart < ExistingEnd + Buffer) AND (RequestedEnd > ExistingStart - Buffer)
        // Since SQL can't easily do math on mysql dates without complex functions, 
        // we'll expand the requested range by the buffer for the query.
        $buffered_start = date( 'Y-m-d H:i:s', $start_ts - $buffer_sec );
        $buffered_end   = date( 'Y-m-d H:i:s', $end_ts + $buffer_sec );

        $overlap = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM " . HA_POWERFLOW_TABLE_BOOKINGS . " 
             WHERE (%s < end_time) AND (%s > start_time)",
            $buffered_start, $buffered_end
        ));

        if ( $overlap ) {
            wp_send_json_error( 'This overlaps with an existing booking or its required buffer zone.' );
        }

        $wpdb->insert( HA_POWERFLOW_TABLE_BOOKINGS, [
            'user_id'    => $user_id,
            'start_time' => $start_time,
            'end_time'   => $end_time
        ]);

        self::send_booking_email( $user_id, $start_time, $end_time, 'confirmed' );

        wp_send_json_success( 'Booking confirmed.' );
    }

    // ── AJAX: Cancel Booking ────────────────────────────────────────────────
    public static function ajax_cancel_booking() {
        check_ajax_referer( 'ha_pf_calendar_nonce', 'nonce' );
        global $wpdb;
        $booking_id = (int)$_POST['booking_id'];
        $b = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . HA_POWERFLOW_TABLE_BOOKINGS . " WHERE id = %d", $booking_id ) );
        if ( ! $b ) wp_send_json_error( 'Booking not found.' );

        if ( (int)$b->user_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorised.' );
        }

        $wpdb->delete( HA_POWERFLOW_TABLE_BOOKINGS, [ 'id' => $booking_id ] );
        
        self::send_booking_email( $b->user_id, $b->start_time, $b->end_time, 'cancelled' );

        wp_send_json_success( 'Booking cancelled.' );
    }

    public static function ajax_save_booking_settings() {
        check_ajax_referer( 'ha_pf_test_connection', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorised.' );

        $options = get_option( 'ha_powerflow_options', [] );
        $options['ev_booking_markup_ranges'] = json_decode( stripslashes( $_POST['markup_ranges'] ), true );
        $options['ev_booking_max_duration']   = floatval( $_POST['max_duration'] );
        $options['ev_booking_buffer']       = intval( $_POST['buffer'] );
        $options['ev_booking_max_active']   = intval( $_POST['max_active'] );
        $options['ev_booking_intel_mode']   = ! empty( $_POST['intel_mode'] );
        $options['ev_booking_offpeak_start'] = sanitize_text_field( $_POST['offpeak_start'] );
        $options['ev_booking_offpeak_end']   = sanitize_text_field( $_POST['offpeak_end'] );
        $options['ev_booking_peak_price']    = floatval( $_POST['peak_price'] );

        update_option( 'ha_powerflow_options', $options );
        wp_send_json_success( 'Settings saved successfully!' );
    }

    public static function render_user_dashboard() {
        if ( ! is_user_logged_in() ) return 'Please log in to view your bookings.';
        self::enqueue_assets();
        global $wpdb;
        $user_id = get_current_user_id();
        
        $bookings = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . HA_POWERFLOW_TABLE_BOOKINGS . " 
             WHERE user_id = %d AND end_time >= %s 
             ORDER BY start_time ASC",
            $user_id, current_time('mysql')
        ), ARRAY_A );

        ob_start();
        ?>
        <div class="ha-pf-user-dashboard">
            <h3>⚡ My Upcoming Charges</h3>
            <?php if ( empty( $bookings ) ) : ?>
                <p class="ha-pf-empty-msg">You have no upcoming bookings. <a href="#" onclick="jQuery('.ha-pf-tab-btn[data-tab=calendar]').click(); return false;">Book a slot now.</a></p>
            <?php else : ?>
                <div class="ha-pf-booking-cards">
                    <?php foreach ( $bookings as $b ) : 
                        $start = strtotime( $b['start_time'] );
                        $end   = strtotime( $b['end_time'] );
                        $nonce = wp_create_nonce( 'ha_pf_calendar_nonce' );
                        $ical_url = admin_url( "admin-ajax.php?action=ha_pf_download_ical&id={$b['id']}&nonce={$nonce}" );
                    ?>
                        <div class="ha-pf-booking-card">
                            <div class="card-date"><?php echo date('D, j M', $start); ?></div>
                            <div class="card-time"><?php echo date('H:i', $start); ?> – <?php echo date('H:i', $end); ?></div>
                            <div class="card-actions">
                                <a href="<?php echo esc_url( $ical_url ); ?>" class="ha-pf-btn-sm">🗓 iCal</a>
                                <button type="button" class="ha-pf-btn-sm ha-pf-btn-cancel" data-id="<?php echo $b['id']; ?>">✕ Cancel</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function ajax_download_ical() {
        check_ajax_referer( 'ha_pf_calendar_nonce', 'nonce' );
        $id = intval( $_GET['id'] ?? 0 );
        global $wpdb;
        $b = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . HA_POWERFLOW_TABLE_BOOKINGS . " WHERE id = %d", $id ) );
        if ( ! $b ) die('Booking not found.');

        // Verify ownership
        if ( (int)$b->user_id !== get_current_user_id() && ! current_user_can('manage_options') ) {
            die('Unauthorized.');
        }

        $start = date( 'Ymd\THis', strtotime( $b->start_time ) );
        $end   = date( 'Ymd\THis', strtotime( $b->end_time ) );
        
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="ev-booking-' . $id . '.ics"');

        echo "BEGIN:VCALENDAR\r\n";
        echo "VERSION:2.0\r\n";
        echo "PRODID:-//HA Powerflow//EV Booking//EN\r\n";
        echo "BEGIN:VEVENT\r\n";
        echo "UID:ev-booking-{$id}@chriswilmot.co.uk\r\n";
        echo "DTSTAMP:" . date('Ymd\THis\Z') . "\r\n";
        echo "DTSTART:{$start}\r\n";
        echo "DTEND:{$end}\r\n";
        echo "SUMMARY:EV Charging Session\r\n";
        echo "DESCRIPTION:Your booked EV charging session.\r\n";
        echo "END:VEVENT\r\n";
        echo "END:VCALENDAR\r\n";
        exit;
    }

    private static function send_booking_email( $user_id, $start, $end, $status ) {
        $u = get_userdata( $user_id );
        if ( ! $u ) return;

        $admin_email = get_option( 'admin_email' );
        $date_str    = date( 'l j F Y', strtotime( $start ) );
        $time_str    = date( 'H:i', strtotime( $start ) ) . ' – ' . date( 'H:i', strtotime( $end ) );
        
        $subject = "[EV Charging] Booking " . ucfirst( $status ) . " - " . $date_str;
        
        $message  = "Hello " . $u->display_name . ",\r\n\r\n";
        $message .= "Your EV charging booking has been " . $status . ".\r\n\r\n";
        $message .= "Details:\r\n";
        $message .= "Date: " . $date_str . "\r\n";
        $message .= "Time: " . $time_str . "\r\n\r\n";
        $message .= "Thank you,\r\n";
        $message .= get_bloginfo( 'name' );

        // Send to User
        wp_mail( $u->user_email, $subject, $message );

        // Notify Admin
        $admin_msg = "A booking for " . $u->display_name . " has been " . $status . ".\r\n" . $date_str . " @ " . $time_str;
        wp_mail( $admin_email, $subject, $admin_msg );
    }
}
