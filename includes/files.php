<?php
if (!defined('ABSPATH')) {
    exit;
}

function calsys_private_attachment($field, $mimes) {
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return array('file_name' => '', 'file_type' => '', 'file_data' => '');
    }
    $file = $_FILES[$field];
    if (!isset($file['error'], $file['tmp_name'], $file['name']) || is_array($file['error']) ||
        $file['error'] !== UPLOAD_ERR_OK || !is_string($file['tmp_name']) || !is_string($file['name']) || !is_uploaded_file($file['tmp_name'])) {
        return new WP_Error('upload', __('The attachment could not be uploaded. Check your server upload limit and try again.', 'community-calendar-study-rooms'));
    }
    $size = filesize($file['tmp_name']);
    if ($size === false || $size < 1 || $size > 5 * MB_IN_BYTES) {
        return new WP_Error('size', __('Attachments must be nonempty and no larger than 5 MB.', 'community-calendar-study-rooms'));
    }
    $name = sanitize_file_name($file['name']);
    $checked = wp_check_filetype_and_ext($file['tmp_name'], $name, $mimes);
    if (!$checked['ext'] || !$checked['type'] || strlen($name) > 200) {
        return new WP_Error('type', __('Use a supported attachment type with a filename no longer than 200 bytes.', 'community-calendar-study-rooms'));
    }
    $data = file_get_contents($file['tmp_name']);
    if ($data === false) {
        error_log('CommonCalendar: unable to read private attachment.');
        return new WP_Error('read', __('The attachment could not be read. Please contact the site administrator.', 'community-calendar-study-rooms'));
    }
    // Keep files out of public uploads; each module checks download access.
    return array('file_name' => $name, 'file_type' => $checked['type'], 'file_data' => base64_encode($data));
}

function calsys_private_file_download($name, $encoded) {
    $data = base64_decode($encoded, true);
    if ($data === false) {
        error_log('CommonCalendar: invalid stored private attachment.');
        wp_die(esc_html__('The attachment is damaged. Please contact the site administrator.', 'community-calendar-study-rooms'), '', array('response' => 500));
    }
    nocache_headers();
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename=\"attachment\"; filename*=UTF-8''" . rawurlencode($name));
    header('Content-Length: ' . strlen($data));
    echo $data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit;
}
