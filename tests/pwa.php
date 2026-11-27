<?php
// Run with: php tests/pwa.php
define('ABSPATH', __DIR__ . '/');
define('CALSYS_PLUGIN_FILE', __FILE__);
function add_action() {}
function add_shortcode() {}
function __($value) { return $value; }
function esc_html__($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_attr__($value) { return esc_html__($value); }
function esc_url($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function wp_parse_url($url, $component) { return parse_url($url, $component); }
function home_url($path) { return 'https://example.org/site' . $path; }
function plugins_url($path) { return 'https://example.org/plugins/' . $path; }
function is_page() { return $GLOBALS['is_page']; }
function get_queried_object() { return $GLOBALS['page']; }
function has_shortcode($content, $shortcode) { return strpos($content, '[' . $shortcode . ']') !== false; }
function get_permalink($page) { return $page->url; }
function current_user_can($capability) { return $GLOBALS['staff']; }
function add_query_arg($key, $value, $url) { return $url . '?' . $key . '=' . $value; }
function calsys_staff_calendar_shortcode() { return $GLOBALS['staff'] ? 'STAFF CALENDAR' : 'LOGIN REQUIRED'; }
function calsys_rooms_calendar_shortcode() { return 'ROOM CALENDAR'; }
function expect($condition, $message) {
    if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); }
}
require __DIR__ . '/../includes/pwa.php';

$url = 'https://example.org/site/staff-app/';
$is_page = true;
$page = (object) array('post_status' => 'publish', 'post_password' => '', 'post_content' => '[calendar_staff_app]', 'url' => $url);
expect(calsys_pwa_page_url() === $url, 'The published app page must be detected');
expect(calsys_pwa_has_scoped_url($url), 'A dedicated trailing-slash page must have a scoped worker');
foreach (array('https://example.org/site/', 'https://example.org/site/staff-app', 'https://example.org/site/?page_id=7', 'https://example.org/site/staff-app/?id=7') as $invalid) {
    expect(!calsys_pwa_has_scoped_url($invalid), 'A root, query-string or non-trailing-slash URL must not be used as an app scope');
}
$page->post_password = 'protected';
expect(calsys_pwa_page_url() === '', 'Password-protected pages must retain normal WordPress handling');
$page->post_password = '';
$page->post_status = 'draft';
expect(calsys_pwa_page_url() === '', 'Draft pages must retain normal WordPress handling');
$page->post_status = 'publish';
$page->post_content = 'other content';
expect(calsys_pwa_page_url() === '', 'Ordinary pages must not become app pages');
$page->post_content = '[calendar_staff_app]';
$is_page = false;
expect(calsys_pwa_page_url() === '', 'The shortcode must not replace posts');

$manifest = calsys_pwa_manifest($url);
expect($manifest['start_url'] === $url && $manifest['scope'] === $url && $manifest['display'] === 'standalone', 'The manifest must launch within the dedicated page scope');
expect(array_column($manifest['icons'], 'sizes') === array('192x192', '512x512'), 'The manifest must include both install icon sizes');
$staff = false;
$_GET = array('calsys_app_view' => 'rooms');
expect(calsys_pwa_content($url) === 'LOGIN REQUIRED', 'Neither calendar may render without staff access');
$staff = true;
$_GET = array();
$content = calsys_pwa_content($url);
expect(strpos($content, 'STAFF CALENDAR') !== false && strpos($content, 'ROOM CALENDAR') === false, 'The staff tab must show only the staff calendar');
expect(strpos($content, 'calsys_app_view=rooms') !== false && strpos($content, 'aria-current="page"') !== false, 'The app must offer the room tab and identify the current tab');
$_GET = array('calsys_app_view' => 'rooms');
$content = calsys_pwa_content($url);
expect(strpos($content, 'ROOM CALENDAR') !== false && strpos($content, 'STAFF CALENDAR') === false, 'The room tab must show only the room calendar');
$_GET = array('calsys_app_view' => array('rooms'));
expect(strpos(calsys_pwa_content($url), 'STAFF CALENDAR') !== false, 'Invalid tab input must not select the room calendar');
echo "Staff app tests passed.\n";
