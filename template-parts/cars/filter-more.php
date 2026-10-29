<?php
/**
 * Cars filter: "المزيد من الفلاتر" panel (fuel, transmission, drivetrain, feature pills, numeric ranges).
 * Fields only; the form around them is opened by sections/cars_section.php. Labels come from inc/cars.php.
 */
$state = $args['state'] ?? get_car_filter_state();

$more_selects = ['f_fuel', 'f_transmission', 'f_drivetrain'];
$facets = get_car_filter_taxonomies();
$ranges = get_car_range_filters();
$features = get_car_facet_terms('f_feature', $state);
$active_features = (array) ($state['taxes']['f_feature'] ?? []);

$open = !empty($state['ranges'])
    || !empty($active_features)
    || !empty(array_intersect_key($state['taxes'], array_flip($more_selects)));

$input_class = 'w-full min-w-0 h-10 rounded-full border border-surface-container-highest bg-white px-4 text-sm text-on-background shadow-xs focus:outline-none focus:ring-2 focus:ring-primary/30';
?>
<details class="mt-4 group" <?php echo $open ? 'open' : ''; ?>>
    <summary class="cursor-pointer text-sm font-bold text-primary inline-flex items-center gap-1 list-none py-2 select-none">
        <?php esc_html_e('المزيد من الفلاتر', 'hello-elementor-child'); ?>
        <span class="material-symbols-outlined text-[18px] transition-transform group-open:rotate-180" aria-hidden="true">expand_more</span>
    </summary>

    <div class="mt-3 flex flex-col gap-4 p-4 rounded-2xl border border-surface-container-highest bg-surface-container-lowest">

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <?php
            foreach ($more_selects as $param) {
                render_car_filter_select($param, $state, 'panel');
            }
            ?>
        </div>

        <?php if ($features): ?>
            <fieldset class="flex flex-col gap-2 min-w-0">
                <legend class="text-xs font-bold text-on-surface-variant px-1 mb-2">
                    <?php echo esc_html($facets['f_feature']['label']); ?>
                </legend>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($features as $term): ?>
                        <label class="inline-flex items-center gap-2 rounded-full border border-surface-container-highest bg-white px-3 py-1 text-xs font-semibold text-on-surface-variant cursor-pointer select-none transition-colors has-[:checked]:border-primary has-[:checked]:bg-primary/10 has-[:checked]:text-primary">
                            <input type="checkbox" name="f_feature[]" value="<?php echo esc_attr($term->slug); ?>" <?php checked(in_array($term->slug, $active_features, true)); ?> />
                            <span><?php echo esc_html($term->name); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        <?php endif; ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4">
            <?php foreach ($ranges as $prefix => $range):
                $min = $state['ranges'][$prefix]['min'] ?? null;
                $max = $state['ranges'][$prefix]['max'] ?? null;
                $heading = $range['label'] . ($range['unit'] !== '' ? ' (' . $range['unit'] . ')' : '');
                ?>
                <fieldset class="flex flex-col gap-1 min-w-0">
                    <legend class="text-xs font-bold text-on-surface-variant px-1 mb-1"><?php echo esc_html($heading); ?></legend>
                    <div class="flex gap-2">
                        <input type="number" name="<?php echo esc_attr($prefix . '_min'); ?>" min="0" step="<?php echo esc_attr((string) $range['step']); ?>"
                            inputmode="<?php echo $range['step'] < 1 ? 'decimal' : 'numeric'; ?>"
                            aria-label="<?php echo esc_attr($range['label'] . ' – ' . __('من', 'hello-elementor-child')); ?>"
                            placeholder="<?php esc_attr_e('من', 'hello-elementor-child'); ?>"
                            value="<?php echo $min !== null ? esc_attr((string) $min) : ''; ?>" class="<?php echo esc_attr($input_class); ?>" />
                        <input type="number" name="<?php echo esc_attr($prefix . '_max'); ?>" min="0" step="<?php echo esc_attr((string) $range['step']); ?>"
                            inputmode="<?php echo $range['step'] < 1 ? 'decimal' : 'numeric'; ?>"
                            aria-label="<?php echo esc_attr($range['label'] . ' – ' . __('إلى', 'hello-elementor-child')); ?>"
                            placeholder="<?php esc_attr_e('إلى', 'hello-elementor-child'); ?>"
                            value="<?php echo $max !== null ? esc_attr((string) $max) : ''; ?>" class="<?php echo esc_attr($input_class); ?>" />
                    </div>
                </fieldset>
            <?php endforeach; ?>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="inline-flex items-center justify-center rounded-full bg-primary text-on-primary px-6 py-2 text-sm font-bold hover:opacity-90 transition-all">
                <?php esc_html_e('تطبيق', 'hello-elementor-child'); ?>
            </button>
        </div>

    </div>
</details>
