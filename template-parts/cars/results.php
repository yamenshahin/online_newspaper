<?php
/**
 * Cars filter: result count, tiles, and pagination (archive) or a "show more" link to the archive (elsewhere).
 * Re-rendered over AJAX; the AJAX handler posts is_car_archive because it has no page context.
 */
$state = $args['state'] ?? get_car_filter_state();
$query = $args['query'] ?? null;
$archive_url = $args['archive_url'] ?? get_cars_archive_url();

if (!$query instanceof WP_Query) {
    return;
}

if (array_key_exists('is_car_archive', $args)) {
    $is_car_archive = (bool) $args['is_car_archive'];
} elseif (isset($_POST['is_car_archive'])) {
    $is_car_archive = sanitize_text_field(wp_unslash($_POST['is_car_archive'])) === '1';
} else {
    $is_car_archive = is_post_type_archive('car');
}
?>

<?php if ($query->have_posts()): ?>
    <p class="text-sm font-bold text-on-surface-variant text-start mb-4" aria-live="polite">
        <?php echo esc_html(car_filter_count_text((int) $query->found_posts)); ?>
    </p>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 mb-10">
        <?php
        while ($query->have_posts()):
            $query->the_post();
            $tile = get_car_tile_data(get_the_ID());
            ?>
            <a href="<?php the_permalink(); ?>"
                class="group flex flex-col p-3 rounded-xl bg-white border border-gray-200 hover:border-primary hover:shadow-md transition-all text-start">

                <div class="aspect-video rounded-xl overflow-hidden bg-surface-container-low mb-2">
                    <img src="<?php echo esc_url($tile['image']); ?>" alt="" loading="lazy" decoding="async"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                </div>

                <?php if ($tile['eyebrow'] !== ''): ?>
                    <div class="text-[11px] font-bold text-primary truncate mb-1"><?php echo esc_html($tile['eyebrow']); ?></div>
                <?php endif; ?>

                <h3 class="text-xs font-bold text-gray-900 group-hover:text-primary transition-colors line-clamp-2 leading-relaxed">
                    <?php the_title(); ?>
                </h3>

                <?php if ($tile['price'] !== ''): ?>
                    <div class="mt-auto pt-2 border-t border-gray-100 text-xs font-bold text-on-surface-variant">
                        <?php echo esc_html($tile['price']); ?>
                    </div>
                <?php endif; ?>
            </a>
        <?php endwhile; ?>
    </div>

    <?php if ($is_car_archive): ?>
        <?php if ((int) $query->max_num_pages > 1): ?>
            <nav class="flex justify-center mt-space-xl" aria-label="<?php esc_attr_e('تصفح الصفحات', 'hello-elementor-child'); ?>">
                <div class="inline-flex items-center gap-2 flex-wrap justify-center [&_.page-numbers]:inline-flex [&_.page-numbers]:items-center [&_.page-numbers]:justify-center [&_.page-numbers]:min-w-[44px] [&_.page-numbers]:h-[44px] [&_.page-numbers]:px-2 [&_.page-numbers]:rounded-full [&_.page-numbers]:font-bold [&_.page-numbers]:shadow-sm [&_.current]:bg-primary [&_.current]:text-on-primary">
                    <?php
                    $add_args = get_car_filter_params($state);
                    $links = paginate_links([
                        'base' => trailingslashit($archive_url) . '%_%',
                        'format' => 'page/%#%/',
                        'total' => (int) $query->max_num_pages,
                        'current' => max(1, (int) $state['paged']),
                        'prev_text' => '«',
                        'next_text' => '»',
                        'add_args' => $add_args ? urlencode_deep($add_args) : false,
                    ]);
                    // list facets as name[]=..., the same shape as the form's own submit and the chip links
                    echo preg_replace('/%5B\d+%5D=/', '%5B%5D=', (string) $links); // phpcs:ignore WordPress.Security.EscapeOutput -- paginate_links escapes its output
                    ?>
                </div>
            </nav>
        <?php endif; ?>
    <?php else: ?>
        <div class="flex justify-center mt-8">
            <a href="<?php echo esc_url(get_cars_filter_url([], $state, $archive_url)); ?>"
                class="inline-flex items-center justify-center px-6 py-3 rounded-full font-bold text-sm bg-primary text-white hover:opacity-90 shadow-sm transition-all">
                <?php esc_html_e('عرض المزيد من السيارات', 'hello-elementor-child'); ?>
            </a>
        </div>
    <?php endif; ?>

    <?php wp_reset_postdata(); ?>
<?php else: ?>
    <div class="text-center py-16 rounded-2xl border border-dashed border-gray-300">
        <p class="text-gray-500 font-medium"><?php echo esc_html(car_filter_count_text(0)); ?></p>
    </div>
<?php endif; ?>
