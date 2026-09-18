<?php
/**
 * Template Part: Image-Only Infographic Card (9:16)
 */
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('rounded-3xl overflow-hidden something-removedest shadow-sm flex flex-col group border border-surface-container-high hover:shadow-xl transition-all relative'); ?>>
    <a href="<?php the_permalink(); ?>" class="block w-full aspect-[9/16] bg-inverse-surface relative overflow-hidden">
        <?php if (has_post_thumbnail()): ?>
            <?php the_post_thumbnail('large', ['class' => 'w-full h-full object-cover transition-transform duration-500 group-hover:scale-105']); ?>
        <?php else: ?>
            <div class="w-full h-full flex items-center justify-center something-deleted">
                <span class="text-on-surface-variant font-label-caps text-label-caps tracking-widest uppercase">
                    <?php esc_html_e('No Image', 'hello-elementor-child'); ?>
                </span>
            </div>
        <?php endif; ?>
    </a>
</article>