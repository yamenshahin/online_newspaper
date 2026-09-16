<?php
/**
 * Master Archive Template
 * Automatically handles Programs, Interviews, Infographics, Posts, Categories, and Tags!
 */

get_header();

// 1. Map Post Types to their exact Titles, Subtitles, and Cards
$post_types_config = [
    'program' => [
        'title' => 'البرامج والعروض • PROGRAMS (SHOWS)',
        'subtitle' => 'تغطيات مرئية ووثائقيات رقمية ترصد مشاريع الجيل السعودي الواعد',
        'card' => 'program'
    ],
    'interview' => [
        'title' => 'حوارات ملهمة • INTERVIEWS & PODCASTS',
        'subtitle' => 'لقاءات حصرية مع قادة الفكر وصناع القرار',
        'card' => 'interview'
    ],
    'infographic' => [
        'title' => 'تصاميم بيانات وإنفوجرافيك • DATA LAB',
        'subtitle' => 'إحصاءات وأرقام تعكس واقع النهضة السعودية',
        'card' => 'infographic'
    ],
    'post' => [
        'title' => 'آخر الأخبار والمستجدات • LATEST NEWS',
        'subtitle' => 'تغطيات وتقارير ترصد مشاريع الجيل السعودي الواعد',
        'card' => 'post'
    ]
];

// 2. Smart Detection Logic
$post_type = get_post_type();

if (isset($post_types_config[$post_type]) && is_post_type_archive()) {
    // It's one of our custom post types
    $config = $post_types_config[$post_type];
} else {
    // Fallback for native Categories, Tags, Date archives, or unmapped CPTs
    $config = [
        'title' => wp_strip_all_tags(get_the_archive_title()),
        'subtitle' => wp_strip_all_tags(get_the_archive_description()),
        'card' => 'post' // Default card fallback
    ];
}
?>

<main id="primary" class="site-main bg-background min-h-screen">

    <section class="w-full px-margin-mobile lg:px-margin py-space-xl pt-12">

        <!-- ARCHIVE HEADER -->
        <div
            class="flex flex-col gap-space-xs text-start mb-space-xl border-b border-surface-container-highest/20 pb-space-lg">

            <!-- Breadcrumb -->
            <div class="flex items-center gap-2 mb-4">
                <a href="<?php echo home_url('/'); ?>"
                    class="text-sm font-semibold text-gray-500 hover:text-primary transition-colors">الرئيسية</a>
                <span class="text-gray-400 text-sm">/</span>
                <span
                    class="text-sm font-semibold text-primary uppercase tracking-widest"><?php echo esc_html($config['title']); ?></span>
            </div>

            <!-- Title -->
            <h1 class="font-headline-xl text-headline-xl-mobile md:text-headline-xl text-on-background font-bold">
                <?php echo esc_html($config['title']); ?>
            </h1>

            <!-- Subtitle -->
            <?php if (!empty($config['subtitle'])): ?>
                <p class="font-body-lg text-body-lg text-on-surface-variant max-w-3xl mt-4 leading-relaxed">
                    <?php echo esc_html($config['subtitle']); ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- 4-COLUMN GRID -->
        <?php if (have_posts()): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-gutter">
                <?php while (have_posts()):
                    the_post(); ?>
                    <?php get_template_part('template-parts/cards/card', $config['card']); ?>
                <?php endwhile; ?>
            </div>

            <!-- PAGINATION -->
            <div class="mt-space-xl pt-space-lg flex justify-center w-full gap-2 
    [&_.page-numbers]:inline-flex [&_.page-numbers]:items-center [&_.page-numbers]:justify-center [&_.page-numbers]:min-w-[44px] [&_.page-numbers]:h-[44px] [&_.page-numbers]:px-2 [&_.page-numbers]:rounded-full [&_.page-numbers]:bg-surface-container-lowest [&_.page-numbers]:text-on-background [&_.page-numbers]:font-bold [&_.page-numbers]:shadow-sm [&_.page-numbers]:transition-colors
    [&_a.page-numbers:hover]:bg-surface-container-low [&_a.page-numbers:hover]:text-primary
    [&_.current]:bg-primary [&_.current]:text-on-primary [&_.current]:shadow-md">
                <?php
                echo paginate_links([
                    'prev_text' => '<span class="material-symbols-outlined text-[18px] rtl:rotate-180">arrow_forward</span>',
                    'next_text' => '<span class="material-symbols-outlined text-[18px] rtl:rotate-180">arrow_back</span>',
                    'type' => 'plain',
                    'mid_size' => 2
                ]);
                ?>
            </div>

        <?php else: ?>
            <!-- NO POSTS FOUND STATE -->
            <div
                class="flex flex-col items-center justify-center py-20 text-center bg-surface-container-lowest rounded-3xl border border-surface-container-highest/30 shadow-sm">
                <span class="material-symbols-outlined text-6xl text-gray-300 mb-4">search_off</span>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">لا توجد محتويات بعد</h3>
                <p class="text-gray-500 text-lg">لم يتم نشر أي محتوى في هذا القسم حتى الآن.</p>
            </div>
        <?php endif; ?>

    </section>

</main>

<?php get_footer(); ?>