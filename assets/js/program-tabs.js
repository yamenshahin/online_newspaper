/**
 * Programs section (logo tabs): a click on a program logo shows that program's episodes. The programs rotate on their
 * own every `data-rotate` ms until the visitor picks one; a bar along the active logo fills up meanwhile. On a switch
 * the old episodes fade out while the new ones rise in one after another. The logo row scrolls with the arrows (a page
 * of whole tiles, smoothly), by dragging with the mouse (it glides on after release), or by swiping on touch screens.
 */
(function () {
    'use strict';

    var REDUCED_MOTION = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var EASE_OUT = 'cubic-bezier(0.22, 1, 0.36, 1)';

    function init(root) {
        var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-program-tab]'));
        var strip = root.querySelector('[data-program-strip]');
        var prev = root.querySelector('[data-program-scroll="prev"]');
        var next = root.querySelector('[data-program-scroll="next"]');
        if (!strip || tabs.length < 2) return;

        var panels = tabs.map(function (tab) {
            return document.getElementById(tab.getAttribute('aria-controls'));
        });
        var fades = []; // panel index => its running fade-out
        var current = 0;
        tabs.forEach(function (tab, i) {
            if (tab.getAttribute('aria-selected') === 'true') current = i;
        });

        function select(index) {
            if (index !== current) {
                tabs.forEach(function (tab, i) {
                    tab.setAttribute('aria-selected', i === index ? 'true' : 'false');
                });
                leave(current);
                enter(index);
                current = index;
            }
            reveal(strip, tabs[index]);
            if (rotation.restart(tabs[index])) preload(panels[(index + 1) % panels.length]);
        }

        // The outgoing episodes fade and lift on top of the incoming ones. Taken out of the flow, so the section
        // takes the new program's height at once.
        function leave(i) {
            var panel = panels[i];
            if (!panel) return;
            if (REDUCED_MOTION || !panel.animate) {
                panel.hidden = true;
                return;
            }
            panel.style.cssText = 'position:absolute;z-index:1;pointer-events:none;top:' + panel.offsetTop + 'px;left:' + panel.offsetLeft + 'px;width:' + panel.offsetWidth + 'px';
            var fade = panel.animate(
                [{ opacity: 1, transform: 'none' }, { opacity: 0, transform: 'translateY(-12px) scale(0.985)' }],
                { duration: 280, easing: 'ease-in' }
            );
            fades[i] = fade;
            fade.onfinish = function () {
                if (fades[i] !== fade) return; // picked again meanwhile
                fades[i] = null;
                panel.hidden = true;
                panel.style.cssText = '';
            };
        }

        // The incoming episodes rise in one after another, from a soft blur into focus.
        function enter(i) {
            var panel = panels[i];
            if (!panel) return;
            if (fades[i]) {
                fades[i].cancel();
                fades[i] = null;
                panel.style.cssText = '';
            }
            panel.hidden = false;
            if (REDUCED_MOTION || !panel.animate) return;
            cards(panel).forEach(function (card, n) {
                if (card.getAnimations) {
                    card.getAnimations().forEach(function (a) {
                        a.cancel();
                    });
                }
                card.animate(
                    [
                        { opacity: 0, transform: 'translateY(24px) scale(0.97)', filter: 'blur(8px)' },
                        { opacity: 1, transform: 'none', filter: 'blur(0)' },
                    ],
                    { duration: 650, delay: 80 + n * 70, easing: EASE_OUT, fill: 'backwards' }
                );
            });
        }

        var rotation = createRotation(root, strip, function () {
            select((current + 1) % tabs.length);
        });
        if (rotation.restart(tabs[current])) preload(panels[(current + 1) % panels.length]);

        tabs.forEach(function (tab, i) {
            tab.addEventListener('click', function () {
                rotation.stop();
                select(i);
            });
        });
        // Swiping or dragging the logo row, or its arrows: the visitor is browsing, the rotation would fight them.
        strip.parentElement.addEventListener('pointerdown', rotation.stop);

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

    // Scrolls the logo row (never the page) until the tab is fully in view.
    function reveal(strip, tab) {
        var view = strip.getBoundingClientRect();
        var box = tab.getBoundingClientRect();
        var pad = 4; // the row's padding: keeps the selected border visible
        var delta = 0;
        if (box.left - pad < view.left) delta = box.left - pad - view.left;
        else if (box.right + pad > view.right) delta = box.right + pad - view.right;
        if (delta) strip.scrollBy({ left: delta, behavior: 'smooth' });
    }

    // Cards of a panel in reading order: the featured grid's large story, then the stories of its 2x2 column.
    function cards(panel) {
        var grid = panel.firstElementChild;
        var list = [];
        if (!grid) return list;
        var featured = getComputedStyle(grid).display === 'flex';
        Array.prototype.forEach.call(grid.children, function (child) {
            if (featured && child.children.length > 1) list.push.apply(list, child.children);
            else list.push(child);
        });
        return list;
    }

    // The next program's images load and decode now (lazy inside a hidden panel, and WordPress decodes them async),
    // so they are there, not blank, when it rotates in.
    function preload(panel) {
        if (!panel) return;
        panel.querySelectorAll('img').forEach(function (img) {
            if (img.loading === 'lazy') img.loading = 'eager';
            if (img.decode) img.decode().catch(function () {});
        });
    }

    // Auto-rotation: the active logo's progress bar fills in `data-rotate` ms and its end calls `advance`, so the bar
    // is the timer: it freezes (and the rotation with it) while the logo row is off screen (nothing changes unseen),
    // the browser tab is hidden, or the mouse or focus is in the section. `stop` ends the rotation for good.
    function createRotation(root, strip, advance) {
        var delay = parseInt(root.getAttribute('data-rotate'), 10) || 5000;
        var timer = null;
        var stopped = false;
        var hovered = false;
        var focused = false;
        var onScreen = !('IntersectionObserver' in window);

        function sync() {
            if (!timer || timer.playState === 'finished') return;
            if (!stopped && !hovered && !focused && onScreen && !document.hidden) timer.play();
            else timer.pause();
        }

        // Starts the countdown on a tab's bar; false when the rotation is over.
        function restart(tab) {
            if (timer) timer.cancel();
            timer = null;
            var bar = tab.querySelector('[data-program-progress]');
            if (stopped || !bar || !bar.animate) return false;
            timer = bar.animate([{ transform: 'scaleX(0)' }, { transform: 'scaleX(1)' }], { duration: delay, easing: 'linear' });
            timer.onfinish = advance;
            sync();
            return true;
        }

        root.addEventListener('mouseenter', function () {
            hovered = true;
            sync();
        });
        root.addEventListener('mouseleave', function () {
            hovered = false;
            sync();
        });
        root.addEventListener('focusin', function () {
            focused = true;
            sync();
        });
        root.addEventListener('focusout', function (e) {
            focused = root.contains(e.relatedTarget);
            sync();
        });
        document.addEventListener('visibilitychange', sync);
        if (!onScreen) {
            new IntersectionObserver(function (entries) {
                onScreen = entries[entries.length - 1].isIntersecting;
                sync();
            }, { threshold: 0.5 }).observe(strip);
        }

        return {
            restart: restart,
            stop: function () {
                stopped = true;
                if (timer) timer.cancel();
                timer = null;
            },
        };
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
