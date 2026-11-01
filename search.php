<?php
/**
 * Search results: every ?s= request, including ?s=…&post_type=….
 *
 * Same shell as archive.php, and the header block, grid, pagination and
 * empty state of template-parts/archive/cpt-loop.php. cpt-loop itself
 * doesn't fit: it needs one post type and prints that type's label as the H1.
 * Which post types are searched, and the skip for too-short terms, live in
 * inc/site-search.php.
 */

get_header();

global $wp_query;

$search_term = get_site_search_term();
$is_prompt = site_search_term_is_too_short($search_term); // Empty or one letter: no query was run.
$found = (int) $wp_query->found_posts;
$max_pages = (int) $wp_query->max_num_pages;
$paged = max(1, (int) get_query_var('paged'));

// Long queries stay readable in the H1; the form keeps the full text.
$display_term = mb_strlen($search_term) > 80 ? mb_substr($search_term, 0, 80) . '…' : $search_term;

// One post type in the URL (?s=…&post_type=program) narrows the results.
// Show it as a removable chip, and keep it when the query is refined.
$requested_types = array_values(array_filter((array) get_query_var('post_type')));
$type_filter = count($requested_types) === 1 ? (string) $requested_types[0] : '';
$type_label = $type_filter !== '' ? get_site_search_type_label($type_filter) : '';
/* translators: %s: post type label, e.g. البرامج */
$remove_type_label = sprintf(__('Remove filter: %s', 'hello-elementor-child'), $type_label);
$all_types_url = add_query_arg('s', rawurlencode($search_term), home_url('/'));
?>

<main id="primary" class="site-main min-h-screen">
    <section class="w-full px-margin-mobile lg:px-margin py-space-xl pt-12 view-search">

        <div
            class="flex flex-col gap-space-xs text-start mb-space-xl border-b border-surface-container-highest/20 pb-space-lg">

            <!-- Breadcrumb: Home / Search -->
            <nav class="flex items-center gap-2 mb-4"
                aria-label="<?php echo esc_attr_x('Breadcrumb', 'search', 'hello-elementor-child'); ?>">
                <a href="<?php echo esc_url(home_url('/')); ?>"
                    class="text-sm font-semibold text-gray-500 hover:text-primary transition-colors">
                    <?php esc_html_e('Home', 'hello-elementor-child'); ?>
                </a>
                <span class="text-gray-400 text-sm" aria-hidden="true">/</span>
                <span class="text-sm font-semibold text-primary uppercase tracking-widest" aria-current="page">
                    <?php esc_html_e('Search', 'hello-elementor-child'); ?>
                </span>
            </nav>

            <h1
                class="font-headline-xl text-headline-xl-mobile md:text-headline-xl text-on-background font-bold wrap-break-word">
                <?php
                if ($is_prompt) {
                    esc_html_e('Site search', 'hello-elementor-child');
                } else {
                    printf(
                        /* translators: %s: the search query, wrapped in «» */
                        esc_html__('Search results for: %s', 'hello-elementor-child'),
                        '<span class="text-primary">«<bdi>' . esc_html($display_term) . '</bdi>»</span>'
                    );
                }
                ?>
            </h1>

            <?php if (!$is_prompt && $found > 0): ?>
                <p class="font-body-lg text-body-lg text-on-surface-variant max-w-3xl mt-4 leading-relaxed">
                    <?php
                    echo esc_html(get_search_results_count_text($found));
                    if ($max_pages > 1) {
                        echo ' · ';
                        printf(
                            /* translators: 1: current page number, 2: number of pages */
                            esc_html__('Page %1$s of %2$s', 'hello-elementor-child'),
                            esc_html(number_format_i18n($paged)),
                            esc_html(number_format_i18n($max_pages))
                        );
                    }
                    ?>
                </p>
            <?php endif; ?>

            <?php if ($type_filter !== ''): ?>
                <div class="flex flex-wrap items-center gap-2 mt-4">
                    <span class="text-sm text-on-surface-variant"><?php esc_html_e('In:', 'hello-elementor-child'); ?></span>
                    <a href="<?php echo esc_url($all_types_url); ?>"
                        class="inline-flex items-center gap-1 rounded-full bg-primary text-on-primary px-3 py-1 text-sm font-semibold"
                        aria-label="<?php echo esc_attr($remove_type_label); ?>">
                        <?php echo esc_html($type_label); ?>
                        <span aria-hidden="true">×</span>
                    </a>
                </div>
            <?php endif; ?>

            <div class="mt-space-lg">
                <?php
                get_template_part('template-parts/search/search-form', null, [
                    'id' => 'search-page-input',
                    'post_type' => $type_filter,
                    // Named apart from the header's search form, which can be open on this page too.
                    'label' => $is_prompt ? __('Search the site content', 'hello-elementor-child') : __('Edit search', 'hello-elementor-child'),
                ]);
                ?>
            </div>

        </div>

        <?php if ($is_prompt): ?>

            <div
                class="flex flex-col items-center justify-center py-20 px-space-lg text-center rounded-3xl border border-surface-container-highest/30 shadow-sm">
                <span class="material-symbols-outlined !text-6xl text-gray-300 mb-4" aria-hidden="true">search</span>
                <?php if ($search_term === ''): ?>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">
                        <?php esc_html_e('What are you looking for?', 'hello-elementor-child'); ?>
                    </h2>
                    <p class="text-gray-500 text-lg">
                        <?php esc_html_e('Type one or more words to search the news, programs, interviews and the rest of the site.', 'hello-elementor-child'); ?>
                    </p>
                <?php else: ?>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">
                        <?php esc_html_e('The search term is too short', 'hello-elementor-child'); ?>
                    </h2>
                    <p class="text-gray-500 text-lg">
                        <?php
                        // "حرفين" (two letters) = SITE_SEARCH_MIN_LENGTH.
                        esc_html_e('Type at least two characters to start searching.', 'hello-elementor-child');
                        ?>
                    </p>
                <?php endif; ?>
            </div>

        <?php elseif (have_posts()): ?>

            <h2 class="sr-only"><?php esc_html_e('Results', 'hello-elementor-child'); ?></h2>

            <div class="search-results-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-gutter mb-12 transition-opacity">
                <?php
                while (have_posts()):
                    the_post();
                    $card = get_search_result_card_slug((string) get_post_type());

                    if ('reel' === $card):
                        /*
                         * card-reel is 9:16: a 626px card in a 1440px-wide 4-column row whose
                         * other cards are 314-398px, and 683px full width on a phone. Reel
                         * thumbnails are landscape anyway (768x388 to 768x425). Here its image
                         * starts at the 16:9 the other cards use, so it never makes a row
                         * taller, then grows to fill the row's height.
                         */
                        ?>
                        <div
                            class="flex flex-col [&>article]:grow [&>article]:flex [&>article]:flex-col [&>article>a]:aspect-video [&>article>a]:grow">
                            <?php get_template_part('template-parts/cards/card', $card); ?>
                        </div>
                        <?php
                    else:
                        get_template_part('template-parts/cards/card', $card);
                    endif;
                endwhile;
                ?>
            </div>

            <?php if ($max_pages > 1): ?>
                <nav class="flex justify-center mt-space-xl"
                    aria-label="<?php esc_attr_e('Search results pages', 'hello-elementor-child'); ?>">
                    <div class="inline-flex items-center gap-2 flex-wrap justify-center
                        [&_.page-numbers]:inline-flex [&_.page-numbers]:items-center [&_.page-numbers]:justify-center
                        [&_.page-numbers]:min-w-[44px] [&_.page-numbers]:h-[44px] [&_.page-numbers]:px-2
                        [&_.page-numbers]:rounded-full [&_.page-numbers]:font-bold [&_.page-numbers]:shadow-sm
                        [&_.current]:bg-primary [&_.current]:text-on-primary">
                        <?php
                        // paginate_links() copies the current query string (s, post_type) onto every link.
                        echo paginate_links([
                            'total' => $max_pages,
                            'current' => $paged,
                            'prev_text' => '<span aria-hidden="true">&laquo;</span><span class="sr-only">'
                                . esc_html__('Previous page', 'hello-elementor-child') . '</span>',
                            'next_text' => '<span aria-hidden="true">&raquo;</span><span class="sr-only">'
                                . esc_html__('Next page', 'hello-elementor-child') . '</span>',
                        ]);
                        ?>
                    </div>
                </nav>
            <?php endif; ?>

        <?php else: ?>

            <div
                class="flex flex-col items-center justify-center py-20 px-space-lg text-center rounded-3xl border border-surface-container-highest/30 shadow-sm">
                <span class="material-symbols-outlined !text-6xl text-gray-300 mb-4" aria-hidden="true">search_off</span>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">
                    <?php esc_html_e('No matching results', 'hello-elementor-child'); ?>
                </h2>
                <p class="text-gray-500 text-lg">
                    <?php
                    printf(
                        /* translators: %s: the search query, wrapped in «» */
                        esc_html__('We found no content matching %s. Try other or fewer words, or check the spelling.', 'hello-elementor-child'),
                        '«<bdi>' . esc_html($display_term) . '</bdi>»'
                    );
                    ?>
                </p>
                <p class="text-gray-500 text-base mt-2">
                    <?php esc_html_e('Results may differ with the spelling of a word, so try for example «إنفوجراف» and «انفوجراف».', 'hello-elementor-child'); ?>
                </p>
                <?php if ($type_filter !== ''): ?>
                    <a href="<?php echo esc_url($all_types_url); ?>"
                        class="mt-space-lg inline-flex items-center rounded-full bg-primary text-on-primary px-4 py-2 text-sm font-semibold transition-opacity hover:opacity-90">
                        <?php esc_html_e('Search all content', 'hello-elementor-child'); ?>
                    </a>
                <?php endif; ?>
            </div>

        <?php endif; ?>

    </section>
</main>

<?php
get_footer();
