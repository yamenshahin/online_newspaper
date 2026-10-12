<?php
/**
 * Cars — primary bar matching entity-browse-section search layout.
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
    <div class="flex flex-col gap-1 min-w-0 flex-1">
        <span class="text-xs font-bold text-white/90 px-1">
            <?php echo esc_html($label); ?>
        </span>

        <div class="relative w-full">
            <select name="<?php echo esc_attr($tax); ?>"
                class="block w-full h-10 appearance-none rounded-full border border-white/20 bg-white text-on-background text-sm font-medium px-4 focus:outline-none focus:ring-2 focus:ring-white/40 cursor-pointer shadow-xs"
                style="appearance:none !important; -webkit-appearance:none !important; -moz-appearance:none !important; background-image:url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 12 12%22 fill=%22none%22%3E%3Cpath d=%22m3 4.5 3 3 3-3%22 stroke=%22%231f2937%22 stroke-width=%221.5%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22/%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:10px center; background-size:12px 12px; padding-left:2rem;">
                <option value=""><?php esc_html_e('الكل', 'hello-elementor-child'); ?></option>
                <?php foreach ($terms as $term): ?>
                    <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($active, $term->slug); ?>>
                        <?php echo esc_html(sprintf('%s (%s)', $term->name, (string) (int) $term->count)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

    </div>
    <?php
};
?>

<form method="get" action="<?php echo esc_url($base_url); ?>" class="mb-6">
    <?php
    cars_render_hidden_filter_fields(
        array_merge(
            array_keys(get_car_filter_taxonomies()),
            ['car_s']
        )
    );
    ?>

    <!-- Main Bar Container -->
    <div
        class="p-4 rounded-2xl bg-primary shadow-sm flex flex-col md:flex-row items-stretch md:items-end justify-between gap-4">

        <!-- Selects Group -->
        <div class="flex flex-wrap items-center gap-3 flex-1">
            <?php
            foreach ($primary_taxes as $tax) {
                if (isset($facets[$tax])) {
                    $render_select($tax, $facets[$tax]);
                }
            }
            ?>
        </div>

        <!-- Search input matching entity-browse-section.php -->
        <div class="entity-search relative w-full md:w-64 shrink-0">
            <span class="text-xs font-bold text-white/90 px-1 mb-1 block">
                <?php esc_html_e('البحث', 'hello-elementor-child'); ?>
            </span>
            <div class="relative flex items-center w-full">
                <span
                    class="material-symbols-outlined absolute start-3 text-on-surface-variant/70 text-[18px] pointer-events-none select-none"
                    aria-hidden="true">
                    search
                </span>
                <input type="search" name="car_s" value="<?php echo esc_attr($state['search'] ?? ''); ?>"
                    class="entity-search-input w-full rounded-full border border-surface-container-highest bg-surface-container-lowest py-2 !ps-10 !pe-3 text-sm text-on-background placeholder:text-on-surface-variant/60 shadow-xs transition-all focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 [&::-webkit-search-cancel-button]:appearance-none"
                    placeholder="<?php esc_attr_e('ابحث عن سيارة…', 'hello-elementor-child'); ?>" autocomplete="off" />
            </div>
        </div>

    </div>
</form>