<?php
/**
 * Hierarchical entity taxonomies: government_entity, private_entity, event.
 * All terms are always listed (including count 0), home and departments.
 */
function get_entity_taxonomies(): array
{
    return ['government_entity', 'private_entity', 'event'];
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
 * @return array<int, array{id:int,name:string,slug:string,parent:int,count:int}>
 */
function get_cached_taxonomy_term_map(string $taxonomy): array
{
    $cache_key = 'entity_term_map_' . $taxonomy;
    $cached = get_transient($cache_key);
    if (false !== $cached && is_array($cached)) {
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
        $map[(int) $term->term_id] = [
            'id' => (int) $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'parent' => (int) $term->parent,
            'count' => (int) $term->count,
        ];
    }
    set_transient($cache_key, $map, DAY_IN_SECONDS);
    return $map;
}

function clear_taxonomy_term_map_cache($term_id = 0, $tt_id = null, $taxonomy = ''): void
{
    if (is_string($taxonomy) && $taxonomy !== '') {
        delete_transient('entity_term_map_' . $taxonomy);
    }
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
 * Counts change when posts are tagged — bust the browse map.
 */
add_action(
    'set_object_terms',
    static function ($object_id, $terms, $tt_ids, $taxonomy): void {
        if (!is_string($taxonomy) || $taxonomy === '') {
            return;
        }
        if (!in_array($taxonomy, get_entity_taxonomies(), true)) {
            return;
        }
        delete_transient('entity_term_map_' . $taxonomy);
    },
    10,
    4
);

add_action(
    'deleted_term_relationships',
    static function ($object_id, $tt_ids, $taxonomy): void {
        if (!is_string($taxonomy) || $taxonomy === '') {
            return;
        }
        if (!in_array($taxonomy, get_entity_taxonomies(), true)) {
            return;
        }
        delete_transient('entity_term_map_' . $taxonomy);
    },
    10,
    3
);

function taxonomy_term_has_children(int $term_id, array $map): bool
{
    foreach ($map as $row) {
        if ((int) $row['parent'] === $term_id) {
            return true;
        }
    }
    return false;
}

function rollup_term_counts(array $leaf_counts, string $taxonomy): array
{
    $map = get_cached_taxonomy_term_map($taxonomy);
    $rolled = $leaf_counts;
    foreach ($leaf_counts as $term_id => $count) {
        $current = (int) $term_id;
        $guard = 0;
        while ($guard++ < 20 && isset($map[$current])) {
            $parent = (int) $map[$current]['parent'];
            if ($parent <= 0) {
                break;
            }
            $rolled[$parent] = ($rolled[$parent] ?? 0) + (int) $count;
            $current = $parent;
        }
    }
    return $rolled;
}

/**
 * Counts for every term (0 allowed). Department ignored for visibility.
 *
 * @return array<int, int>
 */
function get_entity_leaf_counts(string $taxonomy, ?WP_Term $department = null): array
{
    $map = get_cached_taxonomy_term_map($taxonomy);
    if (empty($map)) {
        return [];
    }

    $leaf_counts = [];
    foreach ($map as $id => $row) {
        $leaf_counts[$id] = (int) $row['count'];
    }
    return $leaf_counts;
}

/**
 * Category nodes (have children) at a given parent.
 *
 * @return array<int, array{id:int,name:string,slug:string,count:int}>
 */
function get_entity_category_nav(string $taxonomy, int $parent_id = 0, ?WP_Term $department = null): array
{
    $map = get_cached_taxonomy_term_map($taxonomy);
    if (empty($map)) {
        return [];
    }

    $leaf_counts = get_entity_leaf_counts($taxonomy, $department);
    $rolled = rollup_term_counts($leaf_counts, $taxonomy);
    $items = [];

    foreach ($map as $id => $row) {
        if ((int) $row['parent'] !== $parent_id) {
            continue;
        }
        if (!taxonomy_term_has_children($id, $map)) {
            continue;
        }
        $items[] = [
            'id' => $id,
            'name' => $row['name'],
            'slug' => $row['slug'],
            'count' => (int) ($rolled[$id] ?? 0),
        ];
    }

    usort($items, static fn($a, $b) => $b['count'] <=> $a['count']);
    return $items;
}

/**
 * Entity leaves under a parent. Includes count 0.
 *
 * @return array<int, array{id:int,name:string,slug:string,count:int,link:string}>
 */
function get_entity_leaves_under(string $taxonomy, int $parent_id, string $search = '', ?WP_Term $department = null, int $limit = 50): array
{
    $map = get_cached_taxonomy_term_map($taxonomy);
    if (empty($map) || $parent_id < 1) {
        return [];
    }

    $leaf_counts = get_entity_leaf_counts($taxonomy, $department);
    $q = mb_strtolower(trim($search));
    $items = [];

    foreach ($map as $id => $row) {
        if ((int) $row['parent'] !== $parent_id) {
            continue;
        }
        if (taxonomy_term_has_children($id, $map)) {
            continue;
        }

        $count = (int) ($leaf_counts[$id] ?? $row['count']);

        if ($q !== '' && mb_strpos(mb_strtolower($row['name']), $q) === false) {
            continue;
        }

        $link = get_term_link($id, $taxonomy);
        if (is_wp_error($link)) {
            continue;
        }

        $items[] = [
            'id' => $id,
            'name' => $row['name'],
            'slug' => $row['slug'],
            'count' => $count,
            'link' => $link,
        ];
        if (count($items) >= $limit) {
            break;
        }
    }

    usort($items, static fn($a, $b) => $b['count'] <=> $a['count']);
    return $items;
}

/**
 * Global entity search (leaves only). Includes count 0.
 *
 * @return array<int, array{id:int,name:string,slug:string,count:int,link:string}>
 */
function search_entity_leaves(string $taxonomy, string $search, ?WP_Term $department = null, int $limit = 20): array
{
    $map = get_cached_taxonomy_term_map($taxonomy);
    $q = mb_strtolower(trim($search));
    if ($q === '' || mb_strlen($q) < 2 || empty($map)) {
        return [];
    }

    $leaf_counts = get_entity_leaf_counts($taxonomy, $department);
    $items = [];

    foreach ($map as $id => $row) {
        if (taxonomy_term_has_children($id, $map)) {
            continue;
        }
        if (mb_strpos(mb_strtolower($row['name']), $q) === false) {
            continue;
        }

        $count = (int) ($leaf_counts[$id] ?? $row['count']);
        $link = get_term_link($id, $taxonomy);
        if (is_wp_error($link)) {
            continue;
        }

        $items[] = [
            'id' => $id,
            'name' => $row['name'],
            'slug' => $row['slug'],
            'count' => $count,
            'link' => $link,
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
            ]
        );
        register_rest_route(
            'hello/v1',
            '/entity-search',
            [
                'methods' => 'GET',
                'permission_callback' => '__return_true',
                'callback' => 'rest_entity_search',
            ]
        );
    }
);

function rest_entity_nav(WP_REST_Request $request)
{
    $taxonomy = sanitize_key((string) $request->get_param('taxonomy'));
    $parent = (int) $request->get_param('parent');
    $dept_id = (int) $request->get_param('department');
    $mode = sanitize_key((string) $request->get_param('mode'));

    if (!in_array($taxonomy, get_entity_taxonomies(), true)) {
        return new WP_Error('invalid_tax', 'Invalid taxonomy', ['status' => 400]);
    }

    $department = $dept_id ? get_term($dept_id, 'department') : null;
    if ($department && is_wp_error($department)) {
        $department = null;
    }

    if ($mode === 'entities') {
        $q = sanitize_text_field((string) $request->get_param('q'));
        return rest_ensure_response(
            get_entity_leaves_under($taxonomy, $parent, $q, $department instanceof WP_Term ? $department : null)
        );
    }

    return rest_ensure_response(
        get_entity_category_nav($taxonomy, $parent, $department instanceof WP_Term ? $department : null)
    );
}

function rest_entity_search(WP_REST_Request $request)
{
    $taxonomy = sanitize_key((string) $request->get_param('taxonomy'));
    $q = sanitize_text_field((string) $request->get_param('q'));
    $dept_id = (int) $request->get_param('department');

    if (!in_array($taxonomy, get_entity_taxonomies(), true)) {
        return new WP_Error('invalid_tax', 'Invalid taxonomy', ['status' => 400]);
    }

    $department = $dept_id ? get_term($dept_id, 'department') : null;
    if ($department && is_wp_error($department)) {
        $department = null;
    }

    return rest_ensure_response(
        search_entity_leaves($taxonomy, $q, $department instanceof WP_Term ? $department : null)
    );
}

/**
 * Browse tree: Main → Sub → Entity leaves (all terms, including empty).
 *
 * @return array<int, array{id:int,name:string,slug:string,count:int,link:string,children:array}>
 */
function get_entity_browse_tree(string $taxonomy, ?WP_Term $department = null): array
{
    $map = get_cached_taxonomy_term_map($taxonomy);
    if (empty($map)) {
        return [];
    }

    $leaf_counts = get_entity_leaf_counts($taxonomy, $department);
    $rolled = rollup_term_counts($leaf_counts, $taxonomy);

    $nodes = [];
    foreach ($map as $id => $row) {
        $is_leaf = !taxonomy_term_has_children($id, $map);
        $count = $is_leaf
            ? (int) ($leaf_counts[$id] ?? 0)
            : (int) ($rolled[$id] ?? 0);

        $link = get_term_link($id, $taxonomy);
        if (is_wp_error($link)) {
            $link = '';
        }

        $nodes[$id] = [
            'id' => $id,
            'name' => $row['name'],
            'slug' => $row['slug'],
            'parent' => (int) $row['parent'],
            'count' => $count,
            'link' => $link,
            'is_leaf' => $is_leaf,
            'children' => [],
        ];
    }

    foreach ($nodes as $id => &$node) {
        $pid = $node['parent'];
        if ($pid > 0 && isset($nodes[$pid])) {
            $nodes[$pid]['children'][$id] = &$node;
        }
    }
    unset($node);

    $tree = [];
    foreach ($nodes as $id => $node) {
        if ((int) $node['parent'] === 0 && !$node['is_leaf']) {
            $tree[$id] = $node;
        }
    }

    $prune = static function (array &$list) use (&$prune): void {
        foreach ($list as $id => &$n) {
            if (!empty($n['children'])) {
                $prune($n['children']);
            }
            if (!$n['is_leaf'] && empty($n['children'])) {
                unset($list[$id]);
            }
        }
        unset($n);
    };
    $prune($tree);

    return $tree;
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
    ];
}

/**
 * Whether a layout should be skipped on a term archive.
 */
function should_skip_term_archive_layout(string $layout): bool
{
    return $layout === '' || in_array($layout, get_term_archive_skip_layouts(), true);
}