<?php
if (!defined('ABSPATH')) {
    exit;
}

function calsys_tasks_enabled() {
    global $wpdb;
    return get_user_meta(get_current_user_id(), $wpdb->prefix . 'calsys_tasks_enabled', true) === '1';
}

function calsys_tasks_styles() {
    wp_enqueue_style('calsys-tasks', plugins_url('assets/calendar-tasks.css', CALSYS_PLUGIN_FILE), array(), CALSYS_VERSION);
}
add_action('wp_enqueue_scripts', 'calsys_tasks_styles');
add_action('admin_enqueue_scripts', 'calsys_tasks_styles');

function calsys_admin_tasks_page() {
    calsys_require_staff();
    echo '<div class="wrap"><h1>' . esc_html__('My tasks', 'community-calendar-study-rooms') . '</h1>';
    if (calsys_tasks_enabled()) {
        echo '<p>' . esc_html__('Use the checkmark button to open your task sidebar.', 'community-calendar-study-rooms') . '</p>';
        echo calsys_render_tasks(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    } else {
        $url = admin_url('admin.php?page=calsys-settings#calsys-settings-my-tasks');
        echo '<p><a href="' . esc_url($url) . '">' . esc_html__('Enable your task list in settings.', 'community-calendar-study-rooms') . '</a></p>';
    }
    echo '</div>';
}

function calsys_task_settings_panel() {
    $notice = sanitize_key(wp_unslash($_GET['calsys_task_preference'] ?? ''));
    ?>
    <section class="calsys-settings-card" id="calsys-settings-my-tasks">
        <h2><?php esc_html_e('My tasks', 'community-calendar-study-rooms'); ?></h2>
        <p><?php esc_html_e('This setting applies only to your account. Your tasks are private and stay saved if you turn the sidebar off.', 'community-calendar-study-rooms'); ?></p>
        <?php if ($notice === 'saved') : ?>
            <div class="notice notice-success inline"><p><?php esc_html_e('Your task preference was saved.', 'community-calendar-study-rooms'); ?></p></div>
        <?php elseif ($notice === 'error') : ?>
            <div class="notice notice-error inline"><p><?php esc_html_e('Your task preference could not be saved. Please contact the site administrator.', 'community-calendar-study-rooms'); ?></p></div>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php calsys_task_action_fields('preference'); ?>
            <label>
                <input type="checkbox" name="tasks_enabled" value="1" <?php checked(calsys_tasks_enabled()); ?>>
                <?php esc_html_e('Enable the task sidebar for my account', 'community-calendar-study-rooms'); ?>
            </label>
            <p><button type="submit" class="button"><?php esc_html_e('Save my task preference', 'community-calendar-study-rooms'); ?></button></p>
        </form>
    </section>
    <?php
}

function calsys_task_preference_redirect($notice) {
    $url = add_query_arg(array('page' => 'calsys-settings', 'calsys_task_preference' => $notice), admin_url('admin.php'));
    wp_safe_redirect($url . '#calsys-settings-my-tasks');
    exit;
}

function calsys_tasks_redirect($notice) {
    $fallback = admin_url('admin.php?page=calsys-tasks');
    $url = wp_validate_redirect(wp_get_referer(), $fallback);
    $url = remove_query_arg('calsys_task_notice', $url);
    if ($notice === 'saved') {
        $url = remove_query_arg('calsys_task_edit', $url);
    }
    wp_safe_redirect(add_query_arg('calsys_task_notice', $notice, $url) . '#calsys-tasks');
    exit;
}

function calsys_tasks_action() {
    calsys_require_staff();
    check_admin_referer('calsys_tasks');

    global $wpdb;
    $user_id = get_current_user_id();
    $operation = sanitize_key(wp_unslash($_POST['task_operation'] ?? ''));
    if ($operation === 'preference') {
        $enabled = isset($_POST['tasks_enabled']) && $_POST['tasks_enabled'] === '1' ? '1' : '0';
        $key = $wpdb->prefix . 'calsys_tasks_enabled';
        update_user_meta($user_id, $key, $enabled);
        if (get_user_meta($user_id, $key, true) !== $enabled) {
            error_log('CommonCalendar: unable to save task preference for user ' . $user_id);
            calsys_task_preference_redirect('error');
        }
        calsys_task_preference_redirect('saved');
    }

    if (!calsys_tasks_enabled()) {
        wp_die(esc_html__('Enable your task list before making changes.', 'community-calendar-study-rooms'), '', array('response' => 403));
    }

    $table = calsys_table('tasks');
    $id = absint($_POST['task_id'] ?? 0);
    if ($operation === 'delete' || $operation === 'complete' || $operation === 'reopen') {
        if (!$id) {
            calsys_tasks_redirect('invalid');
        }
        if ($operation === 'delete') {
            $result = $wpdb->delete($table, array('id' => $id, 'user_id' => $user_id), array('%d', '%d'));
        } else {
            $result = $wpdb->update($table, array('completed' => $operation === 'complete' ? 1 : 0), array('id' => $id, 'user_id' => $user_id), array('%d'), array('%d', '%d'));
        }
        if ($result === false) {
            error_log('CommonCalendar: unable to update task: ' . $wpdb->last_error);
            calsys_tasks_redirect('error');
        }
        calsys_tasks_redirect($result ? 'saved' : 'missing');
    }

    if ($operation !== 'save') {
        calsys_tasks_redirect('invalid');
    }
    $title = sanitize_text_field(wp_unslash($_POST['task_title'] ?? ''));
    $notes = sanitize_textarea_field(wp_unslash($_POST['task_notes'] ?? ''));
    $due = sanitize_text_field(wp_unslash($_POST['task_due'] ?? ''));
    $date = $due === '' ? null : DateTimeImmutable::createFromFormat('!Y-m-d', $due, wp_timezone());
    if ($title === '' || preg_match_all('/./us', $title) > 200 ||
        ($due !== '' && (!$date || $date->format('Y-m-d') !== $due))) {
        calsys_tasks_redirect('invalid');
    }

    $raw_events = $_POST['task_events'] ?? array();
    if (!is_array($raw_events)) {
        calsys_tasks_redirect('invalid');
    }
    $event_ids = array_values(array_unique(array_filter(array_map('absint', $raw_events))));
    if ($event_ids) {
        $placeholders = implode(', ', array_fill(0, count($event_ids), '%d'));
        $found = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM " . calsys_table('events') . " WHERE id IN ($placeholders)",
            $event_ids
        ));
        if ($wpdb->last_error) {
            error_log('CommonCalendar: unable to validate task events: ' . $wpdb->last_error);
            calsys_tasks_redirect('error');
        }
        if (count($found) !== count($event_ids)) {
            calsys_tasks_redirect('events_missing');
        }
    }

    $data = array(
        'title' => $title,
        'notes' => $notes,
        'due_date' => $due === '' ? null : $due,
        'event_ids' => wp_json_encode($event_ids),
    );
    if ($id) {
        $result = $wpdb->update($table, $data, array('id' => $id, 'user_id' => $user_id), array('%s', '%s', '%s', '%s'), array('%d', '%d'));
        if ($result === 0) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE id = %d AND user_id = %d", $id, $user_id));
            if ($wpdb->last_error) {
                error_log('CommonCalendar: unable to find task: ' . $wpdb->last_error);
                calsys_tasks_redirect('error');
            }
            if (!$exists) {
                calsys_tasks_redirect('missing');
            }
        }
    } else {
        $data['user_id'] = $user_id;
        $result = $wpdb->insert($table, $data, array('%s', '%s', '%s', '%s', '%d'));
    }
    if ($result === false) {
        error_log('CommonCalendar: unable to save task: ' . $wpdb->last_error);
        calsys_tasks_redirect('error');
    }
    calsys_tasks_redirect('saved');
}
add_action('admin_post_calsys_tasks', 'calsys_tasks_action');

function calsys_tasks_privacy_exporters($exporters) {
    $exporters['commonscal-tasks'] = array(
        'exporter_friendly_name' => __('CommonCalendar personal tasks', 'community-calendar-study-rooms'),
        'callback' => 'calsys_export_tasks',
    );
    return $exporters;
}
add_filter('wp_privacy_personal_data_exporters', 'calsys_tasks_privacy_exporters');

function calsys_export_tasks($email, $page = 1) {
    global $wpdb;
    $user = get_user_by('email', $email);
    if (!$user) {
        return array('data' => array(), 'done' => true);
    }
    $limit = 50;
    $offset = max(0, ((int) $page - 1) * $limit);
    $tasks = $wpdb->get_results($wpdb->prepare(
        "SELECT id, title, notes, due_date, event_ids, completed FROM " . calsys_table('tasks') . " WHERE user_id = %d ORDER BY id LIMIT %d OFFSET %d",
        $user->ID, $limit, $offset
    ));
    if (!is_array($tasks) || $wpdb->last_error) {
        error_log('CommonCalendar: unable to export tasks: ' . $wpdb->last_error);
        return new WP_Error('calsys_tasks_export_failed', __('Personal tasks could not be exported. Check the site error log.', 'community-calendar-study-rooms'));
    }
    $items = array();
    foreach ($tasks as $task) {
        $items[] = array(
            'group_id' => 'commonscal-tasks',
            'group_label' => __('Personal tasks', 'community-calendar-study-rooms'),
            'item_id' => 'commonscal-task-' . $task->id,
            'data' => array(
                array('name' => __('Task', 'community-calendar-study-rooms'), 'value' => $task->title),
                array('name' => __('Notes', 'community-calendar-study-rooms'), 'value' => $task->notes),
                array('name' => __('Due date', 'community-calendar-study-rooms'), 'value' => $task->due_date),
                array('name' => __('Attached event IDs', 'community-calendar-study-rooms'), 'value' => $task->event_ids),
                array('name' => __('Completed', 'community-calendar-study-rooms'), 'value' => $task->completed ? __('Yes', 'community-calendar-study-rooms') : __('No', 'community-calendar-study-rooms')),
            ),
        );
    }
    return array('data' => $items, 'done' => count($tasks) < $limit);
}

function calsys_tasks_privacy_erasers($erasers) {
    $erasers['commonscal-tasks'] = array(
        'eraser_friendly_name' => __('CommonCalendar personal tasks', 'community-calendar-study-rooms'),
        'callback' => 'calsys_erase_tasks',
    );
    return $erasers;
}
add_filter('wp_privacy_personal_data_erasers', 'calsys_tasks_privacy_erasers');

function calsys_erase_tasks($email, $page = 1) {
    global $wpdb;
    $user = get_user_by('email', $email);
    if (!$user) {
        return array('items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true);
    }
    $result = $wpdb->query($wpdb->prepare(
        "DELETE FROM " . calsys_table('tasks') . " WHERE user_id = %d LIMIT 100",
        $user->ID
    ));
    if ($result === false) {
        error_log('CommonCalendar: unable to erase tasks: ' . $wpdb->last_error);
        return array(
            'items_removed' => false,
            'items_retained' => true,
            'messages' => array(__('Personal tasks could not be erased. Check the site error log.', 'community-calendar-study-rooms')),
            'done' => true,
        );
    }
    return array('items_removed' => $result > 0, 'items_retained' => false, 'messages' => array(), 'done' => $result < 100);
}

function calsys_task_form_fields($task, $events) {
    $selected = $task ? json_decode($task->event_ids, true) : array();
    $selected = is_array($selected) ? array_map('intval', $selected) : array();
    ?>
    <label>
        <?php esc_html_e('Task', 'community-calendar-study-rooms'); ?>
        <input type="text" name="task_title" maxlength="200" required value="<?php echo esc_attr($task ? $task->title : ''); ?>">
    </label>
    <label>
        <?php esc_html_e('Notes', 'community-calendar-study-rooms'); ?>
        <textarea name="task_notes" rows="3"><?php echo esc_textarea($task ? $task->notes : ''); ?></textarea>
    </label>
    <label>
        <?php esc_html_e('Due date', 'community-calendar-study-rooms'); ?>
        <input type="date" name="task_due" value="<?php echo esc_attr($task && $task->due_date ? $task->due_date : ''); ?>">
    </label>
    <label>
        <?php esc_html_e('Attached events', 'community-calendar-study-rooms'); ?>
        <select name="task_events[]" multiple size="5">
            <?php foreach ($events as $event) : ?>
                <option value="<?php echo esc_attr($event->id); ?>" <?php selected(in_array((int) $event->id, $selected, true)); ?>>
                    <?php echo esc_html($event->title . ' — ' . mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $event->starts_at)); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <small><?php esc_html_e('Select multiple events with Ctrl or Command. On a phone, use the selection controls.', 'community-calendar-study-rooms'); ?></small>
    <?php
}

function calsys_task_action_fields($operation, $id = 0) {
    wp_nonce_field('calsys_tasks');
    echo '<input type="hidden" name="action" value="calsys_tasks">';
    echo '<input type="hidden" name="task_operation" value="' . esc_attr($operation) . '">';
    if ($id) {
        echo '<input type="hidden" name="task_id" value="' . esc_attr($id) . '">';
    }
}

function calsys_render_task($task, $events, $edit_id) {
    $selected = json_decode($task->event_ids, true);
    $selected = is_array($selected) ? array_map('intval', $selected) : array();
    $available = array_map('intval', wp_list_pluck($events, 'id'));
    ?>
    <article id="calsys-task-<?php echo esc_attr($task->id); ?>" class="calsys-task <?php echo $task->completed ? 'calsys-task-done' : ''; ?>">
        <div class="calsys-task-heading">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php calsys_task_action_fields($task->completed ? 'reopen' : 'complete', $task->id); ?>
                <button type="submit" aria-label="<?php echo esc_attr($task->completed ? sprintf(__('Reopen %s', 'community-calendar-study-rooms'), $task->title) : sprintf(__('Complete %s', 'community-calendar-study-rooms'), $task->title)); ?>"><?php echo $task->completed ? '&#x2611;' : '&#x2610;'; ?></button>
            </form>
            <strong><?php echo esc_html($task->title); ?></strong>
        </div>
        <?php if ($task->due_date) : ?>
            <p><?php echo esc_html(sprintf(__('Due: %s', 'community-calendar-study-rooms'), mysql2date(get_option('date_format'), $task->due_date))); ?></p>
        <?php endif; ?>
        <?php if ($task->notes !== '') : ?>
            <p class="calsys-task-notes"><?php echo esc_html($task->notes); ?></p>
        <?php endif; ?>
        <?php foreach ($events as $event) : ?>
            <?php if (in_array((int) $event->id, $selected, true)) : ?>
                <p><a href="<?php echo esc_url(add_query_arg(array('page' => 'calsys-events', 'edit_event' => $event->id), admin_url('admin.php'))); ?>"><?php echo esc_html($event->title . ' — ' . mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $event->starts_at)); ?></a></p>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if (array_diff($selected, $available)) : ?>
            <p><?php esc_html_e('An attached event was deleted. Saving this task will remove that link.', 'community-calendar-study-rooms'); ?></p>
        <?php endif; ?>
        <?php if ($edit_id === (int) $task->id) : ?>
            <form class="calsys-task-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php calsys_task_action_fields('save', $task->id); ?>
                <?php calsys_task_form_fields($task, $events); ?>
                <button type="submit"><?php esc_html_e('Save task', 'community-calendar-study-rooms'); ?></button>
            </form>
            <a href="<?php echo esc_url(add_query_arg('calsys_task_open', '1', remove_query_arg('calsys_task_edit')) . '#calsys-task-' . $task->id); ?>"><?php esc_html_e('Cancel editing', 'community-calendar-study-rooms'); ?></a>
        <?php else : ?>
            <a href="<?php echo esc_url(add_query_arg('calsys_task_edit', $task->id) . '#calsys-task-' . $task->id); ?>"><?php esc_html_e('Edit task', 'community-calendar-study-rooms'); ?></a>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Delete this task?', 'community-calendar-study-rooms')); ?>');">
            <?php calsys_task_action_fields('delete', $task->id); ?>
            <button type="submit"><?php esc_html_e('Delete task', 'community-calendar-study-rooms'); ?></button>
        </form>
    </article>
    <?php
}

function calsys_render_tasks() {
    static $rendered = false;
    if (!current_user_can('manage_calendar_system') || !calsys_tasks_enabled()) {
        return '';
    }
    if ($rendered) {
        return '';
    }
    $rendered = true;
    $messages = array(
        'saved' => __('Task changes saved.', 'community-calendar-study-rooms'),
        'invalid' => __('Please enter a task title and a valid due date.', 'community-calendar-study-rooms'),
        'missing' => __('That task no longer exists or is not yours.', 'community-calendar-study-rooms'),
        'events_missing' => __('An attached event no longer exists. Please select your events again.', 'community-calendar-study-rooms'),
        'error' => __('Tasks could not be saved. Please contact the site administrator.', 'community-calendar-study-rooms'),
    );
    $notice = sanitize_key(wp_unslash($_GET['calsys_task_notice'] ?? ''));
    $open = isset($messages[$notice]) || !empty($_GET['calsys_task_edit']) || !empty($_GET['calsys_task_open']);
    ob_start();
    ?>
    <details class="calsys-task-sidebar"<?php echo $open ? ' open' : ''; ?>>
        <summary aria-label="<?php esc_attr_e('My tasks', 'community-calendar-study-rooms'); ?>" title="<?php esc_attr_e('My tasks', 'community-calendar-study-rooms'); ?>"><span aria-hidden="true">&#10003;</span></summary>
        <section class="calsys-tasks" id="calsys-tasks" aria-label="<?php esc_attr_e('My tasks', 'community-calendar-study-rooms'); ?>">
            <h2><?php esc_html_e('My tasks', 'community-calendar-study-rooms'); ?></h2>
            <?php if (isset($messages[$notice])) : ?>
                <p role="status"><?php echo esc_html($messages[$notice]); ?></p>
            <?php endif; ?>
            <?php calsys_render_task_list(); ?>
        </section>
    </details>
    <?php
    return ob_get_clean();
}

function calsys_render_task_list() {
    global $wpdb;
    $tasks = $wpdb->get_results($wpdb->prepare(
        "SELECT id, title, notes, due_date, event_ids, completed FROM " . calsys_table('tasks') . " WHERE user_id = %d ORDER BY completed ASC, due_date IS NULL ASC, due_date ASC, id DESC",
        get_current_user_id()
    ));
    if (!is_array($tasks) || $wpdb->last_error) {
        error_log('CommonCalendar: unable to load tasks: ' . $wpdb->last_error);
        echo '<p role="alert">' . esc_html__('Your tasks could not be loaded. Please contact the site administrator.', 'community-calendar-study-rooms') . '</p>';
        return;
    }
    $events = $wpdb->get_results("SELECT id, title, starts_at FROM " . calsys_table('events') . " ORDER BY starts_at DESC, id DESC");
    if (!is_array($events) || $wpdb->last_error) {
        error_log('CommonCalendar: unable to load task events: ' . $wpdb->last_error);
        echo '<p role="alert">' . esc_html__('Events could not be loaded. Please contact the site administrator.', 'community-calendar-study-rooms') . '</p>';
        return;
    }
    $edit_id = absint($_GET['calsys_task_edit'] ?? 0);
    ?>
    <details class="calsys-task-add">
        <summary><?php esc_html_e('Add a task', 'community-calendar-study-rooms'); ?></summary>
        <form class="calsys-task-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php calsys_task_action_fields('save'); ?>
            <?php calsys_task_form_fields(null, $events); ?>
            <button type="submit"><?php esc_html_e('Add task', 'community-calendar-study-rooms'); ?></button>
        </form>
    </details>
    <?php if (!$tasks) : ?>
        <p><?php esc_html_e('No tasks yet.', 'community-calendar-study-rooms'); ?></p>
    <?php endif; ?>
    <?php foreach ($tasks as $task) : ?>
        <?php calsys_render_task($task, $events, $edit_id); ?>
    <?php endforeach; ?>
    <?php
}
