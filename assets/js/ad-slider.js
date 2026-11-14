/**
 * Ad Slider section: rotates image ads (fade), dots to jump, pauses on hover/focus.
 */
(function () {
    'use strict';

    var ON = ['opacity-100'];
    var OFF = ['opacity-0', 'pointer-events-none'];
    var DOT_ON = ['w-6', 'bg-primary'];
    var DOT_OFF = ['w-2', 'bg-outline-variant'];

    function init(root) {
        var slides = root.querySelectorAll('[data-ad-slide]');
        var dots = root.querySelectorAll('[data-ad-dot]');
        if (slides.length < 2) return;

        var delay = parseInt(root.getAttribute('data-interval'), 10) || 5000;
        var current = 0;
        var timer = null;

        function show(index) {
            current = (index + slides.length) % slides.length;
            slides.forEach(function (slide, i) {
                var active = i === current;
                slide.classList.remove.apply(slide.classList, active ? OFF : ON);
                slide.classList.add.apply(slide.classList, active ? ON : OFF);
                if (active) {
                    slide.removeAttribute('aria-hidden');
                } else {
                    slide.setAttribute('aria-hidden', 'true');
                }
                var link = slide.querySelector('a');
                if (link) {
                    if (active) link.removeAttribute('tabindex');
                    else link.setAttribute('tabindex', '-1');
                }
            });
            dots.forEach(function (dot, i) {
                var active = i === current;
                dot.classList.remove.apply(dot.classList, active ? DOT_OFF : DOT_ON);
                dot.classList.add.apply(dot.classList, active ? DOT_ON : DOT_OFF);
                if (active) dot.setAttribute('aria-current', 'true');
                else dot.removeAttribute('aria-current');
            });
        }

        function start() {
            stop();
            timer = setInterval(function () {
                show(current + 1);
            }, delay);
        }

        function stop() {
            if (timer) clearInterval(timer);
            timer = null;
        }

        dots.forEach(function (dot, i) {
            dot.addEventListener('click', function () {
                show(i);
                start();
            });
        });

        root.addEventListener('mouseenter', stop);
        root.addEventListener('mouseleave', start);
        root.addEventListener('focusin', stop);
        root.addEventListener('focusout', start);

        start();
    }

    function boot() {
        document.querySelectorAll('[data-ad-slider]').forEach(init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
