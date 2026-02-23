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

// ──────────────────────────────────────────────────────────
// Test connection — admin-only AJAX handler.
// Hits HA's /api/ root which returns {"message":"API running."}
// and optionally the HA version in the headers/body.
// ──────────────────────────────────────────────────────────
add_action( 'wp_ajax_ha_pf_test_connection', 'ha_pf_ajax_test_connection' );

function ha_pf_ajax_test_connection() {

    // 1. Nonce + capability
    if ( ! check_ajax_referer( 'ha_pf_test_connection', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => 'Invalid security token. Please refresh the page.' ] );
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Insufficient permissions.' ] );
    }

    // 2. Use URL/token from POST if provided (lets us test unsaved values),
    //    falling back to what is saved in the database.
    $ha_url = isset( $_POST['ha_url'] ) ? esc_url_raw( wp_unslash( $_POST['ha_url'] ) ) : '';
    $ha_token = isset( $_POST['ha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['ha_token'] ) ) : '';

    // If the token field was left blank the admin is testing with the saved token
    if ( empty( $ha_url ) )   $ha_url   = get_option( 'ha_powerflow_ha_url', '' );
    if ( empty( $ha_token ) ) $ha_token = get_option( 'ha_powerflow_ha_token', '' );

    if ( empty( $ha_url ) ) {
        wp_send_json_error( [ 'message' => 'No Home Assistant URL configured.' ] );
    }
    if ( empty( $ha_token ) ) {
        wp_send_json_error( [ 'message' => 'No Home Assistant token configured.' ] );
    }

    $ha_url = rtrim( $ha_url, '/' );

    // 3. Hit the HA API root — fast, lightweight, confirms auth works
    $response = wp_remote_get(
        $ha_url . '/api/',
        [
            'headers' => [
                'Authorization' => 'Bearer ' . $ha_token,
                'Content-Type'  => 'application/json',
            ],
            'timeout'   => 8,
            'sslverify' => ( get_option( 'ha_powerflow_ssl_verify', '1' ) === '1' ),
        ]
    );

    // 4. Transport error (DNS failure, timeout, SSL error, etc.)
    if ( is_wp_error( $response ) ) {
        $msg = $response->get_error_message();
        // Strip cURL prefix for cleaner display
        $msg = preg_replace( '/^cURL error \d+:\s*/i', '', $msg );
        wp_send_json_error( [ 'message' => $msg ] );
    }

    $status = wp_remote_retrieve_response_code( $response );
    $body   = wp_remote_retrieve_body( $response );

    // 5. Auth failure
    if ( $status === 401 ) {
        wp_send_json_error( [ 'message' => 'Unauthorised (401) — check your Long-Lived Access Token.' ] );
    }

    // 6. Any other non-200
    if ( $status !== 200 ) {
        wp_send_json_error( [ 'message' => 'Home Assistant returned HTTP ' . intval( $status ) . '.' ] );
    }

    // 7. Parse response — HA /api/ returns {"message":"API running."}
    //    HA 2023.x+ also returns version info via X-HA-Version header
    $data       = json_decode( $body, true );
    $ha_version = wp_remote_retrieve_header( $response, 'x-ha-version' );

    if ( ! $data || ! isset( $data['message'] ) ) {
        wp_send_json_error( [ 'message' => 'Unexpected response — is this a Home Assistant instance?' ] );
    }

    $detail = $ha_version ? 'Home Assistant ' . sanitize_text_field( $ha_version ) : 'Home Assistant';
    wp_send_json_success( [ 'message' => 'Connected successfully', 'detail' => $detail ] );
}
