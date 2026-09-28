<?php
/**
 * Flexible Content: Private Entities Section
 * Department ← entities linked to that department
 * Homepage   ← all private entities with content
 */

$department = $args['department'] ?? null;
$section = $args['section'] ?? [];

if (!empty($section)) {
    $section_title = $section['section_title'] ?? '';
    $subtitle = $section['subtitle'] ?? '';
} else {
    $section_title = get_sub_field('section_title');
    $subtitle = get_sub_field('subtitle');
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
    class="w-full px-margin-mobile lg:px-margin py-space-lg border-b border-surface-container-highest/30 last:border-0">
    <div class="flex flex-col gap-space-md">

        <div class="flex flex-col items-start gap-space-xs text-start">
            <span class="font-label-caps text-label-caps text-primary uppercase tracking-widest">
                <?php echo esc_html($section_title); ?>
            </span>
            <?php if (!empty($subtitle)): ?>
                <h3 class="font-headline-sm text-headline-sm text-on-background mt-1">
                    <?php echo esc_html($subtitle); ?>
                </h3>
            <?php endif; ?>
        </div>

        <!-- Tailwind Expandable Filter Wrapper -->
        <div class="filter-wrapper relative w-full">
            <div
                class="filter-container flex flex-wrap items-center gap-space-sm pb-space-xs pt-1 max-h-[110px] overflow-hidden transition-[max-height] duration-500 ease-in-out">

                <?php if ($department instanceof WP_Term && isset($_GET[$query_var])): ?>
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

                    $pill_classes = $is_active
                        ? 'bg-primary text-on-primary border-primary'
                        : 'est text-on-surface hover:-high border-transparent hover:border-surface-container-highest';

                    $badge_classes = $is_active
                        ? 'bg-primary-container text-on-primary-container'
                        : ' text-on-surface-variant';
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

            <!-- Tailwind Fade indicator (Note: Adjust 'from-background' if your page background class name differs) -->
            <div
                class="filter-fade-overlay absolute bottom-0 left-0 w-full h-[50px] bg-gradient-to-t from-background to-transparent pointer-events-none transition-opacity duration-300">
            </div>
        </div>

        <div class="flex justify-center w-full mt-2">
            <button type="button"
                class="filter-toggle-btn px-space-md py-space-xs rounded-full bg-surface-container border border-surface-container-highest text-on-surface font-label-pill text-label-pill transition-colors hover:bg-surface-container-high hidden items-center gap-2">
                <span class="toggle-text"><?php esc_html_e('عرض المزيد', 'hello-elementor-child'); ?></span>
                <span class="material-symbols-outlined text-[16px] toggle-icon">expand_more</span>
            </button>
        </div>

    </div>
</section>