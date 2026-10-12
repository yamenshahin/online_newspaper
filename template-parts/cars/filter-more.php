<?php
/**
 * Cars — single “More filters” panel
 */
$state = $args['state'] ?? get_car_filter_state();
$base_url = $args['base_url'] ?? get_cars_filter_base_url();
$facets = get_car_filter_taxonomies();

$more_taxes = ['car_model', 'car_origin'];

$price = $state['ranges']['price'] ?? ['min' => null, 'max' => null];
$power = $state['ranges']['power'] ?? ['min' => null, 'max' => null];
$torque = $state['ranges']['torque'] ?? ['min' => null, 'max' => null];
$engine = $state['ranges']['engine'] ?? ['min' => null, 'max' => null];
$seats = $state['ranges']['seats'] ?? ['min' => null, 'max' => null];

$text_labels = [
    'transmission' => __('ناقل الحركة', 'hello-elementor-child'),
    'drive' => __('نظام الدفع', 'hello-elementor-child'),
];
$text_facets = [
    'transmission' => 'transmission',
    'drive' => 'drive_system',
];

$facet_limits = [
    'car_model' => 0,
    'car_origin' => 0,
];

// Same nice select style as the primary bar
$select_style = "appearance:none !important; -webkit-appearance:none !important; -moz-appearance:none !important; background-image:url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 12 12%22 fill=%22none%22%3E%3Cpath d=%22m3 4.5 3 3 3-3%22 stroke=%22%231f2937%22 stroke-width=%221.5%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22/%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:10px center; background-size:12px 12px; padding-left:2rem;";

$render_select = static function (string $tax, string $label) use ($state, $facet_limits, $select_style): void {
    $terms = get_car_facet_terms($tax, (int) ($facet_limits[$tax] ?? 0));
    $active = $state['taxes'][$tax] ?? '';
    if (empty($terms) && $active === '') {
        return;
    }
    ?>
    <div class="flex flex-col gap-1 min-w-0">
        <span class="text-xs font-bold text-on-surface-variant"><?php echo esc_html($label); ?></span>
        <select name="<?php echo esc_attr($tax); ?>"
            class="w-full h-10 appearance-none rounded-full border border-surface-container-highest bg-white text-on-background text-sm font-medium px-4 focus:outline-none focus:ring-2 focus:ring-primary/30 cursor-pointer shadow-xs"
            style="<?php echo esc_attr($select_style); ?>">
            <option value=""><?php esc_html_e('الكل', 'hello-elementor-child'); ?></option>
            <?php foreach ($terms as $term): ?>
                <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($active, $term->slug); ?>>
                    <?php echo esc_html(sprintf('%s (%s)', $term->name, (string) (int) $term->count)); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php
};

$open = (
    !empty(array_intersect_key($state['taxes'] ?? [], array_flip($more_taxes)))
    || isset($state['ranges']['price'])
    || isset($state['ranges']['power'])
    || isset($state['ranges']['torque'])
    || isset($state['ranges']['engine'])
    || isset($state['ranges']['seats'])
    || !empty($state['text'])
);
?>

<details class="mb-8 group" <?php echo $open ? 'open' : ''; ?>>
    <summary class="cursor-pointer text-sm font-bold text-primary flex items-center gap-1 list-none py-2 select-none">
        <?php esc_html_e('المزيد من الفلاتر', 'hello-elementor-child'); ?>
        <span
            class="material-symbols-outlined text-[18px] transition-transform group-open:rotate-180">expand_more</span>
    </summary>

    <div class="mt-4 space-y-5">

        <!-- 1. Four selects in one clean row -->
        <form method="get" action="<?php echo esc_url($base_url); ?>"
            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 p-4 rounded-2xl border border-surface-container-highest bg-surface-container-lowest">

            <?php
            // Keep all current filters when submitting
            cars_render_hidden_filter_fields(
                array_merge(
                    array_keys(get_car_filter_taxonomies()),
                    ['transmission', 'drive']
                )
            );

            // Keep primary taxes
            foreach (['car_maker', 'car_year', 'car_fuel_type', 'car_body_type'] as $tax) {
                if (!empty($state['taxes'][$tax])) {
                    printf(
                        '<input type="hidden" name="%s" value="%s" />' . "\n",
                        esc_attr($tax),
                        esc_attr($state['taxes'][$tax])
                    );
                }
            }
            ?>

            <!-- Model + Origin -->
            <?php
            foreach ($more_taxes as $tax) {
                if (isset($facets[$tax])) {
                    $render_select($tax, $facets[$tax]);
                }
            }
            ?>

            <!-- Transmission + Drive -->
            <?php foreach ($text_facets as $param => $meta_key):
                $values = get_car_distinct_meta_values($meta_key);
                if (empty($values)) {
                    continue;
                }
                $active = $state['text'][$param] ?? '';
                ?>
                <div class="flex flex-col gap-1 min-w-0">
                    <span class="text-xs font-bold text-on-surface-variant">
                        <?php echo esc_html($text_labels[$param] ?? $param); ?>
                    </span>
                    <select name="<?php echo esc_attr($param); ?>"
                        class="w-full h-10 appearance-none rounded-full border border-surface-container-highest bg-white text-on-background text-sm font-medium px-4 focus:outline-none focus:ring-2 focus:ring-primary/30 cursor-pointer shadow-xs"
                        style="<?php echo esc_attr($select_style); ?>">
                        <option value=""><?php esc_html_e('الكل', 'hello-elementor-child'); ?></option>
                        <?php foreach ($values as $val): ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected($active, $val); ?>>
                                <?php echo esc_html($val); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endforeach; ?>
        </form>

        <!-- 2. Five numeric ranges -->
        <form method="get" action="<?php echo esc_url($base_url); ?>"
            class="p-4 rounded-2xl border border-surface-container-highest bg-surface-container-lowest">
            <?php
            cars_render_hidden_filter_fields([
                'price_min',
                'price_max',
                'power_min',
                'power_max',
                'torque_min',
                'torque_max',
                'engine_min',
                'engine_max',
                'seats_min',
                'seats_max',
            ]);
            ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4">

                <!-- Price -->
                <div class="flex flex-col gap-1">
                    <div class="text-xs font-bold text-on-surface-variant">
                        <?php esc_html_e('السعر (ر.س)', 'hello-elementor-child'); ?>
                    </div>
                    <input type="number" name="price_min" min="0" step="1"
                        placeholder="<?php esc_attr_e('من', 'hello-elementor-child'); ?>"
                        value="<?php echo $price['min'] !== null ? esc_attr((string) $price['min']) : ''; ?>"
                        class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm" />
                    <input type="number" name="price_max" min="0" step="1"
                        placeholder="<?php esc_attr_e('إلى', 'hello-elementor-child'); ?>"
                        value="<?php echo $price['max'] !== null ? esc_attr((string) $price['max']) : ''; ?>"
                        class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm" />
                </div>

                <!-- Power -->
                <div class="flex flex-col gap-1">
                    <div class="text-xs font-bold text-on-surface-variant">
                        <?php esc_html_e('القوة', 'hello-elementor-child'); ?>
                    </div>
                    <input type="number" name="power_min" min="0" step="1"
                        placeholder="<?php esc_attr_e('من', 'hello-elementor-child'); ?>"
                        value="<?php echo $power['min'] !== null ? esc_attr((string) $power['min']) : ''; ?>"
                        class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm" />
                    <input type="number" name="power_max" min="0" step="1"
                        placeholder="<?php esc_attr_e('إلى', 'hello-elementor-child'); ?>"
                        value="<?php echo $power['max'] !== null ? esc_attr((string) $power['max']) : ''; ?>"
                        class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm" />
                </div>

                <!-- Torque -->
                <div class="flex flex-col gap-1">
                    <div class="text-xs font-bold text-on-surface-variant">
                        <?php esc_html_e('العزم', 'hello-elementor-child'); ?>
                    </div>
                    <input type="number" name="torque_min" min="0" step="1"
                        placeholder="<?php esc_attr_e('من', 'hello-elementor-child'); ?>"
                        value="<?php echo $torque['min'] !== null ? esc_attr((string) $torque['min']) : ''; ?>"
                        class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm" />
                    <input type="number" name="torque_max" min="0" step="1"
                        placeholder="<?php esc_attr_e('إلى', 'hello-elementor-child'); ?>"
                        value="<?php echo $torque['max'] !== null ? esc_attr((string) $torque['max']) : ''; ?>"
                        class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm" />
                </div>

                <!-- Engine -->
                <div class="flex flex-col gap-1">
                    <div class="text-xs font-bold text-on-surface-variant">
                        <?php esc_html_e('سعة المحرك', 'hello-elementor-child'); ?>
                    </div>
                    <input type="number" name="engine_min" min="0" step="0.1"
                        placeholder="<?php esc_attr_e('من', 'hello-elementor-child'); ?>"
                        value="<?php echo $engine['min'] !== null ? esc_attr((string) $engine['min']) : ''; ?>"
                        class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm" />
                    <input type="number" name="engine_max" min="0" step="0.1"
                        placeholder="<?php esc_attr_e('إلى', 'hello-elementor-child'); ?>"
                        value="<?php echo $engine['max'] !== null ? esc_attr((string) $engine['max']) : ''; ?>"
                        class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm" />
                </div>

                <!-- Seats -->
                <div class="flex flex-col gap-1">
                    <div class="text-xs font-bold text-on-surface-variant">
                        <?php esc_html_e('المقاعد', 'hello-elementor-child'); ?>
                    </div>
                    <input type="number" name="seats_min" min="0" step="1"
                        placeholder="<?php esc_attr_e('من', 'hello-elementor-child'); ?>"
                        value="<?php echo $seats['min'] !== null ? esc_attr((string) $seats['min']) : ''; ?>"
                        class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm" />
                    <input type="number" name="seats_max" min="0" step="1"
                        placeholder="<?php esc_attr_e('إلى', 'hello-elementor-child'); ?>"
                        value="<?php echo $seats['max'] !== null ? esc_attr((string) $seats['max']) : ''; ?>"
                        class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm" />
                </div>
            </div>

            <div class="mt-4 flex justify-end">
                <button type="submit" class="rounded-full bg-primary text-on-primary px-6 py-2 text-sm font-bold">
                    <?php esc_html_e('تطبيق', 'hello-elementor-child'); ?>
                </button>
            </div>
        </form>

    </div>
</details>