<?php
/**
 * Card: Hero Trending (List Row)
 * Minimalist Pure White Theme
 */
$t_post = $args['post'] ?? null;
if (!$t_post instanceof WP_Post) {
    return;
}

// 1. CPT Meta Label (mapped)
$type_map = [
    'post' => 'آخر الأخبار',
    'interview' => 'حوارات ولقاءات',
    'program' => 'آخر الأخبار بالفيديو',
    'infographic' => 'إنفوجرافيك',
];

$post_type_obj = get_post_type_object($t_post->post_type);
$t_meta = $type_map[$t_post->post_type]
    ?? ($post_type_obj ? $post_type_obj->labels->singular_name : '');

$t_link = get_permalink($t_post->ID);
$is_video = in_array($t_post->post_type, ['interview', 'program'], true);

$helper_icon_path = get_stylesheet_directory() . '/assets/images/helper-icon.svg';
?>
<div
    class="flex items-center justify-between gap-4 py-5 group bg-white hover:bg-gray-50/50 transition-colors -mx-4 px-4 rounded-xl">

    <!-- Trending Image Thumbnail (Right in RTL) -->
    <a href="<?php echo esc_url($t_link); ?>"
        class="shrink-0 w-32 md:w-40 aspect-video rounded-xl overflow-hidden relative bg-gray-50 border border-gray-100 shadow-sm">
        <?php if (has_post_thumbnail($t_post->ID)): ?>
            <?php echo get_the_post_thumbnail($t_post->ID, 'medium', [
                'class' => 'w-full h-full object-cover transition-transform duration-500 group-hover:scale-105',
            ]); ?>
        <?php endif; ?>

        <?php if ($is_video): ?>
            <div
                class="absolute inset-0 flex items-center justify-center bg-black/5 group-hover:bg-transparent transition-colors">
                <div
                    class="w-8 h-8 rounded-full bg-white/95 flex items-center justify-center shadow-sm border border-gray-100">
                    <span class="material-symbols-outlined text-primary text-[18px]">play_arrow</span>
                </div>
            </div>
        <?php endif; ?>
    </a>

    <!-- Trending Text (Left in RTL) -->
    <div class="flex-1 min-w-0 text-start">
        <div class="flex items-center gap-1.5 mb-2 text-primary">
            <div
                class="w-3.5 h-3.5 flex items-center justify-center [&>svg]:w-full [&>svg]:h-full [&>svg]:fill-current">
                <?php
                if (file_exists($helper_icon_path)) {
                    echo file_get_contents($helper_icon_path);
                }
                ?>
            </div>
            <span class="font-label-caps text-xs font-bold mt-0.5">
                <?php echo esc_html($t_meta); ?>
            </span>
        </div>

        <a href="<?php echo esc_url($t_link); ?>" class="block">
            <h3
                class="text-lg md:text-xl font-bold text-gray-900 group-hover:text-primary transition-colors leading-snug line-clamp-2">
                <?php echo esc_html(get_the_title($t_post->ID)); ?>
            </h3>
        </a>
    </div>
</div>