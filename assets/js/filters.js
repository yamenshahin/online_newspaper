/**
 * Filters: Geographic show-more + Entity main/sub tabs + entity search
 */
document.addEventListener('DOMContentLoaded', function () {
    initFilterWrappers();
    initEntitySearch();
    initEntityMainTabs();
});

/**
 * Show more / less — Geographic (and any non-entity-browse) only
 */
function initFilterWrappers() {
    document.querySelectorAll('.filter-wrapper').forEach(function (wrapper) {
        if (wrapper.closest('.entity-browse')) {
            return;
        }

        const container = wrapper.querySelector('.filter-container');
        const fadeOverlay = wrapper.querySelector('.filter-fade-overlay');
        const parentSection = wrapper.closest('section') || wrapper.parentElement;
        const toggleBtn = parentSection ? parentSection.querySelector('.filter-toggle-btn') : null;

        if (!container || !toggleBtn) return;
        if (toggleBtn.dataset.initialized === 'true') return;
        toggleBtn.dataset.initialized = 'true';

        const collapsedMax = parseInt(container.dataset.collapsedMax, 10) || 110;

        function checkOverflow() {
            if (wrapper.classList.contains('hidden') || container.classList.contains('hidden')) {
                return;
            }
            if (container.classList.contains('is-expanded') || container.classList.contains('is-searching')) {
                return;
            }

            if (container.scrollHeight > collapsedMax + 10) {
                toggleBtn.classList.remove('hidden');
                toggleBtn.classList.add('inline-flex');
                container.style.maxHeight = collapsedMax + 'px';
                if (fadeOverlay) fadeOverlay.classList.remove('opacity-0', 'hidden');
            } else {
                toggleBtn.classList.add('hidden');
                toggleBtn.classList.remove('inline-flex');
                container.style.maxHeight = 'none';
                if (fadeOverlay) fadeOverlay.classList.add('hidden');
            }
        }

        checkOverflow();

        toggleBtn.addEventListener('click', function () {
            const isExpanded = container.classList.contains('is-expanded');
            const toggleText = toggleBtn.querySelector('.toggle-text');
            const toggleIcon = toggleBtn.querySelector('.toggle-icon');

            if (isExpanded) {
                container.style.maxHeight = collapsedMax + 'px';
                container.classList.remove('is-expanded');
                if (fadeOverlay) fadeOverlay.classList.remove('opacity-0');
                if (toggleText) toggleText.textContent = 'عرض المزيد';
                if (toggleIcon) toggleIcon.style.transform = 'rotate(0deg)';
            } else {
                container.style.maxHeight = container.scrollHeight + 'px';
                container.classList.add('is-expanded');
                if (fadeOverlay) fadeOverlay.classList.add('opacity-0');
                if (toggleText) toggleText.textContent = 'عرض أقل';
                if (toggleIcon) toggleIcon.style.transform = 'rotate(180deg)';
            }
        });

        let resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(checkOverflow, 150);
        });
    });
}

function normalizeArabic(text) {
    if (!text) return '';
    return text
        .toLowerCase()
        .replace(/[\u064B-\u0652]/g, '')
        .replace(/[أإآ]/g, 'ا')
        .replace(/ة/g, 'ه')
        .replace(/ى/g, 'ي')
        .replace(/^ال/g, '')
        .trim();
}

/**
 * Entity search — all entities in the section (all mains/subs), not only active tabs
 */
function initEntitySearch() {
    document.querySelectorAll('.entity-search-input').forEach(function (searchInput) {
        const section = searchInput.closest('.entity-browse');
        if (!section) return;

        const mainTabsWrap = section.querySelector('.entity-main-tabs');

        function getActiveMainId() {
            const t = section.querySelector('.entity-main-tab[aria-selected="true"]');
            return t ? t.getAttribute('data-main-id') : null;
        }

        function getActiveSubId(mainPanel) {
            const t = mainPanel.querySelector('.entity-sub-tab[aria-selected="true"]');
            return t ? t.getAttribute('data-sub-id') : null;
        }

        /** Restore normal tab view after clearing search */
        function restoreTabs() {
            section.classList.remove('is-entity-searching');
            if (mainTabsWrap) mainTabsWrap.classList.remove('hidden');

            section.querySelectorAll('.entity-sub-tabs').forEach(function (el) {
                el.classList.remove('hidden');
            });

            section.querySelectorAll('.entity-pill').forEach(function (pill) {
                pill.style.display = '';
                pill.classList.remove('hidden');
            });

            const mainId = getActiveMainId();
            section.querySelectorAll('.entity-main-panel').forEach(function (panel) {
                const on = panel.getAttribute('data-main-id') === mainId;
                panel.classList.toggle('hidden', !on);

                if (on) {
                    const subId = getActiveSubId(panel);
                    panel.querySelectorAll('.entity-sub-panel').forEach(function (sub) {
                        if (subId === null) {
                            sub.classList.remove('hidden');
                        } else {
                            sub.classList.toggle('hidden', sub.getAttribute('data-sub-id') !== subId);
                        }
                    });
                }
            });
        }

        searchInput.addEventListener('input', function (e) {
            const query = normalizeArabic(e.target.value.trim());

            if (query === '') {
                restoreTabs();
                return;
            }

            section.classList.add('is-entity-searching');

            // Hide tab chrome while searching — results are global
            if (mainTabsWrap) mainTabsWrap.classList.add('hidden');
            section.querySelectorAll('.entity-sub-tabs').forEach(function (el) {
                el.classList.add('hidden');
            });

            // Show all panels; filter every pill in the section
            section.querySelectorAll('.entity-main-panel').forEach(function (panel) {
                panel.classList.remove('hidden');
            });
            section.querySelectorAll('.entity-sub-panel').forEach(function (panel) {
                panel.classList.remove('hidden');
            });

            section.querySelectorAll('.entity-pill').forEach(function (pill) {
                const name = normalizeArabic(
                    pill.getAttribute('data-entity-name') || pill.textContent
                );
                if (name.includes(query)) {
                    pill.style.display = 'inline-flex';
                    pill.classList.remove('hidden');
                } else {
                    pill.style.display = 'none';
                    pill.classList.add('hidden');
                }
            });

            // Hide empty sub panels
            section.querySelectorAll('.entity-sub-panel').forEach(function (sub) {
                const visible = sub.querySelectorAll('.entity-pill:not(.hidden)');
                sub.classList.toggle('hidden', visible.length === 0);
            });

            // Hide empty main panels
            section.querySelectorAll('.entity-main-panel').forEach(function (main) {
                const visible = main.querySelectorAll('.entity-pill:not(.hidden)');
                main.classList.toggle('hidden', visible.length === 0);
            });
        });
    });
}

/**
 * Main tabs → Sub tabs (show/hide only — no height limits)
 */
function initEntityMainTabs() {
    document.querySelectorAll('.entity-browse').forEach(function (section) {
        const mainTabs = section.querySelectorAll('.entity-main-tab');
        const mainPanels = section.querySelectorAll('.entity-main-panel');

        function activateMain(mainId) {
            mainTabs.forEach(function (t) {
                const on = t.getAttribute('data-main-id') === mainId;
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                t.classList.toggle('bg-primary', on);
                t.classList.toggle('text-on-primary', on);
                t.classList.toggle('bg-surface-container', !on);
                t.classList.toggle('text-on-surface', !on);
            });

            mainPanels.forEach(function (panel) {
                const on = panel.getAttribute('data-main-id') === mainId;
                panel.classList.toggle('hidden', !on);
                if (on) {
                    const firstSub = panel.querySelector('.entity-sub-tab');
                    if (firstSub) {
                        activateSub(panel, firstSub.getAttribute('data-sub-id'));
                    }
                }
            });
        }

        function activateSub(mainPanel, subId) {
            const subTabs = mainPanel.querySelectorAll('.entity-sub-tab');
            const subPanels = mainPanel.querySelectorAll('.entity-sub-panel');

            subTabs.forEach(function (t) {
                const on = t.getAttribute('data-sub-id') === subId;
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                t.classList.toggle('bg-primary-container', on);
                t.classList.toggle('text-on-primary-container', on);
                t.classList.toggle('bg-surface-container-low', !on);
                t.classList.toggle('text-on-surface-variant', !on);
            });

            subPanels.forEach(function (p) {
                p.classList.toggle('hidden', p.getAttribute('data-sub-id') !== subId);
            });
        }

        mainTabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                activateMain(tab.getAttribute('data-main-id'));
            });
        });

        section.querySelectorAll('.entity-sub-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                const mainId = tab.getAttribute('data-main-id');
                const mainPanel = section.querySelector('.entity-main-panel[data-main-id="' + mainId + '"]');
                if (mainPanel) {
                    activateSub(mainPanel, tab.getAttribute('data-sub-id'));
                }
            });
        });
    });
}