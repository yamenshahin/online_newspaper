/**
 * Header search + filters: Geographic show-more + combined entity tabs + entity search
 */
document.addEventListener('DOMContentLoaded', function () {
    initHeaderSearch(); // First: it is on every page, so a page-specific init can't stop it.
    initSearchProgress();
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
 * L1 type (government | private | event | international)
 * Gov:   L2 sub → entities
 * Priv:  L2 main → L3 sub → entities
 * Event: L2 category → entities
 * Intl:  entities (L2 category → entities once groups are added)
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

        // Mirrors the classes the template puts on active/inactive tabs,
        // including the hover class that only inactive tabs carry.
        function styleTab(t, on, level) {
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            if (level === 'sub') {
                t.classList.toggle('bg-primary-container', on);
                t.classList.toggle('text-on-primary-container', on);
                t.classList.toggle('bg-surface-container-low', !on);
                t.classList.toggle('text-on-surface-variant', !on);
                t.classList.toggle('hover:bg-surface-container', !on);
            } else {
                t.classList.toggle('bg-primary', on);
                t.classList.toggle('text-on-primary', on);
                t.classList.toggle('bg-surface-container', !on);
                t.classList.toggle('text-on-surface', !on);
                t.classList.toggle('hover:bg-surface-container-high', !on);
            }
        }

        // The selected tab in a tab list, or the first one.
        function pickTab(scope, selector) {
            return scope.querySelector(selector + '[aria-selected="true"]') || scope.querySelector(selector);
        }

        /**
         * Show a type panel. With keep=true, return to the main/sub tabs that
         * were selected before (used when a search is cleared); otherwise
         * start from the first ones.
         */
        function setType(type, keep) {
            section.querySelectorAll('.entity-type-tab').forEach(function (t) {
                styleTab(t, t.getAttribute('data-entity-type') === type, 'type');
            });

            section.querySelectorAll('.entity-type-panel').forEach(function (p) {
                p.classList.toggle('hidden', p.getAttribute('data-entity-type') !== type);
            });

            const panel = section.querySelector('.entity-type-panel[data-entity-type="' + type + '"]');
            if (!panel) return;

            if (type === 'private') {
                const main = keep ? pickTab(panel, '.entity-main-tab') : panel.querySelector('.entity-main-tab');
                if (main) {
                    setPrivateMain(panel, main.getAttribute('data-main-id'), keep);
                    return;
                }
            } else {
                const sub = keep ? pickTab(panel, '.entity-sub-tab') : panel.querySelector('.entity-sub-tab');
                if (sub) {
                    setSub(panel, sub.getAttribute('data-sub-id'), null);
                    return;
                }
            }
            // No tabs at this level: the panel holds a single leaf list.
            panel.querySelectorAll('.entity-sub-panel').forEach(function (sub) {
                sub.classList.remove('hidden');
                ensureLoaded(sub);
            });
        }

        function setPrivateMain(typePanel, mainId, keep) {
            typePanel.querySelectorAll('.entity-main-tab').forEach(function (t) {
                styleTab(t, t.getAttribute('data-main-id') === mainId, 'main');
            });

            typePanel.querySelectorAll('.entity-main-panel').forEach(function (p) {
                const on = p.getAttribute('data-main-id') === mainId;
                p.classList.toggle('hidden', !on);
                if (!on) return;

                const sub = keep ? pickTab(p, '.entity-sub-tab') : p.querySelector('.entity-sub-tab');
                if (sub) {
                    setSub(p, sub.getAttribute('data-sub-id'), mainId);
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
                styleTab(t, on, 'sub');
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

        function cancelSearch() {
            clearTimeout(searchTimer);
            if (searchController) {
                searchController.abort();
                searchController = null;
            }
        }

        // Leave search mode and clear the box (type-tab click).
        function exitSearch() {
            cancelSearch();
            if (searchInput) searchInput.value = '';
            if (resultsEl) resultsEl.replaceChildren();
            showResults(false);
        }

        // Back to the tabs the visitor was on, keeping the text in the box.
        function restoreFromTabs() {
            cancelSearch();
            if (!section.classList.contains('is-entity-searching')) return;
            if (resultsEl) resultsEl.replaceChildren();
            showResults(false);
            const activeType = pickTab(section, '.entity-type-tab');
            if (activeType) {
                setType(activeType.getAttribute('data-entity-type'), true);
            }
        }

        function renderResults(data) {
            if (!resultsEl) return;
            resultsEl.replaceChildren();

            const items = data && Array.isArray(data.items) ? data.items : [];
            if (!items.length) {
                resultsEl.appendChild(makeNote(i18n.no_results || '', 'text-on-surface-variant'));
                return;
            }
            if (data.truncated) {
                resultsEl.appendChild(makeNote(i18n.truncated || '', 'text-on-surface-variant'));
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
                .then(function (data) {
                    if (controller !== searchController) return; // superseded
                    renderResults(data);
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

                // Empty or shorter than the server's minimum (2 characters after
                // normalization): drop any pending search and show the tabs again.
                if (normalizeArabic(raw).length < 2) {
                    restoreFromTabs();
                    return;
                }
                clearTimeout(searchTimer);
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
 * Cars directory – AJAX filters (template-parts/sections/cars_section.php)
 *
 * One GET form holds every filter; this refreshes #cars-chips and #cars-results through
 * admin-ajax (action cars_filter) instead of reloading, mirrors the filters in the URL so the
 * view is shareable, fills the model select from the make without a request, and turns the
 * chips, "clear all" and pagination links into AJAX calls. Without JS the form and links still
 * work as plain GET requests.
 */
function initCarsAjaxFilters() {
    const section = document.getElementById('cars-directory');
    if (!section || typeof CarsFilter === 'undefined') return;

    const form = section.querySelector('form.cars-filter-form');
    const chipsEl = document.getElementById('cars-chips');
    const resultsEl = document.getElementById('cars-results');
    const loadingEl = document.getElementById('cars-loading');
    if (!form || !chipsEl || !resultsEl) return;

    const baseUrl = (section.dataset.baseUrl || CarsFilter.baseUrl || '').replace(/\/?$/, '/');
    const isArchive = section.dataset.isArchive === '1';
    const modelSelect = form.querySelector('select[name="f_model"]');
    let modelsMap = {};
    try {
        const node = document.getElementById('cars-models-map');
        modelsMap = node ? JSON.parse(node.textContent) || {} : {};
    } catch (err) {
        modelsMap = {};
    }
    let searchTimer = null;
    let controller = null; // in-flight request; a newer one aborts it

    // Model options of one make; disabled until a make is chosen.
    function fillModels(makeSlug, selected) {
        if (!modelSelect) return;
        const models = (makeSlug && modelsMap[makeSlug]) || [];
        modelSelect.innerHTML = '';
        modelSelect.appendChild(new Option(makeSlug ? modelSelect.dataset.allLabel : modelSelect.dataset.emptyLabel, ''));
        models.forEach(function (model) {
            modelSelect.appendChild(new Option(model[1], model[0], false, model[0] === selected));
        });
        modelSelect.disabled = !makeSlug;
    }

    // [name, value] pairs of the filled-in fields; list fields keep their [] suffix.
    function collectFilters() {
        const pairs = [];
        new FormData(form).forEach(function (value, key) {
            if (value !== '') pairs.push([key, value]);
        });
        return pairs;
    }

    function pageUrl(pairs, paged) {
        const params = new URLSearchParams();
        pairs.forEach(function (pair) {
            params.append(pair[0], pair[1]);
        });
        const query = params.toString();
        return baseUrl + (paged > 1 ? 'page/' + paged + '/' : '') + (query ? '?' + query : '');
    }

    function requestBody(pairs, paged) {
        const body = new FormData();
        body.append('action', 'cars_filter');
        body.append('nonce', CarsFilter.nonce);
        body.append('base_url', baseUrl);
        body.append('is_car_archive', isArchive ? '1' : '0');
        pairs.forEach(function (pair) {
            const multi = pair[0].endsWith('[]');
            const name = multi ? pair[0].slice(0, -2) : pair[0];
            body.append('filters[' + name + ']' + (multi ? '[]' : ''), pair[1]);
        });
        if (paged > 1) body.append('filters[paged]', String(paged));
        if (section.dataset.perPage) body.append('per_page', section.dataset.perPage); // the section's own page size
        return body;
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

    function replaceResults(html) {
        Array.from(resultsEl.children).forEach(function (child) {
            if (child.id !== 'cars-loading') child.remove();
        });
        const temp = document.createElement('div');
        temp.innerHTML = html;
        while (temp.firstChild) resultsEl.appendChild(temp.firstChild);
    }

    function runFilter(paged) {
        paged = paged > 1 ? paged : 1;
        const pairs = collectFilters();
        if (controller) controller.abort();
        controller = new AbortController();
        const signal = controller.signal;
        showLoading();

        fetch(CarsFilter.ajaxUrl, {
            method: 'POST',
            body: requestBody(pairs, paged),
            credentials: 'same-origin',
            signal: signal
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json || !json.success) return;
                chipsEl.innerHTML = json.data.chips;
                replaceResults(json.data.results);
                try {
                    window.history.replaceState(null, '', pageUrl(pairs, paged));
                } catch (err) {
                    /* unsupported URL: keep the current one */
                }
            })
            .catch(function (err) {
                if (err.name !== 'AbortError') console.error(err);
            })
            .finally(function () {
                if (!signal.aborted) hideLoading();
            });
    }

    // Set every form field from a filter URL (a chip, "clear all"): fields missing from it are cleared.
    function applyUrlToForm(href) {
        const url = new URL(href, window.location.origin);
        const values = {};
        url.searchParams.forEach(function (value, key) {
            const name = key.replace(/\[[^\]]*\]$/, '');
            (values[name] = values[name] || []).push(value);
        });
        fillModels((values.f_make || [''])[0], (values.f_model || [''])[0]);
        form.querySelectorAll('select, input').forEach(function (field) {
            if (!field.name || field.type === 'submit' || field.type === 'button' || field.type === 'hidden') return;
            const list = values[field.name.replace(/\[\]$/, '')] || [];
            if (field.type === 'checkbox' || field.type === 'radio') {
                field.checked = list.indexOf(field.value) !== -1;
            } else {
                field.value = list[0] || '';
            }
        });
    }

    // ===== Field changes =====
    form.addEventListener('change', function (e) {
        if (e.target.name === 'f_make') {
            fillModels(e.target.value, '');
        }
        if (e.target.matches('select, input[type="number"], input[type="checkbox"]')) {
            runFilter(1);
        }
    });

    form.addEventListener('input', function (e) {
        if (e.target.matches('input[type="search"]')) {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { runFilter(1); }, 400);
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearTimeout(searchTimer);
        runFilter(1);
    });

    // ===== Chips, "clear all" =====
    chipsEl.addEventListener('click', function (e) {
        const link = e.target.closest('a[href]');
        if (!link) return;
        e.preventDefault();
        applyUrlToForm(link.getAttribute('href'));
        runFilter(1);
    });

    // ===== Pagination =====
    resultsEl.addEventListener('click', function (e) {
        const link = e.target.closest('a.page-numbers[href]');
        if (!link) return;
        e.preventDefault();
        const match = link.getAttribute('href').match(/\/page\/(\d+)\//);
        runFilter(match ? parseInt(match[1], 10) : 1);
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    section.dataset.carsJs = '1';
}

/**
 * Header search (template-parts/header-search.php)
 *
 * The toggle opens a panel under the header. Escape, the close button, a press
 * outside or tabbing away closes it. Opening the search closes Hello's mobile
 * menu, and opening that menu closes the search.
 */
function initHeaderSearch() {
    const root = document.querySelector('.site-header .header-search');
    if (!root) return;

    const toggle = root.querySelector('.header-search-toggle');
    const input = root.querySelector('input[type="search"]');
    const closeButton = root.querySelector('.header-search-close');
    if (!toggle || !input) return;

    // Hello's mobile menu. hello-frontend.js owns its state, so it is closed
    // through its own toggle, which keeps its aria-expanded and inert in sync.
    const header = root.closest('.site-header');
    const menuToggle = header.querySelector('.site-navigation-toggle');
    const menuHolder = header.querySelector('.site-navigation-toggle-holder');

    function isOpen() {
        return root.classList.contains('is-open');
    }

    function open() {
        if (menuToggle && menuHolder && menuHolder.classList.contains('elementor-active')) {
            menuToggle.click();
        }
        root.classList.add('is-open');
        toggle.setAttribute('aria-expanded', 'true');
        input.focus();
        // Caret after a prefilled query (search results pages).
        input.setSelectionRange(input.value.length, input.value.length);
    }

    function close(restoreFocus) {
        if (!isOpen()) return;
        root.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        if (restoreFocus) toggle.focus();
    }

    toggle.addEventListener('click', function () {
        if (isOpen()) {
            close(true);
        } else {
            open();
        }
    });

    if (closeButton) {
        closeButton.addEventListener('click', function () {
            close(true);
        });
    }

    if (menuToggle) {
        menuToggle.addEventListener('click', function () {
            close(false);
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' || !isOpen()) return;
        e.preventDefault(); // Escape would also clear the search field.
        close(true);
    });

    // pointerdown, not click: iOS sends no click for taps on plain content, and
    // selecting the query with the mouse must not close the panel when the drag
    // ends outside it.
    document.addEventListener('pointerdown', function (e) {
        if (isOpen() && !root.contains(e.target)) close(false);
    });

    // Tabbing out closes the panel, so it never covers the element that gets focus.
    root.addEventListener('focusout', function (e) {
        if (isOpen() && e.relatedTarget && !root.contains(e.relatedTarget)) close(false);
    });
}

/**
 * Search in progress (header search + results-page form)
 *
 * A search loads a new page and takes a couple of seconds, so on submit the
 * button shows a spinner and its data-loading-text, repeat submits are
 * ignored, and the current results (results page) fade. Coming back with the
 * Back button restores a page from the bfcache, so pageshow resets it.
 */
function initSearchProgress() {
    const forms = document.querySelectorAll('.header-search form, .site-search-form');
    if (!forms.length) return;

    function start(form) {
        const button = form.querySelector('button[type="submit"][data-loading-text]');
        const input = form.querySelector('input[name="s"]');

        form.setAttribute('aria-busy', 'true');
        if (input) input.readOnly = true; // Not disabled: a disabled field isn't submitted.

        if (button) {
            button.dataset.idleText = button.textContent.trim();
            const spinner = document.createElement('span');
            spinner.className = 'inline-block size-4 shrink-0 rounded-full border-2 border-current border-t-transparent animate-spin motion-reduce:animate-none';
            spinner.setAttribute('aria-hidden', 'true');
            const label = document.createElement('span');
            label.textContent = button.dataset.loadingText;
            button.replaceChildren(spinner, label);
            button.classList.add('gap-2', 'cursor-wait');
        }

        document.querySelectorAll('.search-results-grid').forEach(function (grid) {
            grid.classList.add('opacity-50', 'pointer-events-none');
        });
    }

    function reset(form) {
        const button = form.querySelector('button[type="submit"][data-loading-text]');
        const input = form.querySelector('input[name="s"]');

        form.removeAttribute('aria-busy');
        if (input) input.readOnly = false;
        if (button && button.dataset.idleText) {
            button.textContent = button.dataset.idleText;
            button.classList.remove('gap-2', 'cursor-wait');
        }
    }

    forms.forEach(function (form) {
        // Runs only for valid submits: required/minlength block the event first.
        form.addEventListener('submit', function (e) {
            if (form.getAttribute('aria-busy') === 'true') {
                e.preventDefault(); // Already searching.
                return;
            }
            start(form);
        });
    });

    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        forms.forEach(reset);
        document.querySelectorAll('.search-results-grid').forEach(function (grid) {
            grid.classList.remove('opacity-50', 'pointer-events-none');
        });
    });
}