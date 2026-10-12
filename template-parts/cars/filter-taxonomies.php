<?php
/**
 * Cars directory — taxonomy facet rows (Phase 1 + Phase 3 counts / pill search).
 */
$state = $args['state'] ?? get_car_filter_state();
$facets = get_car_filter_taxonomies();

$facet_limits = [
    'car_model' => 80,
    'car_body_type' => 60,
    'car_maker' => 0,
    'car_year' => 0,
    'car_origin' => 0,
    'car_fuel_type' => 0,
];

/** Facets that get a client-side search box above the pills. */
$searchable_facets = ['car_model', 'car_body_type', 'car_maker'];

/**
 * True when filters other than this taxonomy row are active
 * (so we recompute counts instead of using global term->count).
 */
$has_contextual_filters = static function (string $current_tax) use ($state): bool {
    if (($state['search'] ?? '') !== '') {
        return true;
    }
    if (!empty($state['ranges']) || !empty($state['text'])) {
        return true;
    }
    $taxes = $state['taxes'] ?? [];
    foreach ($taxes as $tax => $slug) {
        if ($tax !== $current_tax) {
            return true;
        }
    }
    return false;
};
?>

<div class="flex flex-col gap-5 mb-8">
    <?php
    foreach ($facets as $tax => $label):
        $terms = get_car_facet_terms($tax, (int) ($facet_limits[$tax] ?? 0));
        if (empty($terms)) {
            continue;
        }

        $active = $state['taxes'][$tax] ?? '';
        $searchable = in_array($tax, $searchable_facets, true);
        $use_context = $has_contextual_filters($tax);
        ?>
        <div class="text-start car-facet" data-facet="<?php echo esc_attr($tax); ?>">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <div class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                    <?php echo esc_html($label); ?>
                </div>
                <?php if ($searchable): ?>
                    <input type="search"
                        class="car-facet-search rounded-full border border-surface-container-highest bg-surface-container-lowest px-3 py-1.5 text-sm max-w-[220px] focus:outline-none focus:ring-2 focus:ring-primary"
                        placeholder="<?php esc_attr_e('تصفية…', 'hello-elementor-child'); ?>"
                        data-facet-search="<?php echo esc_attr($tax); ?>" autocomplete="off" />
                <?php endif; ?>
            </div>

            <div class="flex flex-wrap gap-2 car-facet-pills">
                <a href="<?php echo esc_url(get_cars_filter_url([$tax => null])); ?>"
                    class="inline-flex rounded-full px-3 py-1.5 text-sm font-semibold border transition-colors <?php echo $active === '' ? 'bg-primary text-on-primary border-primary' : 'bg-surface-container-low text-on-surface border-surface-container-highest hover:border-primary'; ?>">
                    <?php esc_html_e('الكل', 'hello-elementor-child'); ?>
                </a>

                <?php
                foreach ($terms as $term):
                    $is_on = ($active === $term->slug);

                    if ($use_context && function_exists('get_car_facet_term_count')) {
                        $count = get_car_facet_term_count($tax, $term->slug);
                        // Hide zero matches unless this term is the active filter.
                        if ($count < 1 && !$is_on) {
                            continue;
                        }
                    } else {
                        $count = (int) $term->count;
                    }
                    ?>
                    <a href="<?php echo esc_url(get_cars_filter_url([$tax => $is_on ? null : $term->slug])); ?>"
                        data-name="<?php echo esc_attr(mb_strtolower($term->name)); ?>"
                        class="inline-flex items-center gap-1 rounded-full px-3 py-1.5 text-sm font-semibold border transition-colors <?php echo $is_on ? 'bg-primary text-on-primary border-primary' : 'bg-surface-container-lowest text-on-surface border-surface-container-highest hover:border-primary'; ?>">
                        <?php echo esc_html($term->name); ?>
                        <span class="text-[11px] opacity-70">(<?php echo esc_html((string) $count); ?>)</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>