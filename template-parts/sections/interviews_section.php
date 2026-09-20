<?php
/**
 * Flexible Content: Interviews Section
 * Works on Department pages and Homepage
 */

$department = $args['department'] ?? null;
$section = $args['section'] ?? [];

if (!empty($section)) {
    $section_title = $section['section_title'] ?? '';
    $subtitle = $section['subtitle'] ?? '';
    $posts_limit = $section['posts_limit'] ?? 4;
} else {
    $section_title = get_sub_field('section_title');
    $subtitle = get_sub_field('subtitle');
    $posts_limit = get_sub_field('posts_limit') ?: 4;
}

$tax_query = ['relation' => 'AND'];
$is_department_page = ($department instanceof WP_Term);

if ($is_department_page) {
    $tax_query[] = [
        'taxonomy' => 'department',
        'field' => 'term_id',
        'terms' => $department->term_id,
    ];
}

$filterable_taxonomies = [
    'government_entity',
    'private_entity',
    'speaker_influencer',
    'geographic',
    'program_series',
];
$more_link_args = [];

foreach ($filterable_taxonomies as $tax) {
    if (!empty($_GET[$tax])) {
        $term_slug = sanitize_text_field(wp_unslash($_GET[$tax]));
        $tax_query[] = [
            'taxonomy' => $tax,
            'field' => 'slug',
            'terms' => $term_slug,
        ];
        $more_link_args[$tax] = $term_slug;
    }
}

$query = new WP_Query([
    'post_type' => 'interview',
    'posts_per_page' => absint($posts_limit),
    'tax_query' => $tax_query,
    'no_found_rows' => true,
    'ignore_sticky_posts' => true,
]);

if (!$query->have_posts()) {
    return;
}

if ($is_department_page) {
    $more_link = add_query_arg(array_merge(['view' => 'interview'], $more_link_args), get_term_link($department));
} else {
    $more_link = get_post_type_archive_link('interview');
}

$more_icon_path = get_stylesheet_directory() . '/assets/images/more-icon.svg';
$more_icon_uri = get_stylesheet_directory_uri() . '/assets/images/more-icon.svg';
?>

<section class="w-full px-margin-mobile lg:px-margin py-space-xl">
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-space-md mb-space-lg">
        <div class="flex flex-col gap-space-xs text-start">
            <div class="flex items-center gap-space-xs">
                <?php render_department_icon($department ?? null); ?>
                <h2 class="font-headline-lg text-headline-lg text-on-background">
                    <?php echo esc_html($section_title ?: 'حوارات ملهمة • INTERVIEWS & PODCASTS'); ?>
                </h2>
                <span
                    class="px-space-sm py-0.5 rounded-full bg-primary-container text-on-primary font-label-caps text-label-caps font-bold">جلسات
                    مطولة</span>
            </div>
            <p class="font-body-sm text-body-sm text-on-surface-variant">
                <?php echo esc_html($subtitle ?: 'لقاءات معمقة مع قادة التحول التكنولوجي وصناع القرار في المنظومة السعودية'); ?>
            </p>
        </div>

        <a href="<?php echo esc_url($more_link); ?>"
            class="inline-flex items-center gap-1.5 self-start md:self-auto text-base md:text-lg font-medium text-primary hover:opacity-70 transition-opacity">
            <?php if (file_exists($more_icon_path)): ?>
                <span class="inline-flex w-5 h-5 shrink-0 [&>svg]:w-full [&>svg]:h-full" aria-hidden="true">
                    <?php echo file_get_contents($more_icon_path); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents ?>
                </span>
            <?php else: ?>
                <img src="<?php echo esc_url($more_icon_uri); ?>" alt="" class="w-5 h-5" width="20" height="20" />
            <?php endif; ?>
            <span><?php esc_html_e('المزيد', 'hello-elementor-child'); ?></span>
        </a>
    </div>

    <!-- Updated to 4 columns on large screens -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-gutter">
        <?php while ($query->have_posts()):
            $query->the_post();
            $post_id = get_the_ID();
            $interview_label = 'حوار'; // Fallback
        
            if ($is_department_page) {
                // Section is on a Department Page: Pull the "post_editor" taxonomy
                $editors = get_the_terms($post_id, 'post_editor');
                if (!empty($editors) && !is_wp_error($editors)) {
                    $interview_label = $editors[0]->name;
                } else {
                    $interview_label = 'تفاعل السعودية';
                }
            } else {
                // Section is on the Home Page: Pull the "department" taxonomy
                $departments = get_the_terms($post_id, 'department');
                if (!empty($departments) && !is_wp_error($departments)) {
                    $interview_label = $departments[0]->name;
                }
            }

            get_template_part('template-parts/cards/card', 'interview', ['interview_label' => $interview_label]);
        endwhile; ?>
    </div>
</section>

<?php wp_reset_postdata(); ?>s