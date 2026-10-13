<?php
/**
 * Taxonomy Archive: Event
 * /event/{slug}/ — profile + department nav + home Layout Engine
 * filtered via $_GET['event'].
 */
get_header();

$term = get_queried_object();
if (!$term instanceof WP_Term || is_wp_error($term)) {
    get_footer();
    return;
}

// Section templates read $_GET filters the same way as department + ?event=
$_GET[$term->taxonomy] = $term->slug;

$home_id = (int) get_option('page_on_front');


?>
<main id="primary" class="site-main">
    <?php
    get_template_part('template-parts/profiles/term-profile', null, [
        'term' => $term,
        'back_url' => home_url('/'),
        'back_label' => __('العودة إلى الرئيسية', 'hello-elementor-child'),
    ]);

    get_template_part('template-parts/profiles/filter-departments-nav', null, [
        'term' => $term,
        'current_department' => null, // "الكل"
    ]);
    ?>

    <?php
    $sections = $home_id ? get_field('department_sections', $home_id) : null;

    if (!empty($sections)):
        foreach ($sections as $section):
            $layout = $section['acf_fc_layout'] ?? '';
            if (should_skip_term_archive_layout($layout)) {
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
        <div class="w-full px-6 py-16 text-center">
            <p class="text-neutral-500">
                <?php esc_html_e('No sections configured for the homepage.', 'hello-elementor-child'); ?>
            </p>
        </div>
    <?php endif; ?>
</main>
<?php
get_footer();