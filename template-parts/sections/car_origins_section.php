<?php
/**
 * Flexible Content: car brands by country of origin, one flag tile per origin (its page lists the makes), the
 * number of brands and a button to the car directory. The last block of car_home_section as a section of its own,
 * for the front page and the departments; skipped on entity term archives (get_term_archive_skip_layouts()).
 * Header and spacing of the other filter sections (geographic, entities): the title as the eyebrow, the subtitle
 * as the h3. The wrapper carries `car-directory`: the component CSS of the tiles and grids is scoped to it.
 */

if (!function_exists('car_origins_with_makes')) {
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

$section_title = trim((string) $section_title) ?: __('Car Directory', 'hello-elementor-child');
$subtitle = trim((string) $subtitle) ?: __('All car brands by country of origin', 'hello-elementor-child');

$origins = car_origins_with_makes();
if (!$origins) {
    return;
}
?>

<section
    class="car-directory car-origins w-full px-margin-mobile lg:px-margin py-space-lg border-b border-surface-container-highest/30 last:border-0">
    <div class="flex flex-col gap-space-md">
        <div class="flex flex-col items-start gap-space-xs text-start">
            <span class="font-label-caps text-label-caps text-primary uppercase tracking-widest">
                <?php echo esc_html($section_title); ?>
            </span>
            <div class="mt-1 flex flex-wrap items-center gap-space-sm">
                <h3 class="font-headline-sm text-headline-sm text-on-background">
                    <?php echo esc_html($subtitle); ?>
                </h3>
                <span
                    class="rounded-full bg-surface-container px-space-sm py-0.5 font-label-caps text-label-caps text-on-surface-variant"><?php echo esc_html(n_makes(array_sum(array_column($origins, 1)))); ?></span>
            </div>
        </div>

        <?php car_grid(array_map(static fn(array $origin): string => car_origin_tile($origin[0], $origin[1]), $origins)); ?>
    </div>

    <?php car_more_cars_button(); ?>
</section>
