<?php
/**
 * Header search: the toggle in the header bar and the panel it opens.
 *
 * Rendered by template-parts/dynamic-header.php (child override of Hello's
 * header) inside .header-actions, after the desktop menu and before Hello's
 * menu toggle. Styles: "Header search" in src/input.css. Behaviour:
 * initHeaderSearch() in assets/js/filters.js. Without JavaScript the toggle is
 * hidden and the form shows as a row under the bar (@media (scripting: none)).
 */
?>
<div class="header-search">
    <button type="button" class="header-search-toggle" aria-expanded="false" aria-controls="header-search-panel"
        aria-label="<?php esc_attr_e('البحث في الموقع', 'hello-elementor-child'); ?>">
        <span class="material-symbols-outlined header-search-icon-open" aria-hidden="true">search</span>
        <span class="material-symbols-outlined header-search-icon-close" aria-hidden="true">close</span>
    </button>

    <div id="header-search-panel" class="header-search-panel">
        <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>"
            class="mx-auto flex w-full max-w-3xl items-center gap-2"
            aria-label="<?php esc_attr_e('البحث في الموقع', 'hello-elementor-child'); ?>">
            <label for="header-search-input" class="sr-only"><?php esc_html_e('ابحث في الموقع', 'hello-elementor-child'); ?></label>
            <div class="relative flex min-w-0 flex-1 items-center">
                <span class="material-symbols-outlined header-search-field-icon absolute rtl:right-3 ltr:left-3 text-on-surface-variant/70 pointer-events-none select-none"
                    aria-hidden="true">search</span>
                <input type="search" id="header-search-input" name="s"
                    value="<?php echo esc_attr(get_search_query(false)); ?>"
                    class="w-full rounded-full border border-surface-container-highest bg-surface-container-lowest py-2 !ps-10 !pe-3 text-base text-on-background placeholder:text-on-surface-variant/60 shadow-xs transition-all focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 [&::-webkit-search-cancel-button]:appearance-none"
                    placeholder="<?php esc_attr_e('ابحث في الموقع…', 'hello-elementor-child'); ?>" enterkeyhint="search"
                    required minlength="<?php echo (int) (defined('SITE_SEARCH_MIN_LENGTH') ? SITE_SEARCH_MIN_LENGTH : 2); ?>" />
            </div>
            <button type="submit" class="inline-flex shrink-0 items-center rounded-full bg-primary text-on-primary px-6 py-2 text-sm font-bold"
                data-loading-text="<?php esc_attr_e('جارٍ البحث…', 'hello-elementor-child'); ?>"><?php esc_html_e('بحث', 'hello-elementor-child'); ?></button>
            <button type="button"
                class="header-search-close inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container"
                aria-label="<?php esc_attr_e('إغلاق البحث', 'hello-elementor-child'); ?>">
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </form>
    </div>
</div>
