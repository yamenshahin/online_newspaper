<?php
/**
 * Theme functions and definitions.
 */

if (!defined('ABSPATH')) {
	exit;
}

define('HELLO_ELEMENTOR_CHILD_VERSION', '2.0.0');

// ---------------------------------------------------------------------------
// Includes
// ---------------------------------------------------------------------------
$theme_inc = get_stylesheet_directory() . '/inc';
require_once $theme_inc . '/department-context.php';
require_once $theme_inc . '/shortcodes/department-footer.php';

// ---------------------------------------------------------------------------
// Assets
// ---------------------------------------------------------------------------
function hello_elementor_child_scripts_styles()
{
	wp_enqueue_style(
		'font-awesome-cdn',
		'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
		[],
		'6.5.1'
	);

	wp_enqueue_style(
		'hello-elementor-child-fonts',
		'https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Syne:wght@600;700;800&display=swap',
		[],
		null
	);

	wp_enqueue_style(
		'hello-elementor-child-icons',
		'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0',
		[],
		null
	);

	wp_enqueue_style(
		'hello-elementor-child-style',
		get_stylesheet_uri(),
		['font-awesome-cdn', 'hello-elementor-child-fonts'],
		filemtime(get_stylesheet_directory() . '/style.css')
	);

	wp_enqueue_style(
		'hello-elementor-child-tailwind',
		get_stylesheet_directory_uri() . '/assets/css/tailwind.css',
		['hello-elementor-child-style'],
		filemtime(get_stylesheet_directory() . '/assets/css/tailwind.css')
	);
}
add_action('wp_enqueue_scripts', 'hello_elementor_child_scripts_styles', 20);

// ---------------------------------------------------------------------------
// Query vars
// ---------------------------------------------------------------------------
add_filter('query_vars', function ($vars) {
	$vars[] = 'view';
	return $vars;
});

// ---------------------------------------------------------------------------
// Admin UI
// ---------------------------------------------------------------------------
add_action('admin_head', function () {
	if (isset($_GET['taxonomy'])) {
		echo '<style>.term-description-wrap { display: none !important; }</style>';
	}
});

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

// ---------------------------------------------------------------------------
// Permalinks: /{post-type}/{id}/ and /news/{id}/
// ---------------------------------------------------------------------------
add_filter('post_type_link', function ($permalink, $post) {
	$targets = ['program', 'infographic', 'interview', 'video', 'podcast', 'reel'];
	if (in_array($post->post_type, $targets, true)) {
		return home_url($post->post_type . '/' . $post->ID . '/');
	}
	return $permalink;
}, 10, 2);

add_action('init', function () {
	$targets = ['program', 'infographic', 'interview', 'video', 'podcast', 'reel'];
	foreach ($targets as $post_type) {
		add_rewrite_rule(
			'^' . $post_type . '/([0-9]+)/?$',
			'index.php?post_type=' . $post_type . '&p=$matches[1]',
			'top'
		);
	}
});

add_filter('post_link', function ($permalink, $post) {
	if ('post' === $post->post_type) {
		return home_url('news/' . $post->ID . '/');
	}
	return $permalink;
}, 10, 2);

add_action('init', function () {
	add_rewrite_rule('^news/([0-9]+)/?$', 'index.php?p=$matches[1]', 'top');
});

// ---------------------------------------------------------------------------
// Content registry
// ---------------------------------------------------------------------------
function get_content_post_types(): array
{
	return [
		'post',
		'program',
		'interview',
		'infographic',
		'video',
		'podcast',
	];
}

function get_filterable_taxonomies(): array
{
	return [
		'government_entity',
		'private_entity',
		'speaker_influencer',
		'geographic',
		'program_series',
	];
}

function is_content_post_type(string $slug): bool
{
	return in_array($slug, get_content_post_types(), true);
}

// ---------------------------------------------------------------------------
// Department helpers (queries / UI)
// ---------------------------------------------------------------------------
function get_department_intersected_terms(int $department_id, string $taxonomy, int $limit = 0): array
{
	global $wpdb;

	$department_posts = $wpdb->get_col(
		$wpdb->prepare(
			"
			SELECT tr.object_id
			FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			WHERE tt.taxonomy = 'department'
			AND tt.term_id = %d
			",
			$department_id
		)
	);

	if (empty($department_posts)) {
		return [];
	}

	$post_ids = array_map('intval', $department_posts);
	$placeholders = implode(',', array_fill(0, count($post_ids), '%d'));

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

function get_departments_for_filter_term(WP_Term $filter_term): array
{
	global $wpdb;

	$post_types = get_content_post_types();
	$pt_placeholders = implode(',', array_fill(0, count($post_types), '%s'));

	$sql = "
		SELECT DISTINCT tr.object_id
		FROM {$wpdb->term_relationships} tr
		INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
		INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
		WHERE tt.taxonomy = %s
		  AND tt.term_id = %d
		  AND p.post_status = 'publish'
		  AND p.post_type IN ($pt_placeholders)
	";

	$params = array_merge([$filter_term->taxonomy, (int) $filter_term->term_id], $post_types);
	$post_ids = $wpdb->get_col($wpdb->prepare($sql, $params));

	if (empty($post_ids)) {
		return [];
	}

	$post_ids = array_map('intval', $post_ids);
	$id_ph = implode(',', array_fill(0, count($post_ids), '%d'));

	$sql2 = "
		SELECT DISTINCT tt.term_id
		FROM {$wpdb->term_relationships} tr
		INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
		WHERE tt.taxonomy = 'department'
		  AND tr.object_id IN ($id_ph)
	";

	$dept_ids = $wpdb->get_col($wpdb->prepare($sql2, $post_ids));
	if (empty($dept_ids)) {
		return [];
	}

	$departments = [];
	foreach ($dept_ids as $dept_id) {
		$t = get_term((int) $dept_id, 'department');
		if ($t && !is_wp_error($t)) {
			$departments[] = $t;
		}
	}

	usort(
		$departments,
		static function ($a, $b) {
			return strcasecmp($a->name, $b->name);
		}
	);

	return $departments;
}

function render_department_icon($department = null)
{
	$fallback_url = get_stylesheet_directory_uri() . '/assets/images/department-icon.png';
	$icon_url = $fallback_url;
	$alt_text = '';

	if ($department instanceof WP_Term) {
		$acf_icon = get_field('department_icon', $department);
		if (is_array($acf_icon) && !empty($acf_icon['url'])) {
			$icon_url = $acf_icon['url'];
			$alt_text = $acf_icon['alt'] ?: $department->name;
		} elseif (is_numeric($acf_icon) && !empty($acf_icon)) {
			$icon_url = wp_get_attachment_image_url($acf_icon, 'thumbnail');
			$alt_text = $department->name;
		}
	}

	echo '<img src="' . esc_url($icon_url) . '" alt="' . esc_attr($alt_text) . '" class="w-8 h-8 md:w-10 md:h-10 object-contain shrink-0" />';
}

// ---------------------------------------------------------------------------
// Department chrome (logo + primary color)
// ---------------------------------------------------------------------------
add_filter('theme_mod_custom_logo', 'dynamic_department_custom_logo');

function dynamic_department_custom_logo($default_logo_id)
{
	if (is_admin()) {
		return $default_logo_id;
	}

	$department = get_context_department();
	if (!$department instanceof WP_Term) {
		return $default_logo_id;
	}

	$image_data = get_field('department_logo', $department);
	if (is_array($image_data) && !empty($image_data['ID'])) {
		return (int) $image_data['ID'];
	}
	if (is_numeric($image_data) && !empty($image_data)) {
		return (int) $image_data;
	}

	return $default_logo_id;
}

add_action('wp_head', 'dynamic_department_theme_color');

function dynamic_department_theme_color()
{
	if (is_admin()) {
		return;
	}

	$department = get_context_department();
	if (!$department instanceof WP_Term) {
		return;
	}

	$color = get_field('department_color', $department);
	if (empty($color)) {
		return;
	}

	echo "<!-- Dynamic Department Accent Color -->\n<style>\n:root {\n";
	echo '  --color-primary: ' . esc_attr($color) . " !important;\n";
	echo '  --color-primary-container: ' . esc_attr($color) . " !important;\n";
	echo "}\n</style>\n";
}

// ---------------------------------------------------------------------------
// ACF Layout Engine: hide layouts by screen
// ---------------------------------------------------------------------------
add_filter('acf/load_field/name=department_sections', function ($field) {
	if (!is_admin()) {
		return $field;
	}

	$is_page_screen = false;

	$post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
	if ($post_id && get_post_type($post_id) === 'page') {
		$is_page_screen = true;
	} elseif (isset($_GET['post_type']) && $_GET['post_type'] === 'page') {
		$is_page_screen = true;
	}

	if (!$is_page_screen) {
		return $field;
	}

	$layouts_to_hide = ['social_section'];

	if (!empty($field['layouts']) && is_array($field['layouts'])) {
		foreach ($field['layouts'] as $key => $layout) {
			$name = $layout['name'] ?? '';
			if (in_array($name, $layouts_to_hide, true)) {
				unset($field['layouts'][$key]);
			}
		}
		$field['layouts'] = array_values($field['layouts']);
	}

	return $field;
});


/**
 * -------------------------------------------------
 * DYNAMIC CPT LABELS ENGINE
 * Fetches the post type title and subtitle from the 
 * Homepage ACF 'department_sections' flexible content.
 * -------------------------------------------------
 */
function get_dynamic_cpt_labels(string $post_type): array
{
	// Use a static cache so we only loop through the ACF data once per post type per page load
	static $cache = [];

	if (isset($cache[$post_type])) {
		return $cache[$post_type];
	}

	$title = '';
	$subtitle = '';

	$layout_map = [
		'post' => 'posts_section',
		'interview' => 'interviews_section',
		'program' => 'program_series_section',
		'infographic' => 'infographics_section',
		'video' => 'videos_section',
		'podcast' => 'podcasts_section',
	];

	$target_layout = $layout_map[$post_type] ?? $post_type . 's_section';
	$front_page_id = get_option('page_on_front');

	if ($front_page_id) {
		$sections = get_field('department_sections', $front_page_id);
		if (is_array($sections)) {
			foreach ($sections as $sec) {
				if (($sec['acf_fc_layout'] ?? '') === $target_layout) {
					$title = wp_strip_all_tags($sec['section_title'] ?? '');
					$subtitle = $sec['subtitle'] ?? '';
					break;
				}
			}
		}
	}

	$pt_obj = get_post_type_object($post_type);

	// Fallbacks if ACF is empty
	if (empty($title)) {
		$title = $pt_obj ? $pt_obj->labels->singular_name : $post_type;
	}

	if (empty($subtitle) && $pt_obj && !empty($pt_obj->description)) {
		$subtitle = $pt_obj->description;
	}

	$cache[$post_type] = [
		'title' => $title,
		'subtitle' => $subtitle,
	];

	return $cache[$post_type];
}

/**
 * -------------------------------------------------
 * DYNAMIC TAXONOMY LABELS ENGINE
 * Fetches the taxonomy title and subtitle from the 
 * Homepage ACF 'department_sections' flexible content.
 * -------------------------------------------------
 */
function get_dynamic_taxonomy_labels(string $taxonomy): array
{
	// Static cache prevents multiple database lookups per page load
	static $cache = [];

	if (isset($cache[$taxonomy])) {
		return $cache[$taxonomy];
	}

	$title = '';
	$subtitle = '';

	// Map your taxonomies to their exact ACF Flexible Content layout names
	$layout_map = [
		'government_entity' => 'government_entities_section',
		'private_entity' => 'private_entities_section',
		'speaker_influencer' => 'speakers_section',
		'geographic' => 'geographic_section',
	];

	$target_layout = $layout_map[$taxonomy] ?? '';
	$front_page_id = get_option('page_on_front');

	if ($target_layout && $front_page_id) {
		$sections = get_field('department_sections', $front_page_id);
		if (is_array($sections)) {
			foreach ($sections as $sec) {
				if (($sec['acf_fc_layout'] ?? '') === $target_layout) {
					$title = wp_strip_all_tags($sec['section_title'] ?? '');
					$subtitle = $sec['subtitle'] ?? '';
					break;
				}
			}
		}
	}

	// Fallback to standard WP taxonomy labels if ACF is empty
	$tax_obj = get_taxonomy($taxonomy);

	if (empty($title)) {
		$title = $tax_obj ? $tax_obj->labels->singular_name : $taxonomy;
	}

	$cache[$taxonomy] = [
		'title' => $title,
		'subtitle' => $subtitle,
	];

	return $cache[$taxonomy];
}