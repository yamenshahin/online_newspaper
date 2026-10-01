<?php
/**
 * Government entities — full tree (Main → Sub → Entity) + entity search + expandable "Show More".
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

$section_title = $section_title ?: __('الجهات الحكومية', 'hello-elementor-child');
$target_taxonomy = 'government_entity';

$tree = get_entity_browse_tree(
    $target_taxonomy,
    $department instanceof WP_Term ? $department : null
);

if (empty($tree)) {
    return;
}

$dept_id = $department instanceof WP_Term ? (int) $department->term_id : 0;
?>
<section
    class="entity-browse w-full px-margin-mobile lg:px-margin py-space-lg border-b border-surface-container-highest/30 last:border-0"
    data-taxonomy="<?php echo esc_attr($target_taxonomy); ?>"
    data-department="<?php echo esc_attr((string) $dept_id); ?>">

    <div class="flex flex-col gap-space-md">

        <div class="flex flex-col items-start gap-space-xs">
            <span class="font-label-caps text-label-caps text-primary uppercase tracking-widest">
                <?php echo esc_html($section_title); ?>
            </span>
            <?php if ($subtitle): ?>
                <h3 class="font-headline-sm text-headline-sm text-on-background mt-1">
                    <?php echo esc_html($subtitle); ?>
                </h3>
            <?php endif; ?>
        </div>

        <!-- Entity Search Input -->
        <div class="entity-search relative w-full max-w-xl">
            <span
                class="material-symbols-outlined absolute top-1/2 start-3 -translate-y-1/2 text-on-surface-variant text-[20px] pointer-events-none">search</span>
            <input type="search"
                class="entity-search-input w-full rounded-full border border-surface-container-highest bg-surface-container-lowest py-2.5 pe-4 ps-11 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30"
                placeholder="<?php esc_attr_e('ابحث عن جهة…', 'hello-elementor-child'); ?>" autocomplete="off" />
        </div>

        <!-- Tighter Collapsed Height (100px) -->
        <div class="filter-wrapper relative w-full">
            <div class="filter-container entity-tree flex flex-col gap-space-lg max-h-[100px] overflow-hidden transition-[max-height] duration-500 ease-in-out"
                data-collapsed-max="100">
                <?php foreach ($tree as $main): ?>
                    <div class="entity-main">
                        <h3 class="text-base font-bold text-on-background mb-3">
                            <?php echo esc_html($main['name']); ?>
                            <?php if ($main['count']): ?>
                                <span
                                    class="text-xs font-semibold text-on-surface-variant">(<?php echo esc_html((string) $main['count']); ?>)</span>
                            <?php endif; ?>
                        </h3>

                        <?php if (!empty($main['children'])): ?>
                            <div class="flex flex-col gap-space-md ms-1 border-s border-surface-container-highest/40 ps-4">
                                <?php foreach ($main['children'] as $child): ?>
                                    <?php if (!empty($child['is_leaf'])): ?>
                                        <a href="<?php echo esc_url($child['link']); ?>"
                                            class="entity-pill inline-flex px-3 py-1.5 rounded-full text-sm border border-surface-container-highest hover:border-primary hover:text-primary transition-colors"
                                            data-entity-name="<?php echo esc_attr(mb_strtolower($child['name'])); ?>">
                                            <?php echo esc_html($child['name']); ?>
                                        </a>
                                    <?php else: ?>
                                        <div class="entity-sub">
                                            <h4 class="text-sm font-semibold text-on-surface-variant mb-2">
                                                <?php echo esc_html($child['name']); ?>
                                            </h4>
                                            <div class="flex flex-wrap gap-2">
                                                <?php foreach ($child['children'] as $entity): ?>
                                                    <?php if (empty($entity['is_leaf'])) {
                                                        continue;
                                                    } ?>
                                                    <a href="<?php echo esc_url($entity['link']); ?>"
                                                        class="entity-pill inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-sm border border-surface-container-highest bg-surface-container-lowest hover:border-primary hover:text-primary transition-colors"
                                                        data-entity-name="<?php echo esc_attr(mb_strtolower($entity['name'])); ?>">
                                                        <?php echo esc_html($entity['name']); ?>
                                                        <?php if (!empty($entity['count'])): ?>
                                                            <span
                                                                class="text-[11px] font-bold text-on-surface-variant">(<?php echo esc_html((string) $entity['count']); ?>)</span>
                                                        <?php endif; ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Fade Overlay -->
            <div
                class="filter-fade-overlay absolute bottom-0 left-0 w-full h-[60px] bg-gradient-to-t from-background to-transparent pointer-events-none transition-opacity duration-300">
            </div>
        </div>

        <!-- Toggle Button -->
        <div class="flex justify-center w-full mt-2">
            <button type="button"
                class="filter-toggle-btn px-space-md py-space-xs rounded-full bg-surface-container border border-surface-container-highest text-on-surface font-label-pill text-label-pill transition-colors hover:bg-surface-container-high hidden items-center gap-2">
                <span class="toggle-text"><?php esc_html_e('عرض المزيد', 'hello-elementor-child'); ?></span>
                <span class="material-symbols-outlined text-[16px] toggle-icon">expand_more</span>
            </button>
        </div>

    </div>
</section>