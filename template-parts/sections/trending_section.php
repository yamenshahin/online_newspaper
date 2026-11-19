<?php
/**
 * Flexible Content: Most read + most watched (site-wide, inc/shared-sections.php)
 * Works on Department pages and on the Homepage, with the same content on both.
 *
 * A dark band across the page: the most read articles, numbered, beside the most watched videos. Both lists rank
 * the items published in the last `period_days` days by views (get_most_viewed_ids(), cached for a few minutes).
 * The band colour reaches the edges of the window although <main> is boxed: a box-shadow carries it, clipped to
 * the band's own height. Accent text is a light tint of the department colour, so it reads on the dark surface.
 * Hidden while the page is filtered by a taxonomy.
 */

if (!function_exists('get_most_viewed_ids') || has_active_section_filter()) {
    return;
}

$section = $args['section'] ?? [];

if (!empty($section)) {
    $read_title = $section['read_title'] ?? '';
    $watched_title = $section['watched_title'] ?? '';
    $period_days = $section['period_days'] ?? 0;
} else {
    $read_title = get_sub_field('read_title');
    $watched_title = get_sub_field('watched_title');
    $period_days = get_sub_field('period_days');
}

$period_days = max(1, (int) ($period_days ?: 7)); // rows saved without a value get the default
$read_title = trim((string) $read_title) ?: __('Most read', 'hello-elementor-child');
$watched_title = trim((string) $watched_title) ?: __('Most watched', 'hello-elementor-child');

$most_read = get_posts_in_order(get_most_viewed_ids(['post'], 6, $period_days));
// Videos come out less often than articles: a week alone would rarely fill the list.
$most_watched = get_posts_in_order(get_most_viewed_ids(['video', 'program', 'interview'], 3, max(30, $period_days)));

if (!$most_read && !$most_watched) {
    return;
}

// Two columns of three, read downwards (1-3, then 4-6); a short list stays in one column.
$read_list_classes = count($most_read) > 3
    ? 'grid grid-cols-1 gap-x-space-xl md:grid-flow-col md:grid-cols-2 md:grid-rows-3'
    : 'grid grid-cols-1';
?>

<section
    class="w-full px-margin-mobile lg:px-margin py-space-xl bg-inverse-surface text-inverse-on-surface shadow-[0_0_0_100vmax_var(--color-inverse-surface)] [clip-path:inset(0_-100vmax)] [--band-accent:color-mix(in_srgb,var(--color-primary)_45%,white)]">
    <div class="grid grid-cols-1 gap-space-xl lg:grid-cols-12">

        <?php if ($most_read): ?>
            <div class="<?php echo $most_watched ? 'lg:col-span-7' : 'lg:col-span-12'; ?>">
                <div class="mb-space-md flex items-center gap-space-sm">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/10 text-lg text-[color:var(--band-accent)]">
                        <i class="fas fa-arrow-trend-up" aria-hidden="true"></i>
                    </span>
                    <h2 class="font-headline-sm text-headline-sm text-white md:font-headline-lg md:text-headline-lg">
                        <?php echo esc_html($read_title); ?>
                    </h2>
                </div>

                <ol class="<?php echo esc_attr($read_list_classes); ?>">
                    <?php foreach ($most_read as $index => $story):
                        $label = get_story_label($story->ID);
                        ?>
                        <li class="group flex items-start gap-space-md border-b border-white/10 py-space-md">
                            <span class="w-10 shrink-0 text-center text-4xl font-bold leading-none text-[color:var(--band-accent)]" aria-hidden="true">
                                <?php echo esc_html(number_format_i18n($index + 1)); ?>
                            </span>
                            <div class="min-w-0 text-start">
                                <div class="mb-1 flex flex-wrap items-center gap-x-space-sm text-xs text-white/60">
                                    <?php if ($label !== ''): ?>
                                        <span class="font-bold"><?php echo esc_html($label); ?></span>
                                    <?php endif; ?>
                                    <time datetime="<?php echo esc_attr(get_the_date('c', $story)); ?>"><?php echo esc_html(get_story_time_ago($story->ID)); ?></time>
                                </div>
                                <h3 class="m-0 text-base font-bold leading-snug text-white md:text-lg">
                                    <a href="<?php echo esc_url(get_permalink($story)); ?>"
                                        class="line-clamp-2 transition-colors group-hover:text-[color:var(--band-accent)]"><?php echo esc_html(get_the_title($story)); ?></a>
                                </h3>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        <?php endif; ?>

        <?php if ($most_watched): ?>
            <div class="<?php echo $most_read ? 'lg:col-span-5' : 'lg:col-span-12'; ?>">
                <div class="mb-space-md flex items-center gap-space-sm">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/10 text-base text-[color:var(--band-accent)]">
                        <i class="fas fa-play" aria-hidden="true"></i>
                    </span>
                    <h2 class="font-headline-sm text-headline-sm text-white md:font-headline-lg md:text-headline-lg">
                        <?php echo esc_html($watched_title); ?>
                    </h2>
                </div>

                <div class="flex flex-col">
                    <?php foreach ($most_watched as $story):
                        $label = get_story_label($story->ID);
                        ?>
                        <article class="group flex items-center gap-space-md border-b border-white/10 py-space-md">
                            <a href="<?php echo esc_url(get_permalink($story)); ?>" tabindex="-1" aria-hidden="true"
                                class="relative block aspect-video w-32 shrink-0 overflow-hidden rounded-xl bg-white/10 sm:w-40">
                                <?php
                                if (has_post_thumbnail($story)) {
                                    echo get_the_post_thumbnail($story, 'medium', [
                                        'class' => 'absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-105',
                                        'alt' => '',
                                        'loading' => 'lazy',
                                    ]);
                                }
                                ?>
                                <span class="absolute inset-0 flex items-center justify-center bg-black/15">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-sm text-primary shadow-sm">
                                        <i class="fas fa-play" aria-hidden="true"></i>
                                    </span>
                                </span>
                            </a>
                            <div class="min-w-0 text-start">
                                <?php if ($label !== ''): ?>
                                    <span class="mb-1 block text-xs font-bold text-white/60"><?php echo esc_html($label); ?></span>
                                <?php endif; ?>
                                <h3 class="m-0 text-base font-bold leading-snug text-white">
                                    <a href="<?php echo esc_url(get_permalink($story)); ?>"
                                        class="line-clamp-2 transition-colors group-hover:text-[color:var(--band-accent)]"><?php echo esc_html(get_the_title($story)); ?></a>
                                </h3>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</section>
