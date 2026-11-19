<?php
// Run with: php tests/room-email-decisions.php
define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);

class ReviewResponse extends Exception {
    public $body;
    public $status;
    public function __construct($body, $status) { $this->body = $body; $this->status = $status; }
}
class ReviewDatabase {
    public $prefix = 'wp_';
    public $last_error = '';
    public $booking;
    public $updates = 0;
    public $load_fails = false;
    public $booking_update_result = 1;
    public $prepared = array();
    public function prepare($sql, ...$args) { $this->prepared[] = $args; return $sql; }
    public function get_row($sql) {
        if ($this->load_fails) { $this->last_error = 'Database failure'; return null; }
        $this->last_error = '';
        return $this->booking->status === 'pending' ? clone $this->booking : null;
    }
    public function get_var($sql) { return strpos($sql, 'SELECT name') !== false ? '<Blue room>' : 4; }
    public function query($sql) { return 1; }
    public function update($table, $data, $where, $formats = null, $where_formats = null) {
        if ($table === 'wp_calsys_bookings') {
            if ($this->booking_update_result !== 1) { return $this->booking_update_result; }
            $this->booking->status = $data['status'];
            $this->updates++;
        }
        return 1;
    }
}
function expect($condition, $message) {
    if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); }
}
function add_action($name, $callback, $priority = 10, $accepted_args = 1) {
    $GLOBALS['hooks'][$name] = $callback;
    $GLOBALS['hook_args'][$name] = $accepted_args;
}
function add_filter($name, $callback) { $GLOBALS['filters'][$name] = $callback; }
function __($value) { return $value; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_html__($value) { return esc_html($value); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function sanitize_textarea_field($value) { return trim(strip_tags($value)); }
function wp_unslash($value) { return $value; }
function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL); }
function calsys_table($name) { return 'wp_calsys_' . $name; }
function calsys_get_settings() { return array('notifications' => array('room_email_review_enabled' => $GLOBALS['review_enabled'], 'room_email_review_days' => $GLOBALS['review_days'])); }
function calsys_validate_room_email_review_days($days) { return is_int($days) && $days >= 0 ? $days : false; }
function admin_url($path) { return ($GLOBALS['https_url'] ? 'https' : 'http') . '://example.org/wp-admin/' . $path; }
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
function get_option($name, $default = false) {
    if ($name === 'date_format') { return 'Y-m-d'; }
    return $GLOBALS['options'][$name] ?? $default;
}
function add_option($name, $value, $deprecated = '', $autoload = false) {
    if (!$GLOBALS['option_succeeds']) { return false; }
    $GLOBALS['options'][$name] = $value;
    $GLOBALS['autoload'] = $autoload;
    return true;
}
function delete_option($name) { unset($GLOBALS['options'][$name]); return true; }
function wp_schedule_single_event($timestamp, $hook, $args, $wp_error) {
    $GLOBALS['scheduled'][] = array($timestamp, $hook, $args);
    return $GLOBALS['schedule_succeeds'];
}
function nocache_headers() {}
function is_ssl() { return $GLOBALS['secure_request']; }
function wp_die($message, $title = '', $args = array()) { throw new ReviewResponse($message, $args['response'] ?? 500); }
function mysql2date($format, $date) { return date($format, strtotime($date)); }
function calsys_booking_conflict() { return $GLOBALS['conflict']; }
function calsys_send_email($recipient, $subject, $message, $type, $context) {
    $GLOBALS['mail'][] = compact('recipient', 'subject', 'message', 'type', 'context');
    return $GLOBALS['mail_succeeds'];
}
function review($method, $input) {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_GET = $method === 'GET' ? $input : array();
    $_POST = $method === 'POST' ? $input : array();
    try { calsys_room_email_review(); } catch (ReviewResponse $response) { return $response; }
    throw new RuntimeException('Review endpoint did not finish');
}
function link_input($url) {
    parse_str(parse_url($url, PHP_URL_QUERY), $input);
    return $input;
}
function new_booking() {
    return (object) array('room_id' => 4, 'room_name' => '<Blue room>', 'booking_date' => '2026-12-01',
        'starts_at' => '10:00:00', 'ends_at' => '11:00:00', 'requester_name' => 'Alex',
        'requester_email' => 'alex@example.org', 'status' => 'pending', 'source_type' => '');
}

require __DIR__ . '/../includes/room-actions.php';
$wpdb = new ReviewDatabase();
$wpdb->booking = new_booking();
$options = array();
$scheduled = array();
$mail = array();
$conflict = false;
$https_url = true;
$secure_request = true;
$option_succeeds = true;
$schedule_succeeds = true;
$mail_succeeds = true;
$review_enabled = true;
$review_days = 7;
expect(isset($hooks['admin_post_calsys_room_email_review'], $hooks['admin_post_nopriv_calsys_room_email_review']),
    'Email review must work for logged-in and logged-out users');
expect($hook_args['calsys_expire_room_email_review'] === 2, 'Cleanup must receive the token hash from WordPress cron');

$before = time();
$links = calsys_create_room_email_review_links(17);
$approve = link_input($links['approve']);
$deny = link_input($links['deny']);
$option = 'calsys_room_review_booking_17';
expect($approve['token'] === $deny['token'] && preg_match('/^[a-f0-9]{64}$/D', $approve['token']) &&
    $options[$option]['token_hash'] === hash('sha256', $approve['token']) && $options[$option]['booking_id'] === 17 &&
    $options[$option]['expires'] >= $before + 7 * DAY_IN_SECONDS && $options[$option]['expires'] <= time() + 7 * DAY_IN_SECONDS &&
    $autoload === false && $scheduled[0] === array($options[$option]['expires'], 'calsys_expire_room_email_review', array($option, $options[$option]['token_hash'])),
    'A hashed, non-autoloaded token must expire and be scheduled for cleanup after seven days');

$page = review('GET', $approve);
expect($page->status === 200 && strpos($page->body, 'Confirm approval') !== false &&
    strpos($page->body, '&lt;Blue room&gt;') !== false && strpos($page->body, '<Blue room>') === false &&
    strpos($page->body, 'name="decision_comment"') !== false && strpos($page->body, 'name="booking_id" value="17"') !== false &&
    $wpdb->updates === 0,
    'Opening the approve link must show an escaped confirmation without changing the booking');
$page = review('GET', $deny);
expect($page->status === 200 && strpos($page->body, 'Confirm denial') !== false && $wpdb->updates === 0,
    'Opening the deny link must not change the booking');
$review_enabled = false;
expect(review('GET', $approve)->status === 403 && review('POST', $approve)->status === 403 &&
    isset($options[$option]) && $wpdb->updates === 0, 'Disabling links must pause both GET and POST without deleting them');
$review_enabled = true;
$review_days = 1;
expect(review('GET', $approve)->status === 200 && $options[$option]['expires'] >= $before + 7 * DAY_IN_SECONDS,
    'Re-enabling links must restore access and changing duration must not alter existing links');

$invalid = $approve;
$invalid['token'] = str_repeat('0', 64);
expect(review('POST', $invalid)->status === 403 && $wpdb->updates === 0, 'Unknown tokens must not authorize decisions');
$invalid = $approve;
$invalid['decision'] = 'other';
expect(review('POST', $invalid)->status === 403 && $wpdb->updates === 0, 'Invalid decisions must be rejected');
expect(review('HEAD', $approve)->status === 405 && $wpdb->updates === 0, 'Unsupported methods must not change the booking');
$invalid = $approve;
$invalid['decision_comment'] = array('not text');
expect(review('POST', $invalid)->status === 400 && $wpdb->updates === 0, 'Invalid comments must not change the booking');
$invalid['decision_comment'] = str_repeat('x', 16001);
expect(review('POST', $invalid)->status === 400 && $wpdb->updates === 0, 'Oversized comments must not change the booking');
$secure_request = false;
expect(review('GET', $approve)->status === 403, 'Review links must require HTTPS');
$secure_request = true;

$invalid = $approve;
$invalid['booking_id'] = '999';
expect(review('POST', $invalid)->status === 403 && $wpdb->updates === 0, 'A link must not authorize a different booking');
$approve['decision_comment'] = 'Please bring <b>ID</b>';
$result = review('POST', $approve);
expect($result->status === 200 && $wpdb->booking->status === 'approved' && $wpdb->updates === 1 &&
    count($mail) === 1 && strpos($mail[0]['message'], 'Please bring ID') !== false && !isset($options[$option]) &&
    in_array(array(17), $wpdb->prepared, true) && !in_array(array(999), $wpdb->prepared, true),
    'Confirmed approvals must update the booking, notify the requester, and invalidate the link');
expect(review('POST', $deny)->status === 403 && $wpdb->updates === 1 && count($mail) === 1,
    'A used link must not process a second decision');

$wpdb->booking = new_booking();
$links = calsys_create_room_email_review_links(17);
$approve = link_input($links['approve']);
$deny = link_input($links['deny']);
$option = 'calsys_room_review_booking_17';
$conflict = true;
expect(review('POST', $approve)->status === 409 && $wpdb->booking->status === 'pending' && isset($options[$option]),
    'A conflicting approval must fail without consuming the link');
$deny['decision_comment'] = 'Not available';
expect(review('POST', $deny)->status === 200 && $wpdb->booking->status === 'denied' &&
    count($mail) === 2 && strpos($mail[1]['message'], 'Not available') !== false, 'A denial must still be possible after a conflict');
$conflict = false;

$wpdb->booking = new_booking();
$links = calsys_create_room_email_review_links(17);
$approve = link_input($links['approve']);
$option = 'calsys_room_review_booking_17';
$wpdb->booking_update_result = 0;
expect(review('POST', $approve)->status === 409 && $wpdb->booking->status === 'pending' && isset($options[$option]) && count($mail) === 2,
    'A failed conditional update must not notify the requester or consume the link');
$wpdb->booking_update_result = 1;
$review_enabled = false;
$options[$option]['expires'] = time() - 1;
expect(review('POST', $approve)->status === 403 && $wpdb->booking->status === 'pending', 'An expired link must not work while links are paused');
$review_enabled = true;
expect(review('POST', $approve)->status === 403 && !isset($options[$option]) && $wpdb->booking->status === 'pending',
    'Links that expire while paused must remain invalid after re-enabling');
$links = calsys_create_room_email_review_links(17);
$approve = link_input($links['approve']);
$option = 'calsys_room_review_booking_17';
$wpdb->booking->status = 'approved';
expect(review('POST', $approve)->status === 410 && count($mail) === 2, 'Decisions made elsewhere must make the link unusable');
$wpdb->booking = new_booking();
$wpdb->load_fails = true;
expect(review('GET', $approve)->status === 500, 'Database errors must be reported');
$wpdb->load_fails = false;
$mail_succeeds = false;
expect(review('POST', $approve)->status === 500 && $wpdb->booking->status === 'approved' && !isset($options[$option]),
    'A failed requester email must be reported without allowing a repeated decision');

$schedule_succeeds = false;
expect(calsys_create_room_email_review_links(18) === false && count($options) === 0, 'Scheduling failure must not leave a usable token');
$schedule_succeeds = true;
$https_url = false;
expect(calsys_create_room_email_review_links(18) === false, 'Approval links must not be sent over HTTP');
$https_url = true;
$links = calsys_create_room_email_review_links(18);
$input = link_input($links['approve']);
$option = 'calsys_room_review_booking_18';
calsys_expire_room_email_review('unrelated_option');
expect(isset($options[$option]), 'Cleanup must not delete unrelated options');
calsys_expire_room_email_review($option, str_repeat('0', 64));
expect(isset($options[$option]), 'An old cleanup event must not delete a newer token');
calsys_expire_room_email_review($option, hash('sha256', $input['token']));
expect(!isset($options[$option]), 'Scheduled cleanup must remove the token record');

$wpdb->booking = new_booking();
$count_scheduled = count($scheduled);
$links = calsys_create_room_email_review_links(18, 0);
$input = link_input($links['approve']);
expect($options[$option]['expires'] === 0 && $links['days'] === 0 && count($scheduled) === $count_scheduled &&
    review('GET', $input)->status === 200, 'Never-expiring links must not schedule an expiration and must remain usable');
calsys_clear_room_email_review(18);
expect(review('GET', $input)->status === 403, 'Removing the request token must invalidate a never-expiring link');
$links = calsys_create_room_email_review_links(18, 0);
$input = link_input($links['approve']);
$mail_succeeds = true;
expect(review('POST', $input)->status === 200 && !isset($options[$option]), 'A never-expiring link must work and be removed after a decision');

$wpdb->booking = new_booking();
$legacy_token = str_repeat('a', 64);
$legacy_option = 'calsys_room_review_' . hash('sha256', $legacy_token);
$options[$legacy_option] = array('booking_id' => 17, 'expires' => time() + DAY_IN_SECONDS);
$legacy = array('token' => $legacy_token, 'decision' => 'deny');
$mail_succeeds = true;
expect(review('GET', $legacy)->status === 200 && review('POST', $legacy)->status === 200 && !isset($options[$legacy_option]),
    'Previously emailed seven-day links must continue to work after upgrading');
echo "Room email decisions passed.\n";
