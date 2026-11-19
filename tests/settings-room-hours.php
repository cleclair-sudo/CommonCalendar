<?php
// Run with: php tests/settings-room-hours.php
define('ABSPATH', __DIR__ . '/');
define('CALSYS_PLUGIN_FILE', __FILE__);
define('DAY_IN_SECONDS', 86400);

class TestSettingsRedirect extends Exception {
    public $notice;
    public function __construct($notice) { $this->notice = $notice; }
}
function add_action() {}
function register_activation_hook() {}
function register_deactivation_hook() {}
function __($text) { return $text; }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_url($value) { return esc_attr($value); }
function esc_textarea($value) { return esc_html($value); }
function wp_json_encode($value) { return json_encode($value); }
function esc_html_e($value) { echo esc_html($value); }
function esc_attr_e($value) { echo esc_attr($value); }
function checked($value) { if ($value) { echo 'checked="checked"'; } }
function selected($value, $expected) { if ((string) $value === (string) $expected) { echo 'selected="selected"'; } }
function admin_url($path) { return 'https://example.org/wp-admin/' . $path; }
function home_url($path) { return 'https://example.org' . $path; }
function add_query_arg($key, $value, $url) { return $url . '?' . urlencode($key) . '=' . urlencode($value); }
function wp_nonce_field() {}
function submit_button($label) { echo '<button type="submit">' . esc_html($label) . '</button>'; }
function current_time($format) { return '2026-11'; }
function calsys_admin_rooms_settings_panel() {}
function calsys_admin_equipment_settings() {}
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return $value; }
function sanitize_text_field($value) { return trim($value); }
function sanitize_textarea_field($value) { return trim($value); }
function sanitize_hex_color($value) { return preg_match('/^#[a-f0-9]{6}$/i', $value) ? $value : null; }
function sanitize_email($value) { return $value; }
function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL); }
function wp_unslash($value) { return $value; }
function wp_timezone() { return new DateTimeZone('America/New_York'); }
function get_option($name, $default = false) { return $GLOBALS['options'][$name] ?? $default; }
function update_option($name, $value) { $GLOBALS['options'][$name] = $value; return true; }
function calsys_require_staff() {}
function check_admin_referer($action) { $GLOBALS['nonce_action'] = $action; }
function calsys_admin_redirect($page, $notice) { throw new TestSettingsRedirect($notice); }
function expect($condition, $message) {
    if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); }
}
function save_settings() {
    try {
        calsys_save_settings();
    } catch (TestSettingsRedirect $redirect) {
        return $redirect->notice;
    }
    throw new RuntimeException('Settings save did not redirect');
}

require __DIR__ . '/../includes/room-hours.php';
require __DIR__ . '/../includes/core.php';
require __DIR__ . '/../includes/admin-settings.php';

if (($argv[1] ?? '') === 'reload-never') {
    $options = json_decode(stream_get_contents(STDIN), true);
    $notifications = calsys_get_settings()['notifications'];
    expect($notifications['room_email_review_enabled'] === 0 && $notifications['room_email_review_days'] === 0,
        'Disabled links and never-expiring duration must survive a settings reload');
    $_GET = array();
    ob_start();
    calsys_admin_settings_page();
    $html = ob_get_clean();
    expect((bool) preg_match('/<option value="never"[^>]*selected/', $html) &&
        (bool) preg_match('/<input name="notifications\[room_email_review_enabled\]"[^>]*>/', $html, $toggle) &&
        strpos($toggle[0], 'checked') === false, 'The saved link settings must be reflected in the controls');
    exit(0);
}
if (($argv[1] ?? '') === 'reload') {
    $options = json_decode(stream_get_contents(STDIN), true);
    $rules = calsys_get_settings()['booking_rules'];
    expect($rules['weekly_hours'][1] === array('mode' => 'open', 'open' => '09:00', 'close' => '17:00'), 'Reloading settings must retain weekly hours');
    expect($rules['holiday_hours'][0]['annual'] === 1 && $rules['holiday_hours'][0]['open'] === '10:00', 'Reloading settings must retain special hours');
    $email = calsys_get_settings()['email_settings'];
    expect($email['from_email'] === 'calendar@example.org' && $email['logo_placement'] === 'bottom' && $email['templates']['event_reminder']['subject'] === 'Coming up: {event}', 'Reloading settings must retain email preferences');
    expect(calsys_get_settings()['notifications']['email_event_registrant'] === 0, 'An explicitly disabled confirmation preference must stay disabled');
    expect(calsys_get_settings()['notifications']['room_request_recipients'] === array('one@example.org', 'two@example.org'), 'Room request recipients must survive a settings reload');
    expect(calsys_get_settings()['notifications']['room_email_review_enabled'] === 1 &&
        calsys_get_settings()['notifications']['room_email_review_days'] === 35, 'Link settings must survive a reload');
    expect(calsys_get_settings()['calendar_display']['show_filter_heading'] === 1, 'The filter heading preference must survive a reload');
    $_GET = array();
    ob_start();
    calsys_admin_settings_page();
    $html = ob_get_clean();
    expect(strpos($html, 'value="9:00 AM"') !== false && strpos($html, 'value="5:00 PM"') !== false &&
        strpos($html, 'type="time"') === false, 'Opening hours must be displayed in 12-hour format');
    preg_match('/<input name="notifications\[email_event_registrant\]"[^>]*>/', $html, $confirmation);
    preg_match('/<input name="notifications\[email_event_reminders\]"[^>]*>/', $html, $reminder);
    expect(strpos($confirmation[0], 'checked') === false && strpos($reminder[0], 'checked') !== false, 'The displayed controls must reflect the independently saved settings');
    preg_match('/<input name="calendar_display\[show_filter_heading\]"[^>]*>/', $html, $heading);
    expect(!empty($heading[0]) && strpos($heading[0], 'checked') !== false, 'The filter heading setting must show its saved value');
    expect(strpos($html, "one@example.org\ntwo@example.org") !== false && strpos($html, 'Room request notification addresses') !== false,
        'The room request recipients control must display the saved list');
    exit(0);
}

$options = array('admin_email' => 'staff@example.org', 'calsys_settings' => array('calendar_display' => array('initial_month' => 'current')));
$defaults = calsys_default_settings();
expect($defaults['notifications']['email_event_registrant'] === 1, 'New settings must enable immediate registration confirmations by default');
expect(calsys_get_settings()['notifications']['room_email_review_enabled'] === 1 &&
    calsys_get_settings()['notifications']['room_email_review_days'] === 7, 'Existing settings must retain seven-day links by default');
expect(calsys_get_settings()['notifications']['room_request_recipients'] === array(), 'Existing settings must default to the staff address for room request alerts');
expect($defaults['calendar_display']['show_filter_heading'] === 0 && calsys_get_settings()['calendar_display']['show_filter_heading'] === 0, 'The filter heading must default to hidden for new and existing settings');
$_POST = array(
    'calendar_colors' => $defaults['calendar_colors'],
    'staff_types' => array(),
    'event_categories' => array(),
    'booking_rules' => $defaults['booking_rules'],
    'event_registration' => $defaults['event_registration'],
    'calendar_display' => array('week_start_day' => 'site', 'initial_month' => 'current', 'show_filter_heading' => '1'),
    'room_requests' => $defaults['room_requests'],
    'notifications' => array('staff_email' => 'staff@example.org'),
    'privacy' => $defaults['privacy'],
    'room_schedule_present' => '1',
    'weekly_hours' => array_fill(0, 7, array('mode' => 'unrestricted', 'open' => '', 'close' => '')),
    'holiday_hours' => array(array('date' => '2026-12-25', 'mode' => 'open', 'open' => '10:00 AM', 'close' => '1:00 PM', 'annual' => '1')),
);
$_POST['event_registration']['default_fields_present'] = '1';
foreach ($defaults['staff_types'] as $key => $type) {
    $_POST['staff_types'][] = array('key' => $key, 'label' => $type['label'], 'color' => $type['color']);
}
foreach ($defaults['event_categories'] as $key => $category) {
    $_POST['event_categories'][] = array('key' => $key, 'label' => $category['label'], 'color' => $category['color']);
}
$_POST['email_settings'] = $defaults['email_settings'];
$_POST['email_settings']['from_email'] = 'calendar@example.org';
$_POST['email_settings']['logo_placement'] = 'bottom';
$_POST['email_settings']['templates']['event_reminder'] = array('subject' => 'Coming up: {event}', 'body' => 'Hi {name}, {datetime}');
$_POST['notifications']['email_event_reminders'] = '1';
$_POST['notifications']['email_event_registrant'] = '1';
$_POST['notifications']['room_request_recipients'] = "One@example.org, two@example.org\nONE@example.org";
$_POST['notifications']['room_email_review_present'] = '1';
$_POST['notifications']['room_email_review_enabled'] = '1';
$_POST['notifications']['room_email_review_duration'] = 'days';
$_POST['notifications']['room_email_review_days'] = '35';
$_POST['weekly_hours'][1] = array('mode' => 'open', 'open' => '9:00 AM', 'close' => '5:00 PM');
$_POST['weekly_hours'][0] = array('mode' => 'closed', 'open' => '', 'close' => '');

expect(save_settings() === 'settings_saved', 'Valid opening hours must save');
expect($nonce_action === 'calsys_save_settings', 'Settings save must check its nonce');
$saved = $options['calsys_settings'];
expect($saved['notifications']['email_event_registrant'] === 1 && $saved['notifications']['email_event_reminders'] === 1, 'Both event email settings must save when enabled');
expect($saved['notifications']['room_request_recipients'] === array('one@example.org', 'two@example.org') &&
    $saved['notifications']['staff_email'] === 'staff@example.org', 'Room recipients must save separately and remove duplicates');
expect($saved['notifications']['room_email_review_enabled'] === 1 && $saved['notifications']['room_email_review_days'] === 35,
    'Custom link duration and enabled state must save');
expect($saved['calendar_display']['show_filter_heading'] === 1, 'Enabling the filter heading must save');
unset($_POST['notifications']['email_event_registrant']);
expect(save_settings() === 'settings_saved', 'Disabling the confirmation must save');
$saved = $options['calsys_settings'];
expect($saved['booking_rules']['weekly_hours'][1] === array('mode' => 'open', 'open' => '09:00', 'close' => '17:00'), 'Weekly opening hours must persist in canonical form');
expect($saved['booking_rules']['weekly_hours'][0]['mode'] === 'closed', 'Closed weekdays must persist');
expect($saved['booking_rules']['holiday_hours'][0]['annual'] === 1, 'Recurring special hours must persist');
expect($saved['notifications']['email_event_reminders'] === 1 && $saved['email_settings'] === $_POST['email_settings'], 'Reminder, sender, logo, and email templates must persist');
expect(calsys_room_is_within_hours('2026-12-21', '09:00', '17:00', $saved['booking_rules']), 'Saved hours must permit matching bookings');
expect(!calsys_room_is_within_hours('2026-12-21', '08:00', '09:00', $saved['booking_rules']), 'Saved hours must reject bookings outside the interval');
expect(calsys_room_is_within_hours('2027-12-25', '10:00', '13:00', $saved['booking_rules']), 'Saved recurring special hours must apply');

$process = proc_open(array(PHP_BINARY, __FILE__, 'reload'), array(
    0 => array('pipe', 'r'),
    1 => array('pipe', 'w'),
    2 => array('pipe', 'w'),
), $pipes);
expect(is_resource($process), 'Could not start settings reload test');
fwrite($pipes[0], json_encode($options));
fclose($pipes[0]);
$output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
expect(proc_close($process) === 0, 'Settings reload failed: ' . $output);

unset($_POST['calendar_display']['show_filter_heading']);
expect(save_settings() === 'settings_saved' && $options['calsys_settings']['calendar_display']['show_filter_heading'] === 0, 'Disabling the filter heading must save');
$saved = $options['calsys_settings'];
foreach (array('bad-address', '!!!', array('person@example.org'), implode(',', array_map(function ($id) { return 'person' . $id . '@example.org'; }, range(1, 11)))) as $invalid_recipients) {
    $_POST['notifications']['room_request_recipients'] = $invalid_recipients;
    expect(save_settings() === 'settings_invalid_room_recipients' && $options['calsys_settings'] === $saved,
        'Invalid or excessive room recipients must not overwrite settings');
}
$_POST['notifications']['room_request_recipients'] = "One@example.org, two@example.org\nONE@example.org";
foreach (array('0', '-1', '1.5', str_repeat('9', 60), array('7')) as $invalid_days) {
    $_POST['notifications']['room_email_review_days'] = $invalid_days;
    expect(save_settings() === 'settings_invalid_room_review' && $options['calsys_settings'] === $saved,
        'Invalid link durations must not overwrite settings');
}
$_POST['notifications']['room_email_review_days'] = '35';
$_POST['notifications']['room_email_review_duration'] = 'invalid';
expect(save_settings() === 'settings_invalid_room_review' && $options['calsys_settings'] === $saved, 'Invalid duration modes must not overwrite settings');
$_POST['notifications']['room_email_review_duration'] = 'days';
$_POST['calendar_display']['show_filter_heading'] = array('1');
expect(save_settings() === 'settings_invalid' && $options['calsys_settings'] === $saved, 'Invalid filter heading input must not overwrite settings');
unset($_POST['calendar_display']['show_filter_heading']);
$_POST['weekly_hours'][1]['mode'] = 'unrestricted';
expect(save_settings() === 'settings_invalid_hours', 'Times for an unrestricted day must produce an error');
expect($options['calsys_settings'] === $saved, 'Invalid hours must not overwrite saved settings');
$_POST['weekly_hours'][1]['mode'] = 'open';
$_POST['holiday_hours'][0]['mode'] = 'closed';
expect(save_settings() === 'settings_invalid_hours', 'Times for a closed special date must produce an error');
expect($options['calsys_settings'] === $saved, 'Invalid special hours must not overwrite saved settings');
$_POST['holiday_hours'][0]['mode'] = 'open';
$_POST['email_settings']['templates']['room_denied']['body'] = 'No comment placeholder';
expect(save_settings() === 'settings_invalid_email_templates', 'Room decisions must preserve a place for comments');
expect($options['calsys_settings'] === $saved, 'Invalid email templates must not overwrite saved settings');
$_POST['email_settings']['templates']['room_denied']['body'] = '{message}';
$_POST['notifications']['email_event_registrant'] = '1';
unset($_POST['notifications']['email_event_reminders']);
expect(save_settings() === 'settings_saved', 'Each email option must be independently configurable');
expect($options['calsys_settings']['notifications']['email_event_registrant'] === 1 &&
    $options['calsys_settings']['notifications']['email_event_reminders'] === 0, 'Saving one option must not enable the other');
$_GET = array();
ob_start();
calsys_admin_settings_page();
$html = ob_get_clean();
expect(strpos($html, 'href="#calsys-settings-event-emails"') !== false &&
    strpos($html, 'id="calsys-settings-event-emails"') < strpos($html, 'id="calsys-settings-calendars"'), 'Event email settings must be easy to find at the top');
expect(substr_count($html, 'name="notifications[email_event_registrant]"') === 1 &&
    substr_count($html, 'name="notifications[email_event_reminders]"') === 1, 'Each email setting must have exactly one checkbox');
preg_match('/<section[^>]*id="calsys-settings-event-emails"[^>]*>(.*?)<\/section>/s', $html, $section);
expect(strpos($section[1], 'notifications[email_event_registrant]') !== false &&
    strpos($section[1], 'notifications[email_event_reminders]') !== false &&
    strpos($section[1], 'Registrants must opt in') !== false &&
    strpos($section[1], 'type="submit"') !== false, 'The event email panel must contain both controls, opt-in guidance, and a save button');
$_POST['notifications']['room_request_recipients'] = implode(',', array_map(function ($id) { return 'person' . $id . '@example.org'; }, range(1, 10)));
expect(save_settings() === 'settings_saved' && count($options['calsys_settings']['notifications']['room_request_recipients']) === 10,
    'Ten room request recipients must be accepted');
$_POST['notifications']['room_request_recipients'] = '';
expect(save_settings() === 'settings_saved' && $options['calsys_settings']['notifications']['room_request_recipients'] === array(),
    'Clearing the list must restore the staff-address fallback');
$_POST['notifications']['room_email_review_days'] = '3650';
expect(save_settings() === 'settings_saved' && $options['calsys_settings']['notifications']['room_email_review_days'] === 3650,
    'Durations must not have an arbitrary 90-day limit');
unset($_POST['notifications']['room_email_review_enabled']);
$_POST['notifications']['room_email_review_duration'] = 'never';
expect(save_settings() === 'settings_saved' && $options['calsys_settings']['notifications']['room_email_review_enabled'] === 0 &&
    $options['calsys_settings']['notifications']['room_email_review_days'] === 0, 'Disabled and never-expiring link settings must save');
$process = proc_open(array(PHP_BINARY, __FILE__, 'reload-never'), array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
expect(is_resource($process), 'Could not start never-expiring settings reload test');
fwrite($pipes[0], json_encode($options));
fclose($pipes[0]);
$output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
expect(proc_close($process) === 0, 'Never-expiring settings reload failed: ' . $output);
echo "Settings room hours passed.\n";
