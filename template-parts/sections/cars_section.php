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

$query = query_cars_directory([
    'posts_per_page' => absint($posts_limit),
    'paged' => $state['paged'],
]);

$ctx = [
    'state' => $state,
    'base_url' => $base_url,
    'section_title' => $section_title,
    'subtitle' => $subtitle,
    'query' => $query,
];
?>

<section class="cars-directory w-full px-margin-mobile lg:px-margin py-space-xl" id="cars-directory">

    <!-- Title -->
    <div class="flex flex-col items-start gap-space-xs mb-6 text-start">
        <?php if ($section_title): ?>
            <span class="font-label-caps text-label-caps text-primary uppercase tracking-widest">
                <?php echo esc_html($section_title); ?>
            </span>
        <?php endif; ?>

        <?php if ($subtitle): ?>
            <h2 class="font-headline-sm text-headline-sm md:font-headline-lg md:text-headline-lg text-on-background">
                <?php echo esc_html($subtitle); ?>
            </h2>
        <?php endif; ?>
    </div>

    <!-- 1. Primary filter bar -->
    <?php get_template_part('template-parts/cars/filter', 'taxonomies', $ctx); ?>

    <!-- 2. Active filter chips -->
    <div id="cars-chips">
        <?php get_template_part('template-parts/cars/filter', 'chips', $ctx); ?>
    </div>

    <!-- 3. More filters -->
    <?php get_template_part('template-parts/cars/filter', 'more', $ctx); ?>

    <!-- 4. Results + loading overlay -->
    <div id="cars-results" class="relative">
        <div id="cars-loading" class="absolute inset-0 z-10 hidden items-center justify-center bg-white/70 rounded-2xl">
            <div class="flex flex-col items-center gap-3">
                <div class="w-10 h-10 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
                <span class="text-sm font-medium text-on-surface-variant">جاري التحميل…</span>
            </div>
        </div>

        <?php get_template_part('template-parts/cars/results', null, $ctx); ?>
    </div>

</section>