<?php
/**
 * Theme functions and definitions.
 */

if (!defined('ABSPATH')) {
	exit;
}

define('HELLO_ELEMENTOR_CHILD_VERSION', '2.0.0');

function hello_elementor_child_scripts_styles()
{
	// 1. Enqueue FontAwesome (Load this first so it's ready for your UI)
	wp_enqueue_style(
		'font-awesome-cdn',
		'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
		[],
		'6.5.1'
	);

	// 2. Enqueue Google Fonts (Syne & Plus Jakarta Sans)
	wp_enqueue_style(
		'hello-elementor-child-fonts',
		'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Syne:wght@600;700;800&display=swap',
		[],
		null
	);

	// 3. Enqueue Google Material Symbols Outlined
	wp_enqueue_style(
		'hello-elementor-child-icons',
		'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0',
		[],
		null
	);

	// 4. Enqueue the root style.css (Theme identity & base custom styles)
	wp_enqueue_style(
		'hello-elementor-child-style',
		get_stylesheet_uri(),
		['hello-elementor-theme-style', 'font-awesome-cdn', 'hello-elementor-child-fonts', 'hello-elementor-child-icons'],
		filemtime(get_stylesheet_directory() . '/style.css')
	);

	// 5. Enqueue the compiled Tailwind utilities
	wp_enqueue_style(
		'hello-elementor-child-tailwind',
		get_stylesheet_directory_uri() . '/assets/css/tailwind.css',
		['hello-elementor-child-style'],
		filemtime(get_stylesheet_directory() . '/assets/css/tailwind.css')
	);
}
add_action('wp_enqueue_scripts', 'hello_elementor_child_scripts_styles', 20);

/**
 * Register custom query var
 */
add_filter('query_vars', function ($vars) {
	$vars[] = 'view';
	return $vars;
});

/**
 * Hide the default WordPress taxonomy description field
 */
add_action('admin_head', function () {
	if (isset($_GET['taxonomy'])) {
		echo '<style>.term-description-wrap { display: none !important; }</style>';
	}
});

/**
 * Force program, infographic, interview to use /post-type/ID/
 */
add_filter('post_type_link', function ($permalink, $post) {
	$targets = ['program', 'infographic', 'interview'];
	if (in_array($post->post_type, $targets, true)) {
		return home_url($post->post_type . '/' . $post->ID . '/');
	}
	return $permalink;
}, 10, 2);

add_action('init', function () {
	$targets = ['program', 'infographic', 'interview'];
	foreach ($targets as $post_type) {
		add_rewrite_rule(
			'^' . $post_type . '/([0-9]+)/?$',
			'index.php?post_type=' . $post_type . '&p=$matches[1]',
			'top'
		);
	}
});

/**
 * Force standard posts to /news/ID/
 */
add_filter('post_link', function ($permalink, $post) {
	if ('post' === $post->post_type) {
		return home_url('news/' . $post->ID . '/');
	}
	return $permalink;
}, 10, 2);

add_action('init', function () {
	add_rewrite_rule('^news/([0-9]+)/?$', 'index.php?p=$matches[1]', 'top');
});

/**
 * -------------------------------------------------
 * INTERSECTION ENGINE
 * Returns terms of a taxonomy that actually have posts
 * in common with the given department + accurate counts
 * -------------------------------------------------
 */
function get_department_intersected_terms(int $department_id, string $taxonomy, int $limit = 0): array
{
	global $wpdb;

	// 1. Get all post IDs belonging to this department
	$department_posts = $wpdb->get_col($wpdb->prepare("
		SELECT tr.object_id
		FROM {$wpdb->term_relationships} tr
		INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
		WHERE tt.taxonomy = 'department'
		AND tt.term_id = %d
	", $department_id));

	if (empty($department_posts)) {
		return [];
	}

	$post_ids = array_map('intval', $department_posts);
	$placeholders = implode(',', array_fill(0, count($post_ids), '%d'));

	// 2. Find terms of the target taxonomy attached to those posts
	$sql = "
		SELECT tt.term_id, COUNT(tr.object_id) AS post_count
		FROM {$wpdb->term_relationships} tr
		INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
		WHERE tt.taxonomy = %s
		AND tr.object_id IN ($placeholders)
		GROUP BY tt.term_id
		ORDER BY post_count DESC
	";

	$query = $wpdb->prepare($sql, array_merge([$taxonomy], $post_ids));

	if ($limit > 0) {
		$query .= $wpdb->prepare(' LIMIT %d', $limit);
	}

	$results = $wpdb->get_results($query);

	if (empty($results)) {
		return [];
	}

	$output = [];
	foreach ($results as $row) {
		$term = get_term((int) $row->term_id, $taxonomy);
		if ($term && !is_wp_error($term)) {
			$output[] = [
				'term' => $term,
				'count' => (int) $row->post_count,
			];
		}
	}

	return $output;
}

/**
 * Dynamically swap the Custom Logo based on Department context.
 */
add_filter('theme_mod_custom_logo', 'dynamic_department_custom_logo');

function dynamic_department_custom_logo($default_logo_id)
{
	// Do not interfere with the WordPress admin backend
	if (is_admin()) {
		return $default_logo_id;
	}

	$current_department = null;

	// 1. Context Engine: Figure out if we are in a Department
	if (is_tax('department')) {
		$current_department = get_queried_object();
	} elseif (is_singular(['post', 'program', 'infographic', 'interview'])) {
		$terms = get_the_terms(get_the_ID(), 'department');
		if (!empty($terms) && !is_wp_error($terms)) {
			$current_department = $terms[0];
		}
	}

	// 2. Fetch the ACF Logo if context matches
	if ($current_department) {
		$image_data = get_field('department_logo', $current_department);

		if (is_array($image_data) && !empty($image_data['ID'])) {
			return $image_data['ID']; // Return Department Logo ID
		} elseif (is_numeric($image_data) && !empty($image_data)) {
			return (int) $image_data; // Return Department Logo ID
		}
	}

	// 3. Fallback to the default global logo
	return $default_logo_id;
}

/**
 * Hide tag and category taxonomies
 */
add_action('init', function () {
	foreach (get_post_types() as $post_type) {
		unregister_taxonomy_for_object_type('category', $post_type);
		unregister_taxonomy_for_object_type('post_tag', $post_type);
	}
}, 20);

add_action('admin_menu', function () {
	remove_submenu_page('edit.php', 'edit-tags.php?taxonomy=post_tag');
	remove_submenu_page('edit.php', 'edit-tags.php?taxonomy=category');
});

/**
 * Hide Social Media layout from Flexible Content when editing Pages (e.g. Home).
 * Keep it available when editing Department terms.
 */
add_filter('acf/load_field/name=department_sections', function ($field) {
	if (!is_admin()) {
		return $field;
	}

	// Only when editing a Page (Home uses a Page)
	$post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;

	$is_page_screen = false;

	if ($post_id && get_post_type($post_id) === 'page') {
		$is_page_screen = true;
	} elseif (isset($_GET['post_type']) && $_GET['post_type'] === 'page') {
		// post-new.php?post_type=page
		$is_page_screen = true;
	}

	if (!$is_page_screen) {
		return $field; // Department term screens keep Social layout
	}

	if (empty($field['layouts']) || !is_array($field['layouts'])) {
		return $field;
	}

	foreach ($field['layouts'] as $key => $layout) {
		if (isset($layout['name']) && $layout['name'] === 'social_section') {
			unset($field['layouts'][$key]);
		}
	}

	// Re-index so ACF stays happy
	$field['layouts'] = array_values($field['layouts']);

	return $field;
});

