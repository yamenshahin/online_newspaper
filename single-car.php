<?php
/**
 * Trim page (/car-directory/<origin>/<make>/<model>/<year>/<trim>/).
 *
 * Breadcrumbs, title row, hero (gallery | price box + taxonomy chips), highlights, the clamped
 * description, one spec section per ACF tab behind a sticky tab nav, then the sibling trims and
 * rival cars that exist in the directory.
 *
 * Markup comes from inc/cars/helpers.php (builders return html, renderers echo; everything is
 * escaped there) and inc/cars/fields.php (Car_Spec_Renderer); behaviour from
 * assets/js/car-directory.js, styles from the .car-directory block in src/input.css.
 */

get_header();

while (have_posts()):
    the_post();

    $car_id = get_the_ID();
    $title = get_the_title();
    $crumbs = car_post_crumbs($car_id);
    $model_year = trim((string) get_field('model_year', $car_id));
    $spec_region = trim((string) get_field('spec_region', $car_id));
    $price_note = trim((string) get_field('price_note', $car_id));
    $features = wp_get_object_terms($car_id, 'car_feature');
    $has_features = !is_wp_error($features) && !empty($features);
    $has_highlights = (bool) array_filter(
        (array) get_field('highlights', $car_id),
        static fn($h) => is_array($h) && trim((string) ($h['value'] ?? '')) !== ''
    );
    $description = get_the_content();
    $has_description = trim(wp_strip_all_tags($description)) !== '';

    // The hero already shows the price, currency, VAT line, "starting from", price note and warranty:
    // keep them out of the السعر spec section (its price table, financing and maintenance rows stay).
    $spec_skip = array_merge(
        Car_Spec_Renderer::HIDDEN_CAR_FIELDS,
        ['price', 'price_currency', 'vat_included', 'price_is_starting_from', 'price_note', 'warranty']
    );
    $specs = (new Car_Spec_Renderer($car_id))->group('group_car_details', $spec_skip);
    ?>
    <main id="primary" class="site-main car-directory bg-white pb-24">
        <article class="w-full px-margin-mobile lg:px-margin py-space-xl">

            <?php
            car_breadcrumbs($crumbs);
            car_title_row($title, array_slice($crumbs, 0, -1), $model_year !== '' ? 'موديل ' . $model_year : '');
            ?>

            <!-- Hero: gallery | price box + chips -->
            <div class="car-hero mb-8">
                <div class="min-w-0">
                    <?php car_gallery($car_id, $title); ?>
                </div>
                <div class="flex min-w-0 flex-col gap-4">
                    <?php if ($spec_region !== ''): ?>
                        <div><?php echo car_pill($spec_region, '', 'dark'); ?></div>
                    <?php endif; ?>

                    <?php car_price_box($car_id); ?>

                    <?php if ($price_note !== '' && car_price_text($car_id) === ''): ?>
                        <p class="text-body-sm text-on-surface-variant">
                            <?php echo esc_html($price_note); ?>
                        </p>
                    <?php endif; ?>

                    <?php car_chips($car_id, ['car_year', 'body_type', 'fuel_type', 'transmission', 'drivetrain']); ?>

                    <?php if ($has_features): ?>
                        <div>
                            <span class="mb-2 block text-body-sm font-semibold text-on-surface-variant">التجهيزات</span>
                            <?php car_chips($car_id, ['car_feature'], false); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($has_highlights): ?>
                <h2 class="mb-3 font-title-editorial text-title-editorial text-on-background">أبرز المواصفات</h2>
                <?php car_highlights($car_id); ?>
            <?php endif; ?>

            <?php if ($has_description): ?>
                <h2 class="mb-3 font-title-editorial text-title-editorial text-on-background">نظرة عامة</h2>
                <?php car_description($description); ?>
            <?php endif; ?>

            <?php if ($specs['html'] !== ''): ?>
                <div class="car-specs mt-space-xl">
                    <?php
                    car_section_header('المواصفات التفصيلية', 'المواصفات الكاملة');
                    echo Car_Spec_Renderer::nav($specs['nav']) . $specs['html']; // escaped inside the renderer
                    ?>
                </div>
            <?php endif; ?>

            <?php car_siblings($car_id); ?>

        </article>
    </main>
    <?php
endwhile;

get_footer();
