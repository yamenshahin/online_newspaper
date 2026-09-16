<?php
/**
 * Card: Hero Featured (Lead Story)
 * Expects: $args['post'] (WP_Post object)
 */

$featured_post = $args['post'] ?? null;
if (!$featured_post)
    return;

$post_type_obj = get_post_type_object($featured_post->post_type);
$type_name = $post_type_obj ? $post_type_obj->labels->singular_name : '';
$time_diff = human_time_diff(get_post_time('U', false, $featured_post->ID), current_time('timestamp'));
$permalink = get_permalink($featured_post->ID);
?>

<div
    class="lg:col-span-8 rounded-3xl overflow-hidden bg-inverse-surface shadow-xl grid grid-cols-1 lg:grid-cols-2 group min-h-[520px]">

    <!-- Right side in RTL: Narrative & Metadata Content -->
    <div class="p-space-lg md:p-space-xl flex flex-col justify-between gap-space-lg text-on-primary z-10">

        <!-- Top Badges & Time -->
        <div class="flex flex-wrap items-center justify-between gap-space-sm">
            <div class="flex items-center gap-space-xs">
                <span
                    class="px-space-md py-1 rounded-full bg-primary-container text-on-primary font-label-pill text-label-pill flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-surface-bright animate-ping"></span>
                    <?php esc_html_e('قصة الغلاف • FEATURED STORY', 'hello-elementor-child'); ?>
                </span>
                <span
                    class="px-space-md py-1 rounded-full bg-surface-container-lowest/15 text-surface-bright font-label-caps text-label-caps">
                    <?php echo esc_html($type_name); ?>
                </span>
            </div>
            <div
                class="flex items-center gap-space-xs bg-surface-container-lowest/10 px-space-md py-1 rounded-full text-surface-variant font-label-pill text-label-pill">
                <span class="material-symbols-outlined text-[16px] text-primary-fixed">timer</span>
                <span
                    class=""><?php echo sprintf(esc_html__('منذ %s', 'hello-elementor-child'), $time_diff); ?></span>
            </div>
        </div>

        <!-- Core Headline & Description -->
        <div class="flex flex-col gap-space-md my-auto pt-space-md">
            <div class="flex items-center gap-space-xs text-primary-fixed">
                <span class="font-label-caps text-label-caps tracking-widest uppercase">
                    <?php echo esc_html(strtoupper($featured_post->post_type)); ?> & CULTURE
                </span>
            </div>

            <a href="<?php echo esc_url($permalink); ?>" class="block">
                <h2
                    class="font-headline-lg text-headline-lg text-surface-bright tracking-tight leading-tight group-hover:text-primary-fixed-dim transition-colors">
                    <?php echo esc_html(get_the_title($featured_post->ID)); ?>
                </h2>
            </a>

            <p class="font-body-base text-body-base text-surface-variant leading-snug line-clamp-3">
                <?php echo esc_html(wp_strip_all_tags(get_the_excerpt($featured_post->ID))); ?>
            </p>
        </div>

        <!-- Action CTA Buttons -->
        <div class="flex flex-wrap items-center gap-space-md pt-space-xs">
            <a href="<?php echo esc_url($permalink); ?>"
                class="px-space-lg py-space-sm rounded-full bg-primary-container hover:bg-primary text-on-primary font-label-pill text-label-pill flex items-center gap-space-xs transition-all shadow-md inline-flex">
                <span class="material-symbols-outlined text-[20px]"
                    style="font-variation-settings: 'FILL' 1;">play_arrow</span>
                <span class=""><?php esc_html_e('مشاهدة القصة الكاملة', 'hello-elementor-child'); ?></span>
            </a>
            <button
                class="px-space-md py-space-sm rounded-full bg-surface-container-lowest/15 hover:bg-surface-container-lowest/25 text-surface-bright font-label-pill text-label-pill flex items-center gap-space-xs transition-all">
                <span class="material-symbols-outlined text-[18px]">bookmark_add</span>
                <span class=""><?php esc_html_e('حفظ في القائمة', 'hello-elementor-child'); ?></span>
            </button>
        </div>
    </div>

    <!-- Left side in RTL: Unobstructed Pure Image Pane -->
    <a href="<?php echo esc_url($permalink); ?>"
        class="relative w-full h-full min-h-[360px] lg:min-h-full overflow-hidden bg-inverse-surface block">
        <?php
        $featured_img = get_post_thumbnail_id($featured_post->ID);
        if ($featured_img): ?>
            <?php echo wp_get_attachment_image($featured_img, 'full', false, [
                'class' => 'w-full h-full object-cover transition-transform duration-700 group-hover:scale-105'
            ]); ?>
        <?php endif; ?>
    </a>
</div>