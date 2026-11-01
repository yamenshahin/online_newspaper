<?php
/**
 * Entity submissions: registry.
 *
 * Shared vocabulary of the submission module (inc/submissions/): constants,
 * the taxonomy registry (which entity taxonomies accept requests and which
 * ACF term field group each one uses), the depth rules that decide under
 * which level of a taxonomy a request may be filed, the public
 * /hello/v1/submission-parents route that feeds the parent picker, the two
 * page lookups, raw-meta name/slug helpers, the server-set request gate and
 * the shared JS enqueue. The REST route is the only hook registered here.
 *
 * Depths are computed from the cached term map (inc/taxonomy-helpers.php),
 * never hard-coded: leaf depth = deepest level that has terms, parent depth =
 * leaf depth - 1 (-1 = flat taxonomy, a request takes no parent at all).
 *
 * Name and slug helpers read RAW post meta / post fields only, never
 * get_field(): form.php fills an empty review_slug through acf/load_value
 * with get_submission_slug_candidate(), and a get_field() inside it would
 * re-enter that filter forever.
 */
if (!defined('ABSPATH')) {
    exit;
}

const SUBMISSION_POST_TYPE = 'user_submits';
const SUBMISSION_ROLE = 'submitter';
const SUBMISSION_FORM_ID = 'entity-submit-form'; // actual acf_form id = SUBMISSION_FORM_ID . '-' . $taxonomy
const SUBMISSION_SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
const SUBMISSION_ENGLISH_TITLE_PATTERN = "/^[A-Za-z0-9][A-Za-z0-9 &'().,\\-]*$/";
const SUBMISSION_TITLE_MAX = 150; // characters, post title
const SUBMISSION_IMAGE_MAX_BYTES = 3 * 1024 * 1024;
const SUBMISSION_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

// Post meta (submission) and term meta (published page).
const SUBMISSION_META_RESULT_TERM = '_result_term_id';
const SUBMISSION_META_NORMALIZED = '_normalized_name';
const SUBMISSION_META_ERROR = '_approval_error';
const SUBMISSION_TERM_META_SUBMITTED_BY = '_submitted_by';
const SUBMISSION_TERM_META_SUBMISSION = '_submission_id';
const SUBMISSION_TERM_META_ENGLISH = '_english_title';

// ACF field names / keys (group_submission, group_submission_review).
const SUBMISSION_FIELD_TAXONOMY = 'submission_taxonomy';
const SUBMISSION_KEY_TAXONOMY = 'field_submission_taxonomy';
const SUBMISSION_FIELD_ENGLISH = 'english_title';
const SUBMISSION_KEY_ENGLISH = 'field_submission_english_title';
const SUBMISSION_FIELD_PARENT = 'target_parent';
const SUBMISSION_KEY_PARENT = 'field_submission_target_parent';
const SUBMISSION_FIELD_SLUG = 'review_slug';
const SUBMISSION_KEY_SLUG = 'field_submission_review_slug';
const SUBMISSION_FIELD_REASON = 'rejection_reason';
const SUBMISSION_KEY_REASON = 'field_submission_rejection_reason';
const SUBMISSION_FIELD_NOTES = 'review_notes';
const SUBMISSION_KEY_NOTES = 'field_submission_review_notes';
const SUBMISSION_GROUP = 'group_submission';
const SUBMISSION_GROUP_REVIEW = 'group_submission_review';

// ---------------------------------------------------------------------------
// Taxonomy registry
// ---------------------------------------------------------------------------

/**
 * Entity taxonomies that accept requests.
 *
 * @return array<string, array{label:string, group:string}> taxonomy => Arabic label + ACF term field-group key
 */
function submission_taxonomies(): array
{
    return apply_filters('submission_taxonomies', [
        'government_entity' => ['label' => __('الجهات الحكومية السعودية', 'hello-elementor-child'), 'group' => 'group_6aa2bdac2e1bc'],
        'private_entity' => ['label' => __('القطاع الخاص', 'hello-elementor-child'), 'group' => 'group_6aa2bdae8888f'],
        'event' => ['label' => __('الفعاليات', 'hello-elementor-child'), 'group' => 'group_6ac7cfb4e2327'],
        'international_entity' => ['label' => __('الجهات الدولية', 'hello-elementor-child'), 'group' => 'group_7ac66e32d00b2'],
    ]);
}

function is_submission_taxonomy(string $taxonomy): bool
{
    return $taxonomy !== '' && isset(submission_taxonomies()[$taxonomy]);
}

/** Arabic label of a registry taxonomy; '' if unknown. */
function get_submission_taxonomy_label(string $taxonomy): string
{
    return (string) (submission_taxonomies()[$taxonomy]['label'] ?? '');
}

/** ACF term field-group key of a registry taxonomy; '' if unknown. */
function get_submission_taxonomy_group(string $taxonomy): string
{
    return (string) (submission_taxonomies()[$taxonomy]['group'] ?? '');
}

/** Reverse lookup: ACF term field-group key => taxonomy; '' if unknown. */
function get_submission_taxonomy_for_group(string $group_key): string
{
    if ($group_key === '') {
        return '';
    }
    foreach (submission_taxonomies() as $taxonomy => $entry) {
        if ((string) ($entry['group'] ?? '') === $group_key) {
            return (string) $taxonomy;
        }
    }
    return '';
}

// ---------------------------------------------------------------------------
// Depth rules
// ---------------------------------------------------------------------------

/**
 * Depth of every term of a taxonomy plus the deepest level, computed once per
 * request from the cached term map (0 = root).
 *
 * A term whose parent is missing from the map, or a parent loop, counts as a
 * root, so a broken hierarchy can never hang the request.
 *
 * @return array{depth: array<int, int>, leaf: int}
 */
function get_submission_depth_map(string $taxonomy): array
{
    static $cache = [];
    if (isset($cache[$taxonomy])) {
        return $cache[$taxonomy];
    }

    $map = get_cached_taxonomy_term_map($taxonomy);
    $depths = [];
    $leaf = 0;

    foreach ($map as $id => $row) {
        $id = (int) $id;
        if (isset($depths[$id])) {
            continue;
        }
        // Walk up until a term with a known depth or a root, then assign the chain top-down.
        $chain = [];
        $in_chain = [];
        $cursor = $id;
        $base = -1; // depth of the known ancestor the walk stopped at; -1 = reached a root
        while (true) {
            if (isset($depths[$cursor])) {
                $base = $depths[$cursor];
                break;
            }
            $chain[] = $cursor;
            $in_chain[$cursor] = true;
            $parent = (int) ($map[$cursor]['parent'] ?? 0);
            if ($parent <= 0 || !isset($map[$parent]) || isset($in_chain[$parent])) {
                break;
            }
            $cursor = $parent;
        }
        $depth = $base;
        for ($i = count($chain) - 1; $i >= 0; $i--) {
            $depth++;
            $depths[$chain[$i]] = $depth;
            if ($depth > $leaf) {
                $leaf = $depth;
            }
        }
    }

    $cache[$taxonomy] = ['depth' => $depths, 'leaf' => $leaf];
    return $cache[$taxonomy];
}

/** Depth of a term: 0 = root; -1 = not in the taxonomy (or term 0). */
function get_submission_term_depth(string $taxonomy, int $term_id): int
{
    if ($term_id <= 0) {
        return -1;
    }
    return get_submission_depth_map($taxonomy)['depth'][$term_id] ?? -1;
}

/** Deepest level that has terms; 0 if the taxonomy is flat or empty. */
function get_submission_leaf_depth(string $taxonomy): int
{
    return get_submission_depth_map($taxonomy)['leaf'];
}

/** Level a request's parent must sit on: leaf depth - 1; -1 = "no parent". */
function get_submission_parent_depth(string $taxonomy): int
{
    return get_submission_leaf_depth($taxonomy) - 1;
}

/**
 * Children of $parent (0 = roots) when their depth <= parent depth; [] otherwise.
 * Items are {id, name} in the map's (alphabetical) order.
 *
 * @return array<int, array{id:int, name:string}>
 */
function get_submission_parent_options(string $taxonomy, int $parent): array
{
    $parent_depth = get_submission_parent_depth($taxonomy);
    if ($parent_depth < 0 || $parent < 0) {
        return [];
    }
    if ($parent === 0) {
        $child_depth = 0;
    } else {
        $own_depth = get_submission_term_depth($taxonomy, $parent);
        if ($own_depth < 0) {
            return [];
        }
        $child_depth = $own_depth + 1;
    }
    if ($child_depth > $parent_depth) {
        return [];
    }

    $map = get_cached_taxonomy_term_map($taxonomy);
    $index = get_taxonomy_children_index($taxonomy);
    $items = [];
    foreach ($index[$parent] ?? [] as $id) {
        $id = (int) $id;
        if (!isset($map[$id])) {
            continue;
        }
        $items[] = [
            'id' => $id,
            'name' => (string) ($map[$id]['name'] ?? ''),
        ];
    }
    return $items;
}

/**
 * Ancestor ids from the root down to the term itself (inclusive); [] for 0 or an unknown term.
 *
 * @return int[]
 */
function get_submission_term_path(string $taxonomy, int $term_id): array
{
    if ($term_id <= 0) {
        return [];
    }
    $map = get_cached_taxonomy_term_map($taxonomy);
    if (!isset($map[$term_id])) {
        return [];
    }
    $path = [];
    $seen = [];
    $cursor = $term_id;
    while ($cursor > 0 && isset($map[$cursor]) && !isset($seen[$cursor])) {
        $seen[$cursor] = true;
        $path[] = $cursor;
        $cursor = (int) ($map[$cursor]['parent'] ?? 0);
    }
    return array_reverse($path);
}

/**
 * Parent rule: parent depth -1 => $parent must be 0; otherwise $parent must be a
 * term of the taxonomy whose depth equals the parent depth.
 *
 * The WP_Error messages are the only texts shown for parent errors (form.php
 * returns them from acf/validate_value, approval.php passes them through).
 */
function validate_submission_parent(string $taxonomy, int $parent): true|WP_Error
{
    $parent_depth = get_submission_parent_depth($taxonomy);

    if ($parent_depth < 0) {
        if ($parent !== 0) {
            return new WP_Error('parent_not_allowed', __('هذا النوع لا يحتاج إلى تصنيف رئيسي.', 'hello-elementor-child'));
        }
        return true;
    }

    if ($parent === 0) {
        return new WP_Error('parent_required', __('يرجى اختيار التصنيف الرئيسي حتى آخر مستوى.', 'hello-elementor-child'));
    }

    $depth = get_submission_term_depth($taxonomy, $parent);
    if ($depth < 0) {
        return new WP_Error('parent_unknown', __('التصنيف الرئيسي المختار غير موجود.', 'hello-elementor-child'));
    }
    if ($depth !== $parent_depth) {
        return new WP_Error(
            'parent_depth',
            sprintf(
                /* translators: %d: 1-based level number the parent must be picked from */
                __('يرجى اختيار التصنيف الرئيسي من المستوى الأخير (المستوى %d).', 'hello-elementor-child'),
                $parent_depth + 1
            )
        );
    }
    return true;
}

// ---------------------------------------------------------------------------
// Pages
// ---------------------------------------------------------------------------

/**
 * Page id of the submit ('submit' => slug submit-entity) or account ('account' => slug my-account) page; 0 if missing.
 * Slugs can be changed with the `submission_page_slug` filter ($slug, $which).
 */
function get_submission_page_id(string $which): int
{
    $defaults = ['submit' => 'submit-entity', 'account' => 'my-account'];
    if (!isset($defaults[$which])) {
        return 0;
    }
    $slug = (string) apply_filters('submission_page_slug', $defaults[$which], $which);
    if ($slug === '') {
        return 0;
    }
    $page = get_page_by_path($slug, OBJECT, 'page');
    return $page instanceof WP_Post ? (int) $page->ID : 0;
}

/** Permalink of the submit / account page; '' if the page is missing. */
function get_submission_page_url(string $which): string
{
    $page_id = get_submission_page_id($which);
    if ($page_id <= 0) {
        return '';
    }
    $url = get_permalink($page_id);
    return is_string($url) ? $url : '';
}

// ---------------------------------------------------------------------------
// Names and slugs (raw meta only, never get_field())
// ---------------------------------------------------------------------------

/** Raw post title, entities decoded, trimmed: use for wp_insert_term, normalisation, e-mails and columns. */
function get_submission_name(int $post_id): string
{
    return trim(wp_specialchars_decode((string) get_post_field('post_title', $post_id, 'raw'), ENT_QUOTES));
}

/** Same folding as the cached term map's `key`, so a submitted name can be compared with existing terms. */
function get_submission_normalized_name(string $name): string
{
    return normalize_entity_text(wp_specialchars_decode($name, ENT_QUOTES));
}

/**
 * Slug for the future term: review_slug meta if non-empty, else english_title
 * meta, each entity-decoded then sanitize_title()d. Returned only when the
 * result matches SUBMISSION_SLUG_PATTERN (Latin only, no transliteration), else ''.
 */
function get_submission_slug_candidate(int $post_id): string
{
    if ($post_id <= 0) {
        return '';
    }
    foreach ([SUBMISSION_FIELD_SLUG, SUBMISSION_FIELD_ENGLISH] as $meta_key) {
        $raw = get_post_meta($post_id, $meta_key, true);
        $raw = is_scalar($raw) ? trim((string) $raw) : '';
        if ($raw === '') {
            continue;
        }
        $slug = sanitize_title(wp_specialchars_decode($raw, ENT_QUOTES));
        return preg_match(SUBMISSION_SLUG_PATTERN, $slug) === 1 ? $slug : '';
    }
    return '';
}

/** Optional English name: '' is valid; otherwise Latin pattern and at most 120 characters (after entity decoding). */
function is_submission_english_title_valid(string $value): bool
{
    $value = wp_specialchars_decode(trim($value), ENT_QUOTES);
    if ($value === '') {
        return true;
    }
    return mb_strlen($value) <= 120 && preg_match(SUBMISSION_ENGLISH_TITLE_PATTERN, $value) === 1;
}

// ---------------------------------------------------------------------------
// Request gate (server-set; replaces any client marker)
// ---------------------------------------------------------------------------

/** Static flag: form.php sets it to true right before acf_form_head() on the submit page. */
function submission_form_request(?bool $set = null): bool
{
    static $flag = false;
    if ($set !== null) {
        $flag = $set;
    }
    return $flag;
}

/**
 * True during our own form POST (flag) or during ACF's AJAX pre-validation of
 * a new_post form, so the field-level checks show their messages inline too.
 */
function is_submission_form_request(): bool
{
    if (submission_form_request()) {
        return true;
    }
    if (!wp_doing_ajax()) {
        return false;
    }
    $action = $_POST['action'] ?? '';
    $post_id = $_POST['_acf_post_id'] ?? '';
    return is_string($action) && $action === 'acf/validate_save_post'
        && is_string($post_id) && $post_id === 'new_post';
}

// ---------------------------------------------------------------------------
// Shared JS (parent picker; both contexts call this, nobody else enqueues it)
// ---------------------------------------------------------------------------

/**
 * Labels of the parent picker.
 *
 * @return array{choose:string, noParent:string, noOptions:string, loading:string, error:string, level:string}
 */
function get_submission_js_labels(): array
{
    return [
        'choose' => __('اختر…', 'hello-elementor-child'),
        'noParent' => __('لا يحتاج هذا النوع إلى تصنيف رئيسي.', 'hello-elementor-child'),
        'noOptions' => __('لا توجد خيارات متاحة', 'hello-elementor-child'),
        'loading' => __('جارٍ التحميل…', 'hello-elementor-child'),
        'error' => __('تعذر تحميل الخيارات، حاول مرة أخرى.', 'hello-elementor-child'),
        /* translators: %d: 1-based level number of a parent picker select */
        'level' => __('المستوى %d', 'hello-elementor-child'),
    ];
}

/**
 * Enqueue assets/js/submissions.js (footer) with its config in window.helloSubmissions.
 * $taxonomy is the fixed type on the front end ('' in admin, where the type select drives the picker).
 */
function submission_enqueue_script(string $taxonomy, bool $is_admin): void
{
    $path = get_stylesheet_directory() . '/assets/js/submissions.js';
    if (!file_exists($path)) {
        return;
    }
    $handle = 'hello-elementor-child-submissions';
    wp_enqueue_script(
        $handle,
        get_stylesheet_directory_uri() . '/assets/js/submissions.js',
        [],
        filemtime($path),
        true
    );
    wp_localize_script(
        $handle,
        'helloSubmissions',
        [
            'parentsUrl' => esc_url_raw(rest_url('hello/v1/submission-parents')),
            'taxonomy' => $taxonomy,
            'isAdmin' => $is_admin,
            'labels' => get_submission_js_labels(),
        ]
    );
}

// ---------------------------------------------------------------------------
// REST
// ---------------------------------------------------------------------------

function submission_register_rest_routes(): void
{
    register_rest_route(
        'hello/v1',
        '/submission-parents',
        [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => 'rest_submission_parents',
            'args' => [
                'taxonomy' => ['type' => 'string', 'required' => true],
                'parent' => ['type' => 'integer', 'default' => 0],
                'term' => ['type' => 'integer', 'default' => 0],
            ],
        ]
    );
}
add_action('rest_api_init', 'submission_register_rest_routes');

/**
 * GET /hello/v1/submission-parents?taxonomy=event&parent=0&term=0
 * One level of the parent picker: the options under `parent` (0 = roots) plus
 * the depth figures the JS needs to know whether this level is the last one.
 * `path` (root => term) lets an edit screen reopen a saved choice.
 */
function rest_submission_parents(WP_REST_Request $request): WP_REST_Response|WP_Error
{
    $taxonomy = sanitize_key((string) $request->get_param('taxonomy'));
    $parent = max(0, (int) $request->get_param('parent'));
    $term = max(0, (int) $request->get_param('term'));

    if (!is_submission_taxonomy($taxonomy)) {
        return new WP_Error('invalid_tax', 'Invalid taxonomy', ['status' => 400]);
    }

    $depth = 0;
    if ($parent > 0) {
        $parent_term_depth = get_submission_term_depth($taxonomy, $parent);
        if ($parent_term_depth < 0) {
            return new WP_Error('invalid_parent', 'Invalid parent', ['status' => 400]);
        }
        $depth = $parent_term_depth + 1;
    }
    $parent_depth = get_submission_parent_depth($taxonomy);

    $response = rest_ensure_response(
        [
            'taxonomy' => $taxonomy,
            'leaf_depth' => get_submission_leaf_depth($taxonomy),
            'parent_depth' => $parent_depth,
            'depth' => $depth,
            'final' => $depth === $parent_depth,
            'items' => get_submission_parent_options($taxonomy, $parent),
            'path' => $term > 0 ? get_submission_term_path($taxonomy, $term) : [],
        ]
    );
    // Parent lists only change when terms are edited; let browsers keep them briefly.
    $response->header('Cache-Control', 'public, max-age=300');
    return $response;
}
