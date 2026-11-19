<?php
/**
 * Flexible Content: car comparison. The visitor picks two to four cars (make, model, then year and trim) and
 * gets a side-by-side table of their prices and specifications.
 *
 * Lists, REST routes and the table: inc/cars/compare.php; behaviour: assets/js/car-compare.js, which needs the
 * data-compare-* hooks below. A shared link (?car_compare=ID,ID) arrives with its slots filled and its table
 * printed, so it needs no request. Header recipe of the car directory home section: the title is the eyebrow
 * when a subtitle gives the headline. The content is site-wide ($args['department'] is not read); skipped on
 * entity term archives (get_term_archive_skip_layouts()). The wrapper carries `car-directory`: the component
 * CSS of the car blocks is scoped to it.
 */

if (!function_exists('get_car_compare_table_html') || !function_exists('car_section_header') || !function_exists('car_filter_select_style')) {
    return;
}

$section = $args['section'] ?? [];

if (!empty($section)) {
    $section_title = $section['section_title'] ?? '';
    $subtitle = $section['subtitle'] ?? '';
} else {
    $section_title = get_sub_field('section_title');
    $subtitle = get_sub_field('subtitle');
}

$makes = car_compare_makes();
if (!$makes) {
    return;
}

$section_title = trim((string) $section_title) ?: __('Compare cars', 'hello-elementor-child');
$subtitle = trim((string) $subtitle) ?: __('Pick two to four cars and compare their specifications and prices side by side', 'hello-elementor-child');

// A shared link: its cars fill the first slots and the table is printed with the page.
$shared_ids = car_compare_request_ids();
$shared_cars = array_map('car_compare_car', $shared_ids);

$script_path = get_stylesheet_directory() . '/assets/js/car-compare.js';
wp_enqueue_script(
    'hello-elementor-child-car-compare',
    get_stylesheet_directory_uri() . '/assets/js/car-compare.js',
    [],
    file_exists($script_path) ? filemtime($script_path) : null,
    true
);
wp_localize_script('hello-elementor-child-car-compare', 'helloCarCompare', [
    'restUrl' => esc_url_raw(rest_url('hello/v1/car-compare')),
    'min' => CAR_COMPARE_MIN,
    'max' => CAR_COMPARE_MAX,
    'param' => CAR_COMPARE_PARAM,
    'strings' => [
        'chooseModel' => __('Choose a model', 'hello-elementor-child'),
        'chooseCar' => __('Choose a year and trim', 'hello-elementor-child'),
        'loading' => _x('Loading…', 'cars', 'hello-elementor-child'),
        'noOptions' => __('No options available', 'hello-elementor-child'),
        'listError' => __('The options could not be loaded, try again.', 'hello-elementor-child'),
        'tableError' => __('The comparison could not be loaded, try again.', 'hello-elementor-child'),
        'hint' => __('Pick at least two cars', 'hello-elementor-child'),
    ],
]);

$make_options = '';
foreach ($makes as $make) {
    $make_options .= '<option value="' . esc_attr((string) $make['id']) . '">' . esc_html($make['name']) . '</option>';
}

// The selects of the car filter (render_car_filter_select()), dimmed while they wait for the one before them.
$select_class = 'block w-full h-10 appearance-none rounded-full border border-surface-container-highest bg-white text-on-background text-sm font-medium px-4 cursor-pointer shadow-xs focus:outline-none focus:ring-2 focus:ring-primary/30 disabled:cursor-not-allowed disabled:opacity-60';
$select_style = car_filter_select_style();
?>

<?php // scroll-mt-*: a shared link (#car-compare) and the "Compare" button land below the sticky site header. ?>
<div class="car-directory car-compare scroll-mt-20" id="car-compare" data-car-compare>
    <?php
    car_section_open('car-compare-section');
    // the subtitle (it always has one: the fallback) is the headline, the title its eyebrow
    car_section_header($subtitle, $section_title, '', get_cars_archive_url(), __('All cars', 'hello-elementor-child'));
    ?>

    <noscript>
        <div class="mb-space-md">
            <?php car_empty_state(__('Comparing cars requires JavaScript to be enabled in the browser.', 'hello-elementor-child')); ?>
        </div>
    </noscript>

    <div class="grid grid-cols-1 gap-space-md sm:grid-cols-2 lg:grid-cols-4">
        <?php for ($number = 1; $number <= CAR_COMPARE_MAX; $number++):
            $car = $shared_cars[$number - 1] ?? null;
            $slot_id = 'car-compare-slot-' . $number;
            ?>
            <div class="flex min-w-0 flex-col gap-space-sm rounded-2xl border border-surface-container-high bg-surface-container-lowest p-space-md shadow-sm"
                role="group" aria-labelledby="<?php echo esc_attr($slot_id); ?>" tabindex="-1" data-compare-slot data-car="<?php echo esc_attr($car ? (string) $car['id'] : ''); ?>">
                <div class="flex min-h-6 items-center justify-between gap-space-sm">
                    <span id="<?php echo esc_attr($slot_id); ?>" class="font-label-caps text-label-caps uppercase tracking-widest text-primary">
                        <?php
                        /* translators: %s: slot number, 1 to 4 */
                        echo esc_html(sprintf(__('Car %s', 'hello-elementor-child'), number_format_i18n($number)));
                        ?>
                    </span>
                    <?php if ($number > CAR_COMPARE_MIN): ?>
                        <span class="rounded-full bg-surface-container px-2 py-0.5 text-xs text-on-surface-variant"><?php esc_html_e('Optional', 'hello-elementor-child'); ?></span>
                    <?php endif; ?>
                </div>

                <div class="flex items-start justify-between gap-space-sm" data-compare-chosen <?php echo $car ? '' : 'hidden'; ?>>
                    <div class="min-w-0">
                        <p class="font-semibold leading-snug text-on-background" data-compare-title><?php echo esc_html($car['title'] ?? ''); ?></p>
                        <p class="text-body-sm text-on-surface-variant" data-compare-sub><?php echo esc_html($car['label'] ?? ''); ?></p>
                    </div>
                    <button type="button" data-compare-change
                        class="shrink-0 cursor-pointer rounded-full border border-surface-container-highest px-3 py-1.5 text-xs font-bold text-primary transition-colors hover:border-primary">
                        <?php esc_html_e('Change', 'hello-elementor-child'); ?>
                    </button>
                </div>

                <div class="flex flex-col gap-space-sm" data-compare-pick <?php echo $car ? 'hidden' : ''; ?>>
                    <label class="sr-only" for="<?php echo esc_attr($slot_id . '-make'); ?>"><?php esc_html_e('Make', 'hello-elementor-child'); ?></label>
                    <select id="<?php echo esc_attr($slot_id . '-make'); ?>" class="<?php echo esc_attr($select_class); ?>" style="<?php echo esc_attr($select_style); ?>" autocomplete="off" data-compare-make>
                        <option value=""><?php esc_html_e('Choose a make', 'hello-elementor-child'); ?></option>
                        <?php echo $make_options; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?>
                    </select>

                    <label class="sr-only" for="<?php echo esc_attr($slot_id . '-model'); ?>"><?php esc_html_e('Model', 'hello-elementor-child'); ?></label>
                    <select id="<?php echo esc_attr($slot_id . '-model'); ?>" class="<?php echo esc_attr($select_class); ?>" style="<?php echo esc_attr($select_style); ?>" autocomplete="off" data-compare-model disabled>
                        <option value=""><?php esc_html_e('Choose a model', 'hello-elementor-child'); ?></option>
                    </select>

                    <label class="sr-only" for="<?php echo esc_attr($slot_id . '-car'); ?>"><?php esc_html_e('Trim', 'hello-elementor-child'); ?></label>
                    <select id="<?php echo esc_attr($slot_id . '-car'); ?>" class="<?php echo esc_attr($select_class); ?>" style="<?php echo esc_attr($select_style); ?>" autocomplete="off" data-compare-car disabled>
                        <option value=""><?php esc_html_e('Choose a year and trim', 'hello-elementor-child'); ?></option>
                    </select>
                </div>
            </div>
        <?php endfor; ?>
    </div>

    <div class="mt-space-md flex flex-wrap items-center gap-x-space-md gap-y-space-sm">
        <button type="button" data-compare-submit <?php disabled(count($shared_ids) < CAR_COMPARE_MIN); ?>
            class="inline-flex h-11 cursor-pointer items-center justify-center gap-2 rounded-full bg-primary px-8 text-sm font-bold text-on-primary shadow-sm transition-all hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50">
            <?php // The spinner of the car filter's results (cars_section.php), sized for the button. ?>
            <span class="size-4 rounded-full border-2 border-current border-t-transparent animate-spin" data-compare-spinner aria-hidden="true" hidden></span>
            <span><?php esc_html_e('Compare', 'hello-elementor-child'); ?></span>
        </button>
        <button type="button" data-compare-clear
            class="cursor-pointer text-sm font-semibold text-primary underline-offset-2 hover:underline disabled:cursor-not-allowed disabled:opacity-50">
            <?php esc_html_e('Clear all', 'hello-elementor-child'); ?>
        </button>
        <p class="text-body-sm text-on-surface-variant" role="status" data-compare-status><?php
            echo count($shared_ids) < CAR_COMPARE_MIN ? esc_html__('Pick at least two cars', 'hello-elementor-child') : '';
        ?></p>
    </div>

    <div class="scroll-mt-32 transition-opacity" aria-live="polite" data-compare-result><?php
        echo get_car_compare_table_html($shared_ids); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the builder
    ?></div>

    <?php car_section_close(); ?>
</div>
