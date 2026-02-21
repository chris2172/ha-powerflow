<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$delete_uploads = get_option('ha_powerflow_delete_uploads');

if ($delete_uploads === '1') {

    $upload_dir = wp_upload_dir();
    $path = $upload_dir['basedir'] . '/ha-powerflow';

    if (is_dir($path)) {
        // Recursively delete folder
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }

        rmdir($path);
    }
}
