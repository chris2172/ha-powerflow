add_action('wp_ajax_ha_powerflow_copy_image', 'ha_powerflow_copy_image');

function ha_powerflow_copy_image() {

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    if (!isset($_POST['attachment_id'])) {
        wp_send_json_error('No attachment ID');
    }

    $attachment_id = intval($_POST['attachment_id']);
    $file_path = get_attached_file($attachment_id);

    if (!file_exists($file_path)) {
        wp_send_json_error('File not found');
    }

    // Destination folder
    $upload_dir = wp_upload_dir();
    $dest_dir = $upload_dir['basedir'] . '/ha-powerflow';

    if (!file_exists($dest_dir)) {
        wp_mkdir_p($dest_dir);
    }

    // New filename
    $filename = basename($file_path);
    $dest_path = $dest_dir . '/' . $filename;

    // Copy file
    copy($file_path, $dest_path);

    // Build new URL
    $new_url = $upload_dir['baseurl'] . '/ha-powerflow/' . $filename;

    // Save to settings
    update_option('ha_powerflow_image_url', $new_url);

    wp_send_json_success(['url' => $new_url]);
}
