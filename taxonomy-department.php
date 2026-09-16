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

$allowed_views = ['program', 'infographic', 'interview', 'post'];
?>

<main class="w-full pt-20 bg-background">
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
                foreach ($sections as $section):
                    $layout = $section['acf_fc_layout'] ?? '';

                    if (!$layout) {
                        continue;
                    }

                    get_template_part(
                        'template-parts/sections/' . $layout,
                        null,
                        [
                            'department' => $department,
                            'section' => $section,
                        ]
                    );
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