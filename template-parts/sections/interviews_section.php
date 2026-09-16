<?php
/**
 * Flexible Content: Interviews Section
 * Works on Department pages and Homepage
 */

$department = $args['department'] ?? null;
$section = $args['section'] ?? [];

if (!empty($section)) {
    $section_title = $section['section_title'] ?? '';
    $posts_limit = $section['posts_limit'] ?? 4;
} else {
    $section_title = get_sub_field('section_title');
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
    'post_type' => 'interview',
    'posts_per_page' => absint($posts_limit),
    'tax_query' => $tax_query,
    'no_found_rows' => true,
    'ignore_sticky_posts' => true,
]);

if (!$query->have_posts()) {
    return;
}

if ($department instanceof WP_Term) {
    $more_link = add_query_arg(array_merge(['view' => 'interview'], $more_link_args), get_term_link($department));
} else {
    $more_link = get_post_type_archive_link('interview');
}
?>

<section class="w-full px-margin-mobile lg:px-margin py-space-xl bg-surface-container-low">
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-space-md mb-space-lg">
        <div class="flex flex-col gap-space-xs">
            <div class="flex items-center gap-space-xs">
                <span class="font-headline-lg text-headline-lg text-primary font-bold">03 /</span>
                <h2 class="font-headline-lg text-headline-lg text-on-background">
                    <?php echo esc_html($section_title ?: 'حوارات ملهمة • INTERVIEWS & PODCASTS'); ?>
                </h2>
                <span
                    class="px-space-sm py-0.5 rounded-full bg-secondary-container text-on-secondary font-label-caps text-label-caps font-bold">جلسات
                    مطولة</span>
            </div>
            <p class="font-body-sm text-body-sm text-on-surface-variant">لقاءات معمقة مع قادة التحول التكنولوجي وصناع
                القرار في المنظومة السعودية</p>
        </div>

        <a class="px-space-md py-space-xs rounded-full bg-surface-container-lowest hover:bg-surface-container-high text-secondary font-label-pill text-label-pill transition-colors shadow-sm flex items-center gap-1 self-start md:self-auto"
            href="<?php echo esc_url($more_link); ?>">
            <span
                class=""><?php echo esc_html(sprintf(__('استمع لكافة %s', 'hello-elementor-child'), $section_title ?: 'الحلقات')); ?></span>
            <span class="material-symbols-outlined text-[14px]">arrow_back</span>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-gutter">
        <?php while ($query->have_posts()):
            $query->the_post(); ?>
            <?php get_template_part('template-parts/cards/card', 'interview'); ?>
        <?php endwhile; ?>
    </div>
</section>

<?php wp_reset_postdata(); ?>