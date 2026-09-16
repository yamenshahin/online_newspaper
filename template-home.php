<?php
/**
 * Template Name: Home
 * Template Post Type: page
 *
 * Homepage using the Department Layout Engine.
 */

get_header();

$page_id = get_the_ID();
?>

<main class="w-full pt-20 bg-background">
    <div class="flex flex-col w-full">

        <?php
        $sections = get_field('department_sections', $page_id);

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
                        'department' => null,
                        'section' => $section,
                    ]
                );
            endforeach;
        else:
            ?>
            <div class="w-full px-margin-mobile lg:px-margin py-space-xl text-center">
                <p class="font-body-lg text-body-lg text-on-surface-variant font-medium">
                    <?php esc_html_e('لم يتم إعداد أقسام الصفحة الرئيسية بعد.', 'hello-elementor-child'); ?>
                </p>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php
get_footer();