<?php
/**
 * Entity submissions: the `user_submits` staging post type and its clerk screens.
 *
 * A request sent through [entity_submit] lands here as a pending post. The clerk
 * reviews it in wp-admin: «نشر» converts it into a term (approval.php); a rejection
 * reason + «حفظ بإنتظار المراجعة» or «نقل إلى سلة المهملات» rejects it; «استعادة»
 * brings a rejected request back as pending with the reason cleared.
 *
 * This file owns everything WordPress-side around that post type: registration and
 * capabilities (editors/administrators only - submitters never see it), the admin
 * list table (columns + type filter, no Quick Edit / bulk edit), the per-user admin
 * notices store that approval.php writes to, the read-only «بيانات الطلب» meta box,
 * showing the chosen type's ACF term field group on the edit screen, keeping
 * post_author = submitter across clerk saves, attachment ownership and clean-up,
 * trash/untrash behaviour and the admin assets for the parent picker.
 *
 * Deletion rule: delete_submission_uploads() removes only attachments whose
 * post_parent is the request AND whose author is the request's author, and never
 * for an approved request (its images now belong to the published page). Nothing
 * else is ever deleted.
 *
 * Uses the constants and helpers of inc/submissions/registry.php and
 * get_cached_taxonomy_term_map() from inc/taxonomy-helpers.php.
 */

if (!defined('ABSPATH')) {
    exit;
}

/*
 * -------------------------------------------------------------------------
 * Post type
 * -------------------------------------------------------------------------
 */

/**
 * Register the `user_submits` staging post type.
 *
 * Not public, not in REST, title only (every other value is an ACF field). All
 * primitive capabilities map to editor-level ones: edit_others_posts (read, edit,
 * create), publish_posts (approve) and delete_others_posts (reject / delete), and
 * the meta capabilities resolve to them through map_meta_cap(), so the «مُرسل»
 * role (read only) can never open its own request in wp-admin.
 */
function submission_register_post_type(): void
{
    $labels = [
        'name' => __('Add requests', 'hello-elementor-child'),
        'singular_name' => __('Add request', 'hello-elementor-child'),
        'menu_name' => __('Add requests', 'hello-elementor-child'),
        'add_new' => __('New request', 'hello-elementor-child'),
        'add_new_item' => __('New request', 'hello-elementor-child'),
        'edit_item' => __('Review request', 'hello-elementor-child'),
        'view_item' => __('View request', 'hello-elementor-child'),
        'search_items' => __('Search requests', 'hello-elementor-child'),
        'not_found' => __('No requests', 'hello-elementor-child'),
        'not_found_in_trash' => __('No requests in the trash', 'hello-elementor-child'),
        'all_items' => __('All requests', 'hello-elementor-child'),
    ];

    register_post_type(
        SUBMISSION_POST_TYPE,
        [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_admin_bar' => false,
            'show_in_rest' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'has_archive' => false,
            'rewrite' => false,
            'query_var' => false,
            'hierarchical' => false,
            'menu_icon' => 'dashicons-welcome-write-blog',
            'menu_position' => 26,
            'supports' => ['title'],
            'capability_type' => ['user_submit', 'user_submits'],
            'map_meta_cap' => true,
            // Only primitive capabilities are overridden. The meta capabilities keep their
            // defaults (edit_user_submit, read_user_submit, delete_user_submit): map_meta_cap()
            // resolves them to the primitives below. Aliasing them to an existing primitive
            // name would register that name in WordPress' global $post_type_meta_caps and
            // turn every current_user_can('edit_others_posts') on the site into do_not_allow.
            'capabilities' => [
                'edit_posts' => 'edit_others_posts',
                'edit_others_posts' => 'edit_others_posts',
                'edit_published_posts' => 'edit_others_posts',
                'edit_private_posts' => 'edit_others_posts',
                'read_private_posts' => 'edit_others_posts',
                'create_posts' => 'edit_others_posts',
                'publish_posts' => 'publish_posts',
                'delete_posts' => 'delete_others_posts',
                'delete_others_posts' => 'delete_others_posts',
                'delete_published_posts' => 'delete_others_posts',
                'delete_private_posts' => 'delete_others_posts',
            ],
        ]
    );
}
add_action('init', 'submission_register_post_type');

/**
 * Title placeholder on the edit screen (`enter_title_here` filter).
 *
 * @param string  $text Core placeholder.
 * @param WP_Post $post Post being edited.
 * @return string
 */
function submission_enter_title_here($text, $post)
{
    if ($post instanceof WP_Post && $post->post_type === SUBMISSION_POST_TYPE) {
        return __('Entity / event name in Arabic', 'hello-elementor-child');
    }

    return $text;
}
add_filter('enter_title_here', 'submission_enter_title_here', 10, 2);

/**
 * Arabic "saved / published" messages for this type (`post_updated_messages` filter).
 *
 * redirect_post() picks 8 for a pending post (both «حفظ بإنتظار المراجعة» and a «نشر»
 * that approval.php reverted - that one also strips the message), 6 for a published
 * post, 1 for an update and 10 for a draft.
 *
 * @param array<string, array<int, string>> $messages
 * @return array<string, array<int, string>>
 */
function submission_post_updated_messages(array $messages): array
{
    $messages[SUBMISSION_POST_TYPE] = [
        1 => __('Request saved.', 'hello-elementor-child'),
        6 => __('Request published and page created.', 'hello-elementor-child'),
        8 => __('Request saved (under review).', 'hello-elementor-child'),
        10 => __('Request saved (under review).', 'hello-elementor-child'),
    ];

    return $messages;
}
add_filter('post_updated_messages', 'submission_post_updated_messages');

/**
 * No Quick Edit for requests (`post_row_actions` filter): the inline form posts
 * no author and would reassign the request to the clerk.
 *
 * @param array<string, string> $actions
 * @param WP_Post               $post
 * @return array<string, string>
 */
function submission_post_row_actions(array $actions, WP_Post $post): array
{
    if ($post->post_type === SUBMISSION_POST_TYPE) {
        unset($actions['inline hide-if-no-js']);
    }

    return $actions;
}
add_filter('post_row_actions', 'submission_post_row_actions', 10, 2);

/**
 * No bulk edit for requests; Trash, Restore and Delete stay (`bulk_actions-edit-user_submits`).
 *
 * @param array<string, string> $actions
 * @return array<string, string>
 */
function submission_bulk_actions(array $actions): array
{
    unset($actions['edit']);

    return $actions;
}
add_filter('bulk_actions-edit-user_submits', 'submission_bulk_actions');

/**
 * Keep post_author = the submitter on every admin save (`wp_insert_post_data` filter).
 *
 * The type has no Author box, so a classic-editor save posts no post_author and
 * WordPress would fall back to the current user (the clerk). Only admin requests
 * are touched: the front-end acf_form submit (is_admin() false) sets the author
 * itself, and WP-CLI / cron keep core behaviour.
 *
 * @param array<string, mixed> $data    Slashed, sanitized post data about to be written.
 * @param array<string, mixed> $postarr Raw arguments passed to wp_insert_post().
 * @return array<string, mixed>
 */
function submission_preserve_author(array $data, array $postarr): array
{
    if (($data['post_type'] ?? '') !== SUBMISSION_POST_TYPE || empty($postarr['ID']) || !is_admin()) {
        return $data;
    }

    $existing = (int) get_post_field('post_author', (int) $postarr['ID'], 'raw');
    if ($existing > 0) {
        $data['post_author'] = (string) $existing;
    }

    return $data;
}
add_filter('wp_insert_post_data', 'submission_preserve_author', 10, 2);

/*
 * -------------------------------------------------------------------------
 * Admin list table
 * -------------------------------------------------------------------------
 */

/**
 * Columns of the requests list (`manage_user_submits_posts_columns` filter).
 *
 * @param array<string, string> $columns Core columns (cb, title, date).
 * @return array<string, string>
 */
function submission_admin_columns(array $columns): array
{
    return [
        'cb' => $columns['cb'] ?? '<input type="checkbox" />',
        'title' => $columns['title'] ?? __('Name', 'hello-elementor-child'),
        'submission_type' => __('Type', 'hello-elementor-child'),
        'submission_parent' => __('Parent', 'hello-elementor-child'),
        'submission_english' => __('English name', 'hello-elementor-child'),
        'submission_user' => __('Submitter', 'hello-elementor-child'),
        'submission_result' => __('Result', 'hello-elementor-child'),
        'date' => $columns['date'] ?? __('Date', 'hello-elementor-child'),
    ];
}
add_filter('manage_user_submits_posts_columns', 'submission_admin_columns');

/**
 * Cell content of the custom columns (`manage_user_submits_posts_custom_column` action).
 *
 * @param string $column  Column key.
 * @param int    $post_id Request ID.
 */
function submission_render_admin_column(string $column, int $post_id): void
{
    switch ($column) {
        case 'submission_type':
            $label = get_submission_taxonomy_label((string) get_post_meta($post_id, SUBMISSION_FIELD_TAXONOMY, true));
            echo $label !== '' ? esc_html($label) : '—';
            break;

        case 'submission_parent':
            $parent = get_submission_parent_name($post_id);
            echo $parent !== '' ? esc_html($parent) : '—';
            break;

        case 'submission_english':
            $english = trim((string) get_post_meta($post_id, SUBMISSION_FIELD_ENGLISH, true));
            echo $english !== '' ? '<span dir="ltr">' . esc_html($english) . '</span>' : '—';
            break;

        case 'submission_user':
            echo get_submission_submitter_html((int) get_post_field('post_author', $post_id, 'raw')); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by the helper
            break;

        case 'submission_result':
            echo get_submission_result_html($post_id); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by the helper
            break;
    }
}
add_action('manage_user_submits_posts_custom_column', 'submission_render_admin_column', 10, 2);

/**
 * Name of the chosen parent term (decoded), '' when none or unknown.
 */
function get_submission_parent_name(int $post_id): string
{
    $parent = (int) get_post_meta($post_id, SUBMISSION_FIELD_PARENT, true);
    if ($parent <= 0) {
        return '';
    }

    $taxonomy = (string) get_post_meta($post_id, SUBMISSION_FIELD_TAXONOMY, true);
    $term = is_submission_taxonomy($taxonomy) ? get_term($parent, $taxonomy) : get_term($parent);

    return $term instanceof WP_Term ? wp_specialchars_decode($term->name, ENT_QUOTES) : '';
}

/**
 * Escaped "display name <br><small>email</small>" of a user, '—' when the user is gone.
 */
function get_submission_submitter_html(int $user_id): string
{
    $user = $user_id > 0 ? get_userdata($user_id) : false;
    if (!($user instanceof WP_User)) {
        return '—';
    }

    $html = esc_html($user->display_name);
    if ((string) $user->user_email !== '') {
        $html .= '<br><small>' . esc_html($user->user_email) . '</small>';
    }

    return $html;
}

/**
 * Escaped «عرض الصفحة» · «تحرير» links for a published term, '' when the term is gone.
 */
function get_submission_result_links(int $term_id): string
{
    $term = $term_id > 0 ? get_term($term_id) : null;
    if (!($term instanceof WP_Term)) {
        return '';
    }

    $parts = [];

    $view = get_term_link($term);
    if (!is_wp_error($view)) {
        $parts[] = '<a href="' . esc_url($view) . '">' . esc_html__('View page', 'hello-elementor-child') . '</a>';
    }

    $edit = get_edit_term_link($term);
    if (is_string($edit) && $edit !== '') {
        $parts[] = '<a href="' . esc_url($edit) . '">' . esc_html__('Edit', 'hello-elementor-child') . '</a>';
    }

    return implode(' · ', $parts);
}

/**
 * Escaped «النتيجة» cell: published → links to the page, trash → «مرفوض», else «قيد المراجعة».
 *
 * The published check comes first on purpose: an approved request that was trashed
 * as housekeeping is still a published page, not a rejection.
 */
function get_submission_result_html(int $post_id): string
{
    $term_id = (int) get_post_meta($post_id, SUBMISSION_META_RESULT_TERM, true);
    if ($term_id > 0) {
        $links = get_submission_result_links($term_id);

        return $links !== '' ? $links : esc_html__('Published', 'hello-elementor-child');
    }

    if (get_post_status($post_id) === 'trash') {
        return esc_html__('Rejected', 'hello-elementor-child');
    }

    return esc_html__('Under review', 'hello-elementor-child');
}

/**
 * Type dropdown above the requests list (`restrict_manage_posts` action).
 *
 * @param string $post_type Screen post type.
 * @param string $which     'top' or 'bottom'.
 */
function submission_admin_type_filter(string $post_type, string $which): void
{
    if ($post_type !== SUBMISSION_POST_TYPE || $which !== 'top') {
        return;
    }

    $current = sanitize_key((string) ($_GET['submission_type'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter

    echo '<label class="screen-reader-text" for="submission_type">' . esc_html__('Type', 'hello-elementor-child') . '</label>';
    echo '<select name="submission_type" id="submission_type">';
    echo '<option value="">' . esc_html__('All types', 'hello-elementor-child') . '</option>';
    foreach (submission_taxonomies() as $taxonomy => $info) {
        printf(
            '<option value="%1$s"%2$s>%3$s</option>',
            esc_attr((string) $taxonomy),
            selected($current, (string) $taxonomy, false),
            esc_html((string) ($info['label'] ?? $taxonomy))
        );
    }
    echo '</select>';
}
add_action('restrict_manage_posts', 'submission_admin_type_filter', 10, 2);

/**
 * Apply the type dropdown to the admin list query (`pre_get_posts` action).
 *
 * Only the admin main query of this type, only for a value that is a registry key.
 */
function submission_admin_filter_query(WP_Query $query): void
{
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== SUBMISSION_POST_TYPE) {
        return;
    }

    $type = sanitize_key((string) ($_GET['submission_type'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter
    if ($type === '' || !is_submission_taxonomy($type)) {
        return;
    }

    $meta_query = $query->get('meta_query');
    if (!is_array($meta_query)) {
        $meta_query = [];
    }
    $meta_query[] = [
        'key' => SUBMISSION_FIELD_TAXONOMY,
        'value' => $type,
        'compare' => '=',
    ];
    $query->set('meta_query', $meta_query);
}
add_action('pre_get_posts', 'submission_admin_filter_query');

/*
 * -------------------------------------------------------------------------
 * Admin notices store (approval.php writes, the next admin screen prints)
 * -------------------------------------------------------------------------
 */

/**
 * Queue an admin notice for the current user; shown on their next admin screen.
 *
 * The notices survive the redirect after a save because they live in the
 * transient `submission_notices_{user_id}` (60 s), as a list so several notices
 * from one request all show up. $text must already be escaped by the caller
 * (esc_html()/esc_url()); it is printed through wp_kses_post(), which keeps links.
 *
 * @param string $type 'success' or 'error' (WordPress' 'warning' / 'info' also pass).
 * @param string $text Escaped HTML.
 */
function submission_set_admin_notice(string $type, string $text): void
{
    $user_id = get_current_user_id();
    if ($user_id <= 0 || $text === '') {
        return;
    }

    $key = 'submission_notices_' . $user_id;
    $notices = get_transient($key);
    if (!is_array($notices)) {
        $notices = [];
    }

    $notices[] = [
        'type' => in_array($type, ['success', 'error', 'warning', 'info'], true) ? $type : 'info',
        'text' => $text,
    ];

    set_transient($key, $notices, 60);
}

/**
 * Print and clear the queued notices of the current user (`admin_notices` action).
 */
function submission_print_admin_notices(): void
{
    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        return;
    }

    $key = 'submission_notices_' . $user_id;
    $notices = get_transient($key);
    if ($notices === false) {
        return;
    }

    delete_transient($key);
    if (!is_array($notices)) {
        return;
    }

    foreach ($notices as $notice) {
        if (!is_array($notice)) {
            continue;
        }
        $type = (string) ($notice['type'] ?? '');
        if (!in_array($type, ['success', 'error', 'warning', 'info'], true)) {
            $type = 'info';
        }
        $text = (string) ($notice['text'] ?? '');
        if ($text === '') {
            continue;
        }
        printf(
            '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
            esc_attr($type),
            wp_kses_post($text)
        );
    }
}
add_action('admin_notices', 'submission_print_admin_notices');

/*
 * -------------------------------------------------------------------------
 * ACF: show the chosen type's term field group on the request edit screen
 * -------------------------------------------------------------------------
 */

/**
 * Match a `taxonomy == <tax>` location rule on a request edit screen
 * (`acf/location/rule_match/taxonomy` filter).
 *
 * ACF's own taxonomy rule only matches term screens (it returns false whenever
 * $screen has no 'taxonomy' key). On a request edit screen the rule matches when
 * the request's stored `submission_taxonomy` equals the rule value, so the clerk
 * sees exactly the fields the submitter filled. Everything else is left to ACF.
 *
 * @param bool  $match       ACF's result.
 * @param array $rule        {param, operator, value}.
 * @param array $screen      {post_id, post_type, ...}.
 * @param array $field_group Field group being evaluated.
 * @return bool
 */
function submission_match_taxonomy_group($match, $rule, $screen, $field_group)
{
    if (!is_array($screen) || !is_array($rule) || ($screen['post_type'] ?? '') !== SUBMISSION_POST_TYPE) {
        return $match;
    }

    if (($rule['operator'] ?? '==') !== '==') {
        return $match;
    }

    $post_id = (int) ($screen['post_id'] ?? 0);
    if ($post_id <= 0) {
        return $match;
    }

    $taxonomy = (string) get_post_meta($post_id, SUBMISSION_FIELD_TAXONOMY, true);
    if ($taxonomy !== '' && $taxonomy === (string) ($rule['value'] ?? '')) {
        return true;
    }

    return $match;
}
add_filter('acf/location/rule_match/taxonomy', 'submission_match_taxonomy_group', 10, 4);

/*
 * -------------------------------------------------------------------------
 * Read-only «بيانات الطلب» meta box
 * -------------------------------------------------------------------------
 */

/**
 * Register the meta box for this type (`add_meta_boxes` action).
 *
 * @param string $post_type Post type of the edit screen.
 */
function submission_add_info_meta_box(string $post_type): void
{
    if ($post_type !== SUBMISSION_POST_TYPE) {
        return;
    }

    add_meta_box(
        'submission_info',
        __('Request details', 'hello-elementor-child'),
        'render_submission_info_meta_box',
        SUBMISSION_POST_TYPE,
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'submission_add_info_meta_box');

/**
 * Meta box body: clerk hint, submitter, dates, uploads, last approval error,
 * possible duplicates and (once published) links to the page.
 *
 * Everything shown here comes from the submitter, a user or a term, so every
 * value is escaped at output.
 */
function render_submission_info_meta_box(WP_Post $post): void
{
    $post_id = (int) $post->ID;
    $taxonomy = (string) get_post_meta($post_id, SUBMISSION_FIELD_TAXONOMY, true);
    $result_term_id = (int) get_post_meta($post_id, SUBMISSION_META_RESULT_TERM, true);

    echo '<p class="description">' . esc_html__('To approve: review the fields, check the slug, then click "Publish". To reject: enter the reason, then "Save as Pending". "Move to Trash" rejects without a reason.', 'hello-elementor-child') . '</p>';

    // Submitter.
    $author = (int) $post->post_author > 0 ? get_userdata((int) $post->post_author) : false;
    echo '<p><strong>' . esc_html__('Submitter:', 'hello-elementor-child') . '</strong> ';
    if ($author instanceof WP_User) {
        echo esc_html($author->display_name);
        if ((string) $author->user_email !== '') {
            echo '<br><a href="' . esc_url('mailto:' . $author->user_email) . '">' . esc_html($author->user_email) . '</a>';
        }
        $registered = (string) mysql2date(get_option('date_format'), get_date_from_gmt((string) $author->user_registered));
        if ($registered !== '') {
            /* translators: %s: registration date */
            echo '<br><small>' . esc_html(sprintf(__('Registered: %s', 'hello-elementor-child'), $registered)) . '</small>';
        }
    } else {
        echo '—';
    }
    echo '</p>';

    echo '<p><strong>' . esc_html__('Request date:', 'hello-elementor-child') . '</strong> ' . esc_html((string) get_the_date('', $post)) . '</p>';

    // Uploaded files (every attachment parented to the request).
    $uploads = $post_id > 0 ? get_children(
        [
            'post_parent' => $post_id,
            'post_type' => 'attachment',
            'post_status' => 'any',
            'numberposts' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]
    ) : [];
    echo '<p><strong>' . esc_html__('Uploaded files:', 'hello-elementor-child') . '</strong></p>';
    if ($uploads === []) {
        echo '<p>' . esc_html__('No uploaded files.', 'hello-elementor-child') . '</p>';
    } else {
        echo '<ul class="submission-info-uploads">';
        foreach ($uploads as $upload) {
            if (!($upload instanceof WP_Post)) {
                continue;
            }
            $upload_id = (int) $upload->ID;
            $url = (string) wp_get_attachment_url($upload_id);
            $name = wp_basename((string) get_attached_file($upload_id));
            if ($name === '') {
                $name = (string) $upload->post_title;
            }
            $thumb = wp_get_attachment_image($upload_id, [60, 60], true);

            echo '<li style="display:flex;align-items:center;gap:8px;margin:0 0 6px">';
            if ($url !== '') {
                echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . $thumb . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput -- core-generated <img>
            } else {
                echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput -- core-generated <img>
            }
            echo '<span dir="ltr" style="word-break:break-all">' . esc_html($name) . '</span>';
            echo '</li>';
        }
        echo '</ul>';
    }

    // Why the last «نشر» failed (approval.php stores it, clears it on success / restore).
    $error = (string) get_post_meta($post_id, SUBMISSION_META_ERROR, true);
    if ($error !== '') {
        echo '<p style="color:#d63638"><strong>' . esc_html__('Last publishing error:', 'hello-elementor-child') . '</strong><br>' . nl2br(esc_html($error)) . '</p>';
    }

    // Possible duplicates: published terms and other requests with the same normalized name.
    $normalized = (string) get_post_meta($post_id, SUBMISSION_META_NORMALIZED, true);
    if ($normalized === '' && $post_id > 0) {
        $name = get_submission_name($post_id);
        if ($name !== '') {
            $normalized = get_submission_normalized_name($name);
        }
    }

    $term_matches = [];
    if ($normalized !== '' && is_submission_taxonomy($taxonomy)) {
        foreach (get_cached_taxonomy_term_map($taxonomy) as $row) {
            if (!is_array($row) || (string) ($row['key'] ?? '') !== $normalized) {
                continue;
            }
            if ((int) ($row['id'] ?? 0) === $result_term_id) {
                continue; // The page this very request created is not a duplicate.
            }
            $term_matches[] = $row;
        }
    }

    $other_ids = [];
    if ($normalized !== '' && $post_id > 0) {
        $others = new WP_Query(
            [
                'post_type' => SUBMISSION_POST_TYPE,
                'post_status' => ['pending', 'publish'],
                'post__not_in' => [$post_id],
                'meta_key' => SUBMISSION_META_NORMALIZED,
                'meta_value' => $normalized,
                'fields' => 'ids',
                'posts_per_page' => 5,
                'orderby' => 'date',
                'order' => 'DESC',
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ]
        );
        $other_ids = array_map('intval', (array) $others->posts);
    }

    if ($term_matches !== [] || $other_ids !== []) {
        echo '<p style="color:#996800"><strong>' . esc_html__('May already exist', 'hello-elementor-child') . '</strong></p>';

        if ($term_matches !== []) {
            echo '<ul>';
            foreach ($term_matches as $row) {
                $term_name = esc_html((string) ($row['name'] ?? ''));
                $edit = get_edit_term_link((int) ($row['id'] ?? 0), $taxonomy);
                echo '<li>';
                echo is_string($edit) && $edit !== '' ? '<a href="' . esc_url($edit) . '">' . $term_name . '</a>' : $term_name; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above
                if ((string) ($row['link'] ?? '') !== '') {
                    echo ' · <a href="' . esc_url((string) $row['link']) . '" target="_blank" rel="noopener">' . esc_html__('View page', 'hello-elementor-child') . '</a>';
                }
                echo '</li>';
            }
            echo '</ul>';
        }

        if ($other_ids !== []) {
            echo '<p><strong>' . esc_html__('Other requests with the same name:', 'hello-elementor-child') . '</strong></p>';
            echo '<ul>';
            foreach ($other_ids as $other_id) {
                $other_name = get_submission_name($other_id);
                if ($other_name === '') {
                    $other_name = '#' . $other_id;
                }
                $edit = get_edit_post_link($other_id);
                echo '<li>';
                echo is_string($edit) && $edit !== '' ? '<a href="' . esc_url($edit) . '">' . esc_html($other_name) . '</a>' : esc_html($other_name); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inline
                echo '</li>';
            }
            echo '</ul>';
        }
    }

    // Published: where the page lives.
    if ($result_term_id > 0) {
        $links = get_submission_result_links($result_term_id);
        echo '<p><strong>' . esc_html__('Published:', 'hello-elementor-child') . '</strong> ' . ($links !== '' ? $links : '—') . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by the helper
    }
}

/*
 * -------------------------------------------------------------------------
 * Attachments: ownership and clean-up
 * -------------------------------------------------------------------------
 */

/**
 * Only the submitter's own uploads may be parented to a request
 * (`acf/connect_attachment_to_post` filter).
 *
 * ACF re-parents any unattached attachment whose ID is posted for an image/file
 * field. For requests the attachment must exist and share the request's author;
 * otherwise a posted library-image ID would be adopted by the request and later
 * removed by delete_submission_uploads(). Other post types keep ACF's decision.
 *
 * @param bool $allow         ACF's decision (true).
 * @param int  $attachment_id Attachment ID (numeric, ACF checked).
 * @param int  $post_id       Post ID (numeric, ACF checked).
 * @return bool
 */
function submission_filter_attachment_connect($allow, $attachment_id, $post_id)
{
    if (!is_numeric($post_id) || (int) $post_id <= 0 || get_post_type((int) $post_id) !== SUBMISSION_POST_TYPE) {
        return $allow;
    }

    $attachment = is_numeric($attachment_id) && (int) $attachment_id > 0 ? get_post((int) $attachment_id) : null;
    if (!($attachment instanceof WP_Post) || $attachment->post_type !== 'attachment') {
        return false;
    }

    $owner = (int) get_post_field('post_author', (int) $post_id, 'raw');

    return $owner > 0 && (int) $attachment->post_author === $owner;
}
add_filter('acf/connect_attachment_to_post', 'submission_filter_attachment_connect', 10, 3);

/**
 * Permanently delete the submitter's uploads of a request.
 *
 * Does nothing for an approved request (`_result_term_id` set): its images are
 * referenced by the published page. Otherwise deletes every attachment whose
 * post_parent is the request AND whose author is the request's author - a
 * library image the clerk attached while reviewing is left alone. Requests
 * authored by a user who can upload files (clerks) are skipped entirely. Nothing
 * else is ever deleted.
 */
function delete_submission_uploads(int $post_id): void
{
    if ($post_id <= 0 || (int) get_post_meta($post_id, SUBMISSION_META_RESULT_TERM, true) > 0) {
        return;
    }

    $post = get_post($post_id);
    if (!($post instanceof WP_Post) || $post->post_type !== SUBMISSION_POST_TYPE) {
        return;
    }

    $author = (int) $post->post_author;
    if ($author <= 0) {
        return;
    }

    // Clerk-created request: the author can pick shared library images through the media modal,
    // and those must never be deleted. Front-end submitters have no upload_files capability.
    if (user_can($author, 'upload_files')) {
        return;
    }

    $uploads = get_children(
        [
            'post_parent' => $post_id,
            'post_type' => 'attachment',
            'post_status' => 'any',
            'author' => $author,
            'numberposts' => -1,
        ]
    );

    foreach ($uploads as $upload) {
        if (!($upload instanceof WP_Post) || (int) $upload->post_parent !== $post_id || (int) $upload->post_author !== $author) {
            continue;
        }
        wp_delete_attachment((int) $upload->ID, true);
    }
}

/**
 * Uploads go when a request is deleted for good (`before_delete_post` action).
 *
 * Runs before core re-parents the attachments to post_parent 0, which would
 * leave them orphaned in the media library.
 *
 * @param int          $post_id Post being deleted.
 * @param WP_Post|null $post    The post object.
 */
function submission_before_delete_post($post_id, $post = null): void
{
    $post_id = (int) $post_id;
    if ($post_id <= 0 || get_post_type($post_id) !== SUBMISSION_POST_TYPE) {
        return;
    }

    delete_submission_uploads($post_id);
}
add_action('before_delete_post', 'submission_before_delete_post', 10, 2);

/**
 * Rejected uploads leave the server immediately, not after the 30-day trash
 * period (`trashed_post` action). approval.php sends the rejection e-mail from
 * its own `trashed_post` callback; the two are independent.
 *
 * @param int $post_id Post that was trashed.
 */
function submission_delete_uploads_on_trash($post_id): void
{
    $post_id = (int) $post_id;
    if ($post_id <= 0 || get_post_type($post_id) !== SUBMISSION_POST_TYPE) {
        return;
    }

    delete_submission_uploads($post_id);
}
add_action('trashed_post', 'submission_delete_uploads_on_trash');

/*
 * -------------------------------------------------------------------------
 * Restore from trash → back to «قيد المراجعة»
 * -------------------------------------------------------------------------
 */

/**
 * A restored request is pending again, not a draft (`wp_untrash_post_status` filter).
 *
 * Core restores to 'draft' (row action) or to the status before trashing (bulk
 * action, through wp_untrash_post_set_previous_status() at priority 10); drafts
 * are invisible to the submitter's account page and the digest. Priority 20 so
 * this wins in both paths. Approved requests (result term set) go back to 'publish'.
 *
 * @param string $new_status      Status core is about to apply.
 * @param int    $post_id         Post being restored.
 * @param string $previous_status Status before it was trashed.
 * @return string
 */
function submission_untrash_status($new_status, $post_id, $previous_status)
{
    $post_id = (int) $post_id;
    if ($post_id <= 0 || get_post_type($post_id) !== SUBMISSION_POST_TYPE) {
        return $new_status;
    }

    if ((int) get_post_meta($post_id, SUBMISSION_META_RESULT_TERM, true) > 0) {
        return 'publish';
    }

    return 'pending';
}
add_filter('wp_untrash_post_status', 'submission_untrash_status', 20, 3);

/**
 * A restored request is reviewable again (`untrashed_post` action): the rejection
 * reason (value + ACF key reference) and the last approval error are cleared.
 *
 * @param int $post_id Post that was restored.
 */
function submission_after_untrash($post_id): void
{
    $post_id = (int) $post_id;
    if ($post_id <= 0 || get_post_type($post_id) !== SUBMISSION_POST_TYPE) {
        return;
    }

    delete_post_meta($post_id, SUBMISSION_FIELD_REASON);
    delete_post_meta($post_id, '_' . SUBMISSION_FIELD_REASON);
    delete_post_meta($post_id, SUBMISSION_META_ERROR);
}
add_action('untrashed_post', 'submission_after_untrash');

/*
 * -------------------------------------------------------------------------
 * Admin assets for the edit screen (parent picker)
 * -------------------------------------------------------------------------
 */

/**
 * True on the add/edit screen of a request (post.php / post-new.php).
 */
function is_submission_admin_edit_screen(): bool
{
    if (!function_exists('get_current_screen')) {
        return false;
    }

    $screen = get_current_screen();

    return $screen instanceof WP_Screen
        && $screen->base === 'post'
        && $screen->post_type === SUBMISSION_POST_TYPE;
}

/**
 * Load assets/js/submissions.js in admin mode on the request edit screen
 * (`admin_enqueue_scripts` action). The taxonomy is read from the «النوع» select
 * there, so none is passed.
 */
function submission_admin_enqueue_scripts(): void
{
    if (!is_submission_admin_edit_screen()) {
        return;
    }

    submission_enqueue_script('', true);
}
add_action('admin_enqueue_scripts', 'submission_admin_enqueue_scripts');

/**
 * Small CSS for the edit screen (`admin_head` action): Latin-only inputs run
 * left-to-right and the picker levels get breathing room.
 */
function submission_admin_head_css(): void
{
    if (!is_submission_admin_edit_screen()) {
        return;
    }

    echo '<style>.submission-ltr input, .submission-ltr textarea{direction:ltr;text-align:left}.submission-parent-level{margin-top:.5rem}.submission-parent-level select{min-width:60%}</style>';
}
add_action('admin_head', 'submission_admin_head_css');
