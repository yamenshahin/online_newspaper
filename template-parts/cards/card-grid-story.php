<?php
/**
 * Template Part: flat story card of the featured grid (1 + 4), any post type.
 *
 * Args: 'size' => 'large' | 'small'.
 * Images 16:9. Large: 30px title on desktop; small: 16px title. Titles clamp at 2 lines.
 */

$is_large = 'large' === ($args['size'] ?? 'small');
$post_id = get_the_ID();

$card_label = '';
if (is_tax('department')) {
    $editors = get_the_terms($post_id, 'post_editor');
    $card_label = (!empty($editors) && !is_wp_error($editors)) ? implode(_x(' and ', 'between names', 'hello-elementor-child'), wp_list_pluck($editors, 'name')) : __('Tafaol Saudi', 'hello-elementor-child');
} else {
    $departments = get_the_terms($post_id, 'department');
    if (!empty($departments) && !is_wp_error($departments)) {
        $card_label = implode(_x(' and ', 'between names', 'hello-elementor-child'), wp_list_pluck($departments, 'name'));
    }
}
if (empty($card_label) && function_exists('get_dynamic_cpt_labels')) {
    $cpt_data = get_dynamic_cpt_labels(get_post_type());
    $card_label = $cpt_data['title'] ?? '';
}

$type_icons = [
    'video' => 'videocam',
    'podcast' => 'podcasts',
    'interview' => 'graphic_eq',
];
$type_icon = $type_icons[get_post_type()] ?? '';

$time_ago = sprintf(
    /* translators: %s: human time difference, e.g. "8 hours" */
    __('%s ago', 'hello-elementor-child'),
    human_time_diff(get_post_time('U', true), time())
);
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('group flex flex-col gap-2 min-w-0'); ?>>

    <a href="<?php the_permalink(); ?>"
        class="relative block w-full overflow-hidden rounded-2xl bg-surface-container aspect-video">
        <?php if (has_post_thumbnail()): ?>
            <?php the_post_thumbnail($is_large ? 'large' : 'medium_large', ['class' => 'absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-105']); ?>
        <?php endif; ?>

        <?php if ($type_icon): ?>
            <span
                class="absolute bottom-2 right-2 w-8 h-8 rounded-full bg-inverse-surface/80 text-surface-bright flex items-center justify-center">
                <span class="material-symbols-outlined text-[18px]"><?php echo esc_html($type_icon); ?></span>
            </span>
        <?php endif; ?>
    </a>

    <div class="flex items-center gap-space-md text-start">
        <?php if ($card_label): ?>
            <span class="text-[12px] font-bold text-primary"><?php echo esc_html($card_label); ?></span>
        <?php endif; ?>
        <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"
            class="<?php echo $is_large ? 'text-[13px]' : 'text-[11px]'; ?> text-outline"><?php echo esc_html($time_ago); ?></time>
    </div>

    <h3 class="m-0 font-bold text-on-background line-clamp-2 group-hover:text-primary transition-colors <?php echo $is_large ? 'text-base lg:text-[30px] lg:leading-[39px]' : 'text-base leading-[22px]'; ?>">
        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
    </h3>

</article>
