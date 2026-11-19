<?php
/**
 * Flexible Content: Editors' picks + opinion (site-wide, inc/shared-sections.php)
 * Works on Department pages and on the Homepage, with the same content on both.
 *
 * Two lists the editors curate from the post edit screen (field group "Editorial Flags"): the newest items
 * flagged `editor_pick` in a bordered list, and the newest items flagged `is_opinion` beside the round photo of
 * their writer (the post's first Editor term; its `photo` field, or the first letter of the name).
 * A column without items is left out and the other takes the row; nothing is printed when both are empty.
 * Hidden while the page is filtered by a taxonomy.
 */

if (!function_exists('get_flagged_posts') || has_active_section_filter()) {
    return;
}

$department = $args['department'] ?? null;
$section = $args['section'] ?? [];

if (!empty($section)) {
    $picks_title = $section['picks_title'] ?? '';
    $opinion_title = $section['opinion_title'] ?? '';
    $picks_limit = $section['picks_limit'] ?? 0;
    $opinion_limit = $section['opinion_limit'] ?? 0;
} else {
    $picks_title = get_sub_field('picks_title');
    $opinion_title = get_sub_field('opinion_title');
    $picks_limit = get_sub_field('picks_limit');
    $opinion_limit = get_sub_field('opinion_limit');
}

$picks_title = trim((string) $picks_title) ?: __("Editors' picks", 'hello-elementor-child');
$opinion_title = trim((string) $opinion_title) ?: __('Opinion', 'hello-elementor-child');
$picks_limit = min(10, max(1, (int) ($picks_limit ?: 5)));
$opinion_limit = min(8, max(1, (int) ($opinion_limit ?: 4)));

$picks = get_flagged_posts('editor_pick', $picks_limit);
$opinions = get_flagged_posts('is_opinion', $opinion_limit);

if (!$picks && !$opinions) {
    return;
}
?>

<section class="w-full px-margin-mobile lg:px-margin py-space-xl">
    <div class="grid grid-cols-1 gap-space-xl lg:grid-cols-12">

        <?php if ($picks): ?>
            <div class="<?php echo $opinions ? 'lg:col-span-7' : 'lg:col-span-12'; ?>">
                <div class="mb-space-lg flex items-center gap-space-xs">
                    <?php render_department_icon($department instanceof WP_Term ? $department : null); ?>
                    <h2 class="font-headline-lg text-headline-lg text-on-background">
                        <?php echo esc_html($picks_title); ?>
                    </h2>
                </div>

                <div class="divide-y divide-surface-container-high overflow-hidden rounded-3xl border border-surface-container-high bg-surface-container-lowest">
                    <?php foreach ($picks as $story):
                        $label = get_story_label($story->ID);
                        ?>
                        <article class="group flex items-center gap-space-md p-space-md">
                            <a href="<?php echo esc_url(get_permalink($story)); ?>" tabindex="-1" aria-hidden="true"
                                class="relative block aspect-video w-28 shrink-0 overflow-hidden rounded-xl bg-surface-container sm:w-44">
                                <?php
                                if (has_post_thumbnail($story)) {
                                    echo get_the_post_thumbnail($story, 'medium', [
                                        'class' => 'absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-105',
                                        'alt' => '',
                                        'loading' => 'lazy',
                                    ]);
                                }
                                ?>
                                <?php if (is_video_story($story->ID)): ?>
                                    <span class="absolute bottom-1.5 end-1.5 flex h-7 w-7 items-center justify-center rounded-full bg-inverse-surface/80 text-xs text-surface-bright">
                                        <i class="fas fa-play" aria-hidden="true"></i>
                                    </span>
                                <?php endif; ?>
                            </a>
                            <div class="min-w-0 text-start">
                                <div class="mb-1 flex flex-wrap items-center gap-x-space-md">
                                    <?php if ($label !== ''): ?>
                                        <span class="text-[12px] font-bold text-primary"><?php echo esc_html($label); ?></span>
                                    <?php endif; ?>
                                    <time datetime="<?php echo esc_attr(get_the_date('c', $story)); ?>"
                                        class="text-[11px] text-outline"><?php echo esc_html(get_story_time_ago($story->ID)); ?></time>
                                </div>
                                <h3 class="m-0 text-base font-bold leading-snug text-on-background transition-colors group-hover:text-primary md:text-lg">
                                    <a href="<?php echo esc_url(get_permalink($story)); ?>" class="line-clamp-3 sm:line-clamp-2"><?php echo esc_html(get_the_title($story)); ?></a>
                                </h3>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($opinions): ?>
            <div class="<?php echo $picks ? 'lg:col-span-5' : 'lg:col-span-12'; ?>">
                <div class="mb-space-lg flex items-center gap-space-xs">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm text-primary md:h-10 md:w-10 md:text-base">
                        <i class="fas fa-pen-nib" aria-hidden="true"></i>
                    </span>
                    <h2 class="font-headline-lg text-headline-lg text-on-background">
                        <?php echo esc_html($opinion_title); ?>
                    </h2>
                </div>

                <div class="flex flex-col divide-y divide-surface-container-high rounded-3xl bg-surface-container-low px-space-lg">
                    <?php foreach ($opinions as $story):
                        $writer = get_story_writer($story->ID);
                        ?>
                        <article class="group flex items-center gap-space-md py-space-lg">
                            <?php render_writer_avatar($writer); ?>
                            <div class="min-w-0 text-start">
                                <h3 class="m-0 text-base font-bold leading-snug text-on-background transition-colors group-hover:text-primary md:text-lg">
                                    <a href="<?php echo esc_url(get_permalink($story)); ?>" class="line-clamp-3"><?php echo esc_html(get_the_title($story)); ?></a>
                                </h3>
                                <span class="mt-1 block text-sm font-semibold text-primary"><?php echo esc_html($writer['name']); ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</section>
