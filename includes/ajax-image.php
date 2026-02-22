<?php
/**
 * includes/ajax-image.php
 *
 * Handles copying a media library image into the plugin's own upload
 * directory so it persists independently of the media library.
 *
 * Only registered for logged-in users (wp_ajax_) — there is no
 * nopriv version because this requires manage_options capability.
 * Only loaded when is_admin() is true (see ha-powerflow.php).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_ajax_ha_pf_copy_image', 'ha_pf_ajax_copy_image' );

function ha_pf_ajax_copy_image() {

    // --------------------------------------------------
    // 1. Verify nonce
    // --------------------------------------------------
    check_ajax_referer( 'ha_pf_copy_image', 'nonce' );

    // --------------------------------------------------
    // 2. Check capability
    // --------------------------------------------------
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied.' );
    }

    // --------------------------------------------------
    // 3. Validate attachment ID
    // --------------------------------------------------
    if ( ! isset( $_POST['attachment_id'] ) ) {
        wp_send_json_error( 'No attachment ID provided.' );
    }

    $attachment_id = absint( $_POST['attachment_id'] );
    if ( ! $attachment_id ) {
        wp_send_json_error( 'Invalid attachment ID.' );
    }

    // --------------------------------------------------
    // 4. Verify attachment exists and belongs to this site
    // --------------------------------------------------
    $file_path = get_attached_file( $attachment_id );

    if ( ! $file_path || ! file_exists( $file_path ) ) {
        wp_send_json_error( 'Attachment file not found.' );
    }

    // Only allow image file types
    $mime = mime_content_type( $file_path );
    if ( strpos( $mime, 'image/' ) !== 0 ) {
        wp_send_json_error( 'Only image files are allowed.' );
    }

    // --------------------------------------------------
    // 5. Prepare destination directory
    // --------------------------------------------------
    $upload   = wp_upload_dir();
    $dest_dir = $upload['basedir'] . '/ha-powerflow';

    if ( ! file_exists( $dest_dir ) ) {
        wp_mkdir_p( $dest_dir );
    }

    // Use only the filename — no path traversal possible
    $filename  = basename( $file_path );
    $dest_path = $dest_dir . '/' . $filename;

    // --------------------------------------------------
    // 6. Copy and save
    // --------------------------------------------------
    if ( ! copy( $file_path, $dest_path ) ) {
        wp_send_json_error( 'File copy failed.' );
    }

    $new_url = $upload['baseurl'] . '/ha-powerflow/' . $filename;
    update_option( 'ha_powerflow_image_url', esc_url_raw( $new_url ) );

    wp_send_json_success( [ 'url' => $new_url ] );
}
