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
            // 1. Automatically inject the blue color class if the editor types an empty <span>
            $formatted_title = str_replace('<span>', '<span class="text-primary">', $section_title);

            // 2. Automatically wrap the bullet point to give it the light-blue accent and spacing
            $formatted_title = str_replace('•', '<span class="text-primary-container px-2 inline-block">•</span>', $formatted_title);

            // 3. Output the title while safely allowing HTML tags (like span) to render
            echo wp_kses_post($formatted_title);
            ?>
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