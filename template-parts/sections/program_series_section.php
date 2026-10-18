<?php
/**
 * Flexible Content: Program Series Section (Filter)
 * - Department ± URL filters (incl. event)
 * - Taxonomy archives via $_GET
 * - Homepage: series with content
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

$series_filter_taxonomies = [
    'government_entity',
    'private_entity',
    'speaker_influencer',
    'geographic',
    'event', // taxonomy archives set $_GET['event']
];

$active_filters = [];
foreach ($series_filter_taxonomies as $taxonomy) {
    if (empty($_GET[$taxonomy]) || !is_scalar($_GET[$taxonomy])) {
        continue;
    }
    $slug = sanitize_text_field(wp_unslash((string) $_GET[$taxonomy]));
    if ($slug !== '') {
        $active_filters[$taxonomy] = $slug;
    }
}

$active_terms = [];

$needs_scoped_query = ($department instanceof WP_Term) || !empty($active_filters);

if ($needs_scoped_query) {
    $program_tax_query = ['relation' => 'AND'];

    if ($department instanceof WP_Term) {
        $program_tax_query[] = [
            'taxonomy' => 'department',
            'field' => 'term_id',
            'terms' => (int) $department->term_id,
        ];
    }

    foreach ($active_filters as $taxonomy => $slug) {
        $clause = function_exists('get_hierarchical_entity_tax_clause')
            ? get_hierarchical_entity_tax_clause($taxonomy, $slug)
            : null;

        if (is_array($clause)) {
            $program_tax_query[] = $clause;
        } else {
            $program_tax_query[] = [
                'taxonomy' => $taxonomy,
                'field' => 'slug',
                'terms' => $slug,
            ];
        }
    }

    $program_ids = get_posts([
        'post_type' => 'program',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'ignore_sticky_posts' => true,
        'update_post_meta_cache' => false,
        'tax_query' => $program_tax_query,
    ]);

    $series_counts = [];
    foreach ($program_ids as $program_id) {
        $series_terms = get_the_terms((int) $program_id, $target_taxonomy);
        if (empty($series_terms) || is_wp_error($series_terms)) {
            continue;
        }
        foreach ($series_terms as $series_term) {
            $series_id = (int) $series_term->term_id;
            $series_counts[$series_id] = ($series_counts[$series_id] ?? 0) + 1;
        }
    }

    if (!empty($series_counts)) {
        $terms = get_terms([
            'taxonomy' => $target_taxonomy,
            'include' => array_keys($series_counts),
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term) {
                $active_terms[] = [
                    'term' => $term,
                    'count' => (int) $series_counts[(int) $term->term_id],
                ];
            }
        }
    }
} else {
    // Homepage / no filter: series that have programs.
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

if ($department instanceof WP_Term) {
    $archive_link = add_query_arg(array_merge(['view' => 'program'], $active_filters), get_term_link($department));
} else {
    $archive_link = get_post_type_archive_link('program') ?: home_url('/');
    if (!empty($active_filters)) {
        $archive_link = add_query_arg($active_filters, $archive_link);
    }
}

$total_terms = count($active_terms);
$show_limit = 4;
$has_more = $total_terms > $show_limit;
?>
<section class="w-full px-margin-mobile lg:px-margin py-space-xl est">
    <div
        class="p-space-lg md:p-space-xl rounded-3xl bg-primary-container text-on-primary shadow-xl flex flex-col gap-space-xl relative overflow-hidden">
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
                    class="px-space-lg py-space-sm rounded-full bg-white text-gray-700 border border-gray-200 hover:text-primary hover:bg-gray-50 shadow-sm font-label-pill text-label-pill self-start md:self-auto transition-all flex items-center gap-1">
                    <span><?php esc_html_e('دليل كل المواسم', 'hello-elementor-child'); ?></span>
                    <span class="material-symbols-outlined text-[16px]">close</span>
                </a>
            <?php else: ?>
                <a href="<?php echo esc_url($archive_link); ?>"
                    class="px-space-lg py-space-sm rounded-full bg-white text-gray-700 border border-gray-200 hover:text-primary hover:bg-gray-50 shadow-sm font-label-pill text-label-pill self-start md:self-auto transition-all">
                    <?php esc_html_e('دليل كل المواسم', 'hello-elementor-child'); ?>
                </a>
            <?php endif; ?>
        </div>
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
                $is_active = isset($_GET[$query_var]) && $_GET[$query_var] === $term->slug;
                $active_bg = $is_active
                    ? 'bg-white border-2 border-primary shadow-md'
                    : 'bg-white hover:bg-gray-50 border border-gray-200 shadow-sm';

                $is_extra = ($index > $show_limit);
                $card_classes = "rounded-2xl overflow-hidden transition-all flex flex-col group text-start {$active_bg}";
                if ($is_extra && !$is_active) {
                    $card_classes .= ' hidden program-extra-item';
                }
                ?>
                <a href="<?php echo esc_url($url); ?>" class="<?php echo esc_attr($card_classes); ?>">
                    <div class="w-full aspect-video bg-gray-100 relative overflow-hidden">
                        <?php if (!empty($image) && is_array($image)): ?>
                            <?php
                            echo wp_get_attachment_image(
                                $image['ID'],
                                'large',
                                false,
                                [
                                    'class' => 'w-full h-full object-cover transition-transform duration-700 group-hover:scale-105',
                                ]
                            );
                            ?>
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center">
                                <span class="text-4xl font-bold text-gray-300 uppercase">
                                    <?php echo esc_html(mb_substr($term->name, 0, 1)); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="p-space-lg flex flex-col justify-between flex-grow gap-space-md">
                        <div class="flex flex-col gap-space-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-label-caps text-label-caps text-primary">
                                    <?php
                                    $dept_names = [];
                                    if ($department instanceof WP_Term) {
                                        $dept_names[] = $department->name;
                                    } else {
                                        $series_posts = get_posts([
                                            'post_type' => ['program', 'post', 'video', 'interview'],
                                            'posts_per_page' => 5,
                                            'fields' => 'ids',
                                            'tax_query' => [
                                                [
                                                    'taxonomy' => 'program_series',
                                                    'field' => 'term_id',
                                                    'terms' => $term->term_id,
                                                ],
                                            ],
                                        ]);
                                        if (!empty($series_posts)) {
                                            $deps = wp_get_object_terms($series_posts, 'department', ['fields' => 'names']);
                                            if (!is_wp_error($deps) && !empty($deps)) {
                                                $dept_names = array_unique($deps);
                                            }
                                        }
                                    }
                                    $dept_string = !empty($dept_names) ? implode(' و ', $dept_names) : 'تفاعل السعودية';
                                    echo esc_html($dept_string);
                                    ?>
                                </span>
                                <span
                                    class="font-label-pill text-label-pill px-space-sm py-0.5 rounded-full bg-gray-100 text-gray-700">
                                    <?php echo esc_html((string) $count); ?>
                                    <?php esc_html_e('حلقة معتمدة', 'hello-elementor-child'); ?>
                                </span>
                            </div>
                            <h3
                                class="font-headline-sm text-headline-sm text-gray-900 group-hover:text-primary transition-colors mt-1">
                                <?php echo esc_html($term->name); ?>
                            </h3>
                            <?php
                            $acf_term_id = $term->taxonomy . '_' . $term->term_id;
                            $detailed_desc =
                                get_field('detailed_description', $acf_term_id)
                                ?: get_field('detailed_description', 'term_' . $term->term_id)
                                ?: get_term_meta($term->term_id, 'detailed_description', true)
                                ?: $term->description;
                            ?>
                            <?php if (!empty($detailed_desc)): ?>
                                <p class="font-body-sm text-body-sm text-gray-600 line-clamp-2 mt-1">
                                    <?php echo esc_html(wp_strip_all_tags($detailed_desc)); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div
                            class="flex items-center justify-between text-primary font-label-pill text-label-pill pt-space-sm mt-2 border-t border-gray-100">
                            <span><?php esc_html_e('تصفح كل الحلقات', 'hello-elementor-child'); ?></span>
                            <span
                                class="material-symbols-outlined text-[16px] group-hover:-translate-x-1 transition-transform">←</span>
                        </div>
                    </div>
                </a>
                <?php
                $index++;
            endforeach;
            ?>
        </div>
        <?php if ($has_more): ?>
            <div class="flex justify-center z-10 relative mt-2">
                <button type="button" id="toggle-program-series"
                    class="px-space-xl py-space-sm rounded-full bg-white text-gray-700 border border-gray-200 hover:text-primary hover:bg-gray-50 shadow-sm font-label-pill text-label-pill transition-all flex items-center gap-2 cursor-pointer">
                    <span class="button-text"><?php esc_html_e('عرض المزيد', 'hello-elementor-child'); ?></span>
                    <span class="material-symbols-outlined text-[16px] transition-transform duration-300">expand_more</span>
                </button>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const btn = document.getElementById('toggle-program-series');
                    if (!btn) return;
                    const extraItems = document.querySelectorAll('.program-extra-item');
                    const icon = btn.querySelector('.material-symbols-outlined');
                    const textSpan = btn.querySelector('.button-text');
                    let expanded = false;
                    btn.addEventListener('click', function () {
                        expanded = !expanded;
                        extraItems.forEach(item => item.classList.toggle('hidden', !expanded));
                        if (expanded) {
                            textSpan.textContent = '<?php echo esc_js(__('عرض أقل', 'hello-elementor-child')); ?>';
                            icon.style.transform = 'rotate(180deg)';
                        } else {
                            textSpan.textContent = '<?php echo esc_js(__('عرض المزيد', 'hello-elementor-child')); ?>';
                            icon.style.transform = 'rotate(0deg)';
                        }
                    });
                });
            </script>
        <?php endif; ?>
    </div>
</section>