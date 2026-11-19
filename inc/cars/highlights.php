<?php
/**
 * Cars of the week section (template-parts/sections/car_highlights_section.php): a few cars of the recent
 * model years, one per make, picked once a week.
 *
 * The pick is random but seeded with the week, so every page (and every cached copy of a page) shows the same
 * cars until the week turns. Cached for a week per data version, like the other car lists (inc/cars/helpers.php).
 */

if (!defined('ABSPATH')) {
    exit;
}

const CAR_HIGHLIGHTS_COUNT = 4;

/** The week a pick belongs to ("2026-47"). Weeks turn on Sunday, the first day of the Saudi week, at midnight in Riyadh. */
function get_car_highlights_week(): string
{
    return wp_date('o-W', time() + DAY_IN_SECONDS, new DateTimeZone('Asia/Riyadh'));
}

/**
 * IDs of the week's cars: published cars of this model year or newer (last year's too while the new ones are
 * still few), in the week's random order, one car per make. Cars with a photo and a price in riyals are
 * preferred when enough of them have one.
 *
 * @return int[]
 */
function get_car_highlight_ids(int $count = CAR_HIGHLIGHTS_COUNT): array
{
    $count = max(1, $count);
    $week = get_car_highlights_week();
    $cache_key = 'car_highlights_' . md5($week . '|' . $count . '|' . car_directory_data_version());
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    global $wpdb;
    // Key -> value maps filtered in PHP, as car_cheapest_per_model_ids() does: meta joins over ~5,000 cars cost seconds.
    $published_cars = "JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_type = 'car' AND p.post_status = 'publish'";

    $years = [];
    foreach ($wpdb->get_results("SELECT m.post_id, m.meta_value FROM {$wpdb->postmeta} m {$published_cars} AND m.meta_key = 'model_year' AND m.meta_value <> ''", ARRAY_N) as [$id, $year]) {
        if (is_numeric($year)) {
            $years[(int) $id] = (int) $year;
        }
    }

    $ids = [];
    $this_year = (int) wp_date('Y', null, new DateTimeZone('Asia/Riyadh'));
    foreach ([$this_year, $this_year - 1, 0] as $min_year) {
        $ids = array_keys(array_filter($years, static fn(int $year): bool => $year >= $min_year));
        if (count($ids) >= $count * 5) {
            break;
        }
    }
    if (!$ids) {
        return [];
    }

    $with_photo = array_map('intval', $wpdb->get_col(
        "SELECT m.post_id FROM {$wpdb->postmeta} m {$published_cars}
         AND ((m.meta_key = '_thumbnail_id' AND m.meta_value NOT IN ('', '0')) OR (m.meta_key = 'gallery' AND m.meta_value NOT IN ('', 'a:0:{}')))"
    ));
    $priced = [];
    foreach ($wpdb->get_results("SELECT m.post_id, m.meta_value FROM {$wpdb->postmeta} m {$published_cars} AND m.meta_key = 'price' AND m.meta_value <> ''", ARRAY_N) as [$id, $price]) {
        if (is_numeric($price) && $price >= 1000) { // no import glitches like "1"
            $priced[] = (int) $id;
        }
    }
    $priced = array_diff($priced, car_usd_ids()); // riyal prices only, as on the budget pages
    foreach ([$with_photo, $priced] as $preferred) {
        $narrowed = array_values(array_intersect($ids, $preferred));
        if (count($narrowed) >= $count * 3) {
            $ids = $narrowed;
        }
    }

    // The week's order: the same all week, another one next week.
    $rank = [];
    foreach ($ids as $id) {
        $rank[$id] = md5($week . '|' . $id);
    }
    asort($rank, SORT_STRING);
    $ordered = array_keys($rank);

    $make_of = [];
    foreach (array_chunk($ordered, 1000) as $chunk) {
        foreach ($wpdb->get_results("SELECT tr.object_id, tt.term_id FROM {$wpdb->term_relationships} tr
            JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
            WHERE tt.taxonomy = 'car_make' AND tt.parent = 0 AND tr.object_id IN (" . implode(',', array_map('intval', $chunk)) . ')') as $row) {
            $make_of[(int) $row->object_id] = (int) $row->term_id;
        }
    }

    $picked = [];
    $makes = [];
    foreach ($ordered as $id) {
        $make = $make_of[$id] ?? 0;
        if ($make && isset($makes[$make])) {
            continue;
        }
        if ($make) {
            $makes[$make] = true;
        }
        $picked[] = $id;
        if (count($picked) === $count) {
            break;
        }
    }
    foreach ($ordered as $id) { // fewer makes than cards: the next cars of the week's order
        if (count($picked) >= $count) {
            break;
        }
        if (!in_array($id, $picked, true)) {
            $picked[] = $id;
        }
    }

    set_transient($cache_key, $picked, WEEK_IN_SECONDS);
    return $picked;
}

/** The week's cars as posts, in the pick's order. @return WP_Post[] */
function get_car_highlights(int $count = CAR_HIGHLIGHTS_COUNT): array
{
    $ids = get_car_highlight_ids($count);
    if (!$ids) {
        return [];
    }
    return get_posts([
        'post_type' => 'car',
        'post_status' => 'publish',
        'post__in' => $ids,
        'orderby' => 'post__in',
        'posts_per_page' => count($ids),
        'no_found_rows' => true,
    ]);
}

/**
 * Card of a highlighted car: photo with the model year, make, "model trim", three quick specs and the price.
 * Escaped here, like the other car builders (inc/cars/helpers.php).
 */
function car_highlight_card(WP_Post $car): string
{
    $terms = car_post_terms($car->ID);
    $make = $terms['make'] ? $terms['make']->name : '';
    $year = trim((string) get_field('model_year', $car->ID));
    // The title from the model name on, without the year (it is on the photo): the trim_name field alone
    // leaves out the sub-family ("جولف GTI Pack 0", not "جولف Pack 0").
    $title = get_the_title($car);
    $position = $terms['model'] ? mb_strpos($title, $terms['model']->name) : false;
    $name = $position === false ? $title : mb_substr($title, $position);
    if ($year !== '') {
        $name = trim((string) preg_replace('/s+/u', ' ', (string) preg_replace('/(?<!d)' . preg_quote($year, '/') . '(?!d)/u', '', $name))) ?: $title;
    }

    $specs = [];
    $power = get_post_meta($car->ID, 'engine_hp', true);
    if (is_numeric($power) && $power > 0) {
        $specs[] = format_number($power) . ' ' . __('hp', 'hello-elementor-child');
    }
    foreach (['fuel_type', 'body_type'] as $taxonomy) {
        $spec_terms = get_the_terms($car->ID, $taxonomy);
        if (!empty($spec_terms) && !is_wp_error($spec_terms)) {
            $specs[] = $spec_terms[0]->name;
        }
    }

    $image = car_card_image_url($car->ID);
    $price = car_filter_price_text($car->ID);

    return sprintf(
        '<a class="car-week-card group flex flex-col overflow-hidden rounded-2xl border border-surface-container-high bg-surface-container-lowest shadow-sm transition-all hover:border-primary hover:shadow-lg" href="%s">'
        . '<span class="relative block aspect-video overflow-hidden bg-surface-container">%s%s</span>'
        . '<span class="flex flex-1 flex-col gap-1 p-space-md text-start">'
        . '%s<span class="font-title-editorial text-title-editorial text-on-background transition-colors group-hover:text-primary">%s</span>%s'
        . '<span class="mt-auto pt-space-sm font-semibold %s">%s</span>'
        . '</span></a>',
        esc_url(get_permalink($car)),
        car_img($image, get_the_title($car), $image !== '' ? 'h-full w-full object-cover transition-transform duration-500 group-hover:scale-105' : 'h-full w-full p-6'),
        $year !== '' ? '<span class="absolute start-2 top-2 rounded-full bg-inverse-surface/85 px-2.5 py-0.5 text-xs font-bold text-inverse-on-surface">' . esc_html($year) . '</span>' : '',
        $make !== '' ? '<span class="font-label-caps text-label-caps uppercase tracking-widest text-primary">' . esc_html($make) . '</span>' : '',
        esc_html($name),
        $specs ? '<span class="text-body-sm text-on-surface-variant">' . esc_html(implode(' · ', $specs)) . '</span>' : '',
        $price !== '' ? 'text-primary' : 'text-on-surface-variant',
        esc_html($price !== '' ? $price : __('No price', 'hello-elementor-child'))
    );
}
