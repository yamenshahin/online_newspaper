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
$title = $label !== '' ? 'سيارات جديدة ' . $label . ' ريال سعودي' : 'سيارات تناسب ميزانيتك';
$page_url = car_price_range_url($slug) . ($paged > 1 ? "page/{$paged}/" : '');

// the main query is the car archive: give the page its own document title and canonical URL, and drop the
// archive's rel prev / next links. Rank Math computed the archive's canonical before this template loaded,
// so its Paper singleton is reset to rebuild it through the filters above.
add_filter('pre_get_document_title', static fn() => $title . ($paged > 1 ? " - الصفحة {$paged}" : '') . ' - ' . get_bloginfo('name'), 999);
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
        car_breadcrumbs([['سيارات تناسب ميزانيتك', '']]);
        car_empty_state('هذه الفئة السعرية غير موجودة.');
        car_section_close();
        get_footer();
        return;
    }
    car_breadcrumbs([['سيارات تناسب ميزانيتك', ''], [$label, '']]);
    car_title_row($title, [], 'الفئة السعرية');
    if ($cars) {
        echo '<p class="mb-6 text-body-base text-on-surface-variant">'
            . esc_html(sprintf('هل ترغب في شراء سيارة جديدة موديل %d أو %d بميزانية %s ريال سعودي؟ إليك %s بأقل سعر لكل موديل.', $year - 1, $year, $label, n_models(count($cars)))) . '</p>';
    }
    car_section_header('الموديلات المتوفرة', 'أقل سعر لكل موديل', $cars ? n_models(count($cars)) : '');
    get_template_part('template-parts/cars/model-listing', null, [
        'cars' => $cars,
        'base_url' => car_price_range_url($slug),
        'paged' => $paged,
        'per_page' => 24,
        'empty' => $cars ? 'لا توجد سيارات في هذه الصفحة.' : 'لا توجد سيارات في هذه الفئة السعرية حالياً.',
    ]);
    car_section_close();

    car_section_open('pt-0');
    car_section_header('فئات سعرية أخرى', 'سيارات تناسب ميزانيتك');
    car_strip(car_price_range_tiles($slug), true);
    car_section_close();
    ?>
</main>
<?php
get_footer();
