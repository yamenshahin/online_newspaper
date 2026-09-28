document.addEventListener('DOMContentLoaded', function () {
    function initFilterWrappers() {
        const wrappers = document.querySelectorAll('.filter-wrapper');

        wrappers.forEach(wrapper => {
            const container = wrapper.querySelector('.filter-container');
            const fadeOverlay = wrapper.querySelector('.filter-fade-overlay');
            const toggleBtn = wrapper.parentElement.querySelector('.filter-toggle-btn');

            if (!container || !toggleBtn) return;

            // Prefer data attribute, fall back to 110 for safety
            const collapsedMax = parseInt(container.dataset.collapsedMax, 10) || 110;
            const collapsedClass = `max-h-[${collapsedMax}px]`;

            // Avoid attaching multiple listeners
            if (toggleBtn.dataset.initialized === 'true') return;
            toggleBtn.dataset.initialized = 'true';

            if (container.scrollHeight > collapsedMax) {
                toggleBtn.classList.remove('hidden');
                toggleBtn.classList.add('inline-flex');

                toggleBtn.addEventListener('click', function () {
                    const isExpanded = container.classList.contains('max-h-[2000px]');

                    if (isExpanded) {
                        container.classList.remove('max-h-[2000px]');
                        container.classList.add(collapsedClass);
                        if (fadeOverlay) fadeOverlay.classList.remove('opacity-0');
                    } else {
                        // Remove any known collapsed classes
                        container.classList.remove('max-h-[110px]', 'max-h-[190px]');
                        container.classList.add('max-h-[2000px]');
                        if (fadeOverlay) fadeOverlay.classList.add('opacity-0');
                    }

                    const textSpan = toggleBtn.querySelector('.toggle-text');
                    const iconSpan = toggleBtn.querySelector('.toggle-icon');

                    if (!isExpanded) {
                        textSpan.textContent = 'عرض أقل';
                        iconSpan.textContent = 'expand_less';
                    } else {
                        textSpan.textContent = 'عرض المزيد';
                        iconSpan.textContent = 'expand_more';
                    }
                });
            } else {
                if (fadeOverlay) fadeOverlay.classList.add('hidden');
                toggleBtn.classList.add('hidden');
                toggleBtn.classList.remove('inline-flex');
            }
        });
    }

    // Run on load
    initFilterWrappers();

    // Re-run when geo views are switched (they dispatch a resize event)
    window.addEventListener('resize', function () {
        // Small delay so the new view is fully visible
        setTimeout(initFilterWrappers, 50);
    });
});