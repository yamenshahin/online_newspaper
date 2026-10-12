<?php
$state = $args['state'] ?? get_car_filter_state();
$base_url = $args['base_url'] ?? get_cars_filter_base_url();

$range_labels = [
    'price' => __('السعر', 'hello-elementor-child'),
    'power' => __('القوة', 'hello-elementor-child'),
    'torque' => __('العزم', 'hello-elementor-child'),
    'engine' => __('المحرك', 'hello-elementor-child'),
    'seats' => __('المقاعد', 'hello-elementor-child'),
];
$text_labels = [
    'transmission' => __('ناقل الحركة', 'hello-elementor-child'),
    'drive' => __('نظام الدفع', 'hello-elementor-child'),
];

$has_active = (
    $state['search'] !== ''
    || !empty($state['taxes'])
    || !empty($state['ranges'])
    || !empty($state['text'])
);

if (!$has_active) {
    return;
}
?>
<div class="flex flex-wrap gap-2 mb-6">
    <?php if ($state['search'] !== ''): ?>
        <a href="<?php echo esc_url(get_cars_filter_url(['car_s' => null])); ?>"
            class="inline-flex items-center gap-1 rounded-full bg-primary text-on-primary px-3 py-1 text-sm font-semibold">
            <?php echo esc_html($state['search']); ?>
            <span aria-hidden="true">×</span>
        </a>
    <?php endif; ?>

    <?php foreach ($state['taxes'] as $tax => $slug):
        $t = get_term_by('slug', $slug, $tax);
        if (!$t || is_wp_error($t)) {
            continue;
        }
        ?>
        <a href="<?php echo esc_url(get_cars_filter_url([$tax => null])); ?>"
            class="inline-flex items-center gap-1 rounded-full bg-primary text-on-primary px-3 py-1 text-sm font-semibold">
            <?php echo esc_html($t->name); ?>
            <span aria-hidden="true">×</span>
        </a>
    <?php endforeach; ?>

    <?php foreach ($state['ranges'] as $prefix => $range):
        $parts = [];
        if ($range['min'] !== null) {
            $parts[] = '≥ ' . $range['min'];
        }
        if ($range['max'] !== null) {
            $parts[] = '≤ ' . $range['max'];
        }
        $chip = ($range_labels[$prefix] ?? $prefix) . ': ' . implode(' ', $parts);
        ?>
        <a href="<?php echo esc_url(get_cars_filter_url([$prefix . '_min' => null, $prefix . '_max' => null])); ?>"
            class="inline-flex items-center gap-1 rounded-full bg-primary text-on-primary px-3 py-1 text-sm font-semibold">
            <?php echo esc_html($chip); ?>
            <span aria-hidden="true">×</span>
        </a>
    <?php endforeach; ?>

    <?php foreach ($state['text'] as $param => $value): ?>
        <a href="<?php echo esc_url(get_cars_filter_url([$param => null])); ?>"
            class="inline-flex items-center gap-1 rounded-full bg-primary text-on-primary px-3 py-1 text-sm font-semibold">
            <?php echo esc_html(($text_labels[$param] ?? $param) . ': ' . $value); ?>
            <span aria-hidden="true">×</span>
        </a>
    <?php endforeach; ?>

    <a href="<?php echo esc_url($base_url); ?>"
        class="text-sm text-primary font-semibold underline-offset-2 hover:underline self-center">
        <?php esc_html_e('مسح الكل', 'hello-elementor-child'); ?>
    </a>
</div>