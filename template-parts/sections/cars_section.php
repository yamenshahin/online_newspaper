<?php
/**
 * Flexible Content: Cars directory (Phase 1)
 * Tax filters + title search. No department tax on cars.
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
$facets = get_car_filter_taxonomies();
$query = query_cars_directory(
    [
        'posts_per_page' => absint($posts_limit),
        'paged' => $state['paged'],
    ]
);

// Facets that are large: show more terms via higher limit only for maker/year/etc.
$facet_limits = [
    'car_model' => 80,
    'car_body_type' => 60,
    'car_maker' => 0,
    'car_year' => 0,
    'car_origin' => 0,
    'car_fuel_type' => 0,
];
?>

<section class="cars-directory w-full px-margin-mobile lg:px-margin py-space-xl" id="cars-directory">
    <div class="flex flex-col gap-space-md mb-8">
        <h2 class="font-headline-lg text-headline-lg text-on-background text-start">
            <?php echo esc_html($section_title); ?>
        </h2>
        <?php if ($subtitle): ?>
            <p class="font-body-sm text-on-surface-variant text-start"><?php echo esc_html($subtitle); ?></p>
        <?php endif; ?>

        <!-- Search -->
        <form method="get" action="<?php echo esc_url(get_cars_filter_base_url()); ?>" class="w-full max-w-xl">
            <?php
            // Preserve active tax filters when searching.
            foreach ($state['taxes'] as $tax => $slug):
                ?>
                <input type="hidden" name="<?php echo esc_attr($tax); ?>" value="<?php echo esc_attr($slug); ?>" />
            <?php endforeach; ?>
            <label class="relative block">
                <span
                    class="material-symbols-outlined absolute inset-y-0 start-3 flex items-center text-on-surface-variant text-[20px]">search</span>
                <input type="search" name="car_s" value="<?php echo esc_attr($state['search']); ?>"
                    placeholder="<?php esc_attr_e('ابحث عن سيارة…', 'hello-elementor-child'); ?>"
                    class="w-full rounded-full border border-surface-container-highest bg-surface-container-lowest py-3 pe-4 ps-11 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
            </label>
        </form>
    </div>

    <!-- Active chips -->
    <?php if ($state['search'] !== '' || !empty($state['taxes'])): ?>
        <div class="flex flex-wrap gap-2 mb-6">
            <?php if ($state['search'] !== ''): ?>
                <a href="<?php echo esc_url(get_cars_filter_url(['car_s' => null])); ?>"
                    class="inline-flex items-center gap-1 rounded-full bg-primary text-on-primary px-3 py-1 text-sm font-semibold">
                    <?php echo esc_html($state['search']); ?>
                    <span aria-hidden="true">×</span>
                </a>
            <?php endif; ?>
            <?php foreach ($state['taxes'] as $tax => $slug):
                $t = get_term_by('slug', $slug, $tax);
                if (!$t || is_wp_error($t)) {
                    continue;
                }
                ?>
                <a href="<?php echo esc_url(get_cars_filter_url([$tax => null])); ?>"
                    class="inline-flex items-center gap-1 rounded-full bg-primary text-on-primary px-3 py-1 text-sm font-semibold">
                    <?php echo esc_html($t->name); ?>
                    <span aria-hidden="true">×</span>
                </a>
            <?php endforeach; ?>
            <a href="<?php echo esc_url(get_cars_filter_base_url()); ?>"
                class="text-sm text-primary font-semibold underline-offset-2 hover:underline">
                <?php esc_html_e('مسح الكل', 'hello-elementor-child'); ?>
            </a>
        </div>
    <?php endif; ?>

    <!-- Facet rows -->
    <div class="flex flex-col gap-5 mb-10">
        <?php foreach ($facets as $tax => $label):
            $terms = get_car_facet_terms($tax, (int) ($facet_limits[$tax] ?? 0));
            if (empty($terms)) {
                continue;
            }
            $active = $state['taxes'][$tax] ?? '';
            ?>
            <div class="text-start">
                <div class="text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-2">
                    <?php echo esc_html($label); ?>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="<?php echo esc_url(get_cars_filter_url([$tax => null])); ?>"
                        class="inline-flex rounded-full px-3 py-1.5 text-sm font-semibold border transition-colors <?php echo $active === '' ? 'bg-primary text-on-primary border-primary' : 'bg-surface-container-low text-on-surface border-surface-container-highest hover:border-primary'; ?>">
                        <?php esc_html_e('الكل', 'hello-elementor-child'); ?>
                    </a>
                    <?php foreach ($terms as $term):
                        $is_on = ($active === $term->slug);
                        $url = get_cars_filter_url([$tax => $is_on ? null : $term->slug]);
                        ?>
                        <a href="<?php echo esc_url($url); ?>"
                            class="inline-flex items-center gap-1 rounded-full px-3 py-1.5 text-sm font-semibold border transition-colors <?php echo $is_on ? 'bg-primary text-on-primary border-primary' : 'bg-surface-container-lowest text-on-surface border-surface-container-highest hover:border-primary'; ?>">
                            <?php echo esc_html($term->name); ?>
                            <span class="text-[11px] opacity-70">(<?php echo esc_html((string) $term->count); ?>)</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Results -->
    <?php if ($query->have_posts()): ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-gutter mb-10">
            <?php
            while ($query->have_posts()):
                $query->the_post();
                get_template_part('template-parts/cards/card', 'car');
            endwhile;
            ?>
        </div>

        <?php if ((int) $query->max_num_pages > 1): ?>
            <nav class="flex justify-center" aria-label="<?php esc_attr_e('Pagination', 'hello-elementor-child'); ?>">
                <?php
                $add_args = $state['taxes'];
                if ($state['search'] !== '') {
                    $add_args['car_s'] = $state['search'];
                }
                echo paginate_links(
                    [
                        'total' => (int) $query->max_num_pages,
                        'current' => $state['paged'],
                        'prev_text' => '«',
                        'next_text' => '»',
                        'add_args' => $add_args ?: false,
                    ]
                );
                ?>
            </nav>
        <?php endif; ?>
        <?php wp_reset_postdata(); ?>
    <?php else: ?>
        <div class="text-center py-16 rounded-2xl border border-dashed border-surface-container-highest">
            <p class="text-on-surface-variant"><?php esc_html_e('لا توجد سيارات مطابقة.', 'hello-elementor-child'); ?></p>
        </div>
    <?php endif; ?>
</section>