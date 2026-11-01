<?php
/**
 * Origin (/car-directory/<origin>/, e.g. japanese-cars): the imported description, then its makes as a logo grid.
 */

get_header();

$origin = get_queried_object();
$origin = $origin instanceof WP_Term ? $origin : null;
$makes = $origin ? car_origin_makes($origin) : [];
?>
<main id="primary" class="site-main car-directory">
    <?php
    car_section_open();
    if ($origin) {
        car_breadcrumbs([[$origin->name, '']]);
        car_title_row($origin->name, [], car_directory_taxonomies()['car_origin'][0]);
        car_description($origin->description);
        /* translators: %s: origin (country) */
        car_section_header(sprintf(__('Choose from %s', 'hello-elementor-child'), $origin->name), __('Brands', 'hello-elementor-child'), $makes ? n_makes(count($makes)) : '');
        car_grid(
            array_map(static fn(WP_Term $make) => car_term_tile($make, 'car-tile-logo'), $makes),
            'logos',
            __('This origin has no brands yet.', 'hello-elementor-child')
        );
    } else {
        car_breadcrumbs([[car_directory_taxonomies()['car_origin'][0], '']]);
        car_empty_state(__('This origin does not exist.', 'hello-elementor-child'));
    }
    car_section_close();
    ?>
</main>
<?php
get_footer();
