<?php
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
?>
<div class="flex flex-col gap-5 mb-8">
    <?php foreach ($facets as $tax => $label):
        $terms = get_car_facet_terms($tax, (int) ($facet_limits[$tax] ?? 0));
        if (empty($terms)) {
            continue;
        }
        $active = $state['taxes'][$tax] ?? '';
        ?>
        <div class="text-start">
            <div class="text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-2">
                <?php echo esc_html($label); ?>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="<?php echo esc_url(get_cars_filter_url([$tax => null])); ?>"
                    class="inline-flex rounded-full px-3 py-1.5 text-sm font-semibold border transition-colors <?php echo $active === '' ? 'bg-primary text-on-primary border-primary' : 'bg-surface-container-low text-on-surface border-surface-container-highest hover:border-primary'; ?>">
                    <?php esc_html_e('الكل', 'hello-elementor-child'); ?>
                </a>
                <?php foreach ($terms as $term):
                    $is_on = ($active === $term->slug);
                    ?>
                    <a href="<?php echo esc_url(get_cars_filter_url([$tax => $is_on ? null : $term->slug])); ?>"
                        class="inline-flex items-center gap-1 rounded-full px-3 py-1.5 text-sm font-semibold border transition-colors <?php echo $is_on ? 'bg-primary text-on-primary border-primary' : 'bg-surface-container-lowest text-on-surface border-surface-container-highest hover:border-primary'; ?>">
                        <?php echo esc_html($term->name); ?>
                        <span class="text-[11px] opacity-70">(<?php echo esc_html((string) $term->count); ?>)</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>