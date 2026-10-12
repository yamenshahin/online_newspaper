<?php
/**
 * Cars directory — Phase 1 (tax filters + search).
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Taxonomies used as car directory filters. */
function get_car_filter_taxonomies(): array
{
    return [
        'car_maker' => __('الشركة', 'hello-elementor-child'),
        'car_model' => __('الموديل', 'hello-elementor-child'),
        'car_year' => __('السنة', 'hello-elementor-child'),
        'car_origin' => __('المنشأ', 'hello-elementor-child'),
        'car_body_type' => __('الهيكل', 'hello-elementor-child'),
        'car_fuel_type' => __('الوقود', 'hello-elementor-child'),
    ];
}

/** Department term that brands all car screens (logo / accent).*/
function get_cars_department(): ?WP_Term
{
    $term = get_term_by('slug', 'cars', 'department');
    return ($term instanceof WP_Term) ? $term : null;
}

/**
 * Active car filters from the request.
 *
 * @return array{search: string, taxes: array<string, string>, paged: int}
 */
function get_car_filter_state(): array
{
    $taxes = [];
    $allowed = array_keys(get_car_filter_taxonomies());

    foreach ($allowed as $tax) {
        if (empty($_GET[$tax])) {
            continue;
        }
        $slug = sanitize_title(wp_unslash($_GET[$tax]));
        if ($slug !== '' && term_exists($slug, $tax)) {
            $taxes[$tax] = $slug;
        }
    }

    $search = isset($_GET['car_s']) ? sanitize_text_field(wp_unslash($_GET['car_s'])) : '';
    $paged = max(1, (int) (get_query_var('paged') ?: ($_GET['paged'] ?? 1)));

    return [
        'search' => $search,
        'taxes' => $taxes,
        'paged' => $paged,
    ];
}

/**
 * WP_Query for the car directory (Phase 1: no department tax, no meta specs).
 *
 * @param array $args {
 *   @type int $posts_per_page
 *   @type int $paged
 * }
 */
function query_cars_directory(array $args = []): WP_Query
{
    $state = get_car_filter_state();
    $ppp = max(1, (int) ($args['posts_per_page'] ?? 12));
    $paged = (int) ($args['paged'] ?? $state['paged']);

    $query_args = [
        'post_type' => 'car',
        'post_status' => 'publish',
        'posts_per_page' => $ppp,
        'paged' => $paged,
        'ignore_sticky_posts' => true,
    ];

    if ($state['search'] !== '') {
        $query_args['s'] = $state['search'];
    }

    if (!empty($state['taxes'])) {
        $tax_query = ['relation' => 'AND'];
        foreach ($state['taxes'] as $tax => $slug) {
            $tax_query[] = [
                'taxonomy' => $tax,
                'field' => 'slug',
                'terms' => $slug,
            ];
        }
        $query_args['tax_query'] = $tax_query;
    }

    return new WP_Query($query_args);
}

/** Base URL for filter links (current URL without car filter params). */
function get_cars_filter_base_url(): string
{
    if (is_post_type_archive('car')) {
        return get_post_type_archive_link('car') ?: home_url('/car/');
    }
    // Section embedded on a page/department: keep current URL path.
    return (is_ssl() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
}

/**
 * Build filter URL: set/unset one tax or search.
 *
 * @param array $overrides Keys to set; use null to remove a key.
 */
function get_cars_filter_url(array $overrides = []): string
{
    $state = get_car_filter_state();
    $args = $state['taxes'];

    if ($state['search'] !== '') {
        $args['car_s'] = $state['search'];
    }

    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($args[$key]);
        } else {
            $args[$key] = $value;
        }
    }

    // Reset page when filters change.
    unset($args['paged']);

    $base = get_cars_filter_base_url();
    return $args ? add_query_arg($args, $base) : $base;
}

/**
 * Terms for a facet (hide empty). Cap list length for huge taxonomies.
 *
 * @return WP_Term[]
 */
function get_car_facet_terms(string $taxonomy, int $limit = 0): array
{
    $terms = get_terms(
        [
            'taxonomy' => $taxonomy,
            'hide_empty' => true,
            'number' => $limit > 0 ? $limit : 0,
            'orderby' => 'name',
            'order' => 'ASC',
        ]
    );

    return (empty($terms) || is_wp_error($terms)) ? [] : $terms;
}