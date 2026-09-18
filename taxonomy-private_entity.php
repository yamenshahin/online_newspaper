<?php
/**
 * Taxonomy Archive: Private Entity
 *
 * Profile header + Home page Layout Engine sections,
 * filtered to this speaker (same idea as department + ?private_entity=).
 */

get_header();

$term = get_queried_object();

if (!$term instanceof WP_Term || is_wp_error($term)) {
    get_footer();
    return;
}

/**
 * Reuse section templates that already read $_GET['private_entity'].
 * Department URLs use the query string; this archive uses the term itself.
 */
$_GET[$term->taxonomy] = $term->slug;

// Home page ID (Layout Engine lives on the front page)
$home_id = (int) get_option('page_on_front');

// Layouts that do not belong on a single-speaker archive
$skip_layouts = [
    'hero_lead_section',
    'speakers_section', // list of all speakers
    // 'social_section',
    // 'ad_banner_section',
    // 'ad_code_section',
];
?>

<main id="primary" class="site-main ">

    <?php
    // 1. Profile header (your existing template part)
    get_template_part('template-parts/profiles/term-profile', null, [
        'term' => $term,
        'back_url' => home_url('/'),
        'back_label' => __('العودة إلى الرئيسية', 'hello-elementor-child'),
    ]);
    ?>

    <?php
    // 2. Sections from Home (same engine as front-page.php)
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
                    'department' => null, // homepage-style queries
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