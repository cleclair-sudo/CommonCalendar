<?php
if (!defined('ABSPATH')) {
    exit;
}

function calsys_communication_allowed() {
    return current_user_can('manage_calendar_system') &&
        (current_user_can('manage_options') || current_user_can('calsys_use_communication'));
}

function calsys_communication_assets() {
    if (!calsys_communication_allowed()) {
        return;
    }
    wp_enqueue_style('calsys-communication', plugins_url('assets/calendar-communication.css', CALSYS_PLUGIN_FILE), array(), CALSYS_VERSION);
    wp_enqueue_script('calsys-communication', plugins_url('assets/calendar-communication.js', CALSYS_PLUGIN_FILE), array(), CALSYS_VERSION, true);
    wp_localize_script('calsys-communication', 'calsysChat', array(
        'url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('calsys_communication'),
        'failed' => __('Communication could not be loaded or saved. Check your connection and refresh messages before resending; your message may already have been saved.', 'community-calendar-study-rooms'),
        'expired' => __('Communication access has expired or was removed. Reload the page or contact an administrator.', 'community-calendar-study-rooms'),
        'empty' => __('No messages yet. Start the conversation.', 'community-calendar-study-rooms'),
        'delete' => __('Delete message', 'community-calendar-study-rooms'),
        'confirm' => __('Delete this message and its attachment?', 'community-calendar-study-rooms'),
        'loading' => __('Loading messages...', 'community-calendar-study-rooms'),
        'history' => __('Reading older messages pauses automatic refresh. Use Refresh messages to return to the latest conversation.', 'community-calendar-study-rooms'),
        'sent' => __('Message sent.', 'community-calendar-study-rooms'),
        'noChannels' => __('An administrator needs to create a channel. Use Refresh messages after a channel is added.', 'community-calendar-study-rooms'),
    ));
}
add_action('wp_enqueue_scripts', 'calsys_communication_assets');
add_action('admin_enqueue_scripts', 'calsys_communication_assets');

function calsys_communication_settings_panel() {
    if (!current_user_can('manage_options')) {
        return;
    }
    global $wpdb;
    $users = get_users(array('capability' => 'manage_calendar_system', 'orderby' => 'display_name', 'order' => 'ASC'));
    $messages = array(
        'saved' => __('Communication access was saved.', 'community-calendar-study-rooms'),
        'error' => __('Communication access could not be saved. Please contact the site administrator.', 'community-calendar-study-rooms'),
    );
    $notice = sanitize_key(wp_unslash($_GET['calsys_communication_notice'] ?? ''));
    ?>
    <section class="calsys-settings-card" id="calsys-settings-communication">
        <h2><?php esc_html_e('Communication access', 'community-calendar-study-rooms'); ?></h2>
        <p><?php esc_html_e('Assign or remove communication access for calendar staff. Only administrators can change these settings. Administrators with calendar access always have access; removing staff access does not delete messages.', 'community-calendar-study-rooms'); ?></p>
        <?php if (isset($messages[$notice])) : ?>
            <p role="<?php echo $notice === 'error' ? 'alert' : 'status'; ?>"><?php echo esc_html($messages[$notice]); ?></p>
        <?php endif; ?>
        <?php if ($wpdb->last_error) : ?>
            <?php error_log('CommonCalendar: unable to load communication staff: ' . $wpdb->last_error); ?>
            <p role="alert"><?php esc_html_e('Staff accounts could not be loaded. Please contact the site administrator.', 'community-calendar-study-rooms'); ?></p>
        <?php elseif (!$users) : ?>
            <p><?php esc_html_e('No calendar staff accounts found.', 'community-calendar-study-rooms'); ?></p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Staff account', 'community-calendar-study-rooms'); ?></th>
                        <th scope="col"><?php esc_html_e('Communication access', 'community-calendar-study-rooms'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user) : ?>
                        <?php calsys_communication_access_row($user); ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
    <?php
}

function calsys_communication_access_row($user) {
    ?>
    <tr>
        <td><?php echo esc_html($user->display_name . ' (' . $user->user_login . ')'); ?></td>
        <td>
            <?php if (user_can($user, 'manage_options')) : ?>
                <?php esc_html_e('Always enabled for administrators', 'community-calendar-study-rooms'); ?>
            <?php elseif (!current_user_can('edit_user', $user->ID)) : ?>
                <?php esc_html_e('You do not have permission to edit this account.', 'community-calendar-study-rooms'); ?>
            <?php else : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('calsys_communication_access'); ?>
                    <input type="hidden" name="action" value="calsys_communication_access">
                    <input type="hidden" name="user_id" value="<?php echo esc_attr($user->ID); ?>">
                    <label>
                        <input type="checkbox" name="calsys_communication_access" value="1" <?php checked(user_can($user, 'calsys_use_communication')); ?>>
                        <?php esc_html_e('Enable communication', 'community-calendar-study-rooms'); ?>
                    </label>
                    <button type="submit" class="button"><?php esc_html_e('Save access', 'community-calendar-study-rooms'); ?></button>
                </form>
            <?php endif; ?>
        </td>
    </tr>
    <?php
}

function calsys_communication_access_redirect($notice) {
    $url = add_query_arg(array('page' => 'calsys-settings', 'calsys_communication_notice' => $notice), admin_url('admin.php'));
    wp_safe_redirect($url . '#calsys-settings-communication');
    exit;
}

function calsys_communication_save_access() {
    calsys_require_staff();
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Only administrators can assign communication access.', 'community-calendar-study-rooms'), '', array('response' => 403));
    }
    check_admin_referer('calsys_communication_access');
    $user_id = absint($_POST['user_id'] ?? 0);
    if (!current_user_can('edit_user', $user_id)) {
        wp_die(esc_html__('You do not have permission to edit this account.', 'community-calendar-study-rooms'), '', array('response' => 403));
    }
    $user = get_userdata($user_id);
    if (!$user || !user_can($user, 'manage_calendar_system') || user_can($user, 'manage_options')) {
        wp_die(esc_html__('Choose a non-administrator calendar staff account.', 'community-calendar-study-rooms'), '', array('response' => 400));
    }
    $enabled = isset($_POST['calsys_communication_access']) && $_POST['calsys_communication_access'] === '1';
    $user->add_cap('calsys_use_communication', $enabled);
    $saved = get_userdata($user_id);
    if (user_can($saved, 'calsys_use_communication') !== $enabled) {
        error_log('CommonCalendar: unable to save communication access for user ' . $user_id);
        calsys_communication_access_redirect('error');
    }
    calsys_communication_access_redirect('saved');
}
add_action('admin_post_calsys_communication_access', 'calsys_communication_save_access');

function calsys_communication_tools() {
    if (!current_user_can('manage_calendar_system')) {
        return;
    }
    if (is_admin()) {
        $page = sanitize_key(wp_unslash($_GET['page'] ?? ''));
        if (strpos($page, 'calsys-') !== 0) {
            return;
        }
    } else {
        $post = get_queried_object();
        if (!($post instanceof WP_Post)) {
            return;
        }
        $shortcodes = array('calendar_staff_app', 'calendar_staff_calendar', 'calendar_rooms', 'calendar_room_calendar', 'calendar_event_calendar', 'calendar_combined', 'calendar_system');
        $found = false;
        foreach ($shortcodes as $shortcode) {
            $found = $found || has_shortcode($post->post_content, $shortcode);
        }
        if (!$found) {
            return;
        }
    }
    echo calsys_render_tasks(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    if (calsys_communication_allowed()) {
        $right = calsys_tasks_enabled() ? ' calsys-communication-with-tasks' : '';
        echo '<button type="button" class="calsys-communication-launcher' . esc_attr($right) . '" data-chat-toggle aria-controls="calsys-chat-panel" aria-expanded="false">' .
            '<span aria-hidden="true">&#9993;</span> ' . esc_html__('Communication', 'community-calendar-study-rooms') . '</button>';
        ?>
        <section class="calsys-chat-panel" id="calsys-chat-panel" role="dialog" aria-labelledby="calsys-chat-title" hidden>
            <header class="calsys-chat-panel-heading">
                <h2 id="calsys-chat-title"><?php esc_html_e('Communication', 'community-calendar-study-rooms'); ?></h2>
                <button type="button" data-chat-close aria-label="<?php esc_attr_e('Close communication', 'community-calendar-study-rooms'); ?>">&#10005;</button>
            </header>
            <?php echo calsys_communication_workspace(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </section>
        <?php
    }
}
add_action('wp_footer', 'calsys_communication_tools');
add_action('admin_footer', 'calsys_communication_tools');

function calsys_admin_communication_page() {
    if (!calsys_communication_allowed()) {
        wp_die(esc_html__('An administrator must assign you communication access.', 'community-calendar-study-rooms'), '', array('response' => 403));
    }
    echo '<div class="wrap"><h1>' . esc_html__('Communication', 'community-calendar-study-rooms') . '</h1>';
    echo '<p>' . esc_html__('Use the Communication button to open your chatbox. Channels and messages load only when you open it.', 'community-calendar-study-rooms') . '</p>';
    echo '</div>';
}

function calsys_communication_channels() {
    global $wpdb;
    $channels = $wpdb->get_results('SELECT id, name FROM ' . calsys_table('channels') . ' ORDER BY name ASC');
    if (!is_array($channels) || $wpdb->last_error) {
        error_log('CommonCalendar: unable to load communication channels: ' . $wpdb->last_error);
        return new WP_Error('channels', __('Channels could not be loaded. Please contact the site administrator.', 'community-calendar-study-rooms'));
    }
    return $channels;
}

function calsys_communication_workspace() {
    if (!calsys_communication_allowed()) {
        return '';
    }
    ob_start();
    ?>
    <section class="calsys-chat" data-calsys-chat aria-label="<?php esc_attr_e('Staff communication', 'community-calendar-study-rooms'); ?>">
        <aside class="calsys-chat-channels">
            <h2><?php esc_html_e('Channels', 'community-calendar-study-rooms'); ?></h2>
            <p><?php esc_html_e('Shared with all assigned staff.', 'community-calendar-study-rooms'); ?></p>
            <nav data-chat-channels aria-label="<?php esc_attr_e('Communication channels', 'community-calendar-study-rooms'); ?>"></nav>
            <?php if (current_user_can('manage_options')) : ?>
                <form data-chat-create>
                    <label><?php esc_html_e('New channel', 'community-calendar-study-rooms'); ?><input name="name" maxlength="80" required></label>
                    <button type="submit"><?php esc_html_e('Create channel', 'community-calendar-study-rooms'); ?></button>
                </form>
                <p><a href="<?php echo esc_url(admin_url('admin.php?page=calsys-settings#calsys-settings-communication')); ?>"><?php esc_html_e('Manage communication access in CommonCalendar Settings.', 'community-calendar-study-rooms'); ?></a></p>
            <?php endif; ?>
        </aside>
        <div class="calsys-chat-main">
            <h2 data-chat-heading><?php esc_html_e('Choose a channel', 'community-calendar-study-rooms'); ?></h2>
            <p data-chat-status role="status" aria-live="polite"></p>
            <noscript><p><?php esc_html_e('Enable JavaScript to read and send messages.', 'community-calendar-study-rooms'); ?></p></noscript>
            <button type="button" data-chat-retry><?php esc_html_e('Refresh messages', 'community-calendar-study-rooms'); ?></button>
            <button type="button" data-chat-older hidden><?php esc_html_e('Load older messages', 'community-calendar-study-rooms'); ?></button>
            <div class="calsys-chat-messages" data-chat-messages role="region" aria-label="<?php esc_attr_e('Messages', 'community-calendar-study-rooms'); ?>" tabindex="0"></div>
            <form class="calsys-chat-compose" data-chat-send enctype="multipart/form-data">
                <fieldset disabled>
                    <label><?php esc_html_e('Message', 'community-calendar-study-rooms'); ?><textarea name="body" rows="3" maxlength="10000"></textarea></label>
                    <label><?php esc_html_e('Attachment (optional)', 'community-calendar-study-rooms'); ?><input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.txt"></label>
                    <small><?php esc_html_e('PDF, JPG, PNG, or plain text, up to 5 MB. Attachments are private to assigned staff. Do not share patron information.', 'community-calendar-study-rooms'); ?></small>
                    <button type="submit"><?php esc_html_e('Send', 'community-calendar-study-rooms'); ?></button>
                </fieldset>
            </form>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

function calsys_communication_db_error() {
    global $wpdb;
    error_log('CommonCalendar: communication database error: ' . $wpdb->last_error);
    wp_send_json_error(array('message' => __('Communication could not be loaded or saved. Please contact the site administrator.', 'community-calendar-study-rooms')), 500);
}

function calsys_communication_ajax() {
    if (!calsys_communication_allowed()) {
        wp_send_json_error(array('message' => __('You no longer have communication access.', 'community-calendar-study-rooms')), 403);
    }
    if (!check_ajax_referer('calsys_communication', 'nonce', false)) {
        wp_send_json_error(array('message' => __('Your session has expired. Reload the page.', 'community-calendar-study-rooms')), 403);
    }
    $operation = sanitize_key(wp_unslash($_POST['operation'] ?? ''));
    if ($operation === 'channels') {
        $channels = calsys_communication_channels();
        if (is_wp_error($channels)) {
            wp_send_json_error(array('message' => $channels->get_error_message()), 500);
        }
        wp_send_json_success($channels);
    }
    if ($operation === 'create') {
        calsys_communication_create_channel();
    }
    if ($operation === 'delete') {
        calsys_communication_delete_message();
    }
    global $wpdb;
    $channel_id = absint($_POST['channel_id'] ?? 0);
    $channel = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . calsys_table('channels') . ' WHERE id = %d', $channel_id));
    if ($wpdb->last_error) {
        calsys_communication_db_error();
    }
    if (!$channel) {
        wp_send_json_error(array('message' => __('Choose an existing channel.', 'community-calendar-study-rooms')), 404);
    }
    if ($operation === 'list') {
        calsys_communication_list_messages($channel_id);
    }
    if ($operation === 'send') {
        calsys_communication_send_message($channel_id);
    }
    wp_send_json_error(array('message' => __('Unknown communication action.', 'community-calendar-study-rooms')), 400);
}
add_action('wp_ajax_calsys_communication', 'calsys_communication_ajax');

function calsys_communication_logged_out() {
    wp_send_json_error(array('message' => __('Sign in again to use communication.', 'community-calendar-study-rooms')), 403);
}
add_action('wp_ajax_nopriv_calsys_communication', 'calsys_communication_logged_out');

function calsys_communication_create_channel() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Only administrators can create channels.', 'community-calendar-study-rooms')), 403);
    }
    $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    if ($name === '' || strlen($name) > 80) {
        wp_send_json_error(array('message' => __('Enter a channel name of up to 80 bytes.', 'community-calendar-study-rooms')), 400);
    }
    global $wpdb;
    $exists = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . calsys_table('channels') . ' WHERE name = %s', $name));
    if ($wpdb->last_error) {
        calsys_communication_db_error();
    }
    if ($exists) {
        wp_send_json_error(array('message' => __('That channel already exists.', 'community-calendar-study-rooms')), 409);
    }
    if ($wpdb->insert(calsys_table('channels'), array('name' => $name), array('%s')) === false) {
        calsys_communication_db_error();
    }
    wp_send_json_success(array('id' => (int) $wpdb->insert_id, 'name' => $name));
}

function calsys_communication_message_data($row) {
    $user = get_userdata($row->user_id);
    $download = '';
    if ($row->file_name !== '') {
        $download = add_query_arg(array(
            'action' => 'calsys_communication_download',
            'message_id' => $row->id,
            '_wpnonce' => wp_create_nonce('calsys_download_' . $row->id),
        ), admin_url('admin-post.php'));
    }
    return array(
        'id' => (int) $row->id,
        'author' => $user ? $user->display_name : __('Former staff member', 'community-calendar-study-rooms'),
        'body' => $row->body,
        'time' => mysql2date(get_option('date_format') . ' g:i A', $row->created_at),
        'download' => $download,
        'file' => $row->file_name,
        'can_delete' => (int) $row->user_id === get_current_user_id() || current_user_can('manage_options'),
    );
}

function calsys_communication_list_messages($channel_id) {
    global $wpdb;
    $before = absint($_POST['before'] ?? 0);
    $rows = $wpdb->get_results($wpdb->prepare(
        'SELECT id, user_id, body, file_name, created_at FROM ' . calsys_table('messages') .
        ' WHERE channel_id = %d' . ($before ? ' AND id < %d' : ' AND %d = 0') . ' ORDER BY id DESC LIMIT 51',
        $channel_id, $before
    ));
    if (!is_array($rows) || $wpdb->last_error) {
        calsys_communication_db_error();
    }
    $more = count($rows) > 50;
    $rows = array_slice($rows, 0, 50);
    wp_send_json_success(array(
        'messages' => array_map('calsys_communication_message_data', array_reverse($rows)),
        'more' => $more,
    ));
}

function calsys_communication_attachment() {
    $mimes = array('pdf' => 'application/pdf', 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'txt' => 'text/plain');
    return calsys_private_attachment('attachment', $mimes);
}

function calsys_communication_send_message($channel_id) {
    $body = trim(sanitize_textarea_field(wp_unslash($_POST['body'] ?? '')));
    if (strlen($body) > 10000) {
        wp_send_json_error(array('message' => __('Messages must be no longer than 10,000 bytes.', 'community-calendar-study-rooms')), 400);
    }
    $attachment = calsys_communication_attachment();
    if (is_wp_error($attachment)) {
        wp_send_json_error(array('message' => $attachment->get_error_message()), 400);
    }
    if ($body === '' && $attachment['file_name'] === '') {
        wp_send_json_error(array('message' => __('Enter a message or choose an attachment.', 'community-calendar-study-rooms')), 400);
    }
    global $wpdb;
    $data = array_merge(array(
        'channel_id' => $channel_id,
        'user_id' => get_current_user_id(),
        'body' => $body,
        'created_at' => current_time('mysql'),
    ), $attachment);
    if ($wpdb->insert(calsys_table('messages'), $data, array('%d', '%d', '%s', '%s', '%s', '%s', '%s')) === false) {
        calsys_communication_db_error();
    }
    $data['id'] = (int) $wpdb->insert_id;
    wp_send_json_success(calsys_communication_message_data((object) $data));
}

function calsys_communication_delete_message() {
    global $wpdb;
    $id = absint($_POST['message_id'] ?? 0);
    $where = array('id' => $id);
    if (!current_user_can('manage_options')) {
        $where['user_id'] = get_current_user_id();
    }
    $deleted = $wpdb->delete(calsys_table('messages'), $where);
    if ($deleted === false) {
        calsys_communication_db_error();
    }
    if (!$deleted) {
        wp_send_json_error(array('message' => __('That message no longer exists or is not yours.', 'community-calendar-study-rooms')), 404);
    }
    wp_send_json_success();
}

function calsys_communication_download() {
    if (!calsys_communication_allowed()) {
        wp_die(esc_html__('You do not have communication access.', 'community-calendar-study-rooms'), '', array('response' => 403));
    }
    $id = absint($_GET['message_id'] ?? 0);
    check_admin_referer('calsys_download_' . $id);
    global $wpdb;
    $file = $wpdb->get_row($wpdb->prepare('SELECT file_name, file_type, file_data FROM ' . calsys_table('messages') . ' WHERE id = %d', $id));
    if ($wpdb->last_error) {
        error_log('CommonCalendar: unable to load communication attachment: ' . $wpdb->last_error);
        wp_die(esc_html__('The attachment could not be loaded.', 'community-calendar-study-rooms'), '', array('response' => 500));
    }
    if (!$file || $file->file_name === '') {
        wp_die(esc_html__('That attachment no longer exists.', 'community-calendar-study-rooms'), '', array('response' => 404));
    }
    calsys_private_file_download($file->file_name, $file->file_data);
}
add_action('admin_post_calsys_communication_download', 'calsys_communication_download');

function calsys_communication_exporters($exporters) {
    $exporters['commonscal-communication'] = array(
        'exporter_friendly_name' => __('CommonCalendar communication', 'community-calendar-study-rooms'),
        'callback' => 'calsys_export_communication',
    );
    return $exporters;
}
add_filter('wp_privacy_personal_data_exporters', 'calsys_communication_exporters');

function calsys_export_communication($email, $page = 1) {
    $user = get_user_by('email', $email);
    if (!$user) {
        return array('data' => array(), 'done' => true);
    }
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
        'SELECT m.id, m.body, m.file_name, m.created_at, c.name FROM ' . calsys_table('messages') . ' m LEFT JOIN ' .
        calsys_table('channels') . ' c ON c.id = m.channel_id WHERE m.user_id = %d ORDER BY m.id LIMIT 50 OFFSET %d',
        $user->ID, max(0, ((int) $page - 1) * 50)
    ));
    if (!is_array($rows) || $wpdb->last_error) {
        error_log('CommonCalendar: unable to export communication: ' . $wpdb->last_error);
        return new WP_Error('communication_export', __('Communication could not be exported. Check the site error log.', 'community-calendar-study-rooms'));
    }
    $items = array();
    foreach ($rows as $row) {
        $items[] = array(
            'group_id' => 'commonscal-communication',
            'group_label' => __('Staff communication', 'community-calendar-study-rooms'),
            'item_id' => 'commonscal-message-' . $row->id,
            'data' => array(
                array('name' => __('Channel', 'community-calendar-study-rooms'), 'value' => $row->name),
                array('name' => __('Message', 'community-calendar-study-rooms'), 'value' => $row->body),
                array('name' => __('Attachment filename', 'community-calendar-study-rooms'), 'value' => $row->file_name),
                array('name' => __('Sent at', 'community-calendar-study-rooms'), 'value' => $row->created_at),
            ),
        );
    }
    return array('data' => $items, 'done' => count($rows) < 50);
}

function calsys_communication_erasers($erasers) {
    $erasers['commonscal-communication'] = array(
        'eraser_friendly_name' => __('CommonCalendar communication', 'community-calendar-study-rooms'),
        'callback' => 'calsys_erase_communication',
    );
    return $erasers;
}
add_filter('wp_privacy_personal_data_erasers', 'calsys_communication_erasers');

function calsys_erase_communication($email, $page = 1) {
    $user = get_user_by('email', $email);
    if (!$user) {
        return array('items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true);
    }
    global $wpdb;
    $deleted = $wpdb->query($wpdb->prepare(
        'DELETE FROM ' . calsys_table('messages') . ' WHERE user_id = %d LIMIT 100', $user->ID
    ));
    if ($deleted === false) {
        error_log('CommonCalendar: unable to erase communication: ' . $wpdb->last_error);
        return array(
            'items_removed' => false,
            'items_retained' => true,
            'messages' => array(__('Communication could not be erased. Check the site error log.', 'community-calendar-study-rooms')),
            'done' => true,
        );
    }
    return array('items_removed' => $deleted > 0, 'items_retained' => false, 'messages' => array(), 'done' => $deleted < 100);
}
