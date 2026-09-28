document.addEventListener('DOMContentLoaded', function () {
    const wrappers = document.querySelectorAll('.filter-wrapper');

    wrappers.forEach(wrapper => {
        const container = wrapper.querySelector('.filter-container');
        const fadeOverlay = wrapper.querySelector('.filter-fade-overlay');
        const toggleBtn = wrapper.parentElement.querySelector('.filter-toggle-btn');

        if (container && toggleBtn) {
            // Check if content actually overflows the 110px boundary
            if (container.scrollHeight > 110) {
                toggleBtn.classList.remove('hidden');
                toggleBtn.classList.add('inline-flex');

                toggleBtn.addEventListener('click', function () {
                    const isExpanded = container.classList.contains('max-h-[2000px]');

                    if (isExpanded) {
                        container.classList.remove('max-h-[2000px]');
                        container.classList.add('max-h-[110px]');
                        if (fadeOverlay) fadeOverlay.classList.remove('opacity-0');
                    } else {
                        container.classList.remove('max-h-[110px]');
                        container.classList.add('max-h-[2000px]');
                        if (fadeOverlay) fadeOverlay.classList.add('opacity-0');
                    }

                    const textSpan = toggleBtn.querySelector('.toggle-text');
                    const iconSpan = toggleBtn.querySelector('.toggle-icon');

                    if (!isExpanded) {
                        textSpan.textContent = 'عرض أقل'; // Show Less
                        iconSpan.textContent = 'expand_less';
                    } else {
                        textSpan.textContent = 'عرض المزيد'; // Show More
                        iconSpan.textContent = 'expand_more';
                    }
                });
            } else {
                // If it fits perfectly, hide the gradient fade entirely
                if (fadeOverlay) fadeOverlay.classList.add('hidden');
            }
        }
    });
});