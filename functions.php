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
require_once $theme_inc . '/taxonomy-helpers.php';
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

	// ADDED: Enqueue the custom filters JS file in the footer
	$filters_js_path = '/assets/js/filters.js';
	if (file_exists(get_stylesheet_directory() . $filters_js_path)) {
		wp_enqueue_script(
			'hello-elementor-child-filters',
			get_stylesheet_directory_uri() . $filters_js_path,
			[],
			filemtime(get_stylesheet_directory() . $filters_js_path),
			true
		);
		wp_localize_script(
			'hello-elementor-child-filters',
			'helloEntityNav',
			[
				'searchUrl' => esc_url_raw(rest_url('hello/v1/entity-search')),
			]
		);
	}
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

/**
 * Clean up legacy WordPress captions and hardcoded image dimensions globally.
 */
add_filter('the_content', function ($content) {
	// 1. Completely remove the old caption shortcode, keeping only the image and text to make it flexible/responsive
	$content = preg_replace('/\[caption[^\]]*\](<img[^>]+>)(.*?)\[\/caption\]/is', '<div class="my-8">$1<p class="text-center text-sm text-gray-500 mt-2">$2</p></div>', $content);

	// 2. Remove the hardcoded width and height attributes directly from the img tags
	$content = preg_replace('/(<img[^>]+)(width|height)="\d*"\s?/i', '$1', $content);

	// 3. Remove the inline width styles forced by WordPress captions
	$content = preg_replace('/style="[^"]*width:\s*\d+px;?[^"]*"/', '', $content);

	return $content;
}, 20);


/**
 * -------------------------------------------------
 * 1. CACHE-SAFE POST VIEWS TRACKER (REST API)
 * -------------------------------------------------
 */
add_action('rest_api_init', function () {
	register_rest_route('tafaol/v1', '/track-view/(?P<id>\d+)', [
		'methods' => 'POST',
		'callback' => 'tafaol_track_post_view_api',
		'permission_callback' => '__return_true',
	]);
});

function tafaol_track_post_view_api($request)
{
	$post_id = (int) $request['id'];

	if (!$post_id || get_post_status($post_id) !== 'publish') {
		return new WP_Error('invalid_post', 'Invalid Post ID', ['status' => 404]);
	}

	// Basic bot filtering
	$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
	if (preg_match('/bot|crawl|slurp|spider|mediapartners|facebookexternalhit/i', $user_agent)) {
		return rest_ensure_response(['success' => false, 'reason' => 'bot']);
	}

	$count = (int) get_post_meta($post_id, 'post_views_count', true);
	$new_count = $count + 1;
	update_post_meta($post_id, 'post_views_count', $new_count);

	return rest_ensure_response(['success' => true, 'views' => $new_count]);
}

/**
 * -------------------------------------------------
 * 2. FRONTEND SCRIPT (loaded via wp_footer)
 * -------------------------------------------------
 */
add_action('wp_footer', function () {
	if (!is_singular()) {
		return;
	}

	$post_id = get_queried_object_id();
	?>
	<script>
		document.addEventListener("DOMContentLoaded", function () {
			const postId = <?php echo (int) $post_id; ?>;
			const sessionKey = 'viewed_post_' + postId;

			if (!sessionStorage.getItem(sessionKey) && !/bot|crawl|spider|robot/i.test(navigator.userAgent)) {
				fetch('<?php echo esc_url(rest_url('tafaol/v1/track-view/' . $post_id)); ?>', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' }
				})
					.then(response => response.json())
					.then(result => {
						if (result.success) {
							sessionStorage.setItem(sessionKey, '1');
						}
					})
					.catch(() => { });
			}
		});
	</script>
	<?php
});

/**
 * -------------------------------------------------
 * 3. ADMIN UI: READ-ONLY META BOX
 * -------------------------------------------------
 */
add_action('add_meta_boxes', function () {
	$post_types = ['post', 'infographic', 'interview', 'video', 'podcast', 'program'];

	foreach ($post_types as $post_type) {
		add_meta_box(
			'post_view_count',
			__('Post Views', 'hello-elementor-child'),
			function ($post) {
				$count = (int) get_post_meta($post->ID, 'post_views_count', true);
				echo '<p style="font-size: 24px; font-weight: 600; margin: 8px 0; color: #2271b1;">' . esc_html(number_format_i18n($count)) . '</p>';
				echo '<p class="description">Total unique session views by real users.</p>';
			},
			$post_type,
			'side',
			'high'
		);
	}
});

/**
 * -------------------------------------------------
 * 4. ADMIN UI: POST LIST COLUMNS + SORTING
 * -------------------------------------------------
 */
$target_cpts = ['post', 'infographic', 'interview', 'video', 'podcast', 'program'];

foreach ($target_cpts as $cpt) {
	add_filter("manage_{$cpt}_posts_columns", function ($columns) {
		$columns['post_views'] = __('Views', 'hello-elementor-child');
		return $columns;
	});

	add_action("manage_{$cpt}_posts_custom_column", function ($column, $post_id) {
		if ($column === 'post_views') {
			$count = (int) get_post_meta($post_id, 'post_views_count', true);
			echo esc_html(number_format_i18n($count));
		}
	}, 10, 2);

	add_filter("manage_edit-{$cpt}_sortable_columns", function ($columns) {
		$columns['post_views'] = 'post_views_count';
		return $columns;
	});
}

add_action('pre_get_posts', function ($query) {
	if (!is_admin() || !$query->is_main_query()) {
		return;
	}

	if ($query->get('orderby') === 'post_views_count') {
		$query->set('meta_key', 'post_views_count');
		$query->set('orderby', 'meta_value_num');
	}
});

/**
 * Shortcode to display a WordPress menu as plain horizontal links.
 * Usage: [plain_menu name="Footer Menu"] or [plain_menu location="footer"]
 */
add_shortcode('plain_menu', function ($atts) {
	$atts = shortcode_atts([
		'name' => '',
		'location' => '',
		'class' => '',
	], $atts, 'plain_menu');

	$args = [
		'echo' => false,
		'container' => 'nav',
		'container_class' => 'plain-links-nav ' . esc_attr($atts['class']),
		'menu_class' => 'plain-links-list',
		'fallback_cb' => false,
		'depth' => 1, // Single-level flat links only
	];

	if (!empty($atts['name'])) {
		$args['menu'] = $atts['name'];
	} elseif (!empty($atts['location'])) {
		$args['theme_location'] = $atts['location'];
	}

	$html = wp_nav_menu($args);

	return $html ?: '';
});

/**
 * Disable Rank Math Virtual Robots.txt
 */
add_filter('rank_math/tools/robots_txt', '__return_false');