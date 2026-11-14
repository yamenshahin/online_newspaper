/**
 * Programs section (logo tabs): a click on a program logo shows that program's episodes. The logo row scrolls with the
 * arrows (a page of whole tiles, smoothly), by dragging with the mouse (it glides on after release), or by swiping on
 * touch screens.
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

        if (!strip) return;
        var row = createRow(strip);
        if (!prev || !next) return;

        function update() {
            var max = row.max();
            var pos = row.position();
            prev.hidden = next.hidden = max <= 1;
            prev.disabled = pos <= 1;
            next.disabled = pos >= max - 1;
        }

        prev.addEventListener('click', function () {
            row.page(-1);
        });
        next.addEventListener('click', function () {
            row.page(1);
        });
        strip.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        update();
    }

    // Positions are distances from the row's start. In RTL scrollLeft runs from 0 to negative values; `sign` converts.
    function createRow(strip) {
        var sign = getComputedStyle(strip).direction === 'rtl' ? -1 : 1;
        var glide = 0;
        var drag = null;
        var suppressClick = false;

        function max() {
            return strip.scrollWidth - strip.clientWidth;
        }
        function position() {
            return Math.abs(strip.scrollLeft);
        }
        function clamp(pos) {
            return Math.min(Math.max(pos, 0), max());
        }
        function stop() {
            if (glide) cancelAnimationFrame(glide);
            glide = 0;
        }

        // Arrows: a page of whole tiles, so the row always stops on a tile edge.
        function page(direction) {
            stop();
            var tile = strip.firstElementChild;
            var step = tile ? tile.getBoundingClientRect().width + (parseFloat(getComputedStyle(strip).columnGap) || 0) : strip.clientWidth;
            var perPage = Math.max(1, Math.floor(strip.clientWidth / step));
            var target = clamp((Math.round(position() / step) + direction * perPage) * step);
            strip.scrollTo({ left: sign * target, behavior: 'smooth' });
        }

        // After a drag the row glides on with the release speed (px per ms) and slows down.
        function coast(pos, speed) {
            var last = performance.now();
            function frame(now) {
                var dt = now - last;
                last = now;
                pos = clamp(pos + speed * dt);
                speed *= Math.pow(0.94, dt / 16);
                strip.scrollLeft = sign * pos;
                if (Math.abs(speed) > 0.02 && pos > 0 && pos < max()) {
                    glide = requestAnimationFrame(frame);
                } else {
                    glide = 0;
                    strip.style.scrollSnapType = '';
                }
            }
            glide = requestAnimationFrame(frame);
        }

        // Mouse drag (touch screens swipe on their own). A drag never selects the tab it ends on; a click still does.
        strip.addEventListener('pointerdown', function (e) {
            stop();
            if (e.pointerType !== 'mouse' || e.button !== 0) return;
            drag = { x: e.clientX, start: position(), pos: position(), time: performance.now(), speed: 0, id: e.pointerId, moved: false };
        });
        strip.addEventListener('pointermove', function (e) {
            if (!drag) return;
            var dx = e.clientX - drag.x;
            if (!drag.moved) {
                if (Math.abs(dx) < 5) return;
                drag.moved = true;
                strip.style.scrollSnapType = 'none'; // snapping (touch devices) would fight the drag
                try {
                    strip.setPointerCapture(drag.id);
                } catch (err) {
                    // The pointer was already released.
                }
            }
            var now = performance.now();
            var pos = clamp(drag.start - dx * sign);
            if (now > drag.time) {
                drag.speed = 0.8 * ((pos - drag.pos) / (now - drag.time)) + 0.2 * drag.speed;
            }
            drag.pos = pos;
            drag.time = now;
            strip.scrollLeft = sign * pos;
        });

        function end() {
            if (drag && drag.moved) {
                suppressClick = true;
                setTimeout(function () {
                    suppressClick = false;
                }, 0);
                // Held still before letting go: no glide.
                coast(drag.pos, performance.now() - drag.time < 80 ? drag.speed : 0);
            }
            drag = null;
        }
        strip.addEventListener('pointerup', end);
        strip.addEventListener('pointercancel', end);
        strip.addEventListener('wheel', stop, { passive: true });
        strip.addEventListener('click', function (e) {
            if (suppressClick) {
                e.preventDefault();
                e.stopPropagation();
            }
        }, true);
        strip.addEventListener('dragstart', function (e) {
            e.preventDefault(); // the browser's image drag would take over the pointer
        });

        return { max: max, position: position, page: page };
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
