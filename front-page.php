<?php
/**
 * Front Page Template
 * Reuses the same Flexible Content field as Departments
 */

get_header();
?>

<main class="homepage-main">

    <?php
    $sections = get_field('department_sections');

    if (!empty($sections)):
        $counter = 1;
        // Only sections listed in this array will be numbered and increment the counter
        $included_layouts = ['posts_section', 'infographics_section', 'interviews_section', 'programs_section', 'speakers_section'];

        foreach ($sections as $section):
            $layout = $section['acf_fc_layout'] ?? '';

            if (!$layout) {
                continue;
            }

            $should_count = in_array($layout, $included_layouts, true);
            $section_index = $should_count ? $counter : null;

            get_template_part(
                'template-parts/sections/' . $layout,
                null,
                [
                    'section' => $section,
                    'department' => null,
                    'section_index' => $section_index,
                ]
            );

            if ($should_count) {
                $counter++;
            }
        endforeach;
    else:
        echo '<p>' . esc_html__('No sections configured for the homepage.', 'hello-elementor-child') . '</p>';
    endif;
    ?>

</main>

<?php
get_footer();