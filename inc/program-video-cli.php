<?php
/**
 * One-time move of the old "video or tweet link" field into the post body:
 *
 *   wp program-video embed [--dry-run] [--ids=<ids>] [--post_type=<type>] [--backup=<file>] [--print]
 *   wp program-video restore <file> [--force]
 *
 * The old site kept that link in the `_program_video` meta and showed it under the title; the theme doesn't read
 * that meta. The link is put at the top of post_content, so it shows and stays editable:
 * - a tweet: Twitter's embed code (blockquote with the tweet text + widgets.js, from publish.twitter.com/oembed);
 * - a YouTube video / Short: the bare URL on its own line, which WordPress turns into the player (oEmbed).
 * Other links (Drive, ...) are left alone; so are posts that already contain the tweet / video. A tweet whose
 * oEmbed request fails is skipped and listed. Writes, backup and restore are shared with `wp faq`
 * (inc/faq-cli.php): direct SQL, post_modified kept. Loaded only under WP-CLI.
 */

if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) {
    return;
}

final class Program_Video_CLI
{
    const META = '_program_video';
    const TWEET_RE = '~https?://(?:www\.|mobile\.)?(?:x|twitter)\.com/(\w{1,15})/status(?:es)?/(\d+)~i';
    const YOUTUBE_RE = '~https?://(?:(?:www|m)\.)?(?:youtube\.com/(?:watch\?(?:[^\s"]*&)?v=|shorts/|live/|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})~i';

    /**
     * Put the tweet / YouTube video from the old video-or-tweet field at the top of the post content.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Report what would change (the tweets are still fetched); write nothing.
     *
     * [--ids=<ids>]
     * : Comma-separated post IDs.
     *
     * [--post_type=<type>]
     * : Post type to process.
     * ---
     * default: program
     * ---
     *
     * [--backup=<file>]
     * : JSON-lines file for the original content of every changed post. Required unless --dry-run.
     *
     * [--print]
     * : Print what goes at the top of each post.
     *
     * @when after_wp_load
     */
    public function embed($args, $assoc)
    {
        global $wpdb;
        $dry = !empty($assoc['dry-run']);
        $backup = $assoc['backup'] ?? '';
        if (!$dry && '' === $backup) {
            WP_CLI::error('--backup=<file> is required (or use --dry-run).');
        }
        if (!$dry && file_exists($backup)) {
            WP_CLI::error("Backup file already exists: {$backup}");
        }

        $sql = "SELECT p.ID, p.post_content, m.meta_value AS link FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
            WHERE p.post_type = %s AND p.post_status NOT IN ('auto-draft', 'inherit') AND m.meta_value <> ''";
        if (!empty($assoc['ids'])) {
            $ids = array_filter(array_map('intval', explode(',', $assoc['ids'])));
            $sql .= ' AND p.ID IN (' . implode(',', $ids ?: [0]) . ')';
        }
        $rows = $wpdb->get_results($wpdb->prepare($sql . ' ORDER BY p.ID', self::META, $assoc['post_type'] ?? 'program'));

        $changes = [];
        $count = ['tweet' => 0, 'youtube' => 0];
        $present = 0;
        $other = 0;
        $failed = [];
        foreach ($rows as $row) {
            $id = (int) $row->ID;
            if (preg_match(self::TWEET_RE, $row->link, $m)) {
                $kind = 'tweet';
                $key = '/status/' . $m[2];
                $url = "https://x.com/{$m[1]}/status/{$m[2]}";
            } elseif (preg_match(self::YOUTUBE_RE, $row->link, $m)) {
                $kind = 'youtube';
                $key = $m[1];
                // Shorts keep their URL (vertical player); everything else becomes a watch URL without tracking params.
                $url = stripos($row->link, '/shorts/') !== false ? "https://www.youtube.com/shorts/{$m[1]}" : "https://www.youtube.com/watch?v={$m[1]}";
            } else {
                $other++;
                continue;
            }
            $count[$kind]++;
            if (str_contains($row->post_content, $key)) {
                $present++;
                continue;
            }
            if ('tweet' === $kind) {
                $top = self::tweet_code($url, $error);
                if ('' === $top) {
                    $failed[] = "#{$id} {$url}: {$error}";
                    continue;
                }
            } else {
                $top = $url; // own line: WordPress embeds it
            }
            if (!empty($assoc['print'])) {
                WP_CLI::line("===== #{$id}\n{$top}\n");
            }
            $changes[$id] = [$row->post_content, $top . "\n\n" . ltrim($row->post_content)];
        }

        WP_CLI::line(sprintf(
            'Tweets: %d, YouTube: %d, other links: %d. To change: %d, already in the content: %d, failed: %d',
            $count['tweet'], $count['youtube'], $other, count($changes), $present, count($failed)
        ));
        foreach ($failed as $line) {
            WP_CLI::warning("skipped {$line}");
        }
        if ($dry || !$changes) {
            WP_CLI::success($dry ? 'Dry run, nothing written.' : 'Nothing to change.');
            return;
        }
        $done = Faq_Convert_CLI::write_with_backup($changes, $backup);
        WP_CLI::success("Updated {$done} posts. Backup: {$backup}");
    }

    /**
     * Put back the content saved by `wp program-video embed --backup`.
     *
     * ## OPTIONS
     *
     * <file>
     * : The backup file.
     *
     * [--force]
     * : Also restore posts edited since (their edits are lost).
     *
     * @when after_wp_load
     */
    public function restore($args, $assoc)
    {
        (new Faq_Convert_CLI())->restore($args, $assoc);
    }

    /** Twitter's embed code for a tweet (blockquote + widgets.js), or '' with the reason in $error. */
    private static function tweet_code(string $url, ?string &$error): string
    {
        $endpoint = add_query_arg(['url' => rawurlencode($url), 'dnt' => 'true', 'align' => 'center'], 'https://publish.twitter.com/oembed');
        for ($try = 1; $try <= 3; $try++) { // connection timeouts happen; retry those
            $response = wp_remote_get($endpoint, ['timeout' => 20]);
            if (!is_wp_error($response)) {
                break;
            }
        }
        if (is_wp_error($response)) {
            $error = $response->get_error_message();
            return '';
        }
        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $html = is_array($data) ? trim((string) ($data['html'] ?? '')) : '';
        if (200 !== $code || !str_contains($html, 'twitter-tweet')) {
            $error = "HTTP {$code}" . (404 === $code ? ' (tweet deleted or private)' : '');
            return '';
        }
        return $html;
    }
}

WP_CLI::add_command('program-video', 'Program_Video_CLI');
