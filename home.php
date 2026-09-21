<?php
/**
 * Archive for posts
 */
get_header();
?>
<main id="primary" class="site-main min-h-screen">
    <?php
    get_template_part('template-parts/archive/cpt-loop', null, [
        'post_type' => 'post',
        'department' => null,
        'paged' => max(1, (int) get_query_var('paged')),
        'use_main_query' => true,
    ]);
    ?>
</main>
<?php
get_footer();