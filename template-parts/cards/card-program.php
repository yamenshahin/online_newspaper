<?php
/**
 * Template Part: Rich Program Card (16:9)
 */
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('rounded-3xl overflow-hidden bg-surface-container-lowest flex flex-col shadow-sm border border-surface-container-high group hover:shadow-xl transition-all'); ?>>

    <a href="<?php the_permalink(); ?>" class="relative w-full aspect-video overflow-hidden bg-inverse-surface block">
        <?php if (has_post_thumbnail()): ?>
            <?php the_post_thumbnail('large', ['class' => 'w-full h-full object-cover transition-transform duration-500 group-hover:scale-105']); ?>
        <?php else: ?>
            <div class="w-full h-full flex items-center justify-center bg-surface-container">
                <span class="text-on-surface-variant font-label-caps text-label-caps tracking-widest uppercase">
                    <?php esc_html_e('No Image', 'hello-elementor-child'); ?>
                </span>
            </div>
        <?php endif; ?>

        <div class="absolute inset-0 bg-inverse-surface/20 group-hover:bg-transparent transition-colors"></div>

        <!-- Placeholder for Episode Count -->
        <span
            class="absolute top-2 right-2 px-space-md py-1 rounded-full bg-inverse-surface/80 backdrop-blur-md text-surface-bright font-label-caps text-label-caps font-bold shadow-md">
            <?php esc_html_e('حلقات متجددة', 'hello-elementor-child'); ?>
        </span>

        <span
            class="absolute bottom-2 right-2 px-space-sm py-0.5 rounded-full bg-primary-container/90 backdrop-blur-md text-on-primary font-label-caps text-label-caps flex items-center gap-1">
            <span class="material-symbols-outlined text-[14px]">tv</span>
            <span class=""><?php esc_html_e('إنتاج مرئي', 'hello-elementor-child'); ?></span>
        </span>
    </a>

    <div class="p-space-lg flex flex-col justify-between flex-grow gap-space-md">
        <div class="flex flex-col gap-space-xs">
            <div class="flex items-center gap-space-xs">
                <span
                    class="px-space-md py-0.5 rounded-full bg-primary-container text-on-primary font-label-pill text-label-pill">إنتاج
                    أصلي</span>
                <span class="font-label-caps text-label-caps text-primary font-semibold">
                    <?php
                    $series = get_the_terms(get_the_ID(), 'program_series');
                    if (!empty($series) && !is_wp_error($series)) {
                        echo esc_html($series[0]->name);
                    } else {
                        echo esc_html__('برامجنا', 'hello-elementor-child');
                    }
                    ?>
                </span>
            </div>

            <a href="<?php the_permalink(); ?>">
                <h3
                    class="font-headline-sm text-headline-sm text-on-background leading-tight group-hover:text-primary transition-colors mt-1">
                    <?php the_title(); ?>
                </h3>
            </a>

            <div class="font-body-sm text-body-sm text-on-surface-variant line-clamp-2 mt-1">
                <?php echo wp_trim_words(get_the_excerpt(), 25); ?>
            </div>
        </div>

        <div
            class="flex flex-wrap items-center justify-between gap-space-md pt-space-xs border-t border-surface-container">
            <a href="<?php the_permalink(); ?>"
                class="px-space-lg py-space-sm rounded-full bg-primary-container hover:bg-primary text-on-primary font-label-pill text-label-pill flex items-center gap-space-xs transition-colors shadow-md inline-flex">
                <span class="material-symbols-outlined text-[20px]">play_arrow</span>
                <span class="">مشاهدة الحلقات</span>
            </a>

            <div class="flex items-center gap-space-xs text-on-surface-variant font-label-pill text-label-pill">
                <span class="material-symbols-outlined text-[16px]">hd</span>
                <span class="">4K Ultra HD</span>
            </div>
        </div>
    </div>

</article>