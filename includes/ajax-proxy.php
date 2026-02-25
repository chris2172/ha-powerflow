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
// Batch proxy — fetch multiple entity states in one request.
//
// WHY A SEPARATE ENDPOINT?
// -------------------------
// The single-entity proxy is kept intact for backwards compatibility.
// The batch endpoint accepts a JSON-encoded array of entity IDs,
// fires all HA requests in parallel using wp_remote_get() with the
// 'blocking' => false trick is NOT used here — we want the responses.
// Instead we use curl_multi via WP's transport layer by issuing all
// requests before reading any responses (using a rolling approach with
// a helper), which gives true server-side parallelism.
//
// Because WordPress's wp_remote_get() is synchronous (one at a time),
// we build the curl_multi handles manually when the cURL extension is
// available, and fall back to sequential wp_remote_get() otherwise.
// Either way the browser still makes exactly ONE HTTP request.
//
// SECURITY
// ---------
// Same nonce action ('ha_pf_proxy') as the single-entity endpoint —
// the trust boundary is identical: valid nonce = trust the entity list.
// Each entity ID in the array is individually validated before use.
// Maximum 50 entities per request to prevent abuse.
// ──────────────────────────────────────────────────────────
add_action( 'wp_ajax_ha_pf_proxy_batch',        'ha_pf_ajax_proxy_batch' );
add_action( 'wp_ajax_nopriv_ha_pf_proxy_batch', 'ha_pf_ajax_proxy_batch' );

function ha_pf_ajax_proxy_batch() {

    // --------------------------------------------------
    // 1. Verify nonce — same action as single-entity proxy
    // --------------------------------------------------
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ha_pf_proxy' ) ) {
        wp_send_json_error( [ 'message' => 'Invalid or expired request.' ], 403 );
    }

    // --------------------------------------------------
    // 2. Decode and validate the entity list
    // --------------------------------------------------
    $raw = isset( $_POST['entities'] ) ? wp_unslash( $_POST['entities'] ) : '';

    if ( empty( $raw ) ) {
        wp_send_json_error( [ 'message' => 'No entities specified.' ], 400 );
    }

    $entity_map = json_decode( $raw, true );  // { key => entity_id, ... }

    if ( ! is_array( $entity_map ) || json_last_error() !== JSON_ERROR_NONE ) {
        wp_send_json_error( [ 'message' => 'Invalid entity list.' ], 400 );
    }

    // Hard cap — prevents abuse and keeps response times sane
    if ( count( $entity_map ) > 50 ) {
        wp_send_json_error( [ 'message' => 'Too many entities (max 50).' ], 400 );
    }

    // Validate every entity ID before making any HA request
    $valid_pattern = '/^[a-z_]+\.[a-zA-Z0-9_\-]+$/';
    foreach ( $entity_map as $key => $entity_id ) {
        if ( ! is_string( $entity_id ) || ! preg_match( $valid_pattern, $entity_id ) ) {
            wp_send_json_error( [ 'message' => 'Invalid entity ID: ' . esc_html( $entity_id ) ], 400 );
        }
    }

    // --------------------------------------------------
    // 3. Load HA credentials — server-side only
    // --------------------------------------------------
    $ha_url   = get_option( 'ha_powerflow_ha_url' );
    $ha_token = get_option( 'ha_powerflow_ha_token' );

    if ( empty( $ha_url ) || empty( $ha_token ) ) {
        wp_send_json_error( [ 'message' => 'Home Assistant is not configured.' ], 500 );
    }

    $ha_url    = rtrim( $ha_url, '/' );
    $ssl_verify = ( get_option( 'ha_powerflow_ssl_verify', '1' ) === '1' );

    // --------------------------------------------------
    // 4. Fetch all entities in parallel (cURL multi) or
    //    sequentially (fallback when cURL is unavailable)
    // --------------------------------------------------
    $results = function_exists( 'curl_multi_init' )
        ? ha_pf_batch_fetch_curl( $entity_map, $ha_url, $ha_token, $ssl_verify )
        : ha_pf_batch_fetch_sequential( $entity_map, $ha_url, $ha_token, $ssl_verify );

    // --------------------------------------------------
    // 5. Return one JSON object keyed by the same keys
    //    the JS sent — { key: { state, unit } | null }
    // --------------------------------------------------
    wp_send_json_success( $results );
}

/**
 * Fetch multiple HA entity states in parallel using cURL multi.
 *
 * Returns an associative array: [ key => ['state'=>..., 'unit'=>...] | null ]
 *
 * @param array  $entity_map  { dashboard_key => ha_entity_id }
 * @param string $ha_url      Base HA URL (no trailing slash)
 * @param string $ha_token    Long-lived access token (plaintext)
 * @param bool   $ssl_verify  Whether to verify SSL certificates
 * @return array
 */
function ha_pf_batch_fetch_curl( array $entity_map, string $ha_url, string $ha_token, bool $ssl_verify ): array {

    $mh      = curl_multi_init();
    $handles = [];   // key => curl handle

    foreach ( $entity_map as $key => $entity_id ) {
        $ch = curl_init();
        curl_setopt_array( $ch, [
            CURLOPT_URL            => $ha_url . '/api/states/' . rawurlencode( $entity_id ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $ha_token,
                'Content-Type: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => $ssl_verify,
            CURLOPT_SSL_VERIFYHOST => $ssl_verify ? 2 : 0,
        ] );
        curl_multi_add_handle( $mh, $ch );
        $handles[ $key ] = $ch;
    }

    // Execute all handles in parallel
    $active = null;
    do {
        $status = curl_multi_exec( $mh, $active );
        if ( $active ) {
            // Wait up to 0.5 s for activity rather than busy-looping
            curl_multi_select( $mh, 0.5 );
        }
    } while ( $active && $status === CURLM_OK );

    // Collect results
    $results = [];
    foreach ( $handles as $key => $ch ) {
        $body    = curl_multi_getcontent( $ch );
        $http_st = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $err     = curl_error( $ch );

        curl_multi_remove_handle( $mh, $ch );
        curl_close( $ch );

        if ( $err || $http_st !== 200 || empty( $body ) ) {
            if ( $err ) {
                error_log( 'HA PowerFlow batch proxy cURL error for ' . $entity_map[ $key ] . ': ' . $err );
            }
            $results[ $key ] = null;
            continue;
        }

        $data = json_decode( $body, true );
        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
            $results[ $key ] = null;
            continue;
        }

        $results[ $key ] = [
            'state' => isset( $data['state'] ) ? $data['state'] : 'unavailable',
            'unit'  => isset( $data['attributes']['unit_of_measurement'] )
                       ? $data['attributes']['unit_of_measurement']
                       : '',
        ];
    }

    curl_multi_close( $mh );

    return $results;
}

/**
 * Fallback: fetch HA entity states sequentially using wp_remote_get().
 * Used on hosts where the cURL extension is unavailable.
 *
 * @param array  $entity_map
 * @param string $ha_url
 * @param string $ha_token
 * @param bool   $ssl_verify
 * @return array
 */
function ha_pf_batch_fetch_sequential( array $entity_map, string $ha_url, string $ha_token, bool $ssl_verify ): array {

    $results    = [];
    $wp_options = [
        'headers' => [
            'Authorization' => 'Bearer ' . $ha_token,
            'Content-Type'  => 'application/json',
        ],
        'timeout'   => 10,
        'sslverify' => $ssl_verify,
    ];

    foreach ( $entity_map as $key => $entity_id ) {
        $response = wp_remote_get(
            $ha_url . '/api/states/' . rawurlencode( $entity_id ),
            $wp_options
        );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            if ( is_wp_error( $response ) ) {
                error_log( 'HA PowerFlow batch proxy error for ' . $entity_id . ': ' . $response->get_error_message() );
            }
            $results[ $key ] = null;
            continue;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
            $results[ $key ] = null;
            continue;
        }

        $results[ $key ] = [
            'state' => isset( $data['state'] ) ? $data['state'] : 'unavailable',
            'unit'  => isset( $data['attributes']['unit_of_measurement'] )
                       ? $data['attributes']['unit_of_measurement']
                       : '',
        ];
    }

    return $results;
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
