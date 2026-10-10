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
<section class="w-full px-margin-mobile lg:px-margin py-space-xl">
    <div class="flex items-end justify-between mb-space-lg">
        <div class="flex flex-col gap-space-xs text-start">
            <div class="flex items-center gap-space-xs">
                <?php render_department_icon($department ?? null); ?>
                <h2 class="font-headline-lg text-headline-lg text-on-background">
                    <?php echo esc_html($section_title); ?>
                </h2>
            </div>
            <p class="font-body-sm text-body-sm text-on-surface-variant">
                شخصيات قيادية وصناع محتوى يشاركون رؤاهم وتحليلاتهم الحصرية عبر المنصة
            </p>
        </div>

        <?php if ($department instanceof WP_Term && isset($_GET[$query_var])): ?>
            <a href="<?php echo esc_url(get_term_link($department)); ?>"
                class="px-space-md py-space-xs rounded-full hover:bg-surface-container-high text-on-surface font-label-pill text-label-pill transition-colors shadow-sm flex items-center gap-1 self-start md:self-auto border border-surface-container-high">
                <span><?php esc_html_e('كل الخبراء', 'hello-elementor-child'); ?></span>
                <span class="material-symbols-outlined text-[16px]">close</span>
            </a>
        <?php endif; ?>
    </div>

    <!-- 5 Columns Grid Layout -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-gutter">
        <?php
        foreach ($active_terms as $data):
            $term = $data['term'];
            $count = $data['count'];

            if ($department instanceof WP_Term) {
                $url = add_query_arg($query_var, $term->slug, get_term_link($department));
            } else {
                $url = get_term_link($term);
            }

            // Fetch image using standard ACF term context matching working program series template
            $image = get_field('taxonomy_image', $term) ?: get_field('image', $term);

            // Fetch detailed description with fallbacks
            $detailed_desc = get_field('detailed_description', $term)
                ?: get_term_meta($term->term_id, 'detailed_description', true)
                ?: $term->description;

            $is_active = (isset($_GET[$query_var]) && $_GET[$query_var] === $term->slug);
            $active_classes = $is_active
                ? 'ring-2 ring-primary ring-offset-2 ring-offset-background'
                : 'hover:shadow-xl';
            ?>
            <a href="<?php echo esc_url($url); ?>"
                class="group relative rounded-3xl overflow-hidden block transition-all bg-primary p-3 flex flex-col justify-between <?php echo esc_attr($active_classes); ?>">

                <!-- 3:4 Portrait Image Frame -->
                <div class="relative w-full aspect-[3/4] shrink-0 bg-black/20 rounded-2xl overflow-hidden mb-3">
                    <?php if (!empty($image) && is_array($image)): ?>
                        <?php echo wp_get_attachment_image($image['ID'], 'medium_large', false, [
                            'class' => 'w-full h-full object-cover transition-transform duration-500 group-hover:scale-105',
                        ]); ?>
                    <?php elseif (!empty($image) && is_numeric($image)): ?>
                        <?php echo wp_get_attachment_image((int) $image, 'medium_large', false, [
                            'class' => 'w-full h-full object-cover transition-transform duration-500 group-hover:scale-105',
                        ]); ?>
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center bg-black/30">
                            <span class="text-5xl font-bold text-white/40 uppercase">
                                <?php echo esc_html(mb_substr($term->name, 0, 1)); ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <!-- Count Badge in Primary Theme -->
                    <span
                        class="absolute top-3 right-3 px-2.5 py-0.5 rounded-full bg-primary text-white backdrop-blur-md font-label-caps text-[11px] shadow-sm z-10 border border-white/20">
                        <?php echo esc_html($count); ?>
                        <?php esc_html_e('مادة منشورة', 'hello-elementor-child'); ?>
                    </span>
                </div>

                <!-- Text Content on Primary Background -->
                <div class="px-1 flex flex-col gap-1 text-start text-white flex-grow justify-between">
                    <div>
                        <span class="text-[11px] font-medium text-white/80 uppercase tracking-wider block">
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

                        <h3 class="text-lg font-bold text-white transition-colors mt-0.5">
                            <?php echo esc_html($term->name); ?>
                        </h3>

                        <?php if (!empty($detailed_desc)): ?>
                            <p class="text-xs text-white/90 line-clamp-2 mt-1 leading-relaxed">
                                <?php echo esc_html(wp_strip_all_tags($detailed_desc)); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="mt-3 pt-2 flex items-center justify-between border-t border-white/20 text-xs text-white/90">
                        <span class="flex items-center gap-1 transition-all">
                            <?php esc_html_e('عرض الملف والمقالات', 'hello-elementor-child'); ?>
                            <span class="material-symbols-outlined text-[14px] rtl:rotate-180">←</span>
                        </span>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>