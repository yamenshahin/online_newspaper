<?php
/**
 * Short videos section (template-parts/sections/shorts_section.php): the channel's Shorts feed.
 *
 * No API key: YouTube keeps a Shorts-only playlist for every channel (the channel ID with "UUSH" in place of
 * "UC") and serves its newest 15 videos as a public Atom feed.
 *
 * Stale-while-revalidate: the last good list is kept in one option per channel and counts as fresh for 30
 * minutes. After that the next page view asks YouTube again, and only that one: the others keep getting the
 * stored list. When YouTube does not answer the stored list stays, and the next try waits 5 minutes. A page
 * never waits longer than the request timeout, and a channel that never answered just has no videos.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** The site's channel: used when a section has no valid channel ID. */
const YOUTUBE_SHORTS_CHANNEL = 'UC6h0BhjcBqTt8_dUKFcVZvA';

/** How long a fetched list is served without asking YouTube again. */
const YOUTUBE_SHORTS_TTL = 30 * MINUTE_IN_SECONDS;

/** Pause after a refresh started: no second request meanwhile, and no new try after a failure. */
const YOUTUBE_SHORTS_RETRY = 5 * MINUTE_IN_SECONDS;

/** Seconds a page view may wait for the feed. */
const YOUTUBE_SHORTS_TIMEOUT = 4;

/** A channel ID as YouTube writes it ("UC" and 22 characters); anything else becomes the site's channel. */
function sanitize_youtube_channel_id(string $channel_id): string
{
    $channel_id = trim($channel_id);
    return preg_match('/^UC[A-Za-z0-9_-]{22}$/', $channel_id) ? $channel_id : YOUTUBE_SHORTS_CHANNEL;
}

/**
 * The newest Shorts of a channel, newest first.
 *
 * @return array<int, array{id: string, title: string, published: int, views: int}>
 */
function get_youtube_shorts(string $channel_id = '', int $limit = 12): array
{
    $channel_id = sanitize_youtube_channel_id($channel_id);
    $option = 'youtube_shorts_' . $channel_id;
    $lock = 'youtube_shorts_lock_' . $channel_id;

    $stored = get_option($option);
    $stored = is_array($stored) ? $stored : [];
    $items = sanitize_youtube_shorts($stored['items'] ?? []);
    $is_fresh = $items && time() - (int) ($stored['fetched'] ?? 0) < YOUTUBE_SHORTS_TTL;

    if (!$is_fresh && !get_transient($lock)) {
        // Set before the request, and left to run out: page views arriving meanwhile serve the stored list
        // instead of all asking YouTube, and a failed refresh is not tried again right away.
        set_transient($lock, 1, YOUTUBE_SHORTS_RETRY);
        $fetched = fetch_youtube_shorts($channel_id);
        if ($fetched) {
            $items = $fetched;
            update_option($option, ['fetched' => time(), 'items' => $items], false);
        }
    }

    return array_slice($items, 0, max(0, $limit));
}

/**
 * Asks YouTube for the channel's Shorts feed. An empty list for every kind of failure: no answer in time, an
 * error page, a feed without a usable video.
 *
 * @return array<int, array{id: string, title: string, published: int, views: int}>
 */
function fetch_youtube_shorts(string $channel_id): array
{
    $response = wp_remote_get(
        'https://www.youtube.com/feeds/videos.xml?playlist_id=UUSH' . substr(sanitize_youtube_channel_id($channel_id), 2),
        [
            'timeout' => YOUTUBE_SHORTS_TIMEOUT,
            'redirection' => 0, // the feed answers directly, and every redirect would get the whole timeout again
            'limit_response_size' => 512 * KB_IN_BYTES, // the feed is about 35 KB
            'user-agent' => 'WordPress/' . get_bloginfo('version'), // the default one adds the site's address
        ]
    );
    if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
        return [];
    }

    return parse_youtube_shorts_feed(wp_remote_retrieve_body($response));
}

/**
 * Videos of an Atom feed of YouTube, newest first. The video ID is in YouTube's namespace, the view count in
 * Media RSS's.
 *
 * @return array<int, array{id: string, title: string, published: int, views: int}>
 */
function parse_youtube_shorts_feed(string $xml): array
{
    if (trim($xml) === '' || !function_exists('simplexml_load_string')) {
        return [];
    }

    $previous = libxml_use_internal_errors(true); // an error page instead of a feed must not print warnings
    $feed = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$feed instanceof SimpleXMLElement) {
        return [];
    }

    $items = [];
    foreach ($feed->entry as $entry) {
        $entry->registerXPathNamespace('media', 'http://search.yahoo.com/mrss/');
        $views = $entry->xpath('media:group/media:community/media:statistics/@views');
        $items[] = [
            'id' => (string) $entry->children('http://www.youtube.com/xml/schemas/2015')->videoId,
            'title' => (string) $entry->title,
            'published' => (int) strtotime((string) $entry->published),
            'views' => $views ? (int) $views[0] : 0,
        ];
    }

    $items = sanitize_youtube_shorts($items);
    usort($items, static fn(array $a, array $b): int => $b['published'] <=> $a['published']);
    return $items;
}

/**
 * The usable videos of a list (a parsed feed, or the stored option) in the shape get_youtube_shorts() returns:
 * a video needs an 11-character ID and a title, and is listed once.
 *
 * @return array<int, array{id: string, title: string, published: int, views: int}>
 */
function sanitize_youtube_shorts(mixed $items): array
{
    $videos = [];
    foreach (is_array($items) ? $items : [] as $item) {
        if (!is_array($item) || !is_string($item['id'] ?? null) || !is_string($item['title'] ?? null)) {
            continue;
        }
        $id = trim($item['id']);
        $title = wp_strip_all_tags($item['title'], true);
        if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $id) || $title === '' || isset($videos[$id])) {
            continue;
        }
        $videos[$id] = [
            'id' => $id,
            'title' => $title,
            'published' => max(0, (int) ($item['published'] ?? 0)),
            'views' => max(0, (int) ($item['views'] ?? 0)),
        ];
    }
    return array_values($videos);
}

/** A frame of the video in its own 9:16 shape: for a Short, hqdefault.jpg is a 4:3 picture with black bars. */
function youtube_short_thumbnail_url(string $id): string
{
    return 'https://i.ytimg.com/vi/' . rawurlencode($id) . '/frame0.jpg';
}

/** The Shorts tab of a channel on YouTube. */
function youtube_shorts_channel_url(string $channel_id): string
{
    return 'https://www.youtube.com/channel/' . sanitize_youtube_channel_id($channel_id) . '/shorts';
}
