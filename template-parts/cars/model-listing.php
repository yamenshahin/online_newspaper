<?php
/**
 * Model listing of the body-type and price-range pages: one listing tile per model (the cheapest current
 * trim, from car_cheapest_per_model()), 24 per page, with the live pagination recipe (results.php).
 *
 * $args:
 *   cars      WP_Post[]  every car of the listing (the part slices the current page itself)
 *   base_url  string     URL of page 1 with a trailing slash; page N is <base_url>page/N/
 *   paged     int        current page (WP `paged` query var), default 1
 *   per_page  int        tiles per page, default 24
 *   empty     string     dashed empty state when the page has nothing to show
 */

if (!defined('ABSPATH')) {
    exit;
}

$cars = array_values(array_filter((array) ($args['cars'] ?? []), static fn($c) => $c instanceof WP_Post));
$per_page = max(1, (int) ($args['per_page'] ?? 24));
$paged = max(1, (int) ($args['paged'] ?? 1));
$base_url = (string) ($args['base_url'] ?? '');
$empty = (string) ($args['empty'] ?? 'لا توجد سيارات في هذه القائمة حالياً.');
$total_pages = (int) ceil(count($cars) / $per_page);
$page_cars = array_slice($cars, ($paged - 1) * $per_page, $per_page);

if ($page_cars) {
    // one query primes the post, meta and term caches of the page's cars (the tiles read all three)
    get_posts(['post_type' => 'car', 'post__in' => wp_list_pluck($page_cars, 'ID'), 'posts_per_page' => -1, 'no_found_rows' => true]);
}

car_grid(array_map('car_listing_tile', $page_cars), 'wide', $empty);

if ($total_pages > 1 && $base_url !== '') : ?>
    <nav class="flex justify-center mt-space-xl" aria-label="<?php esc_attr_e('تصفح الصفحات', 'hello-elementor-child'); ?>">
        <div class="inline-flex items-center gap-2 flex-wrap justify-center [&_.page-numbers]:inline-flex [&_.page-numbers]:items-center [&_.page-numbers]:justify-center [&_.page-numbers]:min-w-[44px] [&_.page-numbers]:h-[44px] [&_.page-numbers]:px-2 [&_.page-numbers]:rounded-full [&_.page-numbers]:font-bold [&_.page-numbers]:shadow-sm [&_.current]:bg-primary [&_.current]:text-on-primary">
            <?php
            echo paginate_links([ // phpcs:ignore WordPress.Security.EscapeOutput -- paginate_links escapes its output
                'base' => trailingslashit($base_url) . '%_%',
                'format' => 'page/%#%/',
                'total' => $total_pages,
                'current' => $paged,
                'prev_text' => '«',
                'next_text' => '»',
            ]);
            ?>
        </div>
    </nav>
<?php endif; ?>
