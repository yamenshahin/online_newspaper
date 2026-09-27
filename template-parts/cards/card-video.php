<?php
/**
 * Template Part: Rich Video Card (16:9)
 */

$card_label = '';
if (is_tax('department')) {
    $editors = get_the_terms(get_the_ID(), 'post_editor');
    $card_label = (!empty($editors) && !is_wp_error($editors)) ? implode(' و ', wp_list_pluck($editors, 'name')) : __('تفاعل السعودية', 'hello-elementor-child');
} elseif (is_archive() || is_home() || is_front_page() || is_search()) {
    $departments = get_the_terms(get_the_ID(), 'department');
    if (!empty($departments) && !is_wp_error($departments)) {
        $card_label = implode(' و ', wp_list_pluck($departments, 'name'));
    }
}

if (empty($card_label)) {
    $pt = get_post_type();
    if (function_exists('get_dynamic_cpt_labels')) {
        $cpt_data = get_dynamic_cpt_labels($pt);
        $card_label = !empty($cpt_data['title']) ? $cpt_data['title'] : $pt;
    } else {
        $card_label = __('فيديو', 'hello-elementor-child');
    }
}
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('rounded-3xl overflow-hidden est flex flex-col shadow-sm border border-surface-container-high group hover:shadow-xl transition-all'); ?>>

    <a href="<?php the_permalink(); ?>" class="relative w-full aspect-[16/9] overflow-hidden bg-inverse-surface block">
        <?php if (has_post_thumbnail()): ?>
            <?php the_post_thumbnail('large', ['class' => 'w-full h-full object-cover transition-transform duration-500 group-hover:scale-105']); ?>
        <?php else: ?>
            <div class="w-full h-full flex items-center justify-center bg-surface-container">
                <span class="text-on-surface-variant font-label-caps text-label-caps tracking-widest uppercase">
                    <?php esc_html_e('No Image', 'hello-elementor-child'); ?>
                </span>
            </div>
        <?php endif; ?>

        <!-- Video Icon Overlay -->
        <div
            class="absolute bottom-2 right-2 w-8 h-8 rounded-full bg-inverse-surface/80 backdrop-blur-md text-surface-bright flex items-center justify-center shadow-sm">
            <span class="material-symbols-outlined text-[18px]">videocam</span>
        </div>
    </a>

    <div class="p-space-lg flex flex-col justify-between flex-grow gap-space-md">
        <div class="flex flex-col gap-space-xs">
            <span class="font-label-caps text-label-caps text-primary font-semibold">
                <?php echo esc_html($card_label); ?>
            </span>

            <a href="<?php the_permalink(); ?>">
                <h3
                    class="font-headline-sm text-headline-sm text-on-background leading-tight group-hover:text-secondary transition-colors mt-1">
                    <?php the_title(); ?>
                </h3>
            </a>
        </div>
    </div>

</article>