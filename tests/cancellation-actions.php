<?php
// Run with: php tests/cancellation-actions.php
define('ABSPATH', __DIR__ . '/');
define('CALSYS_PLUGIN_FILE', __FILE__);
class CancellationRedirect extends Exception {}
class CancellationDatabase {
    public $prefix = 'wp_';
    public $last_error = '';
    public $in_transaction = false;
    public $commit_fails = false;
    public $deletions = array();
    public $attendees = array();
    public $waiting = array();
    public $event;
    public $booking;
    public function prepare($sql, ...$args) { return $sql; }
    public function query($sql) {
        if ($sql === 'START TRANSACTION') { $this->in_transaction = true; }
        if ($sql === 'COMMIT' && $this->commit_fails) { return false; }
        if ($sql === 'COMMIT' || $sql === 'ROLLBACK') { $this->in_transaction = false; }
        return 1;
    }
    public function get_row($sql) {
        return strpos($sql, 'wp_calsys_events') !== false ? $this->event : $this->booking;
    }
    public function get_results($sql) {
        return strpos($sql, 'wp_calsys_waitlist') !== false ? $this->waiting : $this->attendees;
    }
    public function get_var($sql) { return strpos($sql, 'SELECT name') !== false ? 'Blue room' : 1; }
    public function delete($table, $where, $format = null) { $this->deletions[] = $table; return 1; }
    public function update($table, $data, $where, $format = null, $where_format = null) { return 1; }
}
function add_action() {}
function register_activation_hook() {}
function register_deactivation_hook() {}
function __($value) { return $value; }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function sanitize_textarea_field($value) { return trim(strip_tags($value)); }
function sanitize_email($value) { return filter_var($value, FILTER_SANITIZE_EMAIL); }
function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL); }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function wp_unslash($value) { return $value; }
function get_option($name, $default = false) { return $GLOBALS['options'][$name] ?? $default; }
function delete_option($name) { unset($GLOBALS['options'][$name]); return true; }
function current_time($format) { return '2026-11-14 10:00:00'; }
function mysql2date($format, $date) { return date($format, strtotime($date)); }
function calsys_require_staff() { if (empty($GLOBALS['authorized'])) { throw new RuntimeException('Not authorized'); } }
function check_admin_referer($action) { $GLOBALS['nonce_action'] = $action; }
function calsys_lock_linked_room_ids() { return true; }
function calsys_action_redirect($frontend, $page, $notice) { throw new CancellationRedirect($notice); }
function calsys_frontend_redirect($notice) { throw new CancellationRedirect($notice); }
function wp_mail($to, $subject, $body, $headers = array()) {
    expect(!$GLOBALS['wpdb']->in_transaction, 'Cancellation email must be sent after commit');
    $GLOBALS['mail'][] = compact('to', 'subject', 'body');
    return $to !== ($GLOBALS['fail_email'] ?? null);
}
function expect($condition, $message) {
    if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); }
}
function delete_event_with($comment) {
    $_POST = array('event_id' => '1', 'cancellation_comment' => $comment);
    try { calsys_delete_event(true); } catch (CancellationRedirect $redirect) { return $redirect->getMessage(); }
    throw new RuntimeException('Event deletion did not redirect');
}
function delete_room_with($comment) {
    $_POST = array('booking_id' => '7', 'cancellation_comment' => $comment);
    try { calsys_front_delete_room_booking(); } catch (CancellationRedirect $redirect) { return $redirect->getMessage(); }
    throw new RuntimeException('Room deletion did not redirect');
}
require __DIR__ . '/../includes/room-hours.php';
require __DIR__ . '/../includes/core.php';
require __DIR__ . '/../includes/events.php';
require __DIR__ . '/../includes/room-actions.php';

$options = array('admin_email' => 'staff@example.org', 'date_format' => 'Y-m-d', 'time_format' => 'H:i');
$email_settings = calsys_default_settings()['email_settings'];
$email_settings['templates']['event_cancelled'] = array('subject' => 'Cancelled: {event}', 'body' => 'Event cancelled: {event}');
$email_settings['templates']['room_cancelled'] = array('subject' => 'Cancelled: {room}', 'body' => "Room cancelled: {room}\n{comment}");
$options['calsys_settings'] = array('email_settings' => $email_settings);
$wpdb = new CancellationDatabase();
$wpdb->event = (object) array('title' => 'Story time', 'starts_at' => '2026-11-14 09:00:00', 'ends_at' => '2026-11-14 11:00:00');
$wpdb->attendees = array((object) array('name' => 'Alex', 'email' => 'alex@example.org'), (object) array('name' => 'Pat', 'email' => 'pat@example.org'));
$wpdb->waiting = array((object) array('name' => 'Sam', 'email' => 'sam@example.org'));
$wpdb->booking = (object) array('room_id' => 3, 'source_type' => '', 'source_id' => 0,
    'requester_name' => 'Alex', 'requester_email' => 'alex@example.org',
    'booking_date' => '2026-11-15', 'starts_at' => '10:00', 'ends_at' => '11:00');
$authorized = true;
$mail = array();
$fail_email = null;
expect(delete_event_with("Weather <b>closure</b>") === 'event_deleted', 'An ongoing event must be deleted and notify attendees');
expect(count($mail) === 3 && array_column($mail, 'to') === array('alex@example.org', 'pat@example.org', 'sam@example.org'), 'Registered and waitlisted attendees must each receive an email');
foreach ($mail as $message) {
    expect(strpos(strip_tags($message['body']), 'Event cancelled: Story time') !== false && strpos($message['body'], 'Weather closure') !== false &&
        strpos($message['body'], 'scheduled for 2026-11-14 9:00 AM has been cancelled') !== false &&
        strpos($message['body'], '<b>') === false, 'An older custom template must include cancellation details and the sanitized message');
}
$mail = array();
$fail_email = 'pat@example.org';
expect(delete_event_with('0') === 'event_deleted_email_failed' && count($mail) === 3 &&
    strpos($mail[0]['body'], "Staff comment:<br>\n0") !== false, 'Failed sends must be reported without skipping other recipients or the message 0');
$fail_email = null;
$mail = array();
$wpdb->attendees[] = (object) array('name' => 'No email', 'email' => '');
expect(delete_event_with('') === 'event_deleted_email_failed' && count($mail) === 3, 'Invalid recipient addresses must be reported without blocking other attendees');
array_pop($wpdb->attendees);
$mail = array();
$wpdb->event->ends_at = '2026-11-14 09:59:00';
expect(delete_event_with('Past') === 'event_deleted' && !$mail, 'Deleting a finished event must not email attendees');
$wpdb->event->ends_at = '2026-11-14 11:00:00';
$wpdb->commit_fails = true;
expect(delete_event_with('No commit') === 'invalid' && !$mail, 'A failed event commit must not send email');
$wpdb->commit_fails = false;
$wpdb->deletions = array();
expect(delete_event_with(array('invalid')) === 'invalid' && !$wpdb->deletions, 'Invalid event comments must not delete anything');
expect(delete_event_with(str_repeat('x', 16001)) === 'invalid' && !$wpdb->deletions, 'Oversized event comments must not delete anything');
$wpdb->booking->source_type = 'private';
$options['calsys_room_review_booking_7'] = array('expires' => 0);
expect(delete_room_with("Closed <b>today</b>") === 'room_booking_deleted' && count($mail) === 1 &&
    $mail[0]['to'] === 'alex@example.org' && substr_count($mail[0]['body'], 'Closed today') === 1 &&
    strpos($mail[0]['body'], 'Your room booking was cancelled') !== false &&
    strpos($mail[0]['body'], '10:00 AM to 11:00 AM') !== false, 'Staff-created private bookings must notify the contact once with 12-hour cancellation details');
expect(!isset($options['calsys_room_review_booking_7']), 'Deleting a booking must remove its never-expiring review token');
$mail = array();
$wpdb->booking->source_type = '';
$fail_email = 'alex@example.org';
expect(delete_room_with('Room closed') === 'room_cancel_email_failed' && count($mail) === 1, 'Room delivery failures must be reported after deletion');
$fail_email = null;
$mail = array();
$wpdb->commit_fails = true;
expect(delete_room_with('No commit') === 'invalid_room_request' && !$mail, 'A failed room commit must not send email');
$wpdb->commit_fails = false;
$wpdb->deletions = array();
expect(delete_room_with(array('invalid')) === 'invalid_room_request' && !$wpdb->deletions, 'Invalid room comments must not delete anything');
$wpdb->booking->source_type = 'staff';
expect(delete_room_with('Internal') === 'room_booking_deleted' && !$mail, 'Staff-calendar room links must not email the site administrator as a patron');
$authorized = false;
try { delete_event_with('Unauthorized'); expect(false, 'Unauthorized event cancellation must fail'); } catch (RuntimeException $error) {}
try { delete_room_with('Unauthorized'); expect(false, 'Unauthorized room cancellation must fail'); } catch (RuntimeException $error) {}
echo "Cancellation actions passed.\n";
