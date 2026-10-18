<?php
/**
 * Combined entity browse:
 * L1: الجهات الحكومية | القطاع الخاص | الفعاليات
 * Gov:  L2 subs → entities
 * Priv: L2 mains → L3 subs → entities
 * Event: L2 categories → entities
 *
 * Only the tabs are rendered here. Entities are fetched from
 * /hello/v1/entity-nav when a group is opened (the first visible group is
 * preloaded), and search uses /hello/v1/entity-search. See initEntityBrowse()
 * in assets/js/filters.js. No post counts are shown.
 *
 * $args: section_title, subtitle, department
 */
$section_title = $args['section_title'] ?? '';
$subtitle = $args['subtitle'] ?? '';
$department = $args['department'] ?? null;

// Render once per page: the JS and the search box assume a single instance.
if (!entity_browse_claim_render()) {
    return;
}

$dept_term = $department instanceof WP_Term ? $department : null;
$dept_id = $dept_term ? (int) $dept_term->term_id : 0;

$gov_tree = get_entity_browse_tree('government_entity');
$prv_tree = get_entity_browse_tree('private_entity');
$evt_tree = get_entity_browse_tree('event');

$gov_root_leaves = empty($gov_tree) && entity_taxonomy_has_root_leaves('government_entity');
$prv_root_leaves = empty($prv_tree) && entity_taxonomy_has_root_leaves('private_entity');
$evt_root_leaves = empty($evt_tree) && entity_taxonomy_has_root_leaves('event');

$has_gov = !empty($gov_tree) || $gov_root_leaves;
$has_prv = !empty($prv_tree) || $prv_root_leaves;
$has_evt = !empty($evt_tree) || $evt_root_leaves;

if (!$has_gov && !$has_prv && !$has_evt) {
    return;
}

/*
 * Government: with a single root, its sub-groups are the tabs and its direct
 * leaves form one panel (sub-id 0). With several roots, the roots are the tabs.
 */
$gov_subs = [];
$gov_leaf_parent = 0;
if (count($gov_tree) === 1) {
    $root = reset($gov_tree);
    $gov_subs = $root['children'];
    $gov_leaf_parent = (int) $root['id'];
} else {
    $gov_subs = $gov_tree;
}
$gov_default_sub = array_key_first($gov_subs) ?? 0;

$prv_default_main = array_key_first($prv_tree) ?? 0;
$evt_default_id = array_key_first($evt_tree) ?? 0;

if ($has_gov) {
    $default_type = 'government';
} elseif ($has_prv) {
    $default_type = 'private';
} else {
    $default_type = 'event';
}

$section_title = $section_title ?: __('الجهات', 'hello-elementor-child');

$pill_class = 'entity-pill inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-sm border border-surface-container-highest bg-surface-container-lowest hover:border-primary hover:text-primary transition-colors';

$i18n = [
    'loading' => __('جارٍ التحميل…', 'hello-elementor-child'),
    'empty' => __('لا توجد جهات في هذه المجموعة', 'hello-elementor-child'),
    'error' => __('تعذر التحميل، اضغط لإعادة المحاولة', 'hello-elementor-child'),
    'no_results' => __('لا توجد نتائج', 'hello-elementor-child'),
    'truncated' => __('النتائج كثيرة، اكتب كلمة أدق لتضييق البحث', 'hello-elementor-child'),
    'labels' => [
        'government_entity' => __('الجهات الحكومية', 'hello-elementor-child'),
        'private_entity' => __('القطاع الخاص', 'hello-elementor-child'),
        'event' => __('الفعاليات', 'hello-elementor-child'),
    ],
];

/** Only the first panel the visitor sees is rendered with its entities. */
$preloaded = false;

/**
 * Leaf panel: entities under $parent_id. Empty until opened unless preloaded.
 */
$render_leaf_panel = static function (string $taxonomy, int $parent_id, string $type, bool $visible, int $main_id = 0, int $sub_id = 0) use ($pill_class, &$preloaded): void {
    $preload = $visible && !$preloaded;
    if ($preload) {
        $preloaded = true;
    }
    ?>
    <div class="entity-sub-panel flex flex-wrap gap-2 p-4 md:p-5 rounded-2xl bg-primary <?php echo $visible ? '' : 'hidden'; ?>"
        data-entity-type="<?php echo esc_attr($type); ?>"
        <?php if ($main_id): ?>data-main-id="<?php echo esc_attr((string) $main_id); ?>"<?php endif; ?>
        data-sub-id="<?php echo esc_attr((string) $sub_id); ?>"
        data-taxonomy="<?php echo esc_attr($taxonomy); ?>"
        data-parent="<?php echo esc_attr((string) $parent_id); ?>"
        data-loaded="<?php echo $preload ? '1' : '0'; ?>">
        <?php if ($preload): ?>
            <?php foreach (get_entity_leaves_under($taxonomy, $parent_id) as $entity): ?>
                <a href="<?php echo esc_url($entity['link']); ?>" class="<?php echo esc_attr($pill_class); ?>">
                    <?php echo esc_html($entity['name']); ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php
};

/**
 * Sub-tab buttons for a list of groups.
 */
$render_sub_tabs = static function (array $groups, int $default_id, string $type, int $main_id = 0): void {
    ?>
    <div class="entity-sub-tabs flex flex-wrap gap-2 mb-3" role="tablist">
        <?php foreach ($groups as $sub_id => $sub): ?>
            <button type="button"
                class="entity-sub-tab px-space-sm py-1.5 rounded-full text-sm font-semibold transition-colors <?php echo (int) $sub_id === $default_id ? 'bg-primary-container text-on-primary-container' : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container'; ?>"
                data-entity-type="<?php echo esc_attr($type); ?>"
                <?php if ($main_id): ?>data-main-id="<?php echo esc_attr((string) $main_id); ?>"<?php endif; ?>
                data-sub-id="<?php echo esc_attr((string) $sub_id); ?>"
                aria-selected="<?php echo (int) $sub_id === $default_id ? 'true' : 'false'; ?>">
                <?php echo esc_html($sub['name']); ?>
            </button>
        <?php endforeach; ?>
    </div>
    <?php
};
?>
<section
    class="entity-browse entity-browse--combined w-full px-margin-mobile lg:px-margin py-space-lg border-b border-surface-container-highest/30 last:border-0"
    data-department="<?php echo esc_attr((string) $dept_id); ?>"
    data-pill-class="<?php echo esc_attr($pill_class); ?>"
    data-i18n="<?php echo esc_attr(wp_json_encode($i18n, JSON_UNESCAPED_UNICODE)); ?>">
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

        <div
            class="entity-type-bar flex flex-wrap items-center gap-3 border-b border-surface-container-highest/40 pb-2">
            <div class="entity-type-tabs flex flex-wrap gap-2 flex-1 min-w-0" role="tablist">
                <?php if ($has_gov): ?>
                    <button type="button"
                        class="entity-type-tab px-space-md py-space-xs rounded-full font-label-pill text-label-pill transition-colors <?php echo $default_type === 'government' ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface hover:bg-surface-container-high'; ?>"
                        data-entity-type="government"
                        aria-selected="<?php echo $default_type === 'government' ? 'true' : 'false'; ?>">
                        <?php echo esc_html($i18n['labels']['government_entity']); ?>
                    </button>
                <?php endif; ?>
                <?php if ($has_prv): ?>
                    <button type="button"
                        class="entity-type-tab px-space-md py-space-xs rounded-full font-label-pill text-label-pill transition-colors <?php echo $default_type === 'private' ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface hover:bg-surface-container-high'; ?>"
                        data-entity-type="private"
                        aria-selected="<?php echo $default_type === 'private' ? 'true' : 'false'; ?>">
                        <?php echo esc_html($i18n['labels']['private_entity']); ?>
                    </button>
                <?php endif; ?>
                <?php if ($has_evt): ?>
                    <button type="button"
                        class="entity-type-tab px-space-md py-space-xs rounded-full font-label-pill text-label-pill transition-colors <?php echo $default_type === 'event' ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface hover:bg-surface-container-high'; ?>"
                        data-entity-type="event"
                        aria-selected="<?php echo $default_type === 'event' ? 'true' : 'false'; ?>">
                        <?php echo esc_html($i18n['labels']['event']); ?>
                    </button>
                <?php endif; ?>
            </div>
            <div class="entity-search relative w-full sm:w-56 md:w-64 shrink-0">
                <div class="relative flex items-center w-full">
                    <span
                        class="material-symbols-outlined absolute start-3 text-on-surface-variant/70 text-[18px] pointer-events-none select-none">search</span>
                    <input type="search"
                        class="entity-search-input w-full rounded-full border border-surface-container-highest bg-surface-container-lowest py-2 !ps-10 !pe-3 text-sm text-on-background placeholder:text-on-surface-variant/60 shadow-xs transition-all focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 [&::-webkit-search-cancel-button]:appearance-none"
                        placeholder="<?php esc_attr_e('ابحث…', 'hello-elementor-child'); ?>" autocomplete="off" />
                </div>
            </div>
        </div>

        <?php /* Filled by JS while searching; the type panels are hidden meanwhile. */ ?>
        <div class="entity-search-results flex flex-col gap-space-sm hidden" aria-live="polite"></div>

        <?php if ($has_gov): ?>
            <div class="entity-type-panel <?php echo $default_type === 'government' ? '' : 'hidden'; ?>"
                data-entity-type="government">
                <?php if (!empty($gov_subs)): ?>
                    <?php $render_sub_tabs($gov_subs, (int) $gov_default_sub, 'government'); ?>
                    <?php foreach ($gov_subs as $sub_id => $sub): ?>
                        <?php $render_leaf_panel(
                            'government_entity',
                            (int) $sub_id,
                            'government',
                            $default_type === 'government' && (int) $sub_id === (int) $gov_default_sub,
                            0,
                            (int) $sub_id
                        ); ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php $render_leaf_panel('government_entity', $gov_leaf_parent, 'government', $default_type === 'government'); ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($has_prv): ?>
            <div class="entity-type-panel <?php echo $default_type === 'private' ? '' : 'hidden'; ?>"
                data-entity-type="private">
                <?php if (!empty($prv_tree)): ?>
                    <div class="entity-main-tabs flex flex-wrap gap-2 border-b border-surface-container-highest/30 pb-2 mb-2"
                        role="tablist">
                        <?php foreach ($prv_tree as $main_id => $main): ?>
                            <button type="button"
                                class="entity-main-tab px-space-md py-space-xs rounded-full font-label-pill text-label-pill transition-colors <?php echo (int) $main_id === (int) $prv_default_main ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface hover:bg-surface-container-high'; ?>"
                                data-entity-type="private" data-main-id="<?php echo esc_attr((string) $main_id); ?>"
                                aria-selected="<?php echo (int) $main_id === (int) $prv_default_main ? 'true' : 'false'; ?>">
                                <?php echo esc_html($main['name']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <?php foreach ($prv_tree as $main_id => $main):
                        $is_main_default = (int) $main_id === (int) $prv_default_main;
                        $main_visible = $default_type === 'private' && $is_main_default;
                        $subs = $main['children'];
                        $default_sub = array_key_first($subs) ?? 0;
                        ?>
                        <div class="entity-main-panel <?php echo $is_main_default ? '' : 'hidden'; ?>" data-entity-type="private"
                            data-main-id="<?php echo esc_attr((string) $main_id); ?>">
                            <?php if (!empty($subs)): ?>
                                <?php $render_sub_tabs($subs, (int) $default_sub, 'private', (int) $main_id); ?>
                                <?php foreach ($subs as $sub_id => $sub): ?>
                                    <?php $render_leaf_panel(
                                        'private_entity',
                                        (int) $sub_id,
                                        'private',
                                        $main_visible && (int) $sub_id === (int) $default_sub,
                                        (int) $main_id,
                                        (int) $sub_id
                                    ); ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php $render_leaf_panel('private_entity', (int) $main_id, 'private', $main_visible, (int) $main_id); ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php $render_leaf_panel('private_entity', 0, 'private', $default_type === 'private'); ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($has_evt): ?>
            <div class="entity-type-panel <?php echo $default_type === 'event' ? '' : 'hidden'; ?>"
                data-entity-type="event">
                <?php if (!empty($evt_tree)): ?>
                    <?php $render_sub_tabs($evt_tree, (int) $evt_default_id, 'event'); ?>
                    <?php foreach ($evt_tree as $cat_id => $cat): ?>
                        <?php $render_leaf_panel(
                            'event',
                            (int) $cat_id,
                            'event',
                            $default_type === 'event' && (int) $cat_id === (int) $evt_default_id,
                            0,
                            (int) $cat_id
                        ); ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php $render_leaf_panel('event', 0, 'event', $default_type === 'event'); ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
