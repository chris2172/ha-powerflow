<?php
/**
 * includes/ajax-proxy.php
 *
 * Server-side proxy for Home Assistant API requests.
 *
 * The browser sends only an entity ID and a nonce.
 * This handler authenticates the request server-side using the stored
 * HA token, then returns just the state and unit to the browser.
 * The HA URL and token are never exposed to the client.
 *
 * Registered for both logged-in and logged-out users so that the
 * dashboard works on public-facing WordPress pages.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_ajax_ha_pf_proxy',        'ha_pf_ajax_proxy' );
add_action( 'wp_ajax_nopriv_ha_pf_proxy', 'ha_pf_ajax_proxy' );

function ha_pf_ajax_proxy() {

    // --------------------------------------------------
    // 1. Verify nonce
    // --------------------------------------------------
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ha_pf_proxy' ) ) {
        wp_send_json_error( [ 'message' => 'Invalid or expired request.' ], 403 );
    }

    // --------------------------------------------------
    // 2. Validate entity ID
    //    HA entity IDs are always: domain.object_id
    //    Domain: lowercase letters and underscores only
    //    Object ID: letters, numbers, underscores, hyphens
    // --------------------------------------------------
    $entity = isset( $_POST['entity'] ) ? sanitize_text_field( wp_unslash( $_POST['entity'] ) ) : '';

    if ( empty( $entity ) ) {
        wp_send_json_error( [ 'message' => 'No entity specified.' ], 400 );
    }

    if ( ! preg_match( '/^[a-z_]+\.[a-zA-Z0-9_\-]+$/', $entity ) ) {
        wp_send_json_error( [ 'message' => 'Invalid entity ID.' ], 400 );
    }

    // --------------------------------------------------
    // 3. Load HA credentials — server-side only
    // --------------------------------------------------
    $ha_url   = get_option( 'ha_powerflow_ha_url' );
    $ha_token = get_option( 'ha_powerflow_ha_token' );

    if ( empty( $ha_url ) || empty( $ha_token ) ) {
        wp_send_json_error( [ 'message' => 'Home Assistant is not configured.' ], 500 );
    }

    $ha_url = rtrim( $ha_url, '/' );

    // --------------------------------------------------
    // 4. Make server-side request to Home Assistant
    // --------------------------------------------------
    $response = wp_remote_get(
        $ha_url . '/api/states/' . rawurlencode( $entity ),
        [
            'headers' => [
                'Authorization' => 'Bearer ' . $ha_token,
                'Content-Type'  => 'application/json',
            ],
            'timeout'   => 10,
            // sslverify defaults to true; set to false only if the admin
            // has explicitly opted in via the "Allow self-signed SSL" setting.
            'sslverify' => ( get_option( 'ha_powerflow_ssl_verify', '1' ) === '1' ),
        ]
    );

    // --------------------------------------------------
    // 5. Handle transport errors
    //    Log the real error internally; return a generic message to the browser.
    // --------------------------------------------------
    if ( is_wp_error( $response ) ) {
        error_log( 'HA PowerFlow proxy error: ' . $response->get_error_message() );
        wp_send_json_error( [ 'message' => 'Could not reach Home Assistant.' ], 502 );
    }

    $status = wp_remote_retrieve_response_code( $response );
    $body   = wp_remote_retrieve_body( $response );

    if ( $status !== 200 ) {
        error_log( 'HA PowerFlow proxy: HA returned HTTP ' . $status . ' for entity ' . $entity );
        wp_send_json_error( [ 'message' => 'Home Assistant returned an error.' ], 502 );
    }

    // --------------------------------------------------
    // 6. Parse and return only the fields the browser needs
    // --------------------------------------------------
    $data = json_decode( $body, true );

    if ( json_last_error() !== JSON_ERROR_NONE ) {
        wp_send_json_error( [ 'message' => 'Invalid response from Home Assistant.' ], 502 );
    }

    wp_send_json_success( [
        'state' => isset( $data['state'] ) ? $data['state'] : 'unavailable',
        'unit'  => isset( $data['attributes']['unit_of_measurement'] )
                   ? $data['attributes']['unit_of_measurement']
                   : '',
    ] );
}
