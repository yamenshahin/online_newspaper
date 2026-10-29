<?php
/**
 * Car directory display helpers: the building blocks of every car screen (home section, origin,
 * make, model, model-year, trim, body-type and price-range pages).
 *
 * Builders (car_tile(), car_term_tile(), car_post_tile(), car_pill(), car_img(), ...) RETURN html,
 * renderers (car_section_header(), car_strip(), car_grid(), car_breadcrumbs(), car_price_table(), ...)
 * ECHO it. Everything is escaped here, so templates print a builder's output as is.
 *
 * Markup is the live theme's vocabulary (tokens, cards, pills). The `data-car-*` hooks drive
 * assets/js/car-directory.js; the `.car-*` classes are styled in src/input.css (@layer components,
 * scoped under the `.car-directory` class the templates put on their <main>).
 *
 * Data access lives in inc/cars/directory.php (paths, URLs, price ranges, body-type order).
 */

if (!defined('ABSPATH')) {
    exit;
}

// ---------------------------------------------------------------------------
// Screens, cache generation, images
// ---------------------------------------------------------------------------

/** True on every car directory screen: trim, model year, the directory archive, its taxonomies and price pages. */
function is_car_directory_screen(): bool
{
    return is_singular(['car', 'car_model_year'])
        || is_post_type_archive('car')
        || is_tax(array_keys(car_directory_taxonomies()))
        || (bool) get_query_var('car_price');
}

/** Cache generation of the car data: bump the `car_directory_data_version` option after an import. */
function car_directory_data_version(): string
{
    return sanitize_key((string) get_option('car_directory_data_version', '1')) ?: '1';
}

/** Shared placeholder (16:9 car silhouette) for every missing car / term image. */
function car_placeholder_url(): string
{
    return get_stylesheet_directory_uri() . '/assets/images/car-placeholder.svg';
}

/** Featured image of a car / model-year post, or the placeholder. */
function car_image_url(int $post_id, string $size = 'large'): string
{
    $url = has_post_thumbnail($post_id) ? (string) get_the_post_thumbnail_url($post_id, $size) : '';
    return $url ?: car_placeholder_url();
}

/** Logo of a make / origin / body-type term (ACF `logo` attachment), or the placeholder. */
function car_term_image_url(WP_Term $term, string $size = 'medium'): string
{
    $logo = (int) get_term_meta($term->term_id, 'logo', true);
    $url = $logo ? (string) wp_get_attachment_image_url($logo, $size) : '';
    return $url ?: car_placeholder_url();
}

/** <img>; an empty $url (or the placeholder URL) renders the tinted placeholder. */
function car_img(string $url, string $alt, string $class = '', string $loading = 'lazy'): string
{
    $placeholder = $url === '' || $url === car_placeholder_url();
    return sprintf(
        '<img src="%s" alt="%s" loading="%s" decoding="async" class="%s">',
        esc_url($placeholder ? car_placeholder_url() : $url),
        esc_attr($alt),
        esc_attr($loading),
        esc_attr(trim($class . ($placeholder ? ' car-img-placeholder' : '')))
    );
}

/** Utility classes that give editor HTML (descriptions, reviews) paragraph / list / heading rhythm. */
function car_rich_text_classes(): string
{
    return 'car-rich-text [&_p]:mb-4 [&_p:last-child]:mb-0 [&_ul]:mb-4 [&_ul]:list-disc [&_ul]:ps-6 [&_ol]:mb-4 [&_ol]:list-decimal [&_ol]:ps-6 [&_li]:mb-1'
        . ' [&_h2]:mt-6 [&_h2]:mb-2 [&_h2]:text-xl [&_h2]:font-semibold [&_h3]:mt-6 [&_h3]:mb-2 [&_h3]:text-lg [&_h3]:font-semibold [&_h4]:mt-4 [&_h4]:mb-2 [&_h4]:font-semibold'
        . ' [&_a]:text-primary [&_a]:underline [&_strong]:font-semibold [&_img]:rounded-xl [&_table]:w-full [&_td]:p-2 [&_th]:p-2';
}

// ---------------------------------------------------------------------------
// Hierarchy: origin > make > model > model year > trim
// ---------------------------------------------------------------------------

/** ['origin' => WP_Term|null, 'make' => WP_Term|null, 'model' => WP_Term|null] of a car or model-year post. */
function car_post_terms(int $post_id): array
{
    $out = ['origin' => null, 'make' => null, 'model' => null];
    $makes = wp_get_object_terms($post_id, 'car_make');
    foreach (is_wp_error($makes) ? [] : $makes as $t) {
        $out[$t->parent ? 'model' : 'make'] = $t;
    }
    if ($out['model'] && !$out['make']) {
        $parent = get_term($out['model']->parent, 'car_make');
        $out['make'] = $parent instanceof WP_Term ? $parent : null;
    }
    $origins = wp_get_object_terms($post_id, 'car_origin');
    $out['origin'] = (!is_wp_error($origins) && $origins) ? $origins[0] : ($out['make'] ? car_make_origin($out['make']) : null);
    return $out;
}

/** The origin of a make (`_car_origin` term meta holds the origin slug). */
function car_make_origin(WP_Term $make): ?WP_Term
{
    $slug = (string) get_term_meta($make->term_id, '_car_origin', true);
    $term = $slug ? get_term_by('slug', $slug, 'car_origin') : null;
    return $term instanceof WP_Term ? $term : null;
}

/** Makes of an origin, most cars first. @return WP_Term[] */
function car_origin_makes(WP_Term $origin): array
{
    $makes = get_terms([
        'taxonomy' => 'car_make',
        'parent' => 0,
        'hide_empty' => false,
        'meta_key' => '_car_origin',
        'meta_value' => $origin->slug,
    ]);
    return is_wp_error($makes) ? [] : car_sort_by_count($makes);
}

/** Models of a make, most cars first. @return WP_Term[] */
function car_make_models(WP_Term $make): array
{
    $models = get_terms(['taxonomy' => 'car_make', 'parent' => $make->term_id, 'hide_empty' => false]);
    return is_wp_error($models) ? [] : car_sort_by_count($models);
}

/** Terms of one taxonomy by published-car count (desc), then name. @param WP_Term[] $terms @return WP_Term[] */
function car_sort_by_count(array $terms): array
{
    if (!$terms) {
        return [];
    }
    $counts = car_term_counts($terms[0]->taxonomy);
    usort($terms, static fn(WP_Term $a, WP_Term $b) => (($counts[$b->term_id] ?? 0) <=> ($counts[$a->term_id] ?? 0)) ?: strcmp($a->name, $b->name));
    return $terms;
}

/** Model-year posts of a model, newest first (sub-families excluded: they hang under their year). @return WP_Post[] */
function car_model_years(WP_Term $model): array
{
    return get_posts([
        'post_type' => 'car_model_year',
        'posts_per_page' => -1,
        'no_found_rows' => true,
        'tax_query' => [['taxonomy' => 'car_make', 'terms' => $model->term_id]],
        'meta_query' => [['key' => '_car_page_type', 'value' => 'model_year']],
        'meta_key' => 'model_year',
        'orderby' => ['meta_value_num' => 'DESC', 'title' => 'ASC'],
    ]);
}

/** Cars that are their own model-year page (`_car_is_yearleaf`), newest first. @return WP_Post[] */
function car_yearleaf_cars(WP_Term $model): array
{
    return get_posts([
        'post_type' => 'car',
        'posts_per_page' => -1,
        'no_found_rows' => true,
        'tax_query' => [['taxonomy' => 'car_make', 'terms' => $model->term_id]],
        'meta_query' => [['key' => '_car_is_yearleaf', 'value' => '1']],
        'meta_key' => 'model_year',
        'orderby' => ['meta_value_num' => 'DESC', 'title' => 'ASC'],
    ]);
}

/** Sub-family pages of a model year (`_car_parent_year`). @return WP_Post[] */
function car_subfamilies(int $year_id): array
{
    return get_posts([
        'post_type' => 'car_model_year',
        'posts_per_page' => -1,
        'no_found_rows' => true,
        'meta_query' => [['key' => '_car_parent_year', 'value' => (string) $year_id]],
        'orderby' => 'title',
        'order' => 'ASC',
    ]);
}

/** Trims of a model year in source order (the `trims` relationship keeps the import order). @return WP_Post[] */
function car_year_trims(int $year_id): array
{
    $ids = array_values(array_filter(array_map('intval', (array) get_field('trims', $year_id, false))));
    if (!$ids) {
        return [];
    }
    return get_posts(['post_type' => 'car', 'post__in' => $ids, 'orderby' => 'post__in', 'posts_per_page' => -1, 'no_found_rows' => true]);
}

/** The model-year post a trim belongs to (`parent_model_year` relationship); 0 for year-leaf cars. */
function car_parent_year_id(int $car_id): int
{
    $raw = get_field('parent_model_year', $car_id, false);
    return (int) (is_array($raw) ? ($raw[0] ?? 0) : $raw);
}

/** Full name stored by the import on makes / models (`_car_full_name`), or the term name. */
function car_term_title(WP_Term $term): string
{
    return (string) get_term_meta($term->term_id, '_car_full_name', true) ?: $term->name;
}

/** Term link, '' on error. */
function car_term_url(WP_Term $term): string
{
    $url = get_term_link($term);
    return is_wp_error($url) ? '' : $url;
}

/** Title without the parent's name ("تويوتا كامري 2026" -> "2026"), $fallback when nothing is left. */
function car_short_title(WP_Post $post, array $strip, string $fallback = ''): string
{
    $short = trim(str_replace(array_filter($strip), '', get_the_title($post)));
    if ($short !== '') {
        return $short;
    }
    return $fallback !== '' ? $fallback : get_the_title($post);
}

/**
 * One car per model: its cheapest current trim (model year >= this year - 1), like the source's
 * price-range and body-type pages. $args: price_min, price_max (exclusive, 0 = open), tax_query.
 * Cached for an hour per data version.
 *
 * @return WP_Post[] cheapest first
 */
function car_cheapest_per_model(array $args): array
{
    $cache_key = 'car_cpm_' . md5(wp_json_encode($args) . wp_date('Y') . car_directory_data_version());
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return array_values(array_filter(array_map('get_post', $cached)));
    }
    global $wpdb;
    // Two key -> value maps (meta_key is indexed) filtered in PHP: a meta_query with three postmeta
    // self-joins over ~5,000 cars costs seconds.
    $map = static function (string $key) use ($wpdb): array {
        $out = [];
        foreach ($wpdb->get_results($wpdb->prepare(
            "SELECT m.post_id, m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
             WHERE m.meta_key = %s AND m.meta_value <> '' AND p.post_type = 'car' AND p.post_status = 'publish'",
            $key
        ), ARRAY_N) as [$id, $v]) {
            if (is_numeric($v)) {
                $out[(int) $id] = $v + 0;
            }
        }
        return $out;
    };
    $min_year = (int) wp_date('Y') - 1;
    $years = array_filter($map('model_year'), static fn($y) => $y >= $min_year);
    // SAR prices only (some imported trims are priced in USD), and no import glitches like "1"
    $prices = array_filter($map('price'), static fn($p) => $p >= 1000);
    foreach ($wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'price_currency' AND meta_value <> '' AND meta_value <> 'SAR'") as $foreign) {
        unset($prices[(int) $foreign]);
    }
    $ids = array_keys($years);
    if (isset($args['price_min'])) {
        $lo = max(1, (int) $args['price_min']);
        $hi = (int) ($args['price_max'] ?? 0);
        $ids = array_values(array_filter($ids, static fn($id) => isset($prices[$id]) && $prices[$id] >= $lo && (!$hi || $prices[$id] < $hi)));
    }
    if (!empty($args['tax_query']) && $ids) {
        $ids = get_posts(['post_type' => 'car', 'post__in' => $ids, 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'tax_query' => $args['tax_query']]);
    }
    $model_of = [];
    foreach (array_chunk($ids, 1000) as $chunk) {
        foreach ($wpdb->get_results("SELECT tr.object_id, tt.term_id FROM {$wpdb->term_relationships} tr
            JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
            WHERE tt.taxonomy = 'car_make' AND tt.parent <> 0 AND tr.object_id IN (" . implode(',', array_map('intval', $chunk)) . ')') as $r) {
            $model_of[(int) $r->object_id] = (int) $r->term_id;
        }
    }
    $best = [];
    foreach ($ids as $id) {
        $key = $model_of[$id] ?? "car{$id}";
        $price = $prices[$id] ?? PHP_INT_MAX;
        $year = $years[$id];
        // newest model year wins, then the lowest price
        if (!isset($best[$key]) || $year > $best[$key][1] || ($year === $best[$key][1] && $price < $best[$key][2])) {
            $best[$key] = [$id, $year, $price];
        }
    }
    usort($best, static fn($a, $b) => $a[2] <=> $b[2]);
    $ids = array_column($best, 0);
    set_transient($cache_key, $ids, HOUR_IN_SECONDS);
    return array_values(array_filter(array_map('get_post', $ids)));
}

// ---------------------------------------------------------------------------
// Counts (published cars only: term counts also include model-year posts)
// ---------------------------------------------------------------------------

/** Published cars per term of a taxonomy (term_id => count), cached for a day per data version. */
function car_term_counts(string $taxonomy): array
{
    static $memo = [];
    if (isset($memo[$taxonomy])) {
        return $memo[$taxonomy];
    }
    $key = 'car_term_counts_' . sanitize_key($taxonomy) . '_' . car_directory_data_version();
    $counts = get_transient($key);
    if (!is_array($counts)) {
        global $wpdb;
        $counts = [];
        foreach ($wpdb->get_results($wpdb->prepare(
            "SELECT tt.term_id, COUNT(DISTINCT tr.object_id) AS n FROM {$wpdb->term_relationships} tr
             JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             JOIN {$wpdb->posts} p ON p.ID = tr.object_id
             WHERE tt.taxonomy = %s AND p.post_type = 'car' AND p.post_status = 'publish' GROUP BY tt.term_id",
            $taxonomy
        )) as $r) {
            $counts[(int) $r->term_id] = (int) $r->n;
        }
        set_transient($key, $counts, DAY_IN_SECONDS);
    }
    return $memo[$taxonomy] = $counts;
}

/** Published cars in a term (a make's count includes its models' cars: cars carry both terms). */
function car_term_count(WP_Term $term): int
{
    return car_term_counts($term->taxonomy)[$term->term_id] ?? 0;
}

/** Published cars in the whole directory. */
function car_total_count(): int
{
    return (int) wp_count_posts('car')->publish;
}

// ---------------------------------------------------------------------------
// Formatting: numbers, prices, Arabic number agreement
// ---------------------------------------------------------------------------

/** "1,234" / "2.8"; non-numeric values are returned as they are. */
function format_number($n): string
{
    if (!is_numeric($n)) {
        return (string) $n;
    }
    $n += 0;
    return is_float($n) && floor($n) != $n ? rtrim(rtrim(number_format($n, 2), '0'), '.') : number_format((float) $n);
}

/** "185,150 ر.س" ('' when there is no price; the riyal sign is missing from most fonts, so the abbreviation is used). */
function format_sar($n): string
{
    return is_numeric($n) && $n > 0 ? format_number($n) . ' ر.س' : '';
}

/** A car's price in its own currency (`price_currency`: SAR, or USD for some imported trims); '' when it has none. */
function car_price_text(int $post_id): string
{
    $price = get_field('price', $post_id);
    if (!is_numeric($price) || $price <= 0) {
        return '';
    }
    return get_post_meta($post_id, 'price_currency', true) === 'USD' ? format_number($price) . ' دولار أمريكي' : format_sar($price);
}

/**
 * Arabic number agreement. $forms = [one, two, few (3-10), many (11+)]; the last two take %s.
 * e.g. arabic_count(5, ['سيارة واحدة', 'سيارتان', '%s سيارات', '%s سيارة']) -> "5 سيارات".
 */
function arabic_count(int $n, array $forms, string $none = ''): string
{
    if ($n <= 0) {
        return $none;
    }
    if ($n === 1) {
        return (string) $forms[0];
    }
    if ($n === 2) {
        return (string) $forms[1];
    }
    $last_two = $n % 100;
    $form = ($last_two >= 3 && $last_two <= 10) ? $forms[2] : $forms[3];
    return sprintf((string) $form, number_format_i18n($n));
}

function n_cars(int $n): string
{
    return arabic_count($n, ['سيارة واحدة', 'سيارتان', '%s سيارات', '%s سيارة'], 'لا توجد سيارات');
}

function n_models(int $n): string
{
    return arabic_count($n, ['موديل واحد', 'موديلان', '%s موديلات', '%s موديلاً'], 'لا توجد موديلات');
}

function n_makes(int $n): string
{
    return arabic_count($n, ['علامة واحدة', 'علامتان', '%s علامات', '%s علامة'], 'لا توجد علامات');
}

function n_trims(int $n): string
{
    return arabic_count($n, ['فئة واحدة', 'فئتان', '%s فئات', '%s فئة'], 'لا توجد فئات');
}

function n_years(int $n): string
{
    return arabic_count($n, ['سنة موديل واحدة', 'سنتا موديل', '%s سنوات موديل', '%s سنة موديل'], 'لا توجد سنوات موديل');
}

function n_images(int $n): string
{
    return arabic_count($n, ['صورة واحدة', 'صورتان', '%s صور', '%s صورة'], 'لا توجد صور');
}

// ---------------------------------------------------------------------------
// Breadcrumbs, title row, section wrapper + header
// ---------------------------------------------------------------------------

/** [label, url] crumbs for origin > make > model (only the terms given). */
function car_term_crumbs(?WP_Term $origin, ?WP_Term $make, ?WP_Term $model): array
{
    $crumbs = [];
    foreach ([$origin, $make, $model] as $t) {
        if ($t instanceof WP_Term) {
            $crumbs[] = [$t->name, car_term_url($t)];
        }
    }
    return $crumbs;
}

/** Crumbs of a car or model-year post down to the post itself (the last item has no url). */
function car_post_crumbs(int $post_id): array
{
    $t = car_post_terms($post_id);
    $crumbs = car_term_crumbs($t['origin'], $t['make'], $t['model']);
    if (get_post_type($post_id) === 'car') {
        $year_id = car_parent_year_id($post_id);
        if ($year_id) {
            $grand = (int) get_post_meta($year_id, '_car_parent_year', true); // sub-family: year > sub-family
            if ($grand) {
                $crumbs[] = [get_the_title($grand), get_permalink($grand)];
            }
            $crumbs[] = [get_the_title($year_id), get_permalink($year_id)];
        }
    } elseif ($grand = (int) get_post_meta($post_id, '_car_parent_year', true)) {
        $crumbs[] = [get_the_title($grand), get_permalink($grand)];
    }
    $crumbs[] = [get_the_title($post_id), ''];
    return $crumbs;
}

/** الرئيسية › دليل السيارات › ...; $items = [[label, url|''], ...], the last one is the current page. */
function car_breadcrumbs(array $items): void
{
    $crumbs = array_merge([['الرئيسية', home_url('/')], ['دليل السيارات', car_directory_url()]], $items);
    $last = count($crumbs) - 1;
    echo '<nav class="car-crumbs mb-4 flex flex-wrap items-center gap-1 text-body-sm text-on-surface-variant" aria-label="مسار التصفح">';
    foreach ($crumbs as $i => [$label, $url]) {
        if ($i) {
            echo '<i class="fas fa-angle-left text-xs" aria-hidden="true"></i>';
        }
        if ($i === $last) {
            echo '<span class="font-semibold text-on-surface" aria-current="page">' . esc_html($label) . '</span>';
        } elseif ($url && !is_wp_error($url)) {
            echo '<a class="transition-colors hover:text-primary" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        } else {
            echo '<span>' . esc_html($label) . '</span>';
        }
    }
    echo '</nav>';
}

/** Page title on the right, parent links as pills on the left (the source layout). $parents = [[label, url], ...]. */
function car_title_row(string $title, array $parents = [], string $eyebrow = ''): void
{
    echo '<div class="car-title-row mb-6 flex flex-col gap-space-sm border-b border-surface-container-high pb-4 md:flex-row md:items-start md:justify-between">';
    echo '<div class="flex flex-col gap-space-xs text-start">';
    if ($eyebrow !== '') {
        echo '<span class="font-label-caps text-label-caps uppercase tracking-widest text-primary">' . esc_html($eyebrow) . '</span>';
    }
    echo '<h1 class="font-headline-sm text-headline-sm text-on-background md:font-headline-lg md:text-headline-lg">' . esc_html($title) . '</h1></div>';
    $pills = [];
    foreach (array_reverse($parents) as [$label, $url]) {
        if ($url && !is_wp_error($url)) {
            $pills[] = car_pill($label, $url, 'soft');
        }
    }
    if ($pills) {
        echo '<div class="flex flex-wrap gap-2 md:justify-end md:pt-2">' . implode('', $pills) . '</div>';
    }
    echo '</div>';
}

/** <section class="w-full px-margin-mobile lg:px-margin py-space-xl ..."> - pair with car_section_close(). */
function car_section_open(string $class = '', string $id = ''): void
{
    echo '<section class="' . esc_attr(trim('car-section w-full px-margin-mobile lg:px-margin py-space-xl ' . $class)) . '"' . ($id !== '' ? ' id="' . esc_attr($id) . '"' : '') . '>';
}

function car_section_close(): void
{
    echo '</section>';
}

/**
 * Live section header: eyebrow + h2 (+ count pill, + "more" link). $count is already formatted
 * (n_cars(12)); $tag lets a page use h3 for sub-sections.
 */
function car_section_header(string $title, string $eyebrow = '', string $count = '', string $more_url = '', string $more_label = 'المزيد', string $tag = 'h2'): void
{
    $tag = in_array($tag, ['h2', 'h3', 'h4', 'div'], true) ? $tag : 'h2';
    echo '<div class="car-section-header mb-space-lg flex flex-col justify-between gap-space-md md:flex-row md:items-end">';
    echo '<div class="flex flex-col gap-space-xs text-start">';
    if ($eyebrow !== '') {
        echo '<span class="font-label-caps text-label-caps uppercase tracking-widest text-primary">' . esc_html($eyebrow) . '</span>';
    }
    echo '<div class="flex flex-wrap items-center gap-space-sm">';
    echo "<{$tag} class=\"font-headline-sm text-headline-sm text-on-background md:font-headline-lg md:text-headline-lg\">" . esc_html($title) . "</{$tag}>";
    if ($count !== '') {
        echo '<span class="rounded-full bg-primary-container px-space-sm py-0.5 font-label-caps text-label-caps font-bold text-on-primary">' . esc_html($count) . '</span>';
    }
    echo '</div></div>';
    if ($more_url !== '') {
        echo '<a href="' . esc_url($more_url) . '" class="inline-flex items-center gap-1.5 self-start text-base font-medium text-primary transition-opacity hover:opacity-70 md:self-auto md:text-lg">'
            . '<span>' . esc_html($more_label) . '</span><i class="fas fa-angle-left" aria-hidden="true"></i></a>';
    }
    echo '</div>';
}

/** Dashed empty state. */
function car_empty_state(string $text): void
{
    echo '<p class="car-empty rounded-2xl border border-dashed border-surface-container-highest px-6 py-8 text-center text-on-surface-variant">' . esc_html($text) . '</p>';
}

// ---------------------------------------------------------------------------
// Strips and grids
// ---------------------------------------------------------------------------

/**
 * Horizontal strip with prev / next arrows, or a centered grid when it fits (<= 8 tiles, like the source).
 * $wide = 150px tiles instead of 118px.
 */
function car_strip(array $tiles_html, bool $wide = false): void
{
    if (!$tiles_html) {
        return;
    }
    if (count($tiles_html) <= 8) {
        car_grid($tiles_html, 'center');
        return;
    }
    echo '<div class="car-strip-wrap relative" data-car-strip-wrap>'
        . '<div class="car-strip' . ($wide ? ' car-strip-wide' : '') . '" data-car-strip>' . implode('', $tiles_html) . '</div>'
        . '<button type="button" class="car-strip-arrow car-strip-arrow-prev" data-car-strip-prev aria-label="السابق"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>'
        . '<button type="button" class="car-strip-arrow car-strip-arrow-next" data-car-strip-next aria-label="التالي"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>'
        . '</div>';
}

/**
 * Tile grid. $variant: 'default' (210px columns), 'wide' (280px), 'logos' (130px), 'center' (centered 118px tiles).
 * $empty is shown (dashed) when there are no tiles.
 */
function car_grid(array $tiles_html, string $variant = 'default', string $empty = ''): void
{
    if (!$tiles_html) {
        if ($empty !== '') {
            car_empty_state($empty);
        }
        return;
    }
    $classes = ['default' => 'car-grid', 'wide' => 'car-grid car-grid-wide', 'logos' => 'car-grid car-grid-logos', 'center' => 'car-grid-center'];
    echo '<div class="' . esc_attr($classes[$variant] ?? $classes['default']) . '">' . implode('', $tiles_html) . '</div>';
}

// ---------------------------------------------------------------------------
// Tiles (builders: return html)
// ---------------------------------------------------------------------------

/**
 * Card frame of a tile. Utilities beat @layer components in Tailwind v4, so the frame-less (car-tile-logo)
 * and current (is-current) variants are decided here rather than overridden in CSS.
 */
function car_tile_frame(string $class): string
{
    if (str_contains($class, 'car-tile-logo')) {
        return 'rounded-2xl border border-transparent p-3 transition-all hover:border-surface-container-high';
    }
    $border = str_contains($class, 'is-current') ? 'border-primary' : 'border-surface-container-high hover:border-primary';
    return "rounded-2xl border {$border} bg-surface-container-lowest p-3 shadow-sm transition-all hover:shadow-lg";
}

/**
 * Generic tile: image + title (+ subtitle, price, badge). $img_html comes from car_img();
 * $class adds a variant: car-tile-big (96px image), car-tile-logo (no frame), car-tile-body, is-current.
 */
function car_tile(string $url, string $img_html, string $title, string $sub = '', string $price = '', string $class = '', string $badge = ''): string
{
    return sprintf(
        '<a class="car-tile group relative flex flex-col items-center justify-center gap-1 text-center %s %s" href="%s">%s<span class="car-tile-img">%s</span><span class="car-tile-title font-semibold leading-snug text-on-background transition-colors group-hover:text-primary">%s</span>%s%s</a>',
        car_tile_frame($class),
        esc_attr($class),
        esc_url($url),
        $badge !== '' ? '<span class="car-tile-badge rounded-full bg-primary px-2 font-label-pill text-label-pill text-on-primary">' . esc_html($badge) . '</span>' : '',
        $img_html,
        esc_html($title),
        $sub !== '' ? '<span class="text-body-sm text-on-surface-variant">' . esc_html($sub) . '</span>' : '',
        $price !== '' ? '<span class="font-semibold text-primary">' . esc_html($price) . '</span>' : ''
    );
}

/** Text-only tile (price ranges, "all makes"). $text_html is escaped by the caller (it may hold <br>). */
function car_text_tile(string $url, string $text_html, string $class = ''): string
{
    return sprintf(
        '<a class="car-tile car-tile-text group flex items-center justify-center text-center font-semibold text-on-background hover:text-primary %s %s" href="%s"><span>%s</span></a>',
        car_tile_frame($class),
        esc_attr($class),
        esc_url($url),
        $text_html
    );
}

/** Make / model / origin tile with the term logo (placeholder until the logos are uploaded). */
function car_term_tile(WP_Term $term, string $class = '', string $label = ''): string
{
    return car_tile(car_term_url($term), car_img(car_term_image_url($term), $term->name, 'car-tile-pic'), $label !== '' ? $label : $term->name, '', '', $class);
}

/** Body-type tile: the source icon (term logo) once attached, the placeholder silhouette until then. */
function car_body_tile(WP_Term $body, string $sub = '', string $class = ''): string
{
    return car_tile(car_term_url($body), car_img(car_term_image_url($body), $body->name, 'car-tile-pic'), $body->name, $sub, '', trim('car-tile-big car-tile-body ' . $class));
}

/** Model-year / trim tile with the post's featured image; $label overrides the title (e.g. "2026"). */
function car_post_tile(WP_Post $post, string $label = '', string $badge = ''): string
{
    return car_tile(get_permalink($post), car_img(car_image_url($post->ID, 'medium'), get_the_title($post), 'car-tile-pic'), $label !== '' ? $label : get_the_title($post), '', '', '', $badge);
}

/** Listing card (price-range / body-type pages, rivals): "Make Model", "Trim Year", price. */
function car_listing_tile(WP_Post $car): string
{
    $t = car_post_terms($car->ID);
    $name = $t['model'] ? trim(($t['make']->name ?? '') . ' ' . $t['model']->name) : get_the_title($car);
    $trim = (string) get_field('trim_name', $car->ID);
    $year = (string) get_field('model_year', $car->ID);
    $sub = trim($year !== '' && str_contains($trim, $year) ? $trim : "{$trim} {$year}"); // year-leaf cars: the trim name is the year
    return car_tile(get_permalink($car), car_img(car_image_url($car->ID, 'medium'), $name, 'car-tile-pic'), $name, $sub, car_price_text($car->ID) ?: 'لا يوجد سعر', 'car-tile-big');
}

/** One text tile per price range (car_price_ranges()); $current gets the is-current frame. */
function car_price_range_tiles(string $current = ''): array
{
    $tiles = [];
    foreach (car_price_ranges() as $slug => [$label]) {
        $parts = preg_split('/ إلى /u', $label);
        $text = count($parts) === 2
            ? esc_html($parts[0]) . ' ر.س<br><span class="text-body-sm font-normal text-on-surface-variant">إلى</span><br>' . esc_html($parts[1]) . ' ر.س'
            : esc_html($label) . ' ر.س';
        $tiles[] = car_text_tile(car_price_range_url($slug), $text, $slug === $current ? 'is-current' : '');
    }
    return $tiles;
}

/** One tile per body type in the source order; $with_counts adds "N سيارة" under the name. */
function car_body_type_tiles(bool $with_counts = false, string $current = ''): array
{
    $tiles = [];
    foreach (car_body_types_ordered() as $b) {
        $tiles[] = car_body_tile($b, $with_counts ? n_cars(car_term_count($b)) : '', $b->slug === $current ? 'is-current' : '');
    }
    return $tiles;
}

/** Model page: one tile per model year, then the year-leaf cars, labelled with what is left of the title ("2026", "GTI 2023"). */
function car_model_year_tiles(WP_Term $model): array
{
    $strip = array_unique([car_term_title($model), $model->name]);
    $tiles = [];
    foreach (car_model_years($model) as $y) {
        $tiles[] = car_post_tile($y, car_short_title($y, $strip, (string) get_field('model_year', $y->ID)));
    }
    foreach (car_yearleaf_cars($model) as $car) {
        $tiles[] = car_post_tile($car, car_short_title($car, $strip, (string) get_field('model_year', $car->ID)));
    }
    return $tiles;
}

/** Model-year page: sub-family tiles (badge "فئة فرعية") then the trims, labelled by trim name. */
function car_year_tiles(int $year_id): array
{
    $title = get_the_title($year_id);
    $tiles = [];
    foreach (car_subfamilies($year_id) as $s) {
        $tiles[] = car_post_tile($s, car_short_title($s, [$title]), 'فئة فرعية');
    }
    foreach (car_year_trims($year_id) as $car) {
        $tiles[] = car_post_tile($car, (string) get_field('trim_name', $car->ID) ?: get_the_title($car));
    }
    return $tiles;
}

// ---------------------------------------------------------------------------
// Pills and chips
// ---------------------------------------------------------------------------

/** Pill (link when $url is given). $variant: primary | soft | outline | dark; $prefix is a muted label ("السنة"). */
function car_pill(string $label, string $url = '', string $variant = 'soft', string $prefix = ''): string
{
    $variants = [
        'primary' => 'bg-primary text-on-primary',
        'soft' => 'bg-surface-container text-on-surface transition-colors hover:bg-primary hover:text-on-primary',
        'outline' => 'border border-surface-container-highest bg-surface-container-lowest text-on-surface transition-colors hover:border-primary hover:text-primary',
        'dark' => 'bg-inverse-surface text-inverse-on-surface',
    ];
    $class = 'car-pill inline-flex items-center gap-1 whitespace-nowrap rounded-full px-3 py-1 text-sm font-semibold ' . ($variants[$variant] ?? $variants['soft']);
    $inner = ($prefix !== '' ? '<span class="font-normal opacity-70">' . esc_html($prefix) . '</span> ' : '') . esc_html($label);
    return $url !== ''
        ? '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '">' . $inner . '</a>'
        : '<span class="' . esc_attr($class) . '">' . $inner . '</span>';
}

/** Where a chip goes: origins, makes, models and body types have their own pages; the other facets open the filter. */
function car_chip_url(WP_Term $t): string
{
    if (in_array($t->taxonomy, ['car_origin', 'car_make', 'body_type'], true)) {
        return car_term_url($t);
    }
    foreach (get_car_filter_taxonomies() as $param => $facet) {
        if ($facet['taxonomy'] === $t->taxonomy && $param !== 'f_model') {
            return add_query_arg($param . ($facet['multi'] ? '[]' : ''), $t->slug, get_cars_archive_url());
        }
    }
    return car_term_url($t);
}

/** Taxonomy chips of a post: one pill per term, prefixed with the taxonomy's singular label. */
function car_chips(int $post_id, array $taxonomies, bool $with_labels = true): void
{
    $pills = [];
    foreach ($taxonomies as $tax) {
        $obj = get_taxonomy($tax);
        $terms = wp_get_object_terms($post_id, $tax);
        foreach (is_wp_error($terms) ? [] : $terms as $t) {
            $pills[] = car_pill($t->name, car_chip_url($t), 'soft', $with_labels && $obj ? (string) $obj->labels->singular_name : '');
        }
    }
    if ($pills) {
        echo '<div class="car-chips flex flex-wrap gap-2">' . implode('', $pills) . '</div>';
    }
}

// ---------------------------------------------------------------------------
// Description (read more), highlights, price box, gallery
// ---------------------------------------------------------------------------

/** Editor HTML clamped to two lines with a "read more" toggle when longer than $limit characters. */
function car_description(string $html, int $limit = 160): void
{
    $text = trim(wp_strip_all_tags($html));
    if ($text === '') {
        return;
    }
    static $n = 0;
    $id = 'car-desc-' . ++$n;
    $long = mb_strlen($text) > $limit;
    echo '<div class="car-desc mb-6 text-body-base text-on-surface ' . esc_attr(car_rich_text_classes()) . ($long ? ' car-clamp' : '') . '" id="' . esc_attr($id) . '"' . ($long ? ' data-car-clamp' : '') . '>'
        . wpautop(wp_kses_post($html)) . '</div>';
    if ($long) {
        echo '<button type="button" class="car-more -mt-4 mb-6 text-sm font-semibold text-primary transition-opacity hover:opacity-70" data-car-more aria-expanded="false" aria-controls="' . esc_attr($id) . '" data-label-open="إخفاء" data-label-closed="...اقرأ المزيد">...اقرأ المزيد</button>';
    }
}

/** أبرز المواصفات: the `highlights` repeater as a grid of label / value cards. */
function car_highlights(int $post_id): void
{
    $rows = array_filter((array) get_field('highlights', $post_id), static fn($h) => is_array($h) && trim((string) ($h['value'] ?? '')) !== '');
    if (!$rows) {
        return;
    }
    echo '<div class="car-highlights mb-8">';
    foreach ($rows as $h) {
        echo '<div class="rounded-xl border border-surface-container-high bg-surface-container-lowest px-4 py-3">'
            . '<div class="text-body-sm text-on-surface-variant">' . esc_html((string) ($h['label'] ?? '')) . '</div>'
            . '<div class="font-semibold text-on-background">' . esc_html((string) $h['value']) . '</div></div>';
    }
    echo '</div>';
}

/** Price box of a trim: label (يبدأ من), amount, VAT line, warranty. */
function car_price_box(int $post_id): void
{
    $price = get_field('price', $post_id);
    $has = is_numeric($price) && $price > 0;
    echo '<div class="car-price-box flex flex-col gap-1 rounded-2xl border border-surface-container-high bg-surface-container-lowest p-6 shadow-sm">';
    echo '<span class="font-label-caps text-label-caps uppercase tracking-widest text-primary">' . esc_html($has && get_field('price_is_starting_from', $post_id) ? 'السعر يبدأ من' : 'السعر') . '</span>';
    if ($has) {
        echo '<span class="font-headline-sm text-headline-sm text-primary md:font-headline-lg md:text-headline-lg">' . esc_html(car_price_text($post_id)) . '</span>';
        echo '<span class="text-body-sm text-on-surface-variant">' . esc_html(get_field('vat_included', $post_id) ? 'شامل ضريبة القيمة المضافة' : 'غير شامل الضريبة') . '</span>';
    } else {
        echo '<span class="font-title-editorial text-title-editorial text-on-surface-variant">لا يوجد سعر منشور</span>';
    }
    $warranty = trim((string) get_field('warranty', $post_id));
    if ($warranty !== '') {
        echo '<div class="mt-2 text-body-base text-on-surface"><span class="font-semibold">الضمان:</span> ' . esc_html($warranty) . '</div>';
    }
    echo '</div>';
}

/**
 * Gallery images of a post: the featured image first, then the `gallery` field.
 * @return array<int, array{thumb: string, large: string, full: string}> (empty when there are no images)
 */
function car_gallery_images(int $post_id): array
{
    $ids = array_values(array_filter(array_map('intval', (array) get_field('gallery', $post_id, false))));
    $featured = (int) get_post_thumbnail_id($post_id);
    if ($featured && !in_array($featured, $ids, true)) {
        array_unshift($ids, $featured);
    }
    $out = [];
    foreach ($ids as $id) {
        $thumb = (string) wp_get_attachment_image_url($id, 'thumbnail');
        $large = (string) wp_get_attachment_image_url($id, 'large');
        $full = (string) wp_get_attachment_url($id);
        if ($large === '' && $full === '') {
            continue;
        }
        $out[] = ['thumb' => $thumb ?: $large ?: $full, 'large' => $large ?: $full, 'full' => $full ?: $large];
    }
    return $out;
}

/** Trim hero gallery: main image (placeholder when empty), counter, expand button, thumbnails; opened full screen by the JS. */
function car_gallery(int $post_id, string $title = ''): void
{
    $title = $title !== '' ? $title : get_the_title($post_id);
    $pics = car_gallery_images($post_id);
    $main = $pics[0]['large'] ?? car_placeholder_url();
    echo '<div class="car-gallery" data-car-gallery>';
    echo '<div class="car-gallery-main relative aspect-video overflow-hidden rounded-2xl bg-surface-container' . ($pics ? ' car-gallery-zoom' : '') . '"' . ($pics ? ' data-car-open title="عرض بالحجم الكامل"' : '') . '>';
    printf(
        '<img class="h-full w-full object-contain%s" src="%s" alt="%s" loading="eager" decoding="async" data-car-main%s>',
        $pics ? '' : ' car-img-placeholder',
        esc_url($main),
        esc_attr($title),
        $pics ? ' data-orig="' . esc_url($pics[0]['full']) . '"' : ''
    );
    if ($pics) {
        echo '<span class="car-gallery-count car-ltr" data-car-count>1 / ' . count($pics) . '</span>';
        echo '<button type="button" class="car-gallery-expand" data-car-open aria-label="عرض بالحجم الكامل"><i class="fas fa-expand" aria-hidden="true"></i></button>';
    }
    echo '</div>';
    if (count($pics) > 1) {
        echo '<div class="car-thumbs" data-car-thumbs>';
        foreach ($pics as $i => $p) {
            printf(
                '<button type="button" class="car-thumb%s" data-full="%s" data-orig="%s" data-index="%d" aria-label="%s"><img class="car-thumb-pic" src="%s" alt="%s" loading="lazy" decoding="async"></button>',
                $i === 0 ? ' is-active' : '',
                esc_url($p['large']),
                esc_url($p['full']),
                $i,
                esc_attr(sprintf('صورة %d من %d', $i + 1, count($pics))),
                esc_url($p['thumb']),
                esc_attr($title . ' - ' . ($i + 1))
            );
        }
        echo '</div><p class="mt-2 text-body-sm text-on-surface-variant">' . esc_html(n_images(count($pics))) . ' في المعرض - اضغط على الصورة للعرض بالحجم الكامل</p>';
    }
    echo '</div>';
}

// ---------------------------------------------------------------------------
// Price tables (sortable)
// ---------------------------------------------------------------------------

/** A price-table cell of a car: [number for sorting, text in its own currency] - see car_price_table(). */
function car_price_cell(int $post_id): array
{
    $price = get_field('price', $post_id);
    return [is_numeric($price) ? $price + 0 : null, car_price_text($post_id)];
}

/**
 * Sortable price table under a section header. $header[0] is the name column, the rest are price
 * cells: a number (printed as SAR) or a [number, text] pair from car_price_cell() (printed as given).
 *
 * @param string[] $header
 * @param array<int, array{0: string, 1: string, 2?: mixed}> $rows name, url ('' = plain text), then price cells
 */
function car_price_table(string $title, array $header, array $rows, string $eyebrow = 'قائمة الأسعار'): void
{
    if (!$rows) {
        return;
    }
    car_section_header($title, $eyebrow);
    echo '<div class="car-table-scroll overflow-hidden rounded-xl border border-surface-container-high bg-surface-container-lowest shadow-xs">';
    echo '<table class="car-price-table w-full text-start" data-car-sortable><thead><tr class="border-b border-surface-container-high bg-surface-container-low">';
    foreach ($header as $i => $h) {
        echo $i
            ? '<th class="car-sort cursor-pointer select-none whitespace-nowrap px-4 py-3 text-center font-semibold text-on-surface-variant" scope="col" data-sort="num" role="button" tabindex="0" aria-sort="none">' . esc_html($h) . ' <i class="fas fa-sort text-xs" aria-hidden="true"></i></th>'
            : '<th class="px-4 py-3 text-start font-semibold text-on-surface-variant" scope="col">' . esc_html($h) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $r) {
        [$name, $url] = $r;
        echo '<tr class="odd:bg-surface-container-low"><td class="px-4 py-3 font-semibold text-on-surface">'
            . ($url ? '<a class="transition-colors hover:text-primary" href="' . esc_url($url) . '">' . esc_html((string) $name) . '</a>' : esc_html((string) $name)) . '</td>';
        foreach (array_slice($r, 2) as $v) {
            [$num, $text] = is_array($v) ? [$v[0] ?? null, (string) ($v[1] ?? '')] : [$v, format_sar($v)];
            echo '<td class="px-4 py-3 text-center text-on-surface" data-v="' . esc_attr(is_numeric($num) ? (string) $num : '') . '">' . esc_html($text !== '' ? $text : '—') . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

/** Price table stored by the import on make / model terms (`_car_price_table`), names linked to our pages. */
function car_term_price_table(WP_Term $term, array $link_by_name = []): void
{
    $json = (string) get_term_meta($term->term_id, '_car_price_table', true);
    $data = $json ? json_decode($json, true) : null;
    if (empty($data['rows']) || !is_array($data['rows'])) {
        return;
    }
    $rows = [];
    foreach ($data['rows'] as $r) {
        if (!is_array($r) || trim((string) ($r['name'] ?? '')) === '') {
            continue;
        }
        $cells = array_key_exists('price_min', $r) ? [$r['price_min'] ?? null, $r['price_max'] ?? null] : [$r['price'] ?? null];
        $rows[] = array_merge([(string) $r['name'], (string) ($link_by_name[$r['name']] ?? '')], $cells);
    }
    if (!$rows || !array_filter(array_map(static fn($r) => array_filter(array_slice($r, 2), 'is_numeric'), $rows))) {
        return;
    }
    $header = !empty($data['header']) && is_array($data['header']) ? array_map('strval', $data['header']) : (count($rows[0]) > 3 ? ['الموديل', 'أقل سعر', 'أعلى سعر'] : ['الفئة', 'السعر']);
    car_price_table(!empty($data['heading']) ? (string) $data['heading'] : 'أحدث قائمة أسعار', $header, $rows);
}

// ---------------------------------------------------------------------------
// Siblings / rivals (from the source page, resolved to our own posts only)
// ---------------------------------------------------------------------------

/**
 * Sibling / rival posts of a car: the `_car_siblings` source links resolved to our own published
 * posts by `_car_path`, in one query (lookup_car_path() costs ~0.6s per call on the full postmeta
 * table) and cached for a day per data version. Links that do not resolve are dropped: never a link
 * to the source. Same-model posts are "trims", the rest "rivals"; the source order is kept.
 *
 * @return array{trims: int[], rivals: int[]}
 */
function car_sibling_ids(int $post_id): array
{
    $key = 'car_siblings_' . $post_id . '_' . car_directory_data_version();
    $cached = get_transient($key);
    if (is_array($cached) && isset($cached['trims'], $cached['rivals'])) {
        return $cached;
    }
    $out = ['trims' => [], 'rivals' => []];
    $siblings = json_decode((string) get_post_meta($post_id, '_car_siblings', true), true);
    $paths = [];
    foreach (is_array($siblings) ? $siblings : [] as $s) {
        $path = strtolower(trim(rawurldecode((string) wp_parse_url((string) ($s['url'] ?? ''), PHP_URL_PATH)), '/'));
        if ($path !== '') {
            $paths[$path] = true;
        }
    }
    if ($paths) {
        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($paths), '%s'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT m.meta_value AS path, p.ID FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
             WHERE m.meta_key = %s AND m.meta_value IN ({$placeholders}) AND p.post_status = 'publish' AND p.post_type IN ('car', 'car_model_year')",
            array_merge([CAR_PATH_META], array_keys($paths))
        ));
        $by_path = [];
        foreach ($rows as $r) {
            $by_path[$r->path] = (int) $r->ID;
        }
        $ids = array_values(array_unique(array_filter(array_map(static fn($p) => $by_path[$p] ?? 0, array_keys($paths)), static fn($id) => $id && $id !== $post_id)));
        if ($ids) {
            get_posts(['post_type' => ['car', 'car_model_year'], 'post__in' => $ids, 'posts_per_page' => -1, 'no_found_rows' => true]); // prime post, meta and term caches
            $model = car_post_terms($post_id)['model'];
            foreach ($ids as $id) {
                $out[$model && has_term($model->term_id, 'car_make', $id) ? 'trims' : 'rivals'][] = $id;
            }
        }
    }
    set_transient($key, $out, DAY_IN_SECONDS);
    return $out;
}

/**
 * Tiles of car_sibling_ids(): listing tiles for trims, post tiles for model-year pages.
 *
 * @return array{trims: array<int, string>, rivals: array<int, string>} post id => tile html
 */
function car_sibling_tiles(int $post_id): array
{
    $groups = ['trims' => [], 'rivals' => []];
    $ids = car_sibling_ids($post_id);
    $all = array_merge($ids['trims'], $ids['rivals']);
    if ($all) {
        get_posts(['post_type' => ['car', 'car_model_year'], 'post__in' => $all, 'posts_per_page' => -1, 'no_found_rows' => true]); // prime the caches
    }
    foreach ($ids as $k => $list) {
        foreach ($list as $id) {
            $post = get_post($id);
            if ($post instanceof WP_Post && $post->post_status === 'publish') {
                $groups[$k][$id] = $post->post_type === 'car' ? car_listing_tile($post) : car_post_tile($post);
            }
        }
    }
    return $groups;
}

/** "فئات أخرى من <model>" and "سيارات منافسة" sections (header + wide grid), only when there is something to show. */
function car_siblings(int $post_id): void
{
    $groups = car_sibling_tiles($post_id);
    if (!$groups['trims'] && !$groups['rivals']) {
        return;
    }
    $model = car_post_terms($post_id)['model'];
    $labels = ['trims' => 'فئات أخرى من ' . ($model ? $model->name : 'الموديل'), 'rivals' => 'سيارات منافسة'];
    foreach ($groups as $k => $tiles) {
        if (!$tiles) {
            continue;
        }
        echo '<div class="car-siblings car-siblings-' . esc_attr($k) . ' mt-space-xl">';
        car_section_header($labels[$k], 'قد يهمك أيضًا', n_cars(count($tiles)));
        car_grid(array_values($tiles), 'wide');
        echo '</div>';
    }
}
