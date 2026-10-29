<?php
/**
 * Body type (/car-directory/car-types/<body>/): one listing tile per model - its cheapest current trim of
 * this body type (model year >= this year - 1) - 24 per page, then the other body types as a strip.
 */

get_header();

$term = get_queried_object();
$term = $term instanceof WP_Term ? $term : null;
$paged = max(1, (int) get_query_var('paged'));
$year = (int) wp_date('Y');
$cars = $term ? car_cheapest_per_model(['tax_query' => [['taxonomy' => 'body_type', 'terms' => $term->term_id]]]) : [];
$tax_label = car_directory_taxonomies()['body_type'][0];
?>
<main id="primary" class="site-main car-directory">
    <?php
    car_section_open();
    if (!$term) {
        car_breadcrumbs([[$tax_label, '']]);
        car_empty_state('نوع الهيكل هذا غير موجود.');
        car_section_close();
        get_footer();
        return;
    }
    car_breadcrumbs([[$tax_label, ''], [$term->name, '']]);
    car_title_row('سيارات ' . $term->name . ' جديدة', [], 'نوع الهيكل');
    car_description($term->description);
    if ($cars) {
        echo '<p class="mb-6 text-body-base text-on-surface-variant">'
            . esc_html(sprintf('%s من فئة %s بموديلات %d و%d، بأقل سعر لكل موديل.', n_models(count($cars)), $term->name, $year - 1, $year)) . '</p>';
    }
    car_section_header('الموديلات المتوفرة', 'أقل سعر لكل موديل', $cars ? n_models(count($cars)) : '');
    get_template_part('template-parts/cars/model-listing', null, [
        'cars' => $cars,
        'base_url' => car_term_url($term),
        'paged' => $paged,
        'per_page' => 24,
        'empty' => $cars ? 'لا توجد سيارات في هذه الصفحة.' : 'لا توجد سيارات ' . $term->name . ' بموديلات ' . ($year - 1) . ' و' . $year . ' حالياً.',
    ]);
    car_section_close();

    car_section_open('pt-0');
    car_section_header('أشكال أخرى', $tax_label);
    car_strip(car_body_type_tiles(true, $term->slug), true);
    car_section_close();
    ?>
</main>
<?php
get_footer();
