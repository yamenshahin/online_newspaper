<?php
/**
 * The template for displaying header.
 *
 * Child theme override of hello-elementor/template-parts/dynamic-header.php
 * (Hello Elementor 3.5.1). The only change is the "Child theme: header search"
 * block around the desktop menu. After a Hello update, compare the files from
 * the child theme folder and port any upstream change:
 * diff -u --strip-trailing-cr ../hello-elementor/template-parts/dynamic-header.php template-parts/dynamic-header.php
 *
 * @package HelloElementor
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! hello_get_header_display() ) {
	return;
}

$is_editor = isset( $_GET['elementor-preview'] );
$site_name = get_bloginfo( 'name' );
$tagline   = get_bloginfo( 'description', 'display' );
$header_class = did_action( 'elementor/loaded' ) ? hello_get_header_layout_class() : '';
$menu_args = [
	'theme_location' => 'menu-1',
	'fallback_cb' => false,
	'container' => false,
	'echo' => false,
];
$header_nav_menu = wp_nav_menu( $menu_args );
$header_mobile_nav_menu = wp_nav_menu( $menu_args ); // The same menu but separate call to avoid duplicate ID attributes.
?>
<header id="site-header" class="site-header dynamic-header <?php echo esc_attr( $header_class ); ?>">
	<div class="header-inner">
		<div class="site-branding show-<?php echo esc_attr( hello_elementor_get_setting( 'hello_header_logo_type' ) ); ?>">
			<?php if ( has_custom_logo() && ( 'title' !== hello_elementor_get_setting( 'hello_header_logo_type' ) || $is_editor ) ) : ?>
				<div class="site-logo <?php echo esc_attr( hello_show_or_hide( 'hello_header_logo_display' ) ); ?>">
					<?php the_custom_logo(); ?>
				</div>
			<?php endif;

			if ( $site_name && ( 'logo' !== hello_elementor_get_setting( 'hello_header_logo_type' ) || $is_editor ) ) : ?>
				<div class="site-title <?php echo esc_attr( hello_show_or_hide( 'hello_header_logo_display' ) ); ?>">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr__( 'Home', 'hello-elementor' ); ?>" rel="home">
						<?php echo esc_html( $site_name ); ?>
					</a>
				</div>
			<?php endif;

			if ( $tagline && ( hello_elementor_get_setting( 'hello_header_tagline_display' ) || $is_editor ) ) : ?>
				<p class="site-description <?php echo esc_attr( hello_show_or_hide( 'hello_header_tagline_display' ) ); ?>">
					<?php echo esc_html( $tagline ); ?>
				</p>
			<?php endif; ?>
		</div>

		<?php
		/*
		 * Child theme: header search.
		 * .header-actions groups the desktop menu with the search toggle so the two
		 * wrap onto the next row together, and on tablet/mobile (menu hidden) it puts
		 * the toggle beside Hello's menu toggle. Markup: template-parts/header-search.php.
		 * Keep it before .site-navigation-toggle-holder: Hello's CSS pairs the holder
		 * with .site-navigation-dropdown through a "+" sibling selector.
		 */
		?>
		<div class="header-actions">
		<?php if ( $header_nav_menu ) : ?>
			<nav class="site-navigation <?php echo esc_attr( hello_show_or_hide( 'hello_header_menu_display' ) ); ?>" aria-label="<?php echo esc_attr__( 'Main menu', 'hello-elementor' ); ?>">
				<?php
				// PHPCS - escaped by WordPress with "wp_nav_menu"
				echo $header_nav_menu; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</nav>
		<?php endif; ?>
		<?php get_template_part( 'template-parts/header-search' ); ?>
		</div>
		<?php // End child theme: header search. ?>
		<?php if ( $header_mobile_nav_menu ) : ?>
			<div class="site-navigation-toggle-holder <?php echo esc_attr( hello_show_or_hide( 'hello_header_menu_display' ) ); ?>">
				<button type="button" class="site-navigation-toggle" aria-expanded="false" aria-label="<?php echo esc_attr__( 'Menu', 'hello-elementor' ); ?>">
					<span class="site-navigation-toggle-icon" aria-hidden="true"></span>
				</button>
			</div>
			<nav class="site-navigation-dropdown <?php echo esc_attr( hello_show_or_hide( 'hello_header_menu_display' ) ); ?>" aria-label="<?php echo esc_attr__( 'Mobile menu', 'hello-elementor' ); ?>" aria-hidden="true" inert>
				<?php
				// PHPCS - escaped by WordPress with "wp_nav_menu"
				echo $header_mobile_nav_menu; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</nav>
		<?php endif; ?>
	</div>
</header>
