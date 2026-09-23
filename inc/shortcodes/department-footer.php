<?php

/**
 * Footer branding block for Elementor shortcode.
 */
function render_department_footer_branding(): string
{
    $context = get_context_department();
    $fallback = get_fallback_department();
    $primary = $context instanceof WP_Term ? $context : $fallback;

    $label = get_department_field_with_fallback('footer_label', $context);
    if (is_department_field_empty($label) && $primary instanceof WP_Term) {
        $label = $primary->name;
    }

    $logo = get_department_field_with_fallback('footer_logo', $context);
    if (is_department_field_empty($logo)) {
        $logo = get_department_field_with_fallback('department_logo', $context);
    }

    $description = get_department_field_with_fallback('detailed_description', $context);
    if (is_department_field_empty($description) && $primary instanceof WP_Term) {
        $description = $primary->description;
    }

    $social = get_department_field_with_fallback('social_links', $context);

    $logo_id = 0;
    if (is_array($logo) && !empty($logo['ID'])) {
        $logo_id = (int) $logo['ID'];
    } elseif (is_numeric($logo)) {
        $logo_id = (int) $logo;
    }

    ob_start();
    ?>
    <div class="department-footer-branding text-start">
        <?php if (!is_department_field_empty($label)): ?>
            <h3 class="text-lg font-bold text-white mb-3">
                <?php echo esc_html($label); ?>
            </h3>
        <?php endif; ?>

        <?php if ($logo_id): ?>
            <div class="mb-4">
                <?php
                echo wp_get_attachment_image($logo_id, 'medium', false, [
                    'class' => 'h-12 w-auto object-contain',
                ]);
                ?>
            </div>
        <?php endif; ?>

        <?php if (!is_department_field_empty($description)): ?>
            <div class="text-sm text-white/80 leading-relaxed mb-4 max-w-sm">
                <?php echo wp_kses_post(wpautop($description)); ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * Footer social icons for Elementor shortcode.
 */
function render_department_footer_social(): string
{
    $context = get_context_department();
    $social = get_department_field_with_fallback('social_links', $context);

    if (empty($social) || !is_array($social)) {
        return '';
    }

    ob_start();
    ?>
    <div class="department-footer-social">
        <p class="text-sm font-semibold text-white mb-3">
            <?php esc_html_e('تابعنا', 'hello-elementor-child'); ?>
        </p>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($social as $row):
                $url = $row['link'] ?? '';
                $platform = $row['platform'] ?? 'fas fa-globe';
                if (!$url) {
                    continue;
                }
                ?>
                <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"
                    class="w-10 h-10 inline-flex items-center justify-center rounded-full bg-white text-gray-800 hover:bg-gray-900 hover:text-white transition-colors duration-300 shadow-sm">
                    <i class="<?php echo esc_attr($platform); ?>"></i>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

add_shortcode('department_footer_branding', static function () {
    return render_department_footer_branding();
});

add_shortcode('department_footer_social', static function () {
    return render_department_footer_social();
});