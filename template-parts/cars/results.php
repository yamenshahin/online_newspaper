<?php
$state = $args['state'] ?? get_car_filter_state();
$query = $args['query'] ?? null;

if (!$query instanceof WP_Query) {
    return;
}

$is_car_archive = array_key_exists('is_car_archive', $args)
    ? (bool) $args['is_car_archive']
    : (
        isset($_POST['is_car_archive'])
        ? '1' === sanitize_text_field(
            wp_unslash($_POST['is_car_archive'])
        )
        : (is_post_type_archive('car') || is_page('car-directory'))
    );
?>

<?php if ($query->have_posts()): ?>
    <div class="flex items-center justify-between mb-4">
        <p class="text-sm font-bold text-on-surface-variant text-start">
            <?php
            echo esc_html(
                sprintf(
                    /* translators: %s: number of results */
                    __('%s نتيجة', 'hello-elementor-child'),
                    number_format_i18n((int) $query->found_posts)
                )
            );
            ?>
        </p>
    </div>

    <style>
        .cars-results-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
        }

        @media (min-width: 640px) {
            .cars-results-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (min-width: 1024px) {
            .cars-results-grid {
                grid-template-columns: repeat(6, minmax(0, 1fr));
            }
        }
    </style>

    <!-- Responsive Grid -->
    <div class="cars-results-grid mb-10">
        <?php
        while ($query->have_posts()):
            $query->the_post();

            $makers = wp_get_post_terms(get_the_ID(), 'car_maker', ['fields' => 'names']);
            $years = wp_get_post_terms(get_the_ID(), 'car_year', ['fields' => 'names']);
            $maker_name = !empty($makers) && !is_wp_error($makers) ? $makers[0] : '';
            $year_name = !empty($years) && !is_wp_error($years) ? $years[0] : '';
            ?>

            <a href="<?php the_permalink(); ?>"
                class="group flex flex-col justify-between p-3 rounded-xl bg-white border border-gray-200 hover:border-primary hover:shadow-md transition-all text-center min-h-[100px]">

                <div class="text-[11px] font-bold text-primary truncate mb-1">
                    <?php if ($maker_name): ?>
                        <span><?php echo esc_html($maker_name); ?></span>
                    <?php endif; ?>
                    <?php if ($maker_name && $year_name): ?>
                        <span class="text-gray-300 mx-0.5">•</span>
                    <?php endif; ?>
                    <?php if ($year_name): ?>
                        <span><?php echo esc_html($year_name); ?></span>
                    <?php endif; ?>
                </div>

                <h3
                    class="text-xs font-bold text-gray-900 group-hover:text-primary transition-colors line-clamp-2 leading-relaxed my-auto">
                    <?php the_title(); ?>
                </h3>

                <div
                    class="mt-2 pt-1.5 border-t border-gray-100 flex items-center justify-center gap-1 text-[11px] font-bold text-gray-500 group-hover:text-primary">
                    <span><?php esc_html_e('عرض التفاصيل', 'hello-elementor-child'); ?></span>
                </div>
            </a>
        <?php endwhile; ?>
    </div>

    <!-- Navigation Footer -->
    <?php if ($is_car_archive): ?>
        <?php if ((int) $query->max_num_pages > 1): ?>
            <!-- Pagination for Archive Page -->
            <nav class="flex justify-center mt-space-xl" aria-label="<?php esc_attr_e('Pagination', 'hello-elementor-child'); ?>">
                <div
                    class="inline-flex items-center gap-2 flex-wrap justify-center [&_.page-numbers]:inline-flex [&_.page-numbers]:items-center [&_.page-numbers]:justify-center [&_.page-numbers]:min-w-[44px] [&_.page-numbers]:h-[44px] [&_.page-numbers]:px-2 [&_.page-numbers]:rounded-full [&_.page-numbers]:font-bold [&_.page-numbers]:shadow-sm [&_.current]:bg-primary [&_.current]:text-on-primary">
                    <?php
                    $add_args = $state['taxes'];
                    if ($state['search'] !== '') {
                        $add_args['car_s'] = $state['search'];
                    }
                    foreach ($state['ranges'] as $prefix => $range) {
                        if ($range['min'] !== null) {
                            $add_args[$prefix . '_min'] = $range['min'];
                        }
                        if ($range['max'] !== null) {
                            $add_args[$prefix . '_max'] = $range['max'];
                        }
                    }
                    foreach ($state['text'] as $param => $value) {
                        $add_args[$param] = $value;
                    }

                    echo paginate_links([
                        'base' => trailingslashit(home_url('/car-directory/')) . '%_%',
                        'format' => 'page/%#%/',
                        'total' => (int) $query->max_num_pages,
                        'current' => max(1, (int) $state['paged']),
                        'prev_text' => '«',
                        'next_text' => '»',
                        'add_args' => $add_args ?: false,
                    ]);
                    ?>
                </div>
            </nav>
        <?php endif; ?>
    <?php else: ?>
        <!-- Simple Show More button on home/other pages -->
        <div class="flex justify-center mt-8">
            <a href="<?php echo esc_url(home_url('/car-directory/')); ?>"
                class="inline-flex items-center justify-center px-6 py-3 rounded-full font-bold text-sm bg-primary text-white hover:opacity-90 shadow-sm transition-all">
                <?php esc_html_e('عرض المزيد من السيارات', 'hello-elementor-child'); ?>
            </a>
        </div>
    <?php endif; ?>

    <?php wp_reset_postdata(); ?>
<?php else: ?>
    <div class="text-center py-16 rounded-2xl border border-dashed border-gray-300">
        <p class="text-gray-500 font-medium">
            <?php esc_html_e('لا توجد سيارات مطابقة.', 'hello-elementor-child'); ?>
        </p>
    </div>
<?php endif; ?>