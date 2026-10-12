<?php
/**
 * Card: Car (Phase 1)
 */
$id = get_the_ID();
$url = get_permalink();

$maker = get_the_terms($id, 'car_maker');
$year = get_the_terms($id, 'car_year');
$fuel = get_the_terms($id, 'car_fuel_type');

$maker_name = (!empty($maker) && !is_wp_error($maker)) ? $maker[0]->name : '';
$year_name = (!empty($year) && !is_wp_error($year)) ? $year[0]->name : '';
$fuel_name = (!empty($fuel) && !is_wp_error($fuel)) ? $fuel[0]->name : '';
?>
<article <?php post_class('group flex flex-col rounded-2xl overflow-hidden border border-surface-container-highest bg-surface-container-lowest shadow-sm hover:shadow-lg transition-shadow'); ?>>
    <a href="<?php echo esc_url($url); ?>" class="block aspect-video bg-gray-100 overflow-hidden relative">
        <?php if (has_post_thumbnail()): ?>
            <?php the_post_thumbnail('medium_large', ['class' => 'w-full h-full object-cover group-hover:scale-105 transition-transform duration-500']); ?>
        <?php endif; ?>
    </a>
    <div class="p-4 flex flex-col gap-1 text-start flex-1">
        <?php if ($maker_name || $year_name): ?>
            <span class="text-xs font-semibold text-primary">
                <?php echo esc_html(trim($maker_name . ($year_name ? ' · ' . $year_name : ''))); ?>
            </span>
        <?php endif; ?>
        <h3 class="text-lg font-bold text-on-background group-hover:text-primary transition-colors leading-snug">
            <a href="<?php echo esc_url($url); ?>"><?php the_title(); ?></a>
        </h3>
        <?php if ($fuel_name): ?>
            <span class="text-xs text-on-surface-variant mt-auto pt-2"><?php echo esc_html($fuel_name); ?></span>
        <?php endif; ?>
    </div>
</article>