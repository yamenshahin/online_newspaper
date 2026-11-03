<?php
/**
 * Flexible Content: Custom Ad Code Section
 */

$department = $args['department'] ?? null;
$section = $args['section'] ?? [];

// Primary: data passed from parent
$ad_code = $section['ad_code'] ?? null;

// Fallback: only if parent did not pass section data
if ($ad_code === null && empty($section)) {
    $ad_code = get_sub_field('ad_code', false);
}

if (empty($ad_code)) {
    return;
}
?>

<section class="promo-code-section py-8 my-4">
    <div class="max-w-7xl mx-auto px-6 flex justify-center">
        <div class="w-full max-w-4xl flex justify-center items-center">
            <div class="promo-embed-wrapper text-center w-full overflow-hidden">
                <?php echo $ad_code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </div>
    </div>
</section>