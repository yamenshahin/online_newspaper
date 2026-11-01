<?php
/**
 * Price range (/car-directory/prices/<range>/): a virtual page served by inc/cars/directory.php for the
 * `car_price` query var (the main query is the car archive). One listing tile per model - its cheapest
 * current trim inside the range (SAR, model year >= this year - 1) - 24 per page, then the other ranges.
 */

$slug = sanitize_key((string) get_query_var('car_price'));
$ranges = car_price_ranges();
[$label, $min, $max] = $ranges[$slug] ?? ['', 0, 0];
$paged = max(1, (int) get_query_var('paged'));
$year = (int) wp_date('Y');
$budget = __('Cars for your budget', 'hello-elementor-child');
/* translators: %s: price range ("50 ألف إلى 100 ألف") */
$title = $label !== '' ? sprintf(__('New cars %s Saudi riyals', 'hello-elementor-child'), $label) : $budget;
$page_url = car_price_range_url($slug) . ($paged > 1 ? "page/{$paged}/" : '');

// the main query is the car archive: give the page its own document title and canonical URL, and drop the
// archive's rel prev / next links. Rank Math computed the archive's canonical before this template loaded,
// so its Paper singleton is reset to rebuild it through the filters above.
add_filter('pre_get_document_title', static fn() => $title . ($paged > 1 ? ' - ' . sprintf(__('Page %d', 'hello-elementor-child'), $paged) : '') . ' - ' . get_bloginfo('name'), 999);
add_filter('rank_math/frontend/canonical', static fn() => $page_url, 999);
add_filter('rank_math/frontend/disable_adjacent_rel_links', '__return_true', 999);
if (class_exists('\RankMath\Paper\Paper') && method_exists('\RankMath\Paper\Paper', 'reset')) {
    \RankMath\Paper\Paper::reset();
}

get_header();

$cars = $label !== '' ? car_cheapest_per_model(['price_min' => $min, 'price_max' => $max]) : [];
?>
<main id="primary" class="site-main car-directory">
    <?php
    car_section_open();
    if ($label === '') {
        car_breadcrumbs([[$budget, '']]);
        car_empty_state(__('This price range does not exist.', 'hello-elementor-child'));
        car_section_close();
        get_footer();
        return;
    }
    car_breadcrumbs([[$budget, ''], [$label, '']]);
    car_title_row($title, [], __('Price range', 'hello-elementor-child'));
    if ($cars) {
        echo '<p class="mb-6 text-body-base text-on-surface-variant">'
            /* translators: 1: last year, 2: this year, 3: price range, 4: number of models ("12 models") */
            . esc_html(sprintf(__('Looking to buy a new %1$d or %2$d model car with a budget of %3$s Saudi riyals? Here are %4$s at the lowest price per model.', 'hello-elementor-child'), $year - 1, $year, $label, n_models(count($cars)))) . '</p>';
    }
    car_section_header(__('Available models', 'hello-elementor-child'), __('Lowest price per model', 'hello-elementor-child'), $cars ? n_models(count($cars)) : '');
    get_template_part('template-parts/cars/model-listing', null, [
        'cars' => $cars,
        'base_url' => car_price_range_url($slug),
        'paged' => $paged,
        'per_page' => 24,
        'empty' => $cars ? __('No cars on this page.', 'hello-elementor-child') : __('No cars in this price range at the moment.', 'hello-elementor-child'),
    ]);
    car_section_close();

    car_section_open('pt-0');
    car_section_header(__('Other price ranges', 'hello-elementor-child'), $budget);
    car_strip(car_price_range_tiles($slug), true);
    car_section_close();
    ?>
</main>
<?php
get_footer();
