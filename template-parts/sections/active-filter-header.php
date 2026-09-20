<?php
/**
 * Department + filter: resolve active term, then shared profile + dept nav.
 */

$department = $args['department'] ?? null;

if (!$department instanceof WP_Term) {
    return;
}

$filterable = get_filterable_taxonomies();
$active_term = null;

foreach ($filterable as $tax) {
    if (empty($_GET[$tax])) {
        continue;
    }

    $term = get_term_by(
        'slug',
        sanitize_text_field(wp_unslash($_GET[$tax])),
        $tax
    );

    if ($term && !is_wp_error($term)) {
        $active_term = $term;
        break;
    }
}

if (!$active_term) {
    return;
}

get_template_part('template-parts/profiles/term-profile', null, [
    'term' => $active_term,
    'back_url' => get_term_link($department),
    'back_label' => sprintf(
        /* translators: %s: department name */
        __('العودة إلى %s', 'hello-elementor-child'),
        $department->name
    ),
]);

get_template_part('template-parts/profiles/filter-departments-nav', null, [
    'term' => $active_term,
    'current_department' => $department,
]);