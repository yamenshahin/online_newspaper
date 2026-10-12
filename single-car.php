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
                <h1 class="text-3xl md:text-4xl font-bold text-gray-900">
                    <?php the_title(); ?>
                </h1>
            </header>

            <div class="prose max-w-none text-start mb-12">
                <?php the_content(); ?>
            </div>

            <?php
            // Section header labels map for JSON categories
            $section_labels = [
                'engine_performance' => __('أداء المحرك والأولويات', 'hello-elementor-child'),
                'dimensions_design' => __('الأبعاد والتصميم الخارجي', 'hello-elementor-child'),
                'interior_tech' => __('المقصورة والتقنيات', 'hello-elementor-child'),
                'safety_adas' => __('الأمان وأنظمة المساعدة', 'hello-elementor-child'),
            ];

            // Decode specification JSON field
            $raw_json = get_field('specification_data_json');
            $spec_groups = [];

            if (!empty($raw_json)) {
                if (is_array($raw_json)) {
                    $spec_groups = $raw_json;
                } else {
                    $decoded = json_decode($raw_json, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $spec_groups = $decoded;
                    }
                }
            }
            ?>

            <?php if (!empty($spec_groups)): ?>
                <!-- JSON Categorized Specifications with proper vertical spacing -->
                <div class="space-y-8 mb-12">
                    <?php foreach ($spec_groups as $group_key => $items): ?>
                        <?php if (empty($items) || !is_array($items))
                            continue; ?>
                        <?php $group_title = $section_labels[$group_key] ?? ucfirst(str_replace('_', ' ', $group_key)); ?>

                        <div class="border border-gray-100 rounded-xl overflow-hidden shadow-xs mb-8 last:mb-0">
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-100 font-bold text-gray-800 text-sm">
                                <?php echo esc_html($group_title); ?>
                            </div>
                            <table class="w-full text-start">
                                <tbody>
                                    <?php
                                    $row_idx = 0;
                                    foreach ($items as $item):
                                        $label = $item['label_ar'] ?? '';
                                        $value = $item['value_ar'] ?? '';
                                        if ($label === '' || $value === '')
                                            continue;
                                        ?>
                                        <tr class="<?php echo $row_idx % 2 === 0 ? 'bg-gray-50/50' : 'bg-white'; ?>">
                                            <th class="px-4 py-3 font-semibold text-gray-600 w-1/3 text-start">
                                                <?php echo esc_html($label); ?>
                                            </th>
                                            <td class="px-4 py-3 text-gray-900">
                                                <?php echo esc_html($value); ?>
                                            </td>
                                        </tr>
                                        <?php
                                        $row_idx++;
                                    endforeach;
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <!-- Fallback to Legacy Key-Value Fields -->
                <?php
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
                                    <th class="px-4 py-3 font-semibold text-gray-600 w-1/3">
                                        <?php echo esc_html($row[0]); ?>
                                    </th>
                                    <td class="px-4 py-3">
                                        <?php echo esc_html((string) $row[1]); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>

        </article>
    </main>
    <?php
endwhile;

get_footer();