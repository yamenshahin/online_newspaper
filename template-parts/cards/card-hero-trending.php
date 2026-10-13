<?php
/**
 * Card: Hero Trending
 * Mobile / tablet: ONE column — image on top, title under
 * ≥1360px: row — thumb + text (side panel)
 */
$t_post = $args['post'] ?? null;
if (!$t_post instanceof WP_Post) {
    return;
}

$pt = $t_post->post_type;
$cpt_labels = get_dynamic_cpt_labels($pt);
$t_meta = $cpt_labels['title'];

$departments = get_the_terms($t_post->ID, 'department');
if (!empty($departments) && !is_wp_error($departments)) {
    $t_meta = $cpt_labels['title'] . ' ' . $departments[0]->name;
}

$t_link = get_permalink($t_post->ID);
$is_video = in_array($pt, ['interview', 'program', 'video'], true);
$helper_icon_path = get_stylesheet_directory() . '/assets/images/helper-icon.svg';
?>

<div
    class="flex flex-col w-full gap-3 py-3 group
        min-[1360px]:flex-row min-[1360px]:items-center min-[1360px]:gap-4 min-[1360px]:py-4 min-[1360px]:flex-1 min-[1360px]:min-h-0">

    <!-- IMAGE first = on top in a column -->
    <a href="<?php echo esc_url($t_link); ?>" class="block w-full aspect-video rounded-xl overflow-hidden relative bg-gray-50 border border-gray-100 shadow-sm
            min-[1360px]:w-40 min-[1360px]:shrink-0">
        <?php if (has_post_thumbnail($t_post->ID)): ?>
            <?php
            echo get_the_post_thumbnail(
                $t_post->ID,
                'medium',
                [
                    'class' => 'skip-lazy w-full h-full object-cover transition-transform duration-500 group-hover:scale-105',
                    'loading' => 'eager',
                    'fetchpriority' => 'high',
                    'decoding' => 'async',
                    'data-skip-lazy' => '1',
                ]
            );
            ?>
        <?php endif; ?>
        <?php if ($is_video): ?>
            <div class="absolute inset-0 flex items-center justify-center bg-black/5">
                <div
                    class="w-8 h-8 rounded-full bg-white/95 flex items-center justify-center shadow-sm border border-gray-100">
                    <span class="material-symbols-outlined text-primary text-[18px]">play_arrow</span>
                </div>
            </div>
        <?php endif; ?>
    </a>

    <!-- TITLE under image when flex-col -->
    <div class="w-full min-w-0 text-start">
        <div class="flex items-center gap-1.5 mb-1 text-primary">
            <div
                class="w-3.5 h-3.5 flex items-center justify-center [&>svg]:w-full [&>svg]:h-full [&>svg]:fill-current shrink-0">
                <?php
                if (file_exists($helper_icon_path)) {
                    echo file_get_contents($helper_icon_path); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
                ?>
            </div>
            <span class="font-label-caps text-xs font-bold line-clamp-1">
                <?php echo esc_html($t_meta); ?>
            </span>
        </div>
        <a href="<?php echo esc_url($t_link); ?>" class="block">
            <h3
                class="text-base md:text-lg min-[1360px]:text-xl font-bold text-gray-900 group-hover:text-primary transition-colors leading-snug line-clamp-2">
                <?php echo esc_html(get_the_title($t_post->ID)); ?>
            </h3>
        </a>
    </div>
</div>