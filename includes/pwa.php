<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Identifies a dedicated published app page. */
function calsys_pwa_page_url() {
    if (!is_page()) {
        return '';
    }
    $page = get_queried_object();
    if (!$page || $page->post_status !== 'publish' || $page->post_password !== '' ||
        !has_shortcode($page->post_content, 'calendar_staff_app')) {
        return '';
    }
    return get_permalink($page);
}

/** A trailing-slash permalink confines the service worker to the app page. */
function calsys_pwa_has_scoped_url($url) {
    $path = wp_parse_url($url, PHP_URL_PATH);
    $home_path = wp_parse_url(home_url('/'), PHP_URL_PATH);
    return is_string($path) && rtrim($path, '/') !== rtrim((string) $home_path, '/') &&
        substr($path, -1) === '/' &&
        !wp_parse_url($url, PHP_URL_QUERY) && !wp_parse_url($url, PHP_URL_FRAGMENT);
}

/** Returns the public manifest, without any calendar or account data. */
function calsys_pwa_manifest($url) {
    return array(
        'name' => __('CommonCalendar Staff', 'community-calendar-study-rooms'),
        'short_name' => __('Calendar', 'community-calendar-study-rooms'),
        'id' => $url,
        'start_url' => $url,
        'scope' => $url,
        'display' => 'standalone',
        'background_color' => '#f4f7f8',
        'theme_color' => '#175c66',
        'icons' => array(
            array('src' => plugins_url('assets/calendar-app-192.png', CALSYS_PLUGIN_FILE), 'sizes' => '192x192', 'type' => 'image/png'),
            array('src' => plugins_url('assets/calendar-app-512.png', CALSYS_PLUGIN_FILE), 'sizes' => '512x512', 'type' => 'image/png'),
        ),
    );
}

/** Serves the dedicated app and its install resources without theme navigation. */
function calsys_pwa_serve() {
    $url = calsys_pwa_page_url();
    if (!$url) {
        return;
    }
    calsys_disable_calendar_page_cache();
    if (!headers_sent()) {
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('X-Robots-Tag: noindex, nofollow');
    }
    $resource = $_GET['calsys_app_resource'] ?? '';
    if ($resource === 'manifest' && calsys_pwa_has_scoped_url($url)) {
        header('Content-Type: application/manifest+json; charset=UTF-8');
        echo wp_json_encode(calsys_pwa_manifest($url), JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($resource === 'worker' && calsys_pwa_has_scoped_url($url)) {
        header('Content-Type: application/javascript; charset=UTF-8');
        echo 'importScripts(' . wp_json_encode(plugins_url('assets/calendar-app-worker.js', CALSYS_PLUGIN_FILE)) . ');';
        exit;
    }

    show_admin_bar(false);
    status_header(200);
    ?><!doctype html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#175c66">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <title><?php esc_html_e('CommonCalendar Staff', 'community-calendar-study-rooms'); ?></title>
        <?php if (calsys_pwa_has_scoped_url($url)) : ?>
            <link rel="manifest" href="<?php echo esc_url(add_query_arg('calsys_app_resource', 'manifest', $url)); ?>">
            <link rel="apple-touch-icon" href="<?php echo esc_url(plugins_url('assets/calendar-app-192.png', CALSYS_PLUGIN_FILE)); ?>">
            <meta name="calsys-app-worker" content="<?php echo esc_url(add_query_arg('calsys_app_resource', 'worker', $url)); ?>">
            <meta name="calsys-app-scope" content="<?php echo esc_url($url); ?>">
        <?php endif; ?>
        <?php wp_head(); ?>
    </head>
    <body class="calsys-app">
        <?php wp_body_open(); ?>
        <main class="calsys-app-main">
            <header class="calsys-app-header"><h1><?php esc_html_e('CommonCalendar Staff', 'community-calendar-study-rooms'); ?></h1></header>
            <p class="calsys-app-warning" data-calsys-app-status
                data-insecure="<?php esc_attr_e('Installing the app requires HTTPS. Contact your website administrator.', 'community-calendar-study-rooms'); ?>"
                data-failed="<?php esc_attr_e('The app could not be installed for offline status. Contact your website administrator.', 'community-calendar-study-rooms'); ?>" role="alert" hidden></p>
            <?php if (!calsys_pwa_has_scoped_url($url)) : ?>
                <p class="calsys-app-warning" role="alert"><?php esc_html_e('The app requires a dedicated page with a trailing-slash permalink. Change the WordPress permalink settings before installing it.', 'community-calendar-study-rooms'); ?></p>
            <?php endif; ?>
            <?php echo calsys_pwa_content($url); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </main>
        <?php wp_footer(); ?>
    </body>
    </html><?php
    exit;
}
add_action('template_redirect', 'calsys_pwa_serve', 0);

/** Loads app-only assets on the dedicated page. */
function calsys_pwa_enqueue() {
    if (!calsys_pwa_page_url()) {
        return;
    }
    wp_enqueue_style('calsys-app', plugins_url('assets/calendar-app.css', CALSYS_PLUGIN_FILE), array('calsys-public'), CALSYS_VERSION);
    wp_enqueue_script('calsys-app', plugins_url('assets/calendar-app.js', CALSYS_PLUGIN_FILE), array(), CALSYS_VERSION, true);
}
add_action('wp_enqueue_scripts', 'calsys_pwa_enqueue');

/** Renders only calendars available to an authorized staff account. */
function calsys_pwa_content($url) {
    if (!current_user_can('manage_calendar_system')) {
        return calsys_staff_calendar_shortcode();
    }
    $view = sanitize_key(wp_unslash($_GET['calsys_app_view'] ?? ''));
    $tabs = array('' => __('Staff calendar', 'community-calendar-study-rooms'), 'rooms' => __('Room calendar', 'community-calendar-study-rooms'));
    if (!isset($tabs[$view])) {
        $view = '';
    }
    $output = '<nav class="calsys-app-tabs" aria-label="' . esc_attr__('Staff tools', 'community-calendar-study-rooms') . '">';
    foreach ($tabs as $key => $label) {
        $link = $key ? add_query_arg('calsys_app_view', $key, $url) : $url;
        $output .= '<a href="' . esc_url($link) . '"' . ($view === $key ? ' aria-current="page"' : '') . '>' . esc_html($label) . '</a>';
    }
    if (calsys_communication_allowed()) {
        $output .= '<button type="button" data-chat-toggle aria-controls="calsys-chat-panel" aria-expanded="false">' .
            esc_html__('Communication', 'community-calendar-study-rooms') . '</button>';
    }
    $output .= '</nav>';
    return $output . ($view === 'rooms' ? calsys_rooms_calendar_shortcode() : calsys_staff_calendar_shortcode());
}

/** Explains how to use the shortcode when it is embedded somewhere other than a dedicated page. */
function calsys_pwa_shortcode() {
    return '<p>' . esc_html__('Place this shortcode on its own published page to use the staff calendar app.', 'community-calendar-study-rooms') . '</p>';
}
add_shortcode('calendar_staff_app', 'calsys_pwa_shortcode');
