<?php
/**
 * Sub-profile: Event organizer (ACF Group "organizer")
 * Sub fields: organizer_image, organizer_social_links, organizer_extra_info
 */
$term = $args['term'] ?? null;
if (!$term instanceof WP_Term) {
    return;
}

$acf_term_id = $args['acf_term_id'] ?? ($term->taxonomy . '_' . $term->term_id);

$organizer = get_field('organizer', $acf_term_id);
if (empty($organizer) || !is_array($organizer)) {
    $organizer = get_field('organizer', $term);
}
if (empty($organizer) || !is_array($organizer)) {
    return;
}

$org_name = trim((string) ($organizer['organizer_name'] ?? ''));
$image = $organizer['organizer_image'] ?? null;
$socials = $organizer['organizer_social_links'] ?? [];
$extra = $organizer['organizer_extra_info'] ?? [];

$image_url = '';
$image_alt = $term->name;
if (is_array($image) && !empty($image['url'])) {
    $image_url = $image['url'];
    $image_alt = $image['alt'] ?: $term->name;
} elseif (is_numeric($image)) {
    $image_url = (string) (wp_get_attachment_image_url((int) $image, 'medium') ?: '');
}

$has_extra = false;
if (is_array($extra)) {
    foreach ($extra as $row) {
        if (!empty($row['label']) || !empty($row['value'])) {
            $has_extra = true;
            break;
        }
    }
}

$has_social = is_array($socials) && count(array_filter($socials, static function ($row) {
    return !empty($row['link']);
})) > 0;

// Nothing to show
if ($org_name === '' && $image_url === '' && !$has_extra && !$has_social) {
    return;
}
?>

<div class="mt-12 pt-8 border-t border-gray-100">
    <div class="bg-gray-50/80 p-6 md:p-8 flex flex-col md:flex-row items-start md:items-center gap-6 rounded-2xl">

        <?php if ($image_url !== ''): ?>
            <div
                class="w-24 h-24 md:w-32 md:h-32 flex-shrink-0 bg-white overflow-hidden shadow-sm flex items-center justify-center p-2 rounded-xl">
                <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($image_alt); ?>"
                    class="max-w-full max-h-full object-contain">
            </div>
        <?php else: ?>
            <div
                class="w-24 h-24 md:w-32 md:h-32 flex-shrink-0 bg-white shadow-sm flex items-center justify-center text-gray-300 rounded-xl">
                <span class="material-symbols-outlined text-4xl">corporate_fare</span>
            </div>
        <?php endif; ?>

        <div class="flex-1 min-w-0">
            <span class="text-xs font-bold text-primary uppercase tracking-wider block mb-3">
                <?php esc_html_e('الجهة المنظمة', 'hello-elementor-child'); ?>
            </span>

            <?php if ($org_name !== ''): ?>
                <h3 class="text-xl md:text-2xl font-bold text-gray-900 mb-3">
                    <?php echo esc_html($org_name); ?>
                </h3>
            <?php endif; ?>

            <?php if ($has_extra): ?>
                <div class="flex flex-wrap gap-3">
                    <?php foreach ($extra as $row):
                        $label = trim((string) ($row['label'] ?? ''));
                        $value = trim((string) ($row['value'] ?? ''));
                        if ($label === '' && $value === '') {
                            continue;
                        }
                        ?>
                        <div class="bg-white border border-gray-100 rounded-xl px-4 py-2">
                            <?php if ($label !== ''): ?>
                                <span class="text-xs text-gray-400 uppercase tracking-wider block mb-0.5 font-semibold">
                                    <?php echo esc_html($label); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($value !== ''): ?>
                                <span class="text-sm font-bold text-gray-900 block" dir="auto">
                                    <?php echo esc_html($value); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($has_social): ?>
                <div class="flex flex-wrap gap-3 mt-4 pt-4 border-t border-gray-100/80">
                    <?php foreach ($socials as $row):
                        $url = $row['link'] ?? '';
                        $platform = $row['platform'] ?? 'fas fa-globe';
                        if (empty($url)) {
                            continue;
                        }
                        ?>
                        <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"
                            class="w-10 h-10 flex items-center justify-center rounded-full bg-white text-gray-400 border border-gray-100 hover:bg-primary hover:text-white hover:border-primary transition-all duration-300 text-lg">
                            <i class="<?php echo esc_attr($platform); ?>"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>