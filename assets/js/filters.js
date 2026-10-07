/**
 * Dynamic Taxonomy Filters & Entity Search Handler
 */
document.addEventListener('DOMContentLoaded', function () {
    initFilterWrappers();
    initEntitySearch();
    initEntityMainTabs();
});

/**
 * Expandable Filter Containers ("Show More / Show Less")
 */
function initFilterWrappers() {
    const wrappers = document.querySelectorAll('.filter-wrapper');

    wrappers.forEach(wrapper => {
        const container = wrapper.querySelector('.filter-container');
        const fadeOverlay = wrapper.querySelector('.filter-fade-overlay');
        const parentSection = wrapper.closest('.entity-browse') || wrapper.closest('section') || wrapper.parentElement;
        const toggleBtn = parentSection ? parentSection.querySelector('.filter-toggle-btn') : null;

        if (!container || !toggleBtn) return;

        const collapsedMax = parseInt(container.dataset.collapsedMax, 10) || 110;

        if (toggleBtn.dataset.initialized === 'true') return;
        toggleBtn.dataset.initialized = 'true';

        function checkOverflow() {
            // Only measure the visible panel
            if (wrapper.classList.contains('hidden')) {
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
            const visibleWrapper = parentSection.querySelector('.entity-main-panel:not(.hidden).filter-wrapper') || wrapper;
            const visibleContainer = visibleWrapper.querySelector('.filter-container') || container;
            const visibleFade = visibleWrapper.querySelector('.filter-fade-overlay') || fadeOverlay;
            const isExpanded = visibleContainer.classList.contains('is-expanded');
            const toggleText = toggleBtn.querySelector('.toggle-text');
            const toggleIcon = toggleBtn.querySelector('.toggle-icon');
            const max = parseInt(visibleContainer.dataset.collapsedMax, 10) || collapsedMax;

            if (isExpanded) {
                visibleContainer.style.maxHeight = max + 'px';
                visibleContainer.classList.remove('is-expanded');
                if (visibleFade) visibleFade.classList.remove('opacity-0');
                if (toggleText) toggleText.textContent = 'عرض المزيد';
                if (toggleIcon) toggleIcon.style.transform = 'rotate(0deg)';
            } else {
                visibleContainer.style.maxHeight = visibleContainer.scrollHeight + 'px';
                visibleContainer.classList.add('is-expanded');
                if (visibleFade) visibleFade.classList.add('opacity-0');
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
 * Client-side entity search (active main panel only)
 */
function initEntitySearch() {
    const searchInputs = document.querySelectorAll('.entity-search-input');

    searchInputs.forEach(searchInput => {
        const section = searchInput.closest('.entity-browse') || searchInput.closest('section');
        if (!section) return;

        searchInput.addEventListener('input', function (e) {
            const rawQuery = e.target.value.trim();
            const query = normalizeArabic(rawQuery);

            const activePanel = section.querySelector('.entity-main-panel:not(.hidden)') || section;
            const subGroups = activePanel.querySelectorAll('.entity-sub');
            const pills = activePanel.querySelectorAll('.entity-pill');
            const filterContainer = activePanel.querySelector('.filter-container');
            const fadeOverlay = activePanel.querySelector('.filter-fade-overlay');

            if (query === '') {
                pills.forEach(pill => {
                    pill.style.display = '';
                    pill.classList.remove('hidden');
                });
                subGroups.forEach(sub => {
                    sub.style.display = '';
                    sub.classList.remove('hidden');
                });
                if (filterContainer) {
                    filterContainer.classList.remove('is-searching');
                    const collapsedMax = parseInt(filterContainer.dataset.collapsedMax, 10) || 180;
                    if (!filterContainer.classList.contains('is-expanded')) {
                        filterContainer.style.maxHeight = collapsedMax + 'px';
                        if (fadeOverlay) fadeOverlay.classList.remove('opacity-0');
                    }
                }
                return;
            }

            if (filterContainer) {
                filterContainer.classList.add('is-searching');
                filterContainer.style.maxHeight = 'none';
            }
            if (fadeOverlay) fadeOverlay.classList.add('opacity-0');

            pills.forEach(pill => {
                const rawName = pill.getAttribute('data-entity-name') || pill.textContent;
                const normalizedName = normalizeArabic(rawName);
                if (normalizedName.includes(query)) {
                    pill.style.display = 'inline-flex';
                    pill.classList.remove('hidden');
                } else {
                    pill.style.display = 'none';
                    pill.classList.add('hidden');
                }
            });

            subGroups.forEach(sub => {
                const visiblePills = sub.querySelectorAll('.entity-pill:not(.hidden)');
                if (visiblePills.length > 0) {
                    sub.style.display = '';
                    sub.classList.remove('hidden');
                } else {
                    sub.style.display = 'none';
                    sub.classList.add('hidden');
                }
            });
        });
    });
}

/**
 * Main tabs → Sub tabs → Entity pills
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
                    // Activate first visible sub tab in this main
                    const firstSub = panel.querySelector('.entity-sub-tab');
                    if (firstSub) {
                        activateSub(panel, firstSub.getAttribute('data-sub-id'));
                    }
                }
            });

            window.dispatchEvent(new Event('resize'));
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

            window.dispatchEvent(new Event('resize'));
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