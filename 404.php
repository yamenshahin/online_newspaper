<?php
/**
 * 404: the reporter interviewing a "404" that won't talk to him.
 *
 * The joke plays on the site name (تفاعل). Under it: a plain explanation,
 * a link home, the site search form and the three newest articles, so the
 * visitor always has somewhere to go.
 */

get_header();

$image_url = get_stylesheet_directory_uri() . '/assets/images/Reporter-vs-404.jpg';

$recent_stories = new WP_Query([
    'post_type' => 'post',
    'post_status' => 'publish',
    'posts_per_page' => 3,
    'ignore_sticky_posts' => true,
    'no_found_rows' => true,
]);
?>

<main id="primary" class="site-main min-h-screen">
    <section class="w-full px-margin-mobile lg:px-margin py-space-xl pt-12 view-404">

        <div
            class="flex flex-col items-center text-center gap-space-sm mb-space-xl border-b border-surface-container-highest/20 pb-space-xl">

            <img src="<?php echo esc_url($image_url); ?>" width="1672" height="941"
                alt="<?php esc_attr_e('مراسل يمدّ الميكروفون إلى الرقم 404 الذي يكتّف ذراعيه ويرفض الحديث', 'hello-elementor-child'); ?>"
                class="w-full max-w-3xl h-auto mix-blend-multiply" fetchpriority="high" decoding="async" />

            <p
                class="inline-flex items-center rounded-full bg-primary/10 text-primary px-5 py-1.5 text-lg md:text-xl font-bold">
                <?php esc_html_e('خطأ 404', 'hello-elementor-child'); ?>
            </p>

            <h1
                class="font-headline-xl text-headline-xl-mobile md:text-headline-xl text-on-background font-bold wrap-break-word">
                <?php esc_html_e('الصفحة الوحيدة اللي ما تفاعلت معنا!', 'hello-elementor-child'); ?>
            </h1>

            <p class="font-body-lg text-body-lg text-on-surface-variant max-w-2xl leading-relaxed">
                <?php esc_html_e('الصفحة غير موجودة، لكن الأخبار ما توقف.', 'hello-elementor-child'); ?>
            </p>

            <a href="<?php echo esc_url(home_url('/')); ?>"
                class="mt-space-sm inline-flex items-center gap-2 rounded-full bg-primary text-on-primary px-6 py-3 font-semibold transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                <i class="fas fa-house" aria-hidden="true"></i>
                <?php esc_html_e('العودة للرئيسية', 'hello-elementor-child'); ?>
            </a>

            <div class="mt-space-md w-full flex justify-center">
                <?php
                get_template_part('template-parts/search/search-form', null, [
                    'id' => 'not-found-search-input',
                    'label' => __('ابحث عمّا كنت تريده', 'hello-elementor-child'),
                ]);
                ?>
            </div>

        </div>

        <?php if ($recent_stories->have_posts()): ?>
            <h2 class="text-2xl font-bold text-on-background mb-space-lg">
                <?php esc_html_e('آخر الأخبار', 'hello-elementor-child'); ?>
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-gutter mb-12">
                <?php
                while ($recent_stories->have_posts()):
                    $recent_stories->the_post();
                    get_template_part('template-parts/cards/card', 'post');
                endwhile;
                wp_reset_postdata();
                ?>
            </div>
        <?php endif; ?>

    </section>
</main>

<?php
get_footer();
