<?php
/**
 * Car post type archive (/car-directory/): the filter page.
 */
get_header();
?>
<main class="site-main bg-white pb-24">
    <?php
    get_template_part(
        'template-parts/sections/cars_section',
        null,
        [
            'section' => [
                'section_title' => __('دليل السيارات', 'hello-elementor-child'),
                'subtitle' => __('ابحث عن سيارتك بحسب الشركة والموديل والسنة والسعر والمواصفات', 'hello-elementor-child'),
                'posts_limit' => CAR_FILTER_PER_PAGE,
            ],
        ]
    );
    // under the filter: budget, body types, brands, origins (same layout as on the cars department)
    get_template_part('template-parts/sections/car_home_section', null, ['section' => ['section_title' => __('دليل السيارات', 'hello-elementor-child'), 'hide_header' => true]]);
    ?>
</main>
<?php
get_footer();
