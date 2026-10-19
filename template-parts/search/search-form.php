<?php
/**
 * Site search form: GET to home_url('/') with "s", so it always lands on search.php.
 *
 * Same input as the entity browse and cars searches (rounded-full, Material
 * "search" icon at the start), with two changes: 16px text so iOS Safari
 * doesn't zoom on focus, and a submit button for mouse and touch users.
 * The hook classes are site-search-*, not entity-search-*, so filters.js
 * never binds to it.
 *
 * $args:
 * - id (string) input id, unique on the page. Default 'search-page-input'.
 * - post_type (string) keep a type filter when the visitor refines the query.
 * - label (string) accessible name of the form. Default 'البحث في الموقع'.
 */
$input_id = !empty($args['id']) ? (string) $args['id'] : 'search-page-input';
$type_filter = !empty($args['post_type']) ? sanitize_key((string) $args['post_type']) : '';
$form_label = !empty($args['label']) ? (string) $args['label'] : __('البحث في الموقع', 'hello-elementor-child');
?>
<form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>"
    class="site-search-form w-full max-w-2xl"
    aria-label="<?php echo esc_attr($form_label); ?>">
    <label for="<?php echo esc_attr($input_id); ?>" class="sr-only">
        <?php esc_html_e('كلمات البحث', 'hello-elementor-child'); ?>
    </label>
    <div class="relative flex items-center w-full">
        <span
            class="material-symbols-outlined absolute rtl:right-3 ltr:left-3 text-on-surface-variant/70 !text-[18px] pointer-events-none select-none"
            aria-hidden="true">search</span>
        <input type="search" id="<?php echo esc_attr($input_id); ?>" name="s"
            value="<?php echo esc_attr(get_search_query(false)); ?>"
            required minlength="<?php echo (int) SITE_SEARCH_MIN_LENGTH; ?>" enterkeyhint="search"
            class="site-search-input w-full rounded-full border border-surface-container-highest bg-surface-container-lowest py-2.5 !ps-10 !pe-20 text-base text-on-background placeholder:text-on-surface-variant/60 shadow-xs transition-all focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 [&::-webkit-search-cancel-button]:appearance-none"
            placeholder="<?php esc_attr_e('ابحث في الأخبار والبرامج والحوارات…', 'hello-elementor-child'); ?>" />
        <button type="submit"
            class="absolute end-1 top-1 bottom-1 inline-flex items-center rounded-full bg-primary px-4 text-sm font-semibold text-on-primary transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
            data-loading-text="<?php esc_attr_e('جارٍ البحث…', 'hello-elementor-child'); ?>">
            <?php esc_html_e('بحث', 'hello-elementor-child'); ?>
        </button>
    </div>
    <?php if ($type_filter !== ''): ?>
        <input type="hidden" name="post_type" value="<?php echo esc_attr($type_filter); ?>" />
    <?php endif; ?>
</form>
