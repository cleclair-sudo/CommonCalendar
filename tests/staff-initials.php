<?php
// Run with: php tests/staff-initials.php
$fixture = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'calsys-initials-' . uniqid();
mkdir($fixture . DIRECTORY_SEPARATOR . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes', 0777, true);
file_put_contents($fixture . DIRECTORY_SEPARATOR . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'upgrade.php', "<?php\n");
register_shutdown_function(function () use ($fixture) {
    unlink($fixture . DIRECTORY_SEPARATOR . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'upgrade.php');
    rmdir($fixture . DIRECTORY_SEPARATOR . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes');
    rmdir($fixture . DIRECTORY_SEPARATOR . 'wp-admin');
    rmdir($fixture);
});
define('ABSPATH', $fixture . DIRECTORY_SEPARATOR);
define('CALSYS_PLUGIN_FILE', __FILE__);
define('CALSYS_DB_VERSION', '15');

class InitialsRedirect extends Exception {
    public $notice;
    public function __construct($notice) { $this->notice = $notice; }
}
class InitialsDatabase {
    public $prefix = 'wp_';
    public $last_error = '';
    public $insert_id = 12;
    public $writes = array();
    public $missing_column = '';
    public $missing_notes = false;
    public $last_args = array();
    public function get_charset_collate() { return ''; }
    public function esc_like($value) { return $value; }
    public function prepare($sql, ...$args) { $this->last_args = $args; return $sql; }
    public function get_var($sql) {
        if (strpos($sql, 'SHOW TABLES LIKE') !== false) { return $this->last_args[0]; }
        if ($this->missing_column && strpos($sql, 'SHOW COLUMNS FROM wp_calsys_' . $this->missing_column) !== false) { return null; }
        if ($this->missing_notes && strpos($sql, "LIKE 'notes'") !== false) { return null; }
        return 1;
    }
    public function get_row($sql) { return (object) array('id' => 17, 'status' => 'pending'); }
    public function get_results($sql) {
        return strpos($sql, 'wp_calsys_rooms') !== false
            ? array((object) array('id' => 1, 'name' => 'Room', 'capacity' => 5)) : array();
    }
    public function query($sql) { return 1; }
    public function insert($table, $data, $formats = null) {
        if ($formats !== null) { expect(count($data) === count($formats), 'Insert formats must match fields'); }
        $this->writes[] = array($table, $data);
        return 1;
    }
    public function update($table, $data, $where, $formats = null, $where_formats = null) {
        if ($formats !== null) { expect(count($data) === count($formats), 'Update formats must match fields'); }
        $this->writes[] = array($table, $data);
        return 1;
    }
}
function expect($condition, $message) {
    if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); }
}
function add_action() {}
function register_activation_hook() {}
function register_deactivation_hook() {}
function dbDelta($sql) { $GLOBALS['schemas'][] = $sql; }
function get_role($name) { return null; }
function wp_next_scheduled($name) { return true; }
function update_option($name, $value) { $GLOBALS['updated_options'][$name] = $value; }
function get_option($name, $default = false) {
    if ($name === 'calsys_db_version') { return $GLOBALS['previous_db_version']; }
    if ($name === 'admin_email') { return 'admin@example.org'; }
    if ($name === 'time_format') { return 'H:i'; }
    if ($name === 'calsys_settings') {
        return array('notifications' => array('notify_staff_room_requests' => 1, 'email_room_requester' => 1));
    }
    return $default;
}
function __($value) { return $value; }
function wp_json_encode($value) { return json_encode($value); }
function _n($single, $plural, $count) { return $count === 1 ? $single : $plural; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function esc_textarea($value) { return esc_html($value); }
function esc_html__($value) { return esc_html($value); }
function esc_attr__($value) { return esc_attr($value); }
function esc_html_e($value) { echo esc_html($value); }
function esc_attr_e($value) { echo esc_attr($value); }
function admin_url($value) { return '/wp-admin/' . $value; }
function wp_nonce_field() {}
function wp_create_nonce() { return 'nonce'; }
function selected($value, $expected) { if ($value === $expected) { echo 'selected'; } }
function checked($value) { if ($value) { echo 'checked'; } }
function wp_date($format, $timestamp, $timezone) { return (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone)->format($format); }
function mysql2date($format, $value) { return date($format, strtotime($value)); }
function wp_mail($to, $subject, $body, $headers = array()) {
    $GLOBALS['sent_mail'][] = compact('to', 'subject', 'body');
    return true;
}
function get_privacy_policy_url() { return ''; }
function calsys_equipment_fields() { return ''; }
function absint($value) { return abs((int) $value); }
function wp_unslash($value) { return $value; }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function sanitize_textarea_field($value) { return trim(strip_tags($value)); }
function sanitize_key($value) { return $value; }
function sanitize_email($value) { return $value; }
function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL); }
function current_user_can($capability) { return $GLOBALS['staff']; }
function check_admin_referer() {}
function current_time($format) { return $format === 'Y-m-d' ? '2026-11-11' : '2026-11-11 12:00:00'; }
function wp_timezone() { return new DateTimeZone('UTC'); }
function calsys_normalize_room_schedule($weekly, $holidays) { return array('weekly' => $weekly, 'holidays' => $holidays); }
function calsys_action_redirect($frontend, $page, $notice, $skipped = 0) { throw new InitialsRedirect($notice); }
function calsys_frontend_redirect($notice) { throw new InitialsRedirect($notice); }
function calsys_posted_equipment() { return array(); }
function calsys_parse_datetime($value) {
    if (is_array($value)) { $value = implode('T', $value); }
    return str_replace('T', ' ', $value) . ':00';
}
function calsys_lock_linked_room_ids() { return true; }
function calsys_sync_linked_room_reservations($type, $id, $room, $start, $end, $allow, $initials) {
    $GLOBALS['linked_initials'] = $initials;
    return 0;
}
function calsys_sync_equipment() { return 'saved'; }
function calsys_booking_conflict() { return false; }
function calsys_room_is_within_hours() { return true; }
function calsys_valid_booking_input() {
    return array('room_id' => 1, 'room_name' => 'Room', 'name' => 'Patron', 'email' => 'test@example.org',
        'date' => '2026-12-01', 'start' => '13:37:00', 'end' => '14:00:00');
}
function calsys_save_event($frontend) { throw new InitialsRedirect('event'); }

require __DIR__ . '/../includes/core.php';
require __DIR__ . '/../includes/admin-management.php';
require __DIR__ . '/../includes/room-actions.php';
require __DIR__ . '/../includes/frontend-forms.php';

$wpdb = new InitialsDatabase();
$staff = true;
$sent_mail = array();
$schemas = array();
$updated_options = array();
$previous_db_version = 13;
foreach (array('bookings', 'staff_calendar') as $missing) {
    $wpdb->missing_column = $missing;
    calsys_activate();
    expect(!isset($updated_options['calsys_db_version']), 'A missing initials column must prevent upgrade completion');
}
$wpdb->missing_column = '';
$previous_db_version = 14;
$wpdb->missing_notes = true;
calsys_activate();
expect(!isset($updated_options['calsys_db_version']), 'A missing notes column must prevent upgrade completion');
$wpdb->missing_notes = false;
calsys_activate();
expect($updated_options['calsys_db_version'] === '15', 'A successful upgrade must record database version 15');
foreach (array('bookings', 'staff_calendar') as $table) {
    $matches = array_filter($schemas, function ($sql) use ($table) {
        return strpos($sql, 'CREATE TABLE wp_calsys_' . $table . ' ') !== false &&
            strpos($sql, "staff_initials varchar(40) NOT NULL DEFAULT ''") !== false;
    });
    expect((bool) $matches, 'The upgrade must add a default-empty initials column to ' . $table);
}
expect((bool) array_filter($schemas, function ($sql) {
    return strpos($sql, 'CREATE TABLE wp_calsys_staff_calendar ') !== false && strpos($sql, 'notes text NOT NULL') !== false;
}), 'The upgrade must add staff calendar notes');

$staff_post = array('title' => 'Meeting', 'entry_type' => 'other', 'starts_at' => '2026-12-01T10:00',
    'ends_at' => '2026-12-01T11:00', 'room_id' => '1', 'staff_initials' => ' <b>AB</b> ', 'notes' => " <b>Agenda</b>\nDetails ");
$_POST = $staff_post;
try { calsys_save_staff_entry(); expect(false, 'Expected staff creation redirect'); } catch (InitialsRedirect $result) {
    expect($result->notice === 'staff_event_saved', 'Staff entry creation failed');
}
expect($wpdb->writes[0][1]['staff_initials'] === 'AB' && $linked_initials === 'AB', 'Staff creation must save and propagate sanitized initials');
expect($wpdb->writes[0][1]['notes'] === "Agenda\nDetails", 'Staff creation must save sanitized multiline notes');
$_POST['entry_id'] = '17';
$_POST['staff_initials'] = 'CD';
$_POST['notes'] = '';
try { calsys_save_staff_entry(true); expect(false, 'Expected staff edit redirect'); } catch (InitialsRedirect $result) {
    expect($result->notice === 'staff_event_saved', 'Staff entry editing failed');
}
expect($wpdb->writes[1][1]['staff_initials'] === 'CD' && $linked_initials === 'CD', 'Staff editing must save the current editor initials');
expect($wpdb->writes[1][1]['notes'] === '', 'Staff editing must allow notes to be cleared');

foreach (array(array('bad'), str_repeat('x', 16001)) as $invalid_notes) {
    $_POST = $staff_post;
    $_POST['notes'] = $invalid_notes;
    try { calsys_save_staff_entry(); expect(false, 'Invalid staff-entry notes accepted'); } catch (InitialsRedirect $result) {
        expect($result->notice === 'invalid' && count($wpdb->writes) === 2, 'Invalid staff-entry notes must not write');
    }
}

foreach (array(null, '', '  ', array('AB'), str_repeat('x', 41)) as $invalid) {
    $_POST = $staff_post;
    if ($invalid === null) { unset($_POST['staff_initials']); } else { $_POST['staff_initials'] = $invalid; }
    try { calsys_save_staff_entry(); expect(false, 'Invalid staff-entry initials accepted'); } catch (InitialsRedirect $result) {
        expect($result->notice === 'invalid' && count($wpdb->writes) === 2, 'Invalid staff-entry initials must not write');
    }
    try { calsys_save_booking(true); expect(false, 'Invalid room initials accepted'); } catch (InitialsRedirect $result) {
        expect($result->notice === 'invalid_room_request' && count($wpdb->writes) === 2, 'Invalid room initials must not write');
    }
}
$_POST = array('staff_initials' => ' <b>EF</b> ');
try { calsys_save_booking(true); expect(false, 'Expected staff booking redirect'); } catch (InitialsRedirect $result) {
    expect($result->notice === 'booking_saved', 'Staff booking creation failed');
}
expect($wpdb->writes[2][1]['staff_initials'] === 'EF', 'Staff room creation must save sanitized initials');
$_POST = array('staff_initials' => 'GH', 'booking_id' => '17');
try { calsys_save_booking(true, true); expect(false, 'Expected booking edit redirect'); } catch (InitialsRedirect $result) {
    expect($result->notice === 'room_booking_saved', 'Staff booking edit failed');
}
expect($wpdb->writes[3][1]['staff_initials'] === 'GH' && !isset($wpdb->writes[3][1]['source_type']), 'Editing must save initials without changing the booking source');
$_POST = array();
try { calsys_save_booking(false); expect(false, 'Expected public booking redirect'); } catch (InitialsRedirect $result) {
    expect($result->notice === 'room_request_sent', 'Public room requests must not require initials');
}
expect(!array_key_exists('staff_initials', $wpdb->writes[4][1]), 'Public requests must not supply staff initials');
expect(count($sent_mail) === 2 && strpos($sent_mail[0]['body'], '1:37 PM to 2:00 PM') !== false &&
    strpos($sent_mail[1]['body'], '1:37 PM to 2:00 PM') !== false, 'Public room request emails must use 12-hour times for staff and requesters');

$_POST = array('calsys_frontend' => '1', 'staff_booking' => '1', 'public_event' => '1', 'room_id' => '1',
    'booking_date' => '2026-12-01', 'starts_at' => '10:00', 'ends_at' => '11:00', 'staff_initials' => 'IJ');
try { calsys_public_room_booking(); expect(false, 'Expected event route'); } catch (InitialsRedirect $result) {
    expect($result->notice === 'event' && $_POST['staff_initials'] === 'IJ', 'Public event bookings must pass the same initials to event creation');
}
$room_form = calsys_front_room_form();
$staff_form = calsys_front_staff_form();
expect(substr_count($room_form, 'name="staff_initials"') === 1 &&
    strpos($room_form, 'name="staff_initials" type="text" maxlength="10" required') !== false &&
    strpos($room_form, 'name="staff_initials"') < strpos($room_form, 'data-calsys-public-event-fields'),
    'The staff room form must have one required initials input outside the conditional public event fields');
expect(strpos($staff_form, 'name="staff_initials" type="text" maxlength="10" required') !== false, 'The staff entry form must require initials');
expect(strpos($staff_form, 'name="notes" rows="3" maxlength="4000"') !== false, 'The staff entry form must offer optional notes');
expect(strpos($staff_form, 'name="starts_at_date"') !== false && strpos($staff_form, 'name="starts_at_time"') !== false &&
    strpos($staff_form, 'value="00:00">12:00 AM') !== false && strpos($staff_form, 'value="12:00">12:00 PM') !== false &&
    strpos($staff_form, 'value="13:45">1:45 PM') !== false && strpos($staff_form, 'value="13:37">') === false &&
    substr_count($staff_form, 'data-calsys-existing-time-select') === 2, 'Staff time dropdowns must offer 15-minute choices with 12-hour labels');
expect(strpos(calsys_datetime_fields('starts_at', 'Starts'), 'value="13:37">1:37 PM') !== false,
    'Event time controls must continue to offer every minute');
$_POST = $staff_post;
unset($_POST['starts_at'], $_POST['ends_at']);
$_POST += array('starts_at_date' => '2026-12-02', 'starts_at_time' => '13:37', 'ends_at_date' => '2026-12-02', 'ends_at_time' => '14:00');
try { calsys_save_staff_entry(); expect(false, 'Expected staff creation redirect'); } catch (InitialsRedirect $result) {
    expect($result->notice === 'staff_event_saved', 'Split staff datetime fields must save');
}
expect($wpdb->writes[5][1]['starts_at'] === '2026-12-02 13:37:00', 'Split staff datetime fields must retain the selected minute');
$staff = false;
expect(strpos(calsys_front_room_form(), 'name="staff_initials"') === false, 'The public room request form must not ask for staff initials');
echo "Staff initials passed.\n";
