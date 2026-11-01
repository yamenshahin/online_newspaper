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
        car_empty_state(__('This body type does not exist.', 'hello-elementor-child'));
        car_section_close();
        get_footer();
        return;
    }
    car_breadcrumbs([[$tax_label, ''], [$term->name, '']]);
    /* translators: %s: body type */
    car_title_row(sprintf(__('New %s cars', 'hello-elementor-child'), $term->name), [], __('Body type', 'hello-elementor-child'));
    car_description($term->description);
    if ($cars) {
        echo '<p class="mb-6 text-body-base text-on-surface-variant">'
            /* translators: 1: number of models ("12 models"), 2: body type, 3: last year, 4: this year */
            . esc_html(sprintf(__('%1$s in the %2$s class, %3$d and %4$d models, at the lowest price per model.', 'hello-elementor-child'), n_models(count($cars)), $term->name, $year - 1, $year)) . '</p>';
    }
    car_section_header(__('Available models', 'hello-elementor-child'), __('Lowest price per model', 'hello-elementor-child'), $cars ? n_models(count($cars)) : '');
    get_template_part('template-parts/cars/model-listing', null, [
        'cars' => $cars,
        'base_url' => car_term_url($term),
        'paged' => $paged,
        'per_page' => 24,
        /* translators: 1: body type, 2: last year, 3: this year */
        'empty' => $cars ? __('No cars on this page.', 'hello-elementor-child') : sprintf(__('No %1$s cars with %2$d and %3$d models at the moment.', 'hello-elementor-child'), $term->name, $year - 1, $year),
    ]);
    car_section_close();

    car_section_open('pt-0');
    car_section_header(__('Other shapes', 'hello-elementor-child'), $tax_label);
    car_strip(car_body_type_tiles(true, $term->slug), true);
    car_section_close();
    ?>
</main>
<?php
get_footer();
