<?php
/**
 * Flexible Content: From the archive (site-wide, inc/shared-sections.php)
 * Works on Department pages and on the Homepage, with the same content on both.
 *
 * A row of older stories worth another look: the items the editors flagged "From the archive" (field group
 * "Editorial Flags") first, then the most viewed of the archive, all older than `min_age_days` days. The row
 * changes once a day (get_evergreen_ids()). Flat cards with the item's department and its date.
 * Hidden while the page is filtered by a taxonomy.
 */

if (!function_exists('get_evergreen_ids') || has_active_section_filter()) {
    return;
}

$department = $args['department'] ?? null;
$section = $args['section'] ?? [];

if (!empty($section)) {
    $section_title = $section['section_title'] ?? '';
    $subtitle = $section['subtitle'] ?? '';
    $posts_limit = $section['posts_limit'] ?? 0;
    $min_age_days = $section['min_age_days'] ?? 0;
} else {
    $section_title = get_sub_field('section_title');
    $subtitle = get_sub_field('subtitle');
    $posts_limit = get_sub_field('posts_limit');
    $min_age_days = get_sub_field('min_age_days');
}

$posts_limit = min(8, max(2, (int) ($posts_limit ?: 4)));
$min_age_days = max(1, (int) ($min_age_days ?: 30));

$stories = get_posts_in_order(get_evergreen_ids($posts_limit, $min_age_days));
if (!$stories) {
    return;
}
?>

<section class="w-full px-margin-mobile lg:px-margin py-space-xl">
    <?php
    render_shared_section_header([
        'title' => trim((string) $section_title) ?: __("Stories you shouldn't miss", 'hello-elementor-child'),
        'subtitle' => trim((string) $subtitle) ?: __('From our archive: stories worth another look', 'hello-elementor-child'),
        'department' => $department,
    ]);
    ?>

    <div class="<?php echo esc_attr(get_section_grid_classes('grid', count($stories))); ?>">
        <?php foreach ($stories as $story):
            $label = get_story_label($story->ID);
            ?>
            <article class="group flex min-w-0 flex-col gap-2">
                <a href="<?php echo esc_url(get_permalink($story)); ?>" tabindex="-1" aria-hidden="true"
                    class="relative block aspect-video w-full overflow-hidden rounded-2xl bg-surface-container">
                    <?php
                    if (has_post_thumbnail($story)) {
                        echo get_the_post_thumbnail($story, 'medium_large', [
                            'class' => 'absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-105',
                            'alt' => '',
                            'loading' => 'lazy',
                        ]);
                    }
                    ?>
                    <?php if (is_video_story($story->ID)): ?>
                        <span class="absolute bottom-2 end-2 flex h-8 w-8 items-center justify-center rounded-full bg-inverse-surface/80 text-xs text-surface-bright">
                            <i class="fas fa-play" aria-hidden="true"></i>
                        </span>
                    <?php endif; ?>
                </a>

                <div class="flex flex-wrap items-center gap-x-space-md text-start">
                    <?php if ($label !== ''): ?>
                        <span class="text-[12px] font-bold text-primary"><?php echo esc_html($label); ?></span>
                    <?php endif; ?>
                    <time datetime="<?php echo esc_attr(get_the_date('c', $story)); ?>"
                        class="text-[11px] text-outline"><?php echo esc_html(get_story_time_ago($story->ID)); ?></time>
                </div>

                <h3 class="m-0 font-bold text-on-background transition-colors group-hover:text-primary">
                    <a href="<?php echo esc_url(get_permalink($story)); ?>" class="line-clamp-2"><?php echo esc_html(get_the_title($story)); ?></a>
                </h3>
            </article>
        <?php endforeach; ?>
    </div>
</section>
