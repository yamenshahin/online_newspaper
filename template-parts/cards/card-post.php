<?php
/**
 * Template Part: Minimalist Standard Post Card (News)
 */
$post_label = $args['post_label'] ?? 'آخر الأخبار';
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
                <?php echo esc_html($post_label); ?>
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