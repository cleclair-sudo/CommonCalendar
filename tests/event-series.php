<?php
// Run with: php tests/event-series.php
define('ABSPATH', __DIR__ . '/');
function wp_timezone() { return new DateTimeZone('America/New_York'); }
function calsys_table($name) { return 'wp_calsys_' . $name; }
function calsys_lock_linked_room_ids() { return true; }
function calsys_sync_equipment() { return 'saved'; }
function calsys_sync_linked_room_reservations($type, $id, $room, $start, $end, $allow, $initials) {
    $GLOBALS['linked_initials'][] = $initials;
    return 0;
}
function expect($condition, $message) {
    if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); }
}
require __DIR__ . '/../includes/event-series.php';

expect(calsys_shift_event_datetime('2030-03-03 10:00:00', 7 * 86400) === '2030-03-10 10:00:00', 'A date shift must preserve wall time across DST');
expect(calsys_shift_event_datetime('2030-03-10 01:30:00', 3600) === false, 'A shift into a nonexistent DST time must be rejected');
expect(calsys_shift_event_datetime('2030-11-03 10:00:00', -3600) === '2030-11-03 09:00:00', 'Negative time shifts must be supported');
class SeriesInitialsDatabase {
    public $last_error = '';
    public $updated = array();
    public function prepare($sql, ...$args) { return $sql; }
    public function get_row($sql) {
        return (object) array('id' => 1, 'recurrence_group' => 'group', 'starts_at' => '2026-12-01 10:00:00', 'ends_at' => '2026-12-01 11:00:00');
    }
    public function get_results($sql) {
        return array($this->get_row($sql), (object) array('id' => 2, 'starts_at' => '2026-12-08 10:00:00', 'ends_at' => '2026-12-08 11:00:00'));
    }
    public function delete() { return 1; }
    public function update($table, $data, $where) { $this->updated[] = $data; return 1; }
}
$wpdb = new SeriesInitialsDatabase();
$linked_initials = array();
$ids = calsys_update_event_series(1, 'all', array('starts_at' => '2026-12-01 10:00:00',
    'ends_at' => '2026-12-01 11:00:00', 'staff_initials' => 'AB'), 2, array());
expect($ids === array(1, 2) && $linked_initials === array('AB', 'AB'), 'Recurring edits must pass the editor initials to each linked room');
expect($wpdb->updated[0]['staff_initials'] === 'AB' && $wpdb->updated[1]['staff_initials'] === 'AB', 'Recurring edits must save initials on every updated event');
echo "Event series tests passed.\n";
