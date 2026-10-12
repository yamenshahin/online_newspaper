/**
 * Filters: Geographic show-more + combined entity tabs + entity search
 */
document.addEventListener('DOMContentLoaded', function () {
    initFilterWrappers();
    initEntityMainTabs();
    initEntitySearch();
});

/**
 * Show more / less — Geographic only (skip .entity-browse)
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
 * Combined UI:
 * L1 type (gov|private)
 * Gov:  L2 sub → L3 entities
 * Priv: L2 main → L3 sub → L4 entities
 */
function initEntityMainTabs() {
    document.querySelectorAll('.entity-browse--combined').forEach(function (section) {
        function setType(type) {
            section.querySelectorAll('.entity-type-tab').forEach(function (t) {
                const on = t.getAttribute('data-entity-type') === type;
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                t.classList.toggle('bg-primary', on);
                t.classList.toggle('text-on-primary', on);
                t.classList.toggle('bg-surface-container', !on);
                t.classList.toggle('text-on-surface', !on);
            });

            section.querySelectorAll('.entity-type-panel').forEach(function (p) {
                p.classList.toggle('hidden', p.getAttribute('data-entity-type') !== type);
            });

            const panel = section.querySelector('.entity-type-panel[data-entity-type="' + type + '"]');
            if (!panel) return;

            if (type === 'private') {
                const firstMain = panel.querySelector('.entity-main-tab');
                if (firstMain) {
                    setPrivateMain(panel, firstMain.getAttribute('data-main-id'));
                }
            } else {
                const firstSub = panel.querySelector('.entity-sub-tab');
                if (firstSub) {
                    setSub(panel, firstSub.getAttribute('data-sub-id'), null);
                }
            }
        }

        function setPrivateMain(typePanel, mainId) {
            typePanel.querySelectorAll('.entity-main-tab').forEach(function (t) {
                const on = t.getAttribute('data-main-id') === mainId;
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                t.classList.toggle('bg-primary', on);
                t.classList.toggle('text-on-primary', on);
                t.classList.toggle('bg-surface-container', !on);
                t.classList.toggle('text-on-surface', !on);
            });

            typePanel.querySelectorAll('.entity-main-panel').forEach(function (p) {
                const on = p.getAttribute('data-main-id') === mainId;
                p.classList.toggle('hidden', !on);
                if (on) {
                    const firstSub = p.querySelector('.entity-sub-tab');
                    if (firstSub) {
                        setSub(p, firstSub.getAttribute('data-sub-id'), mainId);
                    } else {
                        p.querySelectorAll('.entity-sub-panel').forEach(function (sub) {
                            sub.classList.remove('hidden');
                        });
                    }
                }
            });
        }

        function setSub(scope, subId, mainId) {
            scope.querySelectorAll('.entity-sub-tab').forEach(function (t) {
                let on = t.getAttribute('data-sub-id') === subId;
                if (mainId !== null && t.getAttribute('data-main-id')) {
                    on = on && t.getAttribute('data-main-id') === mainId;
                }
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                t.classList.toggle('bg-primary-container', on);
                t.classList.toggle('text-on-primary-container', on);
                t.classList.toggle('bg-surface-container-low', !on);
                t.classList.toggle('text-on-surface-variant', !on);
            });

            scope.querySelectorAll('.entity-sub-panel').forEach(function (p) {
                let on = p.getAttribute('data-sub-id') === subId;
                if (mainId !== null && p.getAttribute('data-main-id')) {
                    on = on && p.getAttribute('data-main-id') === mainId;
                }
                p.classList.toggle('hidden', !on);
            });
        }

        section.querySelectorAll('.entity-type-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                section.classList.remove('is-entity-searching');
                const search = section.querySelector('.entity-search-input');
                if (search) search.value = '';
                section.querySelectorAll('.entity-pill').forEach(function (p) {
                    p.style.display = '';
                    p.classList.remove('hidden');
                });
                section.querySelectorAll('.entity-main-tabs, .entity-sub-tabs').forEach(function (el) {
                    el.classList.remove('hidden');
                });
                setType(tab.getAttribute('data-entity-type'));
            });
        });

        section.querySelectorAll('.entity-main-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                const typePanel = section.querySelector('.entity-type-panel[data-entity-type="private"]');
                if (typePanel) {
                    setPrivateMain(typePanel, tab.getAttribute('data-main-id'));
                }
            });
        });

        section.querySelectorAll('.entity-sub-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                const type = tab.getAttribute('data-entity-type');
                const mainId = tab.getAttribute('data-main-id');
                let scope;
                if (mainId) {
                    scope = section.querySelector(
                        '.entity-main-panel[data-entity-type="private"][data-main-id="' + mainId + '"]'
                    );
                } else {
                    scope = section.querySelector('.entity-type-panel[data-entity-type="' + type + '"]');
                }
                if (scope) {
                    setSub(scope, tab.getAttribute('data-sub-id'), mainId);
                }
            });
        });
    });

    // Legacy single-taxonomy browse (if any left)
    document.querySelectorAll('.entity-browse:not(.entity-browse--combined)').forEach(function (section) {
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
            mainPanel.querySelectorAll('.entity-sub-tab').forEach(function (t) {
                const on = t.getAttribute('data-sub-id') === subId;
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                t.classList.toggle('bg-primary-container', on);
                t.classList.toggle('text-on-primary-container', on);
                t.classList.toggle('bg-surface-container-low', !on);
                t.classList.toggle('text-on-surface-variant', !on);
            });
            mainPanel.querySelectorAll('.entity-sub-panel').forEach(function (p) {
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

/**
 * Search gov + private together — keep type tabs visible
 */
function initEntitySearch() {
    document.querySelectorAll('.entity-browse--combined .entity-search-input').forEach(function (searchInput) {
        const section = searchInput.closest('.entity-browse');
        if (!section) return;

        function restoreFromTabs() {
            section.classList.remove('is-entity-searching');

            section.querySelectorAll('.entity-main-tabs, .entity-sub-tabs').forEach(function (el) {
                el.classList.remove('hidden');
            });

            section.querySelectorAll('.entity-pill').forEach(function (p) {
                p.style.display = '';
                p.classList.remove('hidden');
            });

            const activeType = section.querySelector('.entity-type-tab[aria-selected="true"]');
            if (activeType) {
                activeType.click();
            }
        }

        searchInput.addEventListener('input', function () {
            const query = normalizeArabic(searchInput.value.trim());

            if (!query) {
                restoreFromTabs();
                return;
            }

            section.classList.add('is-entity-searching');

            // Keep L1 type tabs (الجهات الحكومية | القطاع الخاص) — do NOT hide them
            // Hide only deeper tab rows so results stay readable
            section.querySelectorAll('.entity-main-tabs, .entity-sub-tabs').forEach(function (el) {
                el.classList.add('hidden');
            });

            // Show both gov + private panels
            section.querySelectorAll('.entity-type-panel').forEach(function (panel) {
                panel.classList.remove('hidden');
            });
            section.querySelectorAll('.entity-main-panel, .entity-sub-panel').forEach(function (p) {
                p.classList.remove('hidden');
            });

            // Filter all pills
            section.querySelectorAll('.entity-pill').forEach(function (pill) {
                const name = normalizeArabic(
                    pill.getAttribute('data-entity-name') || pill.textContent
                );
                const match = name.includes(query);
                pill.style.display = match ? 'inline-flex' : 'none';
                pill.classList.toggle('hidden', !match);
            });

            // Hide empty groups only
            section.querySelectorAll('.entity-sub-panel').forEach(function (sub) {
                sub.classList.toggle('hidden', !sub.querySelector('.entity-pill:not(.hidden)'));
            });
            section.querySelectorAll('.entity-main-panel').forEach(function (main) {
                main.classList.toggle('hidden', !main.querySelector('.entity-pill:not(.hidden)'));
            });
            section.querySelectorAll('.entity-type-panel').forEach(function (typePanel) {
                typePanel.classList.toggle(
                    'hidden',
                    !typePanel.querySelector('.entity-pill:not(.hidden)')
                );
            });
        });
    });
}

/**
 * Cars Directory – AJAX filters
 */
document.addEventListener('DOMContentLoaded', function () {
    // … your existing inits …
    initCarsAjaxFilters();
});

function initCarsAjaxFilters() {
    const section = document.getElementById('cars-directory');
    if (!section || typeof CarsFilter === 'undefined') return;

    const chipsEl = document.getElementById('cars-chips');
    const resultsEl = document.getElementById('cars-results');
    const loadingEl = document.getElementById('cars-loading');

    if (!chipsEl || !resultsEl) return;

    // Collect all current filter values from every form inside the section
    function collectFilters() {
        const data = {};

        section.querySelectorAll('form').forEach(function (form) {
            const fd = new FormData(form);
            fd.forEach(function (value, key) {
                if (value !== '') {
                    data[key] = value;
                }
            });
        });

        return data;
    }

    function showLoading() {
        if (loadingEl) {
            loadingEl.classList.remove('hidden');
            loadingEl.classList.add('flex');
        }
        resultsEl.style.opacity = '0.45';
        resultsEl.style.pointerEvents = 'none';
    }

    function hideLoading() {
        if (loadingEl) {
            loadingEl.classList.add('hidden');
            loadingEl.classList.remove('flex');
        }
        resultsEl.style.opacity = '';
        resultsEl.style.pointerEvents = '';
    }

    function runFilter() {
        showLoading();

        const body = new FormData();
        body.append('action', 'cars_filter');
        body.append('nonce', CarsFilter.nonce);
        body.append('base_url', CarsFilter.baseUrl);   // ← add this

        const filters = collectFilters();
        Object.keys(filters).forEach(function (key) {
            body.append('filters[' + key + ']', filters[key]);
        });

        fetch(CarsFilter.ajaxUrl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (json.success) {
                    chipsEl.innerHTML = json.data.chips;
                    // Keep the loading overlay, replace only the results content
                    const temp = document.createElement('div');
                    temp.innerHTML = json.data.results;
                    // Remove old results children except the loading div
                    Array.from(resultsEl.children).forEach(function (child) {
                        if (child.id !== 'cars-loading') {
                            child.remove();
                        }
                    });
                    // Append new results
                    while (temp.firstChild) {
                        resultsEl.appendChild(temp.firstChild);
                    }
                }
            })
            .catch(function (err) {
                console.error('Cars filter error:', err);
            })
            .finally(function () {
                hideLoading();
            });
    }

    // Listen to all changes inside the section
    section.addEventListener('change', function (e) {
        if (e.target.matches('select, input[type="number"], input[type="search"]')) {
            // Small debounce for the search input
            if (e.target.type === 'search') {
                clearTimeout(section._searchTimer);
                section._searchTimer = setTimeout(runFilter, 400);
            } else {
                runFilter();
            }
        }
    });

    // Also catch the “تطبيق” button
    section.addEventListener('submit', function (e) {
        if (e.target.closest('#cars-directory')) {
            e.preventDefault();
            runFilter();
        }
    });
}

function initCarsAjaxFilters() {
    const section = document.getElementById('cars-directory');
    if (!section || typeof CarsFilter === 'undefined') return;

    const chipsEl = document.getElementById('cars-chips');
    const resultsEl = document.getElementById('cars-results');
    const loadingEl = document.getElementById('cars-loading');

    if (!chipsEl || !resultsEl) return;

    function collectFilters() {
        const data = {};
        section.querySelectorAll('form').forEach(function (form) {
            new FormData(form).forEach(function (value, key) {
                if (value !== '') data[key] = value;
            });
        });
        return data;
    }

    function showLoading() {
        if (loadingEl) {
            loadingEl.classList.remove('hidden');
            loadingEl.classList.add('flex');
        }
        resultsEl.style.opacity = '0.4';
        resultsEl.style.pointerEvents = 'none';
    }

    function hideLoading() {
        if (loadingEl) {
            loadingEl.classList.add('hidden');
            loadingEl.classList.remove('flex');
        }
        resultsEl.style.opacity = '';
        resultsEl.style.pointerEvents = '';
    }

    function runFilter(clear = false) {
        showLoading();

        const body = new FormData();
        body.append('action', 'cars_filter');
        body.append('nonce', CarsFilter.nonce);
        body.append('base_url', CarsFilter.baseUrl);

        if (!clear) {
            const filters = collectFilters();
            Object.keys(filters).forEach(function (key) {
                body.append('filters[' + key + ']', filters[key]);
            });
        }

        fetch(CarsFilter.ajaxUrl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        })
            .then(r => r.json())
            .then(json => {
                if (!json.success) return;

                chipsEl.innerHTML = json.data.chips;

                // Replace results but keep the loading overlay
                Array.from(resultsEl.children).forEach(child => {
                    if (child.id !== 'cars-loading') child.remove();
                });
                const temp = document.createElement('div');
                temp.innerHTML = json.data.results;
                while (temp.firstChild) {
                    resultsEl.appendChild(temp.firstChild);
                }
            })
            .catch(console.error)
            .finally(hideLoading);
    }

    // ===== Change / input =====
    section.addEventListener('change', function (e) {
        if (e.target.matches('select, input[type="number"]')) {
            runFilter();
        }
    });

    section.addEventListener('input', function (e) {
        if (e.target.matches('input[type="search"]')) {
            clearTimeout(section._searchTimer);
            section._searchTimer = setTimeout(() => runFilter(), 400);
        }
    });

    // ===== Form submit (تطبيق button) =====
    section.addEventListener('submit', function (e) {
        e.preventDefault();
        runFilter();
    });

    // ===== Clear all =====
    section.addEventListener('click', function (e) {
        const clearBtn = e.target.closest('[data-cars-clear]');
        if (!clearBtn) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        // Reset all fields visually
        section.querySelectorAll('select, input[type="search"], input[type="number"]').forEach(el => {
            el.value = '';
        });

        runFilter(true); // true = send empty filters
    });
}