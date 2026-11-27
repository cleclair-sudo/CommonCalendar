<?php
// Run with: php tests/equipment-reports.php
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

class EquipmentTestDatabase {
    public $last_error = '';
    public $result = array();
    public $sql = '';
    public function prepare($sql, ...$args) { $this->sql = $sql; return $sql; }
    public function get_results($sql, $output = null) { return $this->result; }
}
function add_action() {}
function wp_unslash($value) { return $value; }
function wp_timezone() { return new DateTimeZone('America/New_York'); }
function __($value) { return $value; }
function esc_html__($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_html_e($value) { echo esc_html__($value); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function current_user_can() { return false; }
function calsys_get_settings() { return array('room_equipment_enabled' => $GLOBALS['room_equipment_enabled']); }
function mysql2date($format, $value) { return date($format, strtotime($value)); }
function get_option($name) { return 'Y-m-d'; }
function calsys_table($name) { return 'wp_calsys_' . $name; }
function expect($condition, $message) {
    if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); }
}

require __DIR__ . '/../includes/equipment.php';
require __DIR__ . '/../includes/reports.php';

$wpdb = new EquipmentTestDatabase();
$intervals = array(
    (object) array('starts_at' => '10:00', 'ends_at' => '11:00', 'quantity' => 2),
    (object) array('starts_at' => '11:00', 'ends_at' => '12:00', 'quantity' => 3),
    (object) array('starts_at' => '10:30', 'ends_at' => '11:30', 'quantity' => 1),
);
expect(calsys_equipment_peak($intervals) === 4, 'Peak must count simultaneous quantities without double-counting adjacent intervals');
expect(calsys_equipment_peak(array()) === 0, 'Empty reservations must have zero peak');

$_POST = array();
expect(calsys_posted_equipment() === null, 'Absent equipment fields must preserve existing reservations');
$_POST = array('equipment_present' => '1', 'equipment' => array('2' => '3'));
expect(calsys_posted_equipment() === false, 'Truncated equipment fields must not remove reservations');
$_POST['equipment_complete'] = '1';
expect(calsys_posted_equipment() === array(2 => 3), 'Valid quantities must be parsed');
$_POST['equipment']['2'] = '-1';
expect(calsys_posted_equipment() === false, 'Negative quantities must be rejected');

$room_equipment_enabled = 1;
$wpdb->result = array((object) array('id' => 2, 'name' => 'Projector', 'quantity' => 3, 'active' => 1));
$room_fields = calsys_room_equipment_fields();
expect(strpos($room_fields, '<details') !== false && strpos($room_fields, '<details open') === false &&
    strpos($room_fields, 'name="equipment[2]"') !== false, 'Enabled room equipment must appear in a collapsed section');
$_POST = array('equipment_present' => '1', 'equipment_complete' => '1', 'equipment' => array('2' => '2'));
expect(calsys_posted_room_equipment() === array(2 => 2), 'Enabled room equipment must accept requested quantities');
$room_equipment_enabled = 0;
expect(calsys_room_equipment_fields() === '', 'Disabled room equipment must not render controls');
expect(calsys_posted_room_equipment() === 'disabled', 'A stale room form must not add equipment while disabled');
$_POST['equipment']['2'] = '0';
expect(calsys_posted_room_equipment() === null, 'Disabled room equipment must preserve existing reservations on edit');
unset($_POST['equipment_complete']);
expect(calsys_posted_room_equipment() === false, 'Incomplete equipment submissions must still be rejected');
$_POST = array();
expect(calsys_posted_room_equipment() === null, 'Disabled forms without equipment fields must preserve existing reservations');

expect(calsys_report_dates('2024-02-29', '2024-03-01') === array('2024-02-29', '2024-03-01'), 'Leap day should be accepted');
expect(calsys_report_dates('2026-02-29', '2026-03-01') === false, 'Invalid dates must be rejected');
expect(calsys_report_dates('2026-03-02', '2026-03-01') === false, 'Reversed dates must be rejected');
expect(calsys_report_csv_cell(" \t=1+1") === "' \t=1+1", 'Spreadsheet formulas must be neutralized');
expect(calsys_report_csv_cell('Normal title') === 'Normal title', 'Ordinary CSV values must be preserved');
$wpdb->result = array(array('equipment' => 'Projector', 'starts_at' => '2026-01-05 13:37:00', 'ends_at' => '2026-01-05 14:00:00'));
$equipment_report = calsys_report_data('equipment', '2026-01-01', '2026-01-31');
expect(count($equipment_report['rows']) === 1 && $equipment_report['rows'][0]['starts_at'] === '2026-01-05 1:37 PM', 'Equipment report must format times in 12-hour form');
$wpdb->result = array(array('date' => '2026-01-05', 'start_time' => '00:00:00', 'end_time' => '12:00:00'));
$room_report = calsys_report_data('rooms', '2026-01-01', '2026-01-31');
expect($room_report['rows'][0]['start_time'] === '12:00 AM' && $room_report['rows'][0]['end_time'] === '12:00 PM', 'Room reports must distinguish midnight and noon');
$wpdb->result = array(array('equipment' => 'Projector', 'starts_at' => '2026-01-05 13:37:00', 'ends_at' => '2026-01-05 14:00:00'));
calsys_report_data('equipment', '2026-01-01', '2026-01-31');
expect(strpos($wpdb->sql, "r.status = 'approved'") !== false && strpos($wpdb->sql, 'r.starts_at < %s AND r.ends_at > %s') !== false, 'Equipment reports must include approved overlapping reservations');
$wpdb->result = array();
$wpdb->last_error = 'Simulated query failure';
expect(calsys_report_data('events', '2026-01-01', '2026-01-31') === false, 'Database errors must not appear as empty reports');
expect(calsys_equipment_for_source('room', 1) === false, 'Database errors must not appear as empty reservations');
echo "Equipment and report tests passed.\n";
