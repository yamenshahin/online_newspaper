<?php
/**
 * Flexible Content: Cars directory
 * Phase 1 — tax filters + title search
 * Phase 2 — spec meta (ranges + transmission/drive)
 *
 * Cars are NOT filtered by department taxonomy.
 */
$section = $args['section'] ?? [];

if (!empty($section)) {
    $section_title = $section['section_title'] ?? '';
    $subtitle = $section['subtitle'] ?? '';
    $posts_limit = $section['posts_limit'] ?? 12;
} else {
    $section_title = get_sub_field('section_title');
    $subtitle = get_sub_field('subtitle');
    $posts_limit = get_sub_field('posts_limit') ?: 12;
}

$section_title = $section_title ?: __('دليل السيارات', 'hello-elementor-child');
$state = get_car_filter_state();
$facets = get_car_filter_taxonomies();
$base_url = get_cars_filter_base_url();

$query = query_cars_directory(
    [
        'posts_per_page' => absint($posts_limit),
        'paged' => $state['paged'],
    ]
);

$facet_limits = [
    'car_model' => 80,
    'car_body_type' => 60,
    'car_maker' => 0,
    'car_year' => 0,
    'car_origin' => 0,
    'car_fuel_type' => 0,
];

$range_labels = [
    'price' => __('السعر', 'hello-elementor-child'),
    'power' => __('القوة', 'hello-elementor-child'),
    'torque' => __('العزم', 'hello-elementor-child'),
    'engine' => __('المحرك', 'hello-elementor-child'),
    'seats' => __('المقاعد', 'hello-elementor-child'),
];

$text_labels = [
    'transmission' => __('ناقل الحركة', 'hello-elementor-child'),
    'drive' => __('نظام الدفع', 'hello-elementor-child'),
];

$price = $state['ranges']['price'] ?? ['min' => null, 'max' => null];
$power = $state['ranges']['power'] ?? ['min' => null, 'max' => null];
$torque = $state['ranges']['torque'] ?? ['min' => null, 'max' => null];
$engine = $state['ranges']['engine'] ?? ['min' => null, 'max' => null];
$seats = $state['ranges']['seats'] ?? ['min' => null, 'max' => null];

$has_active = (
    $state['search'] !== ''
    || !empty($state['taxes'])
    || !empty($state['ranges'])
    || !empty($state['text'])
);

/**
 * Hidden inputs to preserve filter state inside GET forms.
 *
 * @param string[] $skip Keys to omit (e.g. car_s, price_min).
 */
$cars_hidden_state = static function (array $skip = []) use ($state): void {
    foreach ($state['taxes'] as $tax => $slug) {
        if (in_array($tax, $skip, true)) {
            continue;
        }
        printf(
            '<input type="hidden" name="%s" value="%s" />' . "\n",
            esc_attr($tax),
            esc_attr($slug)
        );
    }
    if ($state['search'] !== '' && !in_array('car_s', $skip, true)) {
        printf(
            '<input type="hidden" name="car_s" value="%s" />' . "\n",
            esc_attr($state['search'])
        );
    }
    foreach ($state['text'] as $param => $value) {
        if (in_array($param, $skip, true)) {
            continue;
        }
        printf(
            '<input type="hidden" name="%s" value="%s" />' . "\n",
            esc_attr($param),
            esc_attr($value)
        );
    }
    foreach ($state['ranges'] as $prefix => $range) {
        $min_key = $prefix . '_min';
        $max_key = $prefix . '_max';
        if ($range['min'] !== null && !in_array($min_key, $skip, true)) {
            printf(
                '<input type="hidden" name="%s" value="%s" />' . "\n",
                esc_attr($min_key),
                esc_attr((string) $range['min'])
            );
        }
        if ($range['max'] !== null && !in_array($max_key, $skip, true)) {
            printf(
                '<input type="hidden" name="%s" value="%s" />' . "\n",
                esc_attr($max_key),
                esc_attr((string) $range['max'])
            );
        }
    }
};
?>

<section class="cars-directory w-full px-margin-mobile lg:px-margin py-space-xl" id="cars-directory">

    <div class="flex flex-col gap-space-md mb-8 text-start">
        <h2 class="font-headline-lg text-headline-lg text-on-background">
            <?php echo esc_html($section_title); ?>
        </h2>
        <?php if ($subtitle): ?>
                <p class="font-body-sm text-on-surface-variant">
                    <?php echo esc_html($subtitle); ?>
                </p>
        <?php endif; ?>

        <!-- Search -->
        <form method="get" action="<?php echo esc_url($base_url); ?>" class="w-full max-w-xl">
            <?php $cars_hidden_state(['car_s']); ?>
            <label class="relative block">
                <span class="material-symbols-outlined absolute inset-y-0 start-3 flex items-center text-on-surface-variant text-[20px]" aria-hidden="true">search</span>
                <input
                    type="search"
                    name="car_s"
                    value="<?php echo esc_attr($state['search']); ?>"
                    placeholder="<?php esc_attr_e('ابحث عن سيارة…', 'hello-elementor-child'); ?>"
                    class="w-full rounded-full border border-surface-container-highest bg-surface-container-lowest py-3 pe-4 ps-11 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                />
            </label>
        </form>
    </div>

    <!-- Active chips -->
    <?php if ($has_active): ?>
            <div class="flex flex-wrap gap-2 mb-6">
                <?php if ($state['search'] !== ''): ?>
                        <a href="<?php echo esc_url(get_cars_filter_url(['car_s' => null])); ?>"
                            class="inline-flex items-center gap-1 rounded-full bg-primary text-on-primary px-3 py-1 text-sm font-semibold">
                            <?php echo esc_html($state['search']); ?>
                            <span aria-hidden="true">×</span>
                        </a>
                <?php endif; ?>

                <?php foreach ($state['taxes'] as $tax => $slug):
                    $t = get_term_by('slug', $slug, $tax);
                    if (!$t || is_wp_error($t)) {
                        continue;
                    }
                    ?>
                        <a href="<?php echo esc_url(get_cars_filter_url([$tax => null])); ?>"
                            class="inline-flex items-center gap-1 rounded-full bg-primary text-on-primary px-3 py-1 text-sm font-semibold">
                            <?php echo esc_html($t->name); ?>
                            <span aria-hidden="true">×</span>
                        </a>
                <?php endforeach; ?>

                <?php foreach ($state['ranges'] as $prefix => $range):
                    $parts = [];
                    if ($range['min'] !== null) {
                        $parts[] = '≥ ' . $range['min'];
                    }
                    if ($range['max'] !== null) {
                        $parts[] = '≤ ' . $range['max'];
                    }
                    $chip = ($range_labels[$prefix] ?? $prefix) . ': ' . implode(' ', $parts);
                    ?>
                        <a href="<?php echo esc_url(get_cars_filter_url([$prefix . '_min' => null, $prefix . '_max' => null])); ?>"
                            class="inline-flex items-center gap-1 rounded-full bg-primary text-on-primary px-3 py-1 text-sm font-semibold">
                            <?php echo esc_html($chip); ?>
                            <span aria-hidden="true">×</span>
                        </a>
                <?php endforeach; ?>

                <?php foreach ($state['text'] as $param => $value): ?>
                        <a href="<?php echo esc_url(get_cars_filter_url([$param => null])); ?>"
                            class="inline-flex items-center gap-1 rounded-full bg-primary text-on-primary px-3 py-1 text-sm font-semibold">
                            <?php echo esc_html(($text_labels[$param] ?? $param) . ': ' . $value); ?>
                            <span aria-hidden="true">×</span>
                        </a>
                <?php endforeach; ?>

                <a href="<?php echo esc_url($base_url); ?>"
                    class="text-sm text-primary font-semibold underline-offset-2 hover:underline self-center">
                    <?php esc_html_e('مسح الكل', 'hello-elementor-child'); ?>
                </a>
            </div>
    <?php endif; ?>

    <!-- Taxonomy facets -->
    <div class="flex flex-col gap-5 mb-8">
        <?php foreach ($facets as $tax => $label):
            $terms = get_car_facet_terms($tax, (int) ($facet_limits[$tax] ?? 0));
            if (empty($terms)) {
                continue;
            }
            $active = $state['taxes'][$tax] ?? '';
            ?>
                <div class="text-start">
                    <div class="text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-2">
                        <?php echo esc_html($label); ?>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="<?php echo esc_url(get_cars_filter_url([$tax => null])); ?>"
                            class="inline-flex rounded-full px-3 py-1.5 text-sm font-semibold border transition-colors <?php echo $active === '' ? 'bg-primary text-on-primary border-primary' : 'bg-surface-container-low text-on-surface border-surface-container-highest hover:border-primary'; ?>">
                            <?php esc_html_e('الكل', 'hello-elementor-child'); ?>
                        </a>
                        <?php foreach ($terms as $term):
                            $is_on = ($active === $term->slug);
                            $url = get_cars_filter_url([$tax => $is_on ? null : $term->slug]);
                            ?>
                                <a href="<?php echo esc_url($url); ?>"
                                    class="inline-flex items-center gap-1 rounded-full px-3 py-1.5 text-sm font-semibold border transition-colors <?php echo $is_on ? 'bg-primary text-on-primary border-primary' : 'bg-surface-container-lowest text-on-surface border-surface-container-highest hover:border-primary'; ?>">
                                    <?php echo esc_html($term->name); ?>
                                    <span class="text-[11px] opacity-70">(<?php echo esc_html((string) $term->count); ?>)</span>
                                </a>
                        <?php endforeach; ?>
                    </div>
                </div>
        <?php endforeach; ?>
    </div>

    <!-- Phase 2: price + power (primary ranges) -->
    <form method="get" action="<?php echo esc_url($base_url); ?>"
        class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 rounded-2xl bg-surface-container-low border border-surface-container-highest mb-6">
        <?php
        $cars_hidden_state(
            [
                'price_min',
                'price_max',
                'power_min',
                'power_max',
            ]
        );
        ?>
        <div class="text-start">
            <div class="text-xs font-bold text-on-surface-variant mb-2">
                <?php esc_html_e('السعر (ر.س)', 'hello-elementor-child'); ?>
            </div>
            <div class="flex gap-2">
                <input type="number" name="price_min" min="0" step="1000"
                    placeholder="<?php esc_attr_e('من', 'hello-elementor-child'); ?>"
                    value="<?php echo $price['min'] !== null ? esc_attr((string) $price['min']) : ''; ?>"
                    class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm bg-surface-container-lowest" />
                <input type="number" name="price_max" min="0" step="1000"
                    placeholder="<?php esc_attr_e('إلى', 'hello-elementor-child'); ?>"
                    value="<?php echo $price['max'] !== null ? esc_attr((string) $price['max']) : ''; ?>"
                    class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm bg-surface-container-lowest" />
            </div>
        </div>
        <div class="text-start">
            <div class="text-xs font-bold text-on-surface-variant mb-2">
                <?php esc_html_e('القوة', 'hello-elementor-child'); ?>
            </div>
            <div class="flex gap-2">
                <input type="number" name="power_min" min="0" step="1"
                    placeholder="<?php esc_attr_e('من', 'hello-elementor-child'); ?>"
                    value="<?php echo $power['min'] !== null ? esc_attr((string) $power['min']) : ''; ?>"
                    class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm bg-surface-container-lowest" />
                <input type="number" name="power_max" min="0" step="1"
                    placeholder="<?php esc_attr_e('إلى', 'hello-elementor-child'); ?>"
                    value="<?php echo $power['max'] !== null ? esc_attr((string) $power['max']) : ''; ?>"
                    class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm bg-surface-container-lowest" />
            </div>
        </div>
        <div class="md:col-span-2 flex justify-end">
            <button type="submit" class="rounded-full bg-primary text-on-primary px-6 py-2 text-sm font-bold hover:opacity-90 transition-opacity">
                <?php esc_html_e('تطبيق', 'hello-elementor-child'); ?>
            </button>
        </div>
    </form>

    <!-- Phase 2: advanced meta filters -->
    <details class="mb-10 group">
        <summary class="cursor-pointer text-sm font-bold text-primary flex items-center gap-1 list-none">
            <?php esc_html_e('المزيد من الفلاتر', 'hello-elementor-child'); ?>
            <span class="material-symbols-outlined text-[18px] transition-transform group-open:rotate-180">expand_more</span>
        </summary>

        <div class="mt-4 flex flex-col gap-6">
            <form method="get" action="<?php echo esc_url($base_url); ?>"
                class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 rounded-2xl border border-surface-container-highest bg-surface-container-lowest">
                <?php
                $cars_hidden_state(
                    [
                        'torque_min',
                        'torque_max',
                        'engine_min',
                        'engine_max',
                        'seats_min',
                        'seats_max',
                    ]
                );
                ?>
                <div class="text-start">
                    <div class="text-xs font-bold text-on-surface-variant mb-2">
                        <?php esc_html_e('العزم', 'hello-elementor-child'); ?>
                    </div>
                    <div class="flex gap-2">
                        <input type="number" name="torque_min" min="0" step="1"
                            placeholder="<?php esc_attr_e('من', 'hello-elementor-child'); ?>"
                            value="<?php echo $torque['min'] !== null ? esc_attr((string) $torque['min']) : ''; ?>"
                            class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm" />
                        <input type="number" name="torque_max" min="0" step="1"
                            placeholder="<?php esc_attr_e('إلى', 'hello-elementor-child'); ?>"
                            value="<?php echo $torque['max'] !== null ? esc_attr((string) $torque['max']) : ''; ?>"
                            class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm" />
                    </div>
                </div>
                <div class="text-start">
                    <div class="text-xs font-bold text-on-surface-variant mb-2">
                        <?php esc_html_e('سعة المحرك', 'hello-elementor-child'); ?>
                    </div>
                    <div class="flex gap-2">
                        <input type="number" name="engine_min" min="0" step="0.1"
                            placeholder="<?php esc_attr_e('من', 'hello-elementor-child'); ?>"
                            value="<?php echo $engine['min'] !== null ? esc_attr((string) $engine['min']) : ''; ?>"
                            class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm" />
                        <input type="number" name="engine_max" min="0" step="0.1"
                            placeholder="<?php esc_attr_e('إلى', 'hello-elementor-child'); ?>"
                            value="<?php echo $engine['max'] !== null ? esc_attr((string) $engine['max']) : ''; ?>"
                            class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm" />
                    </div>
                </div>
                <div class="text-start">
                    <div class="text-xs font-bold text-on-surface-variant mb-2">
                        <?php esc_html_e('المقاعد', 'hello-elementor-child'); ?>
                    </div>
                    <div class="flex gap-2">
                        <input type="number" name="seats_min" min="0" step="1"
                            placeholder="<?php esc_attr_e('من', 'hello-elementor-child'); ?>"
                            value="<?php echo $seats['min'] !== null ? esc_attr((string) $seats['min']) : ''; ?>"
                            class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm" />
                        <input type="number" name="seats_max" min="0" step="1"
                            placeholder="<?php esc_attr_e('إلى', 'hello-elementor-child'); ?>"
                            value="<?php echo $seats['max'] !== null ? esc_attr((string) $seats['max']) : ''; ?>"
                            class="w-full rounded-xl border border-surface-container-highest px-3 py-2 text-sm" />
                    </div>
                </div>
                <div class="md:col-span-3 flex justify-end">
                    <button type="submit" class="rounded-full bg-primary text-on-primary px-6 py-2 text-sm font-bold">
                        <?php esc_html_e('تطبيق', 'hello-elementor-child'); ?>
                    </button>
                </div>
            </form>

            <?php
            $text_facets = [
                'transmission' => 'transmission',
                'drive' => 'drive_system',
            ];
            foreach ($text_facets as $param => $meta_key):
                $values = get_car_distinct_meta_values($meta_key);
                if (empty($values)) {
                    continue;
                }
                $active = $state['text'][$param] ?? '';
                ?>
                    <div class="text-start">
                        <div class="text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-2">
                            <?php echo esc_html($text_labels[$param] ?? $param); ?>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="<?php echo esc_url(get_cars_filter_url([$param => null])); ?>"
                                class="inline-flex rounded-full px-3 py-1.5 text-sm font-semibold border transition-colors <?php echo $active === '' ? 'bg-primary text-on-primary border-primary' : 'bg-surface-container-low text-on-surface border-surface-container-highest hover:border-primary'; ?>">
                                <?php esc_html_e('الكل', 'hello-elementor-child'); ?>
                            </a>
                            <?php foreach ($values as $val):
                                $on = ($active === $val);
                                ?>
                                    <a href="<?php echo esc_url(get_cars_filter_url([$param => $on ? null : $val])); ?>"
                                        class="inline-flex rounded-full px-3 py-1.5 text-sm font-semibold border transition-colors <?php echo $on ? 'bg-primary text-on-primary border-primary' : 'bg-surface-container-lowest text-on-surface border-surface-container-highest hover:border-primary'; ?>">
                                        <?php echo esc_html($val); ?>
                                    </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
            <?php endforeach; ?>
        </div>
    </details>

    <!-- Results -->
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

</section>