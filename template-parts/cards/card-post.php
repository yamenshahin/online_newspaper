<?php
/**
 * Template Part: Minimalist Standard Post Card (News)
 */
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('rounded-3xl overflow-hidden est shadow-sm flex flex-col group border border-surface-container-high hover:shadow-xl transition-all'); ?>>

    <?php if (has_post_thumbnail()): ?>
        <div class="relative w-full aspect-[4/3] overflow-hidden bg-inverse-surface">
            <a href="<?php the_permalink(); ?>" class="block w-full h-full">
                <?php the_post_thumbnail('large', ['class' => 'w-full h-full object-cover transition-transform duration-500 group-hover:scale-105']); ?>
            </a>
        </div>
    <?php endif; ?>

    <div class="p-space-md flex flex-col justify-between flex-grow gap-space-sm">
        <div class="flex flex-col gap-1">
            <span class="font-label-caps text-label-caps text-primary font-semibold uppercase tracking-wider">
                <?php esc_html_e('أخبار • NEWS', 'hello-elementor-child'); ?>
            </span>

            <a href="<?php the_permalink(); ?>">
                <h3
                    class="font-title-editorial text-title-editorial text-on-background leading-snug group-hover:text-primary transition-colors">
                    <?php the_title(); ?>
                </h3>
            </a>

            <div class="text-on-surface-variant font-body-sm text-xs line-clamp-3 mt-1">
                <?php echo wp_trim_words(get_the_excerpt(), 20); ?>
            </div>
        </div>

        <div
            class="flex items-center justify-between text-on-surface-variant font-body-sm text-xs pt-space-xs border-t border-surface-container mt-auto">
            <span class=""><?php echo get_the_date(); ?></span>
            <a href="<?php the_permalink(); ?>"
                class="text-primary font-medium hover:text-primary-container transition-colors flex items-center gap-1 group-hover:translate-x-[-4px] duration-300">
                <?php esc_html_e('اقرأ المزيد', 'hello-elementor-child'); ?>
                <span class="material-symbols-outlined text-[14px]">arrow_back</span>
            </a>
        </div>
    </div>

</article>