<?php
/**
 * Template Part: Term Profile Header
 * Used for Taxonomy Archives and Department Filter views.
 * Layout follows document direction (dir=rtl | ltr) — no flex-row-reverse.
 */

$term = $args['term'] ?? null;
if (!$term instanceof WP_Term) {
    return;
}

$back_url = $args['back_url'] ?? home_url('/');
$back_label = $args['back_label'] ?? __('العودة', 'hello-elementor-child');

$acf_term_id = $term->taxonomy . '_' . $term->term_id;

// Image
$image_data = get_field('taxonomy_image', $acf_term_id)
    ?: get_field('taxonomy_image', $term)
    ?: get_field('image', $acf_term_id);

$image_id = 0;
$fallback_image_url = '';

if (is_array($image_data) && !empty($image_data['ID'])) {
    $image_id = (int) $image_data['ID'];
} elseif (is_numeric($image_data)) {
    $image_id = (int) $image_data;
} elseif (empty($image_data)) {
    // Fallback logic for specific taxonomies if no ACF image is set
    if ($term->taxonomy === 'government_entity') {
        $fallback_image_url = get_stylesheet_directory_uri() . '/assets/images/government_entity.jpeg';
    } elseif ($term->taxonomy === 'private_entity') {
        $fallback_image_url = get_stylesheet_directory_uri() . '/assets/images/private_entity.jpeg';
    }
}

// Description
$description = get_field('detailed_description', $acf_term_id)
    ?: get_field('detailed_description', $term);

if (empty($description)) {
    $description = $term->description;
}

// Extra info badges
$extra_info = get_field('extra_info', $acf_term_id)
    ?: get_field('extra_info', $term);

// Fetch dynamic taxonomy label from Homepage ACF (via functions.php)
$dynamic_labels = get_dynamic_taxonomy_labels($term->taxonomy);

if (!empty($dynamic_labels['title'])) {
    $tax_label = $dynamic_labels['title'];
} else {
    $tax_obj = get_taxonomy($term->taxonomy);
    $tax_label = $tax_obj ? $tax_obj->labels->singular_name : __('Profile', 'hello-elementor-child');
}
?>

<section class="relative bg-white border-b border-gray-100 py-12 mb-12 shadow-[0_10px_40px_-15px_rgba(0,0,0,0.05)]">
    <div class="max-w-6xl mx-auto px-6">

        <!-- Back -->
        <div class="mb-10">
            <a href="<?php echo esc_url($back_url); ?>"
                class="inline-flex items-center gap-2 text-sm font-semibold text-gray-500 hover:text-primary transition-colors group">
                <span
                    class="inline-block transition-transform duration-300 group-hover:-translate-x-1 rtl:group-hover:translate-x-1 rtl:rotate-180"
                    aria-hidden="true">←</span>
                <span><?php echo esc_html($back_label); ?></span>
            </a>
        </div>


        <div class="flex flex-col md:flex-row gap-10 md:gap-16 items-start">

            <div class="flex-1 text-start min-w-0">
                <p class="text-sm font-bold text-primary uppercase tracking-widest mb-3">
                    <?php echo esc_html($tax_label); ?>
                </p>

                <h1 class="text-4xl md:text-5xl font-bold text-gray-900 tracking-tight mb-8">
                    <?php echo esc_html($term->name); ?>
                </h1>

                <?php if (!empty($extra_info)): ?>
                    <div class="flex flex-wrap gap-4 mb-8">
                        <?php foreach ($extra_info as $info):
                            if (empty($info['label']) || empty($info['value'])) {
                                continue;
                            }
                            ?>
                            <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-2">
                                <span class="text-xs text-gray-400 uppercase tracking-wider block mb-0.5 font-semibold">
                                    <?php echo esc_html($info['label']); ?>
                                </span>
                                <!-- Added dir="ltr" to ensure phone numbers and codes with spaces/symbols display in correct order -->
                                <span class="text-sm font-bold text-gray-900 block" dir="ltr">
                                    <?php echo esc_html($info['value']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($description)): ?>
                    <div class="prose prose-gray prose-lg max-w-none text-gray-600 leading-relaxed mb-8">
                        <?php echo wp_kses_post(wpautop($description)); ?>
                    </div>
                <?php endif; ?>

                <?php
                $social_key = have_rows('social_links', $acf_term_id)
                    ? $acf_term_id
                    : (have_rows('social_links', $term) ? $term : false);

                if ($social_key && have_rows('social_links', $social_key)):
                    ?>
                    <div class="flex flex-wrap gap-3 pt-4 border-t border-gray-100">
                        <?php
                        while (have_rows('social_links', $social_key)):
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

            <?php if ($image_id): ?>
                <div
                    class="w-48 md:w-72 flex-shrink-0 bg-gray-50 rounded-3xl overflow-hidden border border-gray-100 shadow-xl shadow-gray-200/50">
                    <?php
                    echo wp_get_attachment_image($image_id, 'large', false, [
                        'class' => 'w-full h-auto object-cover',
                    ]);
                    ?>
                </div>
            <? elseif (!empty($fallback_image_url)): ?>
                <div
                    class="w-48 md:w-72 flex-shrink-0 bg-gray-50 rounded-3xl overflow-hidden border border-gray-100 shadow-xl shadow-gray-200/50">
                    <img src="<?php echo esc_url($fallback_image_url); ?>" alt="<?php echo esc_attr($term->name); ?>"
                        class="w-full h-auto object-cover">
                </div>
            <? else: ?>
                <div
                    class="w-48 md:w-72 flex-shrink-0 bg-gray-50 rounded-3xl overflow-hidden border border-gray-100 shadow-xl shadow-gray-200/50 flex items-center justify-center aspect-[3/4]">
                    <span class="material-symbols-outlined text-6xl text-gray-300">person</span>
                </div>
            <?php endif; ?>

        </div>
    </div>
</section>