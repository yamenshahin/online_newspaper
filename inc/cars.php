<?php
/**
 * Cars directory — Phase 1 (tax + search) + Phase 2 (spec meta filters).
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

/** Numeric range facets: GET prefix => ACF meta key. */
function get_car_range_filter_map(): array
{
    return [
        'price' => 'price_in_riyals',
        'power' => 'power',
        'torque' => 'torque',
        'engine' => 'engine_capacity',
        'seats' => 'number_of_seats',
    ];
}

/** Exact text meta facets: GET param => ACF meta key. */
function get_car_text_filter_map(): array
{
    return [
        'transmission' => 'transmission',
        'drive' => 'drive_system',
    ];
}

/** Department term that brands all car screens (logo / accent). */
function get_cars_department(): ?WP_Term
{
    $term = get_term_by('slug', 'cars', 'department');
    return ($term instanceof WP_Term) ? $term : null;
}

/**
 * Active car filters from the request.
 *
 * @return array{
 *   search: string,
 *   taxes: array<string, string>,
 *   ranges: array<string, array{min:?float, max:?float, meta:string}>,
 *   text: array<string, string>,
 *   paged: int
 * }
 */
function get_car_filter_state(): array
{
    $taxes = [];
    $ranges = [];
    $text = [];
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

    foreach (get_car_range_filter_map() as $prefix => $meta_key) {
        $min_raw = isset($_GET[$prefix . '_min']) ? wp_unslash($_GET[$prefix . '_min']) : '';
        $max_raw = isset($_GET[$prefix . '_max']) ? wp_unslash($_GET[$prefix . '_max']) : '';
        $min = ($min_raw !== '' && is_numeric($min_raw)) ? (float) $min_raw : null;
        $max = ($max_raw !== '' && is_numeric($max_raw)) ? (float) $max_raw : null;

        if ($min === null && $max === null) {
            continue;
        }
        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        $ranges[$prefix] = [
            'min' => $min,
            'max' => $max,
            'meta' => $meta_key,
        ];
    }

    foreach (get_car_text_filter_map() as $param => $meta_key) {
        if (empty($_GET[$param])) {
            continue;
        }
        $val = sanitize_text_field(wp_unslash($_GET[$param]));
        if ($val !== '') {
            $text[$param] = $val;
        }
    }

    $search = isset($_GET['car_s']) ? sanitize_text_field(wp_unslash($_GET['car_s'])) : '';
    $paged = max(1, (int) (get_query_var('paged') ?: ($_GET['paged'] ?? 1)));

    return [
        'search' => $search,
        'taxes' => $taxes,
        'ranges' => $ranges,
        'text' => $text,
        'paged' => $paged,
    ];
}

/**
 * WP_Query for the car directory.
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

    $meta_query = ['relation' => 'AND'];

    foreach ($state['ranges'] as $range) {
        $meta_key = $range['meta'];
        $min = $range['min'];
        $max = $range['max'];

        // Posts with empty/missing meta simply won't match (expected with incomplete content).
        if ($min !== null && $max !== null) {
            $meta_query[] = [
                'key' => $meta_key,
                'value' => [$min, $max],
                'type' => 'NUMERIC',
                'compare' => 'BETWEEN',
            ];
        } elseif ($min !== null) {
            $meta_query[] = [
                'key' => $meta_key,
                'value' => $min,
                'type' => 'NUMERIC',
                'compare' => '>=',
            ];
        } elseif ($max !== null) {
            $meta_query[] = [
                'key' => $meta_key,
                'value' => $max,
                'type' => 'NUMERIC',
                'compare' => '<=',
            ];
        }
    }

    $text_map = get_car_text_filter_map();
    foreach ($state['text'] as $param => $value) {
        if (empty($text_map[$param])) {
            continue;
        }
        $meta_query[] = [
            'key' => $text_map[$param],
            'value' => $value,
            'compare' => '=',
        ];
    }

    if (count($meta_query) > 1) {
        $query_args['meta_query'] = $meta_query;
    }

    return new WP_Query($query_args);
}

/** Base URL for filter links. */
function get_cars_filter_base_url(): string
{
    if (is_post_type_archive('car')) {
        return get_post_type_archive_link('car') ?: home_url('/car/');
    }
    return (is_ssl() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
}

/**
 * Build filter URL: set/unset params. Use null to remove a key.
 */
function get_cars_filter_url(array $overrides = []): string
{
    $state = get_car_filter_state();
    $taxes = $state['taxes'] ?? [];
    $ranges = $state['ranges'] ?? [];
    $text = $state['text'] ?? [];
    $search = $state['search'] ?? '';

    $args = $taxes;

    if ($search !== '') {
        $args['car_s'] = $search;
    }

    foreach ($ranges as $prefix => $range) {
        if ($range['min'] !== null) {
            $args[$prefix . '_min'] = $range['min'];
        }
        if ($range['max'] !== null) {
            $args[$prefix . '_max'] = $range['max'];
        }
    }

    foreach ($text as $param => $value) {
        $args[$param] = $value;
    }

    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($args[$key]);
        } else {
            $args[$key] = $value;
        }
    }

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

/**
 * Distinct text meta values for transmission / drive pills (cached 1 hour).
 *
 * @return string[]
 */
function get_car_distinct_meta_values(string $meta_key): array
{
    $cache_key = 'car_meta_vals_' . $meta_key;
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    global $wpdb;
    $sql = $wpdb->prepare(
        "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} pm
		INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		WHERE pm.meta_key = %s
		  AND pm.meta_value <> ''
		  AND p.post_type = 'car'
		  AND p.post_status = 'publish'
		ORDER BY meta_value ASC
		LIMIT 100",
        $meta_key
    );

    $values = $wpdb->get_col($sql);
    $values = array_values(array_filter(array_map('strval', $values ?: [])));
    set_transient($cache_key, $values, HOUR_IN_SECONDS);

    return $values;
}

add_action(
    'save_post_car',
    static function () {
        foreach (array_values(get_car_text_filter_map()) as $meta_key) {
            delete_transient('car_meta_vals_' . $meta_key);
        }
    }
);

/**
 * Print hidden inputs to preserve car filter state in GET forms.
 *
 * @param string[] $skip Param names to omit.
 */
function cars_render_hidden_filter_fields(array $skip = []): void
{
    $state = get_car_filter_state();
    $taxes = $state['taxes'] ?? [];
    $ranges = $state['ranges'] ?? [];
    $text = $state['text'] ?? [];
    $search = $state['search'] ?? '';

    foreach ($taxes as $tax => $slug) {
        if (in_array($tax, $skip, true)) {
            continue;
        }
        printf(
            '<input type="hidden" name="%s" value="%s" />' . "\n",
            esc_attr($tax),
            esc_attr($slug)
        );
    }

    if ($search !== '' && !in_array('car_s', $skip, true)) {
        printf(
            '<input type="hidden" name="car_s" value="%s" />' . "\n",
            esc_attr($search)
        );
    }

    foreach ($text as $param => $value) {
        if (in_array($param, $skip, true)) {
            continue;
        }
        printf(
            '<input type="hidden" name="%s" value="%s" />' . "\n",
            esc_attr($param),
            esc_attr($value)
        );
    }

    foreach ($ranges as $prefix => $range) {
        $min_key = $prefix . '_min';
        $max_key = $prefix . '_max';
        if (isset($range['min']) && $range['min'] !== null && !in_array($min_key, $skip, true)) {
            printf(
                '<input type="hidden" name="%s" value="%s" />' . "\n",
                esc_attr($min_key),
                esc_attr((string) $range['min'])
            );
        }
        if (isset($range['max']) && $range['max'] !== null && !in_array($max_key, $skip, true)) {
            printf(
                '<input type="hidden" name="%s" value="%s" />' . "\n",
                esc_attr($max_key),
                esc_attr((string) $range['max'])
            );
        }
    }
}