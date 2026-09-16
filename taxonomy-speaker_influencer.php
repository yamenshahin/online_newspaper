<?php
/**
 * Taxonomy Archive: Speaker & Influencer
 */

get_header();

$term = get_queried_object();
if (!$term || is_wp_error($term)) {
    get_footer();
    return;
}
?>

<main id="primary" class="site-main bg-background">

    <?php
    // 1. Profile Header 
    get_template_part('template-parts/profiles/term-profile', null, [
        'term' => $term,
        'back_url' => home_url('/'),
        'back_label' => __('العودة إلى الرئيسية', 'hello-elementor-child'),
    ]);

    // 2. Dynamic Content Sections (Configured and ordered perfectly to match the mockups)
    $post_types_config = [
        'program' => [
            'num' => '06',
            'title' => 'مرئيات • VIDEOS STREAM',
            'subtitle' => 'تغطيات مرئية ووثائقيات رقمية ترصد مشاريع الجيل السعودي الواعد',
            'btn_text' => 'استعراض جميع مرئيات',
            'card' => 'program'
        ],
        'infographic' => [
            'num' => '05',
            'title' => 'تصاميم بيانات وإنفوجرافيك • DATA LAB',
            'subtitle' => 'إحصاءات وأرقام تعكس واقع النهضة السعودية',
            'btn_text' => 'استعراض جميع الإنفوجرافيك',
            'card' => 'infographic'
        ],
        'interview' => [
            'num' => '03',
            'title' => 'حوارات ملهمة • INTERVIEWS & PODCASTS',
            'subtitle' => 'لقاءات حصرية مع قادة الفكر وصناع القرار',
            'btn_text' => 'استعراض جميع الحوارات',
            'card' => 'interview'
        ],
        'post' => [
            'num' => '04',
            'title' => 'آخر الأخبار والمستجدات • LATEST NEWS',
            'subtitle' => 'تغطيات وتقارير ترصد مشاريع الجيل السعودي الواعد',
            'btn_text' => 'استعراض جميع الأخبار',
            'card' => 'post'
        ]
    ];

    foreach ($post_types_config as $pt => $data):
        $query = new WP_Query([
            'post_type' => $pt,
            'posts_per_page' => 4,
            'tax_query' => [
                [
                    'taxonomy' => 'speaker_influencer',
                    'field' => 'term_id',
                    'terms' => $term->term_id,
                ]
            ]
        ]);

        if ($query->have_posts()):
            // Generate the correct archive link with the filter appended
            $archive_link = add_query_arg('speaker_influencer', $term->slug, get_post_type_archive_link($pt));
            ?>
            <section
                class="w-full px-margin-mobile lg:px-margin py-space-xl border-t border-surface-container-highest/20 last:border-0">

                <!-- SECTION HEADER (Matches Homepage Design Exactly) -->
                <div class="flex flex-col md:flex-row md:items-end justify-between gap-space-md mb-space-lg">
                    <div class="flex flex-col gap-space-xs text-start">
                        <div class="flex items-center gap-space-xs">
                            <span
                                class="font-headline-lg text-headline-lg text-primary font-bold"><?php echo esc_html($data['num']); ?>
                                /</span>
                            <h2 class="font-headline-lg text-headline-lg text-on-background">
                                <?php echo esc_html($data['title']); ?>
                            </h2>
                        </div>
                        <p class="font-body-sm text-body-sm text-on-surface-variant">
                            <?php echo esc_html($data['subtitle']); ?>
                        </p>
                    </div>

                    <!-- VIEW ALL PILL BUTTON -->
                    <a class="px-space-md py-space-xs rounded-full bg-surface-container-lowest hover:bg-surface-container-high text-primary font-label-pill text-label-pill transition-colors shadow-sm flex items-center gap-1 self-start md:self-auto"
                        href="<?php echo esc_url($archive_link); ?>">
                        <span class=""><?php echo esc_html($data['btn_text']); ?></span>
                        <span class="material-symbols-outlined text-[14px]">arrow_back</span>
                    </a>
                </div>

                <!-- 4-COLUMN GRID -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-gutter">
                    <?php while ($query->have_posts()):
                        $query->the_post(); ?>
                        <?php get_template_part('template-parts/cards/card', $data['card']); ?>
                    <?php endwhile; ?>
                </div>
            </section>
            <?php
        endif;
        wp_reset_postdata();
    endforeach;
    ?>

</main>

<?php get_footer(); ?>