<?php
/**
 * Cars directory — taxonomy filters as compact selects.
 * Change select → submit (keeps search + meta via hidden fields).
 */
$state = $args['state'] ?? get_car_filter_state();
$base_url = $args['base_url'] ?? get_cars_filter_base_url();
$facets = get_car_filter_taxonomies();

$primary_taxes = ['car_maker', 'car_year', 'car_fuel_type', 'car_body_type'];
$more_taxes = ['car_model', 'car_origin'];

$facet_limits = [
    'car_model' => 0,
    'car_body_type' => 0,
    'car_maker' => 0,
    'car_year' => 0,
    'car_origin' => 0,
    'car_fuel_type' => 0,
];

$render_select = static function (string $tax, string $label) use ($state, $facet_limits): void {
    $terms = get_car_facet_terms($tax, (int) ($facet_limits[$tax] ?? 0));
    $active = $state['taxes'][$tax] ?? '';
    if (empty($terms) && $active === '') {
        return;
    }
    ?>
    <label class="flex flex-col gap-1 text-start min-w-0">
        <span class="text-xs font-bold text-on-surface-variant"><?php echo esc_html($label); ?></span>
        <select name="<?php echo esc_attr($tax); ?>"
            class="w-full rounded-xl border border-surface-container-highest bg-surface-container-lowest px-3 py-2.5 text-sm text-on-background focus:outline-none focus:ring-2 focus:ring-primary"
            onchange="this.form.submit()">
            <option value=""><?php esc_html_e('الكل', 'hello-elementor-child'); ?></option>
            <?php foreach ($terms as $term): ?>
                <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($active, $term->slug); ?>>
                    <?php
                    echo esc_html(
                        sprintf('%s (%s)', $term->name, (string) (int) $term->count)
                    );
                    ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php
};
?>

<form method="get" action="<?php echo esc_url($base_url); ?>" class="mb-6">
    <?php
    // Keep search + ranges + text; do not mirror taxes (selects control them).
    cars_render_hidden_filter_fields(array_keys(get_car_filter_taxonomies()));
    ?>

    <div
        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 p-4 rounded-2xl bg-surface-container-low border border-surface-container-highest">
        <?php
        foreach ($primary_taxes as $tax) {
            if (isset($facets[$tax])) {
                $render_select($tax, $facets[$tax]);
            }
        }
        ?>
    </div>

    <details class="mt-3 group">
        <summary class="cursor-pointer text-sm font-bold text-primary flex items-center gap-1 list-none py-2">
            <?php esc_html_e('المزيد من التصنيفات', 'hello-elementor-child'); ?>
            <span
                class="material-symbols-outlined text-[18px] transition-transform group-open:rotate-180">expand_more</span>
        </summary>
        <div
            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 p-4 mt-1 rounded-2xl border border-surface-container-highest bg-surface-container-lowest">
            <?php
            foreach ($more_taxes as $tax) {
                if (isset($facets[$tax])) {
                    $render_select($tax, $facets[$tax]);
                }
            }
            ?>
        </div>
    </details>
</form>