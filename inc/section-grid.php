<?php
/**
 * Card grid of the posts / interviews / videos / podcasts sections (ACF sub field `grid_layout`).
 *
 * - featured_right (default): one large story + 2x2 small stories, large on the right (RTL start).
 * - featured_left: same, large story on the left.
 * - grid: plain 4-column grid of the section's own cards, `posts_limit` cards.
 *
 * The featured grid uses flat cards (template-parts/cards/card-grid-story.php). On desktop the large column is
 * 47.6% wide and its image 1000:696, small images 16:9, so both columns end at about the same height.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Number of stories a featured grid shows: 1 large + 4 small. */
const SECTION_GRID_FEATURED_COUNT = 5;

/**
 * Grid layout of a section row; anything unknown (rows saved before the field existed) is featured_right.
 *
 * @param array $section Flexible content row; empty inside a have_rows() loop.
 */
function get_section_grid_layout(array $section = []): string
{
    $layout = !empty($section) ? ($section['grid_layout'] ?? '') : get_sub_field('grid_layout');

    return in_array($layout, ['featured_right', 'featured_left', 'grid'], true) ? $layout : 'featured_right';
}

/** Number of posts the section queries for its layout. */
function get_section_grid_post_count(string $layout, $posts_limit): int
{
    return 'grid' === $layout ? absint($posts_limit) : SECTION_GRID_FEATURED_COUNT;
}

/** Classes of the grid container. */
function get_section_grid_classes(string $layout): string
{
    if ('grid' === $layout) {
        // Titles at the size of the small stories of the featured grid (16px / 22px, bold).
        return 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-gutter [&_h3]:text-base [&_h3]:leading-[22px] [&_h3]:font-bold';
    }

    // RTL: the first child (large story) sits on the right; row-reverse moves it to the left.
    return 'flex flex-col gap-space-lg lg:gap-5 ' . ('featured_left' === $layout ? 'lg:flex-row-reverse' : 'lg:flex-row');
}

/**
 * Renders the current post of the loop as a card.
 *
 * Plain grid: the section's own card. Featured grid: the first post as the large story, the others inside the
 * 2x2 column, which is opened after the first post and closed after the last one.
 *
 * @param string $card       Card template of the plain grid (post, interview, video, podcast).
 * @param array  $card_args  Args of that card.
 * @param int    $index      0-based position in the loop.
 * @param int    $post_count Number of posts in the loop.
 */
function render_section_grid_card(string $layout, string $card, array $card_args, int $index, int $post_count): void
{
    if ('grid' === $layout) {
        get_template_part('template-parts/cards/card', $card, $card_args);
        return;
    }

    if (0 === $index) {
        echo '<div class="w-full lg:w-1/2 xl:w-[47.6%] shrink-0">';
        get_template_part('template-parts/cards/card', 'grid-story', ['size' => 'large']);
        echo '</div>';

        if ($post_count > 1) {
            echo '<div class="flex-1 min-w-0 grid grid-cols-1 sm:grid-cols-2 gap-x-[19px] gap-y-[21px] content-start">';
        }
        return;
    }

    get_template_part('template-parts/cards/card', 'grid-story', ['size' => 'small']);

    if ($index === $post_count - 1) {
        echo '</div>';
    }
}
