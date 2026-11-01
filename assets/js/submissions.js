/**
 * Entity submissions: cascading picker for the "التصنيف الرئيسي" (target_parent) field.
 *
 * Runs on the front-end [entity_submit] form and on the user_submits edit screen; both are
 * server-rendered by ACF, so everything is wired once on DOMContentLoaded. For every
 * `.submission-parent-picker` (echoed by inc/submissions/form.php right after the number input)
 * the numeric input is turned into a hidden one (same name, so ACF validation errors still attach
 * to the .acf-field wrapper), each level is loaded from GET /hello/v1/submission-parents
 * (`{taxonomy, leaf_depth, parent_depth, depth, final, items:[{id,name}], path:[ids]}`) and the
 * last-level choice is written back into that input ('0' = none).
 *
 * Config comes from wp_localize_script('helloSubmissions') in inc/submissions/registry.php:
 * {parentsUrl, taxonomy, isAdmin, labels:{choose, noParent, noOptions, loading, error, level}}.
 * Tailwind scans this file: every class list below is a complete literal string.
 */
(function () {
    'use strict';

    const cfg = window.helloSubmissions || {};
    const labels = cfg.labels || {};
    const isAdmin = !!cfg.isAdmin;

    // Front-end classes (token vocabulary of the theme; compiled by the Tailwind watcher).
    const FRONT_SELECT_CLASS = 'w-full h-10 rounded-full border border-surface-container-highest bg-white px-4 text-sm text-on-background shadow-xs focus:outline-none focus:ring-2 focus:ring-primary/30';
    const FRONT_LABEL_CLASS = 'text-xs font-bold text-on-surface-variant px-1';
    const FRONT_LEVEL_CLASS = 'submission-parent-level mt-2';
    const FRONT_MUTED_CLASS = 'text-body-sm text-on-surface-variant';
    const FRONT_ERROR_CLASS = 'text-body-sm text-error';
    const FRONT_RETRY_CLASS = 'underline underline-offset-2';

    // Admin classes (WordPress/ACF default styling; .submission-parent-level rules are printed by post-type.php).
    const ADMIN_SELECT_CLASS = 'submission-parent-select';
    const ADMIN_LEVEL_CLASS = 'submission-parent-level';
    const ADMIN_TEXT_CLASS = 'description';

    // The type select of the admin edit screen (ACF select field, ui 0). Not rendered on the front end.
    const TAXONOMY_SELECT = 'select[name="acf[field_submission_taxonomy]"]';

    // Same texts as get_submission_js_labels(); only used when a localized label is missing.
    const FALLBACK_LABELS = {
        choose: 'اختر…',
        noParent: 'لا يحتاج هذا النوع إلى تصنيف رئيسي.',
        noOptions: 'لا توجد خيارات متاحة',
        loading: 'جارٍ التحميل…',
        error: 'تعذر تحميل الخيارات، حاول مرة أخرى.',
        level: 'المستوى %d'
    };

    /** Localized label by key, falling back to the registry text. */
    function text(key) {
        const value = labels[key];
        return typeof value === 'string' && value !== '' ? value : FALLBACK_LABELS[key] || '';
    }

    /** sprintf(labels.level, n) — the label holds a single %d. */
    function levelText(n) {
        return text('level').replace('%d', String(n));
    }

    function make(tag, className, textContent) {
        const el = document.createElement(tag);
        if (className) {
            el.className = className;
        }
        if (textContent !== undefined) {
            el.textContent = textContent;
        }
        return el;
    }

    /** rest_url() may be pretty (/wp-json/...) or ?rest_route=..., so build the query with URL. */
    function buildUrl(taxonomy, parent, term) {
        const url = new URL(cfg.parentsUrl, window.location.href);
        url.searchParams.set('taxonomy', taxonomy);
        url.searchParams.set('parent', String(parent));
        url.searchParams.set('term', String(term));
        return url.toString();
    }

    /** data-taxonomy (front end) → cfg.taxonomy → the admin type select. */
    function resolveTaxonomy(state) {
        return state.picker.dataset.taxonomy || cfg.taxonomy || (state.typeSelect ? state.typeSelect.value : '') || '';
    }

    /** Text of the ACF field label (without the required asterisk), used as the accessible name of unlabeled selects. */
    function fieldLabel(state) {
        const label = state.field.querySelector('.acf-label label');
        return label ? label.textContent.replace(/\*\s*$/, '').trim() : '';
    }

    function abortLoad(state) {
        if (state.controller) {
            state.controller.abort();
            state.controller = null;
        }
    }

    /** Remove every rendered level whose index is >= depth (levels[i] is the select of level i). */
    function removeLevelsFrom(state, depth) {
        while (state.levels.length > depth) {
            const level = state.levels.pop();
            if (level.parentNode) {
                level.parentNode.removeChild(level);
            }
        }
    }

    function clearStatus(state) {
        if (state.status && state.status.parentNode) {
            state.status.parentNode.removeChild(state.status);
        }
        state.status = null;
    }

    /**
     * One status line under the levels: 'loading' | 'noParent' | 'noOptions' | 'error'.
     * The error line is a link that re-runs the failed load.
     */
    function showStatus(state, kind, retry) {
        clearStatus(state);
        let p;
        if (kind === 'error') {
            p = make('p', isAdmin ? ADMIN_TEXT_CLASS : FRONT_ERROR_CLASS);
            p.setAttribute('role', 'alert');
            const link = make('a', isAdmin ? '' : FRONT_RETRY_CLASS, text('error'));
            link.href = '#';
            link.addEventListener('click', function (event) {
                event.preventDefault();
                if (typeof retry === 'function') {
                    retry();
                }
            });
            p.appendChild(link);
        } else {
            p = make('p', isAdmin ? ADMIN_TEXT_CLASS : FRONT_MUTED_CLASS, text(kind));
            p.setAttribute('role', 'status');
        }
        state.picker.appendChild(p);
        state.status = p;
    }

    /** Normalise the REST items to [{id: '123', name: '…'}]; ids are positive integers as strings. */
    function normaliseItems(items) {
        const out = [];
        items.forEach(function (item) {
            if (!item || typeof item !== 'object') {
                return;
            }
            const id = parseInt(item.id, 10);
            if (!(id > 0)) {
                return;
            }
            const name = item.name === undefined || item.name === null ? '' : String(item.name);
            out.push({ id: String(id), name: name !== '' ? name : String(id) });
        });
        return out;
    }

    /** Load the children of `parent` as level `depth` (0 = roots). */
    function load(state, parent, depth) {
        abortLoad(state);
        removeLevelsFrom(state, depth);
        if (state.taxonomy === '') {
            showStatus(state, 'noOptions');
            return;
        }
        const retry = function () {
            load(state, parent, depth);
        };
        showStatus(state, 'loading');
        const controller = new AbortController();
        state.controller = controller;
        fetch(buildUrl(state.taxonomy, parent, state.term), { headers: { Accept: 'application/json' }, signal: controller.signal })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.json();
            })
            .then(function (data) {
                if (state.controller !== controller) {
                    return; // superseded by a newer load
                }
                state.controller = null;
                if (!renderLevel(state, data, depth)) {
                    showStatus(state, 'error', retry);
                }
            })
            .catch(function (err) {
                if ((err && err.name === 'AbortError') || state.controller !== controller) {
                    return;
                }
                state.controller = null;
                showStatus(state, 'error', retry);
            });
    }

    /** Render one level from a REST response; false when the payload is not ours (caller shows the error). */
    function renderLevel(state, data, depth) {
        if (!data || typeof data !== 'object' || !Array.isArray(data.items)) {
            return false;
        }
        const parentDepth = Number(data.parent_depth);
        if (!Number.isFinite(parentDepth)) {
            return false;
        }
        const items = normaliseItems(data.items);
        const path = Array.isArray(data.path) ? data.path.map(function (id) { return String(parseInt(id, 10)); }) : [];
        const isFinal = !!data.final;

        // "No parent" taxonomy (flat): nothing to pick, the field is 0.
        if (parentDepth === -1) {
            state.input.value = '0';
            showStatus(state, 'noParent');
            return true;
        }
        if (!items.length) {
            showStatus(state, 'noOptions'); // input stays as it is ('0' after a change)
            return true;
        }
        clearStatus(state);

        const level = make('div', isAdmin ? ADMIN_LEVEL_CLASS : FRONT_LEVEL_CLASS);
        level.dataset.depth = String(depth);
        const select = make('select', isAdmin ? ADMIN_SELECT_CLASS : FRONT_SELECT_CLASS);
        select.id = 'submission-parent-' + state.index + '-' + depth;
        if (parentDepth >= 1) {
            const label = make('label', isAdmin ? '' : FRONT_LABEL_CLASS, levelText(depth + 1));
            label.htmlFor = select.id;
            level.appendChild(label);
        } else {
            select.setAttribute('aria-label', fieldLabel(state) || text('choose'));
        }
        const placeholder = make('option', '', text('choose'));
        placeholder.value = '';
        select.appendChild(placeholder);
        items.forEach(function (item) {
            const option = make('option', '', item.name);
            option.value = item.id;
            select.appendChild(option);
        });
        level.appendChild(select);
        state.picker.appendChild(level);
        state.levels.push(level);

        select.addEventListener('change', function () {
            onLevelChange(state, select, depth, isFinal);
        });

        // Auto-select: a single option, or the saved path (restore of an existing value).
        let chosen = '';
        if (items.length === 1) {
            chosen = items[0].id;
            select.value = chosen;
            select.disabled = true;
        } else if (path.length > depth && items.some(function (item) { return item.id === path[depth]; })) {
            chosen = path[depth];
            select.value = chosen;
        }
        if (chosen !== '') {
            if (isFinal) {
                state.input.value = chosen;
            } else {
                load(state, parseInt(chosen, 10), depth + 1);
            }
        }
        return true;
    }

    /** User changed a level: deeper levels are dropped; the last level writes the field, the others load children. */
    function onLevelChange(state, select, depth, isFinal) {
        abortLoad(state);
        removeLevelsFrom(state, depth + 1);
        clearStatus(state);
        const value = select.value;
        if (isFinal) {
            state.input.value = value !== '' ? value : '0';
            // Bubbling change: ACF clears the field's validation error on 'focus change' of inner inputs.
            state.input.dispatchEvent(new Event('change', { bubbles: true }));
            return;
        }
        state.input.value = '0';
        if (value !== '') {
            load(state, parseInt(value, 10), depth + 1);
        }
    }

    function initPicker(picker, index) {
        if (picker.dataset.submissionReady === '1') {
            return;
        }
        const field = picker.closest('.acf-field');
        const input = field ? field.querySelector('input[type="number"]') : null;
        if (!input) {
            return;
        }
        picker.dataset.submissionReady = '1';
        input.type = 'hidden'; // name unchanged: ACF's showErrors still finds it and marks the wrapper
        const form = picker.closest('form');
        const state = {
            index: index,
            picker: picker,
            field: field,
            input: input,
            typeSelect: form ? form.querySelector(TAXONOMY_SELECT) : null,
            taxonomy: '',
            term: parseInt(input.value, 10) || 0, // saved parent → the REST `path` restores the selection chain
            levels: [],
            status: null,
            controller: null
        };
        state.taxonomy = resolveTaxonomy(state);

        // Admin: the clerk may change the type; the picker starts over for the new taxonomy.
        if (isAdmin && state.typeSelect) {
            state.typeSelect.addEventListener('change', function () {
                abortLoad(state);
                removeLevelsFrom(state, 0);
                clearStatus(state);
                state.input.value = '0';
                // state.term stays: the REST path is taxonomy-scoped, so a foreign term restores nothing
                // and switching back to the original type restores the saved chain.
                state.taxonomy = resolveTaxonomy(state);
                load(state, 0, 0);
            });
        }

        load(state, 0, 0);
    }

    function init() {
        if (!cfg.parentsUrl) {
            return; // no config: leave the plain number input usable
        }
        Array.prototype.forEach.call(document.querySelectorAll('.submission-parent-picker'), initPicker);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
