<?php
/**
 * Hierarchical entity taxonomies: government_entity, private_entity, event, international_entity.
 * All terms are always listed (including empty ones), home and departments.
 *
 * The browse section renders only the group tabs on the server. Entities
 * (leaf terms) are fetched through the REST routes below when a group is
 * opened, and search runs on the server too. No post counts are shown, so
 * the cached term map only changes when a term is created, edited or deleted.
 */
function get_entity_taxonomies(): array
{
    return ['government_entity', 'private_entity', 'event', 'international_entity'];
}

function get_term_descendant_ids(int $term_id, string $taxonomy): array
{
    $children = get_term_children($term_id, $taxonomy);
    if (is_wp_error($children) || empty($children)) {
        return [$term_id];
    }
    return array_values(array_unique(array_merge([$term_id], array_map('intval', $children))));
}

/**
 * Lower-case and fold Arabic variants so search and sorting match the way
 * normalizeArabic() in assets/js/filters.js does: strip tashkeel, unify
 * alef forms (أ إ آ → ا), ة → ه, ى → ي.
 */
function normalize_entity_text(string $text): string
{
    $text = mb_strtolower(trim($text));
    $text = (string) preg_replace('/[\x{064B}-\x{0652}]/u', '', $text);
    return str_replace(['أ', 'إ', 'آ', 'ة', 'ى'], ['ا', 'ا', 'ا', 'ه', 'ي'], $text);
}

/**
 * Flat term map for one taxonomy, sorted alphabetically, cached for a day.
 *
 * Links are stored in the map so rendering never calls get_term_link() per
 * term — that was one DB query per term (≈3,000 on the homepage).
 *
 * The key includes the site URL and permalink structure, so a migration or a
 * permalink change never serves stored links from the old setup.
 *
 * @return array<int, array{id:int,name:string,parent:int,link:string,key:string}>
 */
function get_entity_term_map_cache_key(string $taxonomy): string
{
    $url_state = substr(md5(home_url('/') . '|' . (string) get_option('permalink_structure')), 0, 8);
    return 'entity_term_map_v3_' . $taxonomy . '_' . $url_state;
}

function get_cached_taxonomy_term_map(string $taxonomy): array
{
    $cache_key = get_entity_term_map_cache_key($taxonomy);

    $memo = wp_cache_get($cache_key, 'hello_entity');
    if (is_array($memo)) {
        return $memo;
    }

    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        wp_cache_set($cache_key, $cached, 'hello_entity');
        return $cached;
    }

    $terms = get_terms(
        [
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ]
    );
    if (is_wp_error($terms) || empty($terms)) {
        return [];
    }

    $map = [];
    foreach ($terms as $term) {
        // Pass the object, not the ID, so no extra term lookup happens.
        $link = get_term_link($term, $taxonomy);
        // WordPress stores "&" as "&amp;". Keep plain text here: PHP output is
        // escaped with esc_html(), and the JS inserts names with textContent.
        $name = wp_specialchars_decode($term->name, ENT_QUOTES);
        $map[(int) $term->term_id] = [
            'id' => (int) $term->term_id,
            'name' => $name,
            'parent' => (int) $term->parent,
            'link' => is_wp_error($link) ? '' : $link,
            'key' => normalize_entity_text($name),
        ];
    }

    // Byte order of UTF-8 equals code point order, which is alphabetical for Arabic.
    uasort($map, static fn(array $a, array $b): int => strcmp($a['key'], $b['key']));

    set_transient($cache_key, $map, DAY_IN_SECONDS);
    wp_cache_set($cache_key, $map, 'hello_entity');
    return $map;
}

/**
 * parent id => child ids, in the map's (alphabetical) order.
 *
 * @return array<int, int[]>
 */
function get_taxonomy_children_index(string $taxonomy): array
{
    $index = [];
    foreach (get_cached_taxonomy_term_map($taxonomy) as $id => $row) {
        $index[$row['parent']][] = $id;
    }
    return $index;
}

function clear_taxonomy_term_map_cache($term_id = 0, $tt_id = null, $taxonomy = ''): void
{
    if (!is_string($taxonomy) || !in_array($taxonomy, get_entity_taxonomies(), true)) {
        return;
    }
    $cache_key = get_entity_term_map_cache_key($taxonomy);
    delete_transient($cache_key);
    wp_cache_delete($cache_key, 'hello_entity');

    // The homepage tabs and the cached /entity-nav and /entity-search responses
    // depend on this map. Term edits are rare, so purging everything is fine.
    // No-op when LiteSpeed Cache is inactive.
    do_action('litespeed_purge_all');
}

add_action('created_term', 'clear_taxonomy_term_map_cache', 10, 3);
add_action('edited_term', 'clear_taxonomy_term_map_cache', 10, 3);
add_action(
    'delete_term',
    static function ($term_id, $tt_id, $taxonomy) {
        clear_taxonomy_term_map_cache($term_id, $tt_id, $taxonomy);
    },
    10,
    3
);

/**
 * Entity leaves (terms without children) directly under a parent.
 * Parent 0 returns top-level leaves.
 *
 * @return array<int, array{id:int,name:string,link:string}>
 */
function get_entity_leaves_under(string $taxonomy, int $parent_id): array
{
    $map = get_cached_taxonomy_term_map($taxonomy);
    if (empty($map) || $parent_id < 0) {
        return [];
    }
    $index = get_taxonomy_children_index($taxonomy);

    $items = [];
    foreach ($index[$parent_id] ?? [] as $id) {
        if (!empty($index[$id]) || $map[$id]['link'] === '') {
            continue;
        }
        $items[] = [
            'id' => $id,
            'name' => $map[$id]['name'],
            'link' => $map[$id]['link'],
        ];
    }
    return $items;
}

/**
 * Search entity leaves of one taxonomy by (normalized) name.
 *
 * @return array<int, array{id:int,name:string,link:string,taxonomy:string}>
 */
function search_entity_leaves(string $taxonomy, string $search, int $limit = 50): array
{
    $q = normalize_entity_text($search);
    if (mb_strlen($q) < 2) {
        return [];
    }
    $map = get_cached_taxonomy_term_map($taxonomy);
    if (empty($map)) {
        return [];
    }
    $index = get_taxonomy_children_index($taxonomy);

    $items = [];
    foreach ($map as $id => $row) {
        if (!empty($index[$id]) || $row['link'] === '') {
            continue;
        }
        // Both sides are normalized UTF-8, so a byte search can only match on character boundaries.
        if (strpos($row['key'], $q) === false) {
            continue;
        }
        $items[] = [
            'id' => $id,
            'name' => $row['name'],
            'link' => $row['link'],
            'taxonomy' => $taxonomy,
        ];
        if (count($items) >= $limit) {
            break;
        }
    }
    return $items;
}

function get_hierarchical_entity_tax_clause(string $taxonomy, string $slug): ?array
{
    $term = get_term_by('slug', $slug, $taxonomy);
    if (!$term || is_wp_error($term)) {
        return null;
    }
    $hierarchical = in_array($taxonomy, get_entity_taxonomies(), true);
    return [
        'taxonomy' => $taxonomy,
        'field' => 'term_id',
        'terms' => [(int) $term->term_id],
        'include_children' => $hierarchical,
    ];
}

// ---------------------------------------------------------------------------
// REST
// ---------------------------------------------------------------------------
add_action(
    'rest_api_init',
    static function () {
        register_rest_route(
            'hello/v1',
            '/entity-nav',
            [
                'methods' => 'GET',
                'permission_callback' => '__return_true',
                'callback' => 'rest_entity_nav',
                'args' => [
                    'taxonomy' => ['type' => 'string', 'required' => true],
                    'parent' => ['type' => 'integer', 'default' => 0],
                ],
            ]
        );
        register_rest_route(
            'hello/v1',
            '/entity-search',
            [
                'methods' => 'GET',
                'permission_callback' => '__return_true',
                'callback' => 'rest_entity_search',
                'args' => [
                    'q' => ['type' => 'string', 'required' => true],
                    'taxonomy' => ['type' => 'string', 'default' => 'all'],
                ],
            ]
        );
    }
);

/**
 * GET /hello/v1/entity-nav?taxonomy=private_entity&parent=123
 * Leaves directly under a group. Used when a tab is opened.
 */
function rest_entity_nav(WP_REST_Request $request)
{
    $taxonomy = sanitize_key((string) $request->get_param('taxonomy'));
    $parent = (int) $request->get_param('parent');

    if (!in_array($taxonomy, get_entity_taxonomies(), true)) {
        return new WP_Error('invalid_tax', 'Invalid taxonomy', ['status' => 400]);
    }

    $response = rest_ensure_response(get_entity_leaves_under($taxonomy, $parent));
    // Entity lists only change when terms are edited; let browsers keep them briefly.
    $response->header('Cache-Control', 'public, max-age=300');
    return $response;
}

/**
 * GET /hello/v1/entity-search?q=...&taxonomy=all|government_entity|private_entity|event|international_entity
 * Returns {items, truncated}: matches across the requested taxonomies, each
 * capped at ENTITY_SEARCH_LIMIT. truncated is true when any taxonomy had more,
 * so the UI can ask for a more specific query.
 */
const ENTITY_SEARCH_LIMIT = 100;

function rest_entity_search(WP_REST_Request $request)
{
    $taxonomy = sanitize_key((string) $request->get_param('taxonomy'));
    $q = sanitize_text_field((string) $request->get_param('q'));

    if ($taxonomy === '' || $taxonomy === 'all') {
        $taxonomies = get_entity_taxonomies();
    } elseif (in_array($taxonomy, get_entity_taxonomies(), true)) {
        $taxonomies = [$taxonomy];
    } else {
        return new WP_Error('invalid_tax', 'Invalid taxonomy', ['status' => 400]);
    }

    $items = [];
    $truncated = false;
    foreach ($taxonomies as $tax) {
        // Ask for one extra to know whether the list was cut.
        $found = search_entity_leaves($tax, $q, ENTITY_SEARCH_LIMIT + 1);
        if (count($found) > ENTITY_SEARCH_LIMIT) {
            $truncated = true;
            $found = array_slice($found, 0, ENTITY_SEARCH_LIMIT);
        }
        $items = array_merge($items, $found);
    }
    return rest_ensure_response(['items' => $items, 'truncated' => $truncated]);
}

/**
 * Group tree: terms that have children, nested. Leaves are not included;
 * fetch them with get_entity_leaves_under() when a group is opened.
 *
 * @return array<int, array{id:int,name:string,has_leaves:bool,children:array}>
 */
function get_entity_browse_tree(string $taxonomy): array
{
    $map = get_cached_taxonomy_term_map($taxonomy);
    if (empty($map)) {
        return [];
    }
    $index = get_taxonomy_children_index($taxonomy);

    $build = static function (int $parent) use (&$build, $map, $index): array {
        $groups = [];
        foreach ($index[$parent] ?? [] as $id) {
            if (empty($index[$id])) {
                continue; // leaf
            }
            $has_leaves = false;
            foreach ($index[$id] as $child) {
                if (empty($index[$child])) {
                    $has_leaves = true;
                    break;
                }
            }
            $groups[$id] = [
                'id' => $id,
                'name' => $map[$id]['name'],
                'has_leaves' => $has_leaves,
                'children' => $build($id),
            ];
        }
        return $groups;
    };

    return $build(0);
}

/**
 * True the first time it is called in a request, false afterwards.
 * (A `static` at file scope in a template part resets on every include.)
 */
function entity_browse_claim_render(): bool
{
    static $claimed = false;
    if ($claimed) {
        return false;
    }
    $claimed = true;
    return true;
}

/**
 * Whether a taxonomy has leaves at the top level (terms with no parent and no children).
 */
function entity_taxonomy_has_root_leaves(string $taxonomy): bool
{
    return !empty(get_entity_leaves_under($taxonomy, 0));
}

/**
 * Flexible layouts to skip on single-term filter archives
 * (speaker, entity, event, geographic, … — not department, not home).
 */
function get_term_archive_skip_layouts(): array
{
    return [
        'hero_lead_section',
        'speakers_section',
        'car_home_section',
    ];
}

/**
 * Whether a layout should be skipped on a term archive.
 */
function should_skip_term_archive_layout(string $layout): bool
{
    return $layout === '' || in_array($layout, get_term_archive_skip_layouts(), true);
}
