<?php
/**
 * Car post type archive — Phase 1 directory.
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
                'subtitle' => '',
                'posts_limit' => 12,
            ],
        ]
    );
    ?>
</main>
<?php
get_footer();