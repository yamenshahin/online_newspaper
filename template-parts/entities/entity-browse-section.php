<?php
/**
 * Shared entity browse: Main tabs → Sub tabs → Entity pills + search.
 * No show-more / max-height (Geographic only uses that).
 *
 * $args: taxonomy, section_title, subtitle, department
 */

$taxonomy = $args['taxonomy'] ?? '';
$section_title = $args['section_title'] ?? '';
$subtitle = $args['subtitle'] ?? '';
$department = $args['department'] ?? null;

if ($taxonomy === '') {
    return;
}

$tree = get_entity_browse_tree(
    $taxonomy,
    $department instanceof WP_Term ? $department : null
);

if (empty($tree)) {
    return;
}

$dept_id = $department instanceof WP_Term ? (int) $department->term_id : 0;
$main_ids = array_keys($tree);
$first_main_id = $main_ids[0] ?? 0;
?>

<section
    class="entity-browse w-full px-margin-mobile lg:px-margin py-space-lg border-b border-surface-container-highest/30 last:border-0"
    data-taxonomy="<?php echo esc_attr($taxonomy); ?>" data-department="<?php echo esc_attr((string) $dept_id); ?>">

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

        <div class="entity-search relative w-full max-w-md">
            <div class="relative flex items-center w-full">
                <span
                    class="material-symbols-outlined absolute start-3.5 text-on-surface-variant/70 text-[20px] pointer-events-none select-none">search</span>
                <input type="search"
                    class="entity-search-input w-full rounded-full border border-surface-container-highest bg-surface-container-lowest py-2.5 !ps-11 !pe-4 text-sm text-on-background placeholder:text-on-surface-variant/60 shadow-xs transition-all duration-200 focus:border-primary focus:bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 [&::-webkit-search-cancel-button]:appearance-none"
                    placeholder="<?php esc_attr_e('ابحث عن جهة…', 'hello-elementor-child'); ?>" autocomplete="off" />
            </div>
        </div>

        <!-- Main tabs -->
        <div class="entity-main-tabs flex flex-wrap gap-2 border-b border-surface-container-highest/40 pb-2"
            role="tablist">
            <?php foreach ($tree as $main_id => $main): ?>
                <button type="button"
                    class="entity-main-tab px-space-md py-space-xs rounded-full font-label-pill text-label-pill transition-colors <?php echo (int) $main_id === (int) $first_main_id ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface hover:bg-surface-container-high'; ?>"
                    data-main-id="<?php echo esc_attr((string) $main_id); ?>"
                    aria-selected="<?php echo (int) $main_id === (int) $first_main_id ? 'true' : 'false'; ?>">
                    <?php echo esc_html($main['name']); ?>
                    <?php if (!empty($main['count'])): ?>
                        <span class="opacity-80 text-[11px]">(<?php echo esc_html((string) $main['count']); ?>)</span>
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>

        <?php foreach ($tree as $main_id => $main):
            $is_first_main = ((int) $main_id === (int) $first_main_id);
            $subs = [];
            $leaf_under_main = [];

            if (!empty($main['children'])) {
                foreach ($main['children'] as $cid => $child) {
                    if (!empty($child['is_leaf'])) {
                        $leaf_under_main[$cid] = $child;
                    } else {
                        $subs[$cid] = $child;
                    }
                }
            }
            $sub_ids = array_keys($subs);
            $default_sub = $sub_ids[0] ?? 0;
            ?>

            <div class="entity-main-panel <?php echo $is_first_main ? '' : 'hidden'; ?>"
                data-main-id="<?php echo esc_attr((string) $main_id); ?>">

                <?php if (!empty($subs)): ?>
                    <div class="entity-sub-tabs flex flex-wrap gap-2 mt-2 mb-3" role="tablist">
                        <?php foreach ($subs as $sub_id => $sub): ?>
                            <button type="button"
                                class="entity-sub-tab px-space-sm py-1.5 rounded-full text-sm font-semibold transition-colors <?php echo (int) $sub_id === (int) $default_sub ? 'bg-primary-container text-on-primary-container' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container'; ?>"
                                data-main-id="<?php echo esc_attr((string) $main_id); ?>"
                                data-sub-id="<?php echo esc_attr((string) $sub_id); ?>"
                                aria-selected="<?php echo (int) $sub_id === (int) $default_sub ? 'true' : 'false'; ?>">
                                <?php echo esc_html($sub['name']); ?>
                                <?php if (!empty($sub['count'])): ?>
                                    <span class="opacity-80 text-[11px]">(<?php echo esc_html((string) $sub['count']); ?>)</span>
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($subs)): ?>
                    <?php foreach ($subs as $sub_id => $sub): ?>
                        <div class="entity-sub-panel flex flex-wrap gap-2 <?php echo (int) $sub_id === (int) $default_sub ? '' : 'hidden'; ?>"
                            data-main-id="<?php echo esc_attr((string) $main_id); ?>"
                            data-sub-id="<?php echo esc_attr((string) $sub_id); ?>">
                            <?php if (!empty($sub['children'])): ?>
                                <?php foreach ($sub['children'] as $entity): ?>
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
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php elseif (!empty($leaf_under_main)): ?>
                    <div class="entity-sub-panel flex flex-wrap gap-2"
                        data-main-id="<?php echo esc_attr((string) $main_id); ?>" data-sub-id="0">
                        <?php foreach ($leaf_under_main as $entity): ?>
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
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>