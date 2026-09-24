<?php
/**
 * Template Part: Minimalist Standard Post Card (News)
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

// Fallback to Dynamic CPT Name for single pages and other contexts
if (empty($card_label)) {
    $pt = get_post_type();
    if (function_exists('get_dynamic_cpt_labels')) {
        $cpt_data = get_dynamic_cpt_labels($pt);
        $card_label = !empty($cpt_data['title']) ? $cpt_data['title'] : $pt;
    } else {
        $card_label = __('آخر الأخبار', 'hello-elementor-child');
    }
}
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('rounded-3xl overflow-hidden est shadow-sm flex flex-col group border border-surface-container-high hover:shadow-xl transition-all'); ?>>

    <?php if (has_post_thumbnail()): ?>
        <div class="relative w-full aspect-[4/3] overflow-hidden bg-inverse-surface">
            <a href="<?php the_permalink(); ?>" class="block w-full h-full">
                <?php the_post_thumbnail('large', ['class' => 'w-full h-full object-cover transition-transform duration-500 group-hover:scale-105']); ?>
            </a>
        </div>
    <?php endif; ?>

    <div class="p-space-md flex flex-col gap-space-sm">
        <div class="flex flex-col gap-1">
            <span class="font-label-caps text-label-caps text-primary font-semibold uppercase tracking-wider">
                <?php echo esc_html($card_label); ?>
            </span>

            <a href="<?php the_permalink(); ?>">
                <h3
                    class="font-title-editorial text-title-editorial text-on-background leading-snug group-hover:text-primary transition-colors">
                    <?php the_title(); ?>
                </h3>
            </a>
        </div>
    </div>

</article>