/**
 * Filters: Geographic show-more + combined entity tabs + entity search
 */
document.addEventListener('DOMContentLoaded', function () {
    initFilterWrappers();
    initEntityBrowse();
    initCarsAjaxFilters();
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
        .trim();
}

/**
 * Combined entity browse:
 * L1 type (government | private | event)
 * Gov:   L2 sub → entities
 * Priv:  L2 main → L3 sub → entities
 * Event: L2 category → entities
 *
 * The server renders the tabs only. Each .entity-sub-panel carries
 * data-taxonomy + data-parent and is filled from /hello/v1/entity-nav the
 * first time it becomes visible (the first panel arrives preloaded).
 * Search calls /hello/v1/entity-search and replaces the panels with results.
 */
function initEntityBrowse() {
    const cfg = window.helloEntityNav || {};

    document.querySelectorAll('.entity-browse--combined').forEach(function (section) {
        let i18n = {};
        try {
            i18n = JSON.parse(section.getAttribute('data-i18n') || '{}');
        } catch (err) {
            i18n = {};
        }
        const labels = i18n.labels || {};
        const pillClass = section.getAttribute('data-pill-class') || 'entity-pill';
        const searchInput = section.querySelector('.entity-search-input');
        const resultsEl = section.querySelector('.entity-search-results');
        const loaded = {}; // "taxonomy:parent" -> Promise<items>

        function restUrl(base, params) {
            // rest_url() may be pretty (/wp-json/...) or ?rest_route=..., so build with URL.
            const url = new URL(base, window.location.href);
            Object.keys(params).forEach(function (key) {
                url.searchParams.set(key, params[key]);
            });
            return url.toString();
        }

        function fetchJson(url, signal) {
            return fetch(url, { headers: { Accept: 'application/json' }, signal: signal }).then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            });
        }

        function makePill(item) {
            const a = document.createElement('a');
            a.href = item.link;
            a.className = pillClass;
            a.textContent = item.name;
            return a;
        }

        function makeNote(text, extraClass) {
            const span = document.createElement('span');
            span.className = 'text-sm ' + (extraClass || 'text-on-primary/80');
            span.textContent = text;
            return span;
        }

        function isShown(el) {
            return !el.closest('.hidden');
        }

        function fetchLeaves(taxonomy, parent) {
            const key = taxonomy + ':' + parent;
            if (!loaded[key]) {
                loaded[key] = fetchJson(restUrl(cfg.navUrl, { taxonomy: taxonomy, parent: parent })).catch(function (err) {
                    delete loaded[key]; // allow a retry
                    throw err;
                });
            }
            return loaded[key];
        }

        /** Fill a leaf panel the first time it is shown. */
        function ensureLoaded(panel) {
            if (!panel || !cfg.navUrl) return;
            if (panel.getAttribute('data-loaded') === '1' || panel.getAttribute('data-loading') === '1') return;

            const taxonomy = panel.getAttribute('data-taxonomy');
            const parent = panel.getAttribute('data-parent') || '0';
            if (!taxonomy) return;

            panel.setAttribute('data-loading', '1');
            panel.replaceChildren(makeNote(i18n.loading || '…'));

            fetchLeaves(taxonomy, parent)
                .then(function (items) {
                    panel.replaceChildren();
                    if (!Array.isArray(items) || !items.length) {
                        panel.appendChild(makeNote(i18n.empty || ''));
                    } else {
                        items.forEach(function (item) {
                            panel.appendChild(makePill(item));
                        });
                    }
                    panel.setAttribute('data-loaded', '1');
                })
                .catch(function () {
                    const retry = document.createElement('button');
                    retry.type = 'button';
                    retry.className = 'text-sm text-on-primary underline';
                    retry.textContent = i18n.error || 'Error';
                    retry.addEventListener('click', function () {
                        ensureLoaded(panel);
                    });
                    panel.replaceChildren(retry);
                })
                .finally(function () {
                    panel.removeAttribute('data-loading');
                });
        }

        function loadVisiblePanels() {
            section.querySelectorAll('.entity-sub-panel').forEach(function (panel) {
                if (isShown(panel)) ensureLoaded(panel);
            });
        }

        // ---- Tabs -------------------------------------------------------

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
                    return;
                }
            } else {
                const firstSub = panel.querySelector('.entity-sub-tab');
                if (firstSub) {
                    setSub(panel, firstSub.getAttribute('data-sub-id'), null);
                    return;
                }
            }
            // No tabs at this level: the panel holds a single leaf list.
            loadVisiblePanels();
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
                if (!on) return;

                const firstSub = p.querySelector('.entity-sub-tab');
                if (firstSub) {
                    setSub(p, firstSub.getAttribute('data-sub-id'), mainId);
                } else {
                    p.querySelectorAll('.entity-sub-panel').forEach(function (sub) {
                        sub.classList.remove('hidden');
                        ensureLoaded(sub);
                    });
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
                if (on) ensureLoaded(p);
            });
        }

        section.querySelectorAll('.entity-type-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                exitSearch();
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

        // ---- Search (all types together) --------------------------------

        let searchTimer = null;
        let searchController = null;

        function showResults(show) {
            section.classList.toggle('is-entity-searching', show);
            if (resultsEl) resultsEl.classList.toggle('hidden', !show);
            section.querySelectorAll('.entity-type-panel').forEach(function (p) {
                if (show) {
                    p.classList.add('hidden');
                }
            });
        }

        function exitSearch() {
            clearTimeout(searchTimer);
            if (searchController) {
                searchController.abort();
                searchController = null;
            }
            if (searchInput) searchInput.value = '';
            if (resultsEl) resultsEl.replaceChildren();
            showResults(false);
        }

        function restoreFromTabs() {
            exitSearch();
            const activeType = section.querySelector('.entity-type-tab[aria-selected="true"]');
            if (activeType) {
                setType(activeType.getAttribute('data-entity-type'));
            }
        }

        function renderResults(items) {
            if (!resultsEl) return;
            resultsEl.replaceChildren();

            if (!Array.isArray(items) || !items.length) {
                resultsEl.appendChild(makeNote(i18n.no_results || '', 'text-on-surface-variant'));
                return;
            }

            const groups = {};
            const order = [];
            items.forEach(function (item) {
                if (!groups[item.taxonomy]) {
                    groups[item.taxonomy] = [];
                    order.push(item.taxonomy);
                }
                groups[item.taxonomy].push(item);
            });

            order.forEach(function (taxonomy) {
                const wrap = document.createElement('div');
                wrap.className = 'flex flex-col gap-2';

                const heading = document.createElement('div');
                heading.className = 'text-sm font-semibold text-on-surface-variant';
                heading.textContent = labels[taxonomy] || taxonomy;
                wrap.appendChild(heading);

                const panel = document.createElement('div');
                panel.className = 'flex flex-wrap gap-2 p-4 md:p-5 rounded-2xl bg-primary';
                groups[taxonomy].forEach(function (item) {
                    panel.appendChild(makePill(item));
                });
                wrap.appendChild(panel);

                resultsEl.appendChild(wrap);
            });
        }

        function runSearch(query) {
            if (!cfg.searchUrl || !resultsEl) return;

            if (searchController) searchController.abort();
            searchController = new AbortController();
            const controller = searchController;

            showResults(true);
            resultsEl.replaceChildren(makeNote(i18n.loading || '…', 'text-on-surface-variant'));

            fetchJson(restUrl(cfg.searchUrl, { q: query, taxonomy: 'all' }), controller.signal)
                .then(function (items) {
                    if (controller !== searchController) return; // superseded
                    renderResults(items);
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') return;
                    if (controller !== searchController) return;
                    resultsEl.replaceChildren(makeNote(i18n.error || 'Error', 'text-on-surface-variant'));
                });
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                const raw = searchInput.value.trim();
                clearTimeout(searchTimer);

                if (!raw) {
                    restoreFromTabs();
                    return;
                }
                // Same minimum as the server (2 characters after normalization).
                if (normalizeArabic(raw).length < 2) {
                    return;
                }
                searchTimer = setTimeout(function () {
                    runSearch(raw);
                }, 250);
            });
        }

        // The first visible panel is preloaded by PHP; this covers any other case.
        loadVisiblePanels();
    });
}

/**
 * Cars Directory – AJAX filters
 */
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

        const currentPath = window.location.pathname.replace(/\/+$/, '');
        const isCarDirectory = /(?:^|\/)car-directory(?:\/|$)/.test(currentPath);

        body.append('is_car_archive', isCarDirectory ? '1' : '0');

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

    // ===== Clear all & Individual filter chip removal =====
    section.addEventListener('click', function (e) {
        // Handle "Clear All" button
        const clearBtn = e.target.closest('[data-cars-clear]');
        if (clearBtn) {
            e.preventDefault();
            e.stopImmediatePropagation();

            section.querySelectorAll('select, input[type="search"], input[type="number"]').forEach(el => {
                el.value = '';
            });

            runFilter(true);
            return;
        }

        // Handle individual filter chip removal
        const chipRemove = e.target.closest(
            'a[href], button, [data-filter-remove], .cars-chip'
        );

        if (chipRemove && chipsEl.contains(chipRemove)) {
            const href = chipRemove.getAttribute('href');

            if (href && href !== '#' && !href.includes('javascript')) {
                e.preventDefault();

                try {
                    const url = new URL(href, window.location.origin);

                    // Normalize a form field name to its URL parameter candidates.
                    function getParamNames(field) {
                        let name = field.name;
                        if (!name) return [];

                        const originalName = name;

                        // Support names such as filters[car_maker].
                        const match = name.match(/^filters\[([^\]]+)\]$/);
                        if (match) {
                            name = match[1];
                        }

                        // Support array-style names such as car_maker[].
                        name = name.replace(/\[\]$/, '');

                        const names = [
                            originalName,
                            name,
                            'filters[' + name + ']'
                        ];

                        // Search fields may use different URL parameter names.
                        if (['s', 'search', 'car_s'].includes(name)) {
                            names.push('s', 'search', 'car_s');
                        }

                        return [...new Set(names)];
                    }

                    // Reset every field whose filter is absent from the target URL.
                    section.querySelectorAll(
                        'select, textarea, input:not([type="submit"]):not([type="button"]):not([type="reset"]):not([type="hidden"])'
                    ).forEach(function (field) {
                        const paramNames = getParamNames(field);

                        if (!paramNames.length) return;

                        const filterStillActive = paramNames.some(function (param) {
                            return url.searchParams.has(param);
                        });

                        if (filterStillActive) return;

                        if (
                            field.type === 'checkbox' ||
                            field.type === 'radio'
                        ) {
                            field.checked = false;
                        } else if (field.tagName === 'SELECT' && field.multiple) {
                            Array.from(field.options).forEach(function (option) {
                                option.selected = false;
                            });
                        } else {
                            field.value = '';
                        }
                    });
                } catch (err) {
                    console.error('Could not reset the removed car filter:', err);
                }

                runFilter();
            }
        }
    });
}