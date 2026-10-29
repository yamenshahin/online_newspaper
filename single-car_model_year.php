<?php
/**
 * Model-year page (/car-directory/<origin>/<make>/<model>/<year>/), also the sub-family pages under
 * a year (/<year>/<sub-family>/).
 *
 * Breadcrumbs, title row, price range + chips, the clamped description, a strip of the sub-family
 * and trim tiles, the sortable trim price table, then the model-year gallery.
 *
 * Markup comes from inc/cars/helpers.php (builders return html, renderers echo; everything is
 * escaped there); behaviour from assets/js/car-directory.js, styles from the .car-directory block
 * in src/input.css.
 */

get_header();

while (have_posts()):
    the_post();

    $year_id = get_the_ID();
    $title = get_the_title();
    $crumbs = car_post_crumbs($year_id);
    $model_year = trim((string) get_field('model_year', $year_id));
    $is_subfamily = get_post_meta($year_id, '_car_page_type', true) === 'subfamily';
    $eyebrow = $is_subfamily
        ? 'فئة فرعية' . ($model_year !== '' ? ' · موديل ' . $model_year : '')
        : trim('سنة الموديل ' . $model_year);
    $trims = car_year_trims($year_id);
    $tiles = car_year_tiles($year_id);
    $pics = car_gallery_images($year_id);

    // Price range of the year (price_min / price_max): the trims' currency, SAR when there are none.
    $price_min = get_field('price_min', $year_id);
    $price_max = get_field('price_max', $year_id);
    $in_usd = $trims && get_post_meta($trims[0]->ID, 'price_currency', true) === 'USD';
    $format_price = static fn($n): string => $in_usd ? format_number($n) . ' دولار أمريكي' : format_sar($n);
    $price_range = '';
    if (is_numeric($price_min) && $price_min > 0 && is_numeric($price_max) && $price_max > $price_min) {
        $price_range = car_pill(format_number($price_min) . ' – ' . $format_price($price_max), '', 'primary', 'الأسعار من');
    } elseif (is_numeric($price_min) && $price_min > 0) {
        $price_range = car_pill($format_price($price_min), '', 'primary', 'يبدأ من');
    }

    // Trim price table: the trims themselves (linked); the hub's own price_table when no trim has a price.
    $price_rows = [];
    $trim_urls = [];
    foreach ($trims as $car) {
        $trim_name = (string) get_field('trim_name', $car->ID) ?: get_the_title($car);
        $trim_urls[$trim_name] = get_permalink($car);
        $price_rows[] = [$trim_name, $trim_urls[$trim_name], car_price_cell($car->ID)];
    }
    if (!array_filter(array_map(static fn($r) => $r[2][0], $price_rows))) {
        $price_rows = [];
        foreach ((array) get_field('price_table', $year_id) as $r) {
            $trim_name = is_array($r) ? trim((string) ($r['trim_name'] ?? '')) : '';
            if ($trim_name !== '' && is_numeric($r['price'] ?? null) && $r['price'] > 0) {
                $price_rows[] = [$trim_name, $trim_urls[$trim_name] ?? '', $r['price'] + 0];
            }
        }
    }
    ?>
    <main id="primary" class="site-main car-directory bg-white pb-24">
        <article class="w-full px-margin-mobile lg:px-margin py-space-xl">

            <?php
            car_breadcrumbs($crumbs);
            car_title_row($title, array_slice($crumbs, 0, -1), $eyebrow);
            ?>

            <div class="mb-6 flex flex-wrap items-center gap-2">
                <?php
                echo $price_range; // built by car_pill(): escaped
                car_chips($year_id, ['car_year', 'body_type']);
                ?>
            </div>

            <?php car_description(get_the_content()); ?>

            <!-- Sub-families and trims -->
            <div class="car-year-trims mt-space-xl">
                <?php
                car_section_header('فئات ' . $title, 'اختر من بين الفئات', $tiles ? n_trims(count($tiles)) : '');
                if ($tiles) {
                    car_strip($tiles);
                } else {
                    car_empty_state('لا توجد فئات مرتبطة بهذه السنة.');
                }
                ?>
            </div>

            <?php if ($price_rows): ?>
                <div class="car-year-prices mt-space-xl">
                    <?php car_price_table('أسعار ' . $title, ['الفئة', 'السعر'], $price_rows); ?>
                </div>
            <?php endif; ?>

            <?php if ($pics): ?>
            <div class="car-year-gallery mt-space-xl">
                <?php
                car_section_header('صور ' . $title, 'معرض الصور', $pics ? n_images(count($pics)) : '');
                echo '<div class="max-w-4xl">';
                car_gallery($year_id, $title);
                echo '</div>';
                ?>
            </div>
            <?php endif; ?>

        </article>
    </main>
    <?php
endwhile;

get_footer();
