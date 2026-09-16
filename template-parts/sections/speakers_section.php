<?php
/**
 * Flexible Content: Speakers & Influencers Section (Filter)
 * Department → speakers linked to that department
 * Homepage   → all speakers with content
 */

$department = $args['department'] ?? null;
$section = $args['section'] ?? [];

if (!empty($section)) {
    $section_title = $section['section_title'] ?? '';
} else {
    $section_title = get_sub_field('section_title');
}

$section_title = $section_title ?: __('صناع الأثر • VOICES & SPEAKERS', 'hello-elementor-child');
$target_taxonomy = 'speaker_influencer';
$query_var = 'speaker_influencer';

// -------------------------------------------------
// Get terms (safe for both contexts)
// -------------------------------------------------
$active_terms = [];

if ($department instanceof WP_Term) {
    // Department page
    $active_terms = get_department_intersected_terms((int) $department->term_id, $target_taxonomy);
} else {
    // Homepage – no department
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

<section class="w-full px-margin-mobile lg:px-margin py-space-xl">
    <div class="flex items-end justify-between mb-space-lg">
        <div class="flex flex-col gap-space-xs">
            <div class="flex items-center gap-space-xs">
                <span class="font-headline-lg text-headline-lg text-primary font-bold">05 /</span>
                <h2 class="font-headline-lg text-headline-lg text-on-background">
                    <?php echo esc_html($section_title); ?>
                </h2>
                <span
                    class="px-space-sm py-0.5 rounded-full bg-primary-container text-on-primary font-label-caps text-label-caps">
                    <?php esc_html_e('قائمة الخبراء', 'hello-elementor-child'); ?>
                </span>
            </div>
            <p class="font-body-sm text-body-sm text-on-surface-variant">شخصيات قيادية وصناع محتوى يشاركون رؤاهم
                وتحليلاتهم الحصرية عبر المنصة</p>
        </div>

        <?php if ($department instanceof WP_Term && isset($_GET[$query_var])): ?>
            <!-- Reset/Clear Filter Button -->
            <a href="<?php echo esc_url(get_term_link($department)); ?>"
                class="px-space-md py-space-xs rounded-full bg-surface-container-lowest hover:bg-surface-container-high text-on-surface font-label-pill text-label-pill transition-colors shadow-sm flex items-center gap-1 self-start md:self-auto border border-surface-container-high">
                <span class=""><?php esc_html_e('كل الخبراء', 'hello-elementor-child'); ?></span>
                <span class="material-symbols-outlined text-[16px]">close</span>
            </a>
        <?php endif; ?>
    </div>

    <!-- Creator Cards Grid Layout -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-gutter">

        <?php
        $index = 1;
        foreach ($active_terms as $data):
            $term = $data['term'];
            $count = $data['count'];

            // Link depends on context
            if ($department instanceof WP_Term) {
                $url = add_query_arg($query_var, $term->slug, get_term_link($department));
            } else {
                $url = get_term_link($term);
            }

            $image = get_field('taxonomy_image', $term);

            // Dynamic primary/secondary fixed text colors to match the design
            $theme_color_text = ($index % 2 === 0) ? 'text-secondary-fixed' : 'text-primary-fixed';

            $is_active = (isset($_GET[$query_var]) && $_GET[$query_var] === $term->slug);
            $active_classes = $is_active ? 'ring-2 ring-primary ring-offset-2 ring-offset-background' : 'hover:shadow-xl';
            ?>

            <div
                class="rounded-3xl overflow-hidden bg-inverse-surface text-surface-bright flex flex-col p-space-md group transition-all <?php echo esc_attr($active_classes); ?>">

                <a href="<?php echo esc_url($url); ?>"
                    class="relative w-full aspect-[4/5] rounded-2xl overflow-hidden bg-surface-container-high mb-space-md block">
                    <?php if (!empty($image) && is_array($image)): ?>
                        <?php echo wp_get_attachment_image($image['ID'], 'medium_large', false, [
                            'class' => 'w-full h-full object-cover transition-transform duration-500 group-hover:scale-105',
                        ]); ?>
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center bg-surface-container">
                            <span class="text-6xl font-bold text-surface-variant/30 uppercase">
                                <?php echo esc_html(mb_substr($term->name, 0, 1)); ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <span
                        class="absolute top-2 right-2 px-space-sm py-0.5 rounded-full bg-inverse-surface/80 backdrop-blur-md text-surface-bright font-label-caps text-label-caps shadow-sm">
                        <?php echo esc_html($count); ?>     <?php esc_html_e('مادة منشورة', 'hello-elementor-child'); ?>
                    </span>
                </a>

                <div class="flex flex-col gap-1">
                    <span class="font-label-caps text-label-caps <?php echo esc_attr($theme_color_text); ?>">
                        <?php
                        // Fallback if no specific title field exists
                        $speaker_title = get_field('speaker_title', $term);
                        echo esc_html($speaker_title ?: __('خبير وصانع أثر', 'hello-elementor-child'));
                        ?>
                    </span>

                    <a href="<?php echo esc_url($url); ?>">
                        <h3
                            class="font-headline-sm text-headline-sm text-surface-bright font-bold hover:text-surface-variant transition-colors">
                            <?php echo esc_html($term->name); ?>
                        </h3>
                    </a>

                    <?php if (!empty($term->description)): ?>
                        <p class="font-body-sm text-body-sm text-surface-variant text-xs mt-1 line-clamp-3">
                            <?php echo esc_html(wp_strip_all_tags($term->description)); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div
                    class="mt-space-md pt-space-xs flex items-center justify-between border-t border-surface-container-highest/10">
                    <a href="<?php echo esc_url($url); ?>"
                        class="<?php echo esc_attr($theme_color_text); ?> hover:text-surface-bright font-label-pill text-label-pill flex items-center gap-1 transition-colors group/link">
                        <span class=""><?php esc_html_e('عرض الملف والمقالات', 'hello-elementor-child'); ?></span>
                        <span
                            class="material-symbols-outlined text-[14px] group-hover/link:-translate-x-1 transition-transform">arrow_back</span>
                    </a>
                </div>

            </div>

            <?php
            $index++;
        endforeach;
        ?>

    </div>
</section>