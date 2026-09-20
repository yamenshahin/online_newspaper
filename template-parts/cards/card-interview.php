<?php
/**
 * Template Part: Rich Interview Card (16:9)
 */
$interview_label = $args['interview_label'] ?? 'حوار';
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('rounded-3xl overflow-hidden est flex flex-col shadow-sm border border-surface-container-high group hover:shadow-xl transition-all'); ?>>

    <a href="<?php the_permalink(); ?>" class="relative w-full aspect-video overflow-hidden block">
        <?php if (has_post_thumbnail()): ?>
            <?php the_post_thumbnail('large', ['class' => 'w-full h-full object-cover transition-transform duration-500 group-hover:scale-105']); ?>
        <?php else: ?>
            <div class="w-full h-full flex items-center justify-center">
                <span class="text-on-surface-variant font-label-caps text-label-caps tracking-widest uppercase">
                    <?php esc_html_e('No Image', 'hello-elementor-child'); ?>
                </span>
            </div>
        <?php endif; ?>

        <!-- Interview Icon Overlay (Icon Only) -->
        <div
            class="absolute bottom-2 right-2 w-8 h-8 rounded-full bg-inverse-surface/80 backdrop-blur-md text-surface-bright flex items-center justify-center shadow-sm">
            <span class="material-symbols-outlined text-[18px]">graphic_eq</span>
        </div>
    </a>

    <div class="p-space-lg flex flex-col justify-between flex-grow gap-space-md">
        <div class="flex flex-col gap-space-xs">
            <!-- Dynamic Label Above Title -->
            <span class="font-label-caps text-label-caps text-secondary font-semibold">
                <?php echo esc_html($interview_label); ?>
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