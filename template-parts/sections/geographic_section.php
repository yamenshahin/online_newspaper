<?php
/**
 * Flexible Content: Geographic Section (Filter)
 * Hierarchical: Parents (Countries) ← Children (Cities)
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
$section_title = $section_title ?: __('Geographic Locations', 'hello-elementor-child');
$target_taxonomy = 'geographic';
$query_var = 'geographic';
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
// Helper for links
$build_geo_url = static function ($term_slug) use ($department, $query_var) {
    if ($department instanceof WP_Term) {
        return add_query_arg($query_var, $term_slug, get_term_link($department));
    }
    $term = get_term_by('slug', $term_slug, 'geographic');
    return ($term && !is_wp_error($term)) ? get_term_link($term) : home_url('/');
};
// Group parents / children
$parents = [];
$children_by_parent = [];
foreach ($active_terms as $data) {
    $term = $data['term'];
    if ((int) $term->parent === 0) {
        $parents[$term->term_id] = $data;
    } else {
        $children_by_parent[$term->parent][] = $data;
    }
}
foreach ($children_by_parent as $parent_id => $children) {
    if (!isset($parents[$parent_id])) {
        $parent_term = get_term($parent_id, $target_taxonomy);
        if ($parent_term && !is_wp_error($parent_term)) {
            $parents[$parent_id] = [
                'term' => $parent_term,
                'count' => array_sum(array_column($children, 'count')),
            ];
        }
    }
}
$active_parent_id = 0;
if (!empty($_GET[$query_var])) {
    $active_term_obj = get_term_by('slug', sanitize_text_field(wp_unslash($_GET[$query_var])), $target_taxonomy);
    if ($active_term_obj && !is_wp_error($active_term_obj)) {
        if ((int) $active_term_obj->parent === 0 && isset($children_by_parent[$active_term_obj->term_id])) {
            $active_parent_id = $active_term_obj->term_id;
        } elseif ((int) $active_term_obj->parent !== 0) {
            $active_parent_id = $active_term_obj->parent;
        }
    }
}
?>
<section
    class="w-full px-margin-mobile lg:px-margin py-space-lg border-b border-surface-container-highest/30 last:border-0"
    id="geographic-filter-section">
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
        <div class="relative">
            <!-- Grid Wrap View (Parents) -->
            <div id="geo-parents-view"
                class="geo-view <?php echo $active_parent_id ? 'hidden' : ''; ?> flex flex-col transition-opacity duration-300">
                <div class="filter-wrapper relative w-full">
                    <div class="filter-container flex flex-wrap gap-3 md:gap-4 pb-space-xs pt-1 max-h-[190px] overflow-hidden transition-[max-height] duration-500 ease-in-out"
                        data-collapsed-max="190">
                        <?php foreach ($parents as $parent_id => $data):
                            $term = $data['term'];
                            $count = $data['count'];
                            $has_children = isset($children_by_parent[$parent_id]);
                            $image = get_field('taxonomy_image', $term);
                            $url = $build_geo_url($term->slug);
                            $tag = $has_children ? 'button' : 'a';
                            $attr = $has_children
                                ? 'type="button" onclick="openCities(' . (int) $parent_id . ')"'
                                : 'href="' . esc_url($url) . '"';
                            ?>
                            <<?php echo $tag; ?>     <?php echo $attr; ?>
                                class="group flex-none w-28 md:w-32 flex flex-col items-center p-3 md:p-4 border
                                border-surface-container-high rounded-2xl hover:border-surface-container-highest
                                hover:shadow-xl hover:-translate-y-1 transition-all duration-300 ease-out text-center
                                focus:outline-none">
                                <div
                                    class="w-12 h-12 md:w-14 md:h-14 aspect-square mb-2 md:mb-3 rounded-xl md:rounded-2xl flex items-center justify-center overflow-hidden border border-surface-container-high group-hover:border-primary transition-colors duration-300 shadow-sm relative">
                                    <?php if (!empty($image) && is_array($image)): ?>
                                        <?php echo wp_get_attachment_image($image['ID'], 'thumbnail', false, [
                                            'class' => 'w-full h-full object-cover group-hover:scale-110 transition-transform duration-500',
                                        ]); ?>
                                    <?php else: ?>
                                        <span class="text-xl md:text-2xl font-bold text-on-surface-variant/40 uppercase">
                                            <?php echo esc_html(mb_substr($term->name, 0, 1)); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h3
                                    class="text-sm font-bold text-on-background text-center mb-2 line-clamp-2 leading-snug group-hover:text-primary transition-colors">
                                    <?php echo esc_html($term->name); ?>
                                </h3>
                                <div class="mt-auto pt-1">
                                    <span
                                        class="text-xs font-bold tracking-wide text-on-surface-variant px-3 py-1 rounded-full group-hover:bg-primary-container group-hover:text-on-primary-container transition-colors">
                                        <?php echo esc_html($count); ?>
                                        <?php esc_html_e('مادة', 'hello-elementor-child'); ?>
                                    </span>
                                </div>
                            </<?php echo $tag; ?>>
                        <?php endforeach; ?>
                    </div>
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
            <?php foreach ($children_by_parent as $parent_id => $children):
                $parent_data = $parents[$parent_id];
                $parent_term = $parent_data['term'];
                $parent_url = $build_geo_url($parent_term->slug);
                $parent_image = get_field('taxonomy_image', $parent_term);
                $is_active = ($active_parent_id === $parent_id);
                ?>
                <!-- Grid Wrap View (Children) -->
                <div id="geo-children-view-<?php echo (int) $parent_id; ?>"
                    class="geo-view <?php echo $is_active ? '' : 'hidden'; ?> flex flex-col transition-opacity duration-300">
                    <div class="filter-wrapper relative w-full">
                        <div class="filter-container flex flex-wrap gap-3 md:gap-4 pb-space-xs pt-1 max-h-[190px] overflow-hidden transition-[max-height] duration-500 ease-in-out"
                            data-collapsed-max="190">
                            <!-- Item 1: Back Button -->
                            <button type="button" onclick="showCountries()"
                                class="group flex-none w-28 md:w-32 flex flex-col items-center justify-center p-3 md:p-4 border border-surface-container-high rounded-2xl hover:border-surface-container-highest hover:shadow-xl transition-all duration-300 focus:outline-none">
                                <div
                                    class="w-12 h-12 rounded-full flex items-center justify-center text-on-surface-variant group-hover:text-primary mb-2 shadow-sm transition-colors border border-surface-container-high group-hover:border-primary">
                                    <span class="text-xl">&rarr;</span>
                                </div>
                                <span
                                    class="text-xs font-bold text-on-surface-variant group-hover:text-primary transition-colors">
                                    <?php esc_html_e('المناطق', 'hello-elementor-child'); ?>
                                </span>
                            </button>
                            <!-- Item 2: All [Parent] Link -->
                            <a href="<?php echo esc_url($parent_url); ?>"
                                class="group flex-none w-28 md:w-32 flex flex-col items-center p-3 md:p-4 border border-surface-container-high rounded-2xl hover:border-surface-container-highest hover:shadow-xl hover:-translate-y-1 transition-all duration-300 ease-out relative overflow-hidden text-center">
                                <div
                                    class="w-12 h-12 md:w-14 md:h-14 aspect-square mb-2 md:mb-3 rounded-xl md:rounded-2xl flex items-center justify-center overflow-hidden border border-surface-container-high group-hover:border-primary transition-colors duration-300 shadow-sm relative z-10">
                                    <?php if (!empty($parent_image) && is_array($parent_image)): ?>
                                        <?php echo wp_get_attachment_image($parent_image['ID'], 'thumbnail', false, [
                                            'class' => 'w-full h-full object-cover group-hover:scale-110 transition-transform duration-500 opacity-80 group-hover:opacity-100',
                                        ]); ?>
                                    <?php else: ?>
                                        <span class="text-xl md:text-2xl font-bold text-on-surface-variant/40 uppercase">
                                            <?php echo esc_html(mb_substr($parent_term->name, 0, 1)); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h3
                                    class="text-sm font-bold text-on-background text-center mb-1 line-clamp-2 leading-snug group-hover:text-primary transition-colors relative z-10">
                                    <?php echo esc_html(sprintf(__('كل %s', 'hello-elementor-child'), $parent_term->name)); ?>
                                </h3>
                                <div class="mt-auto relative z-10 pt-2 w-full flex justify-center">
                                    <span
                                        class="inline-flex items-center justify-center gap-1 text-[11px] font-bold tracking-wide text-white bg-primary-container border border-primary/20 px-2.5 py-1 rounded-full whitespace-nowrap group-hover:bg-primary group-hover:text-on-primary transition-colors">
                                        <span><?php echo esc_html($parent_data['count']); ?></span>
                                        <span><?php esc_html_e('الإجمالي', 'hello-elementor-child'); ?></span>
                                    </span>
                                </div>
                            </a>
                            <!-- Items 3+: Child Terms -->
                            <?php foreach ($children as $data):
                                $term = $data['term'];
                                $count = $data['count'];
                                $url = $build_geo_url($term->slug);
                                $image = get_field('taxonomy_image', $term);
                                ?>
                                <a href="<?php echo esc_url($url); ?>"
                                    class="group flex-none w-28 md:w-32 flex flex-col items-center p-3 md:p-4 border border-surface-container-high rounded-2xl hover:border-surface-container-highest hover:shadow-xl hover:-translate-y-1 transition-all duration-300 ease-out text-center">
                                    <div
                                        class="w-12 h-12 md:w-14 md:h-14 aspect-square mb-2 md:mb-3 rounded-xl md:rounded-2xl flex items-center justify-center overflow-hidden border border-surface-container-high group-hover:border-primary transition-colors duration-300 shadow-sm">
                                        <?php if (!empty($image) && is_array($image)): ?>
                                            <?php echo wp_get_attachment_image($image['ID'], 'thumbnail', false, [
                                                'class' => 'w-full h-full object-cover group-hover:scale-110 transition-transform duration-500',
                                            ]); ?>
                                        <?php else: ?>
                                            <span class="text-xl md:text-2xl font-bold text-on-surface-variant/40 uppercase">
                                                <?php echo esc_html(mb_substr($term->name, 0, 1)); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <h3
                                        class="text-sm font-bold text-on-background text-center mb-2 line-clamp-2 leading-snug group-hover:text-primary transition-colors">
                                        <?php echo esc_html($term->name); ?>
                                    </h3>
                                    <div class="mt-auto pt-1">
                                        <span
                                            class="text-xs font-bold tracking-wide text-on-surface-variant px-3 py-1 rounded-full group-hover:bg-primary-container group-hover:text-on-primary-container transition-colors">
                                            <?php echo esc_html($count); ?>
                                            <?php esc_html_e('مادة', 'hello-elementor-child'); ?>
                                        </span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
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
            <?php endforeach; ?>
        </div>
    </div>
    <script>
        function openCities(parentId) {
            document.querySelectorAll('.geo-view').forEach(view => view.classList.add('hidden'));
            const targetView = document.getElementById('geo-children-view-' + parentId);
            if (targetView) {
                targetView.classList.remove('hidden');
                window.dispatchEvent(new Event('resize'));
            }
        }
        function showCountries() {
            document.querySelectorAll('.geo-view').forEach(view => view.classList.add('hidden'));
            const parentView = document.getElementById('geo-parents-view');
            if (parentView) {
                parentView.classList.remove('hidden');
                window.dispatchEvent(new Event('resize'));
            }
        }
    </script>
</section>