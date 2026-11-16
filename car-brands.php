<?php
/**
 * All brands (/car-directory/brands/): a virtual page served by inc/cars/directory.php for the `car_brands` query var
 * (the main query is the car archive). Every make, grouped by origin: one heading per origin (flag, name linked to the
 * origin page, number of brands), then its makes as logo tiles with their car counts. Origins most cars first, like the
 * origin flags of the car directory home; the «كل العلامات» tile there opens this page.
 */

$title = __('All car brands by country of origin', 'hello-elementor-child');

// the main query is the car archive: give the page its own document title and canonical URL, and drop the
// archive's rel prev / next links (as price-range.php does; Rank Math's Paper is reset to rebuild the canonical).
add_filter('pre_get_document_title', static fn() => $title . ' - ' . get_bloginfo('name'), 999);
add_filter('rank_math/frontend/canonical', static fn() => car_brands_url(), 999);
add_filter('rank_math/frontend/disable_adjacent_rel_links', '__return_true', 999);
if (class_exists('\RankMath\Paper\Paper') && method_exists('\RankMath\Paper\Paper', 'reset')) {
    \RankMath\Paper\Paper::reset();
}

get_header();

$origins = car_origins_with_makes();
?>
<main id="primary" class="site-main car-directory">
    <?php
    car_section_open();
    car_breadcrumbs([[__('All brands', 'hello-elementor-child'), '']]);
    car_title_row($title, [], __('Car Directory', 'hello-elementor-child'));

    if (!$origins) {
        car_empty_state(__('No brands yet.', 'hello-elementor-child'));
    }
    ?>
    <div class="flex flex-col gap-space-xl">
        <?php foreach ($origins as [$origin, $count]): ?>
            <div class="car-brands-origin">
                <div class="mb-4 flex flex-wrap items-center gap-space-sm">
                    <?php echo car_origin_flag($origin, true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped by the helper ?>
                    <h2 class="font-headline-sm text-headline-sm text-on-background">
                        <a href="<?php echo esc_url(car_term_url($origin)); ?>" class="transition-colors hover:text-primary"><?php echo esc_html($origin->name); ?></a>
                    </h2>
                    <span
                        class="rounded-full bg-surface-container px-space-sm py-0.5 font-label-caps text-label-caps text-on-surface-variant"><?php echo esc_html(n_makes($count)); ?></span>
                </div>
                <?php car_grid(array_map('car_make_tile', car_origin_makes($origin)), 'logos'); ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php car_section_close(); ?>
</main>
<?php
get_footer();
