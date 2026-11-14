/**
 * Programs section (logo tabs): a click on a program logo shows that program's episodes; the arrows scroll the
 * logo row while it overflows.
 */
(function () {
    'use strict';

    function init(root) {
        var tabs = root.querySelectorAll('[data-program-tab]');
        var strip = root.querySelector('[data-program-strip]');
        var prev = root.querySelector('[data-program-scroll="prev"]');
        var next = root.querySelector('[data-program-scroll="next"]');

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (other) {
                    var active = other === tab;
                    var panel = document.getElementById(other.getAttribute('aria-controls'));
                    other.setAttribute('aria-selected', active ? 'true' : 'false');
                    if (panel) panel.hidden = !active;
                });
                tab.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
            });
        });

        if (!strip || !prev || !next) return;

        // RTL: scrollLeft runs from 0 (start, on the right) to negative values, and scrollBy takes physical directions.
        var sign = getComputedStyle(strip).direction === 'rtl' ? -1 : 1;

        function update() {
            var max = strip.scrollWidth - strip.clientWidth;
            var pos = Math.abs(strip.scrollLeft);
            prev.hidden = next.hidden = max <= 1;
            prev.disabled = pos <= 1;
            next.disabled = pos >= max - 1;
        }

        function scrollRow(direction) {
            strip.scrollBy({ left: sign * direction * strip.clientWidth * 0.8, behavior: 'smooth' });
        }

        prev.addEventListener('click', function () {
            scrollRow(-1);
        });
        next.addEventListener('click', function () {
            scrollRow(1);
        });
        strip.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        update();
    }

    function boot() {
        document.querySelectorAll('[data-program-tabs]').forEach(init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
