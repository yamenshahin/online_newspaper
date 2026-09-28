<?php
/**
 * Single Podcast — content + related reels
 */
get_header();

while (have_posts()):
	the_post();

	$editor_terms = get_the_terms(get_the_ID(), 'post_editor');
	$display_author = 'تفاعل السعودية';
	if (!empty($editor_terms) && !is_wp_error($editor_terms)) {
		$display_author = $editor_terms[0]->name;
	}

	$reels = get_field('podcast_reels');
	if (!is_array($reels)) {
		$reels = $reels ? [$reels] : [];
	}
	?>
	<main id="primary" class="site-main bg-white pb-24">
		<header class="py-16 md:py-24 bg-gray-50/50 border-b border-gray-100 mb-12">
			<div class="max-w-4xl mx-auto px-6 text-center">
				<span class="text-sm font-bold text-primary uppercase tracking-widest mb-4 block">
					<?php esc_html_e('بودكاست', 'hello-elementor-child'); ?>
				</span>
				<h1 class="text-4xl md:text-5xl lg:text-6xl font-bold text-gray-900 tracking-tight mb-6 leading-tight">
					<?php the_title(); ?>
				</h1>
				<div class="flex items-center justify-center gap-4 text-sm font-medium text-gray-500">
					<time
						datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date()); ?></time>
					<span>&bull;</span>
					<span><?php echo esc_html($display_author); ?></span>
				</div>
			</div>
		</header>

		<?php if (has_post_thumbnail()): ?>
			<div class="max-w-5xl mx-auto px-6 mb-16 -mt-24 relative z-10">
				<div class="rounded-3xl overflow-hidden shadow-2xl shadow-gray-200/50 bg-white p-2">
					<?php the_post_thumbnail('full', ['class' => 'w-full h-auto rounded-2xl']); ?>
				</div>
			</div>
		<?php endif; ?>

		<article class="max-w-3xl mx-auto px-6">
			<div
				class="text-lg md:text-xl text-gray-700 leading-relaxed
				[&>p]:mb-6 [&>h2]:text-3xl [&>h2]:font-bold [&>h2]:mt-12 [&>h2]:mb-6
				[&_a]:text-primary [&_a]:font-medium [&_a:hover]:text-primary/85 [&_a:hover]:underline [&>iframe]:w-full [&>iframe]:rounded-xl [&>iframe]:my-8">
				<?php the_content(); ?>
			</div>
		</article>

		<?php if (!empty($reels)): ?>
			<section class="max-w-7xl mx-auto px-6 mt-20">
				<h2 class="text-2xl font-bold text-gray-900 mb-8">
					<?php esc_html_e('ريلز الحلقة', 'hello-elementor-child'); ?>
				</h2>
				<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
					<?php
					foreach ($reels as $reel_post):
						if (!$reel_post instanceof WP_Post) {
							continue;
						}
						$GLOBALS['post'] = $reel_post;
						setup_postdata($reel_post);
						get_template_part('template-parts/cards/card', 'reel');
					endforeach;
					wp_reset_postdata();
					?>
				</div>
			</section>
		<?php endif; ?>
	</main>
	<?php
endwhile;

get_footer();