<?php
/**
 * Single car — Phase 1 (specs display can expand in Phase 2).
 */
get_header();

while (have_posts()):
    the_post();
    $maker = get_the_terms(get_the_ID(), 'car_maker');
    $year = get_the_terms(get_the_ID(), 'car_year');
    ?>
    <main class="site-main bg-white pb-24">
        <article class="max-w-4xl mx-auto px-6 pt-12">
            <?php if (has_post_thumbnail()): ?>
                <div class="rounded-2xl overflow-hidden aspect-video mb-8 bg-gray-100">
                    <?php the_post_thumbnail('large', ['class' => 'w-full h-full object-cover']); ?>
                </div>
            <?php endif; ?>

            <header class="text-start mb-8">
                <p class="text-sm font-semibold text-primary mb-2">
                    <?php
                    $bits = [];
                    if (!empty($maker) && !is_wp_error($maker)) {
                        $bits[] = $maker[0]->name;
                    }
                    if (!empty($year) && !is_wp_error($year)) {
                        $bits[] = $year[0]->name;
                    }
                    echo esc_html(implode(' · ', $bits));
                    ?>
                </p>
                <h1 class="text-3xl md:text-4xl font-bold text-gray-900"><?php the_title(); ?></h1>
            </header>

            <div class="prose max-w-none text-start mb-12">
                <?php the_content(); ?>
            </div>

            <?php
            // Phase 1: simple spec list (Phase 2 adds filters on these).
            $specs = [
                'power' => __('القوة', 'hello-elementor-child'),
                'torque' => __('عزم الدوران', 'hello-elementor-child'),
                'engine_capacity' => __('سعة المحرك', 'hello-elementor-child'),
                'transmission' => __('ناقل الحركة', 'hello-elementor-child'),
                'drive_system' => __('نظام الدفع', 'hello-elementor-child'),
                'number_of_seats' => __('المقاعد', 'hello-elementor-child'),
                'price_in_riyals' => __('السعر (ر.س)', 'hello-elementor-child'),
            ];
            $rows = [];
            foreach ($specs as $key => $label) {
                $val = get_field($key);
                if ($val !== null && $val !== '' && $val !== false) {
                    $rows[] = [$label, $val];
                }
            }
            if ($rows):
                ?>
                <table class="w-full text-start border border-gray-100 rounded-xl overflow-hidden mb-12">
                    <tbody>
                        <?php foreach ($rows as $i => $row): ?>
                            <tr class="<?php echo $i % 2 === 0 ? 'bg-gray-50' : 'bg-white'; ?>">
                                <th class="px-4 py-3 font-semibold text-gray-600 w-1/3"><?php echo esc_html($row[0]); ?></th>
                                <td class="px-4 py-3"><?php echo esc_html((string) $row[1]); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </article>
    </main>
    <?php
endwhile;

get_footer();