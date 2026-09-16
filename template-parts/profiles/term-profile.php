<?php
/**
 * Template Part: Term Profile Header
 * Used for Taxonomy Archives and Department Filter views.
 */

$term = $args['term'] ?? null;
if (!$term instanceof WP_Term) {
    return;
}

$back_url = $args['back_url'] ?? 'javascript:history.back()';
$back_label = $args['back_label'] ?? __('العودة', 'hello-elementor-child');

// ACF requires "taxonomy_termID" for taxonomy meta
$acf_term_id = $term->taxonomy . '_' . $term->term_id;

// 1. Fetch Image
$image_data = get_field('taxonomy_image', $acf_term_id) ?: get_field('taxonomy_image', $term) ?: get_field('image', $acf_term_id);
$image_id = 0;
if (is_array($image_data) && !empty($image_data['ID'])) {
    $image_id = $image_data['ID'];
} elseif (is_numeric($image_data)) {
    $image_id = (int) $image_data;
}

// 2. Fetch Description
$description = get_field('detailed_description', $acf_term_id) ?: get_field('detailed_description', $term);
if (empty($description)) {
    $description = $term->description;
}

// 3. Fetch Extra Info
$extra_info = get_field('extra_info', $acf_term_id) ?: get_field('extra_info', $term);

// Format taxonomy name for the overline label
$tax_obj = get_taxonomy($term->taxonomy);
$tax_label = $tax_obj ? $tax_obj->labels->singular_name : 'PROFILE';
?>

<section
    class="relative bg-white border-b border-gray-100 py-12 md:py-20 mb-12 shadow-[0_10px_40px_-15px_rgba(0,0,0,0.05)]">
    <div class="max-w-6xl mx-auto px-6">

        <!-- Back Button -->
        <div class="mb-10 text-start">
            <a href="<?php echo esc_url($back_url); ?>"
                class="inline-flex items-center text-sm font-semibold text-gray-500 hover:text-primary transition-colors group">
                <span class="ml-2 transition-transform duration-300 group-hover:translate-x-1">&rarr;</span>
                <?php echo esc_html($back_label); ?>
            </a>
        </div>

        <div class="flex flex-col md:flex-row gap-10 md:gap-16 items-start">

            <!-- Content Column -->
            <div class="flex-1 text-start">

                <p class="text-sm font-bold text-primary uppercase tracking-widest mb-3">
                    <?php echo esc_html($tax_label); ?>
                </p>

                <h1 class="text-4xl md:text-5xl font-bold text-gray-900 tracking-tight mb-8">
                    <?php echo esc_html($term->name); ?>
                </h1>

                <!-- Extra Info Repeater (Badges) -->
                <?php if (!empty($extra_info)): ?>
                    <div class="flex flex-wrap gap-4 mb-8">
                        <?php foreach ($extra_info as $info):
                            if (empty($info['label']) || empty($info['value']))
                                continue;
                            ?>
                            <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-2">
                                <span class="text-xs text-gray-400 uppercase tracking-wider block mb-0.5 font-semibold">
                                    <?php echo esc_html($info['label']); ?>
                                </span>
                                <span class="text-sm font-bold text-gray-900">
                                    <?php echo esc_html($info['value']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Detailed Description -->
                <?php if (!empty($description)): ?>
                    <div class="prose prose-gray prose-lg max-w-none text-gray-600 leading-relaxed mb-8">
                        <?php echo wp_kses_post(wpautop($description)); ?>
                    </div>
                <?php endif; ?>

                <!-- Social Links Mini-Row -->
                <?php
                $target_term = have_rows('social_links', $acf_term_id) ? $acf_term_id : (have_rows('social_links', $term) ? $term : false);

                if ($target_term && have_rows('social_links', $target_term)):
                    ?>
                    <div class="flex flex-wrap gap-3 pt-4 border-t border-gray-100">
                        <?php
                        while (have_rows('social_links', $target_term)):
                            the_row();
                            $url = get_sub_field('link');
                            $platform_val = get_sub_field('platform') ?: 'fas fa-globe';
                            ?>
                            <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"
                                class="w-10 h-10 flex items-center justify-center rounded-full bg-gray-50 text-gray-400 hover:bg-primary hover:text-white hover:shadow-lg hover:-translate-y-1 transition-all duration-300 text-lg">
                                <i class="<?php echo esc_attr($platform_val); ?>"></i>
                            </a>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Image Column -->
            <?php if ($image_id): ?>
                <div
                    class="w-48 md:w-72 flex-shrink-0 bg-gray-50 rounded-3xl overflow-hidden border border-gray-100 shadow-xl shadow-gray-200/50">
                    <?php echo wp_get_attachment_image($image_id, 'large', false, ['class' => 'w-full h-auto object-cover']); ?>
                </div>
            <?php else: ?>
                <div
                    class="w-48 md:w-72 flex-shrink-0 bg-gray-50 rounded-3xl overflow-hidden border border-gray-100 shadow-xl shadow-gray-200/50 flex items-center justify-center aspect-[3/4]">
                    <span class="material-symbols-outlined text-6xl text-gray-300">person</span>
                </div>
            <?php endif; ?>

        </div>

    </div>
</section>