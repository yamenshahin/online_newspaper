<?php
/**
 * Flexible Content: Speakers & Influencers Section (Filter)
 * Department ← speakers linked to that department
 * Homepage   ← all speakers with content
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
                <?php render_department_icon($department ?? null); ?>
                <h2 class="font-headline-lg text-headline-lg text-on-background">
                    <?php echo esc_html($section_title); ?>
                </h2>
                <span
                    class="px-space-sm py-0.5 rounded-full bg-primary-container text-on-primary font-label-caps text-label-caps">
                    <?php esc_html_e('قائمة الخبراء', 'hello-elementor-child'); ?>
                </span>
            </div>
            <p class="font-body-sm text-body-sm text-on-surface-variant">
                شخصيات قيادية وصناع محتوى يشاركون رؤاهم وتحليلاتهم الحصرية عبر المنصة
            </p>
        </div>

        <?php if ($department instanceof WP_Term && isset($_GET[$query_var])): ?>
            <!-- Reset/Clear Filter Button -->
            <a href="<?php echo esc_url(get_term_link($department)); ?>"
                class="px-space-md py-space-xs rounded-full hover:bg-surface-container-high text-on-surface font-label-pill text-label-pill transition-colors shadow-sm flex items-center gap-1 self-start md:self-auto border border-surface-container-high">
                <span><?php esc_html_e('كل الخبراء', 'hello-elementor-child'); ?></span>
                <span class="material-symbols-outlined text-[16px]">close</span>
            </a>
        <?php endif; ?>
    </div>

    <!-- Creator Cards Grid Layout -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-gutter">
        <?php
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
            $is_active = (isset($_GET[$query_var]) && $_GET[$query_var] === $term->slug);
            $active_classes = $is_active
                ? 'ring-2 ring-primary ring-offset-2 ring-offset-background'
                : 'hover:shadow-xl';
            ?>
            <a href="<?php echo esc_url($url); ?>"
                class="group relative rounded-3xl overflow-hidden aspect-[3/4] block transition-all <?php echo esc_attr($active_classes); ?>">

                <!-- Image -->
                <?php if (!empty($image) && is_array($image)): ?>
                    <?php echo wp_get_attachment_image($image['ID'], 'medium_large', false, [
                        'class' => 'absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-105',
                    ]); ?>
                <?php else: ?>
                    <div class="absolute inset-0 w-full h-full flex items-center justify-center bg-primary-container">
                        <span class="text-6xl font-bold text-on-primary-container/30 uppercase">
                            <?php echo esc_html(mb_substr($term->name, 0, 1)); ?>
                        </span>
                    </div>
                <?php endif; ?>

                <!-- Strong dark gradient overlay (this is the key fix) -->
                <div
                    class="absolute inset-x-0 bottom-0 h-3/5 bg-gradient-to-t from-black/85 via-black/50 to-transparent pointer-events-none">
                </div>

                <!-- Count badge -->
                <span
                    class="absolute top-3 right-3 px-space-sm py-0.5 rounded-full bg-primary-container/90 backdrop-blur-md text-on-primary-container font-label-caps text-label-caps shadow-sm z-10">
                    <?php echo esc_html($count); ?>
                    <?php esc_html_e('مادة منشورة', 'hello-elementor-child'); ?>
                </span>

                <!-- Text content sitting on the dark gradient -->
                <div class="absolute inset-x-0 bottom-0 p-5 z-10 flex flex-col gap-1 text-white">
                    <span class="font-label-caps text-label-caps text-white/90">
                        <?php
                        $dept_names = [];
                        if ($department instanceof WP_Term) {
                            $dept_names[] = $department->name;
                        } else {
                            $speaker_posts = get_posts([
                                'post_type' => ['post', 'interview', 'program', 'video', 'infographic'],
                                'posts_per_page' => 5,
                                'fields' => 'ids',
                                'tax_query' => [
                                    [
                                        'taxonomy' => 'speaker_influencer',
                                        'field' => 'term_id',
                                        'terms' => $term->term_id,
                                    ]
                                ]
                            ]);
                            if (!empty($speaker_posts)) {
                                $deps = wp_get_object_terms($speaker_posts, 'department', ['fields' => 'names']);
                                if (!is_wp_error($deps) && !empty($deps)) {
                                    $dept_names = array_unique($deps);
                                }
                            }
                        }
                        $dept_string = !empty($dept_names) ? implode(' و ', $dept_names) : 'تفاعل السعودية';
                        echo esc_html__('خبير وصانع أثر في', 'hello-elementor-child') . ' ' . esc_html($dept_string);
                        ?>
                    </span>

                    <h3
                        class="font-headline-sm text-headline-sm font-bold text-white group-hover:text-white/90 transition-colors">
                        <?php echo esc_html($term->name); ?>
                    </h3>

                    <?php if (!empty($term->description)): ?>
                        <p class="font-body-sm text-body-sm text-white/80 text-xs mt-1 line-clamp-2">
                            <?php echo esc_html(wp_strip_all_tags($term->description)); ?>
                        </p>
                    <?php endif; ?>

                    <div class="mt-3 pt-3 flex items-center justify-between border-t border-white/20">
                        <span
                            class="font-label-pill text-label-pill flex items-center gap-1 group-hover:gap-2 transition-all">
                            <?php esc_html_e('عرض الملف والمقالات', 'hello-elementor-child'); ?>
                            <span class="material-symbols-outlined text-[14px]">←</span>
                        </span>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>