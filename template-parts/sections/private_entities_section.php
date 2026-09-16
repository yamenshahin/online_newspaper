<?php
/**
 * Flexible Content: Private Entities Section
 * Department → entities linked to that department
 * Homepage  → all private entities with content
 */

$department = $args['department'] ?? null;
$section = $args['section'] ?? [];

if (!empty($section)) {
    $section_title = $section['section_title'] ?? '';
} else {
    $section_title = get_sub_field('section_title');
}

$section_title = $section_title ?: __('القطاع الخاص والتقني', 'hello-elementor-child');
$target_taxonomy = 'private_entity';
$query_var = 'private_entity';

$active_terms = [];

if ($department instanceof WP_Term) {
    $active_terms = get_department_intersected_terms((int) $department->term_id, $target_taxonomy);
} else {
    $terms = get_terms([
        'taxonomy' => $target_taxonomy,
        'hide_empty' => true,
    ]);

    if (!empty($terms) && !is_wp_error($terms)) {
        foreach ($terms as $term) {
            $active_terms[] = [
                'term' => $term,
                'count' => (int) $term->count,
            ];
        }
    }
}

if (empty($active_terms)) {
    return;
}
?>

<section
    class="w-full px-margin-mobile lg:px-margin py-space-lg bg-surface-container-low border-b border-surface-container-highest/30 last:border-0">
    <div class="flex flex-col gap-space-md">

        <div class="flex flex-col items-start gap-space-xs">
            <span class="font-label-caps text-label-caps text-primary uppercase tracking-widest">
                PRIVATE SECTOR ENTITIES
            </span>
            <h3 class="font-headline-sm text-headline-sm text-on-background">
                <?php echo esc_html($section_title); ?>
            </h3>
        </div>

        <div
            class="flex items-center gap-space-sm overflow-x-auto pb-space-xs pt-1 whitespace-nowrap [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">

            <?php if ($department instanceof WP_Term && isset($_GET[$query_var])): ?>
                <!-- Reset/Clear Filter Button -->
                <a href="<?php echo esc_url(get_term_link($department)); ?>"
                    class="px-space-md py-space-xs rounded-full bg-on-background text-surface-container-lowest font-label-pill text-label-pill whitespace-nowrap flex items-center justify-center gap-space-xs shadow-sm transition-colors">
                    <span class=""><?php esc_html_e('كل الجهات', 'hello-elementor-child'); ?></span>
                    <span class="material-symbols-outlined text-[16px]">close</span>
                </a>
            <?php endif; ?>

            <?php
            foreach ($active_terms as $data):
                $term = $data['term'];
                $count = $data['count'];

                if ($department instanceof WP_Term) {
                    $url = add_query_arg($query_var, $term->slug, get_term_link($department));
                } else {
                    $url = get_term_link($term);
                }

                $is_active = (isset($_GET[$query_var]) && $_GET[$query_var] === $term->slug);

                // Active vs Inactive styling logic
                $pill_classes = $is_active
                    ? 'bg-primary text-on-primary border-primary'
                    : 'bg-surface-container-lowest text-on-surface hover:bg-surface-container-high border-transparent hover:border-surface-container-highest';

                $badge_classes = $is_active
                    ? 'bg-primary-container text-on-primary-container'
                    : 'bg-surface-container text-on-surface-variant';
                ?>

                <a href="<?php echo esc_url($url); ?>"
                    class="px-space-md py-space-xs rounded-full font-label-pill text-label-pill whitespace-nowrap flex items-center justify-center gap-space-sm shadow-sm transition-all border <?php echo esc_attr($pill_classes); ?>">
                    <span class=""><?php echo esc_html($term->name); ?></span>
                    <span
                        class="px-1.5 py-0.5 rounded-full text-[11px] font-bold <?php echo esc_attr($badge_classes); ?>">
                        <?php echo esc_html($count); ?>     <?php esc_html_e('مادة', 'hello-elementor-child'); ?>
                    </span>
                </a>

            <?php endforeach; ?>

        </div>
    </div>
</section>