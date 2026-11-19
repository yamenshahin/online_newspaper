<?php
/**
 * Flexible Content: cars of the week. Four cars of the recent model years, one per make, picked once a week
 * (inc/cars/highlights.php: the pick and the card). Header recipe of the car directory home section: the title
 * is the eyebrow when a subtitle gives the headline. Offered on the front page and on every department; skipped
 * on entity term archives (get_term_archive_skip_layouts()). The wrapper carries `car-directory`: the component
 * CSS of the car blocks is scoped to it.
 */

if (!function_exists('get_car_highlights') || !function_exists('car_section_header')) {
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

$cars = get_car_highlights();
if (!$cars) {
    return;
}

$section_title = trim((string) $section_title) ?: __("This week's highlights", 'hello-elementor-child');
$subtitle = trim((string) $subtitle);
$headline = $subtitle !== '' ? $subtitle : $section_title;
$eyebrow = $subtitle !== '' ? $section_title : __('Recent models', 'hello-elementor-child');
?>

<div class="car-directory car-week" id="car-week">
    <?php
    car_section_open('car-week-section');
    car_section_header($headline, $eyebrow, '', get_cars_archive_url(), __('All cars', 'hello-elementor-child'));
    ?>

    <div class="grid grid-cols-1 gap-gutter sm:grid-cols-2 lg:grid-cols-4">
        <?php
        foreach ($cars as $car) {
            echo car_highlight_card($car); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the builder
        }
        ?>
    </div>

    <?php car_section_close(); ?>
</div>
