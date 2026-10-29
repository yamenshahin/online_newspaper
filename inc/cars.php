<?php
/**
 * Cars directory filter: facets, request state, query, URLs and the small pieces the filter templates share.
 *
 * Every GET parameter is prefixed (f_make, price_min, car_s, ...) so it can never collide with a WordPress
 * query var: ?car_make=toyota would turn any page into a term archive. The AJAX handler in functions.php
 * (cars_ajax_filter) copies the posted filters into $_GET and calls the same functions, so the no-JS GET
 * form, the AJAX refresh and the shareable URL all read one state.
 */
if (!defined('ABSPATH')) {
    exit;
}

const CAR_FILTER_PER_PAGE = 15; // the directory (/car-directory/); a cars_section elsewhere shows CAR_SECTION_PER_PAGE
const CAR_SECTION_PER_PAGE = 5;

/** Taxonomy facets in display order: GET param => taxonomy, label, multi (list of slugs). f_model = car_make children of f_make. */
function get_car_filter_taxonomies(): array
{
    return [
        'f_make' => ['taxonomy' => 'car_make', 'label' => __('الشركة', 'hello-elementor-child'), 'multi' => false],
        'f_model' => ['taxonomy' => 'car_make', 'label' => __('الموديل', 'hello-elementor-child'), 'multi' => false],
        'f_year' => ['taxonomy' => 'car_year', 'label' => __('السنة', 'hello-elementor-child'), 'multi' => false],
        'f_origin' => ['taxonomy' => 'car_origin', 'label' => __('المنشأ', 'hello-elementor-child'), 'multi' => false],
        'f_body' => ['taxonomy' => 'body_type', 'label' => __('الهيكل', 'hello-elementor-child'), 'multi' => false],
        'f_fuel' => ['taxonomy' => 'fuel_type', 'label' => __('الوقود', 'hello-elementor-child'), 'multi' => false],
        'f_transmission' => ['taxonomy' => 'transmission', 'label' => __('ناقل الحركة', 'hello-elementor-child'), 'multi' => false],
        'f_drivetrain' => ['taxonomy' => 'drivetrain', 'label' => __('نظام الدفع', 'hello-elementor-child'), 'multi' => false],
        'f_feature' => ['taxonomy' => 'car_feature', 'label' => __('التجهيزات', 'hello-elementor-child'), 'multi' => true],
    ];
}

/** Numeric range facets: GET prefix (<prefix>_min, <prefix>_max) => ACF meta key, label, unit, input step. */
function get_car_range_filters(): array
{
    return [
        'price' => ['meta' => 'price', 'label' => __('السعر', 'hello-elementor-child'), 'unit' => __('ر.س', 'hello-elementor-child'), 'step' => 1],
        'power' => ['meta' => 'engine_hp', 'label' => __('القوة', 'hello-elementor-child'), 'unit' => __('حصان', 'hello-elementor-child'), 'step' => 1],
        'torque' => ['meta' => 'engine_torque_nm', 'label' => __('العزم', 'hello-elementor-child'), 'unit' => __('نيوتن.متر', 'hello-elementor-child'), 'step' => 1],
        'engine' => ['meta' => 'engine_displacement_l', 'label' => __('سعة المحرك', 'hello-elementor-child'), 'unit' => __('لتر', 'hello-elementor-child'), 'step' => 0.1],
        'seats' => ['meta' => 'interior_seats', 'label' => __('المقاعد', 'hello-elementor-child'), 'unit' => '', 'step' => 1],
    ];
}

/** Department term that brands all car screens (logo / accent). */
function get_cars_department(): ?WP_Term
{
    $term = get_term_by('slug', 'cars', 'department');
    return ($term instanceof WP_Term) ? $term : null;
}

// ---------------------------------------------------------------------------
// Request state
// ---------------------------------------------------------------------------

/**
 * Active car filters from the request.
 *
 * @return array{
 *   search: string,
 *   taxes: array<string, string|string[]>,
 *   ranges: array<string, array{min: ?float, max: ?float}>,
 *   paged: int
 * }
 */
function get_car_filter_state(): array
{
    $taxes = [];
    foreach (get_car_filter_taxonomies() as $param => $facet) {
        if (empty($_GET[$param])) {
            continue;
        }
        $slugs = [];
        foreach ((array) wp_unslash($_GET[$param]) as $raw) {
            $slug = sanitize_title((string) $raw);
            if ($slug !== '' && !in_array($slug, $slugs, true) && get_term_by('slug', $slug, $facet['taxonomy'])) {
                $slugs[] = $slug;
            }
        }
        if ($slugs) {
            $taxes[$param] = $facet['multi'] ? $slugs : $slugs[0];
        }
    }
    $taxes = resolve_car_filter_make_model($taxes);

    $ranges = [];
    foreach (array_keys(get_car_range_filters()) as $prefix) {
        $min = car_filter_number_param($prefix . '_min');
        $max = car_filter_number_param($prefix . '_max');
        if ($min === null && $max === null) {
            continue;
        }
        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }
        $ranges[$prefix] = ['min' => $min, 'max' => $max];
    }

    return [
        'search' => isset($_GET['car_s']) ? sanitize_text_field(wp_unslash($_GET['car_s'])) : '',
        'taxes' => $taxes,
        'ranges' => $ranges,
        'paged' => max(1, (int) (get_query_var('paged') ?: ($_GET['paged'] ?? 1))),
    ];
}

/** A non-negative number from the request, or null when absent or not numeric. */
function car_filter_number_param(string $name): ?float
{
    $raw = isset($_GET[$name]) ? trim((string) wp_unslash($_GET[$name])) : '';
    return ($raw !== '' && is_numeric($raw) && (float) $raw >= 0) ? (float) $raw : null;
}

/**
 * f_make must be a top-level make and f_model one of its models; a model without its make fills the make in,
 * and anything else is dropped. Keeps the facets in display order.
 */
function resolve_car_filter_make_model(array $taxes): array
{
    $make = isset($taxes['f_make']) ? get_term_by('slug', $taxes['f_make'], 'car_make') : false;
    $model = isset($taxes['f_model']) ? get_term_by('slug', $taxes['f_model'], 'car_make') : false;
    if ($make && $make->parent) {
        $make = false;
    }
    if ($model && !$model->parent) {
        $model = false;
    }
    if ($model && $make && $model->parent !== $make->term_id) {
        $model = false;
    }
    if ($model && !$make) {
        $parent = get_term($model->parent, 'car_make');
        $make = $parent instanceof WP_Term ? $parent : false;
        $model = $make ? $model : false;
    }
    $taxes['f_make'] = $make ? $make->slug : null;
    $taxes['f_model'] = $model ? $model->slug : null;

    $ordered = [];
    foreach (array_keys(get_car_filter_taxonomies()) as $param) {
        if (!empty($taxes[$param])) {
            $ordered[$param] = $taxes[$param];
        }
    }
    return $ordered;
}

// ---------------------------------------------------------------------------
// Query
// ---------------------------------------------------------------------------

/**
 * WP_Query for the car directory.
 *
 * @param array $args {
 *   @type int   $posts_per_page
 *   @type int   $paged
 *   @type array $state  A filter state (default: the request's).
 * }
 */
function query_cars_directory(array $args = []): WP_Query
{
    $state = $args['state'] ?? get_car_filter_state();

    $query_args = [
        'post_type' => 'car',
        'post_status' => 'publish',
        'posts_per_page' => max(1, (int) ($args['posts_per_page'] ?? CAR_FILTER_PER_PAGE)),
        'paged' => max(1, (int) ($args['paged'] ?? $state['paged'])),
        'ignore_sticky_posts' => true,
        'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC'],
    ];
    if ($state['search'] !== '') {
        $query_args['s'] = $state['search'];
    }
    $tax_query = get_car_filter_tax_query($state['taxes']);
    if ($tax_query) {
        $query_args['tax_query'] = $tax_query;
    }
    $meta_query = get_car_filter_meta_query($state['ranges']);
    if ($meta_query) {
        $query_args['meta_query'] = $meta_query;
    }

    return new WP_Query($query_args);
}

/** tax_query clauses: a make includes its models (the model replaces it when chosen); every chosen feature must be present. */
function get_car_filter_tax_query(array $taxes): array
{
    $facets = get_car_filter_taxonomies();
    $clauses = [];
    foreach ($taxes as $param => $value) {
        if (!isset($facets[$param]) || ($param === 'f_make' && isset($taxes['f_model']))) {
            continue;
        }
        $clause = [
            'taxonomy' => $facets[$param]['taxonomy'],
            'field' => 'slug',
            'terms' => $value,
            'include_children' => $param === 'f_make',
        ];
        if (is_array($value)) {
            $clause['operator'] = 'AND';
        }
        $clauses[] = $clause;
    }
    return $clauses ? array_merge(['relation' => 'AND'], $clauses) : [];
}

/** meta_query clauses for the numeric ranges; cars without the meta simply do not match. */
function get_car_filter_meta_query(array $ranges): array
{
    $map = get_car_range_filters();
    $clauses = [];
    foreach ($ranges as $prefix => $range) {
        if (!isset($map[$prefix])) {
            continue;
        }
        $clause = [
            'key' => $map[$prefix]['meta'],
            'type' => $map[$prefix]['step'] < 1 ? 'DECIMAL(10,2)' : 'NUMERIC',
        ];
        if ($range['min'] !== null && $range['max'] !== null) {
            $clause += ['value' => [$range['min'], $range['max']], 'compare' => 'BETWEEN'];
        } elseif ($range['min'] !== null) {
            $clause += ['value' => $range['min'], 'compare' => '>='];
        } else {
            $clause += ['value' => $range['max'], 'compare' => '<='];
        }
        $clauses[] = $clause;
    }
    return $clauses ? array_merge(['relation' => 'AND'], $clauses) : [];
}

// ---------------------------------------------------------------------------
// URLs
// ---------------------------------------------------------------------------

/** The car archive (/car-directory/): the page pagination and "show more" links point at. */
function get_cars_archive_url(): string
{
    return get_post_type_archive_link('car') ?: car_directory_url();
}

/** URL the filter form submits to: the car archive there, elsewhere the current page without its query string or page number. */
function get_cars_filter_base_url(): string
{
    if (is_post_type_archive('car')) {
        return get_cars_archive_url();
    }
    $path = (string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
    $path = preg_replace('#/page/\d+/?$#', '/', $path) ?: '/';
    return (is_ssl() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . $path; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
}

/**
 * The state as GET parameters (strings, or slug lists for multi facets): what links, pagination and the
 * browser URL carry.
 *
 * @return array<string, string|string[]>
 */
function get_car_filter_params(array $state): array
{
    $params = [];
    if ($state['search'] !== '') {
        $params['car_s'] = $state['search'];
    }
    foreach ($state['taxes'] as $param => $value) {
        $params[$param] = $value;
    }
    foreach ($state['ranges'] as $prefix => $range) {
        if ($range['min'] !== null) {
            $params[$prefix . '_min'] = (string) $range['min'];
        }
        if ($range['max'] !== null) {
            $params[$prefix . '_max'] = (string) $range['max'];
        }
    }
    return $params;
}

/**
 * Filter URL for a state with some parameters changed; null or '' removes one, and removing the make also
 * drops its model. Lists are written as name[]=... so links look like the form's own GET submit.
 */
function get_cars_filter_url(array $overrides = [], ?array $state = null, ?string $base = null): string
{
    $params = get_car_filter_params($state ?? get_car_filter_state());
    if (array_key_exists('f_make', $overrides) && empty($overrides['f_make'])) {
        $overrides['f_model'] = null;
    }
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '' || $value === []) {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    $base = $base ?? get_cars_filter_base_url();
    if (!$params) {
        return $base;
    }
    $query = preg_replace('/%5B\d+%5D=/', '%5B%5D=', http_build_query($params, '', '&', PHP_QUERY_RFC3986));
    return $base . (str_contains($base, '?') ? '&' : '?') . $query;
}

// ---------------------------------------------------------------------------
// Facet options
// ---------------------------------------------------------------------------

/**
 * Terms a facet offers, in display order: makes are top-level only, models those of the chosen make, years
 * newest first, body types in the source order, features by popularity, the rest by name.
 *
 * @return WP_Term[]
 */
function get_car_facet_terms(string $param, array $state = []): array
{
    $facets = get_car_filter_taxonomies();
    if (!isset($facets[$param])) {
        return [];
    }
    if ($param === 'f_body') {
        return car_body_types_ordered();
    }
    $args = ['taxonomy' => $facets[$param]['taxonomy'], 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC'];
    if ($param === 'f_make') {
        $args['parent'] = 0;
    } elseif ($param === 'f_model') {
        $make = isset($state['taxes']['f_make']) ? get_term_by('slug', $state['taxes']['f_make'], 'car_make') : false;
        if (!$make) {
            return [];
        }
        $args['parent'] = $make->term_id;
    } elseif ($param === 'f_year') {
        $args['order'] = 'DESC';
    } elseif ($param === 'f_feature') {
        $args['orderby'] = 'count';
        $args['order'] = 'DESC';
    }
    $terms = get_terms($args);
    return is_wp_error($terms) ? [] : $terms;
}

/**
 * Models grouped by make for the dependent model select: make slug => [[model slug, model name], ...].
 * Printed once with the filter so changing the make needs no request.
 */
function get_car_models_map(): array
{
    $terms = get_terms(['taxonomy' => 'car_make', 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC']);
    if (is_wp_error($terms)) {
        return [];
    }
    $slug_of = [];
    foreach ($terms as $term) {
        $slug_of[$term->term_id] = $term->slug;
    }
    $map = [];
    foreach ($terms as $term) {
        if ($term->parent && isset($slug_of[$term->parent])) {
            $map[$slug_of[$term->parent]][] = [$term->slug, $term->name];
        }
    }
    return $map;
}

// ---------------------------------------------------------------------------
// Text
// ---------------------------------------------------------------------------

/** "N سيارة" with Arabic number agreement (none, one, two, 3-10 plural, otherwise singular). */
function car_filter_count_text(int $count): string
{
    if ($count <= 0) {
        return __('لا توجد سيارات مطابقة', 'hello-elementor-child');
    }
    if ($count === 1) {
        return __('سيارة واحدة', 'hello-elementor-child');
    }
    if ($count === 2) {
        return __('سيارتان', 'hello-elementor-child');
    }
    $last = $count % 100;
    /* translators: %s: number of cars */
    $pattern = ($last >= 3 && $last <= 10) ? __('%s سيارات', 'hello-elementor-child') : __('%s سيارة', 'hello-elementor-child');
    return sprintf($pattern, number_format_i18n($count));
}

/** A range value for display: integers as-is, engine sizes with one decimal when needed. */
function car_filter_number(float $value, float $step = 1): string
{
    $decimals = ($step < 1 && floor($value) != $value) ? 1 : 0;
    return number_format_i18n($value, $decimals);
}

/** Chip text of a range: "السعر: من 50,000 ر.س إلى 200,000 ر.س", "من ...", "حتى ...". */
function car_filter_range_text(string $prefix, array $range): string
{
    $map = get_car_range_filters()[$prefix] ?? null;
    if (!$map) {
        return '';
    }
    $format = static fn(float $value): string => car_filter_number($value, $map['step']) . ($map['unit'] !== '' ? ' ' . $map['unit'] : '');
    if ($range['min'] !== null && $range['max'] !== null) {
        /* translators: 1: facet label, 2: minimum, 3: maximum */
        return sprintf(__('%1$s: من %2$s إلى %3$s', 'hello-elementor-child'), $map['label'], $format($range['min']), $format($range['max']));
    }
    if ($range['min'] !== null) {
        /* translators: 1: facet label, 2: minimum */
        return sprintf(__('%1$s: من %2$s', 'hello-elementor-child'), $map['label'], $format($range['min']));
    }
    /* translators: 1: facet label, 2: maximum */
    return sprintf(__('%1$s: حتى %2$s', 'hello-elementor-child'), $map['label'], $format($range['max']));
}

/** "ابتداءً من 159,448 ر.س" or "159,448 ر.س"; empty when the car has no price. */
function car_filter_price_text(int $post_id): string
{
    $price = (float) get_post_meta($post_id, 'price', true);
    if ($price <= 0) {
        return '';
    }
    $text = number_format_i18n($price) . ' ' . __('ر.س', 'hello-elementor-child');
    if (get_post_meta($post_id, 'price_is_starting_from', true)) {
        /* translators: %s: price */
        $text = sprintf(__('ابتداءً من %s', 'hello-elementor-child'), $text);
    }
    return $text;
}

/**
 * What a result tile shows besides the title: image (featured image or the shared placeholder),
 * "make · year" eyebrow and the price text.
 *
 * @return array{image: string, eyebrow: string, price: string}
 */
function get_car_tile_data(int $post_id): array
{
    $make = '';
    $makes = get_the_terms($post_id, 'car_make');
    foreach (is_array($makes) ? $makes : [] as $term) {
        if (!$term->parent) {
            $make = $term->name;
            break;
        }
    }
    $years = get_the_terms($post_id, 'car_year');
    $year = is_array($years) && $years ? $years[0]->name : (string) get_post_meta($post_id, 'model_year', true);

    return [
        'image' => get_the_post_thumbnail_url($post_id, 'medium') ?: get_stylesheet_directory_uri() . '/assets/images/car-placeholder.svg',
        'eyebrow' => implode(' · ', array_filter([$make, $year])),
        'price' => car_filter_price_text($post_id),
    ];
}

// ---------------------------------------------------------------------------
// Form controls
// ---------------------------------------------------------------------------

/** Inline style of the filter selects: native arrow off, one chevron on the end side (RTL: left). */
function car_filter_select_style(): string
{
    return "appearance:none !important; -webkit-appearance:none !important; -moz-appearance:none !important; "
        . "background-image:url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 12 12%22 fill=%22none%22%3E%3Cpath d=%22m3 4.5 3 3 3-3%22 stroke=%22%231f2937%22 stroke-width=%221.5%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22/%3E%3C/svg%3E'); "
        . "background-repeat:no-repeat; background-position:10px center; background-size:12px 12px; padding-left:2rem;";
}

/**
 * One facet select with its label. $variant 'bar' sits on the primary-colored bar, 'panel' on the light panel.
 * The model select is disabled until a make is chosen and carries the labels filters.js needs to refill it.
 */
function render_car_filter_select(string $param, array $state, string $variant = 'panel'): void
{
    $facets = get_car_filter_taxonomies();
    if (!isset($facets[$param])) {
        return;
    }
    $terms = get_car_facet_terms($param, $state);
    $active = (string) ($state['taxes'][$param] ?? '');
    $is_model = $param === 'f_model';
    if (!$terms && !$is_model) {
        return;
    }
    $id = 'cars-' . str_replace('_', '-', $param);
    $all_label = __('الكل', 'hello-elementor-child');
    $empty_label = __('اختر الشركة أولًا', 'hello-elementor-child');
    $disabled = $is_model && empty($state['taxes']['f_make']);
    $label_class = $variant === 'bar' ? 'text-xs font-bold text-white/90 px-1' : 'text-xs font-bold text-on-surface-variant px-1';
    $select_class = 'block w-full h-10 appearance-none rounded-full border bg-white text-on-background text-sm font-medium px-4 cursor-pointer shadow-xs focus:outline-none focus:ring-2 '
        . ($variant === 'bar' ? 'border-white/20 focus:ring-white/40' : 'border-surface-container-highest focus:ring-primary/30');
    ?>
    <div class="flex flex-col gap-1 flex-1 min-w-36">
        <label for="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr($label_class); ?>"><?php echo esc_html($facets[$param]['label']); ?></label>
        <select name="<?php echo esc_attr($param); ?>" id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr($select_class); ?>"
            style="<?php echo esc_attr(car_filter_select_style()); ?>"
            <?php if ($is_model): ?> data-all-label="<?php echo esc_attr($all_label); ?>" data-empty-label="<?php echo esc_attr($empty_label); ?>"<?php endif; ?>
            <?php disabled($disabled); ?>>
            <option value=""><?php echo esc_html($disabled ? $empty_label : $all_label); ?></option>
            <?php foreach ($terms as $term): ?>
                <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($active, $term->slug); ?>><?php echo esc_html($term->name); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php
}
