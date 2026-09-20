<?php
/**
 * Taxonomy Template: Department
 * Handles:
 * 1. Full multi-section layout
 * 2. Isolated CPT view (?view=program|infographic|interview|post)
 */

get_header();

$department = get_queried_object();
$paged = max(1, absint(get_query_var('paged')));

$view = get_query_var('view');
if (empty($view) && isset($_GET['view'])) {
    $view = sanitize_text_field(wp_unslash($_GET['view']));
}

$allowed_views = get_content_post_types();

// Check if any specific taxonomy filter is active
$is_filtered = false;
$filterable_taxonomies = [
    'government_entity',
    'private_entity',
    'speaker_influencer',
    'geographic',
    'program_series',
];
foreach ($filterable_taxonomies as $tax) {
    if (!empty($_GET[$tax])) {
        $is_filtered = true;
        break;
    }
}
?>

<main class="w-full">
    <div class="flex flex-col w-full">

        <?php if ($view && in_array($view, $allowed_views, true)): ?>

            <?php
            get_template_part('template-parts/department', 'single-cpt', [
                'department' => $department,
                'view' => $view,
                'paged' => $paged,
            ]);
            ?>

        <?php else: ?>

            <?php
            // Active Filter Header (if applicable)
            get_template_part('template-parts/sections/active-filter-header', null, [
                'department' => $department,
            ]);
            ?>

            <?php
            $sections = get_field('department_sections', $department);

            if (!empty($sections)):
                $counter = 1;
                // Only sections listed in this array will be numbered and increment the counter
                $included_layouts = ['posts_section', 'infographics_section', 'interviews_section', 'programs_section', 'speakers_section'];

                foreach ($sections as $section):
                    $layout = $section['acf_fc_layout'] ?? '';

                    if (!$layout) {
                        continue;
                    }

                    // Hide the hero section if a filter is applied
                    if ($is_filtered && $layout === 'hero_lead_section') {
                        continue;
                    }

                    $should_count = in_array($layout, $included_layouts, true);

                    get_template_part(
                        'template-parts/sections/' . $layout,
                        null,
                        [
                            'department' => $department,
                            'section' => $section,
                        ]
                    );

                    if ($should_count) {
                        $counter++;
                    }
                endforeach;
            else:
                ?>
                <div class="w-full px-margin-mobile lg:px-margin py-space-xl text-center">
                    <p class="font-body-lg text-body-lg text-on-surface-variant font-medium">
                        <?php esc_html_e('لم يتم إعداد أقسام لهذه الجهة بعد.', 'hello-elementor-child'); ?>
                    </p>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</main>

<?php
get_footer();