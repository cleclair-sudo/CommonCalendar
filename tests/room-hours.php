<?php
// Run with: php tests/room-hours.php
define('ABSPATH', __DIR__ . '/');
function wp_timezone() { return new DateTimeZone('America/New_York'); }
function add_action() {}
function calsys_table($name) { return 'wp_calsys_' . $name; }
function __($value) { return $value; }
function sanitize_email($value) { return $value; }
function get_option($name) { return 'admin@example.org'; }
function current_time($format) { return '2026-11-11 12:00:00'; }
function expect($condition, $message) {
    if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); }
}
require __DIR__ . '/../includes/room-hours.php';
require __DIR__ . '/../includes/room-validation.php';

$weekly = array_fill(0, 7, array('mode' => 'open', 'open' => '09:00', 'close' => '17:00'));
$weekly[0] = array('mode' => 'closed', 'open' => '', 'close' => '');
$holidays = array(
    array('date' => '2026-12-25', 'annual' => '1', 'mode' => 'closed'),
    array('date' => '2027-12-25', 'mode' => 'open', 'open' => '10:00', 'close' => '13:00'),
);
$schedule = calsys_normalize_room_schedule($weekly, $holidays);
expect($schedule !== false, 'Valid weekly and holiday hours were rejected');
$rules = array('weekly_hours' => $schedule['weekly'], 'holiday_hours' => $schedule['holidays']);
expect(calsys_room_is_within_hours('2026-12-21', '09:00', '17:00', $rules), 'Opening and closing boundaries must be allowed');
expect(!calsys_room_is_within_hours('2026-12-21', '08:45', '10:00', $rules), 'Bookings before opening must be rejected');
expect(!calsys_room_is_within_hours('2026-12-27', '10:00', '11:00', $rules), 'Closed weekdays must reject bookings');
expect(!calsys_room_is_within_hours('2028-12-25', '10:00', '11:00', $rules), 'Annual holiday closure must recur');
expect(calsys_room_is_within_hours('2027-12-25', '10:00', '13:00', $rules), 'Specific-date hours must override annual closures');
expect(!calsys_room_is_within_hours('2027-12-25', '12:00', '13:15', $rules), 'Special hours must enforce closing time');
expect(calsys_room_hours_for_date('2026-02-29', $rules) === false, 'Invalid dates must be rejected');
$holidays[] = array('date' => '2028-12-25', 'annual' => '1', 'mode' => 'closed');
expect(calsys_normalize_room_schedule($weekly, $holidays) === false, 'Duplicate yearly exceptions must be rejected');
$weekly[1] = array('mode' => 'open', 'open' => '17:00', 'close' => '09:00');
expect(calsys_normalize_room_schedule($weekly, array()) === false, 'Overnight or reversed hours must be rejected');
$weekly[1] = array('mode' => 'unrestricted', 'open' => '09:00', 'close' => '17:00');
expect(calsys_normalize_room_schedule($weekly, array(), true) === false, 'Submitted unrestricted hours must not silently discard entered times');
expect(calsys_normalize_room_schedule($weekly, array()) !== false, 'Loading existing schedules must remain compatible with unused times');
$weekly[1] = array('mode' => 'open', 'open' => '09:00', 'close' => '17:00');
expect(calsys_normalize_room_schedule($weekly, array(array('date' => '2026-12-25', 'mode' => 'closed', 'open' => '10:00')), true) === false, 'Submitted closed holiday must not silently discard entered times');
expect(calsys_normalize_room_schedule($weekly, array(array('date' => '2026-12-25', 'mode' => 'closed')), true) !== false, 'Closed days without times must remain valid');
expect(!calsys_room_duration_exceeds_limit('09:00', '11:00', 120), 'Bookings at the duration limit must be allowed');
expect(calsys_room_duration_exceeds_limit('09:00', '11:15', 120), 'Bookings over the duration limit must be rejected');
expect(!calsys_room_duration_exceeds_limit('09:00', '17:00', 0), 'Zero must leave booking duration unrestricted');
class LinkedInitialsDatabase {
    public $inserted = array();
    public function delete() { return 1; }
    public function prepare($sql, ...$args) { return $sql; }
    public function get_var($sql) { return 0; }
    public function insert($table, $data, $formats) {
        expect(count($data) === count($formats), 'Linked booking formats must match fields');
        $this->inserted[] = $data;
        return 1;
    }
}
$wpdb = new LinkedInitialsDatabase();
foreach (array('staff', 'event') as $source) {
    $result = calsys_sync_linked_room_reservations($source, 1, 2, '2026-12-01 23:00:00', '2026-12-02 01:00:00', false, 'XY');
    expect($result === 0, 'Linked room reservations should save');
}
expect(count($wpdb->inserted) === 4, 'Both sources should create reservations for each day');
foreach ($wpdb->inserted as $booking) {
    expect($booking['staff_initials'] === 'XY', 'Each linked room reservation must retain the source editor initials');
}
echo "Room opening hours passed.\n";
