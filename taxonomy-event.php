<?php
/**
 * Taxonomy Archive: Event
 * /event/{slug}/  + home sections filtered via $_GET['event']
 */
get_header();

$term = get_queried_object();
if (!$term instanceof WP_Term || is_wp_error($term)) {
    get_footer();
    return;
}

$_GET[$term->taxonomy] = $term->slug;

$home_id = (int) get_option('page_on_front');

$skip_layouts = [
    'hero_lead_section',
    'events_section', // list of all events — not on a single event page
];
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
        'current_department' => null,
    ]);
    ?>

    <?php
    $sections = $home_id ? get_field('department_sections', $home_id) : null;
    if (!empty($sections)):
        foreach ($sections as $section):
            $layout = $section['acf_fc_layout'] ?? '';
            if (!$layout || in_array($layout, $skip_layouts, true)) {
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