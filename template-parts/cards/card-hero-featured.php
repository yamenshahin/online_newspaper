<?php
/**
 * Card: Hero Featured
 * 16:9 box, full image visible, centered in column.
 */
$post = $args['post'] ?? null;
if (!$post instanceof WP_Post) {
    return;
}

$type_map = [
    'post' => 'أخبار',
    'interview' => 'مقابلات',
    'program' => 'برامج',
    'infographic' => 'انفوجرافيك',
];
$pt_obj = get_post_type_object($post->post_type);
$type_name = $type_map[$post->post_type] ?? ($pt_obj ? $pt_obj->labels->singular_name : '');

$depts = get_the_terms($post->ID, 'department');
$dept = (!empty($depts) && !is_wp_error($depts)) ? $depts[0]->name : '';

$time = human_time_diff(get_post_time('U', false, $post->ID), current_time('timestamp'));
$url = get_permalink($post->ID);
$img = get_post_thumbnail_id($post->ID);
?>

<div
    class="lg:col-span-8 rounded-3xl overflow-hidden bg-inverse-surface shadow-xl grid grid-cols-1 lg:grid-cols-2 group">

    <!-- IMAGE -->
    <div class="flex items-center p-0 lg:p-4">
        <a href="<?php echo esc_url($url); ?>"
            class="block w-full aspect-video relative overflow-hidden rounded-xl bg-black/20">
            <?php
            if ($img) {
                echo wp_get_attachment_image($img, 'large', false, [
                    'class' => 'w-full h-full object-contain',
                    'style' => 'width:100%;height:100%;object-fit:contain;',
                ]);
            }
            ?>
        </a>
    </div>

    <!-- TEXT -->
    <div class="p-space-lg md:p-space-xl flex flex-col justify-between gap-space-lg text-on-primary text-start">

        <div class="flex flex-wrap items-center justify-between gap-space-sm w-full">
            <div class="flex items-center gap-space-xs">
                <span
                    class="px-space-md py-1 rounded-full bg-primary-container text-on-primary font-label-pill text-label-pill flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-surface-bright animate-ping"></span>
                    <?php esc_html_e('قصة الغلاف • FEATURED STORY', 'hello-elementor-child'); ?>
                </span>
                <span class="px-space-md py-1 rounded-full est/15 text-surface-bright font-label-caps text-label-caps">
                    <?php echo esc_html($type_name); ?>
                </span>
            </div>
            <div
                class="flex items-center gap-space-xs est/10 px-space-md py-1 rounded-full text-surface-variant font-label-pill text-label-pill">
                <span class="material-symbols-outlined text-[16px] text-primary-fixed">timer</span>
                <span><?php echo sprintf(esc_html__('منذ %s', 'hello-elementor-child'), $time); ?></span>
            </div>
        </div>

        <div class="flex flex-col gap-space-md items-start w-full">
            <?php if ($dept): ?>
                <span class="font-label-caps text-label-caps tracking-widest uppercase text-primary-fixed">
                    <?php echo esc_html($dept); ?>
                </span>
            <?php endif; ?>

            <a href="<?php echo esc_url($url); ?>">
                <h2
                    class="font-headline-lg text-headline-lg text-surface-bright tracking-tight leading-tight group-hover:text-primary-fixed-dim transition-colors">
                    <?php echo esc_html(get_the_title($post->ID)); ?>
                </h2>
            </a>

            <p class="font-body-base text-body-base text-surface-variant leading-snug line-clamp-3">
                <?php echo esc_html(wp_strip_all_tags(get_the_excerpt($post->ID))); ?>
            </p>
        </div>

        <div class="flex flex-wrap gap-space-md w-full">
            <a href="<?php echo esc_url($url); ?>"
                class="px-space-lg py-space-sm rounded-full bg-primary-container hover:bg-primary text-on-primary font-label-pill text-label-pill inline-flex items-center gap-space-xs shadow-md">
                <span class="material-symbols-outlined text-[20px]"
                    style="font-variation-settings:'FILL' 1">play_arrow</span>
                <?php esc_html_e('مشاهدة القصة الكاملة', 'hello-elementor-child'); ?>
            </a>
            <button type="button"
                class="px-space-md py-space-sm rounded-full est/15 hover:est/25 text-surface-bright font-label-pill text-label-pill inline-flex items-center gap-space-xs"
                onclick="navigator.share&&navigator.share({title:'<?php echo esc_js(get_the_title($post->ID)); ?>',url:'<?php echo esc_url($url); ?>'})">
                <span class="material-symbols-outlined text-[18px]">share</span>
                <?php esc_html_e('مشاركة', 'hello-elementor-child'); ?>
            </button>
        </div>
    </div>
</div>