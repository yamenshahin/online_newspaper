<?php
/**
 * Combined entity browse:
 * L1: الجهات الحكومية | القطاع الخاص
 * Gov:  L2 subs → L3 entities  (skip single root "الجهات الحكومية")
 * Priv: L2 mains → L3 subs → L4 entities
 *
 * $args: section_title, subtitle, department
 * (taxonomy arg ignored — both trees load here)
 */

$section_title = $args['section_title'] ?? '';
$subtitle = $args['subtitle'] ?? '';
$department = $args['department'] ?? null;

// Only one combined block per request (gov + private ACF layouts)
static $entity_browse_rendered = false;
if ($entity_browse_rendered) {
    return;
}
$entity_browse_rendered = true;

$dept_term = $department instanceof WP_Term ? $department : null;
$dept_id = $dept_term ? (int) $dept_term->term_id : 0;

$gov_tree = get_entity_browse_tree('government_entity', $dept_term);
$prv_tree = get_entity_browse_tree('private_entity', $dept_term);

if (empty($gov_tree) && empty($prv_tree)) {
    return;
}

/**
 * Gov: if single root, use its children as L2 (subs).
 * @return array{subs: array, leaves_under_root: array}
 */
$gov_level2 = static function (array $tree): array {
    $subs = [];
    $leaves = [];

    if (count($tree) === 1) {
        $root = reset($tree);
        foreach ($root['children'] ?? [] as $cid => $child) {
            if (!empty($child['is_leaf'])) {
                $leaves[$cid] = $child;
            } else {
                $subs[$cid] = $child;
            }
        }
        return ['subs' => $subs, 'leaves_under_root' => $leaves];
    }

    // Multiple roots: treat each root as a "sub" tab (unusual for gov)
    foreach ($tree as $id => $node) {
        if (!empty($node['is_leaf'])) {
            $leaves[$id] = $node;
        } else {
            $subs[$id] = $node;
        }
    }
    return ['subs' => $subs, 'leaves_under_root' => $leaves];
};

$gov_pack = $gov_level2($gov_tree);
$gov_subs = $gov_pack['subs'];
$gov_root_leaves = $gov_pack['leaves_under_root'];
$gov_sub_ids = array_keys($gov_subs);
$gov_default_sub = $gov_sub_ids[0] ?? 0;

$prv_main_ids = array_keys($prv_tree);
$prv_default_main = $prv_main_ids[0] ?? 0;

$default_type = !empty($gov_tree) ? 'government' : 'private';
$section_title = $section_title ?: __('الجهات', 'hello-elementor-child');
?>

<section
    class="entity-browse entity-browse--combined w-full px-margin-mobile lg:px-margin py-space-lg border-b border-surface-container-highest/30 last:border-0"
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


        <!-- L1: Type tabs + search at end of bar -->
        <div
            class="entity-type-bar flex flex-wrap items-center gap-3 border-b border-surface-container-highest/40 pb-2">

            <div class="entity-type-tabs flex flex-wrap gap-2 flex-1 min-w-0" role="tablist">
                <?php if (!empty($gov_tree)): ?>
                    <button type="button"
                        class="entity-type-tab px-space-md py-space-xs rounded-full font-label-pill text-label-pill transition-colors <?php echo $default_type === 'government' ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface hover:bg-surface-container-high'; ?>"
                        data-entity-type="government"
                        aria-selected="<?php echo $default_type === 'government' ? 'true' : 'false'; ?>">
                        <?php esc_html_e('الجهات الحكومية', 'hello-elementor-child'); ?>
                    </button>
                <?php endif; ?>
                <?php if (!empty($prv_tree)): ?>
                    <button type="button"
                        class="entity-type-tab px-space-md py-space-xs rounded-full font-label-pill text-label-pill transition-colors <?php echo $default_type === 'private' ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface hover:bg-surface-container-high'; ?>"
                        data-entity-type="private"
                        aria-selected="<?php echo $default_type === 'private' ? 'true' : 'false'; ?>">
                        <?php esc_html_e('القطاع الخاص', 'hello-elementor-child'); ?>
                    </button>
                <?php endif; ?>
            </div>

            <div class="entity-search relative w-full sm:w-56 md:w-64 shrink-0">
                <div class="relative flex items-center w-full">
                    <span
                        class="material-symbols-outlined absolute start-3 text-on-surface-variant/70 text-[18px] pointer-events-none select-none">search</span>
                    <input type="search"
                        class="entity-search-input w-full rounded-full border border-surface-container-highest bg-surface-container-lowest py-2 !ps-10 !pe-3 text-sm text-on-background placeholder:text-on-surface-variant/60 shadow-xs transition-all focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 [&::-webkit-search-cancel-button]:appearance-none"
                        placeholder="<?php esc_attr_e('ابحث عن جهة…', 'hello-elementor-child'); ?>"
                        autocomplete="off" />
                </div>
            </div>
        </div>

        <?php if (!empty($gov_tree)): ?>
            <!-- GOVERNMENT: L2 subs → L3 entities -->
            <div class="entity-type-panel <?php echo $default_type === 'government' ? '' : 'hidden'; ?>"
                data-entity-type="government">

                <?php if (!empty($gov_subs)): ?>
                    <div class="entity-sub-tabs flex flex-wrap gap-2 mb-3" role="tablist">
                        <?php foreach ($gov_subs as $sub_id => $sub): ?>
                            <button type="button"
                                class="entity-sub-tab px-space-sm py-1.5 rounded-full text-sm font-semibold transition-colors <?php echo (int) $sub_id === (int) $gov_default_sub ? 'bg-primary-container text-on-primary-container' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container'; ?>"
                                data-entity-type="government" data-sub-id="<?php echo esc_attr((string) $sub_id); ?>"
                                aria-selected="<?php echo (int) $sub_id === (int) $gov_default_sub ? 'true' : 'false'; ?>">
                                <?php echo esc_html($sub['name']); ?>
                                <?php if (!empty($sub['count'])): ?>
                                    <span class="opacity-80 text-[11px]">(<?php echo esc_html((string) $sub['count']); ?>)</span>
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <?php foreach ($gov_subs as $sub_id => $sub): ?>
                        <div class="entity-sub-panel flex flex-wrap gap-2 p-4 md:p-5 rounded-2xl bg-primary <?php echo (int) $sub_id === (int) $gov_default_sub ? '' : 'hidden'; ?>"
                            data-entity-type="government" data-sub-id="<?php echo esc_attr((string) $sub_id); ?>">
                            <?php foreach ($sub['children'] ?? [] as $entity): ?>
                                <?php if (empty($entity['is_leaf'])) {
                                    continue;
                                } ?>
                                <a href="<?php echo esc_url($entity['link']); ?>"
                                    class="entity-pill inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-sm border border-surface-container-highest bg-surface-container-lowest hover:border-primary hover:text-primary transition-colors"
                                    data-entity-name="<?php echo esc_attr(mb_strtolower($entity['name'])); ?>"
                                    data-entity-type="government">
                                    <?php echo esc_html($entity['name']); ?>
                                    <?php if (!empty($entity['count'])): ?>
                                        <span
                                            class="text-[11px] font-bold text-on-surface-variant">(<?php echo esc_html((string) $entity['count']); ?>)</span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>

                <?php elseif (!empty($gov_root_leaves)): ?>
                    <div class="entity-sub-panel flex flex-wrap gap-2 p-4 md:p-5 rounded-2xl bg-primary"
                        data-entity-type="government" data-sub-id="0">
                        <?php foreach ($gov_root_leaves as $entity): ?>
                            <a href="<?php echo esc_url($entity['link']); ?>"
                                class="entity-pill inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-sm border border-surface-container-highest bg-surface-container-lowest hover:border-primary hover:text-primary transition-colors"
                                data-entity-name="<?php echo esc_attr(mb_strtolower($entity['name'])); ?>"
                                data-entity-type="government">
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
        <?php endif; ?>

        <?php if (!empty($prv_tree)): ?>
            <!-- PRIVATE: L2 mains → L3 subs → L4 entities -->
            <div class="entity-type-panel <?php echo $default_type === 'private' ? '' : 'hidden'; ?>"
                data-entity-type="private">

                <div class="entity-main-tabs flex flex-wrap gap-2 border-b border-surface-container-highest/30 pb-2 mb-2"
                    role="tablist">
                    <?php foreach ($prv_tree as $main_id => $main): ?>
                        <button type="button"
                            class="entity-main-tab px-space-md py-space-xs rounded-full font-label-pill text-label-pill transition-colors <?php echo (int) $main_id === (int) $prv_default_main ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface hover:bg-surface-container-high'; ?>"
                            data-entity-type="private" data-main-id="<?php echo esc_attr((string) $main_id); ?>"
                            aria-selected="<?php echo (int) $main_id === (int) $prv_default_main ? 'true' : 'false'; ?>">
                            <?php echo esc_html($main['name']); ?>
                            <?php if (!empty($main['count'])): ?>
                                <span class="opacity-80 text-[11px]">(<?php echo esc_html((string) $main['count']); ?>)</span>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <?php foreach ($prv_tree as $main_id => $main):
                    $is_first = ((int) $main_id === (int) $prv_default_main);
                    $subs = [];
                    $leaves = [];
                    foreach ($main['children'] ?? [] as $cid => $child) {
                        if (!empty($child['is_leaf'])) {
                            $leaves[$cid] = $child;
                        } else {
                            $subs[$cid] = $child;
                        }
                    }
                    $sub_ids = array_keys($subs);
                    $default_sub = $sub_ids[0] ?? 0;
                    ?>
                    <div class="entity-main-panel <?php echo $is_first ? '' : 'hidden'; ?>" data-entity-type="private"
                        data-main-id="<?php echo esc_attr((string) $main_id); ?>">

                        <?php if (!empty($subs)): ?>
                            <div class="entity-sub-tabs flex flex-wrap gap-2 mb-3" role="tablist">
                                <?php foreach ($subs as $sub_id => $sub): ?>
                                    <button type="button"
                                        class="entity-sub-tab px-space-sm py-1.5 rounded-full text-sm font-semibold transition-colors <?php echo (int) $sub_id === (int) $default_sub ? 'bg-primary-container text-on-primary-container' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container'; ?>"
                                        data-entity-type="private" data-main-id="<?php echo esc_attr((string) $main_id); ?>"
                                        data-sub-id="<?php echo esc_attr((string) $sub_id); ?>"
                                        aria-selected="<?php echo (int) $sub_id === (int) $default_sub ? 'true' : 'false'; ?>">
                                        <?php echo esc_html($sub['name']); ?>
                                        <?php if (!empty($sub['count'])): ?>
                                            <span class="opacity-80 text-[11px]">(<?php echo esc_html((string) $sub['count']); ?>)</span>
                                        <?php endif; ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>

                            <?php foreach ($subs as $sub_id => $sub): ?>
                                <div class="entity-sub-panel flex flex-wrap gap-2 p-4 md:p-5 rounded-2xl bg-primary <?php echo (int) $sub_id === (int) $default_sub ? '' : 'hidden'; ?>"
                                    data-entity-type="private" data-main-id="<?php echo esc_attr((string) $main_id); ?>"
                                    data-sub-id="<?php echo esc_attr((string) $sub_id); ?>">
                                    <?php foreach ($sub['children'] ?? [] as $entity): ?>
                                        <?php if (empty($entity['is_leaf'])) {
                                            continue;
                                        } ?>
                                        <a href="<?php echo esc_url($entity['link']); ?>"
                                            class="entity-pill inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-sm border border-surface-container-highest bg-surface-container-lowest hover:border-primary hover:text-primary transition-colors"
                                            data-entity-name="<?php echo esc_attr(mb_strtolower($entity['name'])); ?>"
                                            data-entity-type="private">
                                            <?php echo esc_html($entity['name']); ?>
                                            <?php if (!empty($entity['count'])): ?>
                                                <span
                                                    class="text-[11px] font-bold text-on-surface-variant">(<?php echo esc_html((string) $entity['count']); ?>)</span>
                                            <?php endif; ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>

                        <?php elseif (!empty($leaves)): ?>
                            <div class="entity-sub-panel flex flex-wrap gap-2 p-4 md:p-5 rounded-2xl bg-primary"
                                data-entity-type="private" data-main-id="<?php echo esc_attr((string) $main_id); ?>"
                                data-sub-id="0">
                                <?php foreach ($leaves as $entity): ?>
                                    <a href="<?php echo esc_url($entity['link']); ?>"
                                        class="entity-pill inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-sm border border-surface-container-highest bg-surface-container-lowest hover:border-primary hover:text-primary transition-colors"
                                        data-entity-name="<?php echo esc_attr(mb_strtolower($entity['name'])); ?>"
                                        data-entity-type="private">
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
        <?php endif; ?>
    </div>
</section>