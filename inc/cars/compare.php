<?php
/**
 * Car comparison section (template-parts/sections/car_compare_section.php): the picker lists, the REST routes
 * and the side-by-side table.
 *
 * The visitor picks two to four cars, each through make > model > trim. assets/js/car-compare.js loads the
 * dependent lists and the table from the public GET routes below and mirrors the choice in ?car_compare=ID,ID,
 * so a comparison can be shared; a shared link is rendered by the server with the same builders, without a
 * request. No nonce anywhere: the page HTML is cached for days. The parameter is prefixed because a bare
 * taxonomy name in the query string would turn the page into a term archive (see inc/cars.php).
 */

if (!defined('ABSPATH')) {
    exit;
}

const CAR_COMPARE_MIN = 2;
const CAR_COMPARE_MAX = 4;
const CAR_COMPARE_PARAM = 'car_compare';

// ---------------------------------------------------------------------------
// Picker lists
// ---------------------------------------------------------------------------

/** Makes of the picker: every visible top-level make, by name. @return array<int, array{id: int, name: string}> */
function car_compare_makes(): array
{
    $makes = get_terms(['taxonomy' => 'car_make', 'parent' => 0, 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC']);
    return car_compare_term_items(is_wp_error($makes) ? [] : car_visible_terms($makes));
}

/** Models of a make, most cars first; cached for a day per data version. @return array<int, array{id: int, name: string}> */
function car_compare_models(WP_Term $make): array
{
    $key = 'car_compare_models_' . $make->term_id . '_' . car_directory_data_version();
    $models = get_transient($key);
    if (!is_array($models)) {
        $models = car_compare_term_items(car_make_models($make));
        set_transient($key, $models, DAY_IN_SECONDS);
    }
    return $models;
}

/** [{id, name}] of the terms that have published cars: one without any would be a dead end in the picker. */
function car_compare_term_items(array $terms): array
{
    $items = [];
    foreach ($terms as $term) {
        if (car_term_count($term) > 0) {
            $items[] = ['id' => $term->term_id, 'name' => wp_specialchars_decode($term->name, ENT_QUOTES)];
        }
    }
    return $items;
}

/**
 * Published trims of a model for the car select, newest model year first; cached for a day per data version.
 *
 * @return array<int, array{id: int, label: string, year: int}>
 */
function car_compare_cars(WP_Term $model): array
{
    $key = 'car_compare_cars_' . $model->term_id . '_' . car_directory_data_version();
    $cars = get_transient($key);
    if (is_array($cars)) {
        return $cars;
    }
    $cars = [];
    foreach (get_posts([
        'post_type' => 'car',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'no_found_rows' => true,
        'tax_query' => [['taxonomy' => 'car_make', 'terms' => $model->term_id, 'include_children' => false]],
    ]) as $car) {
        $name = car_compare_trim($car, $model);
        $cars[] = ['id' => $car->ID, 'label' => $name['label'], 'year' => $name['year']];
    }
    usort($cars, static fn(array $a, array $b): int => ($b['year'] <=> $a['year']) ?: strnatcasecmp($a['label'], $b['label']));
    set_transient($key, $cars, DAY_IN_SECONDS);
    return $cars;
}

// ---------------------------------------------------------------------------
// Names
// ---------------------------------------------------------------------------

/**
 * Model year of a car, what tells it from the other trims of its model, and the two as the car select prints
 * them ("2026 · Luxury").
 *
 * The trim is what the title adds to the model's name ("تويوتا هايلكس غمارتين GLX 2027" gives "غمارتين GLX"):
 * the trim_name field leaves out the sub-family, so "Luxury" and "Hybrid + Luxury" would both read "Luxury".
 * It is '' for a car that is a whole model year.
 *
 * @return array{year: int, trim: string, label: string}
 */
function car_compare_trim(WP_Post $car, ?WP_Term $model): array
{
    $year = (int) get_post_meta($car->ID, 'model_year', true);
    $title = wp_specialchars_decode($car->post_title, ENT_QUOTES);
    $words = preg_split('/\s+/u', $title, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $title_length = count($words);
    // Leading words shared with the model's full name, then with its own name (a title can leave the make's Latin name out).
    foreach ($model ? [car_term_title($model), $model->name] : [] as $name) {
        $shared = 0;
        foreach (preg_split('/\s+/u', wp_specialchars_decode($name, ENT_QUOTES), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $i => $word) {
            if (($words[$i] ?? null) !== $word) {
                break;
            }
            $shared++;
        }
        $words = array_slice($words, $shared);
    }
    // Nothing shared (the title spells the make its own way, or the model has no full name): the trim is what
    // follows the model's name, wherever that stands in the title.
    $model_words = $model ? (preg_split('/\s+/u', wp_specialchars_decode($model->name, ENT_QUOTES), -1, PREG_SPLIT_NO_EMPTY) ?: []) : [];
    if ($model_words && count($words) === $title_length) {
        for ($i = 0; $i + count($model_words) <= $title_length; $i++) {
            if (array_slice($words, $i, count($model_words)) === $model_words) {
                $words = array_slice($words, $i + count($model_words));
                break;
            }
        }
    }
    $trim = implode(' ', array_filter($words, static fn(string $word): bool => !$year || $word !== (string) $year));
    $label = implode(' · ', array_filter([$year ? (string) $year : '', $trim], 'strlen'));
    return ['year' => $year, 'trim' => $trim, 'label' => $label !== '' ? $label : $title];
}

/**
 * How a filled slot and a table column name a car of car_compare_ids(): "Make Model" (as car_listing_tile()
 * does), with the year, trim and label of car_compare_trim().
 *
 * @return array{id: int, title: string, year: int, trim: string, label: string}
 */
function car_compare_car(int $id): array
{
    $car = get_post($id);
    $terms = car_post_terms($id);
    $title = $terms['model'] ? trim(($terms['make']->name ?? '') . ' ' . $terms['model']->name) : $car->post_title;
    $named = ['id' => $id, 'title' => wp_specialchars_decode($title, ENT_QUOTES)] + car_compare_trim($car, $terms['model']);
    if ($named['trim'] === '' && !$named['year']) {
        $named['label'] = ''; // the car select needs a text (the title); a slot would only print the name twice
    }
    return $named;
}

// ---------------------------------------------------------------------------
// Request
// ---------------------------------------------------------------------------

/**
 * The cars a request may compare, in the order given: published cars only, without repeats, CAR_COMPARE_MAX at
 * most. One query, which also primes their post, meta and term caches.
 *
 * @param string|int[] $raw "12,34,56" or a list of ids
 * @return int[]
 */
function car_compare_ids(string|array $raw): array
{
    $ids = [];
    foreach (is_array($raw) ? $raw : explode(',', $raw) as $id) {
        $id = is_scalar($id) ? trim((string) $id) : '';
        if (ctype_digit($id) && (int) $id > 0) { // digits only: "12abc" is not car 12
            $ids[] = (int) $id;
        }
    }
    if (!$ids) {
        return [];
    }
    return wp_list_pluck(get_posts([
        'post_type' => 'car',
        'post_status' => 'publish',
        'post__in' => $ids,
        'orderby' => 'post__in',
        'posts_per_page' => CAR_COMPARE_MAX,
        'no_found_rows' => true,
    ]), 'ID');
}

/** Cars of a shared link (?car_compare=ID,ID). @return int[] */
function car_compare_request_ids(): array
{
    $raw = $_GET[CAR_COMPARE_PARAM] ?? ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public and read-only
    return is_string($raw) ? car_compare_ids(wp_unslash($raw)) : [];
}

/** "?car_compare=12,34": what the address bar carries for a comparison; '' when there are too few cars for one. */
function car_compare_query(array $ids): string
{
    return count($ids) >= CAR_COMPARE_MIN ? '?' . CAR_COMPARE_PARAM . '=' . implode(',', $ids) : '';
}

// ---------------------------------------------------------------------------
// REST
// ---------------------------------------------------------------------------

add_action(
    'rest_api_init',
    static function (): void {
        register_rest_route(
            'hello/v1',
            '/car-compare/models',
            [
                'methods' => 'GET',
                'permission_callback' => '__return_true',
                'callback' => 'rest_car_compare_models',
                'args' => [
                    'make' => ['type' => 'integer', 'required' => true, 'minimum' => 1],
                ],
            ]
        );
        register_rest_route(
            'hello/v1',
            '/car-compare/cars',
            [
                'methods' => 'GET',
                'permission_callback' => '__return_true',
                'callback' => 'rest_car_compare_cars',
                'args' => [
                    'model' => ['type' => 'integer', 'required' => true, 'minimum' => 1],
                ],
            ]
        );
        register_rest_route(
            'hello/v1',
            '/car-compare/table',
            [
                'methods' => 'GET',
                'permission_callback' => '__return_true',
                'callback' => 'rest_car_compare_table',
                'args' => [
                    'ids' => ['type' => 'string', 'required' => true],
                ],
            ]
        );
    }
);

/** GET /hello/v1/car-compare/models?make=4503: the models of a make, [{id, name}]. */
function rest_car_compare_models(WP_REST_Request $request): WP_REST_Response|WP_Error
{
    $make = get_term((int) $request->get_param('make'), 'car_make');
    if (!$make instanceof WP_Term || $make->parent) {
        return new WP_Error('invalid_make', 'Invalid make', ['status' => 404]);
    }
    return car_compare_rest_response(car_compare_models($make));
}

/** GET /hello/v1/car-compare/cars?model=5274: the published trims of a model, [{id, label, year}]. */
function rest_car_compare_cars(WP_REST_Request $request): WP_REST_Response|WP_Error
{
    $model = get_term((int) $request->get_param('model'), 'car_make');
    if (!$model instanceof WP_Term || !$model->parent) {
        return new WP_Error('invalid_model', 'Invalid model', ['status' => 404]);
    }
    return car_compare_rest_response(car_compare_cars($model));
}

/**
 * GET /hello/v1/car-compare/table?ids=12,34,56
 * Returns {ids, cars: [{id, title, sub}], html, url}: the ids that are published cars, how their slots name
 * them, the table (get_car_compare_table_html(), '' with fewer than two cars) and the query string to share.
 */
function rest_car_compare_table(WP_REST_Request $request): WP_REST_Response
{
    $ids = car_compare_ids((string) $request->get_param('ids'));
    $cars = [];
    foreach ($ids as $id) {
        $car = car_compare_car($id);
        $cars[] = ['id' => $id, 'title' => $car['title'], 'sub' => $car['label']];
    }
    return car_compare_rest_response([
        'ids' => $ids,
        'cars' => $cars,
        'html' => get_car_compare_table_html($ids),
        'url' => car_compare_query($ids),
    ]);
}

/** The lists and the table only change with an import: let browsers keep them briefly, as rest_entity_nav() does. */
function car_compare_rest_response(array $data): WP_REST_Response
{
    $response = rest_ensure_response($data);
    $response->header('Cache-Control', 'public, max-age=300');
    return $response;
}

// ---------------------------------------------------------------------------
// Table
// ---------------------------------------------------------------------------

/** A spec value as Car_Spec_Renderer prints it: the number with its unit, or the text; '' when the car has none. */
function car_compare_value(array $field, mixed $value): string
{
    if ($field['type'] === 'number') {
        if (!is_numeric($value)) {
            return '';
        }
        return '<span class="car-ltr">' . esc_html(format_number($value)) . '</span>'
            . (!empty($field['append']) ? ' <span class="text-body-sm text-on-surface-variant">' . esc_html($field['append']) . '</span>' : '');
    }
    if (is_array($value)) {
        $value = implode(' | ', array_map('strval', array_filter($value, 'is_scalar')));
    }
    return esc_html(trim((string) $value));
}

/**
 * Rows of the table in their groups: price and warranty, then the engine, electric, design, interior and safety
 * groups of the car field group with their text and number sub-fields (photos, colours and the importer's
 * variant code are not values to compare). A row is kept when at least one car has a value, a group when it
 * has a row.
 *
 * @param int[] $ids published cars
 * @return array<int, array{label: string, rows: array<int, array{0: string, 1: string[]}>}> one cell per car: escaped html, '' = no value
 */
function car_compare_groups(array $ids): array
{
    $groups = [[
        'label' => __('Price and warranty', 'hello-elementor-child'),
        'rows' => [
            // car_filter_price_text(): each car in its own currency, never a raw number
            [__('Price', 'hello-elementor-child'), array_map(static fn(int $id): string => esc_html(car_filter_price_text($id)), $ids)],
            [__('Warranty', 'hello-elementor-child'), array_map(static fn(int $id): string => nl2br(esc_html(trim((string) get_field('warranty', $id)))), $ids)],
        ],
    ]];

    $fields = function_exists('acf_get_fields') ? array_column(acf_get_fields('group_car_details') ?: [], null, 'name') : [];
    foreach (['engine', 'ev', 'design', 'interior', 'safety'] as $name) {
        $group = $fields[$name] ?? [];
        if (($group['type'] ?? '') !== 'group') {
            continue;
        }
        $values = [];
        foreach ($ids as $id) {
            $values[$id] = (array) get_field($name, $id); // null when the car has nothing in the group
        }
        $rows = [];
        foreach ($group['sub_fields'] ?? [] as $field) {
            if (!in_array($field['type'], ['text', 'number'], true) || $field['name'] === 'variant_code') {
                continue;
            }
            $rows[] = [(string) $field['label'], array_map(static fn(int $id): string => car_compare_value($field, $values[$id][$field['name']] ?? null), $ids)];
        }
        $groups[] = ['label' => (string) $group['label'], 'rows' => $rows];
    }

    foreach ($groups as $i => $group) {
        $groups[$i]['rows'] = array_values(array_filter($group['rows'], static fn(array $row): bool => implode('', $row[1]) !== ''));
    }
    return array_values(array_filter($groups, static fn(array $group): bool => (bool) $group['rows']));
}

/**
 * Side-by-side table of two to four cars: a column per car (photo, name, price, remove button), a row per value
 * (car_compare_groups()). '' with fewer than two published cars. Escaped here: the template and the REST route
 * print it as it is.
 *
 * The box scrolls sideways on narrow screens while the first column (the spec names) and the group headings
 * stay in view. Utilities only; `.car-ltr` needs the `.car-directory` wrapper of the section.
 *
 * @param int[] $ids
 */
function get_car_compare_table_html(array $ids): string
{
    $ids = car_compare_ids($ids);
    if (count($ids) < CAR_COMPARE_MIN) {
        return '';
    }
    $caption = __('Car comparison table', 'hello-elementor-child');
    $min_width = [2 => 'min-w-[28rem]', 3 => 'min-w-[39rem]', 4 => 'min-w-[50rem]'][count($ids)] ?? 'min-w-[50rem]';
    $sticky = 'sticky start-0 z-10 border-e border-surface-container-high'; // z-10: above the remove buttons that slide under it

    $head = '<td class="' . $sticky . ' w-28 border-b bg-surface-container-lowest sm:w-44 lg:w-56"></td>';
    foreach ($ids as $id) {
        $car = car_compare_car($id);
        $sub = trim($car['trim'] . ' ' . ($car['year'] ?: ''));
        $name = trim($car['title'] . ' ' . $sub);
        $image = car_card_image_url($id);
        $price = car_filter_price_text($id);
        $head .= '<th scope="col" abbr="' . esc_attr($name) . '" class="border-b border-surface-container-high px-3 py-4 text-start align-top font-normal md:px-4">'
            . '<div class="relative mb-space-sm aspect-video max-w-72 overflow-hidden rounded-xl bg-surface-container">'
            . car_img($image, $name, $image !== '' ? 'h-full w-full object-cover' : 'h-full w-full p-3')
            . '<button type="button" data-compare-remove="' . $id . '" class="absolute end-1.5 top-1.5 flex size-8 cursor-pointer items-center justify-center rounded-full bg-surface-container-lowest text-on-surface shadow-md transition-colors hover:bg-primary hover:text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"'
            /* translators: %s: car name */
            . ' aria-label="' . esc_attr(sprintf(__('Remove %s from the comparison', 'hello-elementor-child'), $name)) . '"><i class="fas fa-xmark" aria-hidden="true"></i></button>'
            . '</div>'
            . '<a class="block font-semibold leading-snug text-on-background transition-colors hover:text-primary" href="' . esc_url((string) get_permalink($id)) . '">' . esc_html($car['title']) . '</a>'
            . ($sub !== '' ? '<span class="block text-body-sm text-on-surface-variant">' . esc_html($sub) . '</span>' : '')
            . '<span class="mt-1 block font-semibold ' . ($price !== '' ? 'text-primary' : 'text-on-surface-variant') . '">' . esc_html($price !== '' ? $price : __('No price', 'hello-elementor-child')) . '</span>'
            . '</th>';
    }

    $body = '';
    foreach (car_compare_groups($ids) as $group) {
        // the heading's text is sticky too: it stays in view while its (full-width) cell scrolls
        $body .= '<tbody><tr><th colspan="' . (count($ids) + 1) . '" scope="rowgroup" class="bg-surface-container p-0 text-start">'
            . '<span class="sticky start-0 inline-block px-3 py-2.5 font-semibold text-on-background md:px-4">' . esc_html($group['label']) . '</span></th></tr>';
        foreach ($group['rows'] as [$label, $cells]) {
            // the sticky cell needs the row's own colour: a transparent one would show the values sliding under it
            $body .= '<tr class="group/row odd:bg-surface-container-low">'
                . '<th scope="row" class="' . $sticky . ' bg-surface-container-lowest px-3 py-3 text-start align-top font-semibold text-on-surface-variant group-odd/row:bg-surface-container-low md:px-4">' . esc_html($label) . '</th>';
            foreach ($cells as $cell) {
                $body .= '<td class="px-3 py-3 align-top text-on-surface md:px-4">' . ($cell !== '' ? $cell : '<span class="text-on-surface-variant opacity-70">—</span>') . '</td>';
            }
            $body .= '</tr>';
        }
        $body .= '</tbody>';
    }

    return '<div class="car-compare-table mt-space-lg overflow-x-auto rounded-xl border border-surface-container-high bg-surface-container-lowest" role="region" tabindex="0" aria-label="' . esc_attr($caption) . '">'
        . '<table class="w-full table-fixed border-separate border-spacing-0 break-words text-start text-body-sm md:text-body-base ' . $min_width . '">'
        . '<caption class="sr-only">' . esc_html($caption) . '</caption>'
        . '<thead><tr>' . $head . '</tr></thead>' . $body . '</table></div>';
}
