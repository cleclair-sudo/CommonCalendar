<?php
if (!defined('ABSPATH')) {
    exit;
}

function calsys_save_room() {
    calsys_require_staff();
    check_admin_referer('calsys_save_room');
    global $wpdb;

    $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $capacity = absint($_POST['capacity'] ?? 0);
    $room_id = absint($_POST['room_id'] ?? 0);
    $active = isset($_POST['active']) ? 1 : 0;
    if ($name === '' || strlen($name) > 200 || $capacity < 1) {
        calsys_admin_redirect('calsys-settings', 'invalid');
    }
    $data = array('name' => $name, 'capacity' => $capacity, 'active' => $active);
    $formats = array('%s', '%d', '%d');
    if (isset($_POST['color'])) {
        $color = wp_unslash($_POST['color']);
        if (!is_string($color) || ($color !== '' && !sanitize_hex_color($color))) {
            calsys_admin_redirect('calsys-settings', 'invalid');
        }
        $data['color'] = $color === '' ? '' : sanitize_hex_color($color);
        $formats[] = '%s';
    }
    if ($room_id) {
        $updated = $wpdb->update(calsys_table('rooms'), $data, array('id' => $room_id), $formats, array('%d'));
        if ($updated === false || !$wpdb->get_var($wpdb->prepare("SELECT id FROM " . calsys_table('rooms') . " WHERE id = %d", $room_id))) {
            calsys_admin_redirect('calsys-settings', 'invalid');
        }
    } else {
        if (!$wpdb->insert(calsys_table('rooms'), $data, $formats)) {
            calsys_admin_redirect('calsys-settings', 'invalid');
        }
    }
    calsys_admin_redirect('calsys-settings', 'saved');
}
add_action('admin_post_calsys_save_room', 'calsys_save_room');

/** Validates and saves a patron room request or an immediately confirmed staff booking. */
function calsys_save_booking($staff_booking = false, $frontend = false) {
    global $wpdb;

    if ($staff_booking) {
        calsys_require_staff();
        check_admin_referer(
            $frontend ? 'calsys_front_staff_booking' : 'calsys_staff_booking',
            $frontend ? '_wpnonce_booking' : '_wpnonce'
        );
        $staff_initials = calsys_validate_staff_initials($_POST['staff_initials'] ?? null);
        if ($staff_initials === false) {
            calsys_action_redirect($frontend, 'calsys-rooms', 'invalid_room_request');
        }
    } else {
        check_admin_referer('calsys_request_room');
    }
    $request_reason = '';
    if (!$staff_booking) {
        $raw_reason = wp_unslash($_POST['request_reason'] ?? '');
        if (!is_string($raw_reason)) {
            calsys_action_redirect($frontend, 'calsys-rooms', 'invalid_room_request');
        }
        $request_reason = sanitize_textarea_field($raw_reason);
        if (strlen($request_reason) > 1000) {
            calsys_action_redirect($frontend, 'calsys-rooms', 'invalid_room_request');
        }
    }
    $booking_id = $frontend && $staff_booking ? absint($_POST['booking_id'] ?? 0) : 0;
    $equipment = calsys_posted_room_equipment();
    if ($equipment === false) {
        calsys_action_redirect($frontend, 'calsys-rooms', 'invalid_room_request');
    }
    if ($equipment === 'disabled') {
        calsys_action_redirect($frontend, 'calsys-rooms', 'equipment_disabled');
    }
    $booking = calsys_valid_booking_input(
        $_POST['room_id'] ?? 0,
        $_POST['requester_name'] ?? '',
        $_POST['requester_email'] ?? '',
        $_POST['booking_date'] ?? '',
        $_POST['starts_at'] ?? '',
        $_POST['ends_at'] ?? '',
        $staff_booking
    );
    if (!is_array($booking)) {
        $error_code = $booking;
        if (!$staff_booking && $error_code === 'advance_booking_limit') {
            calsys_action_redirect($frontend, 'calsys-rooms', 'advance_booking_limit');
        }
        calsys_action_redirect($frontend, 'calsys-rooms', $error_code);
    }
    if ($wpdb->query('START TRANSACTION') === false) {
        calsys_action_redirect($frontend, 'calsys-rooms', 'transaction_failed');
    }
    $locked_room = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM " . calsys_table('rooms') . " WHERE id = %d AND active = 1 FOR UPDATE",
        $booking['room_id']
    ));
    if (!$locked_room) {
        $wpdb->query('ROLLBACK');
        calsys_action_redirect($frontend, 'calsys-rooms', 'room_lock_failed');
    }
    $has_conflict = calsys_booking_conflict($booking['room_id'], $booking['date'], $booking['start'], $booking['end'], $booking_id);
    if ($has_conflict === null) {
        $wpdb->query('ROLLBACK');
        calsys_action_redirect($frontend, 'calsys-rooms', 'conflict_check_failed');
    }
    if ($has_conflict && (!$staff_booking || ($_POST['confirm_room_conflict'] ?? '') !== '1')) {
        $wpdb->query('ROLLBACK');
        calsys_action_redirect($frontend, 'calsys-rooms', 'room_conflict');
    }
    if (!$staff_booking) {
        $has_confirmed_booking = calsys_has_confirmed_booking_conflict($booking['room_id'], $booking['date'], $booking['start'], $booking['end'], $booking_id);
        if ($has_confirmed_booking === null) {
            $wpdb->query('ROLLBACK');
            calsys_action_redirect($frontend, 'calsys-rooms', 'conflict_check_failed');
        }
        if ($has_confirmed_booking) {
            $wpdb->query('ROLLBACK');
            calsys_action_redirect($frontend, 'calsys-rooms', 'room_already_booked', array(), true);
        }
    }

    $booking_data = array(
        'room_id' => $booking['room_id'],
        'requester_name' => $booking['name'],
        'requester_email' => $booking['email'],
        'booking_date' => $booking['date'],
        'starts_at' => $booking['start'],
        'ends_at' => $booking['end'],
    );
    if ($staff_booking) {
        $booking_data['staff_initials'] = $staff_initials;
    }
    if ($staff_booking && !$booking_id) {
        $booking_data['source_type'] = 'private';
        $booking_data['source_id'] = 0;
    }
    if ($booking_id) {
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, status FROM " . calsys_table('bookings') . " WHERE id = %d FOR UPDATE",
            $booking_id
        ));
        if (!$existing || !in_array($existing->status, array('pending', 'approved'), true)) {
            $wpdb->query('ROLLBACK');
            calsys_frontend_redirect('invalid_room_request');
        }
        $saved = $wpdb->update(
            calsys_table('bookings'),
            $booking_data,
            array('id' => $booking_id)
        );
        $booking_status = $existing->status;
        $notice = 'room_booking_saved';
    } else {
        $approval_required = (bool) calsys_get_settings()['room_approval_required'];
        $booking_data['status'] = $staff_booking || !$approval_required ? 'approved' : 'pending';
        $booking_status = $booking_data['status'];
        $booking_data['request_reason'] = $request_reason;
        $booking_data['created_at'] = current_time('mysql');
        $saved = $wpdb->insert(calsys_table('bookings'), $booking_data);
        $notice = $staff_booking || !$approval_required ? 'room_booking_saved' : 'room_request_sent';
    }
    if ($saved === false) {
        $wpdb->query('ROLLBACK');
        calsys_action_redirect($frontend, 'calsys-rooms', 'invalid_room_request');
    }
    if (!$booking_id) {
        $booking_id = (int) $wpdb->insert_id;
    }
    $equipment_result = calsys_sync_equipment(
        'room',
        $booking_id,
        $equipment,
        $booking['date'] . ' ' . $booking['start'],
        $booking['date'] . ' ' . $booking['end'],
        $booking_status
    );
    if ($equipment_result !== 'saved' || $wpdb->query('COMMIT') === false) {
        $wpdb->query('ROLLBACK');
        calsys_action_redirect($frontend, 'calsys-rooms', $equipment_result === 'conflict' ? 'equipment_conflict' : 'invalid_room_request');
    }
    if (!$staff_booking) {
        $settings = calsys_get_settings();
        $booking_status = $booking_data['status'] ?? '';
        $status_text = $booking_status === 'approved'
            ? __('confirmed', 'community-calendar-study-rooms')
            : __('awaiting staff approval', 'community-calendar-study-rooms');
        $start_label = mysql2date('g:i A', $booking['date'] . ' ' . $booking['start']);
        $end_label = mysql2date('g:i A', $booking['date'] . ' ' . $booking['end']);
        $booking_summary = sprintf(
            /* translators: 1: requester name, 2: requester email, 3: room, 4: date, 5: start time, 6: end time, 7: booking status, 8: reason */
            __("Requester Name: %1\$s\nRequester Email: %2\$s\nRoom: %3\$s\nDate: %4\$s\nTime: %5\$s to %6\$s\nStatus: %7\$s\nReason: %8\$s", 'community-calendar-study-rooms'),
            $booking['name'],
            $booking['email'],
            $booking['room_name'],
            $booking['date'],
            $start_label,
            $end_label,
            $status_text,
            $request_reason !== '' ? $request_reason : __('Not provided', 'community-calendar-study-rooms')
        );
        $email_context = array('name' => $booking['name'], 'email' => $booking['email'], 'room' => $booking['room_name'], 'date' => $booking['date'],
            'start' => $start_label, 'end' => $end_label);
        if ($settings['notifications']['notify_staff_room_requests'] && $settings['notifications']['room_request_recipients'] !== false) {
            $recipients = $settings['notifications']['room_request_recipients'] ?: array($settings['notifications']['staff_email']);
            $room_review = array();
            if ($booking_status === 'pending' && $settings['notifications']['room_email_review_enabled']) {
                $room_review = calsys_create_room_email_review_links($booking_id, $settings['notifications']['room_email_review_days']);
                if ($room_review === false) {
                    $room_review = array('unavailable' => true);
                }
            }
            foreach ($recipients as $recipient) {
                calsys_send_email(
                    $recipient,
                    __('New room booking request', 'community-calendar-study-rooms'),
                    $booking_summary,
                    'room_request_staff',
                    $email_context,
                    $room_review
                );
            }
        }
        if ($settings['notifications']['email_room_requester']) {
            calsys_send_email(
                $booking['email'],
                $booking_status === 'approved'
                    ? __('Room booking confirmed', 'community-calendar-study-rooms')
                    : __('Room request received', 'community-calendar-study-rooms'),
                $booking_status === 'approved'
                    ? sprintf(__("Hello %1\$s, your room booking is confirmed.\n\n%2\$s", 'community-calendar-study-rooms'), $booking['name'], $booking_summary)
                    : sprintf(__("Hello %1\$s, we received your room request.\n\n%2\$s", 'community-calendar-study-rooms'), $booking['name'], $booking_summary),
                $booking_status === 'approved' ? 'room_booking_confirmed' : 'room_request_received',
                $email_context
            );
        }
    }
    if ($staff_booking && !calsys_room_is_within_hours($booking['date'], substr($booking['start'], 0, 5), substr($booking['end'], 0, 5))) {
        $notice = 'room_booking_saved_outside_hours';
    } elseif (!$frontend) {
        $notice = $staff_booking ? 'booking_saved' : 'room_request_sent';
    }
    calsys_action_redirect($frontend, 'calsys-rooms', $notice);
}

function calsys_staff_booking() {
    calsys_save_booking(true);
}
add_action('admin_post_calsys_staff_booking', 'calsys_staff_booking');

function calsys_public_room_booking() {
    $frontend = !empty($_POST['calsys_frontend']);
    $staff_booking = $frontend && !empty($_POST['staff_booking']);
    if ($staff_booking && !empty($_POST['public_event'])) {
        if (!absint($_POST['room_id'] ?? 0)) {
            calsys_frontend_redirect('invalid');
        }
        $date = sanitize_text_field(wp_unslash($_POST['booking_date'] ?? ''));
        $start = sanitize_text_field(wp_unslash($_POST['starts_at'] ?? ''));
        $end = sanitize_text_field(wp_unslash($_POST['ends_at'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ||
            !preg_match('/^\d{2}:\d{2}$/', $start) ||
            !preg_match('/^\d{2}:\d{2}$/', $end)) {
            calsys_frontend_redirect('invalid');
        }
        $starts_at = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $start, wp_timezone());
        $ends_at = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $end, wp_timezone());
        $date_errors = DateTimeImmutable::getLastErrors();
        if (!$starts_at || !$ends_at || ($date_errors && ($date_errors['warning_count'] || $date_errors['error_count'])) ||
            $starts_at->format('Y-m-d H:i') !== $date . ' ' . $start ||
            $ends_at->format('Y-m-d H:i') !== $date . ' ' . $end) {
            calsys_frontend_redirect('invalid');
        }
        if ($ends_at <= $starts_at) {
            $ends_at = $ends_at->modify('+1 day');
        }
        $_POST['starts_at'] = $starts_at->format('Y-m-d\TH:i');
        $_POST['ends_at'] = $ends_at->format('Y-m-d\TH:i');
        calsys_save_event(true);
    }
    calsys_save_booking($staff_booking, $frontend);
}
add_action('admin_post_calsys_request_room', 'calsys_public_room_booking');
add_action('admin_post_nopriv_calsys_request_room', 'calsys_public_room_booking');

/** Emails a public room requester about a staff decision or cancellation. */
function calsys_notify_room_status($booking, $status, $comment = '') {
    $source_type = $booking->source_type ?? '';
    if ($source_type !== '' && !($status === 'cancelled' && $source_type === 'private')) {
        return true;
    }
    if (!is_email($booking->requester_email ?? '')) {
        error_log('CommonCalendar: a room notification recipient has no valid email address.');
        return false;
    }
    $subjects = array(
        'approved' => __('Your room request was approved', 'community-calendar-study-rooms'),
        'denied' => __('Your room request was declined', 'community-calendar-study-rooms'),
        'cancelled' => __('Your room booking was cancelled', 'community-calendar-study-rooms'),
    );
    if (!isset($subjects[$status])) {
        return false;
    }
    global $wpdb;
    $room_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM " . calsys_table('rooms') . " WHERE id = %d", $booking->room_id));
    if ($room_name === null || $room_name === false) {
        error_log('CommonCalendar: unable to load room name for status email: ' . $wpdb->last_error);
        return false;
    }
    $start_label = mysql2date('g:i A', $booking->booking_date . ' ' . $booking->starts_at);
    $end_label = mysql2date('g:i A', $booking->booking_date . ' ' . $booking->ends_at);
    $message = sprintf(
            __("Hello %1\$s,\n\n%2\$s.\n\nRequester Name: %3\$s\nRequester Email: %4\$s\nRoom: %5\$s\nDate: %6\$s\nTime: %7\$s to %8\$s", 'community-calendar-study-rooms'),
            $booking->requester_name,
            $subjects[$status],
            $booking->requester_name,
            $booking->requester_email,
            $room_name,
            $booking->booking_date,
            $start_label,
            $end_label
        );
    if ($comment !== '' && $status !== 'cancelled') {
        $message .= "\n\n" . __('Staff comment:', 'community-calendar-study-rooms') . "\n" . $comment;
    }
    return calsys_send_email($booking->requester_email, $subjects[$status], $message, 'room_' . $status,
        array('name' => $booking->requester_name, 'email' => $booking->requester_email, 'room' => $room_name, 'date' => $booking->booking_date,
            'start' => $start_label, 'end' => $end_label, 'comment' => $comment));
}

/** Applies an authorized decision, including conflict checks and requester notification. */
function calsys_apply_booking_decision($booking_id, $decision, $comment) {
    global $wpdb;
    $table = calsys_table('bookings');
    if ($wpdb->query('START TRANSACTION') === false) {
        error_log('CommonCalendar: unable to start a room decision transaction: ' . $wpdb->last_error);
        return 'invalid';
    }
    $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d AND status = 'pending' FOR UPDATE", $booking_id));
    if (!$booking) {
        if ($wpdb->last_error) {
            error_log('CommonCalendar: unable to load a pending room decision: ' . $wpdb->last_error);
        }
        $wpdb->query('ROLLBACK');
        return 'invalid';
    }
    if ($decision === 'approve') {
        $locked_room = $wpdb->get_var($wpdb->prepare("SELECT id FROM " . calsys_table('rooms') . " WHERE id = %d FOR UPDATE", $booking->room_id));
        if (!$locked_room) {
            error_log('CommonCalendar: unable to lock a room for an approval: ' . $wpdb->last_error);
            $wpdb->query('ROLLBACK');
            return 'invalid';
        }
        $has_conflict = calsys_booking_conflict($booking->room_id, $booking->booking_date, $booking->starts_at, $booking->ends_at, $booking_id);
        if ($has_conflict === null) {
            error_log('CommonCalendar: unable to check room approval conflicts.');
            $wpdb->query('ROLLBACK');
            return 'invalid';
        }
        if ($has_conflict) {
            $wpdb->query('ROLLBACK');
            return 'conflict';
        }
    }
    $status = $decision === 'approve' ? 'approved' : 'denied';
    $updated = $wpdb->update($table, array('status' => $status), array('id' => $booking_id, 'status' => 'pending'), array('%s'), array('%d', '%s'));
    $equipment_updated = $wpdb->update(calsys_table('equipment_reservations'), array('status' => $status), array('source_type' => 'room', 'source_id' => $booking_id));
    if ($updated !== 1 || $equipment_updated === false || $wpdb->query('COMMIT') === false) {
        error_log('CommonCalendar: unable to save a room decision: ' . $wpdb->last_error);
        $wpdb->query('ROLLBACK');
        return 'invalid';
    }
    calsys_clear_room_email_review($booking_id);
    $sent = calsys_notify_room_status($booking, $status, $comment);
    return $sent ? $status : 'room_status_email_failed';
}

/** Approves or denies a pending room request after permission and conflict checks. */
function calsys_booking_decision() {
    $frontend = false;
    $admin_page = isset($_POST['calsys_return_page']) && $_POST['calsys_return_page'] === 'calsys-room-approvals'
        ? 'calsys-room-approvals' : 'calsys-rooms';
    calsys_require_staff();
    if (!empty($_POST['calsys_frontend'])) {
        calsys_action_redirect(false, 'calsys-room-approvals', 'invalid');
    }
    $booking_id = absint($_POST['booking_id'] ?? 0);
    check_admin_referer('calsys_booking_decision_' . $booking_id);
    $decision = sanitize_key(wp_unslash($_POST['decision'] ?? ''));
    $raw_comment = $_POST['decision_comment'] ?? '';
    if (!$booking_id || !in_array($decision, array('approve', 'deny'), true) || !is_string($raw_comment)) {
        calsys_action_redirect($frontend, $admin_page, 'invalid');
    }
    $comment = sanitize_textarea_field(wp_unslash($raw_comment));
    if (strlen($comment) > 16000) {
        calsys_action_redirect($frontend, $admin_page, 'invalid');
    }
    calsys_action_redirect($frontend, $admin_page, calsys_apply_booking_decision($booking_id, $decision, $comment));
}
add_action('admin_post_calsys_booking_decision', 'calsys_booking_decision');

/** Creates bearer links for a pending request, storing only the token hash. */
function calsys_create_room_email_review_links($booking_id, $days = 7) {
    if (!is_int($booking_id) || $booking_id < 1) {
        error_log('CommonCalendar: invalid room request for email approval links.');
        return false;
    }
    $days = calsys_validate_room_email_review_days($days);
    if ($days === false) {
        error_log('CommonCalendar: invalid room email approval link duration.');
        return false;
    }
    $endpoint = admin_url('admin-post.php');
    if (strtolower((string) parse_url($endpoint, PHP_URL_SCHEME)) !== 'https') {
        error_log('CommonCalendar: HTTPS is required for room request email approval links.');
        return false;
    }
    try {
        $token = bin2hex(random_bytes(32));
    } catch (Exception $error) {
        error_log('CommonCalendar: unable to generate a room request email approval token.');
        return false;
    }
    $option = 'calsys_room_review_booking_' . (int) $booking_id;
    $token_hash = hash('sha256', $token);
    $now = time();
    if ($days > intdiv(PHP_INT_MAX - $now, DAY_IN_SECONDS)) {
        error_log('CommonCalendar: room email approval duration exceeds the supported date range.');
        return false;
    }
    $expires = $days === 0 ? 0 : $now + $days * DAY_IN_SECONDS;
    if (!add_option($option, array('booking_id' => (int) $booking_id, 'expires' => $expires, 'token_hash' => $token_hash), '', false)) {
        error_log('CommonCalendar: unable to save a room request email approval token.');
        return false;
    }
    if ($expires !== 0) {
        $scheduled = wp_schedule_single_event($expires, 'calsys_expire_room_email_review', array($option, $token_hash), true);
        if ($scheduled !== true) {
            delete_option($option);
            error_log('CommonCalendar: unable to schedule room request email token cleanup.');
            return false;
        }
    }
    return array(
        'approve' => add_query_arg(array('action' => 'calsys_room_email_review', 'decision' => 'approve', 'booking_id' => (int) $booking_id, 'token' => $token), $endpoint),
        'deny' => add_query_arg(array('action' => 'calsys_room_email_review', 'decision' => 'deny', 'booking_id' => (int) $booking_id, 'token' => $token), $endpoint),
        'days' => $days,
    );
}

/** Removes the current email review token after the request is decided or deleted. */
function calsys_clear_room_email_review($booking_id) {
    $option = 'calsys_room_review_booking_' . (int) $booking_id;
    if (get_option($option, false) !== false && !delete_option($option)) {
        error_log('CommonCalendar: unable to remove a room email approval token.');
    }
}

function calsys_expire_room_email_review($option, $token_hash = '') {
    if (!is_string($option)) {
        return;
    }
    if (preg_match('/^calsys_room_review_[a-f0-9]{64}$/D', $option)) {
        delete_option($option);
    } elseif (preg_match('/^calsys_room_review_booking_[1-9][0-9]*$/D', $option) && is_string($token_hash)) {
        $review = get_option($option, false);
        if (is_array($review) && isset($review['token_hash']) && is_string($review['token_hash']) &&
            hash_equals($review['token_hash'], $token_hash)) {
            delete_option($option);
        }
    }
}
add_action('calsys_expire_room_email_review', 'calsys_expire_room_email_review', 10, 2);

/** Confirms and applies an email review link without requiring a staff login. */
function calsys_room_email_review() {
    add_filter('nocache_headers', function ($headers) {
        $headers['Cache-Control'] = 'no-cache, must-revalidate, max-age=0, no-store, private';
        return $headers;
    });
    nocache_headers();
    header('Cache-Control: no-store, private, max-age=0');
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: frame-ancestors 'none'", false);

    if (!is_ssl()) {
        wp_die(esc_html__('A secure HTTPS connection is required to review this request.', 'community-calendar-study-rooms'), '', array('response' => 403));
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if ($method !== 'GET' && $method !== 'POST') {
        wp_die(esc_html__('This request method is not supported.', 'community-calendar-study-rooms'), '', array('response' => 405));
    }
    $input = $method === 'POST' ? $_POST : $_GET;
    $token = isset($input['token']) && is_string($input['token']) ? wp_unslash($input['token']) : '';
    $decision = isset($input['decision']) && is_string($input['decision']) ? wp_unslash($input['decision']) : '';
    if (!preg_match('/^[a-f0-9]{64}$/D', $token) || !in_array($decision, array('approve', 'deny'), true)) {
        wp_die(esc_html__('This review link is invalid or has expired.', 'community-calendar-study-rooms'), '', array('response' => 403));
    }
    if (!calsys_get_settings()['notifications']['room_email_review_enabled']) {
        wp_die(esc_html__('Email approval links are temporarily turned off. Please contact the site administrator or try again later.', 'community-calendar-study-rooms'), '', array('response' => 403));
    }
    $link_booking_id = null;
    if (array_key_exists('booking_id', $input)) {
        $raw_booking_id = $input['booking_id'];
        $link_booking_id = is_string($raw_booking_id) ? filter_var(wp_unslash($raw_booking_id), FILTER_VALIDATE_INT, array('options' => array('min_range' => 1))) : false;
        if ($link_booking_id === false) {
            wp_die(esc_html__('This review link is invalid or has expired.', 'community-calendar-study-rooms'), '', array('response' => 403));
        }
    }
    $option = $link_booking_id === null ? 'calsys_room_review_' . hash('sha256', $token) : 'calsys_room_review_booking_' . $link_booking_id;
    $review = get_option($option, false);
    if (!is_array($review) || !isset($review['booking_id'], $review['expires']) ||
        !is_int($review['booking_id']) || $review['booking_id'] < 1 || !is_int($review['expires']) || $review['expires'] < 0 ||
        ($review['expires'] !== 0 && $review['expires'] <= time()) ||
        ($link_booking_id !== null && ($review['booking_id'] !== $link_booking_id ||
            !isset($review['token_hash']) || !is_string($review['token_hash']) || !hash_equals($review['token_hash'], hash('sha256', $token))))) {
        if (is_array($review) && isset($review['expires']) && is_int($review['expires']) && $review['expires'] > 0 && $review['expires'] <= time()) {
            delete_option($option);
        }
        wp_die(esc_html__('This review link is invalid or has expired.', 'community-calendar-study-rooms'), '', array('response' => 403));
    }

    global $wpdb;
    $request = $wpdb->get_row($wpdb->prepare(
        "SELECT b.booking_date, b.starts_at, b.ends_at, r.name AS room_name FROM " . calsys_table('bookings') . " b
        INNER JOIN " . calsys_table('rooms') . " r ON r.id = b.room_id WHERE b.id = %d AND b.status = 'pending'",
        $review['booking_id']
    ));
    if (!$request) {
        if ($wpdb->last_error) {
            error_log('CommonCalendar: unable to load a room request for email review: ' . $wpdb->last_error);
            wp_die(esc_html__('The room request could not be loaded. Please contact the site administrator.', 'community-calendar-study-rooms'), '', array('response' => 500));
        }
        wp_die(esc_html__('This room request is no longer awaiting a decision.', 'community-calendar-study-rooms'), '', array('response' => 410));
    }
    if ($method === 'POST') {
        $raw_comment = $input['decision_comment'] ?? '';
        if (!is_string($raw_comment)) {
            wp_die(esc_html__('The comment is invalid.', 'community-calendar-study-rooms'), '', array('response' => 400));
        }
        $comment = sanitize_textarea_field(wp_unslash($raw_comment));
        if (strlen($comment) > 16000) {
            wp_die(esc_html__('The comment is too long.', 'community-calendar-study-rooms'), '', array('response' => 400));
        }
        $result = calsys_apply_booking_decision($review['booking_id'], $decision, $comment);
        if (in_array($result, array('approved', 'denied', 'room_status_email_failed'), true)) {
            delete_option($option);
        }
        if ($result === 'conflict') {
            wp_die(esc_html__('This request conflicts with another booking and cannot be approved. Please review it in WordPress.', 'community-calendar-study-rooms'), '', array('response' => 409));
        }
        if ($result === 'room_status_email_failed') {
            wp_die(esc_html__('The decision was saved, but the requester email failed. Please contact the site administrator.', 'community-calendar-study-rooms'), '', array('response' => 500));
        }
        if ($result !== 'approved' && $result !== 'denied') {
            wp_die(esc_html__('The decision could not be saved. It may have already been handled; please check WordPress.', 'community-calendar-study-rooms'), '', array('response' => 409));
        }
        wp_die(esc_html($result === 'approved'
            ? __('The room request was approved.', 'community-calendar-study-rooms')
            : __('The room request was denied.', 'community-calendar-study-rooms')), '', array('response' => 200));
    }

    $title = $decision === 'approve' ? __('Approve room request', 'community-calendar-study-rooms') : __('Deny room request', 'community-calendar-study-rooms');
    $date = mysql2date(get_option('date_format'), $request->booking_date);
    $start = mysql2date('g:i A', $request->booking_date . ' ' . $request->starts_at);
    $end = mysql2date('g:i A', $request->booking_date . ' ' . $request->ends_at);
    $html = '<h1>' . esc_html($title) . '</h1>'
        . '<p>' . esc_html($request->room_name) . '<br>' . esc_html($date) . '<br>' . esc_html($start . ' - ' . $end) . '</p>'
        . '<p>' . esc_html__('Opening this page has not changed the request. Confirm your decision below.', 'community-calendar-study-rooms') . '</p>'
        . '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
        . '<input type="hidden" name="action" value="calsys_room_email_review">'
        . '<input type="hidden" name="token" value="' . esc_attr($token) . '">'
        . ($link_booking_id !== null ? '<input type="hidden" name="booking_id" value="' . esc_attr($link_booking_id) . '">' : '')
        . '<input type="hidden" name="decision" value="' . esc_attr($decision) . '">'
        . '<p><label for="calsys-review-comment">' . esc_html__('Comment to requester (optional)', 'community-calendar-study-rooms') . '</label><br>'
        . '<textarea id="calsys-review-comment" name="decision_comment" rows="3" maxlength="4000" style="width:100%;box-sizing:border-box;"></textarea></p>'
        . '<p><button class="button button-large" type="submit">' . esc_html($decision === 'approve' ? __('Confirm approval', 'community-calendar-study-rooms') : __('Confirm denial', 'community-calendar-study-rooms')) . '</button></p></form>';
    wp_die($html, esc_html($title), array('response' => 200));
}
add_action('admin_post_calsys_room_email_review', 'calsys_room_email_review');
add_action('admin_post_nopriv_calsys_room_email_review', 'calsys_room_email_review');

function calsys_front_delete_room_booking() {
    calsys_require_staff();
    check_admin_referer('calsys_front_delete_room_booking');
    $booking_id = absint($_POST['booking_id'] ?? 0);
    $comment = calsys_cancellation_comment($_POST['cancellation_comment'] ?? '');
    if (!$booking_id || $comment === false) {
        calsys_frontend_redirect('invalid_room_request');
    }

    global $wpdb;
    $bookings_table = calsys_table('bookings');
    if ($wpdb->query('START TRANSACTION') === false) {
        calsys_frontend_redirect('invalid_room_request');
    }
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $bookings_table WHERE id = %d FOR UPDATE",
        $booking_id
    ));
    if (!$booking) {
        $wpdb->query('ROLLBACK');
        calsys_frontend_redirect('invalid_room_request');
    }
    $locked_room = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM " . calsys_table('rooms') . " WHERE id = %d FOR UPDATE",
        $booking->room_id
    ));
    if (!$locked_room) {
        $wpdb->query('ROLLBACK');
        calsys_frontend_redirect('invalid_room_request');
    }
    if ($booking->source_type === 'staff') {
        $staff_updated = $wpdb->update(
            calsys_table('staff_calendar'),
            array('room_id' => 0),
            array('id' => $booking->source_id, 'room_id' => $booking->room_id),
            array('%d'),
            array('%d', '%d')
        );
        if ($staff_updated === false) {
            $wpdb->query('ROLLBACK');
            calsys_frontend_redirect('invalid_room_request');
        }
    }
    if ($wpdb->delete(calsys_table('equipment_reservations'), array('source_type' => 'room', 'source_id' => $booking_id)) === false ||
        $wpdb->delete($bookings_table, array('id' => $booking_id), array('%d')) !== 1 ||
        $wpdb->query('COMMIT') === false) {
        $wpdb->query('ROLLBACK');
        calsys_frontend_redirect('invalid_room_request');
    }
    calsys_clear_room_email_review($booking_id);
    calsys_frontend_redirect(calsys_notify_room_status($booking, 'cancelled', $comment) ? 'room_booking_deleted' : 'room_cancel_email_failed');
}
add_action('admin_post_calsys_front_delete_room_booking', 'calsys_front_delete_room_booking');
