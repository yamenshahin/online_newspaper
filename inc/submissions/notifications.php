<?php
/**
 * Entity submissions: e-mails to the submitter and the clerk's daily digest.
 *
 * approval.php fires `submission_approved` ($term_id, $post_id) when a request
 * becomes a published page and `submission_rejected` ($post_id) when one is
 * trashed without a result. The handlers below mail the request's author in
 * plain text: UTF-8, "\n" line breaks, nothing escaped, entities decoded.
 *
 * The digest is a daily WP-Cron event (`submissions_daily_digest`, 06:00 UTC =
 * 09:00 KSA) that mails admin_email the requests that went pending since the
 * previous digest. The window is kept in site time: the query compares the
 * local `post_date` column and `submissions_digest_last` stores
 * current_time('mysql'). Requests created through the form carry a real GMT
 * date (form.php pins it so clerk saves never move post_date); a pending post
 * created any other way may still float (post_date_gmt 0000-00-00), and the
 * local-time comparison is valid for both. Edit links are built with
 * admin_url(): cron runs without a user, for whom get_edit_post_link()
 * returns null.
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sends a plain-text UTF-8 mail: false for an unusable address, else wp_mail()'s result.
 */
function notify_submission_mail(string $to, string $subject, string $body): bool
{
    if (!is_email($to)) {
        return false;
    }

    return (bool) wp_mail($to, $subject, $body, ['Content-Type: text/plain; charset=UTF-8']);
}

/**
 * The author of a request, when the post is a request and the user still
 * exists with a usable e-mail address; null otherwise (nothing is sent).
 */
function notify_submission_recipient(int $post_id): ?WP_User
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== SUBMISSION_POST_TYPE) {
        return null;
    }

    $user = get_userdata((int) $post->post_author);
    if (!$user instanceof WP_User || !is_email((string) $user->user_email)) {
        return null;
    }

    return $user;
}

/**
 * Display name for a plain-text mail: entities decoded, the login when empty.
 */
function notify_submission_display_name(WP_User $user): string
{
    $name = trim(wp_specialchars_decode((string) $user->display_name, ENT_QUOTES));

    return $name !== '' ? $name : (string) $user->user_login;
}

/**
 * `submission_approved`: tells the submitter that the page is live.
 */
function notify_submission_approved(int $term_id, int $post_id): void
{
    $user = notify_submission_recipient($post_id);
    if (!$user) {
        return;
    }

    $name     = get_submission_name($post_id);
    $blogname = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
    $link     = get_term_link($term_id);

    $lines = [
        /* translators: %s: submitter display name */
        sprintf(__('Hello %s,', 'hello-elementor-child'), notify_submission_display_name($user)),
        /* translators: 1: request name, 2: site name */
        sprintf(__('Your request "%1$s" was published on %2$s.', 'hello-elementor-child'), $name, $blogname),
    ];
    if (!is_wp_error($link) && (string) $link !== '') {
        $lines[] = __('Page link:', 'hello-elementor-child');
        $lines[] = (string) $link;
    }
    $lines[] = __('Thank you for your contribution.', 'hello-elementor-child');
    /* translators: %s: site name */
    $lines[] = sprintf(__('— %s', 'hello-elementor-child'), $blogname);

    notify_submission_mail(
        (string) $user->user_email,
        /* translators: %s: request name */
        sprintf(__('Your request was published: %s', 'hello-elementor-child'), $name),
        implode("\n", $lines)
    );
}
add_action('submission_approved', 'notify_submission_approved', 10, 2);

/**
 * `submission_rejected`: sends the clerk's reason (the raw `rejection_reason`
 * meta) or a generic line when the request was trashed without one.
 */
function notify_submission_rejected(int $post_id): void
{
    $user = notify_submission_recipient($post_id);
    if (!$user) {
        return;
    }

    $name     = get_submission_name($post_id);
    $blogname = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
    $reason   = wp_specialchars_decode((string) get_post_meta($post_id, SUBMISSION_FIELD_REASON, true), ENT_QUOTES);
    $reason   = trim(str_replace(["\r\n", "\r"], "\n", $reason));
    $account  = get_submission_page_url('account');

    $lines = [
        /* translators: %s: submitter display name */
        sprintf(__('Hello %s,', 'hello-elementor-child'), notify_submission_display_name($user)),
        /* translators: %s: request name */
        sprintf(__('About your request "%s":', 'hello-elementor-child'), $name),
        $reason !== '' ? $reason : __('We could not publish your request.', 'hello-elementor-child'),
    ];
    if ($account !== '') {
        $lines[] = __('You can follow your requests or send a new one here:', 'hello-elementor-child');
        $lines[] = $account;
    }
    /* translators: %s: site name */
    $lines[] = sprintf(__('— %s', 'hello-elementor-child'), $blogname);

    notify_submission_mail(
        (string) $user->user_email,
        /* translators: %s: request name */
        sprintf(__('About your request: %s', 'hello-elementor-child'), $name),
        implode("\n", $lines)
    );
}
add_action('submission_rejected', 'notify_submission_rejected', 10, 1);

/**
 * Schedules the daily digest once; the first run is tomorrow 06:00 UTC
 * (09:00 KSA, the site runs on UTC).
 */
function submissions_schedule_daily_digest(): void
{
    if (wp_next_scheduled('submissions_daily_digest')) {
        return;
    }

    $first_run = strtotime('tomorrow 06:00 UTC');
    if ($first_run) {
        wp_schedule_event($first_run, 'daily', 'submissions_daily_digest');
    }
}
add_action('init', 'submissions_schedule_daily_digest');

/**
 * Removes the digest event when the site switches away from this theme.
 */
function submissions_clear_daily_digest(): void
{
    wp_clear_scheduled_hook('submissions_daily_digest');
}
add_action('switch_theme', 'submissions_clear_daily_digest');

/**
 * Mails admin_email the requests that went pending since the last digest
 * (the last 24 hours on the first run): one line per request, 50 at most,
 * then a count of the rest. Nothing is sent when there is nothing new.
 *
 * Window and bookmark are both site-local time (`post_date`, current_time):
 * valid whether or not a pending post carries a GMT date (form-created
 * requests do; form.php pins it). The bookmark is taken before the query
 * and advanced only when the mail went out, so nothing slips between runs.
 */
function submissions_send_daily_digest(): void
{
    $max_lines = 50;
    $now       = current_time('mysql');
    $last      = (string) get_option('submissions_digest_last');
    if ($last === '') {
        $last = wp_date('Y-m-d H:i:s', time() - DAY_IN_SECONDS);
    }

    $args = [
        'post_type'      => SUBMISSION_POST_TYPE,
        'post_status'    => 'pending',
        'posts_per_page' => 51,
        'orderby'        => 'date',
        'order'          => 'ASC',
        'no_found_rows'  => true,
        'date_query'     => [['column' => 'post_date', 'after' => $last, 'inclusive' => false]],
    ];
    $posts = (new WP_Query($args))->posts;
    $total = count($posts);
    if ($total === 0) {
        return;
    }
    if ($total > $max_lines) {
        // 51 rows only prove there are more: count them for the subject and the closing line.
        $ids   = (new WP_Query(array_merge($args, ['fields' => 'ids', 'posts_per_page' => -1])))->posts;
        $total = max($total, count($ids));
    }

    $lines = [];
    foreach (array_slice($posts, 0, $max_lines) as $post) {
        $post_id = (int) $post->ID;
        $label   = get_submission_taxonomy_label((string) get_post_meta($post_id, SUBMISSION_FIELD_TAXONOMY, true));
        $user    = get_userdata((int) $post->post_author);
        $lines[] = sprintf(
            /* translators: 1: request name, 2: type label, 3: submitter display name, 4: edit URL */
            __('• %1$s — %2$s — %3$s — %4$s', 'hello-elementor-child'),
            get_submission_name($post_id),
            $label !== '' ? $label : '—',
            $user instanceof WP_User ? notify_submission_display_name($user) : '—',
            admin_url('post.php?post=' . $post_id . '&action=edit')
        );
    }
    if ($total > $max_lines) {
        /* translators: %d: number of requests not listed */
        $lines[] = sprintf(__('and %d more', 'hello-elementor-child'), $total - $max_lines);
    }

    $blogname = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
    $sent     = notify_submission_mail(
        (string) get_option('admin_email'),
        /* translators: 1: number of new requests, 2: site name */
        sprintf(__('New add requests (%1$d) — %2$s', 'hello-elementor-child'), $total, $blogname),
        implode("\n", $lines)
    );
    if ($sent) {
        update_option('submissions_digest_last', $now, false);
    }
}
add_action('submissions_daily_digest', 'submissions_send_daily_digest');
