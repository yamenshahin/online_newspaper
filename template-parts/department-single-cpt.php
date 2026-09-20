<?php
/**
 * Department isolated CPT view ← shared cpt-loop
 */

$department = $args['department'] ?? null;
$view = $args['view'] ?? '';
$paged = $args['paged'] ?? 1;

if (!$department instanceof WP_Term || !is_content_post_type($view)) {
    return;
}

get_template_part('template-parts/archive/cpt-loop', null, [
    'post_type' => $view,
    'department' => $department,
    'paged' => $paged,
    'use_main_query' => false,
]);