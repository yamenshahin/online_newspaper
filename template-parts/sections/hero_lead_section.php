<?php
/**
 * Flexible Content: Hero Lead Section
 * Used on Home + Department archives
 * Editorial Split: Large Image | Text + Trending
 */
$section = $args['section'] ?? [];
$department = $args['department'] ?? null; // null on home, WP_Term on department

$featured = $section['hero_featured'] ?? null;
$trending = $section['hero_trending'] ?? [];
$section_title = $section['section_title'] ?? '';

if ($featured && !is_array($featured)) {
    $featured = [$featured];
}
$featured_post = $featured[0] ?? null;

if (!is_array($trending)) {
    $trending = $trending ? [$trending] : [];
}

// ── Fallback: auto content when on a department and ACF is empty ──
if (!$featured_post && $department instanceof WP_Term) {
    $auto = new WP_Query([
        'post_type' => ['post', 'interview', 'program', 'infographic'],
        'posts_per_page' => 1,
        'tax_query' => [
            [
                'taxonomy' => 'department',
                'field' => 'term_id',
                'terms' => $department->term_id,
            ]
        ],
        'no_found_rows' => true,
    ]);
    if ($auto->have_posts()) {
        $featured_post = $auto->posts[0];
    }
    wp_reset_postdata();
}

if (empty($trending) && $department instanceof WP_Term) {
    $auto_trending = new WP_Query([
        'post_type' => ['post', 'interview', 'program', 'infographic'],
        'posts_per_page' => 3,
        'post__not_in' => $featured_post ? [$featured_post->ID] : [],
        'tax_query' => [
            [
                'taxonomy' => 'department',
                'field' => 'term_id',
                'terms' => $department->term_id,
            ]
        ],
        'no_found_rows' => true,
    ]);
    $trending = $auto_trending->posts;
    wp_reset_postdata();
}

if (!$featured_post) {
    return; // nothing to show
}

// ── Meta label = CPT (mapped) ──
$type_map = [
    'post' => 'آخر الأخبار',
    'interview' => 'حوارات ولقاءات',
    'program' => 'آخر الأخبار بالفيديو',
    'infographic' => 'إنفوجرافيك',
];

$post_type_obj = get_post_type_object($featured_post->post_type);
$f_meta = $type_map[$featured_post->post_type]
    ?? ($post_type_obj ? $post_type_obj->labels->singular_name : '');

// ── Department Label Logic ──
$f_departments = get_the_terms($featured_post->ID, 'department');
if (!empty($f_departments) && !is_wp_error($f_departments)) {
    $f_dept_name = $f_departments[0]->name; // Grab first department if multiple exist
    $f_meta = 'آخر الأخبار ' . $f_dept_name;
}

$f_link = get_permalink($featured_post->ID);
$helper_icon_path = get_stylesheet_directory() . '/assets/images/helper-icon.svg';
?>
<section class="w-full px-margin-mobile lg:px-margin py-space-xl bg-white">
    <?php if (!empty($section_title)): ?>
        <div class="flex flex-col gap-space-md mb-10 md:mb-12 text-start">
            <h2 class="font-headline-xl text-headline-xl-mobile md:text-headline-xl font-bold text-gray-900 leading-tight">
                <?php
                $formatted_title = str_replace('<span>', '<span class="text-primary">', $section_title);
                $formatted_title = str_replace('•', '<span class="text-gray-300 px-2 inline-block">•</span>', $formatted_title);
                echo wp_kses_post($formatted_title);
                ?>
            </h2>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 md:gap-12 items-stretch">
        <!-- FEATURED IMAGE (Right in RTL) -->
        <div class="lg:col-span-7 min-h-[400px] lg:min-h-[600px] relative h-full">
            <a href="<?php echo esc_url($f_link); ?>"
                class="block w-full h-full relative group overflow-hidden rounded-2xl shadow-sm border border-gray-100">
                <?php if (has_post_thumbnail($featured_post->ID)): ?>
                    <?php echo get_the_post_thumbnail($featured_post->ID, 'full', [
                        'class' => 'absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-105'
                    ]); ?>
                <?php else: ?>
                    <div class="absolute inset-0 bg-gray-50 w-full h-full flex items-center justify-center">
                        <span class="text-gray-400 font-label-caps tracking-widest uppercase">No Image</span>
                    </div>
                <?php endif; ?>
            </a>
        </div>

        <!-- TEXT + TRENDING (Left in RTL) -->
        <div class="lg:col-span-5 flex flex-col justify-between">
            <div class="mb-10 text-start">
                <div class="flex items-center gap-2 mb-4 text-primary">
                    <div
                        class="w-4 h-4 flex items-center justify-center [&>svg]:w-full [&>svg]:h-full [&>svg]:fill-current">
                        <?php if (file_exists($helper_icon_path))
                            echo file_get_contents($helper_icon_path); ?>
                    </div>
                    <span class="font-label-caps text-sm uppercase tracking-widest font-bold mt-0.5">
                        <?php echo esc_html($f_meta); ?>
                    </span>
                </div>
                <a href="<?php echo esc_url($f_link); ?>" class="block group">
                    <h1
                        class="text-3xl md:text-4xl lg:text-5xl font-bold text-gray-900 leading-[1.3] tracking-tight group-hover:text-primary transition-colors">
                        <?php echo esc_html(get_the_title($featured_post->ID)); ?>
                    </h1>
                </a>
            </div>

            <div class="flex flex-col">
                <?php foreach ($trending as $t_post):
                    get_template_part('template-parts/cards/card', 'hero-trending', ['post' => $t_post]);
                endforeach; ?>
            </div>
        </div>
    </div>
</section>