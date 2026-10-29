<?php
/**
 * Flexible Content: Car directory home (the staging front page as one Layout Engine row).
 *
 * Four switchable blocks: price ranges (budget), body types, the most-listed makes, then every
 * origin with its makes as logo tiles. Built from the car directory helpers (inc/cars/helpers.php:
 * tiles, strips, grids, section header; inc/cars/directory.php: price ranges, body-type order, URLs).
 * Offered on the front page and on every department; skipped on entity term archives
 * (get_term_archive_skip_layouts()). The wrapper carries `car-directory`: the component CSS of the
 * strips and tiles is scoped to it, and car-directory.js (strip arrows) is enqueued from here.
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

$section_title = trim((string) $section_title) ?: __('دليل السيارات', 'hello-elementor-child');
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
    $top_makes = is_wp_error($makes) ? [] : array_slice(car_sort_by_count($makes), 0, $brands_limit);
}

// Every origin that has makes, most cars first: [WP_Term $origin, WP_Term[] $makes].
$origin_blocks = [];
$origin_makes_total = 0;
if ($show_origins) {
    // same order as the staging home page (term count, desc): سيارات يابانية first
    $origins = get_terms(['taxonomy' => 'car_origin', 'hide_empty' => false, 'orderby' => 'count', 'order' => 'DESC']);
    foreach (is_wp_error($origins) ? [] : $origins as $origin) {
        $makes = car_origin_makes($origin);
        if ($makes) {
            $origin_blocks[] = [$origin, $makes];
            $origin_makes_total += count($makes);
        }
    }
}

if (!$show_budget && !$show_body_types && !$top_makes && !$origin_blocks) {
    return;
}

// Strip arrows live in car-directory.js, which functions.php only enqueues on car screens.
$script_path = get_stylesheet_directory() . '/assets/js/car-directory.js';
if (file_exists($script_path) && !wp_script_is('hello-elementor-child-car-directory', 'enqueued')) {
    wp_enqueue_script(
        'hello-elementor-child-car-directory',
        get_stylesheet_directory_uri() . '/assets/js/car-directory.js',
        [],
        filemtime($script_path),
        true
    );
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

/** Frameless logo tile of a make, its car count under the name (placeholder until the logos are uploaded). */
$make_tile = static function (WP_Term $make): string {
    $count = car_term_count($make);
    return car_tile(
        car_term_url($make),
        car_img(car_term_image_url($make), $make->name, 'car-tile-pic'),
        $make->name,
        $count > 0 ? n_cars($count) : '',
        '',
        'car-tile-logo'
    );
};

$block_gap = ''; // mt-space-xl from the second visible block on
?>

<div class="car-directory car-home" id="car-home">
    <?php car_section_open('car-home-section'); ?>

    <?php if (empty($section['hide_header'])) { // the car archive shows the filter's header above instead
        car_section_header($headline, $eyebrow, n_cars(car_total_count()), $archive_url, __('كل السيارات', 'hello-elementor-child'));
    } ?>

    <?php if ($show_budget): ?>
        <div class="car-home-block<?php echo esc_attr($block_gap); ?>">
            <?php
            $block_header(__('سيارات تناسب ميزانيتك', 'hello-elementor-child'));
            car_grid(car_price_range_tiles(), 'wide'); // filters are grids, never sliders
            $block_gap = ' mt-space-xl';
            ?>
        </div>
    <?php endif; ?>

    <?php if ($show_body_types): ?>
        <div class="car-home-block<?php echo esc_attr($block_gap); ?>">
            <?php
            $block_header(__('اختر سيارتك حسب الشكل', 'hello-elementor-child'));
            car_grid(car_body_type_tiles(true), 'wide');
            $block_gap = ' mt-space-xl';
            ?>
        </div>
    <?php endif; ?>

    <?php if ($top_makes): ?>
        <div class="car-home-block<?php echo esc_attr($block_gap); ?>">
            <?php
            $block_header(__('اختر علامتك لمتابعة أخبارها وسياراتها', 'hello-elementor-child'));
            $tiles = array_map($make_tile, $top_makes);
            // "all brands" jumps to the first origin group below (staging: #all-brands)
            $first_origin = $origin_blocks ? 'car-origin-' . $origin_blocks[0][0]->slug : '';
            $tiles[] = car_text_tile($first_origin ? '#' . $first_origin : $archive_url, 'كل<br>العلامات');
            car_grid($tiles, 'logos');
            $block_gap = ' mt-space-xl';
            ?>
        </div>
    <?php endif; ?>

    <?php if ($origin_blocks): ?>
        <div class="car-home-block<?php echo esc_attr($block_gap); ?>" id="car-home-origins">
            <?php $block_header(__('كل العلامات حسب بلد المنشأ', 'hello-elementor-child'), n_makes($origin_makes_total)); ?>

            <?php foreach ($origin_blocks as [$origin, $makes]): ?>
                <div class="car-home-origin mt-space-lg scroll-mt-6" id="<?php echo esc_attr('car-origin-' . $origin->slug); ?>">
                    <div class="mb-4 flex flex-wrap items-center gap-space-sm">
                        <h4 class="font-title-editorial text-title-editorial text-on-background">
                            <a class="inline-flex items-center gap-space-sm transition-colors hover:text-primary" href="<?php echo esc_url(car_term_url($origin)); ?>">
                                <i class="fas fa-car text-primary" aria-hidden="true"></i>
                                <span><?php echo esc_html($origin->name); ?></span>
                            </a>
                        </h4>
                        <?php
                        echo car_pill(n_makes(count($makes))); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper
                        echo car_pill(n_cars(car_term_count($origin)), car_term_url($origin), 'outline'); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper
                        ?>
                    </div>
                    <?php car_grid(array_map($make_tile, $makes), 'logos'); ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php car_section_close(); ?>
</div>
