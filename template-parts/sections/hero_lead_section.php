<?php
/**
 * Flexible Content: Hero Lead Section (Home Page)
 * Layout name: hero_lead_section
 */

$section = $args['section'] ?? [];

// 1. Read and Normalize Data
$featured = $section['hero_featured'] ?? null;
$trending = $section['hero_trending'] ?? [];
$section_title = $section['section_title'] ?: 'نبض أمة<span> • صوت الجيل الجديد</span>'; // Fallback if empty

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

    <!-- Dynamic Oversized Editorial Title -->
    <div class="flex flex-col gap-space-md mb-space-lg">
        <h1
            class="font-headline-xl text-headline-xl-mobile md:text-headline-xl font-bold text-on-background leading-tight text-right">
            <?php
            $formatted_title = str_replace('<span>', '<span class="text-primary">', $section_title);
            $formatted_title = str_replace('•', '<span class="text-primary-container px-2 inline-block">•</span>', $formatted_title);
            echo wp_kses_post($formatted_title);
            ?>
        </h1>
    </div>

    <!-- Asymmetric Hero Composition (8/4 Split restored) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter items-stretch">

        <!-- RIGHT SIDE: Featured Lead Story (Col-span 8) -->
        <div class="lg:col-span-8 flex w-full h-full">
            <?php
            get_template_part('template-parts/cards/card', 'hero-featured', [
                'post' => $featured_post
            ]);
            ?>
        </div>

        <!-- LEFT SIDE: Trending Radar & Velocity Stack (Col-span 4) -->
        <div class="lg:col-span-4 flex flex-col gap-space-md h-full">

            <!-- Live Velocity Metrics Hub -->
            <div
                class="p-space-lg rounded-3xl bg-surface-container-lowest shadow-sm flex flex-col gap-space-md flex-grow overflow-hidden">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-space-xs">
                        <span class="w-2.5 h-2.5 rounded-full bg-primary-container"></span>
                        <h3 class="font-headline-sm text-headline-sm text-on-background">الأكثر تداولاً</h3>
                    </div>
                </div>

                <!-- Trending List Items -->
                <div
                    class="flex flex-col gap-space-sm pt-space-xs flex-grow overflow-y-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
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
                class="p-space-md rounded-3xl bg-primary text-on-primary flex items-center justify-between flex-shrink-0 shadow-md">
                <div class="flex flex-col">
                    <span class="font-label-caps text-label-caps uppercase opacity-80">DAILY NEWSLETTER</span>
                    <span class="font-title-editorial text-title-editorial font-bold">نشرة عن السعودية الموجزة</span>
                </div>
                <button
                    class="px-space-md py-space-xs rounded-full bg-surface-container-lowest text-primary font-label-pill text-label-pill hover:bg-surface-container-low transition-colors shadow-sm">
                    تفعيل التنبيهات
                </button>
            </div>

        </div>
    </div>
</section>