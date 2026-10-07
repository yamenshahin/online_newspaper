<?php
/**
 * Flexible Content: government_entities_section
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
        'taxonomy' => 'government_entity',
        'section_title' => $section_title ?: __('الجهات الحكومية', 'hello-elementor-child'),
        'subtitle' => $subtitle,
        'department' => $department,
    ]
);