<?php
/**
 * Hierarchical entity taxonomies: government_entity, private_entity.
 */

function get_entity_taxonomies(): array
{
    return ['government_entity', 'private_entity'];
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

    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
    ]);
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
add_action('delete_term', function ($term_id, $tt_id, $taxonomy) {
    clear_taxonomy_term_map_cache($term_id, $tt_id, $taxonomy);
}, 10, 3);

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
 * Leaf counts for department intersection, or native counts on home.
 *
 * @return array<int, int>
 */
function get_entity_leaf_counts(string $taxonomy, ?WP_Term $department = null): array
{
    $map = get_cached_taxonomy_term_map($taxonomy);
    if (empty($map)) {
        return [];
    }

    if ($department instanceof WP_Term) {
        $leaf_counts = [];
        foreach (get_department_intersected_terms((int) $department->term_id, $taxonomy) as $row) {
            $leaf_counts[(int) $row['term']->term_id] = (int) $row['count'];
        }
        return $leaf_counts;
    }

    $leaf_counts = [];
    foreach ($map as $id => $row) {
        if ($row['count'] > 0) {
            $leaf_counts[$id] = $row['count'];
        }
    }
    return $leaf_counts;
}

/**
 * Category nodes (have children) at a given parent, with rolled counts.
 * parent_id = 0 → main categories.
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
    if (empty($leaf_counts)) {
        return [];
    }
    $rolled = rollup_term_counts($leaf_counts, $taxonomy);
    $items = [];

    foreach ($map as $id => $row) {
        if ((int) $row['parent'] !== $parent_id) {
            continue;
        }
        if (!taxonomy_term_has_children($id, $map)) {
            continue;
        }
        $count = (int) ($rolled[$id] ?? 0);
        if ($count < 1) {
            continue;
        }
        $items[] = [
            'id' => $id,
            'name' => $row['name'],
            'slug' => $row['slug'],
            'count' => $count,
        ];
    }

    usort($items, static fn($a, $b) => $b['count'] <=> $a['count']);
    return $items;
}

/**
 * Entity leaves under a parent (subcategory), optional search.
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
        // leaf only
        if (taxonomy_term_has_children($id, $map)) {
            continue;
        }
        $count = (int) ($leaf_counts[$id] ?? $row['count']);
        if ($department instanceof WP_Term && $count < 1) {
            continue;
        }
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
 * Global entity search (leaves only).
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
            continue; // entities only
        }
        if (mb_strpos(mb_strtolower($row['name']), $q) === false) {
            continue;
        }
        $count = (int) ($leaf_counts[$id] ?? $row['count']);
        if ($department instanceof WP_Term && $count < 1) {
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
add_action('rest_api_init', function () {
    register_rest_route('hello/v1', '/entity-nav', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => 'rest_entity_nav',
    ]);
    register_rest_route('hello/v1', '/entity-search', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => 'rest_entity_search',
    ]);
});

function rest_entity_nav(WP_REST_Request $request)
{
    $taxonomy = sanitize_key((string) $request->get_param('taxonomy'));
    $parent = (int) $request->get_param('parent');
    $dept_id = (int) $request->get_param('department');
    $mode = sanitize_key((string) $request->get_param('mode')); // categories | entities

    if (!in_array($taxonomy, get_entity_taxonomies(), true)) {
        return new WP_Error('invalid_tax', 'Invalid taxonomy', ['status' => 400]);
    }

    $department = $dept_id ? get_term($dept_id, 'department') : null;
    if ($department && is_wp_error($department)) {
        $department = null;
    }

    if ($mode === 'entities') {
        $q = sanitize_text_field((string) $request->get_param('q'));
        return rest_ensure_response(get_entity_leaves_under($taxonomy, $parent, $q, $department instanceof WP_Term ? $department : null));
    }

    return rest_ensure_response(get_entity_category_nav($taxonomy, $parent, $department instanceof WP_Term ? $department : null));
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
 * Browse tree: Main → Sub → Entity leaves (with content).
 * No click-through; for rendering the full organized UI.
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

    // Build nodes
    $nodes = [];
    foreach ($map as $id => $row) {
        $is_leaf = !taxonomy_term_has_children($id, $map);
        $count = $is_leaf
            ? (int) ($leaf_counts[$id] ?? 0)
            : (int) ($rolled[$id] ?? 0);

        if ($is_leaf && $count < 1) {
            continue; // skip empty entities
        }

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

    // Attach children
    foreach ($nodes as $id => &$node) {
        $pid = $node['parent'];
        if ($pid > 0 && isset($nodes[$pid])) {
            $nodes[$pid]['children'][$id] = &$node;
        }
    }
    unset($node);

    // Roots (parent = 0)
    $tree = [];
    foreach ($nodes as $id => $node) {
        if ((int) $node['parent'] === 0 && !$node['is_leaf']) {
            $tree[$id] = $node;
        }
    }

    // Drop category nodes with no nested entities left
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