<?php
/**
 * Flexible Content: Ad Slider Section (rotating image ads)
 */

$section = $args['section'] ?? [];

// Primary: data passed from parent (get_field loop)
$ads = $section['ads'] ?? null;
$interval = $section['ad_interval'] ?? null;

// Fallback: only if parent did not pass section data
if ($ads === null && empty($section)) {
    $ads = get_sub_field('ads');
    $interval = get_sub_field('ad_interval');
}

$slides = [];
foreach ((array) $ads as $ad) {
    $image = $ad['ad_image'] ?? null;
    $image_id = is_array($image) ? (int) ($image['ID'] ?? 0) : (int) $image;
    if ($image_id) {
        $slides[] = ['image_id' => $image_id, 'link' => $ad['ad_link'] ?? ''];
    }
}

if (!$slides) {
    return;
}

$interval = max(2, (int) ($interval ?: 5));
$count = count($slides);

if ($count > 1) {
    $path = get_stylesheet_directory() . '/assets/js/ad-slider.js';
    wp_enqueue_script(
        'hello-elementor-child-ad-slider',
        get_stylesheet_directory_uri() . '/assets/js/ad-slider.js',
        [],
        file_exists($path) ? filemtime($path) : null,
        true
    );
}
?>

<section class="promo-slider-section w-full px-margin-mobile md:px-60 py-space-xl">
    <div class="flex flex-col items-center gap-3" data-ad-slider data-interval="<?php echo esc_attr($interval * 1000); ?>">
        <div class="grid min-w-0 max-w-full overflow-hidden">
            <?php foreach ($slides as $i => $slide):
                $img = wp_get_attachment_image($slide['image_id'], 'full', false, [
                    'class' => 'block w-auto h-auto max-w-full max-h-[70vh] object-contain mx-auto',
                    'loading' => $i === 0 ? 'eager' : 'lazy',
                ]);
                $state = $i === 0 ? 'opacity-100' : 'opacity-0 pointer-events-none';
                ?>
                <div class="[grid-area:1/1] flex items-center justify-center transition-opacity duration-700 <?php echo esc_attr($state); ?>"
                    data-ad-slide <?php echo $i === 0 ? '' : 'aria-hidden="true"'; ?>>
                    <?php if (!empty($slide['link'])): ?>
                        <a href="<?php echo esc_url($slide['link']); ?>" target="_blank" rel="noopener noreferrer nofollow"
                            class="block" <?php echo $i === 0 ? '' : 'tabindex="-1"'; ?>>
                            <?php echo $img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </a>
                    <?php else: ?>
                        <?php echo $img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($count > 1): ?>
            <div class="flex items-center gap-2">
                <?php foreach ($slides as $i => $slide): ?>
                    <button type="button" data-ad-dot
                        class="h-2 rounded-full transition-all <?php echo $i === 0 ? 'w-6 bg-primary' : 'w-2 bg-outline-variant'; ?>"
                        aria-label="<?php
                        /* translators: %d: ad number */
                        echo esc_attr(sprintf(__('Show ad %d', 'hello-elementor-child'), $i + 1)); ?>"></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
