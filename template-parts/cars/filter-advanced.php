<?php
$state = $args['state'] ?? get_car_filter_state();
$base_url = $args['base_url'] ?? get_cars_filter_base_url();
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
?>
<details class="mb-10 group">
    <summary class="cursor-pointer text-sm font-bold text-primary flex items-center gap-1 list-none">
        <?php esc_html_e('المزيد من الفلاتر', 'hello-elementor-child'); ?>
        <span
            class="material-symbols-outlined text-[18px] transition-transform group-open:rotate-180">expand_more</span>
    </summary>

    <div class="mt-4 flex flex-col gap-6">
        <form method="get" action="<?php echo esc_url($base_url); ?>"
            class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 rounded-2xl border border-surface-container-highest bg-surface-container-lowest">
            <?php
            cars_render_hidden_filter_fields(
                [
                    'torque_min',
                    'torque_max',
                    'engine_min',
                    'engine_max',
                    'seats_min',
                    'seats_max',
                ]
            );
            ?>
            <div class="text-start">
                <div class="text-xs font-bold text-on-surface-variant mb-2">
                    <?php esc_html_e('العزم', 'hello-elementor-child'); ?></div>
                <div class="flex gap-2">
                    <input type="number" name="torque_min" min="0" step="1"
                        placeholder="<?php esc_attr_e('من', 'hello-elementor-child'); ?>"
                        value="<?php echo $torque['min'] !== null ? esc_attr((string) $torque['min']) : ''; ?>"
                        class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm" />
                    <input type="number" name="torque_max" min="0" step="1"
                        placeholder="<?php esc_attr_e('إلى', 'hello-elementor-child'); ?>"
                        value="<?php echo $torque['max'] !== null ? esc_attr((string) $torque['max']) : ''; ?>"
                        class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm" />
                </div>
            </div>
            <div class="text-start">
                <div class="text-xs font-bold text-on-surface-variant mb-2">
                    <?php esc_html_e('سعة المحرك', 'hello-elementor-child'); ?></div>
                <div class="flex gap-2">
                    <input type="number" name="engine_min" min="0" step="0.1"
                        placeholder="<?php esc_attr_e('من', 'hello-elementor-child'); ?>"
                        value="<?php echo $engine['min'] !== null ? esc_attr((string) $engine['min']) : ''; ?>"
                        class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm" />
                    <input type="number" name="engine_max" min="0" step="0.1"
                        placeholder="<?php esc_attr_e('إلى', 'hello-elementor-child'); ?>"
                        value="<?php echo $engine['max'] !== null ? esc_attr((string) $engine['max']) : ''; ?>"
                        class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm" />
                </div>
            </div>
            <div class="text-start">
                <div class="text-xs font-bold text-on-surface-variant mb-2">
                    <?php esc_html_e('المقاعد', 'hello-elementor-child'); ?></div>
                <div class="flex gap-2">
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
            <div class="md:col-span-3 flex justify-end">
                <button type="submit" class="rounded-full bg-primary text-on-primary px-6 py-2 text-sm font-bold">
                    <?php esc_html_e('تطبيق', 'hello-elementor-child'); ?>
                </button>
            </div>
        </form>

        <?php foreach ($text_facets as $param => $meta_key):
            $values = get_car_distinct_meta_values($meta_key);
            if (empty($values)) {
                continue;
            }
            $active = $state['text'][$param] ?? '';
            ?>
            <div class="text-start">
                <div class="text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-2">
                    <?php echo esc_html($text_labels[$param] ?? $param); ?>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="<?php echo esc_url(get_cars_filter_url([$param => null])); ?>"
                        class="inline-flex rounded-full px-3 py-1.5 text-sm font-semibold border transition-colors <?php echo $active === '' ? 'bg-primary text-on-primary border-primary' : 'bg-surface-container-low text-on-surface border-surface-container-highest hover:border-primary'; ?>">
                        <?php esc_html_e('الكل', 'hello-elementor-child'); ?>
                    </a>
                    <?php foreach ($values as $val):
                        $on = ($active === $val);
                        ?>
                        <a href="<?php echo esc_url(get_cars_filter_url([$param => $on ? null : $val])); ?>"
                            class="inline-flex rounded-full px-3 py-1.5 text-sm font-semibold border transition-colors <?php echo $on ? 'bg-primary text-on-primary border-primary' : 'bg-surface-container-lowest text-on-surface border-surface-container-highest hover:border-primary'; ?>">
                            <?php echo esc_html($val); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</details>