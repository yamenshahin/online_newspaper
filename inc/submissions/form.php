<?php
/**
 * Entity submissions: front end.
 *
 * `[entity_submit]` (type picker, then an acf_form that creates a pending `user_submits` post) and
 * `[entity_account]` (our own login form, the submitter's request list). This file also owns every
 * acf/* field-level filter for the submission fields in both contexts (front-end form and the
 * clerk's edit screen), the submit-time validation and security gates, the post-creation work and
 * the title → `_normalized_name` sync.
 *
 * Uses the registry (inc/submissions/registry.php): constants, taxonomy helpers, depth rules, page
 * lookup, name/slug helpers, the request gate and the shared script enqueue. functions.php loads
 * the registry before this file.
 *
 * @package hello-elementor-child
 */

if (!defined('ABSPATH')) {
    exit;
}

// ---------------------------------------------------------------------------------------------
// Render-time context and shared class strings
// ---------------------------------------------------------------------------------------------

/**
 * Taxonomy of the form being rendered on the front end; '' everywhere else (admin, account page).
 *
 * @param string|null $set New value; null only reads.
 */
function submission_form_context(?string $set = null): string
{
    static $context = '';
    if ($set !== null) {
        $context = $set;
    }
    return $context;
}

/**
 * Shared Tailwind class strings (kept as complete literals so the watcher compiles them).
 */
function get_submission_form_class(string $name): string
{
    static $classes = null;
    if ($classes === null) {
        $classes = [
            'input' => 'w-full min-w-0 h-10 rounded-full border border-surface-container-highest bg-white px-4 text-sm text-on-background shadow-xs focus:outline-none focus:ring-2 focus:ring-primary/30',
            'label' => 'text-xs font-bold text-on-surface-variant px-1',
            'field' => 'flex flex-col gap-1',
            'button' => 'inline-flex items-center justify-center rounded-full bg-primary text-on-primary px-6 py-2 text-sm font-bold hover:opacity-90 transition-all',
            'link' => 'text-sm text-primary font-semibold underline-offset-2 hover:underline',
            'heading' => 'text-2xl font-bold text-on-background',
            'muted' => 'text-body-sm text-on-surface-variant',
            'error' => 'text-body-sm text-error',
            'card' => 'rounded-2xl border border-surface-container-high bg-surface-container-lowest p-6 text-center flex flex-col items-center gap-4',
            'type_card' => 'rounded-2xl border border-surface-container-high hover:border-primary bg-surface-container-lowest p-5 shadow-sm transition-all hover:shadow-lg flex items-center justify-between gap-3',
            'panel' => 'rounded-2xl border border-surface-container-high bg-surface-container-lowest p-6 md:p-8',
            'button_outline' => 'inline-flex items-center justify-center rounded-full border border-primary bg-white text-primary px-6 py-2 text-sm font-bold hover:bg-primary hover:text-on-primary transition-all',
            'info_box' => 'rounded-xl border border-surface-container-high bg-surface-container-lowest px-4 py-3',
            'row' => 'rounded-2xl border border-surface-container-high bg-surface-container-lowest p-4 flex flex-wrap items-center justify-between gap-3',
            'badge_published' => 'rounded-full bg-primary-container text-on-primary px-space-sm py-0.5 font-label-caps text-label-caps',
            'badge_rejected' => 'rounded-full bg-error-container text-on-error-container px-space-sm py-0.5 font-label-caps text-label-caps',
            'badge_pending' => 'rounded-full bg-surface-container text-on-surface-variant px-space-sm py-0.5 font-label-caps text-label-caps',
            'reason' => 'text-body-sm text-on-surface-variant w-full',
            'success_box' => 'rounded-2xl bg-primary-container text-on-primary px-4 py-3',
            'empty' => 'rounded-2xl border border-dashed border-surface-container-highest px-6 py-8 text-center text-on-surface-variant',
        ];
    }
    return $classes[$name] ?? '';
}

// ---------------------------------------------------------------------------------------------
// 5.1 Pages and head
// ---------------------------------------------------------------------------------------------

/**
 * Which submission page the main query is on: 'submit', 'account' or ''.
 */
function get_submission_form_page(): string
{
    if (!is_page()) {
        return '';
    }
    $page_id = (int) get_queried_object_id();
    if ($page_id <= 0) {
        return '';
    }
    if ($page_id === get_submission_page_id('submit')) {
        return 'submit';
    }
    if ($page_id === get_submission_page_id('account')) {
        return 'account';
    }
    $content = (string) get_post_field('post_content', $page_id, 'raw');
    if (has_shortcode($content, 'entity_submit')) {
        return 'submit';
    }
    if (has_shortcode($content, 'entity_account')) {
        return 'account';
    }
    return '';
}

/**
 * Both pages send no-cache headers; the submit page processes the acf_form POST before any output
 * (acf_form_head() validates, saves and redirects). Logged-out POSTs are simply not processed.
 */
function submission_template_redirect(): void
{
    $which = get_submission_form_page();
    if ($which === '') {
        return;
    }
    nocache_headers();
    do_action('litespeed_control_set_nocache', 'submission pages');

    if ($which === 'submit' && is_user_logged_in() && function_exists('acf_form_head')) {
        submission_form_request(true);
        acf_form_head();
    }
}
add_action('template_redirect', 'submission_template_redirect', 1);

/**
 * '<p>' notice when one of the two pages is missing; '' when both exist.
 */
function render_submission_form_missing_pages(): string
{
    if (get_submission_page_url('submit') !== '' && get_submission_page_url('account') !== '') {
        return '';
    }
    $text = esc_html__('الصفحة غير مُعدّة بعد.', 'hello-elementor-child');
    if (current_user_can('manage_options')) {
        $text .= ' ' . esc_html__('أنشئ صفحتَي submit-entity و my-account.', 'hello-elementor-child');
    }
    return '<p class="' . get_submission_form_class('muted') . '">' . $text . '</p>';
}

/**
 * Output container: the post body column width (max-w-4xl, as in single.php) with a plain header
 * like the home sections (title + subtitle at the start, action at the end), then the content.
 * Hello's own page title is hidden on these pages.
 *
 * @param string $inner    Body HTML.
 * @param string $title    Header title (escaped here); the page title when empty.
 * @param string $subtitle Header subtitle (escaped here).
 * @param string $action   Ready-made HTML for the end side of the header.
 */
function render_submission_form_wrap(string $inner, string $title = '', string $subtitle = '', string $action = ''): string
{
    $title = $title !== '' ? $title : get_the_title();

    $header = '<header class="flex flex-col md:flex-row md:items-end justify-between gap-space-md mb-space-lg">'
        . '<div class="flex flex-col gap-space-xs text-start">'
        . '<h1 class="font-headline-lg text-headline-lg text-on-background">' . esc_html($title) . '</h1>'
        . ($subtitle !== '' ? '<p class="font-body-sm text-body-sm text-on-surface-variant">' . esc_html($subtitle) . '</p>' : '')
        . '</div>'
        . ($action !== '' ? '<div class="flex flex-wrap items-center gap-3 shrink-0 self-start md:self-auto">' . $action . '</div>' : '')
        . '</header>';

    return '<section class="entity-submission w-full px-margin-mobile lg:px-margin pt-12 pb-24">'
        . '<div class="max-w-4xl mx-auto">'
        . $header
        . '<div class="flex flex-col gap-space-lg">' . $inner . '</div>'
        . '</div>'
        . '</section>';
}

/**
 * An outlined button for the header's action row.
 */
function render_submission_header_link(string $url, string $text): string
{
    return '<a class="' . get_submission_form_class('button_outline') . '" href="' . esc_url($url) . '">' . esc_html($text) . '</a>';
}

/**
 * Hide Hello's unpadded page title on the submission pages; render_submission_form_wrap() prints it
 * (`hello_elementor_page_title` filter).
 */
function submission_hide_page_title($show)
{
    if (is_page() && in_array((int) get_queried_object_id(), [get_submission_page_id('submit'), get_submission_page_id('account')], true)) {
        return false;
    }
    return $show;
}
add_filter('hello_elementor_page_title', 'submission_hide_page_title', 20);

// ---------------------------------------------------------------------------------------------
// 5.2 [entity_submit]
// ---------------------------------------------------------------------------------------------

/**
 * Shortcode `[entity_submit]`: login card → type picker (step 1) → acf_form for the chosen type (step 2).
 */
function render_submission_form_shortcode(): string
{
    $missing = render_submission_form_missing_pages();
    if ($missing !== '') {
        return $missing;
    }

    $type = '';
    if (is_user_logged_in() && isset($_GET['type']) && is_string($_GET['type'])) {
        $candidate = sanitize_key(wp_unslash($_GET['type']));
        if (is_submission_taxonomy($candidate)) {
            $type = $candidate;
        }
    }
    if ($type !== '') {
        submission_form_context($type);
    }
    submission_enqueue_script(submission_form_context(), false);

    $intro = __('اختر النوع، ثم أدخل البيانات. يُراجع الطلب قبل النشر.', 'hello-elementor-child');
    if (!is_user_logged_in()) {
        return render_submission_form_wrap(render_submission_form_login_card(), '', $intro);
    }
    if ($type === '') {
        return render_submission_form_wrap(render_submission_form_type_cards(), '', $intro);
    }
    return render_submission_form_wrap(
        render_submission_form_fields($type),
        /* translators: %s: type label (e.g. الفعاليات) */
        sprintf(__('إضافة: %s', 'hello-elementor-child'), get_submission_taxonomy_label($type)),
        __('الاسم بالعربية مطلوب، والاسم بالإنجليزية اختياري ويُستخدم لرابط الصفحة. الصور: jpg أو png أو webp حتى 3 ميغابايت. يُراجع الطلب قبل النشر ويصلك بريد عند النشر.', 'hello-elementor-child'),
        render_submission_header_link(get_submission_page_url('submit'), __('تغيير النوع', 'hello-elementor-child'))
    );
}
add_shortcode('entity_submit', 'render_submission_form_shortcode');

/**
 * Logged-out visitors: a card that sends them to the account page.
 */
function render_submission_form_login_card(): string
{
    return '<div class="' . get_submission_form_class('card') . '">'
        . '<p class="font-semibold text-on-background">' . esc_html__('سجّل الدخول لإرسال طلب إضافة', 'hello-elementor-child') . '</p>'
        . '<a class="' . get_submission_form_class('button') . '" href="' . esc_url(get_submission_page_url('account')) . '">'
        . esc_html__('تسجيل الدخول', 'hello-elementor-child') . '</a>'
        . '</div>';
}

/**
 * Step 1: one card per registry entry linking to `?type=<taxonomy>`.
 */
function render_submission_form_type_cards(): string
{
    $submit_url = get_submission_page_url('submit');

    $html = '<h2 class="' . get_submission_form_class('heading') . '">' . esc_html__('ماذا تريد إضافته؟', 'hello-elementor-child') . '</h2>';
    $html .= '<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">';
    foreach (array_keys(submission_taxonomies()) as $taxonomy) {
        $taxonomy = (string) $taxonomy;
        $html .= '<a class="' . get_submission_form_class('type_card') . '" href="' . esc_url(add_query_arg('type', $taxonomy, $submit_url)) . '">'
            . '<span class="font-semibold text-on-background">' . esc_html(get_submission_taxonomy_label($taxonomy)) . '</span>'
            . '<span class="text-sm text-primary font-semibold">' . esc_html__('إضافة ←', 'hello-elementor-child') . '</span>'
            . '</a>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Step 2: heading, rules, noscript hint and the acf_form that creates the pending request.
 */
function render_submission_form_fields(string $type): string
{
    if (!function_exists('acf_form')) {
        return '<p class="' . get_submission_form_class('error') . '">' . esc_html__('الصفحة غير مُعدّة بعد.', 'hello-elementor-child') . '</p>';
    }

    $account_url = get_submission_page_url('account');

    $html = '<noscript><p class="text-body-sm text-error">' . esc_html__('يتطلب هذا النموذج تفعيل JavaScript في المتصفح.', 'hello-elementor-child') . '</p></noscript>';

    ob_start();
    acf_form([
        'id'                 => SUBMISSION_FORM_ID . '-' . $type,
        'post_id'            => 'new_post',
        'new_post'           => ['post_type' => SUBMISSION_POST_TYPE, 'post_status' => 'pending', 'post_author' => get_current_user_id()],
        'field_groups'       => [SUBMISSION_GROUP, get_submission_taxonomy_group($type)], // index 1 is authoritative for the type
        'post_title'         => true,
        'post_content'       => false,
        'uploader'           => 'basic',
        'kses'               => true,
        'honeypot'           => true,
        'html_before_fields' => '<input type="hidden" name="submission_type" value="' . esc_attr($type) . '">',
        // Plain concatenation: add_query_arg() would encode the %post_id% placeholder.
        'return'             => $account_url . (str_contains($account_url, '?') ? '&' : '?') . 'submitted=%post_id%',
        'submit_value'       => __('إرسال الطلب', 'hello-elementor-child'),
        'html_submit_button' => '<button type="submit" class="inline-flex items-center justify-center rounded-full bg-primary text-on-primary px-6 py-3 text-sm font-bold hover:opacity-90 transition-all">%s</button>',
        'updated_message'    => false,
        'label_placement'    => 'top',
    ]);
    $html .= '<div class="' . get_submission_form_class('panel') . '">' . (string) ob_get_clean() . '</div>';

    return $html;
}

// ---------------------------------------------------------------------------------------------
// 5.3 Field filters (render-time context = submission_form_context() !== ''; request gate = is_submission_form_request())
// ---------------------------------------------------------------------------------------------

/**
 * `submission_taxonomy` select: choices come from the registry (all contexts).
 */
function submission_form_load_field_taxonomy(array $field): array
{
    $choices = [];
    foreach (array_keys(submission_taxonomies()) as $taxonomy) {
        $choices[(string) $taxonomy] = get_submission_taxonomy_label((string) $taxonomy);
    }
    $field['choices'] = $choices;
    return $field;
}
add_filter('acf/load_field/name=submission_taxonomy', 'submission_form_load_field_taxonomy');

/**
 * The type select is not rendered on the front end (the type comes from the form); untouched in admin.
 */
function submission_form_prepare_field_taxonomy(array $field): array|false
{
    return submission_form_context() !== '' ? false : $field;
}
add_filter('acf/prepare_field/name=submission_taxonomy', 'submission_form_prepare_field_taxonomy');

/**
 * acf_form's `_post_title` pseudo field gets an Arabic label and placeholder on the front end (required stays true).
 */
function submission_form_prepare_field_title(array $field): array
{
    if (submission_form_context() === '') {
        return $field;
    }
    $field['label'] = __('اسم الجهة / الفعالية بالعربية', 'hello-elementor-child');
    $field['placeholder'] = __('مثال: وزارة الصحة', 'hello-elementor-child');
    return $field;
}
add_filter('acf/prepare_field/key=_post_title', 'submission_form_prepare_field_title');

/**
 * Empty name on the front-end form: ACF's default message is untranslated («العنوان value is required»),
 * so return our own Arabic one under the gate (also catches whitespace-only names). Admin untouched.
 *
 * @param bool|string $valid
 * @param mixed       $value
 * @param string      $input
 * @return bool|string
 */
function submission_form_validate_title($valid, $value, array $field, $input)
{
    if (is_submission_form_request() && (!is_string($value) || trim($value) === '')) {
        return __('اسم الجهة / الفعالية بالعربية مطلوب.', 'hello-elementor-child');
    }
    return $valid;
}
add_filter('acf/validate_value/key=_post_title', 'submission_form_validate_title', 10, 4);

/**
 * Latin-only inputs (english_title, review_slug) carry the `submission-ltr` wrapper class (idempotent; also set in JSON).
 */
function submission_form_prepare_field_ltr(array $field): array
{
    $wrapper = is_array($field['wrapper'] ?? null) ? $field['wrapper'] : [];
    $classes = preg_split('/\s+/', trim((string) ($wrapper['class'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (!in_array('submission-ltr', $classes, true)) {
        $classes[] = 'submission-ltr';
    }
    $wrapper['class'] = implode(' ', $classes);
    $field['wrapper'] = $wrapper;
    return $field;
}
add_filter('acf/prepare_field/name=english_title', 'submission_form_prepare_field_ltr');
add_filter('acf/prepare_field/name=review_slug', 'submission_form_prepare_field_ltr');

/**
 * Front-end form only: every WYSIWYG field gets a small toolbar (bold, italic, lists, link),
 * the visual tab only and no media button. wp-admin and the term screens keep the field's own settings.
 */
function submission_form_prepare_field_wysiwyg($field)
{
    if (!is_array($field) || submission_form_context() === '' || is_admin()) {
        return $field;
    }
    $field['toolbar'] = 'submission';
    $field['tabs'] = 'visual';
    $field['media_upload'] = 0;
    return $field;
}
add_filter('acf/prepare_field/type=wysiwyg', 'submission_form_prepare_field_wysiwyg');

/**
 * The `submission` WYSIWYG toolbar used by the front-end form.
 */
function submission_form_wysiwyg_toolbars(array $toolbars): array
{
    $toolbars['submission'] = [1 => ['bold', 'italic', 'bullist', 'numlist', 'link', 'unlink']];
    return $toolbars;
}
add_filter('acf/fields/wysiwyg/toolbars', 'submission_form_wysiwyg_toolbars');

/**
 * Mount point for the JS parent picker, printed right after the `target_parent` number input (both contexts).
 */
function submission_form_render_parent_picker(array $field): void
{
    echo '<div class="submission-parent-picker" data-taxonomy="' . esc_attr(submission_form_context()) . '"></div>';
}
add_action('acf/render_field/name=target_parent', 'submission_form_render_parent_picker');

/**
 * Empty review_slug on the clerk's screen is pre-filled from the raw meta candidate (review_slug, else english_title).
 * get_submission_slug_candidate() reads raw meta only, so this filter cannot re-enter itself.
 *
 * @param mixed      $value
 * @param int|string $post_id
 * @return mixed
 */
function submission_form_load_value_review_slug($value, $post_id, array $field)
{
    static $running = false;
    if ($running || !is_admin() || !is_numeric($post_id) || is_array($value)) {
        return $value;
    }
    if (trim((string) $value) !== '') {
        return $value;
    }
    $running = true;
    $candidate = get_submission_slug_candidate((int) $post_id);
    $running = false;
    return $candidate;
}
add_filter('acf/load_value/name=review_slug', 'submission_form_load_value_review_slug', 10, 3);

/**
 * review_slug: non-empty values must sanitize to a Latin slug.
 *
 * @param bool|string $valid
 * @param mixed       $value
 * @return bool|string
 */
function submission_form_validate_review_slug($valid, $value, array $field, $input)
{
    if (is_array($value)) {
        return __('الرابط يجب أن يكون بحروف لاتينية صغيرة وأرقام وشرطات فقط.', 'hello-elementor-child');
    }
    $value = trim((string) $value);
    if ($value === '') {
        return $valid;
    }
    $slug = sanitize_title(wp_specialchars_decode($value, ENT_QUOTES));
    if (preg_match(SUBMISSION_SLUG_PATTERN, $slug) !== 1) {
        return __('الرابط يجب أن يكون بحروف لاتينية صغيرة وأرقام وشرطات فقط.', 'hello-elementor-child');
    }
    return $valid;
}
add_filter('acf/validate_value/name=review_slug', 'submission_form_validate_review_slug', 10, 4);

/**
 * english_title: Latin pattern, max 120 characters ('' is valid). $_POST is slashed (wp_magic_quotes) and ACF
 * validates the raw POST value, so unslash first; kses encodes `&` before validation and the helper decodes it.
 *
 * @param bool|string $valid
 * @param mixed       $value
 * @return bool|string
 */
function submission_form_validate_english_title($valid, $value, array $field, $input)
{
    if (is_array($value) || !is_submission_english_title_valid(wp_unslash((string) $value))) {
        return __('الاسم بالإنجليزية يجب أن يحتوي حروفاً لاتينية فقط (حتى 120 حرفاً).', 'hello-elementor-child');
    }
    return $valid;
}
add_filter('acf/validate_value/name=english_title', 'submission_form_validate_english_title', 10, 4);

/**
 * target_parent: must satisfy the taxonomy's depth rule. The taxonomy comes from the form's hidden
 * `submission_type` under the gate, else from the posted select, else from the edited post's meta.
 *
 * @param bool|string $valid
 * @param mixed       $value
 * @return bool|string
 */
function submission_form_validate_target_parent($valid, $value, array $field, $input)
{
    $taxonomy = '';
    if (is_submission_form_request()) {
        $taxonomy = get_submission_form_type();
    } else {
        $posted = $_POST['acf'][SUBMISSION_KEY_TAXONOMY] ?? null;
        if (is_string($posted)) {
            $taxonomy = sanitize_key(wp_unslash($posted));
        } else {
            $post_id = isset($_POST['post_ID']) && is_scalar($_POST['post_ID']) ? (int) $_POST['post_ID'] : 0;
            if ($post_id > 0) {
                $taxonomy = sanitize_key((string) get_post_meta($post_id, SUBMISSION_FIELD_TAXONOMY, true));
            }
        }
    }
    if (!is_submission_taxonomy($taxonomy)) {
        return $valid;
    }
    $parent = is_scalar($value) ? (int) $value : 0;
    $result = validate_submission_parent($taxonomy, $parent);
    return is_wp_error($result) ? $result->get_error_message() : $valid;
}
add_filter('acf/validate_value/name=target_parent', 'submission_form_validate_target_parent', 10, 4);

/**
 * Gate only: image/file fields may not receive an existing attachment. The basic uploader posts a
 * string - '' (no file / no JS) or the JS-built `url=…&size=…&type=…` - never a number and never an
 * array. A numeric value, or an array/object such as ['ID' => 123] that acf_idval() would resolve to
 * an attachment id in update_value(), selects a media-library item and is rejected.
 *
 * @param bool|string $valid
 * @param mixed       $value
 * @return bool|string
 */
function submission_form_validate_no_attachment_id($valid, $value, array $field, $input)
{
    if (!is_submission_form_request() || $value === null || $value === '') {
        return $valid;
    }
    if (!is_string($value) || is_numeric($value)) {
        return __('غير مسموح باختيار ملف موجود، ارفع صورة جديدة.', 'hello-elementor-child');
    }
    return $valid;
}
add_filter('acf/validate_value/type=image', 'submission_form_validate_no_attachment_id', 10, 4);
add_filter('acf/validate_value/type=file', 'submission_form_validate_no_attachment_id', 10, 4);

/**
 * Gate only: every posted select value must be one of the field's choices (ACF has no server-side check).
 *
 * @param bool|string $valid
 * @param mixed       $value
 * @return bool|string
 */
function submission_form_validate_select_choices($valid, $value, array $field, $input)
{
    if (!is_submission_form_request()) {
        return $valid;
    }
    $choices = is_array($field['choices'] ?? null) ? $field['choices'] : [];
    if ($choices === []) {
        return $valid;
    }
    foreach ((array) $value as $item) {
        if ($item === '' || $item === null) {
            continue;
        }
        if (!is_scalar($item) || is_bool($item) || !array_key_exists((string) $item, $choices)) {
            return __('قيمة غير صالحة.', 'hello-elementor-child');
        }
    }
    return $valid;
}
add_filter('acf/validate_value/type=select', 'submission_form_validate_select_choices', 10, 4);

/**
 * Gate only: jpg/jpeg/png/webp up to 3 MB. Runs in both `basic_upload` (client data, blocks the save)
 * and `upload` (the real file; a rejected file leaves the image empty but the request is still created).
 *
 * @param array<string, string> $errors
 * @param array<string, mixed>  $file
 * @param mixed                 $attachment
 * @return array<string, string>
 */
function submission_form_validate_attachment(array $errors, array $file, $attachment, array $field, $context): array
{
    if (!is_submission_form_request()) {
        return $errors;
    }
    $extension = strtolower((string) ($file['type'] ?? ''));
    if (!in_array($extension, SUBMISSION_IMAGE_EXTENSIONS, true)) {
        $errors['submission_type'] = __('نوع الملف غير مسموح. المسموح: jpg أو png أو webp.', 'hello-elementor-child');
    }
    $size = isset($file['size']) && is_scalar($file['size']) ? (int) $file['size'] : 0;
    if ($size > SUBMISSION_IMAGE_MAX_BYTES) {
        $errors['submission_size'] = __('حجم الصورة يتجاوز 3 ميغابايت.', 'hello-elementor-child');
    }
    return $errors;
}
add_filter('acf/validate_attachment', 'submission_form_validate_attachment', 10, 5);

// ---------------------------------------------------------------------------------------------
// 5.4 Submit-time validation and security
// ---------------------------------------------------------------------------------------------

/**
 * The type posted by the form's hidden `submission_type` input ('' when missing or not a string).
 */
function get_submission_form_type(): string
{
    $raw = $_POST['submission_type'] ?? '';
    return is_string($raw) ? sanitize_key(wp_unslash($raw)) : '';
}

/**
 * Rate-limit counters and their hourly limits: per user 5, per IP 10, site-wide 60.
 *
 * @return array<string, int>
 */
function get_submission_form_rate_limits(): array
{
    $ip = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    return [
        'submission_rate_user_' . get_current_user_id() => 5,
        'submission_rate_ip_' . md5($ip) => 10,
        'submission_rate_all' => 60,
    ];
}

/**
 * Hourly bucket (UTC clock hour) the rate-limit counters are kept in.
 */
function get_submission_form_rate_bucket(): string
{
    return gmdate('YmdH');
}

/**
 * Current value of one counter (0 when absent), read straight from wp_options. The counters are plain
 * option rows (never transients, never autoloaded) so the increment can be one atomic UPSERT shared by
 * all PHP workers; they are never read through get_option(), so the options cache stays out of the way.
 */
function get_submission_form_rate_count(string $key): int
{
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
        $key . '_' . get_submission_form_rate_bucket()
    ));
}

/**
 * Read-only check used for the friendly validation message.
 */
function is_submission_form_rate_limited(): bool
{
    foreach (get_submission_form_rate_limits() as $key => $limit) {
        if (get_submission_form_rate_count($key) >= $limit) {
            return true;
        }
    }
    return false;
}

/**
 * Check-and-increment right before the post is created, atomic across concurrent requests: each counter is
 * bumped with a single `INSERT … ON DUPLICATE KEY UPDATE option_value = option_value + 1` and read back, so
 * N simultaneous POSTs cannot all pass on the same stale count. Counters are checked user → IP → site and
 * the loop stops at the first saturated one (it stays saturated; the later ones are not touched, so a refused
 * user cannot exhaust the site-wide allowance). Older hourly buckets are swept after an accepted bump.
 */
function submission_form_bump_rate_limits(): bool
{
    global $wpdb;
    $bucket = get_submission_form_rate_bucket();
    foreach (get_submission_form_rate_limits() as $key => $limit) {
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '1', 'off')
             ON DUPLICATE KEY UPDATE option_value = option_value + 1",
            $key . '_' . $bucket
        ));
        if (get_submission_form_rate_count($key) > $limit) {
            return false;
        }
    }
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s",
        $wpdb->esc_like('submission_rate_') . '%',
        '%' . $wpdb->esc_like('_' . $bucket)
    ));
    return true;
}

/**
 * acf/validate_save_post (priority 10, after ACF's own field validation), only under the gate.
 */
function submission_validate_form(): void
{
    if (!is_submission_form_request()) {
        return;
    }
    if (!is_user_logged_in()) {
        acf_add_validation_error('', __('يجب تسجيل الدخول لإرسال طلب.', 'hello-elementor-child'));
        return;
    }

    $type = get_submission_form_type();
    if (!is_submission_taxonomy($type)) {
        acf_add_validation_error('acf[_post_title]', __('النوع غير صالح.', 'hello-elementor-child'));
        return;
    }
    if (!isset($_POST['acf']) || !is_array($_POST['acf'])) {
        acf_add_validation_error('', __('بيانات غير متطابقة.', 'hello-elementor-child'));
        return;
    }
    if (isset($_POST['acf'][SUBMISSION_KEY_TAXONOMY])) {
        $posted = $_POST['acf'][SUBMISSION_KEY_TAXONOMY];
        if (!is_string($posted) || sanitize_key(wp_unslash($posted)) !== $type) {
            acf_add_validation_error('acf[_post_title]', __('بيانات غير متطابقة.', 'hello-elementor-child'));
        }
    }

    // Read only here; acf/pre_save_post does the check-and-increment.
    if (is_submission_form_rate_limited()) {
        acf_add_validation_error('', __('لقد أرسلت عدداً كبيراً من الطلبات، حاول لاحقاً.', 'hello-elementor-child'));
    }

    // Title: plain text, length cap, then written back slashed (ACF hands it to wp_insert_post).
    $raw_title = $_POST['acf']['_post_title'] ?? '';
    $title = is_string($raw_title) ? sanitize_text_field(wp_unslash($raw_title)) : '';
    // Measure the decoded name: the real POST is kses-encoded ('&' -> '&amp;'), the AJAX pre-validation is not.
    if (mb_strlen(wp_specialchars_decode($title, ENT_QUOTES)) > SUBMISSION_TITLE_MAX) {
        acf_add_validation_error('acf[_post_title]', __('الاسم طويل جداً.', 'hello-elementor-child'));
    }
    $_POST['acf']['_post_title'] = wp_slash($title);
    if ($title === '') {
        // ACF's required check (priority 5) saw the raw value and only flags a truly empty string, and our
        // key=_post_title filter adds whitespace-only / non-string values; tag-only input passes both, and a
        // missing key never registers the field at all. Flag it here unless an error already targets the
        // input, so there is exactly one message and never a nameless request.
        foreach ((array) acf_get_validation_errors() as $error) {
            if (is_array($error) && ($error['input'] ?? '') === 'acf[_post_title]') {
                return;
            }
        }
        acf_add_validation_error('acf[_post_title]', __('الاسم مطلوب.', 'hello-elementor-child'));
        return;
    }

    $normalized = get_submission_normalized_name($title);
    if ($normalized === '') {
        return;
    }
    foreach (get_cached_taxonomy_term_map($type) as $row) {
        if ((string) ($row['key'] ?? '') === $normalized) {
            acf_add_validation_error(
                'acf[_post_title]',
                __('هذه الجهة موجودة بالفعل: ', 'hello-elementor-child') . esc_html((string) ($row['name'] ?? ''))
            );
            return;
        }
    }

    $pending = new WP_Query([
        'post_type' => SUBMISSION_POST_TYPE,
        'post_status' => 'pending',
        'meta_key' => SUBMISSION_META_NORMALIZED,
        'meta_value' => $normalized,
        'fields' => 'ids',
        'posts_per_page' => 1,
        'no_found_rows' => true,
    ]);
    if ($pending->have_posts()) {
        acf_add_validation_error('acf[_post_title]', __('يوجد طلب قيد المراجعة لهذه الجهة.', 'hello-elementor-child'));
    }
}
add_action('acf/validate_save_post', 'submission_validate_form', 10);

/**
 * acf/pre_save_post (priority 1, before ACF creates the post): the encrypted form blob is authoritative
 * for the type; login, type match and the rate limits are enforced with wp_die().
 *
 * @param int|string|false $post_id
 * @param mixed            $form
 * @return int|string|false
 */
function submission_guard_new_post($post_id, $form)
{
    if ($post_id !== 'new_post' || !is_array($form) || ($form['new_post']['post_type'] ?? '') !== SUBMISSION_POST_TYPE) {
        return $post_id;
    }

    $groups = is_array($form['field_groups'] ?? null) ? $form['field_groups'] : [];
    $type = get_submission_taxonomy_for_group((string) ($groups[1] ?? ''));
    if (!is_user_logged_in() || $type === '' || get_submission_form_type() !== $type) {
        wp_die(esc_html__('غير مسموح.', 'hello-elementor-child'), '', ['response' => 403]);
    }
    if (!submission_form_bump_rate_limits()) {
        wp_die(esc_html__('لقد أرسلت عدداً كبيراً من الطلبات، حاول لاحقاً.', 'hello-elementor-child'), '', ['response' => 429]);
    }
    return $post_id;
}
add_filter('acf/pre_save_post', 'submission_guard_new_post', 1, 2);

// ---------------------------------------------------------------------------------------------
// 5.5 After creation
// ---------------------------------------------------------------------------------------------

/**
 * acf/submit_form: store the type (from the authoritative field group), the normalized name, pin the
 * submission date and make sure status/author are what the form promised.
 *
 * @param mixed            $form
 * @param int|string|false $post_id
 */
function submission_after_submit($form, $post_id): void
{
    if (!is_array($form) || !str_starts_with((string) ($form['id'] ?? ''), SUBMISSION_FORM_ID)) {
        return;
    }
    if (!is_numeric($post_id) || (int) $post_id <= 0) {
        return;
    }
    $post_id = (int) $post_id;
    if (get_post_type($post_id) !== SUBMISSION_POST_TYPE) {
        return;
    }

    $groups = is_array($form['field_groups'] ?? null) ? $form['field_groups'] : [];
    $type = get_submission_taxonomy_for_group((string) ($groups[1] ?? ''));
    if ($type !== '') {
        update_field(SUBMISSION_KEY_TAXONOMY, $type, $post_id);
    }
    update_post_meta($post_id, SUBMISSION_META_NORMALIZED, get_submission_normalized_name(get_submission_name($post_id)));

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return;
    }
    $fix = [];
    if ($post->post_status !== 'pending') {
        $fix['post_status'] = 'pending';
    }
    if ((int) $post->post_author !== get_current_user_id()) {
        $fix['post_author'] = get_current_user_id();
    }
    // Pin the submission time. A pending post whose post_date_gmt is 0000-00-00 has a floating
    // date: every later wp_update_post() without edit_date (clerk saves, wp_trash_post) rewrites
    // post_date to "now", which re-lists the request in the digest and re-dates it in the account
    // list and the info box. A real GMT date switches that branch off for good.
    if (!$post->post_date_gmt || $post->post_date_gmt === '0000-00-00 00:00:00') {
        $fix['post_date']     = $post->post_date;
        $fix['post_date_gmt'] = get_gmt_from_date($post->post_date);
        $fix['edit_date']     = true; // tells wp_update_post() not to clear the date on this call
    }
    if ($fix !== []) {
        $fix['ID'] = $post_id;
        wp_update_post($fix);
    }
}
add_action('acf/submit_form', 'submission_after_submit', 10, 2);

/**
 * Keep `_normalized_name` in sync with the title on every save (type-specific hook: it only reads the title).
 */
function submission_form_sync_normalized_name(int $post_id, WP_Post $post, bool $update): void
{
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id) || $post->post_status === 'auto-draft') {
        return;
    }
    update_post_meta($post_id, SUBMISSION_META_NORMALIZED, get_submission_normalized_name(get_submission_name($post_id)));
}
add_action('save_post_' . SUBMISSION_POST_TYPE, 'submission_form_sync_normalized_name', 5, 3);

// ---------------------------------------------------------------------------------------------
// 5.6 [entity_account]
// ---------------------------------------------------------------------------------------------

/**
 * Shortcode `[entity_account]`: login form for visitors, greeting + request list for submitters.
 */
function render_submission_account_shortcode(): string
{
    $missing = render_submission_form_missing_pages();
    if ($missing !== '') {
        return $missing;
    }
    submission_enqueue_script(submission_form_context(), false);

    if (!is_user_logged_in()) {
        return render_submission_form_wrap(
            render_submission_account_login(),
            '',
            __('سجّل الدخول لإرسال طلب إضافة ومتابعة طلباتك.', 'hello-elementor-child')
        );
    }

    $user = wp_get_current_user();
    $action = '<a class="' . get_submission_form_class('button_outline') . '" href="' . esc_url(wp_logout_url(get_submission_page_url('account'))) . '">' . esc_html__('تسجيل الخروج', 'hello-elementor-child') . '</a>'
        . '<a class="' . get_submission_form_class('button') . '" href="' . esc_url(get_submission_page_url('submit')) . '">' . esc_html__('إرسال طلب جديد', 'hello-elementor-child') . '</a>';
    return render_submission_form_wrap(
        render_submission_account_overview(),
        '',
        /* translators: %s: the user's display name */
        sprintf(__('مرحباً، %s', 'hello-elementor-child'), (string) $user->display_name),
        $action
    );
}
add_shortcode('entity_account', 'render_submission_account_shortcode');

/**
 * Logged out: optional Nextend Google button, then our own form posting to wp-login.php
 * (wp_login_form() is not used because Nextend injects its buttons into it).
 */
function render_submission_account_login(): string
{
    $account_url = get_submission_page_url('account');

    $html = '<div class="' . get_submission_form_class('panel') . ' w-full max-w-xl mx-auto flex flex-col gap-4">';
    $html .= '<h2 class="' . get_submission_form_class('heading') . '">' . esc_html__('تسجيل الدخول', 'hello-elementor-child') . '</h2>';

    if (shortcode_exists('nextend_social_login')) {
        $html .= '<div class="submission-social-login" dir="ltr">'
            . do_shortcode('[nextend_social_login provider="google" style="fullwidth" redirect="' . esc_url($account_url) . '"]')
            . '</div>';
        $html .= '<div class="flex items-center gap-3 ' . get_submission_form_class('muted') . '">'
            . '<span class="h-px flex-1 bg-surface-container-high" aria-hidden="true"></span>'
            . '<span>' . esc_html__('أو', 'hello-elementor-child') . '</span>'
            . '<span class="h-px flex-1 bg-surface-container-high" aria-hidden="true"></span>'
            . '</div>';
    }

    if (isset($_GET['login']) && $_GET['login'] === 'failed') {
        $html .= '<p class="' . get_submission_form_class('error') . '" role="alert">' . esc_html__('بيانات الدخول غير صحيحة.', 'hello-elementor-child') . '</p>';
    }
    // wp-login.php sends the visitor back here (the lost-password link's redirect_to) once the reset mail is out.
    if (isset($_GET['checkemail']) && $_GET['checkemail'] === 'confirm') {
        $html .= '<div class="' . get_submission_form_class('info_box') . '" role="status"><p class="' . get_submission_form_class('muted') . '">' . esc_html__('تحقق من بريدك الإلكتروني: أرسلنا إليك رابط إعادة تعيين كلمة المرور، ثم سجّل الدخول من هنا.', 'hello-elementor-child') . '</p></div>';
    }

    $html .= '<form method="post" action="' . esc_url(site_url('wp-login.php', 'login_post')) . '" class="flex flex-col gap-4">';
    $html .= '<div class="' . get_submission_form_class('field') . '">'
        . '<label class="' . get_submission_form_class('label') . '" for="submission-login-user">' . esc_html__('البريد الإلكتروني أو اسم المستخدم', 'hello-elementor-child') . '</label>'
        . '<input type="text" name="log" id="submission-login-user" class="' . get_submission_form_class('input') . '" autocomplete="username" required>'
        . '</div>';
    $html .= '<div class="' . get_submission_form_class('field') . '">'
        . '<label class="' . get_submission_form_class('label') . '" for="submission-login-pass">' . esc_html__('كلمة المرور', 'hello-elementor-child') . '</label>'
        . '<input type="password" name="pwd" id="submission-login-pass" class="' . get_submission_form_class('input') . '" autocomplete="current-password" required>'
        . '</div>';
    $html .= '<label class="inline-flex items-center gap-2 text-sm text-on-surface-variant">'
        . '<input type="checkbox" name="rememberme" value="forever"> ' . esc_html__('تذكرني', 'hello-elementor-child')
        . '</label>';
    $html .= '<input type="hidden" name="redirect_to" value="' . esc_attr($account_url) . '">';
    $html .= wp_referer_field(false);
    $html .= '<div><button type="submit" name="wp-submit" class="' . get_submission_form_class('button') . '">' . esc_html__('دخول', 'hello-elementor-child') . '</button></div>';
    $html .= '</form>';

    $links = [];
    if (get_option('users_can_register')) {
        $links[] = '<a class="' . get_submission_form_class('link') . '" href="' . esc_url(wp_registration_url()) . '">' . esc_html__('إنشاء حساب', 'hello-elementor-child') . '</a>';
    }
    $links[] = '<a class="' . get_submission_form_class('link') . '" href="' . esc_url(wp_lostpassword_url(add_query_arg('checkemail', 'confirm', $account_url))) . '">' . esc_html__('نسيت كلمة المرور؟', 'hello-elementor-child') . '</a>';
    $html .= '<div class="flex flex-wrap items-center gap-4">' . implode('', $links) . '</div>';
    $html .= '</div>';

    return $html;
}

/**
 * After a failed login that came from the account page, go back there with `?login=failed`
 * (wp-login.php would otherwise show its own form).
 *
 * @param mixed $username Unused; WordPress passes the attempted login.
 */
function submission_form_login_failed($username = ''): void
{
    $account_url = get_submission_page_url('account');
    if ($account_url === '') {
        return;
    }
    $referer = wp_get_referer();
    if (!is_string($referer) || $referer === '') {
        return;
    }
    $referer_path = untrailingslashit((string) (wp_parse_url($referer, PHP_URL_PATH) ?? ''));
    $account_path = untrailingslashit((string) (wp_parse_url($account_url, PHP_URL_PATH) ?? ''));
    if ($referer_path !== $account_path) {
        return;
    }
    $referer_host = (string) (wp_parse_url($referer, PHP_URL_HOST) ?? '');
    $account_host = (string) (wp_parse_url($account_url, PHP_URL_HOST) ?? '');
    if ($referer_host !== '' && strcasecmp($referer_host, $account_host) !== 0) {
        return;
    }
    wp_safe_redirect(add_query_arg('login', 'failed', $account_url));
    exit;
}
add_action('wp_login_failed', 'submission_form_login_failed');

/**
 * Logged in: greeting, logout link, new-request button, the `?submitted=` confirmation and the list.
 */
function render_submission_account_overview(): string
{
    $html = '';
    $submitted = isset($_GET['submitted']) && is_scalar($_GET['submitted']) ? absint($_GET['submitted']) : 0;
    if ($submitted > 0) {
        $post = get_post($submitted);
        if ($post instanceof WP_Post && $post->post_type === SUBMISSION_POST_TYPE && (int) $post->post_author === get_current_user_id()) {
            $html .= '<div class="' . get_submission_form_class('success_box') . '" role="status">' . esc_html__('تم استلام طلبك وسيُراجع قريباً.', 'hello-elementor-child') . '</div>';
        }
    }

    $html .= '<h2 class="' . get_submission_form_class('heading') . '">' . esc_html__('طلباتي', 'hello-elementor-child') . '</h2>';
    $html .= render_submission_account_list();

    return $html;
}

/**
 * The current user's requests (pending, published, rejected), newest first.
 */
function render_submission_account_list(): string
{
    $query = new WP_Query([
        'post_type' => SUBMISSION_POST_TYPE,
        'author' => get_current_user_id(),
        'post_status' => ['pending', 'publish', 'trash'],
        'posts_per_page' => 50,
        'orderby' => 'date',
        'order' => 'DESC',
        'no_found_rows' => true,
    ]);
    if (empty($query->posts)) {
        return '<div class="' . get_submission_form_class('empty') . '">' . esc_html__('لم ترسل أي طلب بعد.', 'hello-elementor-child') . '</div>';
    }

    $html = '<ul class="flex flex-col gap-3">';
    foreach ($query->posts as $post) {
        if ($post instanceof WP_Post) {
            $html .= render_submission_account_row($post);
        }
    }
    return $html . '</ul>';
}

/**
 * One request row. Badge order: published (any status with `_result_term_id`) → rejected (trash) → pending.
 */
function render_submission_account_row(WP_Post $post): string
{
    $post_id = (int) $post->ID;

    $meta = [];
    $type_label = get_submission_taxonomy_label((string) get_post_meta($post_id, SUBMISSION_FIELD_TAXONOMY, true));
    if ($type_label !== '') {
        $meta[] = $type_label;
    }
    $date = get_the_date('', $post);
    if (is_string($date) && $date !== '') {
        $meta[] = $date;
    }

    $badge = '';
    $extra = '';
    $term_id = (int) get_post_meta($post_id, SUBMISSION_META_RESULT_TERM, true);
    if ($term_id > 0) {
        $badge = '<span class="' . get_submission_form_class('badge_published') . '">' . esc_html__('منشور', 'hello-elementor-child') . '</span>';
        $link = get_term_link($term_id);
        if (!is_wp_error($link)) {
            $badge .= '<a class="' . get_submission_form_class('link') . '" href="' . esc_url($link) . '">' . esc_html__('عرض الصفحة', 'hello-elementor-child') . '</a>';
        }
    } elseif ($post->post_status === 'trash') {
        $badge = '<span class="' . get_submission_form_class('badge_rejected') . '">' . esc_html__('مرفوض', 'hello-elementor-child') . '</span>';
        $reason = get_post_meta($post_id, SUBMISSION_FIELD_REASON, true);
        $reason = is_string($reason) ? trim($reason) : '';
        if ($reason !== '') {
            $extra = '<p class="' . get_submission_form_class('reason') . '">' . nl2br(esc_html($reason)) . '</p>';
        }
    } else {
        $badge = '<span class="' . get_submission_form_class('badge_pending') . '">' . esc_html__('قيد المراجعة', 'hello-elementor-child') . '</span>';
    }

    return '<li class="' . get_submission_form_class('row') . '">'
        . '<div class="flex min-w-0 flex-col gap-1">'
        . '<span class="font-semibold text-on-background">' . esc_html(get_submission_name($post_id)) . '</span>'
        . '<span class="' . get_submission_form_class('muted') . '">' . esc_html(implode(' · ', $meta)) . '</span>'
        . '</div>'
        . '<div class="flex items-center gap-3">' . $badge . '</div>'
        . $extra
        . '</li>';
}
