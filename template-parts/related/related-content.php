<?php
/**
 * Related content under a single item: the main row (picks + same type) and
 * the mixed strip (other types with a specific match). See inc/related-content.php.
 *
 * Cards are the types' own card-{type}.php. On a single page they label
 * themselves with the post type, which doubles as the type badge in the strip.
 */

$related = get_related_content(get_the_ID());

// The post's own department, for the heading icon (as in the Layout Engine sections).
$department = get_context_department();

$rows = [
    'main' => __('قد يهمك أيضًا', 'hello-elementor-child'),
    'strip' => __('تغطيات أخرى حول الموضوع', 'hello-elementor-child'),
];

global $post;

foreach ($rows as $row => $title):
    if (empty($related[$row])) {
        continue;
    }
    $heading_id = 'related-' . $row . '-title';
    ?>
    <section class="w-full px-margin-mobile lg:px-margin pt-space-xl" aria-labelledby="<?php echo esc_attr($heading_id); ?>">
        <div class="flex items-center gap-space-xs mb-space-lg">
            <?php render_department_icon($department ?? null); ?>
            <h2 id="<?php echo esc_attr($heading_id); ?>" class="font-headline-lg text-headline-lg text-on-background">
                <?php echo esc_html($title); ?>
            </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-gutter">
            <?php
            foreach ($related[$row] as $post):
                setup_postdata($post);
                get_template_part('template-parts/cards/card', get_search_result_card_slug((string) get_post_type()));
            endforeach;
            ?>
        </div>
    </section>
    <?php
endforeach;

wp_reset_postdata();
