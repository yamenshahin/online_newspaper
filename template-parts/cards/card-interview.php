<?php
/**
 * Template Part: Rich Interview Card (16:9)
 */
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('rounded-3xl overflow-hidden bg-surface-container-lowest flex flex-col shadow-sm border border-surface-container-high group hover:shadow-xl transition-all'); ?>>

    <a href="<?php the_permalink(); ?>" class="relative w-full aspect-video overflow-hidden bg-surface-container block">
        <?php if (has_post_thumbnail()): ?>
            <?php the_post_thumbnail('large', ['class' => 'w-full h-full object-cover transition-transform duration-500 group-hover:scale-105']); ?>
        <?php else: ?>
            <div class="w-full h-full flex items-center justify-center">
                <span class="text-on-surface-variant font-label-caps text-label-caps tracking-widest uppercase">
                    <?php esc_html_e('No Image', 'hello-elementor-child'); ?>
                </span>
            </div>
        <?php endif; ?>

        <div
            class="absolute bottom-2 right-2 px-space-sm py-0.5 rounded-full bg-inverse-surface/80 backdrop-blur-md text-surface-bright font-label-caps text-label-caps flex items-center gap-1">
            <span class="material-symbols-outlined text-[14px]">graphic_eq</span>
            <span class="">320 KBPS</span>
        </div>
    </a>

    <div class="p-space-lg flex flex-col justify-between flex-grow gap-space-md">
        <div class="flex flex-col gap-space-xs">
            <div class="flex flex-wrap items-center justify-between gap-space-xs">
                <div class="flex items-center gap-space-xs">
                    <span
                        class="px-space-md py-0.5 rounded-full bg-secondary-container text-on-secondary font-label-pill text-label-pill">حوار
                        خاص</span>
                    <!-- Note: Static placeholder for Episode number -->
                    <span
                        class="px-space-md py-0.5 rounded-full bg-surface-container-high text-on-surface-variant font-label-caps text-label-caps">متميز</span>
                </div>
                <span class="font-label-caps text-label-caps text-secondary font-semibold">
                    <?php
                    $speakers = get_the_terms(get_the_ID(), 'speaker_influencer');
                    if (!empty($speakers) && !is_wp_error($speakers)) {
                        echo esc_html($speakers[0]->name);
                    } else {
                        echo esc_html__('ضيف خاص', 'hello-elementor-child');
                    }
                    ?>
                </span>
            </div>

            <a href="<?php the_permalink(); ?>">
                <h3
                    class="font-headline-sm text-headline-sm text-on-background leading-tight group-hover:text-secondary transition-colors mt-1">
                    <?php the_title(); ?>
                </h3>
            </a>
        </div>

        <div
            class="flex flex-wrap items-center justify-between gap-space-md pt-space-xs border-t border-surface-container">
            <a href="<?php the_permalink(); ?>"
                class="px-space-lg py-space-sm rounded-full bg-secondary-container hover:bg-secondary text-on-secondary font-label-pill text-label-pill flex items-center gap-space-xs transition-colors shadow-md inline-flex">
                <span class="material-symbols-outlined text-[20px]">play_arrow</span>
                <span class="">مشاهدة اللقاء</span>
            </a>

            <div class="flex items-center gap-space-xs text-on-surface-variant font-label-pill text-label-pill">
                <span class="material-symbols-outlined text-[16px]">podcasts</span>
                <span class="">Apple Podcasts & Spotify</span>
            </div>
        </div>
    </div>

</article>