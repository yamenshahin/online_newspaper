<?php
/**
 * Flexible Content: Programs Section (logo tabs)
 * Works on Department pages, entity archives and the Homepage.
 *
 * A row of program logos: every program_series term with episodes in scope (department ± URL filters), the program
 * with the newest episode first. A click on a logo shows that program's latest episodes in the grid of the posts
 * section (`grid_layout`, inc/section-grid.php). All panels are rendered; assets/js/program-tabs.js switches them and
 * scrolls the logo row. A program without a logo (term field `logo`) gets a placeholder tile with its name.
 */

$department = $args['department'] ?? null;
$section = $args['section'] ?? [];

if (!empty($section)) {
    $section_title = $section['section_title'] ?? '';
    $subtitle = $section['subtitle'] ?? '';
    $posts_limit = $section['posts_limit'] ?? 4;
} else {
    $section_title = get_sub_field('section_title');
    $subtitle = get_sub_field('subtitle');
    $posts_limit = get_sub_field('posts_limit') ?: 4;
}
$grid_layout = get_section_grid_layout($section);
$episodes_per_program = max(1, get_section_grid_post_count($grid_layout, $posts_limit));
$section_title = $section_title ?: __('Our programs', 'hello-elementor-child');

$tax_query = ['relation' => 'AND'];

if ($department instanceof WP_Term) {
    $tax_query[] = [
        'taxonomy' => 'department',
        'field' => 'term_id',
        'terms' => $department->term_id,
    ];
}

$more_link_args = [];

foreach (get_filterable_taxonomies() as $tax) {
    if (!empty($_GET[$tax]) && is_scalar($_GET[$tax])) {
        $term_slug = sanitize_text_field(wp_unslash($_GET[$tax]));
        $tax_query[] = [
            'taxonomy' => $tax,
            'field' => 'slug',
            'terms' => $term_slug,
        ];
        $more_link_args[$tax] = $term_slug;
    }
}

// Episodes in scope, newest first, so a program's first episode in the list is its latest one.
$episode_ids = get_posts([
    'post_type' => 'program',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'no_found_rows' => true,
    'ignore_sticky_posts' => true,
    'tax_query' => $tax_query,
]);

$series_rows = $episode_ids ? wp_get_object_terms($episode_ids, 'program_series', ['fields' => 'all_with_object_id']) : [];
$series_by_episode = [];
foreach (is_wp_error($series_rows) ? [] : $series_rows as $series) {
    $series_by_episode[(int) $series->object_id][] = $series;
}

$programs = []; // term_id => ['term' => WP_Term, 'episode_ids' => int[]]
foreach ($episode_ids as $episode_id) {
    foreach ($series_by_episode[(int) $episode_id] ?? [] as $series) {
        if (!isset($programs[$series->term_id])) {
            $programs[$series->term_id] = ['term' => $series, 'episode_ids' => []];
        }
        if (count($programs[$series->term_id]['episode_ids']) < $episodes_per_program) {
            $programs[$series->term_id]['episode_ids'][] = (int) $episode_id;
        }
    }
}

if (!$programs) {
    return;
}
$programs = array_values($programs);

if ($department instanceof WP_Term) {
    $more_link = add_query_arg(array_merge(['view' => 'program'], $more_link_args), get_term_link($department));
} else {
    $more_link = get_post_type_archive_link('program');
    if (!empty($more_link_args)) {
        $more_link = add_query_arg($more_link_args, $more_link);
    }
}

$more_icon_path = get_stylesheet_directory() . '/assets/images/more-icon.svg';
$more_icon_uri = get_stylesheet_directory_uri() . '/assets/images/more-icon.svg';

$has_tabs = count($programs) > 1;
if ($has_tabs) {
    $script_path = get_stylesheet_directory() . '/assets/js/program-tabs.js';
    wp_enqueue_script(
        'hello-elementor-child-program-tabs',
        get_stylesheet_directory_uri() . '/assets/js/program-tabs.js',
        [],
        file_exists($script_path) ? filemtime($script_path) : null,
        true
    );
}

$uid = wp_unique_id('program-tabs-');
?>

<section id="<?php echo esc_attr($uid); ?>" class="w-full px-margin-mobile lg:px-margin py-space-xl" data-program-tabs>
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-space-md mb-space-lg">
        <div class="flex flex-col gap-space-xs text-start">
            <div class="flex items-center gap-space-xs">
                <?php render_department_icon($department ?? null); ?>
                <h2 class="font-headline-lg text-headline-lg text-on-background">
                    <?php echo esc_html($section_title); ?>
                </h2>
                <span
                    class="px-space-sm py-0.5 rounded-full bg-primary-container text-on-primary font-label-caps text-label-caps font-bold"><?php esc_html_e('New episodes', 'hello-elementor-child'); ?></span>
            </div>
            <p class="font-body-sm text-body-sm text-on-surface-variant">
                <?php echo esc_html($subtitle ?: __('The newest episodes from our programs and series', 'hello-elementor-child')); ?>
            </p>
        </div>

        <a href="<?php echo esc_url($more_link); ?>"
            class="inline-flex items-center gap-1.5 self-start md:self-auto text-base md:text-lg font-medium text-primary hover:opacity-70 transition-opacity">
            <?php if (file_exists($more_icon_path)): ?>
                <span class="inline-flex w-5 h-5 shrink-0 [&>svg]:w-full [&>svg]:h-full" aria-hidden="true">
                    <?php echo file_get_contents($more_icon_path); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents ?>
                </span>
            <?php else: ?>
                <img src="<?php echo esc_url($more_icon_uri); ?>" alt="" class="w-5 h-5" width="20" height="20" />
            <?php endif; ?>
            <span><?php esc_html_e('More', 'hello-elementor-child'); ?></span>
        </a>
    </div>

    <div class="relative mb-space-lg">
        <?php if ($has_tabs): // Arrows: mouse devices (touch swipes), only while the logo row overflows; RTL: previous on the right. ?>
            <button type="button" data-program-scroll="prev" hidden
                class="absolute -start-4 top-1/2 -translate-y-1/2 z-10 hidden pointer-fine:flex items-center justify-center w-9 h-9 rounded-full bg-white border border-outline-variant shadow-md text-on-background hover:text-primary cursor-pointer transition-opacity disabled:opacity-0 disabled:pointer-events-none"
                aria-label="<?php esc_attr_e('Previous', 'hello-elementor-child'); ?>">
                <i class="fas fa-angle-right" aria-hidden="true"></i>
            </button>
        <?php endif; ?>

        <div role="tablist" aria-label="<?php echo esc_attr($section_title); ?>" data-program-strip
            class="flex gap-space-md overflow-x-auto snap-x pointer-fine:snap-none p-1 scroll-px-1 select-none [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <?php foreach ($programs as $i => $program):
                $term = $program['term'];
                $logo = get_field('logo', $term);
                $logo_id = is_array($logo) ? (int) ($logo['ID'] ?? 0) : (int) $logo;
                ?>
                <button type="button" role="tab" data-program-tab id="<?php echo esc_attr("{$uid}-tab-{$i}"); ?>"
                    aria-controls="<?php echo esc_attr("{$uid}-panel-{$i}"); ?>"
                    aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>" title="<?php echo esc_attr($term->name); ?>"
                    class="group relative shrink-0 snap-start w-36 h-24 lg:w-44 lg:h-28 rounded-xl border border-outline-variant bg-white overflow-hidden cursor-pointer transition-colors hover:border-primary aria-selected:border-primary aria-selected:bg-surface-container-high focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                    <?php if ($logo_id): ?>
                        <?php echo wp_get_attachment_image($logo_id, 'medium_large', false, [
                            // multiply: the logo's white background takes the tile color of the active tab.
                            'class' => 'w-full h-full object-contain p-1 mix-blend-multiply',
                            'alt' => $term->name,
                            'sizes' => '(min-width: 1024px) 176px, 144px',
                            'loading' => 'lazy',
                        ]); ?>
                    <?php else: ?>
                        <span class="flex flex-col items-center justify-center gap-1 w-full h-full p-2 text-center">
                            <span class="material-symbols-outlined text-primary" aria-hidden="true">tv</span>
                            <span class="text-[13px] leading-tight font-bold text-on-background line-clamp-2"><?php echo esc_html($term->name); ?></span>
                        </span>
                    <?php endif; ?>

                    <svg class="pointer-events-none absolute start-0 top-1/2 -translate-y-1/2 hidden w-3 h-6 text-primary group-aria-selected:block"
                        viewBox="0 0 12 24" aria-hidden="true">
                        <path d="M12 0 0 12l12 12v-7l-5-5 5-5z" fill="currentColor" />
                    </svg>
                </button>
            <?php endforeach; ?>
        </div>

        <?php if ($has_tabs): ?>
            <button type="button" data-program-scroll="next" hidden
                class="absolute -end-4 top-1/2 -translate-y-1/2 z-10 hidden pointer-fine:flex items-center justify-center w-9 h-9 rounded-full bg-white border border-outline-variant shadow-md text-on-background hover:text-primary cursor-pointer transition-opacity disabled:opacity-0 disabled:pointer-events-none"
                aria-label="<?php esc_attr_e('Next', 'hello-elementor-child'); ?>">
                <i class="fas fa-angle-left" aria-hidden="true"></i>
            </button>
        <?php endif; ?>
    </div>

    <?php foreach ($programs as $i => $program):
        $episodes = new WP_Query([
            'post_type' => 'program',
            'post__in' => $program['episode_ids'],
            'orderby' => 'post__in',
            'posts_per_page' => count($program['episode_ids']),
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
        ]);
        ?>
        <div role="tabpanel" id="<?php echo esc_attr("{$uid}-panel-{$i}"); ?>"
            aria-labelledby="<?php echo esc_attr("{$uid}-tab-{$i}"); ?>" <?php echo 0 === $i ? '' : 'hidden'; ?>>
            <div class="<?php echo esc_attr(get_section_grid_classes($grid_layout)); ?>">
                <?php
                $card_index = 0;
                while ($episodes->have_posts()):
                    $episodes->the_post();
                    render_section_grid_card($grid_layout, 'program', [], $card_index++, $episodes->post_count);
                endwhile;
                ?>
            </div>
        </div>
    <?php endforeach; ?>
</section>

<?php wp_reset_postdata(); ?>
