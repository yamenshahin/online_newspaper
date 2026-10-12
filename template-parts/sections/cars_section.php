<?php
/**
 * Flexible Content: Cars directory (orchestrator)
 */
$section = $args['section'] ?? [];

if (!empty($section)) {
    $section_title = $section['section_title'] ?? '';
    $subtitle = $section['subtitle'] ?? '';
    $posts_limit = $section['posts_limit'] ?? 12;
} else {
    $section_title = get_sub_field('section_title');
    $subtitle = get_sub_field('subtitle');
    $posts_limit = get_sub_field('posts_limit') ?: 12;
}

$section_title = $section_title ?: __('دليل السيارات', 'hello-elementor-child');
$state = get_car_filter_state();
$base_url = get_cars_filter_base_url();

$query = query_cars_directory(
    [
        'posts_per_page' => absint($posts_limit),
        'paged' => $state['paged'],
    ]
);

$ctx = [
    'state' => $state,
    'base_url' => $base_url,
    'section_title' => $section_title,
    'subtitle' => $subtitle,
    'query' => $query,
];
?>

<section class="cars-directory w-full px-margin-mobile lg:px-margin py-space-xl" id="cars-directory">

    <div class="flex flex-col gap-space-md mb-8 text-start">
        <h2 class="font-headline-lg text-headline-lg text-on-background">
            <?php echo esc_html($section_title); ?>
        </h2>
        <?php if ($subtitle): ?>
                <p class="font-body-sm text-on-surface-variant"><?php echo esc_html($subtitle); ?></p>
        <?php endif; ?>

        <?php get_template_part('template-parts/cars/filter', 'search', $ctx); ?>
    </div>

    <?php get_template_part('template-parts/cars/filter', 'chips', $ctx); ?>
    <?php get_template_part('template-parts/cars/filter', 'taxonomies', $ctx); ?>
    <?php get_template_part('template-parts/cars/filter', 'ranges-primary', $ctx); ?>
    <?php get_template_part('template-parts/cars/filter', 'advanced', $ctx); ?>
    <?php get_template_part('template-parts/cars/results', null, $ctx); ?>

</section>