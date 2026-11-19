<?php
/**
 * Site-wide Layout Engine sections: short videos, most read + most watched, editors' picks + opinion and the
 * archive row (template-parts/sections/shorts_section.php, trending_section.php, editors_opinion_section.php,
 * evergreen_section.php).
 *
 * They show the same content on the homepage and on every department: the page's department only sets the
 * header icon, and the taxonomy filters (?geographic=...) are not applied. So they are hidden while a filter
 * is active (has_active_section_filter()) and skipped on entity archives (get_term_archive_skip_layouts()).
 *
 * Here: the shared header, the lists (ranked by views, flagged by the editors) and the small pieces the
 * templates share. The Shorts feed lives in inc/youtube-shorts.php.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** How long a list ranked by views is kept: views change all the time, the ranking barely does. */
const RANKED_LIST_TTL = 15 * MINUTE_IN_SECONDS;

/** Periods (days) tried after the section's own one until a ranked list is full; 0 = no date limit. */
const RANKED_LIST_WINDOWS = [30, 90, 365, 0];

/** Post types that carry the editorial flags (acf-json/group_editorial_flags.json). */
function get_editorial_flag_post_types(): array
{
    return ['post', 'infographic', 'interview', 'program', 'video'];
}

/** Meta keys of the editorial flags: editors' picks, opinion pieces, archive row. */
const EDITORIAL_FLAGS = ['editor_pick', 'is_opinion', 'evergreen'];

/**
 * A flag switched on or off on a published item: the list it feeds is printed on the homepage and on every
 * department, and a page cache would keep the old list on most of those pages. Flags change rarely, so
 * purging everything is fine (as for entity terms, inc/taxonomy-helpers.php); a no-op without LiteSpeed Cache.
 *
 * Hooked before and after the write: a save rewrites every flag, changed or not (and a first save adds "0" for
 * the flags that are off), so the purge only follows a real change.
 */
function purge_pages_on_editorial_flag_change($meta_id, $post_id, $meta_key, $meta_value): void
{
    static $before = [];
    if (!in_array($meta_key, EDITORIAL_FLAGS, true)) {
        return;
    }
    $key = $post_id . ':' . $meta_key;
    if (current_action() === 'update_post_meta') {
        $before[$key] = (bool) get_post_meta((int) $post_id, $meta_key, true);
        return;
    }
    if ((bool) $meta_value !== ($before[$key] ?? false) && get_post_status((int) $post_id) === 'publish') {
        do_action('litespeed_purge_all');
    }
}
add_action('update_post_meta', 'purge_pages_on_editorial_flag_change', 10, 4);
add_action('updated_post_meta', 'purge_pages_on_editorial_flag_change', 10, 4);
add_action('added_post_meta', 'purge_pages_on_editorial_flag_change', 10, 4);

/** A flagged item published, unpublished or trashed: the same lists change, on the same pages. */
function purge_pages_on_flagged_post_status($new_status, $old_status, $post): void
{
    if ($new_status === $old_status || ($new_status !== 'publish' && $old_status !== 'publish') || !$post instanceof WP_Post) {
        return;
    }
    foreach (EDITORIAL_FLAGS as $flag) {
        if (get_post_meta($post->ID, $flag, true)) {
            do_action('litespeed_purge_all');
            return;
        }
    }
}
add_action('transition_post_status', 'purge_pages_on_flagged_post_status', 10, 3);

/** True while the page is filtered by a taxonomy (?geographic=..., or an entity archive, which sets the same key). */
function has_active_section_filter(): bool
{
    foreach (get_filterable_taxonomies() as $taxonomy) {
        if (!empty($_GET[$taxonomy])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return true;
        }
    }
    return false;
}

/**
 * Header of a site-wide section: the markup of the posts / videos sections (icon, h2, badge, subtitle, link).
 *
 * @param array $args {
 *   @type string       $title
 *   @type string       $subtitle
 *   @type string       $badge      Pill beside the title.
 *   @type string       $more_url   No link when empty.
 *   @type string       $more_label Default "More".
 *   @type bool         $external   The link leaves the site (new tab).
 *   @type WP_Term|null $department Department of the page, for the icon.
 * }
 */
function render_shared_section_header(array $args): void
{
    $args += ['title' => '', 'subtitle' => '', 'badge' => '', 'more_url' => '', 'more_label' => '', 'external' => false, 'department' => null];
    $more_icon_path = get_stylesheet_directory() . '/assets/images/more-icon.svg';
    ?>
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-space-md mb-space-lg">
        <div class="flex flex-col gap-space-xs text-start">
            <?php // flex-wrap: on a phone the badge drops under the title instead of squeezing it onto two lines ?>
            <div class="flex flex-wrap items-center gap-space-xs">
                <?php render_department_icon($args['department'] instanceof WP_Term ? $args['department'] : null); ?>
                <h2 class="font-headline-lg text-headline-lg text-on-background">
                    <?php echo esc_html($args['title']); ?>
                </h2>
                <?php if ($args['badge'] !== ''): ?>
                    <span
                        class="px-space-sm py-0.5 rounded-full bg-primary-container text-on-primary font-label-caps text-label-caps font-bold shrink-0 whitespace-nowrap"><?php echo esc_html($args['badge']); ?></span>
                <?php endif; ?>
            </div>
            <?php if ($args['subtitle'] !== ''): ?>
                <p class="font-body-sm text-body-sm text-on-surface-variant">
                    <?php echo esc_html($args['subtitle']); ?>
                </p>
            <?php endif; ?>
        </div>

        <?php if ($args['more_url'] !== ''): ?>
            <a href="<?php echo esc_url($args['more_url']); ?>" <?php echo $args['external'] ? 'target="_blank" rel="noopener"' : ''; ?>
                class="inline-flex items-center gap-1.5 self-start md:self-auto text-base md:text-lg font-medium text-primary hover:opacity-70 transition-opacity">
                <?php if (file_exists($more_icon_path)): ?>
                    <span class="inline-flex w-5 h-5 shrink-0 [&>svg]:w-full [&>svg]:h-full" aria-hidden="true">
                        <?php echo file_get_contents($more_icon_path); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents ?>
                    </span>
                <?php endif; ?>
                <span><?php echo esc_html($args['more_label'] !== '' ? $args['more_label'] : __('More', 'hello-elementor-child')); ?></span>
            </a>
        <?php endif; ?>
    </div>
    <?php
}

// ---------------------------------------------------------------------------
// Lists
// ---------------------------------------------------------------------------

/**
 * Published items of $post_types ranked by views (most viewed first, then newest), optionally published after
 * and / or before a moment (site-local "Y-m-d H:i:s").
 *
 * Two plain reads ranked in PHP: the counters (`post_views_count`, functions.php) and the candidate posts.
 * Joining and sorting them in SQL takes seconds once the date range is wide (about 20,000 posts).
 * Items nobody opened have no counter and are never listed.
 *
 * @param string[] $post_types
 * @param int[]    $exclude
 * @return int[]
 */
function query_most_viewed_ids(array $post_types, int $limit, string $after = '', string $before = '', array $exclude = []): array
{
    global $wpdb;
    static $views = null;
    if ($views === null) {
        $views = [];
        foreach ($wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'post_views_count'", ARRAY_N) as [$id, $count]) {
            if ((int) $count > 0) {
                $views[(int) $id] = (int) $count;
            }
        }
    }
    if (!$views || !$post_types) {
        return [];
    }

    $where = "post_status = 'publish' AND post_type IN (" . implode(',', array_fill(0, count($post_types), '%s')) . ')';
    $params = array_values($post_types);
    if ($after !== '') {
        $where .= ' AND post_date >= %s';
        $params[] = $after;
    }
    if ($before !== '') {
        $where .= ' AND post_date < %s';
        $params[] = $before;
    }
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are built above.
    $rows = $wpdb->get_results($wpdb->prepare("SELECT ID, post_date FROM {$wpdb->posts} WHERE {$where}", $params), ARRAY_N);

    $exclude = array_flip(array_map('intval', $exclude));
    $ranked = [];
    foreach ($rows as [$id, $date]) {
        $id = (int) $id;
        if (isset($views[$id]) && !isset($exclude[$id])) {
            $ranked[$id] = [$views[$id], $date];
        }
    }
    uasort($ranked, static fn(array $a, array $b): int => ($b[0] <=> $a[0]) ?: strcmp($b[1], $a[1]));
    return array_slice(array_keys($ranked), 0, max(1, $limit));
}

/** Site-local "Y-m-d H:i:s" of $days days ago: what post_date is compared with. */
function get_days_ago_datetime(int $days): string
{
    return current_datetime()->modify('-' . max(0, $days) . ' days')->format('Y-m-d H:i:s');
}

/**
 * Most viewed published items of $post_types among those published in the last $days days, most viewed first.
 * A period with fewer than $limit viewed items widens (RANKED_LIST_WINDOWS) until the list is full, so the
 * section never comes up short on a quiet week.
 *
 * @param string[] $post_types
 * @return int[]
 */
function get_most_viewed_ids(array $post_types, int $limit, int $days): array
{
    $limit = max(1, $limit);
    $days = max(1, $days);
    $cache_key = 'most_viewed_' . md5(wp_json_encode([$post_types, $limit, $days]));
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    $ids = [];
    foreach (array_unique(array_merge([$days], RANKED_LIST_WINDOWS)) as $window) {
        if ($window !== 0 && $window < $days) {
            continue; // narrower than the section's own period
        }
        $ids = query_most_viewed_ids($post_types, $limit, $window ? get_days_ago_datetime($window) : '');
        if (count($ids) >= $limit) {
            break;
        }
    }

    set_transient($cache_key, $ids, RANKED_LIST_TTL);
    return $ids;
}

/**
 * Newest published items carrying an editorial flag (`editor_pick`, `is_opinion`), newest first. Not cached
 * here; a page cache in front of the site still serves the old list until that page is purged.
 *
 * @param string[] $post_types Default: every type that has the flags.
 * @return WP_Post[]
 */
function get_flagged_posts(string $flag, int $limit, array $post_types = []): array
{
    $query = new WP_Query([
        'post_type' => $post_types ?: get_editorial_flag_post_types(),
        'post_status' => 'publish',
        'posts_per_page' => max(1, $limit),
        'meta_query' => [['key' => $flag, 'value' => '1']],
        'no_found_rows' => true,
        'ignore_sticky_posts' => true,
    ]);
    update_post_thumbnail_cache($query);
    return $query->posts;
}

/**
 * Items of the archive row: published more than $min_age_days days ago, the ones the editors flagged
 * (`evergreen`, any flagged type) first, then the most viewed reports and explainers of the archive. Each list
 * is shuffled with the date (Riyadh time) as the seed, so the row changes every day and stays the same all day
 * (cached pages and visitors agree).
 *
 * @return int[]
 */
function get_evergreen_ids(int $limit, int $min_age_days): array
{
    $limit = max(1, $limit);
    $min_age_days = max(1, $min_age_days);
    $day = wp_date('Ymd', null, new DateTimeZone('Asia/Riyadh'));
    $cache_key = 'evergreen_' . md5(wp_json_encode([$limit, $min_age_days, $day]));
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    $before = get_days_ago_datetime($min_age_days);
    $shuffle = static function (array $ids) use ($day): array {
        $ids = array_map('intval', $ids);
        usort($ids, static fn(int $a, int $b): int => strcmp(md5($day . $a), md5($day . $b)));
        return $ids;
    };

    // Every flagged item takes its turn, however many there are.
    $flagged = $shuffle((new WP_Query([
        'post_type' => get_editorial_flag_post_types(),
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => [['key' => 'evergreen', 'value' => '1']],
        'date_query' => [['before' => $before]],
        'no_found_rows' => true,
        'ignore_sticky_posts' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ]))->posts);

    $ids = array_slice($flagged, 0, $limit);
    if (count($ids) < $limit) {
        // videos have the most watched list
        $viewed = query_most_viewed_ids(['post', 'infographic', 'interview', 'program'], max(12, $limit * 3), '', $before, $flagged);
        $ids = array_merge($ids, array_slice($shuffle($viewed), 0, $limit - count($ids)));
    }

    set_transient($cache_key, $ids, HOUR_IN_SECONDS); // an hour: a newly flagged item joins the row soon
    return $ids;
}

/**
 * Published posts for a list of ids, in that order (one query, thumbnails primed).
 *
 * @param int[] $ids
 * @return WP_Post[]
 */
function get_posts_in_order(array $ids): array
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) {
        return [];
    }
    $query = new WP_Query([
        'post_type' => get_content_post_types(), // not 'any': that leaves out types excluded from search
        'post_status' => 'publish',
        'post__in' => $ids,
        'orderby' => 'post__in',
        'posts_per_page' => count($ids),
        'no_found_rows' => true,
        'ignore_sticky_posts' => true,
    ]);
    update_post_thumbnail_cache($query);
    return $query->posts;
}

// ---------------------------------------------------------------------------
// Pieces of a story
// ---------------------------------------------------------------------------

/**
 * Label of an item in a site-wide list: its department (the cards' own label follows the page, and on a
 * department page they would print the editor instead), or its content type when it has no department.
 */
function get_story_label(int $post_id): string
{
    $departments = get_the_terms($post_id, 'department');
    if (!empty($departments) && !is_wp_error($departments)) {
        return $departments[0]->name;
    }
    return (string) (get_dynamic_cpt_labels((string) get_post_type($post_id))['title'] ?? '');
}

/** "منذ 8 ساعات". */
function get_story_time_ago(int $post_id): string
{
    /* translators: %s: human time difference, e.g. "8 hours" */
    return sprintf(__('%s ago', 'hello-elementor-child'), human_time_diff((int) get_post_time('U', true, $post_id), time()));
}

/** Types whose thumbnail gets a play mark in the lists. */
function is_video_story(int $post_id): bool
{
    return in_array(get_post_type($post_id), ['video', 'program', 'interview'], true);
}

/**
 * Writer of an item: its first `post_editor` term and that term's photo (ACF `photo`, acf-json/group_post_editor.json).
 *
 * @return array{name: string, photo_id: int}
 */
function get_story_writer(int $post_id): array
{
    $editors = get_the_terms($post_id, 'post_editor');
    if (empty($editors) || is_wp_error($editors)) {
        return ['name' => __('Tafaol Saudi', 'hello-elementor-child'), 'photo_id' => 0];
    }
    $photo = function_exists('get_field') ? get_field('photo', $editors[0], false) : 0;
    return [
        'name' => $editors[0]->name,
        'photo_id' => (int) (is_array($photo) ? ($photo['ID'] ?? 0) : $photo),
    ];
}

/** Round portrait of a writer; without a photo, the first letter of the name on a tint of the accent color. */
function render_writer_avatar(array $writer, string $size_class = 'w-16 h-16'): void
{
    $name = (string) ($writer['name'] ?? '');
    $photo = !empty($writer['photo_id']) ? wp_get_attachment_image((int) $writer['photo_id'], 'thumbnail', false, [
        'class' => 'h-full w-full object-cover',
        'alt' => '', // the name is printed beside the photo
        'loading' => 'lazy',
    ]) : '';

    echo '<span class="' . esc_attr($size_class) . ' flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary/10 text-2xl font-bold text-primary ring-2 ring-primary/20 ring-offset-2 ring-offset-white">';
    if ($photo !== '') {
        echo $photo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by wp_get_attachment_image()
    } else {
        echo '<span aria-hidden="true">' . esc_html(mb_substr(trim($name), 0, 1)) . '</span>';
    }
    echo '</span>';
}
