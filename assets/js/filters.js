/**
 * Dynamic Taxonomy Filters & Entity Search Handler
 */
document.addEventListener('DOMContentLoaded', function () {
    initFilterWrappers();
    initEntitySearch();
});

/**
 * Initialize Expandable Filter Containers ("Show More / Show Less")
 */
function initFilterWrappers() {
    const wrappers = document.querySelectorAll('.filter-wrapper');

    wrappers.forEach(wrapper => {
        const container = wrapper.querySelector('.filter-container');
        const fadeOverlay = wrapper.querySelector('.filter-fade-overlay');
        const parentSection = wrapper.closest('section') || wrapper.parentElement;
        const toggleBtn = parentSection ? parentSection.querySelector('.filter-toggle-btn') : null;

        if (!container || !toggleBtn) return;

        const collapsedMax = parseInt(container.dataset.collapsedMax, 10) || 110;

        // Prevent attaching multiple event listeners
        if (toggleBtn.dataset.initialized === 'true') return;
        toggleBtn.dataset.initialized = 'true';

        // Check overflow status and set initial states
        function checkOverflow() {
            // Do not recalculate if user is currently searching or has manually expanded
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

        // Run initial calculation
        checkOverflow();

        // Handle Toggle Button Click
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

        // Recalculate on window resize (debounced)
        let resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(checkOverflow, 150);
        });
    });
}

/**
 * Normalize Arabic text for accurate search matching
 */
function normalizeArabic(text) {
    if (!text) return '';
    return text
        .toLowerCase()
        .replace(/[\u064B-\u0652]/g, '') // Remove Tashkeel (diacritics)
        .replace(/[أإآ]/g, 'ا')         // Normalize Alif
        .replace(/ة/g, 'ه')             // Normalize Ta Marbouta
        .replace(/ى/g, 'ي')             // Normalize Alef Maqsoora
        .replace(/^ال/g, '')            // Strip leading "ال"
        .trim();
}

/**
 * Initialize Client-Side Entity Live Filtering
 */
function initEntitySearch() {
    const searchInputs = document.querySelectorAll('.entity-search-input');

    searchInputs.forEach(searchInput => {
        const section = searchInput.closest('section') || searchInput.parentElement;
        if (!section) return;

        const mainGroups = section.querySelectorAll('.entity-main');
        const subGroups = section.querySelectorAll('.entity-sub');
        const pills = section.querySelectorAll('.entity-pill');
        const filterContainer = section.querySelector('.filter-container');
        const fadeOverlay = section.querySelector('.filter-fade-overlay');
        const toggleBtn = section.querySelector('.filter-toggle-btn');

        searchInput.addEventListener('input', function (e) {
            const rawQuery = e.target.value.trim();
            const query = normalizeArabic(rawQuery);

            // 1. Reset state when search input is empty
            if (query === '') {
                pills.forEach(pill => {
                    pill.style.display = '';
                    pill.classList.remove('hidden');
                });
                subGroups.forEach(sub => {
                    sub.style.display = '';
                    sub.classList.remove('hidden');
                });
                mainGroups.forEach(main => {
                    main.style.display = '';
                    main.classList.remove('hidden');
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

            // 2. Expand container during active search
            if (filterContainer) {
                filterContainer.classList.add('is-searching');
                filterContainer.style.maxHeight = 'none';
            }
            if (fadeOverlay) fadeOverlay.classList.add('opacity-0');

            // 3. Filter individual pills using normalized Arabic matching
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

            // 4. Hide empty sub-groups
            subGroups.forEach(sub => {
                const visiblePills = sub.querySelectorAll('.entity-pill:not([style*="display: none"])');
                if (visiblePills.length > 0) {
                    sub.style.display = '';
                    sub.classList.remove('hidden');
                } else {
                    sub.style.display = 'none';
                    sub.classList.add('hidden');
                }
            });

            // 5. Hide empty main groups
            mainGroups.forEach(main => {
                const visiblePills = main.querySelectorAll('.entity-pill:not([style*="display: none"])');
                if (visiblePills.length > 0) {
                    main.style.display = '';
                    main.classList.remove('hidden');
                } else {
                    main.style.display = 'none';
                    main.classList.add('hidden');
                }
            });
        });
    });
}

/**
 * Fetch entity typeahead search results via REST API (Optional)
 */
let fetchDebounceTimer;
function fetchResultsREST(query, resultsContainer) {
    clearTimeout(fetchDebounceTimer);

    if (query.length < 2) {
        resultsContainer.innerHTML = '';
        resultsContainer.classList.add('hidden');
        return;
    }

    fetchDebounceTimer = setTimeout(() => {
        const fetchUrl = `${helloEntityNav.searchUrl}?s=${encodeURIComponent(query)}`;

        fetch(fetchUrl)
            .then(res => res.json())
            .then(data => {
                if (!Array.isArray(data) || data.length === 0) {
                    resultsContainer.innerHTML = `<li class="px-4 py-3 text-sm text-on-surface-variant">لا توجد نتائج مطابقة</li>`;
                    resultsContainer.classList.remove('hidden');
                    return;
                }

                let html = '';
                data.forEach(item => {
                    html += `
                        <li>
                            <a href="${item.link}" class="block px-4 py-2.5 text-sm text-on-background hover:bg-surface-container-high transition-colors">
                                ${item.name}
                            </a>
                        </li>
                    `;
                });

                resultsContainer.innerHTML = html;
                resultsContainer.classList.remove('hidden');
            })
            .catch(() => {
                resultsContainer.classList.add('hidden');
            });
    }, 250);
}