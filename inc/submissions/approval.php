<?php
/**
 * Entity submissions: approval and rejection of «طلبات الإضافة» (`user_submits`).
 *
 * The clerk works on the classic edit screen of a pending request:
 * - «نشر» publishes it: convert_submission_to_term() creates the term in the chosen taxonomy,
 *   copies the ACF values to it and stores the term id in `_result_term_id`. A failure puts the
 *   request back to pending and records the reason in `_approval_error`.
 * - A rejection reason + «حفظ بإنتظار المراجعة» moves the request to the trash; post-type.php
 *   deletes the uploads and notifications.php mails the reason (`submission_rejected`).
 * - «نقل إلى سلة المهملات» rejects without a reason (same `trashed_post` path).
 *
 * ACF writes the clerk's values from the generic `save_post` hook at priority 10, so the handler
 * sits on the generic `save_post` at priority 20: `save_post_{post_type}` fires earlier and would
 * only see the previous save's values.
 *
 * Uses: registry constants and helpers (registry.php: is_submission_taxonomy(),
 * get_submission_taxonomy_group(), get_submission_name(), get_submission_slug_candidate(),
 * validate_submission_parent(), is_submission_form_request()) and submission_set_admin_notice()
 * (post-type.php).
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('save_post', 'submission_handle_admin_save', 20, 3);
add_filter('redirect_post_location', 'submission_redirect_post_location', 10, 2);
add_action('trashed_post', 'submission_on_trashed', 10, 1);

/**
 * Request-scoped flags shared by the save handler and the redirect filter.
 *
 * - 'redirect_to_list': the request was rejected; send the clerk back to the list.
 * - 'approval_failed': publishing failed; drop WordPress' own status message from the redirect.
 */
function submission_save_flag(string $flag, ?bool $set = null): bool
{
    static $flags = [];

    if ($set !== null) {
        $flags[$flag] = $set;
    }
    return (bool) ($flags[$flag] ?? false);
}

/**
 * Runs $callback with the admin save handler detached. Nested wp_trash_post() / wp_update_post()
 * calls fire `save_post` again and must not re-enter the handler.
 */
function submission_without_admin_save(callable $callback): mixed
{
    remove_action('save_post', 'submission_handle_admin_save', 20);
    try {
        return $callback();
    } finally {
        add_action('save_post', 'submission_handle_admin_save', 20, 3);
    }
}

/**
 * Generic `save_post`, priority 20 (after ACF saved the clerk's values).
 *
 * 1. Rejection: a non-empty rejection reason posted from the clerk's edit-screen save (post.php:
 *    an admin request carrying this post's post_ID, never the front-end acf_form POST) trashes an
 *    unapproved request (the Trash row action rejects without a reason).
 * 2. Publish: a published request without `_result_term_id` is converted to a term; on failure it
 *    goes back to pending with the error stored in `_approval_error`.
 *
 * @param int|string   $post_id
 * @param WP_Post|null $post
 * @param bool         $update
 */
function submission_handle_admin_save($post_id, $post, $update = false): void
{
    static $running = false;

    if ($running || !($post instanceof WP_Post) || $post->post_type !== SUBMISSION_POST_TYPE) {
        return;
    }
    $post_id = (int) $post_id;
    if ($post_id <= 0 || $post->post_status === 'auto-draft' || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    $running = true;
    try {
        $has_result = (int) get_post_meta($post_id, SUBMISSION_META_RESULT_TERM, true) > 0;

        // 1. Rejection — only when the reason is posted from the clerk's edit screen for this post in
        //    this request: post.php checked the update-post_{ID} nonce and the edit capability before
        //    save_post, and its classic form always posts post_ID (acf_form never does). Never from the
        //    front-end form POST: ACF creates the request inside acf/pre_save_post while $_POST['acf']
        //    still holds every posted key (its field-key whitelist runs afterwards), so a submitter
        //    could otherwise trash their own new request. WP-CLI, REST and AJAX are excluded as well.
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- post.php's update-post_{ID} nonce was checked before save_post.
        $posted_id = $_POST['post_ID'] ?? 0;
        $from_edit_screen = is_admin()
            && !wp_doing_ajax()
            && !is_submission_form_request()
            && is_scalar($posted_id) && (int) $posted_id === $post_id;
        $acf_post = $from_edit_screen && isset($_POST['acf']) && is_array($_POST['acf']) ? $_POST['acf'] : [];
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        $raw_reason = $acf_post[SUBMISSION_KEY_REASON] ?? '';
        $posted = is_scalar($raw_reason) ? trim((string) wp_unslash((string) $raw_reason)) : '';
        if ($posted !== '' && $post->post_status !== 'trash' && !$has_result) {
            $trashed = submission_without_admin_save(static fn() => wp_trash_post($post_id));
            if ($trashed) {
                submission_save_flag('redirect_to_list', true);
                submission_set_admin_notice('success', esc_html__('تم رفض الطلب وإشعار المُرسل.', 'hello-elementor-child'));
            } else {
                submission_set_admin_notice('error', esc_html__('تعذر نقل الطلب إلى سلة المهملات.', 'hello-elementor-child'));
            }
            return;
        }

        // 2. Publish.
        if ($post->post_status !== 'publish' || $has_result) {
            return;
        }

        // ACF returns without saving when its validation fails on publish, while WordPress has
        // already set the status: don't convert stale values, go back to pending.
        $acf_errors = function_exists('acf_get_validation_errors') ? acf_get_validation_errors() : false;
        if (!empty($acf_errors) && is_array($acf_errors)) {
            $messages = array_filter(wp_list_pluck($acf_errors, 'message'), 'is_string');
            submission_set_admin_notice(
                'error',
                esc_html__('لم يُنشر الطلب: ', 'hello-elementor-child') . esc_html(implode(' / ', $messages))
            );
            submission_save_flag('approval_failed', true);
            submission_without_admin_save(static fn() => wp_update_post(['ID' => $post_id, 'post_status' => 'pending']));
            return;
        }

        // Two overlapping publish requests (double click, two clerks) must not both convert.
        $lock = 'submission_lock_' . $post_id;
        if (get_transient($lock)) {
            return;
        }
        set_transient($lock, 1, 30);
        try {
            $result = convert_submission_to_term($post_id);
            if (is_wp_error($result)) {
                clean_post_cache($post_id);
                if ((int) get_post_meta($post_id, SUBMISSION_META_RESULT_TERM, true) > 0) {
                    return; // a parallel request created the term meanwhile
                }
                $message = $result->get_error_message();
                update_post_meta($post_id, SUBMISSION_META_ERROR, $message);
                submission_set_admin_notice(
                    'error',
                    esc_html__('لم يُنشر الطلب: ', 'hello-elementor-child') . esc_html($message)
                );
                submission_save_flag('approval_failed', true);
                submission_without_admin_save(static fn() => wp_update_post(['ID' => $post_id, 'post_status' => 'pending']));
                return;
            }
            delete_post_meta($post_id, SUBMISSION_META_ERROR);
            submission_set_admin_notice('success', submission_published_notice((int) $result));
        } finally {
            delete_transient($lock);
        }
    } finally {
        $running = false;
    }
}

/**
 * Success notice for a published request: «تم نشر الصفحة:» + links to view and edit the term.
 */
function submission_published_notice(int $term_id): string
{
    $text = esc_html__('تم نشر الصفحة:', 'hello-elementor-child');
    $term = get_term($term_id);
    if (!($term instanceof WP_Term)) {
        return $text;
    }

    $links = [];
    $view = get_term_link($term);
    if (is_string($view)) {
        $links[] = '<a href="' . esc_url($view) . '">' . esc_html__('عرض', 'hello-elementor-child') . '</a>';
    }
    $edit = get_edit_term_link($term);
    if (is_string($edit) && $edit !== '') {
        $links[] = '<a href="' . esc_url($edit) . '">' . esc_html__('تحرير', 'hello-elementor-child') . '</a>';
    }
    return $links === [] ? $text : $text . ' ' . implode(' · ', $links);
}

/**
 * `redirect_post_location`: after a rejection go back to the list; after a failed publish drop
 * WordPress' own "published / pending" message so only our notice is shown.
 *
 * @param string     $location
 * @param int|string $post_id
 */
function submission_redirect_post_location($location, $post_id): string
{
    $location = (string) $location;
    if (submission_save_flag('redirect_to_list')) {
        return admin_url('edit.php?post_type=' . SUBMISSION_POST_TYPE);
    }
    if (submission_save_flag('approval_failed')) {
        return remove_query_arg('message', $location);
    }
    return $location;
}

/**
 * Creates the term for an approved request and copies its ACF values to it.
 *
 * Reads the submission's own fields as raw meta (never get_field(): review_slug is filtered).
 * Returns the new term id, or a WP_Error whose message is shown to the clerk and stored in
 * `_approval_error`. Fires `submission_approved` ($term_id, $post_id) on success.
 */
function convert_submission_to_term(int $post_id): int|WP_Error
{
    // 1. Type.
    $taxonomy = (string) get_post_meta($post_id, SUBMISSION_FIELD_TAXONOMY, true);
    if (!is_submission_taxonomy($taxonomy)) {
        return new WP_Error('no_type', __('النوع غير محدد.', 'hello-elementor-child'));
    }

    // 2. Name (raw title, entities decoded).
    $name = get_submission_name($post_id);
    if ($name === '') {
        return new WP_Error('no_name', __('الاسم مطلوب.', 'hello-elementor-child'));
    }

    // 3. Slug: review_slug, else english_title; Latin only, never derived from the Arabic name.
    $slug = get_submission_slug_candidate($post_id);
    if ($slug === '') {
        return new WP_Error('no_slug', __('الرابط (slug) بالإنجليزية مطلوب قبل النشر.', 'hello-elementor-child'));
    }
    if ((string) get_post_meta($post_id, SUBMISSION_FIELD_SLUG, true) === '') {
        update_field(SUBMISSION_KEY_SLUG, $slug, $post_id);
    }

    // 4. Parent (depth rule of the taxonomy).
    $parent = (int) get_post_meta($post_id, SUBMISSION_FIELD_PARENT, true);
    $parent_check = validate_submission_parent($taxonomy, $parent);
    if (is_wp_error($parent_check)) {
        return $parent_check;
    }

    // 5. Duplicates — wp_insert_term() would silently suffix the slug with -2.
    $existing = get_term_by('slug', $slug, $taxonomy);
    if ($existing instanceof WP_Term) {
        return new WP_Error(
            'slug_exists',
            __('توجد صفحة بنفس الرابط: ', 'hello-elementor-child') . wp_specialchars_decode($existing->name, ENT_QUOTES)
        );
    }
    if (term_exists($name, $taxonomy, $parent)) {
        return new WP_Error('name_exists', __('توجد صفحة بنفس الاسم ضمن نفس التصنيف الرئيسي.', 'hello-elementor-child'));
    }

    // 6. Term.
    $inserted = wp_insert_term($name, $taxonomy, ['slug' => $slug, 'parent' => $parent]);
    if (is_wp_error($inserted)) {
        return $inserted;
    }
    $term_id = (int) $inserted['term_id'];

    // 7. Copy the type's fields. Raw values (repeater rows keyed by sub-field key, group arrays,
    //    attachment ids) round-trip unchanged through update_field(); attachments keep their parent.
    $group = get_submission_taxonomy_group($taxonomy);
    $fields = $group !== '' ? acf_get_fields($group) : [];
    if (is_array($fields)) {
        foreach ($fields as $field) {
            if (!is_array($field) || empty($field['key'])) {
                continue;
            }
            $raw = get_field($field['key'], $post_id, false);
            if ($raw === null || $raw === '' || $raw === false || $raw === []) {
                continue;
            }
            update_field($field['key'], $raw, 'term_' . $term_id);
        }
    }

    // 8. Term meta.
    update_term_meta($term_id, SUBMISSION_TERM_META_SUBMITTED_BY, (int) get_post_field('post_author', $post_id, 'raw'));
    update_term_meta($term_id, SUBMISSION_TERM_META_SUBMISSION, $post_id);
    $english = trim(wp_specialchars_decode((string) get_post_meta($post_id, SUBMISSION_FIELD_ENGLISH, true), ENT_QUOTES));
    if ($english !== '') {
        update_term_meta($term_id, SUBMISSION_TERM_META_ENGLISH, $english);
    }

    // 9. Link the request to its page.
    update_post_meta($post_id, SUBMISSION_META_RESULT_TERM, $term_id);

    // 10. `created_term` already flushed the entity term map cache.
    do_action('submission_approved', $term_id, $post_id);

    return $term_id;
}

/**
 * `trashed_post`: trashing an unapproved request is a rejection, with or without a reason.
 * post-type.php deletes the uploads on the same hook; notifications.php mails the submitter.
 *
 * @param int|string $post_id
 */
function submission_on_trashed($post_id): void
{
    $post_id = (int) $post_id;
    if ($post_id <= 0 || get_post_type($post_id) !== SUBMISSION_POST_TYPE) {
        return;
    }
    if ((int) get_post_meta($post_id, SUBMISSION_META_RESULT_TERM, true) > 0) {
        return;
    }
    do_action('submission_rejected', $post_id);
}
