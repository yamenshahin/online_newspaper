<?php
/**
 * Flexible Content: Program Series Section (Filter)
 * Department → series linked to that department
 * Homepage   → all series with content
 */

$department = $args['department'] ?? null;
$section = $args['section'] ?? [];

if (!empty($section)) {
    $section_title = $section['section_title'] ?? '';
} else {
    $section_title = get_sub_field('section_title');
}

$section_title = $section_title ?: __('ORIGINAL SERIES / برامجنا الحصرية', 'hello-elementor-child');
$target_taxonomy = 'program_series';
$query_var = 'program_series';

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

// Determine the archive link for the "دليل كل المواسم" button
if ($department instanceof WP_Term) {
    $archive_link = add_query_arg(['view' => 'program'], get_term_link($department));
} else {
    $archive_link = get_post_type_archive_link('program') ?: home_url('/');
}
?>

<section class="w-full px-margin-mobile lg:px-margin py-space-xl est">
    <div
        class="p-space-lg md:p-space-xl rounded-3xl bg-inverse-surface text-inverse-on-surface shadow-xl flex flex-col gap-space-xl relative overflow-hidden">

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-space-md">
            <div class="flex flex-col gap-space-xs text-start">
                <span class="font-label-caps text-label-caps text-primary-fixed uppercase tracking-widest">
                    <?php echo esc_html($section_title); ?>
                </span>
                <h2 class="font-headline-lg text-headline-lg text-surface-bright">
                    <?php esc_html_e('سلاسل وثائقية معمقة تؤرخ التحول التاريخي', 'hello-elementor-child'); ?>
                </h2>
            </div>

            <?php if ($department instanceof WP_Term && isset($_GET[$query_var])): ?>
                <a href="<?php echo esc_url(get_term_link($department)); ?>"
                    class="px-space-lg py-space-sm rounded-full bg-primary-container hover:bg-primary text-on-primary font-label-pill text-label-pill self-start md:self-auto transition-colors flex items-center gap-1">
                    <span class=""><?php esc_html_e('دليل كل المواسم', 'hello-elementor-child'); ?></span>
                    <span class="material-symbols-outlined text-[16px]">close</span>
                </a>
            <?php else: ?>
                <a href="<?php echo esc_url($archive_link); ?>"
                    class="px-space-lg py-space-sm rounded-full bg-primary-container hover:bg-primary text-on-primary font-label-pill text-label-pill self-start md:self-auto transition-colors">
                    <?php esc_html_e('دليل كل المواسم', 'hello-elementor-child'); ?>
                </a>
            <?php endif; ?>
        </div>

        <!-- Updated to 4 columns on large screens -->
        <div class="relative z-10 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-gutter">

            <?php
            $index = 1;
            foreach ($active_terms as $data):
                $term = $data['term'];
                $count = $data['count'];

                if ($department instanceof WP_Term) {
                    $url = add_query_arg($query_var, $term->slug, get_term_link($department));
                } else {
                    $url = get_term_link($term);
                }

                $image = get_field('taxonomy_image', $term);
                $theme_color_text = ($index % 2 === 0) ? 'text-secondary-fixed' : 'text-primary-fixed';

                $is_active = (isset($_GET[$query_var]) && $_GET[$query_var] === $term->slug);
                $active_bg = $is_active ? 'bg-inverse-surface border border-surface-variant' : 'bg-inverse-surface/80 hover:bg-inverse-surface border border-transparent';
                ?>

                <a href="<?php echo esc_url($url); ?>"
                    class="rounded-2xl overflow-hidden transition-colors flex flex-col shadow-inner group text-start <?php echo esc_attr($active_bg); ?>">

                    <!-- Wide (16:9) Image -->
                    <div class="w-full aspect-video -highest/10 relative overflow-hidden">
                        <?php if (!empty($image) && is_array($image)): ?>
                            <?php echo wp_get_attachment_image($image['ID'], 'large', false, [
                                'class' => 'w-full h-full object-cover transition-transform duration-700 group-hover:scale-105',
                            ]); ?>
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center">
                                <span class="text-4xl font-bold text-surface-variant/30 uppercase">
                                    <?php echo esc_html(mb_substr($term->name, 0, 1)); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Text Content -->
                    <div class="p-space-lg flex flex-col justify-between flex-grow gap-space-md">
                        <div class="flex flex-col gap-space-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-label-caps text-label-caps <?php echo esc_attr($theme_color_text); ?>">
                                    SERIES // <?php echo esc_html(str_pad($index, 2, '0', STR_PAD_LEFT)); ?>
                                </span>
                                <span
                                    class="font-label-pill text-label-pill px-space-sm py-0.5 rounded-full est/10 text-surface-bright">
                                    <?php echo esc_html($count); ?>
                                    <?php esc_html_e('حلقة معتمدة', 'hello-elementor-child'); ?>
                                </span>
                            </div>

                            <h3
                                class="font-headline-sm text-headline-sm text-surface-bright group-hover:<?php echo esc_attr($theme_color_text); ?> transition-colors mt-1">
                                <?php echo esc_html($term->name); ?>
                            </h3>

                            <?php if (!empty($term->description)): ?>
                                <p class="font-body-sm text-body-sm text-surface-variant line-clamp-2 mt-1">
                                    <?php echo esc_html(wp_strip_all_tags($term->description)); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div
                            class="flex items-center justify-between <?php echo esc_attr($theme_color_text); ?> font-label-pill text-label-pill pt-space-sm mt-2 border-t border-surface-container-lowest/10">
                            <span class=""><?php esc_html_e('تصفح كل الحلقات', 'hello-elementor-child'); ?></span>
                            <span
                                class="material-symbols-outlined text-[16px] group-hover:-translate-x-1 transition-transform">arrow_back</span>
                        </div>
                    </div>

                </a>

                <?php
                $index++;
            endforeach;
            ?>

        </div>

    </div>
</section>