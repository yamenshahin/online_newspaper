<?php
/**
 * Cars filter: primary bar (make, model, year, origin, body type + text search).
 * Fields only; the form around them is opened by sections/cars_section.php.
 */
$state = $args['state'] ?? get_car_filter_state();

$primary = ['f_make', 'f_model', 'f_year', 'f_origin', 'f_body'];
?>
<div class="p-4 rounded-2xl bg-primary shadow-sm flex flex-col md:flex-row items-stretch md:items-end justify-between gap-4">

    <div class="flex flex-wrap items-end gap-3 flex-1">
        <?php
        foreach ($primary as $param) {
            render_car_filter_select($param, $state, 'bar');
        }
        ?>
    </div>

    <div class="flex items-end gap-3 w-full md:w-auto">
        <div class="flex flex-col gap-1 flex-1 md:w-64">
            <label for="cars-car-s" class="text-xs font-bold text-white/90 px-1">
                <?php esc_html_e('البحث', 'hello-elementor-child'); ?>
            </label>
            <div class="relative flex items-center w-full">
                <span class="material-symbols-outlined absolute start-3 text-on-surface-variant/70 text-[18px] pointer-events-none select-none" aria-hidden="true">search</span>
                <input type="search" name="car_s" id="cars-car-s" value="<?php echo esc_attr($state['search']); ?>"
                    class="w-full h-10 rounded-full border border-white/20 bg-white !ps-10 !pe-3 text-sm text-on-background placeholder:text-on-surface-variant/60 shadow-xs transition-all focus:outline-none focus:ring-2 focus:ring-white/40 [&::-webkit-search-cancel-button]:appearance-none"
                    placeholder="<?php esc_attr_e('ابحث عن سيارة…', 'hello-elementor-child'); ?>" autocomplete="off" />
            </div>
        </div>

        <button type="submit" class="inline-flex items-center justify-center h-10 rounded-full bg-white text-primary px-5 text-sm font-bold shadow-xs hover:opacity-90 transition-all shrink-0">
            <?php esc_html_e('بحث', 'hello-elementor-child'); ?>
        </button>
    </div>

</div>
