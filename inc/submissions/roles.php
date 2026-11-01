<?php
/**
 * Entity submissions: the «مُرسل» (submitter) role, wp-admin lockout and login redirect.
 *
 * People who only send requests never see wp-admin: the role carries `read` alone,
 * the admin bar is hidden for them, `admin_init` sends them to the account page
 * (profile.php included - passwords are reset through «نسيت كلمة المرور؟») and
 * `login_redirect` lands them on the account page after a password or Nextend
 * (Google) login. Nextend applies `login_redirect` only while no fixed redirect
 * URL is configured in its settings.
 *
 * The checks are role-agnostic (`!user_can('edit_posts')`), so a subscriber created
 * while the default role was still "subscriber" is treated exactly like a submitter.
 *
 * Uses SUBMISSION_ROLE and get_submission_page_url() from inc/submissions/registry.php.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the «مُرسل» role (read only) once.
 */
function submission_register_role(): void
{
    if (get_role(SUBMISSION_ROLE) === null) {
        add_role(SUBMISSION_ROLE, _x('Submitter', 'user role', 'hello-elementor-child'), ['read' => true]);
    }
}
add_action('init', 'submission_register_role');

/**
 * True when the user (given, or the current one) exists and cannot edit posts.
 *
 * Role-agnostic on purpose: covers `submitter` and `subscriber`, so a forgotten
 * default-role setting still keeps people out of wp-admin. Logged-out visitors
 * (ID 0) are not "submission-only users".
 */
function is_submission_only_user(?WP_User $user = null): bool
{
    if ($user === null) {
        $user = wp_get_current_user();
    }

    if (!($user instanceof WP_User) || (int) $user->ID <= 0 || !$user->exists()) {
        return false;
    }

    return !user_can($user, 'edit_posts');
}

/**
 * Shorthand for the current user.
 */
function current_user_is_submitter_only(): bool
{
    return is_submission_only_user(wp_get_current_user());
}

/**
 * No admin bar for submission-only users (`show_admin_bar` filter).
 *
 * @param bool $show Whether WordPress would show the bar.
 * @return bool
 */
function submission_hide_admin_bar($show)
{
    return current_user_is_submitter_only() ? false : $show;
}
add_filter('show_admin_bar', 'submission_hide_admin_bar');

/**
 * Send submission-only users from wp-admin (profile.php included) to the account page.
 *
 * Skipped for admin-ajax (ACF validates the front-end form through it) and for REST
 * requests; the fallback destination is the home page when the account page is missing.
 */
function submission_lock_dashboard(): void
{
    if (!current_user_is_submitter_only()) {
        return;
    }

    if (wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }

    wp_safe_redirect(get_submission_page_url('account') ?: home_url('/'));
    exit;
}
add_action('admin_init', 'submission_lock_dashboard');

/**
 * After login, submission-only users land on the account page (`login_redirect` filter).
 *
 * Core passes a WP_Error as $user on a failed login; anything that is not a WP_User,
 * any user who can edit posts, and a missing account page leave $redirect_to untouched.
 *
 * @param string           $redirect_to Destination resolved so far.
 * @param string           $requested   The `redirect_to` the request asked for.
 * @param WP_User|WP_Error $user        The user who just logged in, or an error.
 * @return string
 */
function submission_login_redirect($redirect_to, $requested, $user)
{
    if (!($user instanceof WP_User) || !is_submission_only_user($user)) {
        return $redirect_to;
    }

    $account_url = get_submission_page_url('account');

    return $account_url !== '' ? $account_url : $redirect_to;
}
add_filter('login_redirect', 'submission_login_redirect', 10, 3);
