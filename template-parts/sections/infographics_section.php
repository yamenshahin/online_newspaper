<?php
/**
 * Flexible Content: Infographics Section
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

if ($department instanceof WP_Term) {
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
    'post_type' => 'infographic',
    'posts_per_page' => absint($posts_limit),
    'tax_query' => $tax_query,
    'no_found_rows' => true,
    'ignore_sticky_posts' => true,
]);

if (!$query->have_posts()) {
    return;
}

if ($department instanceof WP_Term) {
    $more_link = add_query_arg(array_merge(['view' => 'infographic'], $more_link_args), get_term_link($department));
} else {
    $more_link = get_post_type_archive_link('infographic');
}

$more_icon_path = get_stylesheet_directory() . '/assets/images/more-icon.svg';
$more_icon_uri = get_stylesheet_directory_uri() . '/assets/images/more-icon.svg';
?>

<section class="w-full px-margin-mobile lg:px-margin py-space-xl">
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-space-md mb-space-lg">
        <div class="flex flex-col gap-space-xs">
            <div class="flex items-center gap-space-xs">
                <?php render_department_icon($department ?? null); ?>
                <h2 class="font-headline-lg text-headline-lg text-on-background">
                    <?php echo esc_html($section_title ?: 'مختبر البيانات والإنفوجرافيك'); ?>
                </h2>
                <span
                    class="px-space-sm py-0.5 rounded-full bg-primary-container text-on-primary font-label-caps text-label-caps font-bold">أرقام
                    تفاعلية</span>
            </div>
            <p class="font-body-sm text-body-sm text-on-surface-variant">
                <?php echo esc_html($subtitle ?: 'تحليلات بصرية ميكروية ورسوم بيانية ترصد تسارع منظومة التكنولوجيا والتحول الرقمي'); ?>
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

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-gutter">
        <?php while ($query->have_posts()):
            $query->the_post(); ?>
            <?php get_template_part('template-parts/cards/card', 'infographic'); ?>
        <?php endwhile; ?>
    </div>
</section>

<?php wp_reset_postdata(); ?>