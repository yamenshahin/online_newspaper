<?php
$state = $args['state'] ?? get_car_filter_state();
$base_url = $args['base_url'] ?? get_cars_filter_base_url();
?>
<form method="get" action="<?php echo esc_url($base_url); ?>" class="w-full max-w-xl">
    <?php cars_render_hidden_filter_fields(['car_s']); ?>
    <label class="relative block">
        <span
            class="material-symbols-outlined absolute inset-y-0 start-3 flex items-center text-on-surface-variant text-[20px]"
            aria-hidden="true">search</span>
        <input type="search" name="car_s" value="<?php echo esc_attr($state['search']); ?>"
            placeholder="<?php esc_attr_e('ابحث عن سيارة…', 'hello-elementor-child'); ?>"
            class="w-full rounded-full border border-surface-container-highest bg-surface-container-lowest py-3 pe-4 ps-11 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
    </label>
</form>