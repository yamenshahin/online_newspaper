<?php
/**
 * Flexible Content: Hero Lead Section (Home Page)
 * Layout name: hero_lead_section
 */

$section = $args['section'] ?? [];

// 1. Read and Normalize Data
$featured = $section['hero_featured'] ?? null;
$trending = $section['hero_trending'] ?? [];

if ($featured && !is_array($featured)) {
    $featured = [$featured];
}
$featured_post = $featured[0] ?? null;

if (!is_array($trending)) {
    $trending = $trending ? [$trending] : [];
}

// 2. Enforce Curation (Show nothing if editors haven't picked a featured post)
if (!$featured_post) {
    return;
}
?>

<section class="w-full px-margin-mobile lg:px-margin pt-space-xl pb-space-lg">

    <!-- Top Cultural Marquee -->
    <div
        class="flex flex-col md:flex-row md:items-end justify-between mb-space-lg gap-4 border-b border-surface-variant pb-4">
        <h1 class="font-headline-xl text-headline-xl-mobile md:text-headline-xl font-bold text-primary tracking-tight">
            نبض أمة <span class="text-secondary mx-2">•</span> صوت الجيل الجديد
        </h1>
        <div class="flex items-center gap-3">
            <span class="font-label-caps text-label-caps text-on-surface-variant uppercase tracking-widest">KSA YOUTH
                DISRUPTION</span>
            <span class="px-3 py-1 rounded-full bg-secondary text-white font-label-pill text-label-pill">EDITION 2025 //
                04</span>
        </div>
    </div>

    <!-- Hero Grid: RTL Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">

        <!-- ========================================== -->
        <!-- RIGHT SIDE: Featured Lead Story (Col-span 8) -->
        <!-- Move to: template-parts/cards/card-hero-featured.php -->
        <!-- ========================================== -->
        <div
            class="lg:col-span-8 group relative rounded-3xl overflow-hidden bg-inverse-surface min-h-[500px] flex items-end">
            <?php
            $featured_img = get_post_thumbnail_id($featured_post->ID);
            if ($featured_img): ?>
                <?php echo wp_get_attachment_image($featured_img, 'full', false, [
                    'class' => 'absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-105'
                ]); ?>
            <?php endif; ?>

            <div
                class="absolute inset-0 bg-gradient-to-l from-inverse-surface/90 via-inverse-surface/70 to-transparent">
            </div>

            <div class="relative z-10 w-full lg:w-2/3 p-space-xl flex flex-col items-start text-surface-bright">

                <div class="flex items-center gap-3 mb-4">
                    <span class="px-space-sm py-1 rounded-full bg-secondary text-white font-label-caps text-label-caps">
                        قصة الغلاف • FEATURED STORY
                    </span>
                    <span
                        class="px-space-sm py-1 rounded-full bg-white/10 backdrop-blur-md text-white font-label-pill text-label-pill border border-white/20">
                        وثائقي خاص
                    </span>
                </div>

                <div
                    class="flex items-center gap-2 font-label-pill text-label-pill text-surface-variant/80 mb-space-sm">
                    <span class="material-symbols-outlined text-[16px]">schedule</span>
                    <span><?php echo human_time_diff(get_post_time('U', false, $featured_post->ID), current_time('timestamp')) . ' ' . __('مضت', 'hello-elementor-child'); ?></span>
                    <span class="mx-1">•</span>
                    <span>قراءة 6 دقائق</span>
                </div>

                <span class="font-label-caps text-label-caps text-primary-fixed uppercase tracking-widest mb-2">
                    <?php
                    $post_type_obj = get_post_type_object($featured_post->post_type);
                    echo esc_html($post_type_obj->labels->singular_name);
                    ?> & CULTURE
                </span>

                <a href="<?php echo esc_url(get_permalink($featured_post->ID)); ?>"
                    class="hover:text-primary-fixed transition-colors block">
                    <h2 class="font-headline-lg text-headline-lg font-bold leading-tight mb-4">
                        <?php echo esc_html(get_the_title($featured_post->ID)); ?>
                    </h2>
                </a>

                <p class="font-body-base text-body-base text-surface-variant/80 line-clamp-3 mb-space-lg max-w-md">
                    <?php echo esc_html(wp_strip_all_tags(get_the_excerpt($featured_post->ID))); ?>
                </p>

                <a href="<?php echo esc_url(get_permalink($featured_post->ID)); ?>"
                    class="flex items-center gap-2 px-space-lg py-2 rounded-full bg-primary hover:bg-primary-container text-white transition-colors font-label-pill text-label-pill group/btn">
                    <span
                        class="material-symbols-outlined text-[18px] group-hover/btn:-translate-x-1 transition-transform">play_circle</span>
                    <span>مشاهدة القصة الكاملة</span>
                </a>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- LEFT SIDE: Trending Velocity Radar (Col-span 4) -->
        <!-- Move to: template-parts/cards/card-hero-trending.php -->
        <!-- ========================================== -->
        <div
            class="lg:col-span-4 flex flex-col bg-white rounded-3xl shadow-sm border border-surface-variant/30 overflow-hidden">

            <div class="p-space-lg flex items-center gap-2 border-b border-surface-variant/30">
                <div class="w-2 h-2 rounded-full bg-secondary"></div>
                <h3 class="font-headline-sm text-headline-sm font-bold text-on-background">الأكثر تداولاً</h3>
            </div>

            <div class="flex flex-col p-space-lg gap-space-md flex-grow">
                <?php if (!empty($trending)): ?>
                    <?php foreach ($trending as $index => $t_post):
                        $num = str_pad($index + 1, 2, '0', STR_PAD_LEFT);
                        ?>
                        <div class="flex items-start gap-4 group cursor-pointer">
                            <span
                                class="font-headline-sm text-headline-sm font-bold text-secondary/30 group-hover:text-secondary transition-colors">
                                <?php echo esc_html($num); ?>
                            </span>
                            <div class="flex flex-col gap-1">
                                <span class="font-label-caps text-[10px] text-outline uppercase tracking-wider">
                                    <?php
                                    $t_type = get_post_type_object($t_post->post_type);
                                    echo esc_html($t_type->labels->singular_name);
                                    ?>
                                </span>
                                <a href="<?php echo esc_url(get_permalink($t_post->ID)); ?>"
                                    class="font-title-editorial text-title-editorial font-bold text-on-background group-hover:text-primary transition-colors line-clamp-2">
                                    <?php echo esc_html(get_the_title($t_post->ID)); ?>
                                </a>
                                <div class="flex items-center gap-2 font-label-pill text-[11px] text-on-surface-variant mt-1">
                                    <span><?php echo human_time_diff(get_post_time('U', false, $t_post->ID), current_time('timestamp')) . ' ' . __('مضت', 'hello-elementor-child'); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="font-body-sm text-surface-variant text-center py-4">لم يتم تحديد مقالات.</p>
                <?php endif; ?>
            </div>

            <!-- Daily Newsletter Block -->
            <div class="bg-secondary p-space-md flex items-center justify-between mt-auto">
                <a href="#"
                    class="px-space-md py-2 bg-white text-secondary hover:bg-surface-container rounded-full font-label-pill text-label-pill transition-colors shadow-sm">
                    تفعيل التنبيهات
                </a>
                <div class="flex flex-col items-end text-white text-right">
                    <span class="font-label-caps text-[10px] uppercase tracking-widest text-white/70">DAILY
                        NEWSLETTER</span>
                    <span class="font-title-editorial text-sm font-bold">نشرة عن السعودية الموجزة</span>
                </div>
            </div>

        </div>

    </div>
</section>