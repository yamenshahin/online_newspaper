<?php
/**
 * Cars — primary bar: 4 selects + search at the end (entity-style bar).
 */
$state = $args['state'] ?? get_car_filter_state();
$base_url = $args['base_url'] ?? get_cars_filter_base_url();
$facets = get_car_filter_taxonomies();

$primary_taxes = ['car_maker', 'car_year', 'car_fuel_type', 'car_body_type'];

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
    <label class="flex flex-col gap-1 text-start min-w-0 flex-1">
        <span class="text-xs font-bold text-on-surface-variant px-1"><?php echo esc_html($label); ?></span>
        <select name="<?php echo esc_attr($tax); ?>"
            class="w-full rounded-full border border-surface-container-highest bg-surface-container-lowest px-3 py-2 text-sm text-on-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
            onchange="this.form.submit()">
            <option value=""><?php esc_html_e('الكل', 'hello-elementor-child'); ?></option>
            <?php foreach ($terms as $term): ?>
                <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($active, $term->slug); ?>>
                    <?php echo esc_html(sprintf('%s (%s)', $term->name, (string) (int) $term->count)); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php
};
?>

<form method="get" action="<?php echo esc_url($base_url); ?>" class="mb-4">
    <?php
    cars_render_hidden_filter_fields(
        array_merge(
            array_keys(get_car_filter_taxonomies()),
            ['car_s']
        )
    );
    ?>

    <div class="p-3 sm:p-4 rounded-2xl bg-surface-container-low border border-surface-container-highest/40">
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 items-end">
            <?php
            foreach ($primary_taxes as $tax) {
                if (isset($facets[$tax])) {
                    $render_select($tax, $facets[$tax]);
                }
            }
            ?>

            <!-- 5th column: search -->
            <div class="relative w-full col-span-2 lg:col-span-1">
                <span
                    class="material-symbols-outlined absolute start-3 top-1/2 -translate-y-1/2 text-on-surface-variant/70 text-[18px] pointer-events-none">search</span>
                <input type="search" name="car_s" value="<?php echo esc_attr($state['search'] ?? ''); ?>"
                    placeholder="<?php esc_attr_e('ابحث عن سيارة…', 'hello-elementor-child'); ?>"
                    class="w-full rounded-full border border-surface-container-highest bg-surface-container-lowest py-2 !ps-10 !pe-3 text-sm text-on-background placeholder:text-on-surface-variant/60 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 [&::-webkit-search-cancel-button]:appearance-none"
                    autocomplete="off" />
            </div>
        </div>
    </div>
</form>