<?php
/**
 * Related content under a single item (template-parts/related/related-content.php).
 *
 * Two rows of up to RELATED_CONTENT_LIMIT cards:
 * - main: the editor's picks (ACF `related_posts`, any type) first, then the
 *   same post type ranked by shared terms, then the newest items of the same
 *   department, so the row is always full.
 * - strip: the other content types, only when they share a specific term
 *   (series, speaker, event, entity, place). No department filler; the row
 *   is hidden when nothing qualifies.
 *
 * The ranked candidates are cached per post; the picks are read on every
 * request, so editing them shows up at once.
 */
if (!defined('ABSPATH')) {
    exit;
}

const RELATED_CONTENT_LIMIT = 4;

/**
 * Candidates cached per row: twice the row size, so a pick or an item
 * unpublished since the cache was built doesn't leave a gap.
 */
const RELATED_CONTENT_POOL = 8;

/**
 * A geographic term on this many items or more (السعودية is on most of the
 * geo-tagged content) only breaks ties: it doesn't qualify an item for the
 * strip, and it ranks no higher than a shared department.
 */
const RELATED_BROAD_PLACE_COUNT = 200;

/**
 * Post types whose single view shows related content. Podcasts list their
 * own reels instead, and reels and cars have no related section.
 *
 * @return string[]
 */
function get_related_content_source_types(): array
{
    return ['post', 'program', 'interview', 'infographic', 'video'];
}

/**
 * Post types that can appear as related items: every content type,
 * podcasts included. Reels and cars never do.
 *
 * @return string[]
 */
function get_related_content_result_types(): array
{
    return get_content_post_types();
}

/**
 * How much one shared term of each taxonomy counts. Department isn't here:
 * it adds 1 and only breaks ties (see get_related_term_weights()).
 *
 * @return array<string, int>
 */
function get_related_taxonomy_weights(): array
{
    return [
        'program_series' => 8,
        'speaker_influencer' => 5,
        'event' => 5,
        'government_entity' => 4,
        'private_entity' => 4,
        'international_entity' => 4,
        'geographic' => 2,
    ];
}

/**
 * The current post's terms as term_taxonomy_id => [weight, qualifies].
 * "qualifies" marks a specific match, the only kind that puts an item in
 * the strip or ranks it above the department fill.
 *
 * @return array<int, array{int, int}>
 */
function get_related_term_weights(int $post_id): array
{
    $weights = [];

    foreach (get_related_taxonomy_weights() as $taxonomy => $weight) {
        $terms = get_the_terms($post_id, $taxonomy);
        if (empty($terms) || is_wp_error($terms)) {
            continue;
        }
        foreach ($terms as $term) {
            $is_broad = 'geographic' === $taxonomy && $term->count >= RELATED_BROAD_PLACE_COUNT;
            $weights[(int) $term->term_taxonomy_id] = $is_broad ? [1, 0] : [$weight, 1];
        }
    }

    $departments = get_the_terms($post_id, 'department');
    if (!empty($departments) && !is_wp_error($departments)) {
        foreach ($departments as $term) {
            $weights[(int) $term->term_taxonomy_id] ??= [1, 0];
        }
    }

    return $weights;
}

/**
 * Published items of $post_types that share at least one specific term with
 * $post_id, best score first, newest first on a tie.
 *
 * The items sharing a specific term are collected first, and only their
 * rows are scored, so a large department or a broad place (tie-breakers,
 * 13k+ items) is never scanned in full.
 *
 * @param array<int, array{int, int}> $weights get_related_term_weights()
 * @param string[]                    $post_types
 * @return int[]
 */
function query_related_by_terms(int $post_id, array $weights, array $post_types, int $limit): array
{
    global $wpdb;

    $specific_ids = array_keys(array_filter($weights, static fn(array $w): bool => 1 === $w[1]));
    if (empty($specific_ids) || empty($post_types)) {
        return [];
    }

    $score_cases = '';
    $case_args = [];
    foreach ($weights as $tt_id => [$weight]) {
        $score_cases .= ' WHEN %d THEN %d';
        array_push($case_args, $tt_id, $weight);
    }

    $tt_ids = array_keys($weights);
    $specific_placeholders = implode(',', array_fill(0, count($specific_ids), '%d'));
    $tt_placeholders = implode(',', array_fill(0, count($tt_ids), '%d'));
    $type_placeholders = implode(',', array_fill(0, count($post_types), '%s'));

    $sql = "
        SELECT m.object_id
        FROM (
            SELECT DISTINCT object_id
            FROM {$wpdb->term_relationships}
            WHERE term_taxonomy_id IN ($specific_placeholders)
        ) m
        INNER JOIN {$wpdb->posts} p ON p.ID = m.object_id
        INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = m.object_id
        WHERE tr.term_taxonomy_id IN ($tt_placeholders)
        AND p.post_type IN ($type_placeholders)
        AND p.post_status = 'publish'
        AND p.ID <> %d
        GROUP BY m.object_id
        ORDER BY SUM(CASE tr.term_taxonomy_id $score_cases ELSE 0 END) DESC, MAX(p.post_date) DESC
        LIMIT %d
    ";

    $args = array_merge($specific_ids, $tt_ids, $post_types, [$post_id], $case_args, [$limit]);

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders are built above.
    return array_map('intval', $wpdb->get_col($wpdb->prepare($sql, $args)));
}

/**
 * Newest published items of $post_type in the post's departments, leaving
 * out $exclude. With $in_departments false, or when the post has no
 * department, the newest of the whole type.
 *
 * @param int[] $exclude
 * @return int[]
 */
function query_related_department_fill(int $post_id, string $post_type, array $exclude, int $limit, bool $in_departments = true): array
{
    $args = [
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'post__not_in' => array_merge([$post_id], $exclude),
        'fields' => 'ids',
        'no_found_rows' => true,
        'ignore_sticky_posts' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ];

    $departments = $in_departments ? get_the_terms($post_id, 'department') : false;
    if (!empty($departments) && !is_wp_error($departments)) {
        $args['tax_query'] = [
            [
                'taxonomy' => 'department',
                'field' => 'term_id',
                'terms' => wp_list_pluck($departments, 'term_id'),
            ],
        ];
    }

    return array_map('intval', (new WP_Query($args))->posts);
}

function get_related_content_cache_key(int $post_id): string
{
    return 'related_content_' . $post_id;
}

/**
 * Ranked candidates for both rows, before the picks are applied.
 *
 * @return array{same: int[], other: int[]}
 */
function get_related_content_candidates(int $post_id): array
{
    $cache_key = get_related_content_cache_key($post_id);
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    $post_type = (string) get_post_type($post_id);
    $weights = get_related_term_weights($post_id);

    $same = query_related_by_terms($post_id, $weights, [$post_type], RELATED_CONTENT_POOL);
    // Department first; a department with few items of this type falls back to the newest of the type.
    foreach ([true, false] as $in_departments) {
        if (count($same) < RELATED_CONTENT_POOL) {
            $same = array_merge(
                $same,
                query_related_department_fill($post_id, $post_type, $same, RELATED_CONTENT_POOL - count($same), $in_departments)
            );
        }
    }

    $other_types = array_values(array_diff(get_related_content_result_types(), [$post_type]));
    $other = query_related_by_terms($post_id, $weights, $other_types, RELATED_CONTENT_POOL);

    $candidates = ['same' => $same, 'other' => $other];
    set_transient($cache_key, $candidates, 12 * HOUR_IN_SECONDS);

    return $candidates;
}

/**
 * The editor's picks (ACF `related_posts`), in their order.
 *
 * @return int[]
 */
function get_pinned_related_ids(int $post_id): array
{
    if (!function_exists('get_field')) {
        return [];
    }

    // Unformatted: IDs, without loading each post the way the field's Post Object format would.
    $ids = array_map('intval', (array) get_field('related_posts', $post_id, false));

    return array_values(array_diff(array_unique(array_filter($ids)), [$post_id]));
}

/**
 * The two rows for a single item, as published posts ready to render.
 * Both are empty for a post type outside get_related_content_source_types().
 *
 * @return array{main: WP_Post[], strip: WP_Post[]}
 */
function get_related_content(int $post_id): array
{
    $rows = ['main' => [], 'strip' => []];

    if (!in_array(get_post_type($post_id), get_related_content_source_types(), true)) {
        return $rows;
    }

    $pinned = get_pinned_related_ids($post_id);
    $candidates = get_related_content_candidates($post_id);
    $ids = array_values(array_unique(array_merge($pinned, $candidates['same'], $candidates['other'])));
    if (empty($ids)) {
        return $rows;
    }

    // One query loads every candidate (and their thumbnails) and drops any
    // that is no longer published or isn't a related type.
    $query = new WP_Query([
        'post_type' => get_related_content_result_types(),
        'post_status' => 'publish',
        'post__in' => $ids,
        'orderby' => 'post__in',
        'posts_per_page' => count($ids),
        'no_found_rows' => true,
        'ignore_sticky_posts' => true,
    ]);

    $posts = [];
    foreach ($query->posts as $post) {
        $posts[$post->ID] = $post;
    }

    $pick = static function (array $ids, array $taken) use ($posts): array {
        $picked = [];
        foreach ($ids as $id) {
            if (isset($posts[$id]) && !isset($taken[$id]) && !isset($picked[$id])) {
                $picked[$id] = $posts[$id];
            }
        }
        return $picked;
    };

    $main = array_slice($pick(array_merge($pinned, $candidates['same']), []), 0, RELATED_CONTENT_LIMIT, true);
    $strip = array_slice($pick($candidates['other'], $main), 0, RELATED_CONTENT_LIMIT, true);

    return ['main' => array_values($main), 'strip' => array_values($strip)];
}

/**
 * Saving a post rebuilds its related content on the next view. Other posts
 * that list it catch up when their cache expires.
 */
function clear_related_content_cache(int $post_id): void
{
    if (wp_is_post_revision($post_id)) {
        return;
    }
    delete_transient(get_related_content_cache_key($post_id));
}
add_action('save_post', 'clear_related_content_cache');
