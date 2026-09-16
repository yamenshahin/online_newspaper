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

// 2. Enforce Curation
if (!$featured_post) {
    return;
}
?>

<section class="w-full px-margin-mobile lg:px-margin py-space-xl">

    <!-- Top Header & Marquee -->
    <div class="flex flex-col gap-space-md mb-space-lg">
        <div class="flex flex-wrap items-center justify-between gap-space-md">
            <div class="flex items-center gap-space-sm">
                <span
                    class="font-label-caps text-label-caps px-space-md py-1 rounded-full bg-primary-container text-on-primary font-bold">EDITION
                    2025 // 04</span>
                <span class="font-label-caps text-label-caps text-tertiary tracking-widest">KSA YOUTH DISRUPTION</span>
            </div>
            <div class="flex items-center gap-space-xs font-label-pill text-label-pill text-on-surface-variant">
                <span class="material-symbols-outlined text-[16px] text-primary">schedule</span>
                <span class=""><?php esc_html_e('تحديث مباشر كل 15 دقيقة', 'hello-elementor-child'); ?></span>
            </div>
        </div>

        <!-- Oversized Editorial Kinetic Title (Fixed Scaling) -->
        <h1
            class="font-headline-xl text-headline-xl-mobile md:text-headline-xl font-bold text-on-background leading-tight text-right">
            <span class="">نبض أمة</span>
            <span class="text-primary-container px-2">•</span>
            <span class="text-primary">صوت الجيل الجديد</span>
        </h1>
    </div>

    <!-- Asymmetric Hero Composition (70/30 Split) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter items-stretch">

        <!-- RIGHT SIDE: Featured Lead Story (Col-span 8) -->
        <?php
        get_template_part('template-parts/cards/card', 'hero-featured', [
            'post' => $featured_post
        ]);
        ?>

        <!-- LEFT SIDE: Trending Radar & Velocity Stack (Col-span 4) -->
        <div class="lg:col-span-4 flex flex-col gap-space-md">

            <!-- Live Velocity Metrics Hub -->
            <div class="p-space-lg rounded-3xl bg-surface-container-lowest shadow-sm flex flex-col gap-space-md h-full">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-space-xs">
                        <span class="w-2.5 h-2.5 rounded-full bg-primary-container"></span>
                        <h3 class="font-headline-sm text-headline-sm text-on-background">الأكثر تداولاً</h3>
                    </div>
                </div>

                <!-- Trending List Items -->
                <div class="flex flex-col gap-space-sm pt-space-xs flex-grow">
                    <?php if (!empty($trending)): ?>
                        <?php foreach ($trending as $index => $t_post):
                            $num = str_pad($index + 1, 2, '0', STR_PAD_LEFT);
                            get_template_part('template-parts/cards/card', 'hero-trending', [
                                'post' => $t_post,
                                'num' => $num
                            ]);
                        endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pulse Highlight Mini Banner -->
            <div
                class="p-space-md rounded-3xl bg-secondary text-on-secondary flex items-center justify-between mt-auto">
                <div class="flex flex-col">
                    <span class="font-label-caps text-label-caps uppercase opacity-80">DAILY NEWSLETTER</span>
                    <span class="font-title-editorial text-title-editorial font-bold">نشرة عن السعودية الموجزة</span>
                </div>
                <button
                    class="px-space-md py-space-xs rounded-full bg-surface-container-lowest text-on-background font-label-pill text-label-pill hover:bg-secondary-fixed transition-colors">
                    تفعيل التنبيهات
                </button>
            </div>

        </div>
    </div>
</section>