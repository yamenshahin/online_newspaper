<?php
/**
 * Master archive → shared cpt-loop
 */

get_header();

$post_type = get_query_var('post_type');
if (is_array($post_type)) {
    $post_type = reset($post_type);
}
if (!$post_type) {
    $post_type = get_post_type();
}

// Blog posts index
if (is_home() && !is_front_page()) {
    $post_type = 'post';
}

$paged = max(1, (int) get_query_var('paged'));
?>

<main id="primary" class="site-main min-h-screen">
    <?php if ($post_type && is_content_post_type($post_type)): ?>
        <?php
        get_template_part('template-parts/archive/cpt-loop', null, [
            'post_type' => $post_type,
            'department' => null,
            'paged' => $paged,
            'use_main_query' => true,
        ]);
        ?>
    <?php else: ?>
        <section class="w-full px-margin-mobile lg:px-margin py-space-xl">
            <h1 class="font-headline-xl text-on-background font-bold mb-8">
                <?php echo esc_html(wp_strip_all_tags(get_the_archive_title())); ?>
            </h1>
            <?php if (have_posts()): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-gutter">
                    <?php
                    while (have_posts()):
                        the_post();
                        get_template_part('template-parts/cards/card', 'post');
                    endwhile;
                    ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>

<?php
get_footer();