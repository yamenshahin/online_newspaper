<?php
/**
 * Social Media Links
 * Department → term social_links
 * Homepage   → page social_links
 */

$department = $args['department'] ?? null;
$section = $args['section'] ?? [];

if (!$department instanceof WP_Term) {
    return;
}

if ($department instanceof WP_Term) {
    $context = $department;
    $title_name = $department->name;
} else {
    $context = get_queried_object_id();
    if (!$context) {
        return;
    }
    $title_name = get_the_title($context) ?: get_bloginfo('name');
}

if ($department instanceof WP_Term) {
    $active_filters = [
        'government_entity',
        'private_entity',
        'speaker_influencer',
        'geographic',
        'country',
        'city',
        'program_series',
    ];
    foreach ($active_filters as $filter) {
        if (!empty($_GET[$filter])) {
            return;
        }
    }
}

if (!have_rows('social_links', $context)) {
    return;
}

$detailed_description = get_field('detailed_description', $context);
if (empty($detailed_description)) {
    $detailed_description = __('Follow us across our digital platforms for the latest updates, programs, and exclusive insights.', 'hello-elementor-child');
}

// Get section title from ACF flexible content or fallback
if (!empty($section)) {
    $section_title = $section['section_title'] ?? '';
} else {
    $section_title = get_sub_field('section_title');
}

$section_title = $section_title ?: __('انضم إلى مجتمع', 'hello-elementor-child');
$heading_text = trim($section_title . ' ' . $title_name);
?>

<section class="py-16 bg-gray-50/50 border-t border-gray-100">
    <div class="max-w-7xl mx-auto px-6 text-center">
        <span class="text-xs font-bold text-primary uppercase tracking-widest mb-3 block">
            <?php esc_html_e('Stay Connected', 'hello-elementor-child'); ?>
        </span>
        <h2 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight mb-4">
            <?php echo esc_html($heading_text); ?>
        </h2>
        <div class="text-lg text-gray-500 max-w-2xl mx-auto mb-10 [&>p]:mb-4">
            <?php echo wp_kses_post($detailed_description); ?>
        </div>
        <div class="flex flex-wrap justify-center gap-6">
            <?php
            while (have_rows('social_links', $context)):
                the_row();
                $url = get_sub_field('link');
                $platform_val = get_sub_field('platform') ?: 'fas fa-globe';
                $follower_count = get_sub_field('follower_count');
                $platform_obj = get_sub_field_object('platform');
                $label = $platform_obj['choices'][$platform_val] ?? 'Website';
                ?>
                <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"
                    class="group w-44 flex flex-col items-center justify-between p-6 bg-white border border-gray-100 rounded-2xl hover:border-gray-200 hover:shadow-xl hover:shadow-gray-200/50 hover:-translate-y-1 transition-all duration-300 ease-out">
                    <div
                        class="w-14 h-14 flex items-center justify-center rounded-full bg-primary/10 text-primary group-hover:bg-primary group-hover:text-on-primary transition-colors duration-300 mb-4 text-2xl">
                        <i class="<?php echo esc_attr($platform_val); ?>"></i>
                    </div>
                    <h3 class="text-sm font-bold text-gray-900 mb-2"><?php echo esc_html($label); ?></h3>
                    <span
                        class="text-xs font-semibold text-gray-400 flex items-center group-hover:text-primary transition-colors">
                        <?php echo $follower_count ? esc_html($follower_count) : esc_html__('Visit', 'hello-elementor-child'); ?>
                        <span class="mx-1 transition-transform duration-300 group-hover:translate-x-1">&rarr;</span>
                    </span>
                </a>
            <?php endwhile; ?>
        </div>
    </div>
</section>