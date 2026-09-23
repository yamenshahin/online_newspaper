<?php
/**
 * Single Reel — reverse link to parent podcast
 */
get_header();

while (have_posts()):
    the_post();

    $parent = get_field('parent_podcast');
    if (is_array($parent)) {
        $parent = $parent[0] ?? null;
    }
    ?>
    <main id="primary" class="site-main bg-white pb-24">
        <header class="py-12 md:py-16 border-b border-gray-100 mb-10">
            <div class="max-w-3xl mx-auto px-6 text-center">
                <span class="text-sm font-bold text-primary uppercase tracking-widest mb-3 block">
                    <?php esc_html_e('ريل', 'hello-elementor-child'); ?>
                </span>
                <h1 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight mb-4">
                    <?php the_title(); ?>
                </h1>
                <?php if ($parent instanceof WP_Post): ?>
                    <p class="text-sm text-gray-500">
                        <?php esc_html_e('من بودكاست', 'hello-elementor-child'); ?>
                        <a href="<?php echo esc_url(get_permalink($parent)); ?>"
                            class="text-primary font-semibold hover:underline">
                            <?php echo esc_html(get_the_title($parent)); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        </header>

        <?php if (has_post_thumbnail()): ?>
            <div class="max-w-md mx-auto px-6 mb-12">
                <div class="rounded-3xl overflow-hidden shadow-xl aspect-[9/16] bg-gray-50">
                    <?php the_post_thumbnail('large', ['class' => 'w-full h-full object-cover']); ?>
                </div>
            </div>
        <?php endif; ?>

        <article class="max-w-2xl mx-auto px-6">
            <div class="text-lg text-gray-700 leading-relaxed [&>p]:mb-6 [&>iframe]:w-full [&>iframe]:rounded-xl">
                <?php the_content(); ?>
            </div>

            <?php if ($parent instanceof WP_Post): ?>
                <div class="mt-12 pt-8 border-t border-gray-100">
                    <a href="<?php echo esc_url(get_permalink($parent)); ?>"
                        class="inline-flex items-center gap-2 text-primary font-semibold hover:opacity-80">
                        <span aria-hidden="true">←</span>
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: %s: podcast title */
                                __('العودة إلى %s', 'hello-elementor-child'),
                                get_the_title($parent)
                            )
                        );
                        ?>
                    </a>
                </div>
            <?php endif; ?>
        </article>
    </main>
    <?php
endwhile;

get_footer();