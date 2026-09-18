<?php
/**
 * Flexible Content: Hero Lead Section (Home Page)
 * Editorial Split Layout: Large Image on the Right, Text & Trending on the Left
 * Minimalist Pure White Theme
 */

$section = $args['section'] ?? [];

// 1. Read and Normalize Data
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

if (!$featured_post) {
    return;
}

// 2. Dynamic Category/Meta Label 
$dept = get_the_terms($featured_post->ID, 'department');
$f_meta = (!empty($dept) && !is_wp_error($dept)) ? $dept[0]->name : '';

if (!$f_meta) {
    $post_type_obj = get_post_type_object($featured_post->post_type);
    $f_meta = $post_type_obj ? $post_type_obj->labels->singular_name : '';
}

$f_link = get_permalink($featured_post->ID);

// Path to your new SVG
$helper_icon_path = get_stylesheet_directory() . '/assets/images/helper-icon.svg';
?>

<section class="w-full px-margin-mobile lg:px-margin py-space-xl bg-white">

    <!-- DYNAMIC TITLE FROM ACF -->
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

    <!-- MAIN GRID -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 md:gap-12 items-stretch">

        <!-- 1. FEATURED IMAGE COLUMN (Sits on the RIGHT in RTL) -->
        <div class="lg:col-span-7 min-h-[400px] lg:min-h-[600px] relative h-full">
            <a href="<?php echo esc_url($f_link); ?>"
                class="block w-full h-full relative group overflow-hidden rounded-2xl shadow-sm border border-gray-100">
                <?php if (has_post_thumbnail($featured_post->ID)): ?>
                    <?php echo get_the_post_thumbnail($featured_post->ID, 'full', ['class' => 'absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-105']); ?>
                <?php else: ?>
                    <div class="absolute inset-0 bg-gray-50 w-full h-full flex items-center justify-center">
                        <span class="text-gray-400 font-label-caps tracking-widest uppercase">No Image</span>
                    </div>
                <?php endif; ?>
            </a>
        </div>

        <!-- 2. TEXT & TRENDING COLUMN (Sits on the LEFT in RTL) -->
        <div class="lg:col-span-5 flex flex-col justify-between">

            <!-- Featured Post Text Block -->
            <div class="mb-10 text-start">
                <div class="flex items-center gap-2 mb-4 text-primary">
                    <div
                        class="w-4 h-4 flex items-center justify-center [&>svg]:w-full [&>svg]:h-full [&>svg]:fill-current">
                        <?php
                        if (file_exists($helper_icon_path)) {
                            echo file_get_contents($helper_icon_path);
                        }
                        ?>
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

            <!-- Trending Posts List -->
            <div class="flex flex-col">
                <?php foreach ($trending as $t_post):
                    get_template_part('template-parts/cards/card', 'hero-trending', ['post' => $t_post]);
                endforeach; ?>
            </div>

        </div>

    </div>
</section>