<?php
/**
 * Flexible Content: Short Videos Section (YouTube Shorts)
 * Works on the Homepage and on Department pages: the same videos everywhere.
 *
 * A row of round thumbnails, the newest Shorts of the site's YouTube channel (inc/youtube-shorts.php). A click
 * plays the video in the vertical player that assets/js/shorts.js builds; without JavaScript the links open
 * YouTube. The videos do not follow the taxonomy filters (?geographic=...), so the section is left out while
 * one is active.
 *
 * Keep the row and the player out of <article> elements: src/input.css forces 16:9 on YouTube iframes there.
 */

if (has_active_section_filter()) {
    return;
}

$department = $args['department'] ?? null;
$section = $args['section'] ?? [];

if (!empty($section)) {
    $section_title = $section['section_title'] ?? '';
    $subtitle = $section['subtitle'] ?? '';
    $channel_id = $section['channel_id'] ?? '';
    $items_limit = $section['items_limit'] ?? 12;
} else {
    $section_title = get_sub_field('section_title');
    $subtitle = get_sub_field('subtitle');
    $channel_id = get_sub_field('channel_id');
    $items_limit = get_sub_field('items_limit');
}
$section_title = trim((string) $section_title) ?: __('Short videos', 'hello-elementor-child');
$channel_id = trim((string) $channel_id);
$items_limit = min(15, max(4, (int) ($items_limit ?: 12))); // the feed has 15 videos at most

$shorts = get_youtube_shorts($channel_id, $items_limit);
if (!$shorts) {
    return;
}

// One script and one player for the page, however many rows it has.
if (!wp_script_is('hello-elementor-child-shorts')) {
    $script_path = get_stylesheet_directory() . '/assets/js/shorts.js';
    wp_enqueue_script(
        'hello-elementor-child-shorts',
        get_stylesheet_directory_uri() . '/assets/js/shorts.js',
        [],
        file_exists($script_path) ? filemtime($script_path) : null,
        true
    );
    wp_localize_script('hello-elementor-child-shorts', 'helloShorts', [
        'dialog' => __('Short videos', 'hello-elementor-child'),
        'close' => __('Close', 'hello-elementor-child'),
        'previous' => __('Previous', 'hello-elementor-child'),
        'next' => __('Next', 'hello-elementor-child'),
        'watch' => __('Watch on YouTube', 'hello-elementor-child'),
        /* translators: 1: position of the video that is playing, 2: number of videos */
        'counter' => __('%1$d of %2$d', 'hello-elementor-child'),
    ]);
}
?>

<section class="w-full px-margin-mobile lg:px-margin py-space-xl" data-shorts>
    <?php
    render_shared_section_header([
        'title' => $section_title,
        'subtitle' => trim((string) $subtitle),
        'badge' => __('Shorts', 'hello-elementor-child'),
        'more_url' => youtube_shorts_channel_url($channel_id),
        'more_label' => __('More on YouTube', 'hello-elementor-child'),
        'external' => true,
        'department' => $department,
    ]);
    ?>

    <div class="relative">
        <?php // Arrows: mouse devices (touch swipes), only while the row overflows, level with the thumbnails; RTL: previous on the right. ?>
        <button type="button" data-shorts-scroll="prev" hidden
            class="absolute -start-4 top-11 lg:top-13 -translate-y-1/2 z-10 hidden pointer-fine:flex items-center justify-center w-9 h-9 rounded-full bg-white border border-outline-variant shadow-md text-on-background hover:text-primary cursor-pointer transition-opacity disabled:opacity-0 disabled:pointer-events-none"
            aria-label="<?php esc_attr_e('Previous', 'hello-elementor-child'); ?>">
            <i class="fas fa-angle-right" aria-hidden="true"></i>
        </button>

        <?php // p-1: room for the focus ring, which the scroll box would cut off. ?>
        <ul role="list" aria-label="<?php echo esc_attr($section_title); ?>" data-shorts-strip
            class="flex gap-space-md overflow-x-auto snap-x pointer-fine:snap-none p-1 scroll-px-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <?php foreach ($shorts as $short): ?>
                <li class="shrink-0 snap-start">
                    <a href="<?php echo esc_url('https://www.youtube.com/shorts/' . $short['id']); ?>" target="_blank" rel="noopener"
                        data-short="<?php echo esc_attr($short['id']); ?>" data-title="<?php echo esc_attr($short['title']); ?>"
                        aria-label="<?php echo esc_attr($short['title']); ?>"
                        class="group flex flex-col items-center gap-2 w-24 lg:w-28 rounded-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                        <?php // The "stories" look: a ring in the accent color, a white gap, the picture. ?>
                        <span class="relative block w-20 h-20 lg:w-24 lg:h-24 rounded-full border-[3px] border-primary bg-white p-[3px] transition-transform duration-300 group-hover:scale-105 group-focus-visible:scale-105">
                            <img src="<?php echo esc_url(youtube_short_thumbnail_url($short['id'])); ?>" alt="" width="96" height="96"
                                loading="lazy" decoding="async" class="w-full h-full rounded-full object-cover bg-surface-container">
                            <span class="absolute -bottom-0.5 -end-0.5 flex items-center justify-center w-6 h-6 lg:w-7 lg:h-7 rounded-full border-2 border-white bg-primary text-on-primary text-[9px] lg:text-[10px]" aria-hidden="true">
                                <i class="fas fa-play"></i>
                            </span>
                        </span>
                        <span class="w-full text-center text-[13px] leading-snug font-bold text-on-background line-clamp-2 transition-colors group-hover:text-primary"><?php echo esc_html($short['title']); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <button type="button" data-shorts-scroll="next" hidden
            class="absolute -end-4 top-11 lg:top-13 -translate-y-1/2 z-10 hidden pointer-fine:flex items-center justify-center w-9 h-9 rounded-full bg-white border border-outline-variant shadow-md text-on-background hover:text-primary cursor-pointer transition-opacity disabled:opacity-0 disabled:pointer-events-none"
            aria-label="<?php esc_attr_e('Next', 'hello-elementor-child'); ?>">
            <i class="fas fa-angle-left" aria-hidden="true"></i>
        </button>
    </div>
</section>
