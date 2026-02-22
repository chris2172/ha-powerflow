<?php
if (!defined('ABSPATH')) exit;

/**
 * SECTION: HA Proxy AJAX Handler
 *
 * Receives a request from the browser containing only an entity ID.
 * Makes the authenticated request to Home Assistant server-side,
 * so the HA token is never exposed in page source or browser requests.
 *
 * Registered for both logged-in (wp_ajax_) and logged-out (wp_ajax_nopriv_)
 * users so the dashboard works on public-facing pages.
 */
function ha_powerflow_ajax_proxy() {

    /* SECTION: Verify Nonce */
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ha_powerflow_proxy')) {
        wp_send_json_error(['message' => 'Invalid nonce'], 403);
        return;
    }

    /* SECTION: Validate Entity ID */
    $entity = isset($_POST['entity']) ? sanitize_text_field($_POST['entity']) : '';

    if (empty($entity)) {
        wp_send_json_error(['message' => 'No entity specified'], 400);
        return;
    }

    // Entity IDs must match the pattern: domain.entity_name
    // e.g. sensor.grid_power, switch.ev_charger
    if (!preg_match('/^[a-z_]+\.[a-zA-Z0-9_]+$/', $entity)) {
        wp_send_json_error(['message' => 'Invalid entity ID'], 400);
        return;
    }

    /* SECTION: Load HA Credentials (server-side only) */
    $ha_url   = get_option('ha_powerflow_ha_url');
    $ha_token = get_option('ha_powerflow_ha_token');

    if (empty($ha_url) || empty($ha_token)) {
        wp_send_json_error(['message' => 'Home Assistant not configured'], 500);
        return;
    }

    $ha_url = rtrim($ha_url, '/');

    /* SECTION: Make Server-Side Request to Home Assistant */
    $response = wp_remote_get(
        $ha_url . '/api/states/' . $entity,
        [
            'headers' => [
                'Authorization' => 'Bearer ' . $ha_token,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 10,
            'sslverify' => true,
        ]
    );

    /* SECTION: Handle Request Errors */
    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Could not reach Home Assistant: ' . $response->get_error_message()], 502);
        return;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body        = wp_remote_retrieve_body($response);

    if ($status_code !== 200) {
        wp_send_json_error(['message' => 'Home Assistant returned status ' . $status_code], $status_code);
        return;
    }

    /* SECTION: Parse + Return HA Response */
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error(['message' => 'Invalid JSON from Home Assistant'], 502);
        return;
    }

    // Only return what the frontend actually needs — state and unit
    wp_send_json_success([
        'state' => $data['state'] ?? 'unavailable',
        'unit'  => $data['attributes']['unit_of_measurement'] ?? '',
    ]);
}

add_action('wp_ajax_ha_powerflow_proxy',        'ha_powerflow_ajax_proxy');
add_action('wp_ajax_nopriv_ha_powerflow_proxy', 'ha_powerflow_ajax_proxy');
