<?php
/**
 * Compact reel card
 */
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('rounded-2xl overflow-hidden border border-surface-container-high group hover:shadow-lg transition-all'); ?>>
    <a href="<?php the_permalink(); ?>" class="relative block aspect-[9/16] overflow-hidden bg-surface-container">
        <?php if (has_post_thumbnail()): ?>
            <?php the_post_thumbnail('large', ['class' => 'w-full h-full object-cover group-hover:scale-105 transition-transform duration-500']); ?>
        <?php endif; ?>
        <div
            class="absolute bottom-2 right-2 w-8 h-8 rounded-full bg-inverse-surface/80 text-surface-bright flex items-center justify-center">
            <span class="material-symbols-outlined text-[18px]">movie</span>
        </div>
    </a>
    <div class="p-3">
        <h3
            class="font-headline-sm text-sm text-on-background leading-snug group-hover:text-primary transition-colors line-clamp-2">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h3>
    </div>
</article>