<?php
/**
 * WP-CLI tooling that moves the car directory from the staging site (its own plugin) to live:
 *
 *   wp cars purge [--confirm]                 remove the OLD ACF-defined car directory (once, before the first import)
 *   wp cars import-pack <file> [...]          import a pack written by the staging site's export
 *   wp cars apply-keep <keep-file> [...]      restore the old descriptions / SEO fields; write the 301 map
 *   wp cars paths                             rebuild the nested URLs (_car_path)
 *
 * Loaded only under WP-CLI (functions.php). Packs are NDJSON: attachments, terms (parents first), posts
 * with every raw meta row and term relationships, options. Objects are matched by stable keys -
 * posts/terms by _car_source_id (else type + slug), attachments by _car_image_url (else file
 * path) - so a pack can be sent again after the clerk changes more; ids are remapped (ACF relationship
 * / gallery lists, featured images, term parents, term logos, attachment parents). Meta that is not in
 * the pack (Rank Math, view counts) stays, and a description restored by apply-keep stays on re-import.
 * Posts are written with direct SQL (no save hooks): a full pack takes minutes, not an hour.
 */

if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) {
    return;
}

final class Car_Directory_CLI
{
    const SOURCE_ID = '_car_source_id';
    const SOURCE_URL = '_car_source_url';
    const IMAGE_URL = '_car_image_url';
    const KEEP_FLAG = '_car_keep_applied';
    const TYPES = ['car', 'car_model_year'];
    const LIST_KEYS = ['parent_model_year', 'trims', 'gallery', 'design_wheel_designs']; // serialized lists of post ids
    const INT_KEYS = ['_thumbnail_id', '_car_parent_year'];                        // one post id
    const SKIP_KEYS = ['_edit_lock', '_edit_last', '_car_pending_media'];
    /** --keep-text: meta that is text an editor may have changed on live (kept on existing objects) */
    const TEXT_KEYS = ['review', 'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword', '_car_keep_applied'];
    const PROTECTED_KEYS = ['_car_keep_applied'];

    // the OLD directory (purge)
    const OLD_TAXONOMIES = ['car_maker', 'car_model', 'car_year', 'car_origin', 'car_body_type', 'car_fuel_type', 'car_model_year'];
    const OLD_KEEP_META = ['rank_math_title', 'rank_math_description', 'rank_math_focus_keyword', 'rank_math_robots', 'rank_math_canonical_url', 'post_views_count'];
    const RANK_MATH_OPTIONS = ['rank-math-options-titles', 'rank-math-options-sitemap'];

    private array $stats = [];
    private array $warnings = [];
    private array $post_by_source = [];
    private array $post_by_slug = [];
    private array $term_by_source = [];
    private array $term_by_key = [];
    private array $att_by_url = [];
    private array $att_by_file = [];
    private array $keep_applied = [];
    private array $post_map = [];
    private array $term_map = [];
    private array $new_atts = [];
    private array $new_posts = []; // target IDs created by this run
    private float $last_line = 0.0; // progress(): last time a line was printed
    private int $pack_total = 0;    // records in the pack being imported (header)
    private array $tt_of = []; // taxonomy => term_id => term_taxonomy_id
    private string $pack_prefix = ''; // meta-key prefix used in the pack (header), mapped to _car_
    private bool $keep_text = false;

    // ===================================================================== purge

    /**
     * Remove the old ACF-defined car directory (CPT `car` at /car-directory/, its car_* taxonomies, the
     * "Car Specifications Tables" field group, the cars' own media, stale Rank Math keys) so the new
     * directory can own `car`. Writes a keep-file first (also in the dry run). Never touches `department`.
     *
     * ## OPTIONS
     *
     * [--confirm]
     * : Really delete. Without it the command only reports what it would do.
     *
     * [--keep-file=<path>]
     * : Where to write the keep-file (default: <WordPress root>/tmp/car-purge-keep-<time>.ndjson). Never overwritten without --overwrite.
     *
     * [--overwrite]
     * : Overwrite an existing keep-file.
     *
     * [--resume]
     * : Continue an interrupted run: keep the existing keep-file (written before anything was deleted) and go on deleting.
     *
     * [--keep-media]
     * : Leave the old cars' attachments in the Media Library.
     *
     * [--force]
     * : Delete the old taxonomies even when objects other than cars are attached to them.
     *
     * @when after_wp_load
     */
    public function purge($args, $assoc): void
    {
        global $wpdb;
        $this->unbuffer();
        $dry = !WP_CLI\Utils\get_flag_value($assoc, 'confirm', false);
        $tag = $dry ? '[Purge DRY RUN]' : '[Purge]';
        $cars = array_map('intval', $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'car' AND post_status <> 'auto-draft' ORDER BY ID"));
        $ours = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
            WHERE p.post_type = 'car' AND m.meta_value <> ''", self::SOURCE_ID));
        if ($ours) {
            WP_CLI::error(sprintf('%d of %d car posts carry %s - they are new-directory cars; refusing to purge a mixed site', $ours, count($cars), self::SOURCE_ID));
        }
        $taxes = "'" . implode("','", self::OLD_TAXONOMIES) . "'";
        $ours = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->termmeta} m JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = m.term_id
            WHERE m.meta_key = %s AND m.meta_value <> '' AND tt.taxonomy IN ({$taxes})", self::SOURCE_ID));
        if ($ours) {
            WP_CLI::error("{$ours} terms of the old taxonomies carry " . self::SOURCE_ID . ' - they belong to the new directory; refusing to purge');
        }
        $this->line(sprintf('%s old cars: %d', $tag, count($cars)));

        $file = $assoc['keep-file'] ?? ($this->tmp_dir() . 'car-purge-keep-' . gmdate('Ymd-His') . '.ndjson');
        if (!$cars) {
            $this->line("{$tag} no old car posts - keep-file untouched, cleaning up what is left");
        } else {
            $complete = is_file($file) && (int) (json_decode((string) fgets(fopen($file, 'rb')), true)['cars'] ?? -1) === count($cars);
            if (is_file($file) && WP_CLI\Utils\get_flag_value($assoc, 'resume', false)) {
                $this->line(sprintf('%s resuming: keep-file %s kept (%d cars still to delete)', $tag, $file, count($cars)));
            } elseif ($complete) {
                $this->line(sprintf('%s keep-file %s already holds all %d cars (from the dry run) - reused', $tag, $file, count($cars)));
            } else {
                if (is_file($file) && !WP_CLI\Utils\get_flag_value($assoc, 'overwrite', false)) {
                    WP_CLI::error("{$file} exists - pass --resume to continue an interrupted run, or --overwrite");
                }
                $this->write_keep_file($file, $cars);
                $this->line(sprintf('%s keep-file: %s (%d cars)', $tag, $file, count($cars)));
            }
        }

        [$atts, $kept] = WP_CLI\Utils\get_flag_value($assoc, 'keep-media', false) ? [[], []] : $this->old_car_attachments($cars);
        $this->line(sprintf('%s attachments used only by cars: %d%s', $tag, count($atts), $kept ? ' | kept because something else uses them: ' . count($kept) : ''));
        $this->stats['attachments'] = count($atts);
        if (!$dry) {
            $this->speed_up();
            // bulk run: the per-object hooks of other plugins (image optimiser, cache purge, SEO analytics) cost ~1 s
            // per deletion and add nothing here - their tables are cleaned below and the cache is flushed at the end
            foreach (['delete_attachment', 'delete_post', 'deleted_post', 'before_delete_post', 'after_delete_post', 'delete_term', 'deleted_term_relationships'] as $hook) {
                remove_all_actions($hook);
            }
            foreach ($atts as $i => $id) {
                wp_delete_attachment($id, true);
                $this->progress($tag, 'attachments deleted', $i + 1, count($atts), 100);
            }
            $left = $atts ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID IN (" . implode(',', $atts) . ')') : 0;
            $this->stats['attachments'] -= $left;
        }

        $this->stats['cars'] = count($cars);
        if (!$dry) {
            $failed = 0;
            $wpdb->query('START TRANSACTION');
            foreach ($cars as $i => $id) {
                if (!wp_delete_post($id, true)) {
                    ++$failed;
                }
                if ($wpdb->last_error !== '') {
                    $wpdb->query('ROLLBACK');
                    WP_CLI::error("database error while deleting car {$id}: {$wpdb->last_error} - nothing of this batch was deleted; fix and re-run");
                }
                if (($i + 1) % 250 === 0) {
                    $wpdb->query('COMMIT');
                    wp_cache_flush_runtime();
                    $wpdb->queries = [];
                    $wpdb->query('START TRANSACTION');
                }
                $this->progress($tag, 'cars deleted', $i + 1, count($cars), 250);
            }
            $wpdb->query('COMMIT');
            wp_cache_flush_runtime();
            $left = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'car' AND post_status <> 'auto-draft'");
            if ($failed || $left) {
                WP_CLI::error("{$failed} cars could not be deleted, {$left} still exist - re-run the command");
            }
        }

        $this->line("{$tag} taxonomies...");
        $terms_total = 0;
        foreach (self::OLD_TAXONOMIES as $tax) {
            $terms = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $tax));
            if (!$terms) {
                continue;
            }
            $foreign = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                 JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE tt.taxonomy = %s AND p.post_type <> 'car'",
                $tax
            ));
            if ($foreign && !WP_CLI\Utils\get_flag_value($assoc, 'force', false)) {
                WP_CLI::error("taxonomy {$tax}: {$foreign} relationships to objects that are not cars - check them, then pass --force to delete the taxonomy anyway");
            }
            $this->line(sprintf('%s taxonomy %s: %d terms%s', $tag, $tax, $terms, $foreign ? " ({$foreign} relationships to non-car objects, --force)" : ''));
            if (!$dry) {
                $this->purge_taxonomy($tax);
            }
            $terms_total += $terms;
        }

        $this->line("{$tag} ACF definitions, options, plugin tables...");
        $defs = 0;
        foreach ($this->old_acf_definitions() as $d) {
            if ($d['shared']) {
                $this->line(sprintf('%s ACF %s "%s" (%s) is also used by other post types - left in place', $tag, $d['type'], $d['title'], $d['key']));
                continue;
            }
            $this->line(sprintf('%s ACF %s "%s" (%s, %s)%s', $tag, $d['type'], $d['title'], $d['key'], $d['status'], $d['json'] ? ' + ' . $d['json'] : ''));
            if (!$dry) {
                foreach ($this->acf_children($d['id']) as $field_id) {
                    wp_delete_post($field_id, true);
                }
                wp_delete_post($d['id'], true);
                if ($d['json'] && is_file($d['json'])) {
                    wp_delete_file($d['json']);
                }
            }
            ++$defs;
        }

        $keys = 0;
        $re = '/^(pt_car_|tax_(' . implode('|', array_merge(self::OLD_TAXONOMIES, ['car_make'])) . ')_)/';
        foreach (self::RANK_MATH_OPTIONS as $name) {
            $opt = get_option($name);
            if (!is_array($opt)) {
                continue;
            }
            $clean = array_filter($opt, fn($k) => !preg_match($re, (string) $k), ARRAY_FILTER_USE_KEY);
            $keys += count($opt) - count($clean);
            if (!$dry && count($clean) !== count($opt)) {
                update_option($name, $clean);
            }
        }
        $transients = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_car_meta_vals_%' OR option_name LIKE '_transient_timeout_car_meta_vals_%'");
        if (!$dry && $transients) {
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_car_meta_vals_%' OR option_name LIKE '_transient_timeout_car_meta_vals_%'");
        }
        $analytics = $this->purge_table_rows($wpdb->prefix . 'rank_math_analytics_objects', "object_subtype = 'car'", $dry);
        if ($atts) {
            $this->purge_table_rows($wpdb->prefix . 'ewwwio_images', 'attachment_id IN (' . implode(',', $atts) . ')', $dry);
        }
        if (!$dry) {
            wp_defer_term_counting(false);
            wp_suspend_cache_invalidation(false);
            wp_cache_flush();
            flush_rewrite_rules(false);
        }
        $this->line(sprintf('[%s] purge | cars %d | attachments %d (kept %d) | terms %d | ACF definitions %d | Rank Math keys %d | transients %d | analytics rows %d | keep-file: %s%s',
            $dry ? 'DRY RUN' : 'COMPLETED', $this->stats['cars'], $this->stats['attachments'], count($kept), $terms_total, $defs, $keys, $transients, $analytics, $file,
            $dry ? ' | add --confirm to run it' : ' | next: wp rewrite flush, then wp cars import-pack <pack>'));
    }

    private function write_keep_file(string $file, array $cars): void
    {
        global $wpdb;
        $fh = fopen($file, 'wb');
        if (!$fh) {
            WP_CLI::error("cannot write {$file}");
        }
        $lines = 0;
        $write = function (array $row) use ($fh, $file, &$lines): void {
            $line = wp_json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
            if (fwrite($fh, $line) !== strlen($line)) {
                WP_CLI::error("short write to {$file} (disk full?)");
            }
            ++$lines;
        };
        $write(['type' => 'header', 'site' => home_url(), 'exported' => gmdate('c'), 'cars' => count($cars),
            'old_url_shape' => '/car-directory/<decoded, lower-cased slug>/ - compare after rawurldecode + strtolower of the request path']);
        $taxes = "'" . implode("','", self::OLD_TAXONOMIES) . "'";
        foreach ($cars as $i => $id) {
            $this->progress('[Purge]', 'keep-file', $i + 1, count($cars), 500);
            $p = get_post($id);
            if (!$p) {
                continue;
            }
            $meta = [];
            foreach (self::OLD_KEEP_META as $k) {
                $v = get_post_meta($id, $k, true);
                if (is_array($v) ? $v !== [] : ((string) $v !== '' && $v !== null)) { // rank_math_robots is a list
                    $meta[$k] = $v;
                }
            }
            $terms = [];
            foreach ($wpdb->get_results($wpdb->prepare(
                "SELECT tt.taxonomy, t.slug, t.name FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                 JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tr.object_id = %d AND tt.taxonomy IN ({$taxes})",
                $id
            )) as $r) {
                $terms[$r->taxonomy][] = [$r->slug, $r->name];
            }
            $slug = $p->post_name;
            if ($p->post_status === 'trash') {
                $slug = (string) get_post_meta($id, '_wp_desired_post_slug', true) ?: preg_replace('/__trashed(-\d+)?$/', '', $slug);
            }
            $thumb = (int) get_post_meta($id, '_thumbnail_id', true);
            $write([
                'type' => 'car', 'id' => $id, 'path' => $slug ? '/car-directory/' . strtolower(rawurldecode($slug)) . '/' : '', 'slug' => $p->post_name,
                'title' => $p->post_title, 'status' => $p->post_status, 'date' => $p->post_date, 'modified' => $p->post_modified,
                'source_url' => (string) ($wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s AND meta_value <> '' LIMIT 1", $id, '%' . $wpdb->esc_like('_source_url'))) ?? ''), // the old site stored it under its own prefix
                'content' => $p->post_content, 'excerpt' => $p->post_excerpt,
                'meta' => $meta, 'terms' => $terms, 'thumbnail' => $thumb ? (string) get_post_meta($thumb, '_wp_attached_file', true) : '',
            ]);
        }
        if (!fclose($fh) || $lines !== count($cars) + 1) {
            WP_CLI::error(sprintf('%s holds %d lines, expected %d - not deleting anything', $file, $lines, count($cars) + 1));
        }
    }

    /** @return array{0: int[], 1: int[]} [attachments to delete, attachments kept because something outside the cars uses them] */
    private function old_car_attachments(array $cars): array
    {
        global $wpdb;
        if (!$cars) {
            return [[], []];
        }
        $t0 = microtime(true);
        $step = function (string $what) use (&$t0): void {
            $this->line(sprintf('[Purge] images: %s (%.0f s)', $what, microtime(true) - $t0));
            $t0 = microtime(true);
        };
        $ids = [];
        foreach (array_chunk($cars, 500) as $chunk) {
            $in = implode(',', $chunk);
            $ids = array_merge($ids, $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_parent IN ({$in})"));
            $ids = array_merge($ids, $wpdb->get_col("SELECT a.ID FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} a ON a.ID = m.meta_value AND a.post_type = 'attachment'
                WHERE m.meta_key = '_thumbnail_id' AND m.post_id IN ({$in})"));
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $step(count($ids) . ' candidate images of the old cars collected');
        if (!$ids) {
            return [[], []];
        }
        $in = implode(',', $ids);
        $cars_in = implode(',', $cars);
        $keep = [];
        foreach ([get_option('custom_logo'), get_option('site_icon')] as $opt) {
            if ((int) $opt) {
                $keep[(int) $opt] = true;
            }
        }
        // 1/4: any meta value of something that is not a car (featured image, ACF image, SEO social image...)
        $this->line('[Purge] images: 1/4 looking for them in the meta of other content (one pass over postmeta)...');
        foreach ($wpdb->get_results("SELECT m.meta_value, p.post_type FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
            WHERE m.meta_value IN ({$in}) AND m.post_id NOT IN ({$cars_in}) AND p.post_type <> 'attachment'") as $r) {
            $keep[(int) $r->meta_value] = true;
        }
        $step('1/4 done');
        $this->line('[Purge] images: 2/4 term meta...');
        foreach ($wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->termmeta} WHERE meta_value IN ({$in})") as $used) {
            $keep[(int) $used] = true;
        }
        $step('2/4 done');
        $this->line('[Purge] images: 3/4 attachments that belong to other posts...');
        $car_set = array_flip($cars);
        foreach ($wpdb->get_results("SELECT ID, post_parent FROM {$wpdb->posts} WHERE ID IN ({$in})") as $a) {
            if ($a->post_parent && !isset($car_set[(int) $a->post_parent])) {
                $keep[(int) $a->ID] = true;
            }
        }
        $step('3/4 done');
        // 4/4: embedded in the content of something that is not a car (wp-image-<id> class, or the file name): one pass
        $this->line('[Purge] images: 4/4 looking for them inside article contents (one pass over the posts)...');
        $files = [];
        foreach ($wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND post_id IN ({$in})") as $f) {
            $files[(int) $f->post_id] = wp_basename($f->meta_value);
        }
        foreach (array_chunk($ids, 500) as $chunk) {
            $alts = ['wp-image-(' . implode('|', $chunk) . ')([^0-9]|$)'];
            foreach ($chunk as $att_id) {
                if (isset($files[$att_id])) {
                    $alts[] = preg_replace('/[.\\\\+*?\[^\]$(){}=!<>|:#-]/', '\\\\$0', $files[$att_id]);
                }
            }
            $hits = $wpdb->get_results($wpdb->prepare("SELECT ID, post_content FROM {$wpdb->posts} WHERE post_type NOT IN ('car', 'attachment', 'revision')
                AND post_status <> 'auto-draft' AND post_content REGEXP %s", '(' . implode('|', $alts) . ')'));
            foreach ($hits as $h) {
                foreach ($chunk as $att_id) {
                    if (preg_match('/wp-image-' . $att_id . '(?!\d)/', $h->post_content) || (isset($files[$att_id]) && str_contains($h->post_content, $files[$att_id]))) {
                        $keep[$att_id] = true;
                    }
                }
            }
        }
        $step('4/4 done');
        return [
            array_values(array_filter($ids, fn(int $id) => !isset($keep[$id]))),
            array_values(array_filter($ids, fn(int $id) => isset($keep[$id]))),
        ];
    }

    private function purge_taxonomy(string $tax): void
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT term_taxonomy_id, term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $tax));
        if (!$rows) {
            return;
        }
        $tt_ids = implode(',', array_map(fn($r) => (int) $r->term_taxonomy_id, $rows));
        $term_ids = array_map(fn($r) => (int) $r->term_id, $rows);
        $wpdb->query("DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ({$tt_ids})");
        $wpdb->query("DELETE FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id IN ({$tt_ids})");
        $in = implode(',', $term_ids);
        $shared = array_map('intval', $wpdb->get_col("SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_id IN ({$in})"));
        $orphans = array_diff($term_ids, $shared);
        if ($orphans) {
            $in = implode(',', $orphans);
            $wpdb->query("DELETE FROM {$wpdb->termmeta} WHERE term_id IN ({$in})");
            $wpdb->query("DELETE FROM {$wpdb->terms} WHERE term_id IN ({$in})");
        }
        delete_option("{$tax}_children");
        if (taxonomy_exists($tax)) {
            clean_taxonomy_cache($tax);
        }
    }

    private function old_acf_definitions(): array
    {
        global $wpdb;
        $out = [];
        $dirs = function_exists('acf_get_setting') ? (array) acf_get_setting('load_json') : [];
        $dirs = array_unique(array_merge($dirs, [get_stylesheet_directory() . '/acf-json', get_template_directory() . '/acf-json']));
        foreach ($wpdb->get_results("SELECT ID, post_type, post_title, post_name, post_status, post_content FROM {$wpdb->posts}
            WHERE post_type IN ('acf-post-type', 'acf-taxonomy', 'acf-field-group') AND post_status <> 'auto-draft'") as $p) {
            $s = maybe_unserialize($p->post_content);
            $s = is_array($s) ? $s : [];
            $hit = false;
            $shared = false;
            if ($p->post_type === 'acf-post-type') {
                $hit = ($s['post_type'] ?? '') === 'car' && ($s['key'] ?? $p->post_name) !== $this->new_definition_key('post_type', 'car');
            } elseif ($p->post_type === 'acf-taxonomy') {
                $hit = in_array($s['taxonomy'] ?? '', self::OLD_TAXONOMIES, true) && !$this->is_new_taxonomy_definition($p->post_name);
            } else {
                foreach ($s['location'] ?? [] as $group) {
                    $car_rule = false;
                    foreach ((array) $group as $rule) {
                        if (($rule['param'] ?? '') === 'post_type' && ($rule['value'] ?? '') === 'car' && ($rule['operator'] ?? '==') === '==') {
                            $car_rule = true;
                        }
                    }
                    $hit = $hit || $car_rule;
                    $shared = $shared || !$car_rule;
                }
                $hit = $hit && !str_starts_with($p->post_name, 'group_car_'); // the new directory's own groups
            }
            if (!$hit) {
                continue;
            }
            $json = '';
            foreach ($dirs as $dir) {
                if (is_file(rtrim($dir, '/\\') . '/' . $p->post_name . '.json')) {
                    $json = rtrim($dir, '/\\') . '/' . $p->post_name . '.json';
                }
            }
            $out[] = ['id' => (int) $p->ID, 'type' => $p->post_type, 'title' => $p->post_title, 'key' => $p->post_name, 'status' => $p->post_status, 'json' => $json, 'shared' => $shared];
        }
        return $out;
    }

    /** Key of the new directory's own ACF definition (acf-json/post_type_*.json / taxonomy_*.json, generated with a stable hash). */
    private function new_definition_key(string $kind, string $slug): string
    {
        return $kind . '_' . substr(md5('tafaol-' . $slug), 0, 13);
    }

    private function is_new_taxonomy_definition(string $key): bool
    {
        foreach (array_keys(car_directory_taxonomies()) as $slug) {
            if ($key === $this->new_definition_key('taxonomy', $slug)) {
                return true;
            }
        }
        return false;
    }

    private function acf_children(int $parent): array
    {
        global $wpdb;
        $ids = array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'acf-field' AND post_parent = %d", $parent)));
        foreach ($ids as $id) {
            $ids = array_merge($ids, $this->acf_children($id));
        }
        return $ids;
    }

    private function purge_table_rows(string $table, string $where, bool $dry): int
    {
        global $wpdb;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return 0;
        }
        $n = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where}");
        if ($n && !$dry) {
            $wpdb->query("DELETE FROM {$table} WHERE {$where}");
        }
        return $n;
    }

    // ===================================================================== import-pack

    /**
     * Import a pack written by the staging site's export (or by `wp cars export-pack`). Re-importing updates in place.
     *
     * ## OPTIONS
     *
     * <file>
     * : The pack file (NDJSON).
     *
     * [--media-dir=<path>]
     * : The staging site's wp-content/uploads folder (a local copy): image files are copied from it.
     *
     * [--media-url=<url>]
     * : The staging site's uploads URL (https://.../wp-content/uploads): files not found in --media-dir are downloaded.
     *
     * [--skip-media]
     * : Create the attachments without their files (local rehearsal).
     *
     * [--keep-text]
     * : For objects that already exist here keep their titles, descriptions, term names / descriptions, reviews and
     * SEO fields (the clerk's packs from staging bring images, galleries, specs and deleted images; the texts are edited on live).
     *
     * [--dry-run]
     * : Match only: report what would be created / updated; write nothing.
     *
     * ## EXAMPLES
     *
     *     wp cars import-pack tmp/all.ndjson --dry-run
     *     wp cars import-pack tmp/toyota.ndjson --media-dir=/home/user/staging-uploads
     *
     * @subcommand import-pack
     * @when after_wp_load
     */
    public function import_pack($args, $assoc): void
    {
        global $wpdb;
        $this->unbuffer();
        $file = $args[0];
        $opt = [
            'dry-run' => (bool) WP_CLI\Utils\get_flag_value($assoc, 'dry-run', false),
            'media-dir' => $assoc['media-dir'] ?? '',
            'media-url' => $assoc['media-url'] ?? '',
            'skip-media' => (bool) WP_CLI\Utils\get_flag_value($assoc, 'skip-media', false),
        ];
        $this->keep_text = (bool) WP_CLI\Utils\get_flag_value($assoc, 'keep-text', false);
        $dry = $opt['dry-run'];
        if (!is_readable($file)) {
            WP_CLI::error("{$file} not found");
        }
        if ($opt['media-dir'] && !is_dir($opt['media-dir'])) {
            WP_CLI::error("--media-dir {$opt['media-dir']} is not a folder");
        }
        foreach (array_keys(car_directory_taxonomies()) as $tax) {
            if (!taxonomy_exists($tax)) {
                WP_CLI::error("taxonomy {$tax} is not registered - are the acf-json/taxonomy_*.json files of the car directory in place?");
            }
        }
        $tag = $dry ? '[Pack DRY RUN]' : '[Pack]';
        if (!$dry) {
            $this->speed_up();
        }
        $this->load_target();
        $header = null;
        $n = 0;
        $this->begin($dry);
        foreach ($this->ndjson($file) as $rec) {
            $rec = $this->normalize_keys($rec);
            switch ($rec['type']) {
                case 'header':
                    $header = $rec;
                    $this->pack_total = (int) ($rec['posts'] ?? 0) + (int) ($rec['terms'] ?? 0) + (int) ($rec['attachments'] ?? 0);
                    $this->pack_prefix = (string) ($rec['meta_prefix'] ?? '');
                    if ($this->pack_prefix === '') {
                        WP_CLI::error('this pack has no meta_prefix in its header - export it again with the current tooling');
                    }
                    $this->line(sprintf('%s %s | from %s (%s) | %d posts, %d terms, %d attachments', $tag, basename($file), $rec['site'] ?? '?', $rec['exported'] ?? '?',
                        $rec['posts'] ?? 0, $rec['terms'] ?? 0, $rec['attachments'] ?? 0));
                    break;
                case 'attachment':
                    $this->import_attachment($rec, $opt, $dry);
                    break;
                case 'term':
                    $this->import_term($rec, $dry);
                    break;
                case 'post':
                    $this->import_post_row($rec, $dry);
                    break;
            }
            $this->tick(++$n, $dry, $tag, 'pass 1');
        }
        $this->commit($dry);
        if (!$header) {
            WP_CLI::error('not a pack file (no header line)');
        }
        $n = 0;
        $this->begin($dry);
        foreach ($this->ndjson($file) as $rec) {
            $rec = $this->normalize_keys($rec);
            if ($rec['type'] === 'post') {
                $this->import_post_meta($rec, $dry);
            } elseif ($rec['type'] === 'attachment' && !$dry) {
                $new = $this->post_map[(int) $rec['id']] ?? 0;
                $parent = $this->post_map[(int) $rec['parent']] ?? 0;
                if ($new > 0 && $parent > 0 && in_array($new, $this->new_atts, true)) {
                    $wpdb->update($wpdb->posts, ['post_parent' => $parent], ['ID' => $new]);
                }
            }
            $this->tick(++$n, $dry, $tag, 'pass 2');
        }
        $this->commit($dry);
        if (!$dry) {
            $this->finish();
        }
        $this->report($dry ? 'DRY RUN' : 'COMPLETED', 'import-pack', 'next: wp cars apply-keep <keep-file> --redirects-out=<csv>');
    }

    private function load_target(): void
    {
        global $wpdb;
        $this->line('[Pack] reading this site\'s cars, model years, terms and attachments...');
        $types = "'" . implode("','", self::TYPES) . "'";
        $taxes = "'" . implode("','", array_keys(car_directory_taxonomies())) . "'";
        foreach ($wpdb->get_results($wpdb->prepare("SELECT p.ID, p.post_type, m.meta_value FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
            WHERE p.post_type IN ({$types})", self::SOURCE_ID)) as $r) {
            if ((string) $r->meta_value !== '') {
                $this->post_by_source[$r->post_type][(string) $r->meta_value] = (int) $r->ID;
            }
        }
        foreach ($wpdb->get_results("SELECT ID, post_type, post_name FROM {$wpdb->posts} WHERE post_type IN ({$types}) AND post_status NOT IN ('auto-draft', 'trash')") as $r) {
            $this->post_by_slug[$r->post_type][$r->post_name] = (int) $r->ID;
        }
        foreach ($wpdb->get_results("SELECT tt.term_id, tt.term_taxonomy_id, tt.taxonomy, tt.parent, t.slug FROM {$wpdb->term_taxonomy} tt JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tt.taxonomy IN ({$taxes})") as $r) {
            $this->term_by_key[$r->taxonomy]["{$r->parent}|{$r->slug}"] = (int) $r->term_id;
            $this->tt_of[$r->taxonomy][(int) $r->term_id] = (int) $r->term_taxonomy_id;
        }
        foreach ($wpdb->get_results($wpdb->prepare("SELECT tt.taxonomy, m.term_id, m.meta_value FROM {$wpdb->termmeta} m JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = m.term_id
            WHERE m.meta_key = %s AND tt.taxonomy IN ({$taxes})", self::SOURCE_ID)) as $r) {
            if ((string) $r->meta_value !== '' && (string) $r->meta_value !== '0') {
                $this->term_by_source[$r->taxonomy][(string) $r->meta_value] = (int) $r->term_id;
            }
        }
        foreach ($wpdb->get_results($wpdb->prepare("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", self::IMAGE_URL)) as $r) {
            $this->att_by_url[$r->meta_value] = (int) $r->post_id;
        }
        foreach ($wpdb->get_results("SELECT m.post_id, m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id AND p.post_type = 'attachment' WHERE m.meta_key = '_wp_attached_file'") as $r) {
            $this->att_by_file[$r->meta_value] = (int) $r->post_id;
        }
        foreach ($wpdb->get_col($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", self::KEEP_FLAG)) as $id) {
            $this->keep_applied[(int) $id] = true;
        }
        $this->line(sprintf('[Pack] on this site: %d cars, %d model years, %d terms, %d attachments, %d cars with a restored description',
            count($this->post_by_slug['car'] ?? []), count($this->post_by_slug['car_model_year'] ?? []),
            array_sum(array_map('count', $this->term_by_key)), count($this->att_by_file), count($this->keep_applied)));
    }

    private function import_attachment(array $rec, array $opt, bool $dry): void
    {
        global $wpdb;
        $url = '';
        foreach ($rec['meta'] as [$k, $v]) {
            if ($k === self::IMAGE_URL) {
                $url = $v;
            }
        }
        $id = ($url ? $this->att_by_url[$url] ?? 0 : 0) ?: ($rec['file'] ? $this->att_by_file[$rec['file']] ?? 0 : 0);
        if ($id) {
            $this->post_map[(int) $rec['id']] = $id;
            $this->bump('attachments_existing');
            return;
        }
        $this->bump('attachments_new');
        if ($dry) {
            $this->post_map[(int) $rec['id']] = -1;
            $this->media_check($rec, $opt);
            return;
        }
        $upload = wp_upload_dir();
        $id = $this->insert_post([
            'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => $rec['title'], 'post_name' => $rec['name'],
            'post_mime_type' => $rec['mime'], 'post_excerpt' => (string) $rec['excerpt'], 'post_content' => (string) $rec['content'],
            'post_date' => $rec['date'], 'post_date_gmt' => $rec['date_gmt'], 'post_modified' => $rec['date'], 'post_modified_gmt' => $rec['date_gmt'],
            'guid' => $rec['file'] ? trailingslashit($upload['baseurl']) . $rec['file'] : '', 'menu_order' => 0,
        ]);
        if (!$id) {
            $this->warn("attachment {$rec['file']}: " . $wpdb->last_error);
            return;
        }
        $this->write_meta('post', $id, $rec['meta'], []);
        $this->post_map[(int) $rec['id']] = $id;
        $this->new_atts[] = $id;
        if ($url) {
            $this->att_by_url[$url] = $id;
        }
        if ($rec['file']) {
            $this->att_by_file[$rec['file']] = $id;
        }
        if (!$opt['skip-media']) {
            $this->fetch_media($rec, $opt);
        }
    }

    private function media_check(array $rec, array $opt): void
    {
        $base = wp_upload_dir()['basedir'] . '/';
        foreach (array_merge($rec['file'] ? [$rec['file']] : [], $rec['sizes']) as $path) {
            if (is_file($base . $path)) {
                $this->bump('files_present');
            } elseif ($opt['media-dir'] && is_file(rtrim($opt['media-dir'], '/\\') . '/' . $path)) {
                $this->bump('files_to_copy');
            } elseif ($opt['media-url']) {
                $this->bump('files_to_download');
            } else {
                $this->bump('files_missing');
            }
        }
    }

    private function fetch_media(array $rec, array $opt): void
    {
        $base = wp_upload_dir()['basedir'] . '/';
        foreach (array_merge($rec['file'] ? [$rec['file']] : [], $rec['sizes']) as $path) {
            $target = $base . $path;
            if (is_file($target)) {
                $this->bump('files_present');
                continue;
            }
            wp_mkdir_p(dirname($target));
            if ($opt['media-dir']) {
                $src = rtrim($opt['media-dir'], '/\\') . '/' . $path;
                if (is_file($src) && copy($src, $target)) {
                    $this->bump('files_copied');
                    continue;
                }
            }
            if ($opt['media-url']) {
                $res = wp_remote_get(rtrim($opt['media-url'], '/') . '/' . str_replace('%2F', '/', rawurlencode($path)), ['timeout' => 120, 'stream' => true, 'filename' => $target]);
                if (!is_wp_error($res) && (int) wp_remote_retrieve_response_code($res) === 200 && filesize($target) > 0) {
                    $this->bump('files_downloaded');
                    continue;
                }
                if (is_file($target)) {
                    wp_delete_file($target);
                }
            }
            $this->bump('files_missing');
            $this->warn("file missing: {$path}");
        }
    }

    private function import_term(array $rec, bool $dry): void
    {
        $tax = $rec['taxonomy'];
        $old = (int) $rec['id'];
        $parent = 0;
        $parent_new = false;
        if ($rec['parent']) {
            $parent = $this->term_map[(int) $rec['parent']] ?? null;
            if ($parent === null) {
                $this->warn("term {$tax}/{$rec['slug']}: parent {$rec['parent']} not in the pack - imported at the top level");
                $parent = 0;
            } elseif ($parent < 0) {
                $parent_new = true;
                $parent = 0;
            }
        }
        $source = '';
        foreach ($rec['meta'] as [$k, $v]) {
            if ($k === self::SOURCE_ID && $v !== '' && $v !== '0') {
                $source = $v;
            }
        }
        $id = $source ? ($this->term_by_source[$tax][$source] ?? 0) : 0;
        if (!$id && !$parent_new) {
            $id = $this->term_by_key[$tax]["{$parent}|{$rec['slug']}"] ?? 0;
        }
        $this->bump($id ? 'terms_existing' : 'terms_new');
        if ($dry) {
            $this->term_map[$old] = $id ?: -1;
            return;
        }
        $args = ['slug' => $rec['slug'], 'parent' => $parent, 'description' => $rec['description']];
        if ($id && $this->keep_text) {
            $res = wp_update_term($id, $tax, ['slug' => $rec['slug'], 'parent' => $parent]); // name and description stay
            $this->bump('terms_text_kept');
        } elseif ($id) {
            $res = wp_update_term($id, $tax, $args + ['name' => wp_slash($rec['name'])]);
        } else {
            $res = wp_insert_term(wp_slash($rec['name']), $tax, $args);
            if (is_wp_error($res) && $res->get_error_code() === 'term_exists') {
                $res = wp_insert_term(wp_slash("{$rec['name']} ({$rec['slug']})"), $tax, $args);
            }
        }
        if (is_wp_error($res)) {
            $this->warn("term {$tax}/{$rec['slug']}: " . $res->get_error_message());
            return;
        }
        $id = (int) $res['term_id'];
        $this->term_map[$old] = $id;
        $this->term_by_key[$tax]["{$parent}|{$rec['slug']}"] = $id;
        $this->tt_of[$tax][$id] = (int) $res['term_taxonomy_id'];
        if ($source) {
            $this->term_by_source[$tax][$source] = $id;
        }
        $rows = [];
        foreach ($rec['meta'] as [$k, $v]) {
            if ($this->keep_text && $id && in_array($k, self::TEXT_KEYS, true)) {
                continue;
            }
            if ($k === 'logo') {
                $v = (string) ($this->post_map[(int) $v] ?? 0);
                if ($v === '0') {
                    $this->warn("term {$tax}/{$rec['slug']}: logo attachment not in the pack - skipped");
                    continue;
                }
            }
            $rows[] = [$k, $v];
        }
        $this->write_meta('term', $id, $rows, [], $this->keep_text ? self::TEXT_KEYS : []);
    }

    private function import_post_row(array $rec, bool $dry): void
    {
        global $wpdb;
        $type = $rec['post_type'];
        $source = '';
        foreach ($rec['meta'] as [$k, $v]) {
            if ($k === self::SOURCE_ID && $v !== '') {
                $source = $v;
            }
        }
        $id = $source ? ($this->post_by_source[$type][$source] ?? 0) : 0;
        if (!$id) {
            $id = $this->post_by_slug[$type][$rec['name']] ?? 0;
        }
        $keep = $id && isset($this->keep_applied[$id]);
        $this->bump($id ? "{$type}_existing" : "{$type}_new");
        if ($keep) {
            $this->bump('descriptions_kept');
        }
        if ($dry) {
            $this->post_map[(int) $rec['id']] = $id ?: -1;
            return;
        }
        $now = current_time('mysql');
        $row = [
            'post_type' => $type, 'post_status' => $rec['status'], 'post_name' => $rec['name'],
            'post_date' => $rec['date'], 'post_date_gmt' => $rec['date_gmt'], 'post_modified' => $now, 'post_modified_gmt' => get_gmt_from_date($now),
            'menu_order' => (int) $rec['menu_order'],
        ];
        $keep_text = $id && $this->keep_text;
        if (!$keep_text) {
            $row['post_title'] = $rec['title'];
        }
        if (!$keep && !$keep_text) {
            $row['post_content'] = (string) $rec['content'];
            $row['post_excerpt'] = (string) $rec['excerpt'];
        }
        if ($keep_text) {
            $this->bump('texts_kept');
        }
        if ($id) {
            $wpdb->update($wpdb->posts, $row, ['ID' => $id]);
            clean_post_cache($id);
        } else {
            $id = $this->insert_post($row + ['post_title' => $rec['title'], 'post_content' => (string) $rec['content'], 'post_excerpt' => (string) $rec['excerpt']]);
            if (!$id) {
                $this->warn("{$type} {$rec['name']}: " . $wpdb->last_error);
                return;
            }
            $this->new_posts[] = $id;
        }
        $this->post_map[(int) $rec['id']] = $id;
        $this->post_by_slug[$type][$rec['name']] = $id;
        if ($source) {
            $this->post_by_source[$type][$source] = $id;
        }
    }

    /** Insert a post row directly (no save hooks); returns the new id or 0. */
    private function insert_post(array $row): int
    {
        global $wpdb;
        $row += [
            'post_author' => get_current_user_id() ?: 1, 'post_content' => '', 'post_excerpt' => '', 'comment_status' => 'closed', 'ping_status' => 'closed',
            'post_parent' => 0, 'post_mime_type' => '', 'guid' => '', 'to_ping' => '', 'pinged' => '', 'post_content_filtered' => '', 'post_password' => '',
        ];
        if (!$wpdb->insert($wpdb->posts, $row)) {
            return 0;
        }
        $id = (int) $wpdb->insert_id;
        if ($row['guid'] === '') {
            $wpdb->update($wpdb->posts, ['guid' => add_query_arg(['post_type' => $row['post_type'], 'p' => $id], home_url('/'))], ['ID' => $id]);
        }
        return $id;
    }

    private function import_post_meta(array $rec, bool $dry): void
    {
        global $wpdb;
        $id = $this->post_map[(int) $rec['id']] ?? 0;
        if ($dry || $id <= 0) {
            return;
        }
        $rows = [];
        $existing = !in_array($id, $this->new_posts, true);
        foreach ($rec['meta'] as [$k, $v]) {
            if (in_array($k, self::SKIP_KEYS, true) || ($this->keep_text && $existing && in_array($k, self::TEXT_KEYS, true))) {
                continue;
            }
            if (in_array($k, self::LIST_KEYS, true)) {
                $v = $this->remap_list($v, "{$rec['post_type']} {$rec['name']} {$k}");
            } elseif (in_array($k, self::INT_KEYS, true)) {
                $new = $this->post_map[(int) $v] ?? 0;
                if ($new <= 0) {
                    if ((int) $v) {
                        $this->warn("{$rec['post_type']} {$rec['name']}: {$k} {$v} not in the pack - skipped");
                    }
                    continue;
                }
                $v = (string) $new;
            }
            $rows[] = [$k, $v];
        }
        $this->write_meta('post', $id, $rows, array_merge(self::LIST_KEYS, self::INT_KEYS), $this->keep_text && $existing ? self::TEXT_KEYS : []);

        // term relationships of our taxonomies: replaced to mirror the pack (counts recomputed in finish())
        $taxes = "'" . implode("','", array_keys(car_directory_taxonomies())) . "'";
        $wpdb->query($wpdb->prepare("DELETE tr FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
            WHERE tr.object_id = %d AND tt.taxonomy IN ({$taxes})", $id));
        $values = [];
        foreach ((array) $rec['terms'] as $tax => $olds) {
            foreach ((array) $olds as $old) {
                $new = $this->term_map[(int) $old] ?? 0;
                $tt = $new > 0 ? ($this->tt_of[$tax][$new] ?? 0) : 0;
                $tt ? $values[] = $wpdb->prepare('(%d, %d, 0)', $id, $tt) : $this->warn("{$rec['post_type']} {$rec['name']}: {$tax} term {$old} not in the pack");
            }
        }
        if ($values) {
            $wpdb->query("INSERT IGNORE INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id, term_order) VALUES " . implode(',', $values));
        }
        clean_post_cache($id);
    }

    private function remap_list(string $value, string $what): string
    {
        $list = maybe_unserialize($value);
        if (!is_array($list)) {
            return $value;
        }
        $out = [];
        foreach ($list as $old) {
            $new = $this->post_map[(int) $old] ?? 0;
            $new > 0 ? $out[] = (string) $new : $this->warn("{$what}: id {$old} not in the pack - dropped");
        }
        return serialize($out); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- ACF list format
    }

    /** Replace the pack's rows on one object (its keys, `_car_*` keys and $also, minus the protected ones); other meta stays. */
    private function write_meta(string $type, int $id, array $rows, array $also, array $protect = []): void
    {
        global $wpdb;
        $table = $type === 'post' ? $wpdb->postmeta : $wpdb->termmeta;
        $col = $type === 'post' ? 'post_id' : 'term_id';
        $keys = array_fill_keys(array_merge(array_column($rows, 0), $also), true);
        $protect = array_merge(self::PROTECTED_KEYS, $protect);
        $drop = [];
        $kept = []; // protected keys the object already has: the pack's copy is not written on top
        foreach ($wpdb->get_results($wpdb->prepare("SELECT meta_id, meta_key FROM {$table} WHERE {$col} = %d", $id)) as $m) {
            if (in_array($m->meta_key, $protect, true)) {
                $kept[$m->meta_key] = true;
            } elseif (isset($keys[$m->meta_key]) || str_starts_with($m->meta_key, '_car_')) {
                $drop[] = (int) $m->meta_id;
            }
        }
        $rows = array_values(array_filter($rows, fn($row) => !isset($kept[$row[0]])));
        foreach (array_chunk($drop, 500) as $chunk) {
            $wpdb->query("DELETE FROM {$table} WHERE meta_id IN (" . implode(',', $chunk) . ')');
        }
        $values = [];
        foreach ($rows as [$k, $v]) {
            $values[] = $wpdb->prepare('(%d, %s, %s)', $id, $k, $v);
        }
        foreach (array_chunk($values, 400) as $chunk) {
            $wpdb->query("INSERT INTO {$table} ({$col}, meta_key, meta_value) VALUES " . implode(',', $chunk));
        }
        wp_cache_delete($id, $type === 'post' ? 'post_meta' : 'term_meta');
    }

    private function finish(): void
    {
        wp_suspend_cache_invalidation(false);
        wp_defer_term_counting(false);
        $t0 = microtime(true);
        $this->line('[Pack] finishing 1/3: term counts...');
        foreach (array_keys(car_directory_taxonomies()) as $tax) {
            clean_taxonomy_cache($tax);
            $ids = get_terms(['taxonomy' => $tax, 'hide_empty' => false, 'fields' => 'tt_ids']);
            if ($ids && !is_wp_error($ids)) {
                wp_update_term_count_now($ids, $tax);
            }
        }
        $this->line(sprintf('[Pack] finishing 2/3: nested URLs... (counts took %.0f s)', microtime(true) - $t0));
        $t0 = microtime(true);
        $paths = build_car_paths();
        $this->line(sprintf('[Pack] nested URLs: %d written, %d unchanged, %d without parent, %d duplicates (%.0f s)',
            $paths['written'], $paths['unchanged'], count($paths['missing']), count($paths['duplicates']), microtime(true) - $t0));
        foreach (array_slice(array_merge($paths['missing'], $paths['duplicates']), 0, 20) as $w) {
            $this->warn('path: ' . $w);
        }
        $this->line('[Pack] finishing 3/3: cache and rewrite rules...');
        wp_cache_flush();
        flush_rewrite_rules(false);
    }

    // ===================================================================== apply-keep

    /**
     * Restore the old site's descriptions / SEO fields onto the matching new cars (by source
     * URL) and write the 301 map (old path -> new path; unmatched cars go to their model, make or the directory).
     *
     * ## OPTIONS
     *
     * <file>
     * : The keep-file written by `wp cars purge`.
     *
     * [--redirects-out=<path>]
     * : Write the redirect map as CSV (old_path,new_path,kind).
     *
     * [--dry-run]
     * : Match and report only.
     *
     * @subcommand apply-keep
     * @when after_wp_load
     */
    public function apply_keep($args, $assoc): void
    {
        global $wpdb;
        $this->unbuffer();
        $file = $args[0];
        $dry = (bool) WP_CLI\Utils\get_flag_value($assoc, 'dry-run', false);
        $out = $assoc['redirects-out'] ?? '';
        if (!is_readable($file)) {
            WP_CLI::error("{$file} not found");
        }
        if (!$dry) {
            $this->speed_up();
        }
        $this->line('[Keep] reading the new cars by source URL and the make / model terms...');
        $by_url = [];
        foreach ($wpdb->get_results($wpdb->prepare("SELECT p.ID, p.post_type, m.meta_value FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
            WHERE p.post_type IN ('car', 'car_model_year') AND p.post_status = 'publish'", self::SOURCE_URL)) as $r) {
            $by_url[$this->url_key($r->meta_value)] = [$r->post_type, (int) $r->ID];
        }
        $by_title = []; // unique car titles only
        $dupes = [];
        foreach ($wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'car' AND post_status = 'publish'") as $r) {
            $k = $this->title_key($r->post_title);
            isset($by_title[$k]) ? $dupes[$k] = true : $by_title[$k] = ['car', (int) $r->ID];
        }
        $by_title = array_diff_key($by_title, $dupes);
        $makes = [];
        $models = [];
        foreach (get_terms(['taxonomy' => 'car_make', 'hide_empty' => false]) as $t) {
            $t->parent ? $models[$t->slug] = $t : $makes[$t->slug] = $t;
        }
        $archive = (string) wp_parse_url(get_post_type_archive_link('car'), PHP_URL_PATH);
        $redirects = [];
        $this->begin($dry);
        $n = 0;
        foreach ($this->ndjson($file) as $rec) {
            if ($rec['type'] !== 'car') {
                continue;
            }
            $hit = $rec['source_url'] ? $by_url[$this->url_key($rec['source_url'])] ?? null : null;
            if (!$hit && isset($by_title[$this->title_key((string) $rec['title'])])) { // the source moved the page: same title
                $hit = $by_title[$this->title_key((string) $rec['title'])];
                $this->bump('matched_by_title');
            }
            $kind = 'unmatched';
            $to = $archive;
            if ($hit) {
                [$type, $id] = $hit;
                $kind = $type;
                $to = (string) wp_parse_url(get_permalink($id), PHP_URL_PATH);
                if ($type === 'car' || $type === 'car_model_year') { // an old car whose source page was a model year: its text goes there
                    $this->bump($type === 'car' ? 'applied' : 'applied_model_year');
                    if (!$dry) {
                        if (trim((string) $rec['content']) !== '') {
                            $wpdb->update($wpdb->posts, ['post_content' => $rec['content'], 'post_excerpt' => (string) $rec['excerpt']], ['ID' => $id]);
                            clean_post_cache($id);
                        }
                        foreach ((array) ($rec['meta'] ?? []) as $k => $v) {
                            update_post_meta($id, $k, wp_slash($v));
                        }
                        update_post_meta($id, self::KEEP_FLAG, gmdate('c'));
                    }
                } else {
                    $this->bump('matched_model_year');
                }
            } elseif ($parent = $this->nearest_existing_parent((string) $rec['source_url'])) {
                // the trim is gone from the source: its model year (or model, make...) by the source URL, one level up at a time
                [$to, $kind] = $parent;
                $this->bump('unmatched_' . $kind);
            } else {
                foreach (['car_model' => $models, 'car_maker' => $makes] as $tax => $pool) {
                    foreach ((array) ($rec['terms'][$tax] ?? []) as $old) {
                        [$slug, $name] = is_array($old) ? $old + ['', ''] : [$old, ''];
                        $term = $pool[$slug] ?? $this->term_by_name($pool, $name);
                        if ($term) {
                            $to = (string) wp_parse_url(get_term_link($term), PHP_URL_PATH);
                            $kind = $tax === 'car_model' ? 'model' : 'make';
                            break 2;
                        }
                    }
                }
                $this->bump('unmatched_' . $kind);
            }
            if ((string) $rec['path'] !== '' && ($rec['status'] ?? 'publish') === 'publish') {
                $redirects[] = [$rec['path'], $to, $kind];
            }
            $this->tick(++$n, $dry, $dry ? '[Keep DRY RUN]' : '[Keep]', 'cars');
        }
        $this->commit($dry);
        if ($out) {
            $fh = fopen($out, 'wb');
            if (!$fh) {
                WP_CLI::error("cannot write {$out}");
            }
            fwrite($fh, "old_path,new_path,kind\n");
            foreach ($redirects as $r) {
                fputcsv($fh, $r);
            }
            fclose($fh);
        }
        $map = [];
        foreach ($redirects as [$from, $to]) {
            $map[$from] = $to;
        }
        if (!$dry || $out) { // the map is not content: a dry run that writes the CSV also installs it
            update_option('car_redirects', $map, false); // inc/cars/directory.php answers 301 from it
        }
        $this->stats['redirects'] = count($redirects);
        if (!$dry) {
            wp_suspend_cache_invalidation(false);
            wp_cache_flush();
        }
        $this->report($dry ? 'DRY RUN' : 'COMPLETED', 'apply-keep', '');
    }

    /** @return array{0: string, 1: string}|null [path of the closest page above the source URL, its kind] */
    private function nearest_existing_parent(string $source_url): ?array
    {
        $parts = explode('/', trim(strtolower(rawurldecode((string) wp_parse_url($source_url, PHP_URL_PATH))), '/'));
        for (array_pop($parts); count($parts) >= 2; array_pop($parts)) {
            $hit = lookup_car_path(implode('/', $parts));
            if (!$hit) {
                continue;
            }
            $url = $hit['kind'] === 'post' ? get_permalink($hit['id']) : get_term_link($hit['id'], $hit['type']);
            if (!is_string($url)) {
                continue;
            }
            $kind = $hit['kind'] === 'post' ? $hit['type'] : (get_term($hit['id'])->parent ? 'model' : ($hit['type'] === 'car_make' ? 'make' : $hit['type']));
            return [(string) wp_parse_url($url, PHP_URL_PATH), $kind];
        }
        return null;
    }

    private function term_by_name(array $pool, string $name): ?WP_Term
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        foreach ($pool as $term) {
            if (trim($term->name) === $name) {
                return $term;
            }
        }
        return null;
    }

    private function title_key(string $title): string
    {
        return preg_replace('/\s+/u', ' ', trim(mb_strtolower($title)));
    }

    private function url_key(string $url): string
    {
        return rtrim(strtolower(rawurldecode(preg_replace('#^https?://(www\.)?#', '', trim($url)))), '/');
    }

    // ===================================================================== export-pack

    /**
     * Export the car directory (or part of it) as a pack for `import-pack` on another site: model years,
     * trims, every meta row, the terms they use, the attachments they reference. Same format as the
     * staging site's export.
     *
     * ## OPTIONS
     *
     * [--out=<path>]
     * : Pack file to write (default: <WordPress root>/tmp/car-pack-<time>.ndjson).
     *
     * [--make=<slugs>]
     * : Only these makes (car_make slugs, comma-separated).
     *
     * [--origin=<slugs>]
     * : Only these origins (car_origin slugs).
     *
     * [--path=<prefixes>]
     * : Only objects whose nested path starts with these prefixes.
     *
     * [--media=<which>]
     * : Attachments to include: all, main (featured images + logos/icons), logos (brand logos and icons only), none.
     * ---
     * default: all
     * options:
     *   - all
     *   - main
     *   - logos
     *   - none
     * ---
     *
     * [--zip=<path>]
     * : Also write the image files of the selected attachments (all sizes) into this zip, laid out like the uploads folder.
     *
     * ## EXAMPLES
     *
     *     wp cars export-pack --media=logos --out=tmp/pack.ndjson --zip=tmp/pack-images.zip
     *
     * @subcommand export-pack
     * @when after_wp_load
     */
    public function export_pack($args, $assoc): void
    {
        global $wpdb;
        $this->unbuffer();
        $opt = ['out' => $assoc['out'] ?? '', 'make' => $assoc['make'] ?? '', 'origin' => $assoc['origin'] ?? '', 'path' => $assoc['path'] ?? '',
            'media' => $assoc['media'] ?? 'all', 'zip' => $assoc['zip'] ?? ''];
        $file = $opt['out'] ?: $this->tmp_dir() . 'car-pack-' . gmdate('Ymd-His') . '.ndjson';
        $fh = fopen($file, 'wb');
        if (!$fh) {
            WP_CLI::error("cannot write {$file}");
        }
        $prefixes = $this->export_prefixes($opt);
        $posts = $this->export_posts($prefixes);
        $terms = $this->export_terms(array_keys($posts), (bool) $prefixes);
        $atts = $opt['media'] === 'none' ? [] : $this->export_attachments($opt['media'] === 'logos' ? [] : array_keys($posts), array_column($terms, 'term_id'), $opt['media'] === 'main');
        $this->line(sprintf('[Pack] selection: %s | %d model years, %d cars, %d terms, %d attachments', $prefixes ? implode(', ', $prefixes) : 'everything',
            count(array_keys($posts, 'car_model_year', true)), count(array_keys($posts, 'car', true)), count($terms), count($atts)));
        $put = function (array $record) use ($fh): void {
            fwrite($fh, wp_json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
        };
        $put(['type' => 'header', 'site' => home_url(), 'exported' => gmdate('c'), 'selection' => $prefixes ?: 'all', 'meta_prefix' => '_car_', 'posts' => count($posts), 'terms' => count($terms), 'attachments' => count($atts)]);

        $upload = wp_upload_dir();
        $files = [];
        $written = 0;
        foreach (array_chunk($atts, 200) as $chunk) {
            $written += count($chunk);
            $this->progress('[Pack]', 'attachments written', $written, count($atts), 1000);
            $meta = $this->export_meta_rows($wpdb->postmeta, 'post_id', $chunk);
            foreach ($wpdb->get_results("SELECT ID, post_parent, post_title, post_name, post_mime_type, post_date, post_date_gmt, post_excerpt, post_content
                FROM {$wpdb->posts} WHERE ID IN (" . implode(',', $chunk) . ')') as $a) {
                $rows = $meta[(int) $a->ID] ?? [];
                $file_ = '';
                $sizes = [];
                foreach ($rows as [$k, $v]) {
                    if ($k === '_wp_attached_file') {
                        $file_ = $v;
                    } elseif ($k === '_wp_attachment_metadata') {
                        $m = maybe_unserialize($v);
                        foreach ((array) ($m['sizes'] ?? []) as $sz) {
                            if (!empty($sz['file'])) {
                                $sizes[] = $sz['file'];
                            }
                        }
                    }
                }
                $dir = $file_ ? dirname($file_) : '';
                $paths = array_merge($file_ ? [$file_] : [], array_map(fn($sz) => ($dir === '.' || $dir === '' ? '' : $dir . '/') . $sz, $sizes));
                foreach ($paths as $pth) {
                    $files[$pth] = true;
                }
                $put(['type' => 'attachment', 'id' => (int) $a->ID, 'parent' => (int) $a->post_parent, 'title' => $a->post_title, 'name' => $a->post_name,
                    'mime' => $a->post_mime_type, 'date' => $a->post_date, 'date_gmt' => $a->post_date_gmt, 'excerpt' => $a->post_excerpt, 'content' => $a->post_content,
                    'file' => $file_, 'sizes' => array_slice($paths, $file_ ? 1 : 0), 'exists' => $file_ && is_file($upload['basedir'] . '/' . $file_), 'meta' => $rows]);
            }
        }
        $this->line(sprintf('[Pack] writing %d terms...', count($terms)));
        foreach (array_chunk($terms, 200) as $chunk) {
            $meta = $this->export_meta_rows($wpdb->termmeta, 'term_id', array_column($chunk, 'term_id'));
            foreach ($chunk as $t) {
                $put(['type' => 'term', 'id' => (int) $t['term_id'], 'taxonomy' => $t['taxonomy'], 'name' => $t['name'], 'slug' => $t['slug'],
                    'description' => $t['description'], 'parent' => (int) $t['parent'], 'meta' => $meta[(int) $t['term_id']] ?? []]);
            }
        }
        $taxes = "'" . implode("','", array_keys(car_directory_taxonomies())) . "'";
        $n = 0;
        foreach (array_chunk(array_keys($posts), 200) as $chunk) {
            $in = implode(',', $chunk);
            $meta = $this->export_meta_rows($wpdb->postmeta, 'post_id', $chunk);
            $rel = [];
            foreach ($wpdb->get_results("SELECT tr.object_id, tt.taxonomy, tt.term_id FROM {$wpdb->term_relationships} tr
                JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tr.object_id IN ({$in}) AND tt.taxonomy IN ({$taxes})") as $r) {
                $rel[(int) $r->object_id][$r->taxonomy][] = (int) $r->term_id;
            }
            foreach ($wpdb->get_results("SELECT ID, post_type, post_name, post_title, post_content, post_excerpt, post_status, post_date, post_date_gmt,
                post_modified, post_modified_gmt, menu_order FROM {$wpdb->posts} WHERE ID IN ({$in})") as $p) {
                $rows = [];
                foreach ($meta[(int) $p->ID] ?? [] as $row) {
                    if (!in_array($row[0], self::SKIP_KEYS, true)) {
                        $rows[] = $row;
                    }
                }
                $put(['type' => 'post', 'id' => (int) $p->ID, 'post_type' => $p->post_type, 'name' => $p->post_name, 'title' => $p->post_title, 'content' => $p->post_content,
                    'excerpt' => $p->post_excerpt, 'status' => $p->post_status, 'date' => $p->post_date, 'date_gmt' => $p->post_date_gmt, 'modified' => $p->post_modified,
                    'modified_gmt' => $p->post_modified_gmt, 'menu_order' => (int) $p->menu_order, 'meta' => $rows, 'terms' => $rel[(int) $p->ID] ?? []]);
                ++$n;
            }
            $this->progress('[Pack]', 'posts written', $n, count($posts), 1000);
        }
        $put(['type' => 'footer', 'posts' => $n]);
        fclose($fh);
        $zipped = $opt['zip'] ? $this->zip_files($opt['zip'], array_keys($files), $upload['basedir']) : 0;
        $this->line(sprintf('[COMPLETED] export-pack | %d posts | %d terms | %d attachments | %.1f MB | %s%s | next (on the other site): wp cars import-pack %s --media-dir=<the unzipped folder>',
            count($posts), count($terms), count($atts), filesize($file) / 1048576, $file,
            $opt['zip'] ? sprintf(' | zip: %d files, %.1f MB, %s', $zipped, (is_file($opt['zip']) ? filesize($opt['zip']) : 0) / 1048576, $opt['zip']) : '', basename($file)));
    }

    private function export_prefixes(array $opt): array
    {
        $out = [];
        foreach (array_filter(array_map('trim', explode(',', (string) $opt['origin']))) as $slug) {
            $t = get_term_by('slug', $slug, 'car_origin');
            $t ? $out[] = (string) get_term_meta($t->term_id, CAR_PATH_META, true) : WP_CLI::error("origin '{$slug}' not found");
        }
        foreach (array_filter(array_map('trim', explode(',', (string) $opt['make']))) as $slug) {
            $found = get_terms(['taxonomy' => 'car_make', 'slug' => $slug, 'parent' => 0, 'hide_empty' => false]);
            $found && !is_wp_error($found) ? $out[] = (string) get_term_meta($found[0]->term_id, CAR_PATH_META, true) : WP_CLI::error("make '{$slug}' not found");
        }
        foreach (array_filter(array_map('trim', explode(',', (string) $opt['path']))) as $pth) {
            $out[] = trim(strtolower($pth), '/');
        }
        return array_values(array_unique(array_filter($out)));
    }

    /** @return array<int, string> ID => post_type */
    private function export_posts(array $prefixes): array
    {
        global $wpdb;
        $where = "p.post_type IN ('car', 'car_model_year') AND p.post_status NOT IN ('auto-draft', 'trash')";
        if ($prefixes) {
            $conds = [];
            foreach ($prefixes as $pre) {
                $conds[] = $wpdb->prepare('(m.meta_value = %s OR m.meta_value LIKE %s)', $pre, $wpdb->esc_like($pre) . '/%');
            }
            $sql = "SELECT p.ID, p.post_type FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '" . CAR_PATH_META . "'
                WHERE {$where} AND (" . implode(' OR ', $conds) . ') ORDER BY p.ID';
        } else {
            $sql = "SELECT p.ID, p.post_type FROM {$wpdb->posts} p WHERE {$where} ORDER BY p.ID";
        }
        $out = [];
        foreach ($wpdb->get_results($sql) as $r) {
            $out[(int) $r->ID] = $r->post_type;
        }
        return $out;
    }

    /** Terms of the directory's taxonomies used by the posts (plus ancestors and every body type), or all of them; parents first. */
    private function export_terms(array $post_ids, bool $subset): array
    {
        global $wpdb;
        $taxes = "'" . implode("','", array_keys(car_directory_taxonomies())) . "'";
        $all = [];
        foreach ($wpdb->get_results("SELECT t.term_id, t.name, t.slug, tt.taxonomy, tt.description, tt.parent FROM {$wpdb->term_taxonomy} tt
            JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tt.taxonomy IN ({$taxes})", ARRAY_A) as $t) {
            $all[(int) $t['term_id']] = $t;
        }
        $want = [];
        if ($subset) {
            foreach (array_chunk($post_ids, 500) as $chunk) {
                foreach ($wpdb->get_col("SELECT DISTINCT tt.term_id FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                    WHERE tr.object_id IN (" . implode(',', $chunk) . ") AND tt.taxonomy IN ({$taxes})") as $id) {
                    for ($id = (int) $id; $id && isset($all[$id]) && !isset($want[$id]); $id = (int) $all[$id]['parent']) {
                        $want[$id] = true;
                    }
                }
            }
            foreach ($all as $id => $t) {
                if ($t['taxonomy'] === 'body_type') {
                    $want[$id] = true;
                }
            }
        } else {
            $want = array_fill_keys(array_keys($all), true);
        }
        $depth = function (int $id) use ($all, &$depth): int {
            $par = (int) ($all[$id]['parent'] ?? 0);
            return $par && isset($all[$par]) ? 1 + $depth($par) : 0;
        };
        $terms = array_values(array_intersect_key($all, $want));
        usort($terms, fn($a, $b) => $depth((int) $a['term_id']) <=> $depth((int) $b['term_id']) ?: (int) $a['term_id'] <=> (int) $b['term_id']);
        return $terms;
    }

    private function export_attachments(array $post_ids, array $term_ids, bool $main_only): array
    {
        global $wpdb;
        $ids = [];
        foreach (array_chunk($post_ids, 500) as $chunk) {
            $keys = $main_only ? "'_thumbnail_id'" : "'_thumbnail_id', 'gallery', 'design_wheel_designs'";
            foreach ($wpdb->get_results("SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN (" . implode(',', $chunk) . ") AND meta_key IN ({$keys})") as $r) {
                foreach ($r->meta_key === '_thumbnail_id' ? [$r->meta_value] : (array) maybe_unserialize($r->meta_value) as $v) {
                    if ((int) $v) {
                        $ids[(int) $v] = true;
                    }
                }
            }
        }
        foreach (array_chunk($term_ids, 500) as $chunk) {
            foreach ($wpdb->get_col("SELECT meta_value FROM {$wpdb->termmeta} WHERE meta_key = 'logo' AND term_id IN (" . implode(',', $chunk) . ')') as $v) {
                if ((int) $v) {
                    $ids[(int) $v] = true;
                }
            }
        }
        $out = [];
        foreach (array_chunk(array_keys($ids), 500) as $chunk) {
            $out = array_merge($out, array_map('intval', $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND ID IN (" . implode(',', $chunk) . ')')));
        }
        sort($out);
        return $out;
    }

    /** @return array<int, array<int, array{0: string, 1: string}>> */
    private function export_meta_rows(string $table, string $col, array $ids): array
    {
        global $wpdb;
        $out = [];
        if (!$ids) {
            return $out;
        }
        foreach ($wpdb->get_results("SELECT {$col} AS oid, meta_key, meta_value FROM {$table} WHERE {$col} IN (" . implode(',', array_map('intval', $ids)) . ') ORDER BY meta_id') as $r) {
            $out[(int) $r->oid][] = [$r->meta_key, (string) $r->meta_value];
        }
        return $out;
    }

    private function zip_files(string $zip_file, array $paths, string $base): int
    {
        if (!class_exists('ZipArchive')) {
            WP_CLI::error('the PHP zip extension is missing - leave out --zip and copy the files another way');
        }
        $zip = new ZipArchive();
        if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            WP_CLI::error("cannot write {$zip_file}");
        }
        $n = 0;
        $bytes = 0;
        $this->line(sprintf('[Pack] zip: %d files to add...', count($paths)));
        foreach ($paths as $i => $pth) {
            if (is_file("{$base}/{$pth}")) {
                $zip->addFile("{$base}/{$pth}", $pth);
                $zip->setCompressionName($pth, ZipArchive::CM_STORE);
                $bytes += (int) filesize("{$base}/{$pth}");
                ++$n;
            }
            $this->progress('[Pack]', 'zip', $i + 1, count($paths), 500, sprintf(' files, %.0f MB', $bytes / 1048576));
        }
        $this->line('[Pack] zip: closing the archive (this can take a while for a big one)...');
        $zip->close();
        return $n;
    }

    // ===================================================================== status

    /**
     * Counts of the car directory on this site (compare source and target after a move).
     *
     * @when after_wp_load
     */
    public function status($args, $assoc): void
    {
        global $wpdb;
        $this->unbuffer();
        $q = fn(string $sql) => (int) $wpdb->get_var($sql);
        $this->line(sprintf('cars: %d published (%d other) | model years: %d | cars without a nested path: %d | with the old live description: %d',
            $q("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'car' AND post_status = 'publish'"),
            $q("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'car' AND post_status NOT IN ('publish', 'auto-draft')"),
            $q("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'car_model_year' AND post_status = 'publish'"),
            $q("SELECT COUNT(*) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '" . CAR_PATH_META . "' WHERE p.post_type = 'car' AND p.post_status = 'publish' AND m.meta_id IS NULL"),
            $q("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '" . self::KEEP_FLAG . "'")));
        $parts = [];
        foreach (car_directory_taxonomies() as $tax => $def) {
            $parts[] = $tax . ' ' . $q($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $tax));
        }
        $this->line('terms: ' . implode(' | ', $parts));
        $this->line(sprintf('images: %d attachments from the source | cars with a featured image: %d | cars with a gallery: %d | term logos/icons: %d',
            $q("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '" . self::IMAGE_URL . "'"),
            $q("SELECT COUNT(*) FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id AND p.post_type = 'car' WHERE m.meta_key = '_thumbnail_id' AND m.meta_value <> '' AND m.meta_value <> '0'"),
            $q("SELECT COUNT(*) FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id AND p.post_type = 'car' WHERE m.meta_key = 'gallery' AND m.meta_value NOT IN ('', 'a:0:{}')"),
            $q("SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_key = 'logo' AND meta_value <> ''")));
        $this->line(sprintf('old-URL redirects: %d | data version: %s', count((array) get_option('car_redirects', [])), (string) get_option('car_directory_data_version', '-')));
    }

    // ===================================================================== paths

    /**
     * Rebuild the nested URLs (_car_path) of origins, makes, models, model years and cars, then flush rewrites.
     *
     * @when after_wp_load
     */
    public function paths($args, $assoc): void
    {
        $this->unbuffer();
        $this->line('[Paths] rebuilding the nested URLs of origins, makes, models, model years and cars...');
        $t0 = microtime(true);
        $r = build_car_paths();
        $this->line(sprintf('[Paths] built in %.0f s', microtime(true) - $t0));
        update_option('car_directory_data_version', (string) time());
        foreach (array_slice(array_merge($r['missing'], $r['duplicates']), 0, 20) as $w) {
            $this->line('    ' . $w);
        }
        flush_rewrite_rules(false);
        $this->line(sprintf('[COMPLETED] paths | written %d | unchanged %d | without parent %d | duplicates %d', $r['written'], $r['unchanged'], count($r['missing']), count($r['duplicates'])));
    }

    // ===================================================================== plumbing

    /** Pack meta keys carry the exporting site's prefix (header meta_prefix); here they are `_car_*`. */
    private function normalize_keys(array $rec): array
    {
        if ($this->pack_prefix === '_car_' || !isset($rec['meta']) || !is_array($rec['meta'])) {
            return $rec;
        }
        foreach ($rec['meta'] as $i => $row) {
            if (is_array($row) && isset($row[0]) && str_starts_with((string) $row[0], $this->pack_prefix)) {
                $rec['meta'][$i][0] = '_car_' . substr((string) $row[0], strlen($this->pack_prefix));
            }
        }
        return $rec;
    }

    private function ndjson(string $file): Generator
    {
        $fh = fopen($file, 'rb');
        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if ($line !== '') {
                yield json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            }
        }
        fclose($fh);
    }

    private function speed_up(): void
    {
        if (!defined('WP_IMPORTING')) {
            define('WP_IMPORTING', true);
        }
        wp_raise_memory_limit('admin');
        wp_defer_term_counting(true);
        wp_suspend_cache_invalidation(true);
        kses_remove_filters();
        remove_action('post_updated', 'wp_save_post_revision', 10);
        remove_action('do_pings', 'do_all_pings', 10);
    }

    private function begin(bool $dry): void
    {
        global $wpdb;
        if (!$dry) {
            $wpdb->query('START TRANSACTION');
        }
    }

    private function commit(bool $dry): void
    {
        global $wpdb;
        if (!$dry) {
            $wpdb->query('COMMIT');
            wp_cache_flush_runtime();
            $wpdb->queries = [];
        }
    }

    private function tick(int $n, bool $dry, string $tag, string $what): void
    {
        global $wpdb;
        if ($n % 200 === 0 && !$dry) {
            $wpdb->query('COMMIT');
            wp_cache_flush_runtime();
            $wpdb->queries = [];
            $wpdb->query('START TRANSACTION');
        }
        $files = array_intersect_key($this->stats, array_flip(['files_copied', 'files_downloaded', 'files_present', 'files_missing']));
        $extra = $files ? ' | files ' . implode(', ', array_map(fn($k, $v) => str_replace('files_', '', $k) . ' ' . number_format($v), array_keys($files), $files)) : '';
        $this->progress($tag, $what, $n, $this->pack_total, 1000, $extra);
    }

    /** One progress line every $every items or every 15 s, whichever comes first, and always on the last item. */
    private function progress(string $tag, string $what, int $done, int $total, int $every, string $extra = ''): void
    {
        $now = microtime(true);
        if ($done % $every !== 0 && $now - $this->last_line < 15 && $done !== $total) {
            return;
        }
        $this->last_line = $now;
        $this->line(sprintf('%s %s: %s / %s%s', $tag, $what, number_format($done), $total ? number_format($total) : '?', $extra));
    }

    private function report(string $state, string $what, string $next): void
    {
        $parts = [];
        foreach ($this->stats as $k => $v) {
            $parts[] = str_replace('_', ' ', $k) . ' ' . number_format($v);
        }
        if ($this->warnings) {
            $file = $this->tmp_dir() . "cars-{$what}-warnings.txt";
            file_put_contents($file, implode("\n", $this->warnings) . "\n");
            $this->line(sprintf('[%s] %d warnings -> %s (first 10 below)', $what, count($this->warnings), $file));
            foreach (array_slice($this->warnings, 0, 10) as $w) {
                $this->line('    ' . $w);
            }
        }
        $this->line("[{$state}] {$what} | " . ($parts ? implode(' | ', $parts) : 'nothing to do') . ($next ? " | {$next}" : ''));
    }

    private function tmp_dir(): string
    {
        $dir = trailingslashit(ABSPATH) . 'tmp/';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        return $dir;
    }

    private function unbuffer(): void
    {
        while (ob_get_level()) {
            ob_end_flush();
        }
    }

    private function line(string $text): void
    {
        WP_CLI::line('[' . gmdate('Y-m-d H:i:s') . '] ' . $text);
    }

    private function bump(string $k): void
    {
        $this->stats[$k] = ($this->stats[$k] ?? 0) + 1;
    }

    private function warn(string $msg): void
    {
        $this->warnings[] = $msg;
    }
}

WP_CLI::add_command('cars', 'Car_Directory_CLI');
