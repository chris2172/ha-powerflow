<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class HA_Powerflow_Ajax {

    public static function init() {
        add_action( 'wp_ajax_ha_pf_test_connection', [ __CLASS__, 'test_connection' ] );
        add_action( 'wp_ajax_ha_powerflow_get_data', [ __CLASS__, 'get_data' ] );
        add_action( 'wp_ajax_nopriv_ha_powerflow_get_data', [ __CLASS__, 'get_data' ] );
        
        add_action( 'wp_ajax_ha_powerflow_restore_snapshot',  [ __CLASS__, 'restore_snapshot' ] );
        add_action( 'wp_ajax_ha_powerflow_create_snapshot',   [ __CLASS__, 'manual_snapshot' ] );
        add_action( 'wp_ajax_ha_powerflow_download_snapshot', [ __CLASS__, 'download_snapshot' ] );
        add_action( 'wp_ajax_ha_powerflow_upload_snapshot',   [ __CLASS__, 'upload_snapshot' ] );
        add_action( 'wp_ajax_ha_powerflow_discover_entities', [ __CLASS__, 'discover_entities' ] );
    }

    public static function test_connection() {
        check_ajax_referer( 'ha_pf_test_connection', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorised.' );
        }

        $ha_url   = rtrim( sanitize_text_field( $_POST['ha_url']   ?? '' ), '/' );
        $ha_token = sanitize_text_field( $_POST['ha_token'] ?? '' );

        if ( ! $ha_url || ! $ha_token ) {
            wp_send_json_error( 'URL and token are required.' );
        }

        $response = wp_remote_get( $ha_url . '/api/', [
            'headers' => [
                'Authorization' => 'Bearer ' . $ha_token,
                'Content-Type'  => 'application/json',
            ],
            'timeout'   => 8,
            'sslverify' => true,
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( 'Could not reach Home Assistant: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 200 && isset( $body['message'] ) ) {
            wp_send_json_success( 'Connected successfully — ' . esc_html( $body['message'] ) );
        } elseif ( $code === 401 ) {
            wp_send_json_error( 'Authorisation failed — check your Access Token.' );
        } else {
            wp_send_json_error( 'Unexpected response (HTTP ' . $code . '). Check your URL.' );
        }
    }

    public static function get_data() {
        check_ajax_referer( 'ha_powerflow_nonce', 'nonce' );

        $o = get_option( 'ha_powerflow_options', [] );
        $ha_url   = isset( $o['ha_url'] )   ? rtrim( $o['ha_url'], '/' ) : '';
        $ha_token = isset( $o['ha_token'] ) ? $o['ha_token']             : '';

        if ( ! $ha_url || ! $ha_token ) {
            wp_send_json_error( 'HA Powerflow is not configured. Please visit Settings → HA Powerflow.' );
        }

        $headers = [
            'Authorization' => 'Bearer ' . $ha_token,
            'Content-Type'  => 'application/json',
        ];

        $sensors = [
            'grid_power'         => $o['grid_power']         ?? '',
            'load_power'         => $o['load_power']         ?? '',
            'grid_energy'        => $o['grid_energy']        ?? '',
            'grid_energy_out'    => $o['grid_energy_out']    ?? '',
            'grid_price_in'      => $o['grid_price_in']      ?? '',
            'grid_price_out'     => $o['grid_price_out']     ?? '',
            'load_energy'        => $o['load_energy']        ?? '',
            'pv_power'           => $o['pv_power']           ?? '',
            'pv_energy'          => $o['pv_energy']          ?? '',
            'battery_power'      => $o['battery_power']      ?? '',
            'battery_in_energy'  => $o['battery_in_energy']  ?? '',
            'battery_out_energy' => $o['battery_out_energy'] ?? '',
            'battery_soc'        => $o['battery_soc']        ?? '',
            'ev_power'           => $o['ev_power']           ?? '',
            'ev_soc'             => $o['ev_soc']             ?? '',
            'heatpump_power'     => $o['heatpump_power']     ?? '',
            'heatpump_energy'    => $o['heatpump_energy']    ?? '',
            'heatpump_efficiency'=> $o['heatpump_efficiency']?? '',
            'weather'            => $o['weather_entity']     ?? '',
        ];

        $template_keys = [];
        $data = [];

        foreach ( $sensors as $key => $entity_id ) {
            if ( ! $entity_id ) {
                $data[ $key ] = [ 'state' => 'N/A', 'unit' => '' ];
            } else {
                $template_keys[] = sprintf(
                    '"%s": {"state": states("%s"), "unit": state_attr("%s", "unit_of_measurement")}',
                    $key,
                    $entity_id,
                    $entity_id
                );
            }
        }

        // Add custom entities
        $custom = ! empty( $o['custom_entities'] ) && is_array( $o['custom_entities'] ) ? $o['custom_entities'] : [];
        foreach ( $custom as $index => $item ) {
            $entity_id = $item['entity'] ?? '';
            if ( ! $entity_id ) continue;

            $template_keys[] = sprintf(
                '"custom_%d": {"state": states("%s"), "unit": state_attr("%s", "unit_of_measurement")}',
                $index,
                $entity_id,
                $entity_id
            );
        }

        if ( ! empty( $template_keys ) ) {
            $template_content = sprintf(
                '{%% set data = {%s} %%}{{ data | to_json }}',
                implode( ',', $template_keys )
            );

            $response = wp_remote_post( $ha_url . '/api/template', [
                'headers'   => $headers,
                'body'      => json_encode( [ 'template' => $template_content ] ),
                'timeout'   => 10,
                'sslverify' => true,
            ] );

            if ( is_wp_error( $response ) ) {
                wp_send_json_error( 'Could not reach Home Assistant: ' . $response->get_error_message() );
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( is_array( $body ) ) {
                // Return data formatted the same way JS expects.
                foreach ( $body as $k => $v ) {
                    $data[ $k ] = [
                        'state' => isset( $v['state'] ) ? $v['state'] : 'N/A',
                        'unit'  => ( isset( $v['unit'] ) && $v['unit'] !== null ) ? $v['unit'] : '',
                    ];
                }
            } else {
                wp_send_json_error( 'Invalid response from Home Assistant.' );
            }
        }

        wp_send_json_success( $data );
    }

    public static function discover_entities() {
        check_ajax_referer( 'ha_pf_test_connection', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorised.' );

        $o = get_option( 'ha_powerflow_options', [] );
        $ha_url   = isset( $o['ha_url'] )   ? rtrim( $o['ha_url'], '/' ) : '';
        $ha_token = isset( $o['ha_token'] ) ? $o['ha_token']             : '';

        if ( ! $ha_url || ! $ha_token ) wp_send_json_error( 'Not configured.' );

        $response = wp_remote_get( $ha_url . '/api/states', [
            'headers' => [ 'Authorization' => 'Bearer ' . $ha_token ],
            'timeout' => 15
        ] );

        if ( is_wp_error( $response ) ) wp_send_json_error( $response->get_error_message() );

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $body ) ) wp_send_json_error( 'Invalid HA response.' );

        $findings = [];
        $keywords = [ 'power', 'energy', 'soc', 'battery', 'solar', 'grid' ];

        foreach ( $body as $state ) {
            $eid = $state['entity_id'] ?? '';
            $domain = explode( '.', $eid )[0];
            if ( $domain !== 'sensor' ) continue;

            $found = false;
            foreach ( $keywords as $kw ) {
                if ( stripos( $eid, $kw ) !== false ) {
                    $found = true;
                    break;
                }
            }

            if ( $found ) {
                $findings[] = [
                    'entity_id'  => $eid,
                    'attributes' => $state['attributes'] ?? []
                ];
            }
            if ( count( $findings ) > 50 ) break; // Limit results
        }

        wp_send_json_success( $findings );
    }

    public static function restore_snapshot() {
        check_ajax_referer( 'ha_pf_test_connection', 'nonce' ); // Reusing nonce for admin
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorised.' );

        $filename = sanitize_file_name( $_POST['filename'] ?? '' );
        $path = HA_POWERFLOW_CONFIG_DIR . $filename;

        if ( ! $filename || ! file_exists( $path ) ) {
            wp_send_json_error( 'Snapshot file not found.' );
        }

        $content = file_get_contents( $path );
        $data = self::parse_yaml( $content );

        if ( empty( $data ) ) {
            wp_send_json_error( 'Invalid or empty snapshot file.' );
        }

        update_option( 'ha_powerflow_options', $data );
        wp_send_json_success( 'Settings restored successfully from ' . $filename );
    }

    public static function manual_snapshot() {
        check_ajax_referer( 'ha_pf_test_connection', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorised.' );

        $o = get_option( 'ha_powerflow_options', [] );
        ha_pf_create_snapshot( $o );

        wp_send_json_success( 'Manual snapshot created successfully.' );
    }

    public static function download_snapshot() {
        check_ajax_referer( 'ha_pf_test_connection', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorised.' );

        $filename = sanitize_file_name( $_GET['filename'] ?? '' );
        $path = HA_POWERFLOW_CONFIG_DIR . $filename;

        if ( ! $filename || ! file_exists( $path ) ) {
            wp_die( 'File not found.' );
        }

        while ( ob_get_level() ) {
            ob_end_clean();
        }

        header( 'Content-Type: application/octet-stream' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Expires: 0' );
        header( 'Cache-Control: must-revalidate' );
        header( 'Pragma: public' );
        header( 'Content-Length: ' . filesize( $path ) );
        readfile( $path );
        exit;
    }

    public static function upload_snapshot() {
        check_ajax_referer( 'ha_pf_test_connection', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorised.' );

        if ( ! isset( $_FILES['snapshot'] ) ) {
            wp_send_json_error( 'No file uploaded.' );
        }

        $content = file_get_contents( $_FILES['snapshot']['tmp_name'] );
        $data = self::parse_yaml( $content );

        if ( empty( $data ) ) {
            wp_send_json_error( 'Invalid or empty backup file.' );
        }

        update_option( 'ha_powerflow_options', $data );
        wp_send_json_success( 'Backup uploaded and applied successfully.' );
    }

    private static function parse_yaml( $content ) {
        $lines = explode( "\n", $content );
        $data = [];
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( ! $line || $line[0] === '#' ) continue;
            
            $pos = strpos( $line, ':' );
            if ( $pos === false ) continue;
            
            $key = trim( substr( $line, 0, $pos ) );
            $val = trim( substr( $line, $pos + 1 ) );
            
            // Remove surrounding quotes if present
            if ( ( $val[0] === '"' && substr( $val, -1 ) === '"' ) || 
                 ( $val[0] === "'" && substr( $val, -1 ) === "'" ) ) {
                $val = substr( $val, 1, -1 );
            }
            // Unescape quotes
            $val = str_replace( '\"', '"', $val );
            
            $data[ $key ] = $val;
        }
        return $data;
    }
}
