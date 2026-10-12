<?php
$state = $args['state'] ?? get_car_filter_state();
$base_url = $args['base_url'] ?? get_cars_filter_base_url();
$price = $state['ranges']['price'] ?? ['min' => null, 'max' => null];
$power = $state['ranges']['power'] ?? ['min' => null, 'max' => null];
?>
<form method="get" action="<?php echo esc_url($base_url); ?>"
    class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 rounded-2xl bg-surface-container-low border border-surface-container-highest mb-6">
    <?php cars_render_hidden_filter_fields(['price_min', 'price_max', 'power_min', 'power_max']); ?>

    <div class="text-start">
        <div class="text-xs font-bold text-on-surface-variant mb-2">
            <?php esc_html_e('السعر (ر.س)', 'hello-elementor-child'); ?>
        </div>
        <div class="flex gap-2">
            <input type="number" name="price_min" min="0" step="1000"
                placeholder="<?php esc_attr_e('من', 'hello-elementor-child'); ?>"
                value="<?php echo $price['min'] !== null ? esc_attr((string) $price['min']) : ''; ?>"
                class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm bg-surface-container-lowest" />
            <input type="number" name="price_max" min="0" step="1000"
                placeholder="<?php esc_attr_e('إلى', 'hello-elementor-child'); ?>"
                value="<?php echo $price['max'] !== null ? esc_attr((string) $price['max']) : ''; ?>"
                class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm bg-surface-container-lowest" />
        </div>
    </div>

    <div class="text-start">
        <div class="text-xs font-bold text-on-surface-variant mb-2">
            <?php esc_html_e('القوة', 'hello-elementor-child'); ?>
        </div>
        <div class="flex gap-2">
            <input type="number" name="power_min" min="0" step="1"
                placeholder="<?php esc_attr_e('من', 'hello-elementor-child'); ?>"
                value="<?php echo $power['min'] !== null ? esc_attr((string) $power['min']) : ''; ?>"
                class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm bg-surface-container-lowest" />
            <input type="number" name="power_max" min="0" step="1"
                placeholder="<?php esc_attr_e('إلى', 'hello-elementor-child'); ?>"
                value="<?php echo $power['max'] !== null ? esc_attr((string) $power['max']) : ''; ?>"
                class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm bg-surface-container-lowest" />
        </div>
    </div>

    <div class="md:col-span-2 flex justify-end">
        <button type="submit"
            class="rounded-full bg-primary text-on-primary px-6 py-2 text-sm font-bold hover:opacity-90 transition-opacity">
            <?php esc_html_e('تطبيق', 'hello-elementor-child'); ?>
        </button>
    </div>
</form>