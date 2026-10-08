<?php
/**
 * Flexible Content: filter_section
 * Combined government + private entity browser.
 */
$department = $args['department'] ?? null;
$section = $args['section'] ?? [];

if (!empty($section)) {
    $section_title = $section['section_title'] ?? '';
    $subtitle = $section['subtitle'] ?? '';
} else {
    $section_title = get_sub_field('section_title');
    $subtitle = get_sub_field('subtitle');
}

get_template_part(
    'template-parts/entities/entity-browse-section',
    null,
    [
        'section_title' => $section_title ?: __('الجهات', 'hello-elementor-child'),
        'subtitle' => $subtitle,
        'department' => $department,
    ]
);