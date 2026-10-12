<?php
$state = $args['state'] ?? get_car_filter_state();
$query = $args['query'] ?? null;

if (!$query instanceof WP_Query) {
    return;
}
?>

<?php if ($query->have_posts()): ?>
    <p class="text-sm text-on-surface-variant mb-4 text-start">
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

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-gutter mb-10">
        <?php
        while ($query->have_posts()):
            $query->the_post();
            get_template_part('template-parts/cards/card', 'car');
        endwhile;
        ?>
    </div>

    <?php if ((int) $query->max_num_pages > 1): ?>
        <nav class="flex justify-center" aria-label="<?php esc_attr_e('Pagination', 'hello-elementor-child'); ?>">
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

            echo paginate_links(
                [
                    'total' => (int) $query->max_num_pages,
                    'current' => $state['paged'],
                    'prev_text' => '«',
                    'next_text' => '»',
                    'add_args' => $add_args ?: false,
                ]
            );
            ?>
        </nav>
    <?php endif; ?>
    <?php wp_reset_postdata(); ?>
<?php else: ?>
    <div class="text-center py-16 rounded-2xl border border-dashed border-surface-container-highest">
        <p class="text-on-surface-variant">
            <?php esc_html_e('لا توجد سيارات مطابقة.', 'hello-elementor-child'); ?>
        </p>
    </div>
<?php endif; ?>