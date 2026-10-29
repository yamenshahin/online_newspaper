<?php
/**
 * Cars filter: active filter chips (each removes its filter) + "مسح الكل". Re-rendered over AJAX.
 */
$state = $args['state'] ?? get_car_filter_state();
$base_url = $args['base_url'] ?? get_cars_filter_base_url();

if ($state['search'] === '' && empty($state['taxes']) && empty($state['ranges'])) {
    return;
}

$facets = get_car_filter_taxonomies();
$chips = []; // [text, url]

if ($state['search'] !== '') {
    $chips[] = [$state['search'], get_cars_filter_url(['car_s' => null], $state, $base_url)];
}

foreach ($state['taxes'] as $param => $value) {
    $taxonomy = $facets[$param]['taxonomy'] ?? '';
    foreach ((array) $value as $slug) {
        $term = get_term_by('slug', $slug, $taxonomy);
        if (!$term) {
            continue;
        }
        $remaining = is_array($value) ? array_values(array_diff($value, [$slug])) : null;
        $chips[] = [$term->name, get_cars_filter_url([$param => $remaining], $state, $base_url)];
    }
}

foreach ($state['ranges'] as $prefix => $range) {
    $chips[] = [
        car_filter_range_text($prefix, $range),
        get_cars_filter_url([$prefix . '_min' => null, $prefix . '_max' => null], $state, $base_url),
    ];
}
?>
<div class="flex flex-wrap items-center gap-2 mt-4">
    <?php foreach ($chips as [$text, $url]): ?>
        <a href="<?php echo esc_url($url); ?>"
            class="inline-flex items-center gap-1 rounded-full bg-primary text-on-primary px-3 py-1 text-sm font-semibold hover:opacity-90 transition-all"
            aria-label="<?php echo esc_attr(sprintf(/* translators: %s: filter text */ __('إزالة الفلتر: %s', 'hello-elementor-child'), $text)); ?>">
            <?php echo esc_html($text); ?>
            <span aria-hidden="true">×</span>
        </a>
    <?php endforeach; ?>

    <a href="<?php echo esc_url($base_url); ?>" data-cars-clear
        class="text-sm text-primary font-semibold underline-offset-2 hover:underline self-center">
        <?php esc_html_e('مسح الكل', 'hello-elementor-child'); ?>
    </a>
</div>
