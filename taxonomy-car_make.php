<?php
/**
 * car_make term: a make (/car-directory/<origin>/<make>/) or one of its models (/car-directory/<origin>/<make>/<model>/).
 *
 *   make:  title + origin pill, description, models strip, the make's price list (term meta _car_price_table)
 *   model: title + make / origin pills, description, model-year + year-leaf tiles strip, the newest year's trim prices
 *          (the stored price list with its trim names linked to our trim pages, or a list built from our own trims)
 */

get_header();

$term = get_queried_object();
$term = $term instanceof WP_Term ? $term : null;
$is_model = $term && $term->parent;
$make = $is_model ? get_term($term->parent, 'car_make') : $term;
$make = $make instanceof WP_Term ? $make : null;
$origin = $make ? car_make_origin($make) : null;
$parents = $is_model ? car_term_crumbs($origin, $make, null) : car_term_crumbs($origin, null, null);
$title = $term ? car_term_title($term) : '';
?>
<main id="primary" class="site-main car-directory">
    <?php
    car_section_open();
    if (!$term) {
        car_breadcrumbs([[car_directory_taxonomies()['car_make'][0], '']]);
        car_empty_state('هذه العلامة التجارية غير موجودة.');
        car_section_close();
        get_footer();
        return;
    }
    car_breadcrumbs(array_merge($parents, [[$term->name, '']]));
    car_title_row($title, $parents, $is_model ? 'الموديل' : 'العلامة التجارية');
    car_description($term->description);

    $links = []; // name in the stored price list => our page
    $latest_posts = []; // model page: the model-year posts of the newest year
    if ($is_model) {
        $tiles = car_model_year_tiles($term);
        car_section_header('اختر من بين سيارات ' . $title, car_directory_taxonomies()['car_year'][0], $tiles ? n_models(count($tiles)) : '');
        $tiles ? car_strip($tiles) : car_empty_state('لا توجد سنوات موديل لهذا الموديل بعد.');

        // the stored price list is the newest year's: link its trim names to our trim pages, starting with the
        // year post the list is named after (e.g. "هايلكس غمارتين 2027" when the year also has "غمارة 2027")
        $years = car_model_years($term);
        $latest = $years ? (string) get_field('model_year', $years[0]->ID) : '';
        $stored = json_decode((string) get_term_meta($term->term_id, '_car_price_table', true), true);
        $heading = is_array($stored) ? (string) ($stored['heading'] ?? '') : '';
        $latest_posts = array_values(array_filter($years, static fn(WP_Post $y) => (string) get_field('model_year', $y->ID) === $latest));
        usort($latest_posts, static fn(WP_Post $a, WP_Post $b) => (int) ($heading !== '' && str_contains($heading, get_the_title($b))) <=> (int) ($heading !== '' && str_contains($heading, get_the_title($a))));
        foreach ($latest_posts as $y) {
            foreach (car_year_trims($y->ID) as $car) {
                $name = (string) get_field('trim_name', $car->ID);
                if ($name !== '' && !isset($links[$name])) {
                    $links[$name] = get_permalink($car);
                }
            }
        }
    } else {
        $models = car_make_models($term);
        car_section_header('اختر من بين سيارات ' . $term->name, 'الموديلات', $models ? n_models(count($models)) : '');
        $models ? car_strip(array_map('car_term_tile', $models)) : car_empty_state('لا توجد موديلات لهذه العلامة التجارية بعد.');
        foreach ($models as $model) {
            $links[$model->name] = car_term_url($model);
        }
    }
    car_section_close();

    // price list: the stored one, or (model page) the newest year's trims from our own data
    ob_start();
    car_term_price_table($term, $links);
    $table = ob_get_clean();
    if ($table === '' && $latest_posts) {
        $rows = [];
        foreach (car_year_trims($latest_posts[0]->ID) as $car) {
            $cell = car_price_cell($car->ID);
            if ($cell[1] !== '') {
                $rows[] = [(string) get_field('trim_name', $car->ID) ?: get_the_title($car), get_permalink($car), $cell];
            }
        }
        ob_start();
        car_price_table('أسعار ' . get_the_title($latest_posts[0]), ['الفئة', 'السعر'], $rows);
        $table = ob_get_clean();
    }
    if ($table !== '') {
        car_section_open('pt-0');
        echo $table; // phpcs:ignore WordPress.Security.EscapeOutput -- built by car_price_table(), escaped there
        car_section_close();
    }
    ?>
</main>
<?php
get_footer();
