<?php
/**
 * Flexible Content: Hero Lead Section
 * Image (16:9) + post title under it | Trending list
 * Desktop ≥1360px: columns equal height (trending stretches)
 * Mobile: stacked, tight trending rows
 */
$section = $args['section'] ?? [];
$department = $args['department'] ?? null;
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

if (!$featured_post && $department instanceof WP_Term) {
    $auto = new WP_Query(
        [
            'post_type' => ['post', 'interview', 'program', 'infographic'],
            'posts_per_page' => 1,
            'tax_query' => [
                [
                    'taxonomy' => 'department',
                    'field' => 'term_id',
                    'terms' => $department->term_id,
                ],
            ],
            'no_found_rows' => true,
        ]
    );
    if ($auto->have_posts()) {
        $featured_post = $auto->posts[0];
    }
    wp_reset_postdata();
}

if (empty($trending) && $department instanceof WP_Term) {
    $auto_trending = new WP_Query(
        [
            'post_type' => ['post', 'interview', 'program', 'infographic'],
            'posts_per_page' => 4,
            'post__not_in' => $featured_post ? [$featured_post->ID] : [],
            'tax_query' => [
                [
                    'taxonomy' => 'department',
                    'field' => 'term_id',
                    'terms' => $department->term_id,
                ],
            ],
            'no_found_rows' => true,
        ]
    );
    $trending = $auto_trending->posts;
    wp_reset_postdata();
}

if (!$featured_post) {
    return;
}

$f_link = get_permalink($featured_post->ID);
?>

<section class="w-full px-margin-mobile lg:px-margin py-space-xl bg-white">

    <?php if (!empty($section_title)): ?>
        <div class="flex flex-col gap-space-md mb-10 md:mb-12 text-start">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-900 leading-tight">
                <?php
                $formatted_title = str_replace('<span>', '<span class="text-primary">', $section_title);
                $formatted_title = str_replace('•', '<span class="text-gray-300 px-2 inline-block">•</span>', $formatted_title);
                echo wp_kses_post($formatted_title);
                ?>
            </h1>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 min-[1360px]:grid-cols-12 gap-6 md:gap-12 items-start min-[1360px]:items-stretch">

        <!-- Image + featured post title under it -->
        <div class="min-[1360px]:col-span-7 flex flex-col gap-4 md:gap-6">
            <a href="<?php echo esc_url($f_link); ?>"
                class="block w-full aspect-video relative group overflow-hidden rounded-2xl shadow-sm border border-gray-100 bg-gray-100">
                <?php if (has_post_thumbnail($featured_post->ID)): ?>
                    <?php
                    echo get_the_post_thumbnail(
                        $featured_post->ID,
                        'large',
                        [
                            'class' => 'skip-lazy absolute inset-0 w-full h-full object-cover object-center transition-transform duration-700 group-hover:scale-105',
                            'loading' => 'eager',
                            'fetchpriority' => 'high',
                            'decoding' => 'async',
                            'data-skip-lazy' => '1',
                        ]
                    );
                    ?>
                <?php else: ?>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="text-gray-400 font-label-caps tracking-widest uppercase">No Image</span>
                    </div>
                <?php endif; ?>
            </a>

            <div class="text-start">
                <a href="<?php echo esc_url($f_link); ?>" class="block group">
                    <h2
                        class="text-2xl md:text-3xl font-bold text-gray-900 leading-snug tracking-tight group-hover:text-primary transition-colors">
                        <?php echo esc_html(get_the_title($featured_post->ID)); ?>
                    </h2>
                </a>
            </div>
        </div>

        <!-- Trending -->
        <div class="min-[1360px]:col-span-5 flex flex-col">
            <div
                class="flex flex-col gap-1 sm:gap-2 min-[1360px]:flex-1 min-[1360px]:justify-between min-[1360px]:gap-2">
                <?php
                foreach ($trending as $t_post) {
                    get_template_part('template-parts/cards/card', 'hero-trending', ['post' => $t_post]);
                }
                ?>
            </div>
        </div>
    </div>

</section>