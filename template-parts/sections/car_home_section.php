<?php
/**
 * Flexible Content: Car directory home (the staging front page as one Layout Engine row).
 *
 * Four switchable blocks: price ranges (budget), body types, the most-listed makes, then every
 * origin as a flag tile (its page lists the makes). Built from the car directory helpers (inc/cars/helpers.php:
 * tiles, strips, grids, section header; inc/cars/directory.php: price ranges, body-type order, URLs).
 * Offered on the front page and on every department; skipped on entity term archives
 * (get_term_archive_skip_layouts()). The wrapper carries `car-directory`: the component CSS of the
 * tiles and grids is scoped to it (no script is needed).
 */

if (!function_exists('car_section_header') || !function_exists('car_price_ranges')) {
    return;
}

$section = $args['section'] ?? [];

if (!empty($section)) {
    $section_title = $section['section_title'] ?? '';
    $subtitle = $section['subtitle'] ?? '';
    $show_budget = $section['show_budget'] ?? null;
    $show_body_types = $section['show_body_types'] ?? null;
    $show_brands = $section['show_brands'] ?? null;
    $brands_limit = $section['brands_limit'] ?? 15;
    $show_origins = $section['show_origins'] ?? null;
} else {
    $section_title = get_sub_field('section_title');
    $subtitle = get_sub_field('subtitle');
    $show_budget = get_sub_field('show_budget');
    $show_body_types = get_sub_field('show_body_types');
    $show_brands = get_sub_field('show_brands');
    $brands_limit = get_sub_field('brands_limit');
    $show_origins = get_sub_field('show_origins');
}

// A toggle that was never saved (row older than the field) is on, like its default.
$is_on = static fn($value): bool => $value === null || (bool) $value;

$section_title = trim((string) $section_title) ?: __('Car Directory', 'hello-elementor-child');
$subtitle = trim((string) $subtitle);
$brands_limit = max(1, (int) $brands_limit ?: 15);
$show_budget = $is_on($show_budget);
$show_body_types = $is_on($show_body_types);
$show_brands = $is_on($show_brands);
$show_origins = $is_on($show_origins);

$archive_url = car_directory_url();

// Most-listed makes (published cars, desc) for the brands strip.
$top_makes = [];
if ($show_brands) {
    $makes = get_terms(['taxonomy' => 'car_make', 'parent' => 0, 'hide_empty' => true]);
    $top_makes = is_wp_error($makes) ? [] : array_slice(car_sort_by_count(car_visible_terms($makes)), 0, $brands_limit);
}

// Every origin that has makes, most cars first: [WP_Term $origin, int $makes] (also a section of its own: car_origins_section).
// Their total is the brand count of the "all brands" tile and of the all-brands page it opens (car-brands.php).
$all_origins = $show_brands || $show_origins ? car_origins_with_makes() : [];
$brands_total = array_sum(array_column($all_origins, 1));
$origin_blocks = $show_origins ? $all_origins : [];

if (!$show_budget && !$show_body_types && !$top_makes && !$origin_blocks) {
    return;
}

// Section header recipe (eyebrow + headline): the title is the eyebrow when a subtitle gives the headline.
$headline = $subtitle !== '' ? $subtitle : $section_title;
$eyebrow = $subtitle !== '' ? $section_title : '';

/** Block header: h3 + optional count pill + optional link (the section header one level down). */
$block_header = static function (string $title, string $count = '', string $more_url = '', string $more_label = ''): void {
    echo '<div class="car-home-block-header mb-4 flex flex-wrap items-center justify-between gap-space-sm">';
    echo '<div class="flex flex-wrap items-center gap-space-sm">';
    echo '<h3 class="font-headline-sm text-headline-sm text-on-background">' . esc_html($title) . '</h3>';
    if ($count !== '') {
        echo '<span class="rounded-full bg-surface-container px-space-sm py-0.5 font-label-caps text-label-caps text-on-surface-variant">' . esc_html($count) . '</span>';
    }
    echo '</div>';
    if ($more_url !== '') {
        echo '<a href="' . esc_url($more_url) . '" class="inline-flex items-center gap-1.5 text-base font-medium text-primary transition-opacity hover:opacity-70">'
            . '<span>' . esc_html($more_label) . '</span><i class="fas fa-angle-left" aria-hidden="true"></i></a>';
    }
    echo '</div>';
};

$block_gap = ''; // mt-space-xl from the second visible block on
?>

<div class="car-directory car-home" id="car-home">
    <?php car_section_open('car-home-section'); ?>

    <?php if (empty($section['hide_header'])) { // the car archive shows the filter's header above instead
        car_section_header($headline, $eyebrow, n_cars(car_total_count()), $archive_url, __('All cars', 'hello-elementor-child'));
    } ?>

    <?php if ($show_budget): ?>
        <div class="car-home-block<?php echo esc_attr($block_gap); ?>">
            <?php
            $block_header(__('Shop by budget', 'hello-elementor-child'));
            car_grid(car_price_range_tiles(), 'cols-9'); // filters are grids, never sliders
            $block_gap = ' mt-space-xl';
            ?>
        </div>
    <?php endif; ?>

    <?php if ($show_body_types): ?>
        <div class="car-home-block<?php echo esc_attr($block_gap); ?>">
            <?php
            $block_header(__('Choose your car by shape', 'hello-elementor-child'));
            car_grid(car_body_type_tiles(true), 'cols-6');
            $block_gap = ' mt-space-xl';
            ?>
        </div>
    <?php endif; ?>

    <?php if ($top_makes): ?>
        <div class="car-home-block<?php echo esc_attr($block_gap); ?>">
            <?php
            $block_header(__('Choose your brand to follow its news and cars', 'hello-elementor-child'));
            $tiles = array_map('car_make_tile', $top_makes);
            // "all brands" (same frameless tile as the makes) opens the all-brands page: every make, grouped by origin
            $tiles[] = car_tile(
                car_brands_url(),
                '<span class="flex size-12 items-center justify-center rounded-full bg-surface-container text-xl text-primary transition-colors group-hover:bg-primary group-hover:text-on-primary"><i class="fas fa-table-cells-large" aria-hidden="true"></i></span>',
                __('All brands', 'hello-elementor-child'),
                n_makes($brands_total),
                '',
                'car-tile-logo'
            );
            car_grid($tiles, 'logos');
            $block_gap = ' mt-space-xl';
            ?>
        </div>
    <?php endif; ?>

    <?php if ($origin_blocks): ?>
        <div class="car-home-block<?php echo esc_attr($block_gap); ?>">
            <?php
            $block_header(__('All brands by country of origin', 'hello-elementor-child'), n_makes($brands_total));
            // one flag per origin; its page lists the makes
            car_grid(array_map(static fn(array $block): string => car_origin_tile($block[0], $block[1]), $origin_blocks));
            ?>
        </div>
    <?php endif; ?>

    <?php if (!is_post_type_archive('car')) { // departments show no car list: this opens the directory
        car_more_cars_button();
    } ?>

    <?php car_section_close(); ?>
</div>
