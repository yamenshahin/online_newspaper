<?php
/**
 * Car directory: nested source-style URLs under /car-directory/, price-range pages, body-type order.
 *
 *   /car-directory/                                              car archive (the filter)
 *   /car-directory/japanese-cars/                                car_origin
 *   /car-directory/japanese-cars/toyota/                         car_make (make)
 *   /car-directory/japanese-cars/toyota/camry/                   car_make (model)
 *   /car-directory/japanese-cars/toyota/camry/2026-camry/        car_model_year
 *   /car-directory/japanese-cars/toyota/camry/2026-camry/<trim>/ car (trim)
 *   /car-directory/car-types/sedan/                              body_type (rewrite slug, ACF JSON)
 *   /car-directory/prices/less-than-50k/                         price range (virtual, query var car_price)
 *
 * Every car / model-year post and every car_origin / car_make term stores its path (without the base)
 * in the `_car_path` meta; `wp cars paths` rebuilds them after an import. The post types,
 * taxonomies and field groups themselves are ACF JSON (acf-json/post_type_*, taxonomy_*, group_car_*).
 * The data is produced on the staging site by its own plugin and moved here in packs
 * (inc/cars/cli.php) - the plugin itself never runs on live.
 */

if (!defined('ABSPATH')) {
    exit;
}

const CAR_DIRECTORY_BASE = 'car-directory';
const CAR_PATH_META = '_car_path';

/** The directory's taxonomies: slug => [label, hierarchical, also on car_model_year]. */
function car_directory_taxonomies(): array
{
    return [
        'car_make' => ['الماركات والموديلات', true, true],
        'car_origin' => ['المنشأ', false, true],
        'car_year' => ['سنوات الموديل', false, true],
        'body_type' => ['أنواع الهيكل', false, true],
        'fuel_type' => ['أنواع الوقود', false, false],
        'transmission' => ['أنواع ناقل الحركة', false, false],
        'drivetrain' => ['أنظمة الدفع', false, false],
        'car_feature' => ['التجهيزات', false, false],
    ];
}

/** Absolute URL of a directory path (origin/make/..., prices/<slug>, car-types/<slug>). */
function car_directory_url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return home_url(user_trailingslashit(CAR_DIRECTORY_BASE . ($path !== '' ? '/' . $path : '')));
}

/** Price-range pages: slug => [label, min, max] (SAR, max exclusive; 0 = open). The source's own slugs. */
function car_price_ranges(): array
{
    $ranges = ['less-than-50k' => ['أقل من 50 ألف', 0, 50000]];
    for ($k = 50; $k < 400; $k += 50) {
        $ranges["from-{$k}k-to-" . ($k + 50) . 'k'] = ["{$k} ألف إلى " . ($k + 50) . ' ألف', $k * 1000, ($k + 50) * 1000];
    }
    $ranges['more-than-400k'] = ['أكثر من 400 ألف', 400000, 0];
    return apply_filters('car_price_ranges', $ranges);
}

function car_price_range_url(string $slug): string
{
    return car_directory_url("prices/{$slug}");
}

// ---------------------------------------------------------------------------
// Resolve
// ---------------------------------------------------------------------------

add_filter('query_vars', function (array $vars): array {
    $vars[] = 'car_price';
    return $vars;
});

add_filter('request', 'route_car_directory_request', 1);

function route_car_directory_request(array $qv): array
{
    if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return $qv;
    }
    $path = car_request_path();
    if ($path === '') {
        return $qv;
    }
    $paged = 0;
    if (preg_match('#^(.+)/page/(\d+)$#', $path, $m)) {
        $path = $m[1];
        $paged = (int) $m[2];
    }
    if (preg_match('#^prices/([a-z0-9-]+)$#', $path, $m) && isset(car_price_ranges()[$m[1]])) {
        return ['car_price' => $m[1], 'post_type' => 'car', 'paged' => max(1, $paged)];
    }
    $hit = lookup_car_path($path);
    if (!$hit) {
        return $qv;
    }
    if ($hit['kind'] === 'post') {
        return ['post_type' => $hit['type'], 'p' => $hit['id'], $hit['type'] => $hit['slug'], 'name' => $hit['slug']];
    }
    return [$hit['type'] => $hit['slug']] + ($paged ? ['paged' => $paged] : []);
}

/** The request path inside the directory ('' when the request is the archive itself or outside it). */
function car_request_path(): string
{
    $uri = (string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
    $home = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
    if ($home && str_starts_with($uri, $home)) {
        $uri = substr($uri, strlen($home));
    }
    $path = strtolower(trim(rawurldecode($uri), '/'));
    if ($path === CAR_DIRECTORY_BASE) {
        return '';
    }
    return str_starts_with($path, CAR_DIRECTORY_BASE . '/') ? substr($path, strlen(CAR_DIRECTORY_BASE) + 1) : '';
}

/** @return array{kind: string, type: string, id: int, slug: string}|null */
function lookup_car_path(string $path): ?array
{
    global $wpdb;
    $post = $wpdb->get_row($wpdb->prepare(
        "SELECT p.ID, p.post_type, p.post_name FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
         WHERE m.meta_key = %s AND m.meta_value = %s AND p.post_status = 'publish' LIMIT 1",
        CAR_PATH_META,
        $path
    ));
    if ($post) {
        return ['kind' => 'post', 'type' => $post->post_type, 'id' => (int) $post->ID, 'slug' => $post->post_name];
    }
    $term = $wpdb->get_row($wpdb->prepare(
        "SELECT t.term_id, t.slug, tt.taxonomy FROM {$wpdb->termmeta} m JOIN {$wpdb->terms} t ON t.term_id = m.term_id
         JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE m.meta_key = %s AND m.meta_value = %s LIMIT 1",
        CAR_PATH_META,
        $path
    ));
    if ($term) {
        return ['kind' => 'term', 'type' => $term->taxonomy, 'id' => (int) $term->term_id, 'slug' => $term->slug];
    }
    return null;
}

// price-range pages render price-range.php when the theme has it
add_filter('template_include', function (string $template): string {
    if (get_query_var('car_price') && !is_admin()) {
        $found = locate_template('price-range.php');
        if ($found) {
            return $found;
        }
    }
    return $template;
}, 20);

// ---------------------------------------------------------------------------
// Print
// ---------------------------------------------------------------------------

add_filter('post_type_link', function (string $link, WP_Post $post): string {
    if (in_array($post->post_type, ['car', 'car_model_year'], true)) {
        $path = get_post_meta($post->ID, CAR_PATH_META, true);
        if ($path) {
            return car_directory_url($path);
        }
    }
    return $link;
}, 10, 2);

add_filter('term_link', function (string $url, WP_Term $term, string $taxonomy): string {
    if (in_array($taxonomy, ['car_origin', 'car_make'], true)) {
        $path = get_term_meta($term->term_id, CAR_PATH_META, true);
        if ($path) {
            return car_directory_url($path);
        }
    }
    return $url;
}, 10, 3);

// ---------------------------------------------------------------------------
// Build
// ---------------------------------------------------------------------------

/**
 * (Re)build the `_car_path` meta of origins, makes, models, model years, sub-families and cars.
 *
 * @return array{written: int, unchanged: int, missing: string[], duplicates: string[]}
 */
function build_car_paths(): array
{
    global $wpdb;
    $out = ['written' => 0, 'unchanged' => 0, 'missing' => [], 'duplicates' => []];
    $seen = [];
    $write = function (string $type, int $id, string $path) use (&$out, &$seen): void {
        if (isset($seen[$path])) {
            $out['duplicates'][] = "{$path} ({$seen[$path]} and {$type} {$id})";
            return;
        }
        $seen[$path] = "{$type} {$id}";
        $old = $type === 'term' ? get_term_meta($id, CAR_PATH_META, true) : get_post_meta($id, CAR_PATH_META, true);
        if ($old === $path) {
            ++$out['unchanged'];
            return;
        }
        $type === 'term' ? update_term_meta($id, CAR_PATH_META, $path) : update_post_meta($id, CAR_PATH_META, $path);
        ++$out['written'];
    };

    $origin_path = [];
    foreach (get_terms(['taxonomy' => 'car_origin', 'hide_empty' => false]) as $o) {
        $origin_path[$o->slug] = $o->slug;
        $write('term', $o->term_id, $o->slug);
    }
    $term_path = []; // car_make term id => path
    foreach (get_terms(['taxonomy' => 'car_make', 'hide_empty' => false, 'parent' => 0]) as $m) {
        $origin = get_term_meta($m->term_id, '_car_origin', true);
        if (!isset($origin_path[$origin])) {
            $out['missing'][] = "make {$m->slug}: no origin";
            continue;
        }
        $term_path[$m->term_id] = "{$origin_path[$origin]}/{$m->slug}";
        $write('term', $m->term_id, $term_path[$m->term_id]);
    }
    foreach (get_terms(['taxonomy' => 'car_make', 'hide_empty' => false, 'childless' => false]) as $t) {
        if ($t->parent && isset($term_path[$t->parent])) {
            $term_path[$t->term_id] = "{$term_path[$t->parent]}/{$t->slug}";
            $write('term', $t->term_id, $term_path[$t->term_id]);
        } elseif ($t->parent) {
            $out['missing'][] = "model {$t->slug}: make has no path";
        }
    }

    $model_of = []; // model term of every car / model-year post
    foreach ($wpdb->get_results(
        "SELECT tr.object_id, tt.term_id FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
         WHERE tt.taxonomy = 'car_make' AND tt.parent <> 0"
    ) as $r) {
        $model_of[(int) $r->object_id] = (int) $r->term_id;
    }
    $meta = function (string $key) use ($wpdb): array {
        $map = [];
        foreach ($wpdb->get_results($wpdb->prepare("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", $key)) as $r) {
            $map[(int) $r->post_id] = $r->meta_value;
        }
        return $map;
    };
    $parent_year = $meta('_car_parent_year');
    $page_type = $meta('_car_page_type');
    $car_parent = $meta('parent_model_year');

    $post_path = []; // model years first (years before sub-families), then cars
    $years = $wpdb->get_results("SELECT ID, post_name FROM {$wpdb->posts} WHERE post_type = 'car_model_year' AND post_status = 'publish'");
    usort($years, fn($a, $b) => (($page_type[$a->ID] ?? '') === 'subfamily') <=> (($page_type[$b->ID] ?? '') === 'subfamily'));
    foreach ($years as $y) {
        $id = (int) $y->ID;
        $base = ($page_type[$id] ?? '') === 'subfamily'
            ? ($post_path[(int) ($parent_year[$id] ?? 0)] ?? null)
            : ($term_path[$model_of[$id] ?? 0] ?? null);
        if (!$base) {
            $out['missing'][] = "car_model_year {$y->post_name}: no parent path";
            continue;
        }
        $post_path[$id] = "{$base}/{$y->post_name}";
        $write('post', $id, $post_path[$id]);
    }
    foreach ($wpdb->get_results("SELECT ID, post_name FROM {$wpdb->posts} WHERE post_type = 'car' AND post_status = 'publish'") as $c) {
        $id = (int) $c->ID;
        $parent = isset($car_parent[$id]) ? (int) (maybe_unserialize($car_parent[$id])[0] ?? 0) : 0;
        $base = $parent ? ($post_path[$parent] ?? null) : ($term_path[$model_of[$id] ?? 0] ?? null); // year-leaf: directly under the model
        if (!$base) {
            $out['missing'][] = "car {$c->post_name}: no parent path";
            continue;
        }
        $write('post', $id, "{$base}/{$c->post_name}");
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Body types
// ---------------------------------------------------------------------------

/** Body-type terms in the source's order (`_car_order` term meta; terms without one go last). */
function car_body_types_ordered(bool $hide_empty = true): array
{
    $terms = get_terms(['taxonomy' => 'body_type', 'hide_empty' => $hide_empty]);
    if (is_wp_error($terms)) {
        return [];
    }
    usort($terms, fn($a, $b) => ((int) get_term_meta($a->term_id, '_car_order', true) ?: 99) <=> ((int) get_term_meta($b->term_id, '_car_order', true) ?: 99));
    return $terms;
}

// ---------------------------------------------------------------------------
// Archive screens
// ---------------------------------------------------------------------------

// model years have no list page of their own: /car-directory/models/ goes to the directory
add_action('template_redirect', function (): void {
    if (is_post_type_archive('car_model_year')) {
        wp_safe_redirect(car_directory_url(), 301);
        exit;
    }
});

// `wp cars visibility --mode=images`: a term page without published cars is a 404 (its cars are drafts)
add_action('template_redirect', function (): void {
    if (!car_visibility_images() || !is_tax(array_keys(car_directory_taxonomies()))) {
        return;
    }
    $term = get_queried_object();
    if ($term instanceof WP_Term && car_term_count($term) === 0) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
    }
});

/** Document title of the directory's front door (the car archive), without WordPress' "Archive" suffix. */
function car_directory_archive_title(string $title): string
{
    if (!is_post_type_archive('car') || get_query_var('car_price')) {
        return $title;
    }
    $paged = max(1, (int) get_query_var('paged'));
    return 'دليل السيارات' . ($paged > 1 ? ' - صفحة ' . $paged : '') . ' - ' . get_bloginfo('name');
}
add_filter('pre_get_document_title', 'car_directory_archive_title', 20);
add_filter('rank_math/frontend/title', 'car_directory_archive_title', 20);

// ---------------------------------------------------------------------------
// 301s from the old /car-directory/<arabic-slug>/ URLs (map written by `wp cars apply-keep`)
// ---------------------------------------------------------------------------

add_action('template_redirect', function (): void {
    if (!is_404()) {
        return;
    }
    $path = strtolower(rawurldecode((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH))); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
    $home = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
    if ($home !== '/' && str_starts_with($path, rtrim($home, '/'))) {
        $path = substr($path, strlen(rtrim($home, '/')));
    }
    if (!str_starts_with($path, '/' . CAR_DIRECTORY_BASE . '/')) {
        return;
    }
    $map = get_option('car_redirects', []);
    $to = $map[trailingslashit($path)] ?? $map[$path] ?? '';
    if ($to !== '') {
        wp_safe_redirect(home_url($to), 301);
        exit;
    }
}, 1);
