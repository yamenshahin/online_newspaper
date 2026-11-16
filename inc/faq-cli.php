<?php
/**
 * One-time conversion of the old theme's FAQ shortcodes in post content to plain HTML:
 *
 *   wp faq convert [--dry-run] [--ids=<ids>] [--backup=<file>] [--print]
 *   wp faq restore <file> [--force]
 *
 *   [jnews_faq …][faq_item question="Q"]<div>A</div>[/faq_item][/jnews_faq]
 *   → <details class="faq-item"><summary>Q</summary><div class="faq-answer">A</div></details>
 *
 * Styles: `.faq-item` in src/input.css; Classic Editor view in assets/css/editor-style.css.
 * The question is read up to the `"` that closes the tag, so questions with inner quotes or links (empty on the
 * old site, WordPress can't parse those attributes) come through. Posts are written with direct SQL: no save hooks,
 * and post_modified stays (pages would otherwise all show "Last updated" today). Loaded only under WP-CLI.
 */

if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) {
    return;
}

final class Faq_Convert_CLI
{
    /** Inline elements, <p> and headings a FAQ item is moved out of: <details> can't sit inside them. */
    const HOIST = 'p|h[1-6]|strong|b|em|i|u|span|a|font';
    const TOKEN = "\x01FAQ%d\x01";
    const TOKEN_RE = '\x01FAQ\d+\x01';

    /**
     * Convert the FAQ shortcodes in post content to <details class="faq-item">.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Report what would change; write nothing.
     *
     * [--ids=<ids>]
     * : Comma-separated post IDs (default: every post that contains the shortcodes).
     *
     * [--backup=<file>]
     * : JSON-lines file for the original content of every changed post. Required unless --dry-run.
     *
     * [--print]
     * : Print the converted content of each post (use with --ids).
     *
     * @when after_wp_load
     */
    public function convert($args, $assoc)
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

        $where = "post_type <> 'revision' AND post_status <> 'auto-draft' AND (post_content LIKE %s OR post_content LIKE %s)";
        $params = ['%' . $wpdb->esc_like('[faq_item') . '%', '%' . $wpdb->esc_like('[jnews_faq') . '%'];
        if (!empty($assoc['ids'])) {
            $ids = array_filter(array_map('intval', explode(',', $assoc['ids'])));
            $where .= ' AND ID IN (' . implode(',', $ids ?: [0]) . ')';
        }
        $rows = $wpdb->get_results($wpdb->prepare("SELECT ID, post_type, post_content FROM {$wpdb->posts} WHERE {$where} ORDER BY ID", $params));

        $changes = [];
        $skipped = [];
        $empty = [];
        $totals =['items' => 0, 'empty_answers' => 0, 'quoted_questions' => 0];
        foreach ($rows as $row) {
            $stats = ['items' => 0, 'empty_answers' => 0, 'quoted_questions' => 0, 'error' => ''];
            $new = self::convert_content($row->post_content, $stats);
            if (null === $new) {
                $skipped[] = "#{$row->ID} ({$row->post_type}): {$stats['error']}";
                continue;
            }
            foreach ($totals as $k => $v) {
                $totals[$k] = $v + $stats[$k];
            }
            if ($stats['empty_answers']) {
                $empty[] = $row->ID;
            }
            if ($new !== $row->post_content) {
                $changes[(int) $row->ID] = [$row->post_content, $new];
            }
            if (!empty($assoc['print'])) {
                WP_CLI::line("===== #{$row->ID}\n{$new}\n");
            }
        }

        WP_CLI::line(sprintf('Posts with FAQ shortcodes: %d, to change: %d, skipped: %d', count($rows), count($changes), count($skipped)));
        WP_CLI::line(sprintf('FAQ items: %d (questions with quotes: %d, empty answers: %d)', $totals['items'], $totals['quoted_questions'], $totals['empty_answers']));
        foreach ($skipped as $line) {
            WP_CLI::warning("skipped {$line}");
        }
        if ($empty) {
            WP_CLI::warning('Empty answers (the source is broken, fix by hand after converting): #' . implode(', #', $empty));
        }
        if ($dry || !$changes) {
            WP_CLI::success($dry ? 'Dry run, nothing written.' : 'Nothing to change.');
            return;
        }

        // Back up everything first, then write.
        $fh = fopen($backup, 'x');
        if (!$fh) {
            WP_CLI::error("Can't create {$backup}");
        }
        foreach ($changes as $id => [$old, $new]) {
            fwrite($fh, wp_json_encode(['ID' => $id, 'post_content' => $old, 'converted_md5' => md5($new)]) . "\n");
        }
        fclose($fh);

        $done = 0;
        foreach ($changes as $id => [, $new]) {
            if (false === $wpdb->update($wpdb->posts, ['post_content' => $new], ['ID' => $id])) {
                WP_CLI::warning("#{$id}: update failed ({$wpdb->last_error})");
                continue;
            }
            clean_post_cache($id);
            $done++;
        }
        WP_CLI::success("Converted {$done} posts. Backup: {$backup}");
    }

    /**
     * Put back the content saved by `wp faq convert --backup`.
     *
     * ## OPTIONS
     *
     * <file>
     * : The backup file.
     *
     * [--force]
     * : Also restore posts edited since the conversion (their edits are lost).
     *
     * @when after_wp_load
     */
    public function restore($args, $assoc)
    {
        global $wpdb;
        $file = $args[0];
        if (!is_readable($file)) {
            WP_CLI::error("Can't read {$file}");
        }
        $done = 0;
        $kept = 0;
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $rec = json_decode($line, true);
            if (!is_array($rec) || empty($rec['ID']) || !isset($rec['post_content'])) {
                WP_CLI::warning('Bad line: ' . substr($line, 0, 80));
                continue;
            }
            $id = (int) $rec['ID'];
            $current = $wpdb->get_var($wpdb->prepare("SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $id));
            if (null === $current) {
                WP_CLI::warning("#{$id}: post not found");
                continue;
            }
            if (empty($assoc['force']) && isset($rec['converted_md5']) && md5($current) !== $rec['converted_md5']) {
                WP_CLI::warning("#{$id}: edited since the conversion, kept (use --force to restore anyway)");
                $kept++;
                continue;
            }
            if (false === $wpdb->update($wpdb->posts, ['post_content' => $rec['post_content']], ['ID' => $id])) {
                WP_CLI::warning("#{$id}: update failed ({$wpdb->last_error})");
                continue;
            }
            clean_post_cache($id);
            $done++;
        }
        WP_CLI::success("Restored {$done} posts" . ($kept ? ", kept {$kept} edited ones" : '') . '.');
    }

    /**
     * Content with every FAQ shortcode turned into <details>, or null (reason in $stats['error']) when it can't be
     * converted cleanly.
     */
    public static function convert_content(string $content, array &$stats): ?string
    {
        $items = [];
        // An item ends at [/faq_item], or at the next item / block tag when it was left unclosed.
        $content = preg_replace_callback(
            '/\[faq_item\s+question\s*=\s*"(.*?)"\s*\](.*?)(?:\[\/faq_item\]|(?=\[faq_item\b|\[\/?jnews_faq\b)|$)/s',
            static function ($m) use (&$items, &$stats) {
                $question = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($m[1])));
                if ('' === $question) {
                    $stats['error'] = $stats['error'] ?: 'empty question';
                }
                if (str_contains($m[1], '"')) {
                    $stats['quoted_questions']++;
                }
                $answer = self::answer_html($m[2]);
                if ('' === trim(wp_strip_all_tags($answer, true)) && !preg_match('/<(img|iframe|video|table)\b/i', $answer)) {
                    $stats['empty_answers']++;
                }
                $items[] = "<details class=\"faq-item\">\n<summary>{$question}</summary>\n<div class=\"faq-answer\">\n\n{$answer}\n\n</div>\n</details>";
                $stats['items']++;
                return sprintf(self::TOKEN, count($items) - 1);
            },
            $content
        );
        if (null === $content) {
            $stats['error'] = 'regex failed: ' . preg_last_error_msg();
            return null;
        }
        if ('' !== $stats['error']) {
            return null;
        }

        // Block tags, and stray closing tags (no opener, or the "]" lost while editing).
        $content = preg_replace(['/\[jnews_faq\b[^\]]*\]/', '/\[\/(?:jnews_faq|faq_item)\]?/'], '', $content);
        if (preg_match('/\[\/?(faq_item|jnews_faq)\b/', $content, $left)) {
            $stats['error'] = "unconverted [{$left[1]}] left";
            return null;
        }

        $content = self::tidy_around_items(self::hoist_items($content));

        return preg_replace_callback('/\x01FAQ(\d+)\x01/', static fn($m) => $items[(int) $m[1]], $content);
    }

    /** The answer: inside of the <div> that wraps it (stray tags around it dropped), else all of it; tags balanced. */
    private static function answer_html(string $raw): string
    {
        $edge = '(?:\s|&nbsp;|<\/?(?:' . self::HOIST . ')\b[^>]*>)*';
        if (preg_match('/^' . $edge . '<div\b[^>]*>(.*)<\/div>' . $edge . '$/s', $raw, $m)) {
            $raw = $m[1];
        }
        $html = force_balance_tags(trim(str_replace("\r\n", "\n", $raw)));
        // empty pairs left by the balancing (e.g. a lone <strong> at the end)
        do {
            $html = preg_replace('/<(' . self::HOIST . '|div)\b[^>]*>(?:\s|&nbsp;)*<\/\1>/i', '', $html, -1, $n);
        } while ($n);
        return trim($html);
    }

    /** Close the inline elements / <p> that are open around an item before it and reopen them after it. */
    private static function hoist_items(string $html): string
    {
        $parts = preg_split('/(<\/?(?:' . self::HOIST . ')\b[^>]*>|' . self::TOKEN_RE . ')/i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $stack = [];
        $out = '';
        foreach ($parts as $part) {
            if (preg_match('/^' . self::TOKEN_RE . '$/', $part)) {
                for ($i = count($stack) - 1; $i >= 0; $i--) {
                    $out .= '</' . $stack[$i][0] . '>';
                }
                $out .= "\n" . $part . "\n";
                foreach ($stack as [, $open]) {
                    $out .= $open;
                }
                continue;
            }
            if (preg_match('/^<(\/?)(' . self::HOIST . ')\b/i', $part, $m)) {
                $name = strtolower($m[2]);
                if ('' === $m[1]) {
                    if (!str_ends_with($part, '/>')) {
                        $stack[] = [$name, $part];
                    }
                } else {
                    for ($i = count($stack) - 1; $i >= 0; $i--) {
                        if ($stack[$i][0] === $name) {
                            array_splice($stack, $i);
                            break;
                        }
                    }
                }
            }
            $out .= $part;
        }
        return $out;
    }

    /**
     * Next to the items, drop the empty tag pairs the hoisting left; a gap between two items that holds only
     * tags, spaces, &nbsp;, <br> or a stray full stop / comma goes away.
     */
    private static function tidy_around_items(string $html): string
    {
        $segments = preg_split('/(' . self::TOKEN_RE . ')/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $last = count($segments) - 1;
        $run = '(?:\s|&nbsp;|<br\s*\/?>|<\/?(?:' . self::HOIST . ')\b[^>]*>)*';
        foreach ($segments as $i => $seg) {
            if ($i % 2) {
                continue; // token
            }
            $clean = static function (string $s): string {
                do {
                    $s = preg_replace('/<(' . self::HOIST . ')\b[^>]*>(?:\s|&nbsp;)*<\/\1>/i', '', $s, -1, $n);
                } while ($n);
                return $s;
            };
            if ($i > 0 && $i < $last) {
                $bare = preg_replace('/(<[^>]*>)|[.,،؛]/u', '$1', $seg); // punctuation outside tags
                if (preg_match('/^' . $run . '$/i', $bare)) {
                    $tags = $clean($bare);
                    $segments[$i] = preg_match('/^(?:\s|&nbsp;|<br\s*\/?>)*$/i', $tags) ? "\n" : $tags;
                    continue;
                }
            }
            if ($i > 0 && preg_match('/^' . $run . '/i', $seg, $m)) { // run right after an item
                $seg = $clean($m[0]) . substr($seg, strlen($m[0]));
            }
            if ($i < $last && preg_match('/' . $run . '$/i', $seg, $m)) { // run right before an item
                $seg = substr($seg, 0, strlen($seg) - strlen($m[0])) . $clean($m[0]);
            }
            $segments[$i] = $seg;
        }
        return implode('', $segments);
    }
}

WP_CLI::add_command('faq', 'Faq_Convert_CLI');
