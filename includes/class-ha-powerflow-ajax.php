<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class HA_Powerflow_Ajax {

    public static function init() {
        add_action( 'wp_ajax_ha_pf_test_connection', [ __CLASS__, 'test_connection' ] );
        add_action( 'wp_ajax_ha_powerflow_get_data', [ __CLASS__, 'get_data' ] );
        add_action( 'wp_ajax_nopriv_ha_powerflow_get_data', [ __CLASS__, 'get_data' ] );
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
}
