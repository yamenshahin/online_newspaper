<?php
/**
 * Flexible Content: Cars directory (the filter). Also the body of archive-car.php.
 *
 * One GET form holds every filter field (primary bar + "more" panel), so a plain submit works without JS;
 * filters.js refreshes #cars-chips and #cars-results over AJAX (action cars_filter) and mirrors the state
 * in the URL. The models map lets the model select follow the make without a request.
 */
$section = $args['section'] ?? [];

if (!empty($section)) {
    $section_title = $section['section_title'] ?? '';
    $subtitle = $section['subtitle'] ?? '';
    $posts_limit = $section['posts_limit'] ?? 0;
} else {
    $section_title = get_sub_field('section_title');
    $subtitle = get_sub_field('subtitle');
    $posts_limit = get_sub_field('posts_limit') ?: 0;
}

$section_title = $section_title ?: __('دليل السيارات', 'hello-elementor-child');
$is_car_archive = is_post_type_archive('car');
$posts_limit = absint($posts_limit) ?: ($is_car_archive ? CAR_FILTER_PER_PAGE : CAR_SECTION_PER_PAGE);

$state = get_car_filter_state();
$base_url = get_cars_filter_base_url();
$archive_url = get_cars_archive_url();

$query = query_cars_directory([
    'posts_per_page' => absint($posts_limit) ?: CAR_FILTER_PER_PAGE,
    'paged' => $state['paged'],
    'state' => $state,
]);

$ctx = [
    'state' => $state,
    'base_url' => $base_url,
    'archive_url' => $archive_url,
    'query' => $query,
    'is_car_archive' => $is_car_archive,
];
?>

<section class="cars-directory w-full px-margin-mobile lg:px-margin py-space-xl" id="cars-directory"
    data-base-url="<?php echo esc_url($base_url); ?>"
    data-archive-url="<?php echo esc_url($archive_url); ?>"
    data-is-archive="<?php echo $is_car_archive ? '1' : '0'; ?>"
    data-per-page="<?php echo esc_attr((string) (absint($posts_limit) ?: CAR_FILTER_PER_PAGE)); ?>">

    <div class="flex flex-col items-start gap-space-xs mb-6 text-start">
        <?php if ($is_car_archive): ?>
            <div class="flex flex-wrap items-center gap-space-sm">
                <h1 class="font-headline-sm text-headline-sm md:font-headline-lg md:text-headline-lg text-on-background">
                    <?php echo esc_html($section_title); ?>
                </h1>
                <?php if (function_exists('car_pill')) {
                    echo car_pill(n_cars(car_total_count()), '', 'primary'); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper
                } ?>
            </div>
            <?php if ($subtitle): ?>
                <p class="font-body-sm text-body-sm text-on-surface-variant"><?php echo esc_html($subtitle); ?></p>
            <?php endif; ?>
        <?php else: ?>
            <span class="font-label-caps text-label-caps text-primary uppercase tracking-widest">
                <?php echo esc_html($section_title); ?>
            </span>
            <?php if ($subtitle): ?>
                <h2 class="font-headline-sm text-headline-sm md:font-headline-lg md:text-headline-lg text-on-background">
                    <?php echo esc_html($subtitle); ?>
                </h2>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <form method="get" action="<?php echo esc_url($base_url); ?>" class="cars-filter-form" role="search">
        <?php get_template_part('template-parts/cars/filter', 'taxonomies', $ctx); ?>

        <div id="cars-chips">
            <?php get_template_part('template-parts/cars/filter', 'chips', $ctx); ?>
        </div>

        <?php get_template_part('template-parts/cars/filter', 'more', $ctx); ?>
    </form>

    <div id="cars-results" class="relative mt-8">
        <div id="cars-loading" class="absolute inset-0 z-10 hidden items-center justify-center bg-white/70 rounded-2xl">
            <div class="flex flex-col items-center gap-3">
                <div class="w-10 h-10 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
                <span class="text-sm font-medium text-on-surface-variant"><?php esc_html_e('جاري التحميل…', 'hello-elementor-child'); ?></span>
            </div>
        </div>

        <?php get_template_part('template-parts/cars/results', null, $ctx); ?>
    </div>

    <script type="application/json" id="cars-models-map"><?php echo wp_json_encode(get_car_models_map(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
</section>
