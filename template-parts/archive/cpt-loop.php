<?php
/**
 * Shared CPT archive loop (global + department isolation).
 *
 * $args:
 * - post_type (string) required
 * - department (WP_Term|null)
 * - paged (int)
 * - use_main_query (bool) if true, use main query (archive.php)
 * - subtitle (string) optional override
 */

$post_type = $args['post_type'] ?? '';
$department = $args['department'] ?? null;
$paged = max(1, (int) ($args['paged'] ?? 1));
$use_main_query = !empty($args['use_main_query']);

if (!$post_type || !is_content_post_type($post_type)) {
    return;
}

$filterable = get_filterable_taxonomies();

// -------------------------------------------------------------------------
// Query
// -------------------------------------------------------------------------
if ($use_main_query) {
    global $wp_query;
    $loop_query = $wp_query;
} else {
    $tax_query = ['relation' => 'AND'];

    if ($department instanceof WP_Term) {
        $tax_query[] = [
            'taxonomy' => 'department',
            'field' => 'term_id',
            'terms' => $department->term_id,
        ];
    }

    foreach ($filterable as $tax) {
        if (!empty($_GET[$tax])) {
            $tax_query[] = [
                'taxonomy' => $tax,
                'field' => 'slug',
                'terms' => sanitize_text_field(wp_unslash($_GET[$tax])),
            ];
        }
    }

    $loop_query = new WP_Query([
        'post_type' => $post_type,
        'posts_per_page' => 12,
        'paged' => $paged,
        'post_status' => 'publish',
        'tax_query' => $tax_query,
        'ignore_sticky_posts' => true,
    ]);
}

// -------------------------------------------------------------------------
// Title / subtitle (Dynamically fetched from Homepage ACF via Functions)
// -------------------------------------------------------------------------
$cpt_labels = get_dynamic_cpt_labels($post_type);
$type_label = $cpt_labels['title'];

$subtitle = $args['subtitle'] ?? '';
if ($subtitle === '') {
    $subtitle = $cpt_labels['subtitle'];
}

if ($department instanceof WP_Term) {
    $header_title = sprintf(
        /* translators: 1: department name, 2: post type label */
        __('%1$s — %2$s', 'hello-elementor-child'),
        $department->name,
        $type_label
    );
} else {
    $header_title = $type_label;
}
?>

<section class="w-full px-margin-mobile lg:px-margin py-space-xl pt-12 view-<?php echo esc_attr($post_type); ?>">

    <div
        class="flex flex-col gap-space-xs text-start mb-space-xl border-b border-surface-container-highest/20 pb-space-lg">

        <?php if ($department instanceof WP_Term): ?>
            <!-- Back to department -->
            <div class="mb-4">
                <a href="<?php echo esc_url(get_term_link($department)); ?>"
                    class="inline-flex items-center gap-1 text-sm font-semibold text-gray-500 hover:text-primary transition-colors group">
                    <span
                        class="transition-transform duration-300 group-hover:translate-x-1 rtl:group-hover:-translate-x-1 rtl:rotate-180"
                        aria-hidden="true">←</span>
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: %s: department name */
                            __('العودة إلى %s', 'hello-elementor-child'),
                            $department->name
                        )
                    );
                    ?>
                </a>
            </div>
        <?php else: ?>
            <!-- Breadcrumb: Home / CPT -->
            <div class="flex items-center gap-2 mb-4">
                <a href="<?php echo esc_url(home_url('/')); ?>"
                    class="text-sm font-semibold text-gray-500 hover:text-primary transition-colors">
                    <?php esc_html_e('الرئيسية', 'hello-elementor-child'); ?>
                </a>
                <span class="text-gray-400 text-sm">/</span>
                <span class="text-sm font-semibold text-primary uppercase tracking-widest">
                    <?php echo esc_html($type_label); ?>
                </span>
            </div>
        <?php endif; ?>

        <h1 class="font-headline-xl text-headline-xl-mobile md:text-headline-xl text-on-background font-bold">
            <?php echo wp_kses_post($header_title); ?>
        </h1>

        <?php if ($subtitle !== '' && !($department instanceof WP_Term)): ?>
            <p class="font-body-lg text-body-lg text-on-surface-variant max-w-3xl mt-4 leading-relaxed">
                <?php echo wp_kses_post($subtitle); ?>
            </p>
        <?php endif; ?>

    </div>

    <?php if ($loop_query->have_posts()): ?>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-gutter mb-12">
            <?php
            while ($loop_query->have_posts()):
                $loop_query->the_post();
                get_template_part('template-parts/cards/card', $post_type);
            endwhile;
            ?>
        </div>

        <?php if ((int) $loop_query->max_num_pages > 1): ?>
            <nav class="flex justify-center mt-space-xl"
                aria-label="<?php esc_attr_e('Pagination', 'hello-elementor-child'); ?>">
                <div class="inline-flex items-center gap-2 flex-wrap justify-center
                    [&_.page-numbers]:inline-flex [&_.page-numbers]:items-center [&_.page-numbers]:justify-center
                    [&_.page-numbers]:min-w-[44px] [&_.page-numbers]:h-[44px] [&_.page-numbers]:px-2
                    [&_.page-numbers]:rounded-full [&_.page-numbers]:font-bold [&_.page-numbers]:shadow-sm
                    [&_.current]:bg-primary [&_.current]:text-on-primary">
                    <?php
                    $pagination_args = [];

                    if ($department instanceof WP_Term) {
                        $pagination_args['view'] = $post_type;
                        foreach ($filterable as $tax) {
                            if (!empty($_GET[$tax])) {
                                $pagination_args[$tax] = sanitize_text_field(wp_unslash($_GET[$tax]));
                            }
                        }
                    }

                    echo paginate_links([
                        'total' => (int) $loop_query->max_num_pages,
                        'current' => $paged,
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'add_args' => $pagination_args ?: false,
                    ]);
                    ?>
                </div>
            </nav>
        <?php endif; ?>

        <?php
        if (!$use_main_query) {
            wp_reset_postdata();
        }
        ?>

    <?php else: ?>

        <div
            class="flex flex-col items-center justify-center py-20 text-center rounded-3xl border border-surface-container-highest/30 shadow-sm">
            <span class="material-symbols-outlined text-6xl text-gray-300 mb-4">search_off</span>
            <h3 class="text-2xl font-bold text-gray-900 mb-2">
                <?php esc_html_e('لا توجد محتويات بعد', 'hello-elementor-child'); ?>
            </h3>
            <p class="text-gray-500 text-lg">
                <?php esc_html_e('لم يتم نشر أي محتوى في هذا القسم حتى الآن.', 'hello-elementor-child'); ?>
            </p>
        </div>

    <?php endif; ?>

</section>