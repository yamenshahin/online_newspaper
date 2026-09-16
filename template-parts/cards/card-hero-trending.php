<?php
/**
 * Card: Hero Trending (List Row)
 * Expects: $args['post'] (WP_Post object), $args['num'] (string)
 */

$t_post = $args['post'] ?? null;
$num = $args['num'] ?? '01';
if (!$t_post)
    return;

// 1. Custom Arabic Post Type Mapping
$type_map = [
    'post' => 'أخبار',
    'interview' => 'مقابلات',
    'program' => 'برامج',
    'infographic' => 'انفوجرافيك'
];
$post_type_obj = get_post_type_object($t_post->post_type);
$type_name = $type_map[$t_post->post_type] ?? ($post_type_obj ? $post_type_obj->labels->singular_name : '');

$time_diff = human_time_diff(get_post_time('U', false, $t_post->ID), current_time('timestamp'));
$permalink = get_permalink($t_post->ID);
?>

<a href="<?php echo esc_url($permalink); ?>"
    class="p-space-sm rounded-2xl hover:bg-surface-container-low transition-colors flex items-start gap-space-sm cursor-pointer group text-right w-full">

    <span
        class="font-headline-sm text-headline-sm text-primary font-bold opacity-60 group-hover:opacity-100 transition-opacity mt-1 shrink-0">
        <?php echo esc_html($num); ?>
    </span>

    <div class="flex flex-col flex-1 min-w-0 items-start">
        <span class="font-label-caps text-label-caps text-tertiary">
            <?php echo esc_html($type_name); ?>
        </span>
        <h4
            class="font-title-editorial text-title-editorial text-on-background group-hover:text-primary transition-colors line-clamp-2 text-right">
            <?php echo esc_html(get_the_title($t_post->ID)); ?>
        </h4>

        <div class="flex items-center gap-space-sm mt-1 text-on-surface-variant font-body-sm text-body-sm text-xs">
            <span class=""><?php echo sprintf(esc_html__('منذ %s', 'hello-elementor-child'), $time_diff); ?></span>
            <span class="">•</span>
            <span
                class="text-primary font-semibold"><?php esc_html_e('أحدث المستجدات', 'hello-elementor-child'); ?></span>
        </div>
    </div>
</a>