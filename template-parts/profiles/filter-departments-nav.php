<?php
/**
 * Departments nav for a filter term (speaker, gov, private, geographic…).
 *
 * $args:
 * - term (WP_Term)          required — active filter term
 * - current_department (WP_Term|null) — set on department+filter pages
 */

$term = $args['term'] ?? null;
if (!$term instanceof WP_Term) {
    return;
}

$current_department = $args['current_department'] ?? null;
$departments = get_departments_for_filter_term($term);

if (empty($departments)) {
    return;
}

$all_url = get_term_link($term);
if (is_wp_error($all_url)) {
    return;
}

// "All" is active when we are on the pure taxonomy archive (no current department)
$is_all_active = !($current_department instanceof WP_Term);
?>

<nav class="w-full max-w-6xl mx-auto px-6 pb-8 -mt-4 mb-8"
    aria-label="<?php esc_attr_e('Departments', 'hello-elementor-child'); ?>">
    <ul class="flex flex-wrap items-center gap-2 border-b border-gray-100 pb-4">
        <!-- الكل / All ← pure taxonomy archive -->
        <li>
            <a href="<?php echo esc_url($all_url); ?>" class="inline-flex px-4 py-2 rounded-full text-sm font-semibold transition-colors <?php echo $is_all_active
                   ? 'bg-primary text-white'
                   : 'bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary'; ?>">
                <?php esc_html_e('الكل', 'hello-elementor-child'); ?>
            </a>
        </li>

        <?php foreach ($departments as $dept):
            $url = add_query_arg($term->taxonomy, $term->slug, get_term_link($dept));
            $is_active = ($current_department instanceof WP_Term && (int) $current_department->term_id === (int) $dept->term_id);
            ?>
            <li>
                <a href="<?php echo esc_url($url); ?>" class="inline-flex px-4 py-2 rounded-full text-sm font-semibold transition-colors <?php echo $is_active
                       ? 'bg-primary text-white'
                       : 'bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-primary'; ?>">
                    <?php echo esc_html($dept->name); ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>