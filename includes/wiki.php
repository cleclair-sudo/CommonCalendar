<?php
if (!defined('ABSPATH')) {
    exit;
}

function calsys_wiki_enabled() {
    return get_option('calsys_wiki_enabled', '0') === '1';
}

function calsys_wiki_require_access() {
    if (!current_user_can('calsys_manage_wiki')) {
        wp_die(esc_html__('The wiki is disabled or you do not have calendar staff access.', 'community-calendar-study-rooms'), '', array('response' => 403));
    }
}

function calsys_wiki_capabilities($caps) {
    foreach ($caps as $key => $cap) {
        if ($cap === 'calsys_manage_wiki') {
            $caps[$key] = calsys_wiki_enabled() ? 'manage_calendar_system' : 'do_not_allow';
        }
    }
    return $caps;
}
add_filter('map_meta_cap', 'calsys_wiki_capabilities');

function calsys_wiki_register() {
    register_post_type('calsys_wiki', array(
        'labels' => array(
            'name' => __('Wiki pages', 'community-calendar-study-rooms'),
            'singular_name' => __('Wiki page', 'community-calendar-study-rooms'),
            'add_new_item' => __('Add wiki page', 'community-calendar-study-rooms'),
            'edit_item' => __('Edit wiki page', 'community-calendar-study-rooms'),
            'all_items' => __('All wiki pages', 'community-calendar-study-rooms'),
        ),
        'public' => false,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'show_ui' => calsys_wiki_enabled(),
        'show_in_menu' => false,
        'show_in_rest' => false,
        'rewrite' => false,
        'query_var' => false,
        'supports' => array('title', 'editor', 'revisions', 'author'),
        'map_meta_cap' => false,
        'capabilities' => array_fill_keys(array(
            'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts',
            'delete_posts', 'publish_posts', 'read_private_posts', 'delete_private_posts',
            'delete_published_posts', 'delete_others_posts', 'edit_private_posts',
            'edit_published_posts', 'create_posts', 'read',
        ), 'calsys_manage_wiki'),
    ));
    register_taxonomy('calsys_wiki_folder', 'calsys_wiki', array(
        'labels' => array(
            'name' => __('Wiki folders', 'community-calendar-study-rooms'),
            'singular_name' => __('Wiki folder', 'community-calendar-study-rooms'),
            'add_new_item' => __('Add wiki folder', 'community-calendar-study-rooms'),
            'parent_item' => __('Parent folder', 'community-calendar-study-rooms'),
        ),
        'public' => false,
        'hierarchical' => true,
        'show_ui' => calsys_wiki_enabled(),
        'show_admin_column' => true,
        'show_in_rest' => false,
        'show_in_nav_menus' => false,
        'rewrite' => false,
        'capabilities' => array_fill_keys(array('manage_terms', 'edit_terms', 'delete_terms', 'assign_terms'), 'calsys_manage_wiki'),
    ));
}
add_action('init', 'calsys_wiki_register');

function calsys_wiki_settings_panel() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $notice = sanitize_key(wp_unslash($_GET['calsys_wiki_notice'] ?? ''));
    ?>
    <section class="calsys-settings-card" id="calsys-settings-wiki">
        <h2><?php esc_html_e('Wiki', 'community-calendar-study-rooms'); ?></h2>
        <p><?php esc_html_e('Enable an internal documentation wiki in the WordPress dashboard. Calendar staff can read and edit all pages, organize nested folders, and upload private attachments. Disabling it hides the wiki and blocks access without deleting documents or files.', 'community-calendar-study-rooms'); ?></p>
        <?php if ($notice === 'saved') : ?>
            <p role="status"><?php esc_html_e('The wiki setting was saved.', 'community-calendar-study-rooms'); ?></p>
        <?php elseif ($notice === 'error') : ?>
            <p role="alert"><?php esc_html_e('The wiki setting could not be saved. Please contact the site administrator.', 'community-calendar-study-rooms'); ?></p>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('calsys_wiki_setting'); ?>
            <input type="hidden" name="action" value="calsys_wiki_setting">
            <label>
                <input type="checkbox" name="wiki_enabled" value="1" <?php checked(calsys_wiki_enabled()); ?>>
                <?php esc_html_e('Enable the wiki for calendar staff', 'community-calendar-study-rooms'); ?>
            </label>
            <p><button type="submit" class="button"><?php esc_html_e('Save wiki setting', 'community-calendar-study-rooms'); ?></button></p>
        </form>
    </section>
    <?php
}

function calsys_wiki_save_setting() {
    calsys_require_staff();
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Only administrators can enable or disable the wiki.', 'community-calendar-study-rooms'), '', array('response' => 403));
    }
    check_admin_referer('calsys_wiki_setting');
    $enabled = isset($_POST['wiki_enabled']) && $_POST['wiki_enabled'] === '1' ? '1' : '0';
    update_option('calsys_wiki_enabled', $enabled);
    $notice = get_option('calsys_wiki_enabled') === $enabled ? 'saved' : 'error';
    if ($notice === 'error') {
        error_log('CommonCalendar: unable to save wiki setting.');
    }
    $url = add_query_arg(array('page' => 'calsys-settings', 'calsys_wiki_notice' => $notice), admin_url('admin.php'));
    wp_safe_redirect($url . '#calsys-settings-wiki');
    exit;
}
add_action('admin_post_calsys_wiki_setting', 'calsys_wiki_save_setting');

function calsys_wiki_assets() {
    $screen = get_current_screen();
    if ($screen && ($screen->post_type === 'calsys_wiki' || ($_GET['page'] ?? '') === 'calsys-wiki')) {
        wp_enqueue_style('calsys-wiki', plugins_url('assets/calendar-wiki.css', CALSYS_PLUGIN_FILE), array(), CALSYS_VERSION);
    }
}
add_action('admin_enqueue_scripts', 'calsys_wiki_assets');

function calsys_wiki_parent_menu($menu) {
    $screen = get_current_screen();
    return $screen && $screen->post_type === 'calsys_wiki' ? 'calsys-home' : $menu;
}
add_filter('parent_file', 'calsys_wiki_parent_menu');

function calsys_wiki_submenu($menu) {
    $screen = get_current_screen();
    return $screen && $screen->post_type === 'calsys_wiki' ? 'calsys-wiki' : $menu;
}
add_filter('submenu_file', 'calsys_wiki_submenu');

function calsys_admin_wiki_page() {
    calsys_wiki_require_access();
    $folder = absint($_GET['wiki_folder'] ?? 0);
    if ($folder) {
        $term = get_term($folder, 'calsys_wiki_folder');
        if (!$term || is_wp_error($term)) {
            wp_die(esc_html__('That wiki folder does not exist.', 'community-calendar-study-rooms'), '', array('response' => 404));
        }
    }
    ?>
    <div class="wrap calsys-wiki">
        <h1><?php esc_html_e('Wiki', 'community-calendar-study-rooms'); ?></h1>
        <p><?php esc_html_e('Internal documentation for calendar staff. Pages and attachments are not published on the website.', 'community-calendar-study-rooms'); ?></p>
        <p class="calsys-wiki-actions">
            <a class="button button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=calsys_wiki')); ?>"><?php esc_html_e('New page', 'community-calendar-study-rooms'); ?></a>
            <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=calsys_wiki')); ?>"><?php esc_html_e('Manage pages and revisions', 'community-calendar-study-rooms'); ?></a>
            <a class="button" href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=calsys_wiki_folder&post_type=calsys_wiki')); ?>"><?php esc_html_e('Manage folders', 'community-calendar-study-rooms'); ?></a>
        </p>
        <div class="calsys-wiki-layout">
            <aside class="calsys-wiki-sidebar">
                <?php calsys_wiki_filters($folder); ?>
                <?php calsys_wiki_page_list($folder); ?>
            </aside>
            <main class="calsys-wiki-document">
                <?php calsys_wiki_document(absint($_GET['wiki_document'] ?? 0)); ?>
            </main>
        </div>
    </div>
    <?php
}

function calsys_wiki_filters($folder) {
    $terms = get_terms(array('taxonomy' => 'calsys_wiki_folder', 'hide_empty' => false));
    if (is_wp_error($terms)) {
        error_log('CommonCalendar: unable to load wiki folders: ' . $terms->get_error_message());
        echo '<p role="alert">' . esc_html__('Wiki folders could not be loaded.', 'community-calendar-study-rooms') . '</p>';
        return;
    }
    ?>
    <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
        <input type="hidden" name="page" value="calsys-wiki">
        <label for="calsys-wiki-folder"><?php esc_html_e('Folder', 'community-calendar-study-rooms'); ?></label>
        <?php wp_dropdown_categories(array(
            'taxonomy' => 'calsys_wiki_folder', 'name' => 'wiki_folder', 'id' => 'calsys-wiki-folder',
            'hide_empty' => false, 'hierarchical' => true, 'selected' => $folder,
            'show_option_all' => __('All folders', 'community-calendar-study-rooms'),
        )); ?>
        <label for="calsys-wiki-search"><?php esc_html_e('Search pages', 'community-calendar-study-rooms'); ?></label>
        <input id="calsys-wiki-search" type="search" name="wiki_search" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_GET['wiki_search'] ?? ''))); ?>">
        <button class="button" type="submit"><?php esc_html_e('Browse', 'community-calendar-study-rooms'); ?></button>
    </form>
    <?php
}

function calsys_wiki_page_list($folder) {
    global $wpdb;
    $args = array(
        'post_type' => 'calsys_wiki',
        'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
        'posts_per_page' => 20,
        'paged' => max(1, absint($_GET['wiki_index'] ?? 1)),
        'orderby' => 'title', 'order' => 'ASC',
        's' => sanitize_text_field(wp_unslash($_GET['wiki_search'] ?? '')),
    );
    if ($folder) {
        $args['tax_query'] = array(array('taxonomy' => 'calsys_wiki_folder', 'field' => 'term_id', 'terms' => $folder));
    }
    $query = new WP_Query($args);
    if ($wpdb->last_error) {
        error_log('CommonCalendar: unable to load wiki pages: ' . $wpdb->last_error);
        echo '<p role="alert">' . esc_html__('Wiki pages could not be loaded.', 'community-calendar-study-rooms') . '</p>';
        return;
    }
    echo '<h2>' . esc_html__('Pages', 'community-calendar-study-rooms') . '</h2><ul>';
    foreach ($query->posts as $page) {
        $url = add_query_arg('wiki_document', $page->ID);
        $title = $page->post_title !== '' ? $page->post_title : __('Untitled page', 'community-calendar-study-rooms');
        $current = absint($_GET['wiki_document'] ?? 0) === $page->ID ? ' aria-current="page"' : '';
        echo '<li><a href="' . esc_url($url) . '"' . $current . '>' . esc_html($title) . '</a></li>';
    }
    echo '</ul>';
    if (!$query->posts) {
        echo '<p>' . esc_html__('No pages found. Create a page or change your filters.', 'community-calendar-study-rooms') . '</p>';
    }
    if ($query->max_num_pages > 1) {
        echo wp_kses_post(paginate_links(array(
            'base' => add_query_arg('wiki_index', '%#%', remove_query_arg('wiki_document')),
            'format' => '', 'total' => $query->max_num_pages, 'current' => $args['paged'],
        )));
    }
}

function calsys_wiki_document($id) {
    if (!$id) {
        echo '<p>' . esc_html__('Choose a page to read, or create a new document.', 'community-calendar-study-rooms') . '</p>';
        return;
    }
    $page = get_post($id);
    if (!$page || $page->post_type !== 'calsys_wiki' || in_array($page->post_status, array('trash', 'auto-draft'), true) || !current_user_can('read_post', $id)) {
        echo '<p role="alert">' . esc_html__('That wiki page is unavailable.', 'community-calendar-study-rooms') . '</p>';
        return;
    }
    $title = $page->post_title !== '' ? $page->post_title : __('Untitled page', 'community-calendar-study-rooms');
    echo '<h2>' . esc_html($title) . '</h2>';
    echo '<p><a class="button" href="' . esc_url(admin_url('post.php?post=' . $id . '&action=edit')) . '">' . esc_html__('Edit document', 'community-calendar-study-rooms') . '</a></p>';
    echo '<p>' . esc_html(sprintf(__('Last updated: %s', 'community-calendar-study-rooms'), mysql2date(get_option('date_format') . ' g:i A', $page->post_modified))) . '</p>';
    echo '<div class="calsys-wiki-content">' . wp_kses_post(wpautop($page->post_content)) . '</div>';
    calsys_wiki_file_list($id, false);
}

function calsys_wiki_editor_settings($settings) {
    if (!is_admin()) {
        return $settings;
    }
    $screen = get_current_screen();
    if ($screen && $screen->post_type === 'calsys_wiki') {
        $settings['media_buttons'] = false;
    }
    return $settings;
}
add_filter('wp_editor_settings', 'calsys_wiki_editor_settings');

function calsys_wiki_attachment_box($post) {
    add_meta_box('calsys-wiki-files', __('Private attachments', 'community-calendar-study-rooms'), 'calsys_wiki_attachment_fields', 'calsys_wiki', 'normal');
}
add_action('add_meta_boxes_calsys_wiki', 'calsys_wiki_attachment_box');

function calsys_wiki_form_upload($post) {
    if ($post->post_type === 'calsys_wiki') {
        echo ' enctype="multipart/form-data"';
    }
}
add_action('post_edit_form_tag', 'calsys_wiki_form_upload');

function calsys_wiki_attachment_fields($post) {
    wp_nonce_field('calsys_wiki_files_' . $post->ID, 'calsys_wiki_files_nonce');
    calsys_wiki_file_list($post->ID, true);
    ?>
    <p><label>
        <?php esc_html_e('Add an attachment', 'community-calendar-study-rooms'); ?>
        <input type="file" name="calsys_wiki_attachment" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.txt,.doc,.docx,.xls,.xlsx,.ppt,.pptx">
    </label></p>
    <p><?php esc_html_e('PDF, JPG, PNG, GIF, WebP, plain text, Word, Excel, or PowerPoint; up to 5 MB per file, subject to your host upload limit. Save the page to upload or remove attachments. Add more files with later saves. Copy an attachment link into the document if needed; downloads always require wiki access. Do not store patron information.', 'community-calendar-study-rooms'); ?></p>
    <?php
}

function calsys_wiki_file_list($post_id, $editing) {
    global $wpdb;
    $files = $wpdb->get_results($wpdb->prepare(
        'SELECT id, file_name FROM ' . calsys_table('wiki_files') . ' WHERE post_id = %d ORDER BY id',
        $post_id
    ));
    if (!is_array($files) || $wpdb->last_error) {
        error_log('CommonCalendar: unable to load wiki attachments: ' . $wpdb->last_error);
        echo '<p role="alert">' . esc_html__('Attachments could not be loaded.', 'community-calendar-study-rooms') . '</p>';
        return;
    }
    if (!$files) {
        return;
    }
    echo '<h3>' . esc_html__('Attachments', 'community-calendar-study-rooms') . '</h3><ul>';
    foreach ($files as $file) {
        $url = add_query_arg(array('action' => 'calsys_wiki_download', 'file_id' => $file->id), admin_url('admin-post.php'));
        echo '<li><a href="' . esc_url($url) . '">' . esc_html($file->file_name) . '</a>';
        if ($editing) {
            echo ' <label><input type="checkbox" name="calsys_wiki_delete_files[]" value="' . esc_attr($file->id) . '"> ' . esc_html__('Remove on save', 'community-calendar-study-rooms') . '</label>';
        }
        echo '</li>';
    }
    echo '</ul>';
}

function calsys_wiki_file_mimes() {
    return array(
        'pdf' => 'application/pdf', 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'txt' => 'text/plain',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    );
}

function calsys_wiki_file_error($post_id, $message, $detail = '') {
    error_log('CommonCalendar: wiki attachment save failed for page ' . $post_id . ': ' . $message . ' ' . $detail);
    set_transient('calsys_wiki_error_' . get_current_user_id(), $message, 5 * MINUTE_IN_SECONDS);
}

function calsys_wiki_save_files($post_id) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || !isset($_POST['calsys_wiki_files_nonce'])) {
        return;
    }
    if (!current_user_can('edit_post', $post_id) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['calsys_wiki_files_nonce'])), 'calsys_wiki_files_' . $post_id)) {
        calsys_wiki_file_error($post_id, __('Attachment changes were not authorized. Reload the editor and try again.', 'community-calendar-study-rooms'));
        return;
    }
    $file = calsys_private_attachment('calsys_wiki_attachment', calsys_wiki_file_mimes());
    if (is_wp_error($file)) {
        calsys_wiki_file_error($post_id, $file->get_error_message());
        return;
    }
    global $wpdb;
    if ($file['file_name'] !== '') {
        $data = array_merge(array('post_id' => $post_id, 'user_id' => get_current_user_id(), 'created_at' => current_time('mysql')), $file);
        if ($wpdb->insert(calsys_table('wiki_files'), $data, array('%d', '%d', '%s', '%s', '%s', '%s')) === false) {
            calsys_wiki_file_error($post_id, __('The attachment could not be saved. Please contact the site administrator.', 'community-calendar-study-rooms'), $wpdb->last_error);
            return;
        }
    }
    $remove = $_POST['calsys_wiki_delete_files'] ?? array();
    if (!is_array($remove)) {
        calsys_wiki_file_error($post_id, __('Invalid attachment removal selection.', 'community-calendar-study-rooms'));
        return;
    }
    foreach (array_unique(array_map('absint', $remove)) as $id) {
        $deleted = $wpdb->delete(calsys_table('wiki_files'), array('id' => $id, 'post_id' => $post_id), array('%d', '%d'));
        if ($deleted === false) {
            calsys_wiki_file_error($post_id, __('An attachment could not be removed. Please contact the site administrator.', 'community-calendar-study-rooms'), $wpdb->last_error);
            return;
        }
        if (!$deleted) {
            calsys_wiki_file_error($post_id, __('A selected attachment no longer exists on this page. Reload the editor before removing files.', 'community-calendar-study-rooms'));
            return;
        }
    }
}
add_action('save_post_calsys_wiki', 'calsys_wiki_save_files');

function calsys_wiki_file_notice() {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'calsys_wiki') {
        return;
    }
    $key = 'calsys_wiki_error_' . get_current_user_id();
    $message = get_transient($key);
    if ($message) {
        delete_transient($key);
        echo '<div class="notice notice-error"><p>' . esc_html__('The document save was processed, but attachment changes failed: ', 'community-calendar-study-rooms') . esc_html($message) . '</p></div>';
    }
}
add_action('admin_notices', 'calsys_wiki_file_notice');

function calsys_wiki_download() {
    calsys_wiki_require_access();
    global $wpdb;
    $id = absint($_GET['file_id'] ?? 0);
    $file = $wpdb->get_row($wpdb->prepare('SELECT post_id, file_name, file_data FROM ' . calsys_table('wiki_files') . ' WHERE id = %d', $id));
    if ($wpdb->last_error) {
        error_log('CommonCalendar: unable to load wiki attachment: ' . $wpdb->last_error);
        wp_die(esc_html__('The attachment could not be loaded.', 'community-calendar-study-rooms'), '', array('response' => 500));
    }
    $page = $file ? get_post($file->post_id) : null;
    if (!$page || $page->post_type !== 'calsys_wiki' || in_array($page->post_status, array('trash', 'auto-draft'), true) || !current_user_can('read_post', $page->ID)) {
        wp_die(esc_html__('That attachment is unavailable.', 'community-calendar-study-rooms'), '', array('response' => 404));
    }
    calsys_private_file_download($file->file_name, $file->file_data);
}
add_action('admin_post_calsys_wiki_download', 'calsys_wiki_download');
add_action('admin_post_nopriv_calsys_wiki_download', 'calsys_wiki_download');

function calsys_wiki_delete_files($post_id, $post) {
    if ($post->post_type !== 'calsys_wiki') {
        return;
    }
    global $wpdb;
    if ($wpdb->delete(calsys_table('wiki_files'), array('post_id' => $post_id), array('%d')) === false) {
        error_log('CommonCalendar: unable to delete wiki attachments: ' . $wpdb->last_error);
        wp_die(esc_html__('Wiki attachments could not be deleted. The page was not permanently deleted. Please contact the site administrator.', 'community-calendar-study-rooms'), '', array('response' => 500));
    }
}
add_action('before_delete_post', 'calsys_wiki_delete_files', 10, 2);
