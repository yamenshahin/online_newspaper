<?php
/**
 * Flexible Content: Hero Lead Section (Home Page)
 * Editorial Split Layout: Large Image on the Right, Text & Trending on the Left
 */

$section = $args['section'] ?? [];

// 1. Read and Normalize Data
$featured = $section['hero_featured'] ?? null;
$trending = $section['hero_trending'] ?? [];

if ($featured && !is_array($featured)) {
    $featured = [$featured];
}
$featured_post = $featured[0] ?? null;

if (!is_array($trending)) {
    $trending = $trending ? [$trending] : [];
}

// 2. Enforce Curation
if (!$featured_post) {
    return;
}

// Custom Arabic Post Type Mapping
$type_map = [
    'post' => 'أخبار',
    'interview' => 'مقابلات خاصة',
    'program' => 'برامج',
    'infographic' => 'انفوجرافيك'
];

// Helper to get category/department name dynamically
function get_hero_meta_label($post_id, $post_type)
{
    global $type_map;
    $dept = get_the_terms($post_id, 'department');
    if (!empty($dept) && !is_wp_error($dept)) {
        return $dept[0]->name;
    }
    $post_type_obj = get_post_type_object($post_type);
    return $type_map[$post_type] ?? ($post_type_obj ? $post_type_obj->labels->singular_name : 'خبر');
}

$f_meta = get_hero_meta_label($featured_post->ID, $featured_post->post_type);
$f_link = get_permalink($featured_post->ID);

// 3. Custom Split-Diamond Brand Icon (Matches your screenshot exactly)
$brand_icon_svg = '<svg class="w-full h-full fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M10.5 3L1.5 12L10.5 21V3ZM13.5 3V21L22.5 12L13.5 3Z"/></svg>';
?>

<section class="w-full px-margin-mobile lg:px-margin py-space-xl bg-background">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 md:gap-12 items-stretch">

        <!-- 1. FEATURED IMAGE COLUMN (First in DOM = Sits on the RIGHT in RTL) -->
        <div class="lg:col-span-7 min-h-[400px] lg:min-h-[600px] relative h-full">
            <a href="<?php echo esc_url($f_link); ?>"
                class="block w-full h-full relative group overflow-hidden rounded-2xl shadow-lg">
                <?php if (has_post_thumbnail($featured_post->ID)): ?>
                    <?php echo get_the_post_thumbnail($featured_post->ID, 'full', ['class' => 'absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-105']); ?>
                <?php else: ?>
                    <div class="absolute inset-0 bg-surface-container-high w-full h-full flex items-center justify-center">
                        <span class="text-on-surface-variant font-label-caps tracking-widest uppercase">No Image</span>
                    </div>
                <?php endif; ?>
            </a>
        </div>

        <!-- 2. TEXT & TRENDING COLUMN (Second in DOM = Sits on the LEFT in RTL) -->
        <div class="lg:col-span-5 flex flex-col justify-between">

            <!-- Featured Post Text Block (Top Left visually in RTL) -->
            <div class="mb-10 text-start">
                <div class="flex items-center gap-2 mb-4 text-primary">
                    <div class="w-4 h-4 flex items-center justify-center">
                        <?php echo $brand_icon_svg; ?>
                    </div>
                    <span class="font-label-caps text-sm uppercase tracking-widest font-bold mt-0.5">
                        <?php echo esc_html($f_meta); ?>
                    </span>
                </div>
                <a href="<?php echo esc_url($f_link); ?>" class="block group">
                    <h1
                        class="text-3xl md:text-4xl lg:text-5xl font-bold text-on-background leading-[1.3] tracking-tight group-hover:text-primary transition-colors">
                        <?php echo esc_html(get_the_title($featured_post->ID)); ?>
                    </h1>
                </a>
            </div>

            <!-- Trending Posts List (Bottom Left visually in RTL) -->
            <div class="flex flex-col">
                <?php foreach ($trending as $t_post):
                    $t_meta = get_hero_meta_label($t_post->ID, $t_post->post_type);
                    $t_link = get_permalink($t_post->ID);
                    $is_video = in_array($t_post->post_type, ['interview', 'program']);
                    ?>

                    <div
                        class="flex items-center justify-between gap-4 py-5 border-t border-surface-container-highest/20 group">

                        <!-- Trending Image Thumbnail (First in DOM = Sits on the Right in RTL) -->
                        <a href="<?php echo esc_url($t_link); ?>"
                            class="shrink-0 w-32 md:w-40 aspect-video rounded-xl overflow-hidden relative bg-surface-container shadow-sm">
                            <?php if (has_post_thumbnail($t_post->ID)): ?>
                                <?php echo get_the_post_thumbnail($t_post->ID, 'medium', ['class' => 'w-full h-full object-cover transition-transform duration-500 group-hover:scale-105']); ?>
                            <?php endif; ?>

                            <?php if ($is_video): ?>
                                <div
                                    class="absolute inset-0 flex items-center justify-center bg-inverse-surface/10 group-hover:bg-transparent transition-colors">
                                    <div
                                        class="w-8 h-8 rounded-full bg-surface-container-lowest/95 flex items-center justify-center shadow-md">
                                        <span class="material-symbols-outlined text-primary text-[18px]">play_arrow</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </a>

                        <!-- Trending Text (Second in DOM = Sits on the Left in RTL) -->
                        <div class="flex-1 min-w-0 text-start">
                            <div class="flex items-center gap-1.5 mb-2 text-primary">
                                <div class="w-3.5 h-3.5 flex items-center justify-center">
                                    <?php echo $brand_icon_svg; ?>
                                </div>
                                <span class="font-label-caps text-xs font-bold mt-0.5">
                                    <?php echo esc_html($t_meta); ?>
                                </span>
                            </div>
                            <a href="<?php echo esc_url($t_link); ?>" class="block">
                                <h3
                                    class="text-lg md:text-xl font-bold text-on-background group-hover:text-primary transition-colors leading-snug line-clamp-2">
                                    <?php echo esc_html(get_the_title($t_post->ID)); ?>
                                </h3>
                            </a>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>

        </div>

    </div>
</section>