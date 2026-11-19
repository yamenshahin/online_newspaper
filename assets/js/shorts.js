/**
 * Short videos section: the row of thumbnails and the player it opens.
 *
 * The row scrolls on its own (swipe, trackpad); on mouse devices two arrows move it by a page of thumbnails.
 * A click on a thumbnail opens the video in a full-screen dialog, built here once and shared by every row on
 * the page: a 9:16 frame with YouTube's player, and previous / next by buttons, arrow keys and swipes. The
 * videos of the clicked row are the playlist: when one ends the next one starts, and the last one is the end.
 *
 * The first video is a plain embed, created inside the click so it may start with sound. YouTube's IFrame API
 * is loaded on that first click and attaches to the iframe: from then on the next video loads into the same,
 * already started player (phones need no new tap), and the player tells when a video is over. Without the API
 * (blocked, or not attached yet) every video gets a new iframe instead, and nothing follows on its own.
 *
 * Markup: template-parts/sections/shorts_section.php. Strings: helloShorts (wp_localize_script).
 */
(function () {
    'use strict';

    var T = window.helloShorts || {};
    var EMBED_URL = 'https://www.youtube-nocookie.com/embed/';
    var API_URL = 'https://www.youtube.com/iframe_api';
    var VIDEO_ID = /^[A-Za-z0-9_-]{11}$/;
    var REDUCED_MOTION = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ------------------------------------------------------------------ the row of thumbnails

    // Positions are distances from the row's start. In RTL scrollLeft runs from 0 to negative values; `sign` converts.
    function initRow(root) {
        var strip = root.querySelector('[data-shorts-strip]');
        var prev = root.querySelector('[data-shorts-scroll="prev"]');
        var next = root.querySelector('[data-shorts-scroll="next"]');
        if (!strip || !prev || !next) return;

        var sign = getComputedStyle(strip).direction === 'rtl' ? -1 : 1;

        function max() {
            return strip.scrollWidth - strip.clientWidth;
        }
        function position() {
            return Math.abs(strip.scrollLeft);
        }

        // A page of whole thumbnails, so the row always stops on a thumbnail's edge.
        function page(direction) {
            var item = strip.firstElementChild;
            var step = item ? item.getBoundingClientRect().width + (parseFloat(getComputedStyle(strip).columnGap) || 0) : strip.clientWidth;
            var perPage = Math.max(1, Math.floor(strip.clientWidth / step));
            var target = (Math.round(position() / step) + direction * perPage) * step;
            strip.scrollTo({ left: sign * Math.min(Math.max(target, 0), max()), behavior: REDUCED_MOTION ? 'auto' : 'smooth' });
        }

        // Arrows: hidden while every thumbnail fits, disabled at the row's ends.
        function update() {
            var end = max();
            var pos = position();
            prev.hidden = next.hidden = end <= 1;
            prev.disabled = pos <= 1;
            next.disabled = pos >= end - 1;
        }

        prev.addEventListener('click', function () {
            page(-1);
        });
        next.addEventListener('click', function () {
            page(1);
        });
        strip.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        update();
    }

    // ------------------------------------------------------------------ the player

    var dialog = null; // built on the first click
    var ui = {}; // its parts
    var playlist = []; // [{ id, title, url }]: the videos of the row the dialog was opened from
    var current = 0;
    var opener = null; // the thumbnail that gets the focus back
    var pageOverflow = '';
    var iframe = null;
    var loaded = false; // YouTube's page is in the iframe: the API can talk to it
    var player = null; // the YT.Player of `iframe`
    var apiRequested = false;
    var watcher = 0; // timer of watch()
    var seen = { id: '', time: 0 }; // the video and its clock at the last look
    var endedAt = 0; // when the player said that the video on show ended

    // Phones: the frame nearly fills the screen, previous / next sit under it beside the caption. From 640px the
    // buttons stand beside the frame and its height leaves room for them. 9.5rem of height is for the top bar and
    // the caption.
    var MARKUP = ''
        + '<div class="flex shrink-0 items-center justify-between gap-space-md px-3 py-1.5" data-shorts-backdrop>'
        + '<p class="rounded-full bg-white/10 px-3.5 py-1 text-sm font-semibold" role="status">'
        + '<span class="inline-block [direction:ltr] [unicode-bidi:isolate]" aria-hidden="true" data-shorts-count></span>'
        + '<span class="sr-only" data-shorts-status></span>'
        + '</p>'
        + '<button type="button" class="flex h-11 w-11 cursor-pointer items-center justify-center rounded-full bg-white/10 text-xl text-white transition-colors hover:bg-primary hover:text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white" data-shorts-close>'
        + '<i class="fas fa-xmark" aria-hidden="true"></i>'
        + '</button>'
        + '</div>'
        + '<div class="grid min-h-0 flex-1 grid-cols-[auto_minmax(0,1fr)_auto] content-center items-center justify-center gap-3 px-3 pb-3 sm:grid-cols-[auto_auto_auto] sm:gap-x-4" data-shorts-backdrop>'
        // RTL: previous on the right.
        + '<button type="button" class="col-start-1 row-start-2 flex h-12 w-12 cursor-pointer items-center justify-center rounded-full bg-white/10 text-lg text-white transition-colors hover:bg-primary hover:text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white sm:row-start-1" data-shorts-go="-1">'
        + '<i class="fas fa-chevron-right" aria-hidden="true"></i>'
        + '</button>'
        + '<div class="relative col-[1/-1] row-start-1 aspect-[9/16] h-[min(100dvh_-_9.5rem,(100vw_-_1.5rem)_*_16_/_9)] justify-self-center overflow-hidden rounded-2xl bg-black sm:col-2 sm:h-[min(100dvh_-_9.5rem,(100vw_-_9.5rem)_*_16_/_9)]" data-shorts-frame>'
        // Touch screens: a touch on the iframe never reaches the page, so two clear zones over its sides take the
        // taps and the swipes. YouTube's title (top), controls (bottom) and play button (middle) stay free.
        + '<span class="absolute start-0 top-[14%] bottom-[22%] z-10 hidden w-[22%] cursor-pointer [-webkit-tap-highlight-color:transparent] pointer-coarse:block" aria-hidden="true" data-shorts-go="-1"></span>'
        + '<span class="absolute end-0 top-[14%] bottom-[22%] z-10 hidden w-[22%] cursor-pointer [-webkit-tap-highlight-color:transparent] pointer-coarse:block" aria-hidden="true" data-shorts-go="1"></span>'
        + '</div>'
        + '<button type="button" class="col-start-3 row-start-2 flex h-12 w-12 cursor-pointer items-center justify-center rounded-full bg-white/10 text-lg text-white transition-colors hover:bg-primary hover:text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white sm:row-start-1" data-shorts-go="1">'
        + '<i class="fas fa-chevron-left" aria-hidden="true"></i>'
        + '</button>'
        // From 640px: as wide as the frame, without widening its column.
        + '<div class="col-start-2 row-start-2 min-w-0 text-center sm:w-0 sm:min-w-full">'
        + '<p class="line-clamp-2 text-[15px] leading-snug font-bold" aria-live="polite" data-shorts-title></p>'
        + '<a class="mt-1 inline-flex items-center gap-1.5 rounded-sm text-sm text-white/80 transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white" target="_blank" rel="noopener" data-shorts-watch>'
        + '<i class="fab fa-youtube" aria-hidden="true"></i><span></span>'
        + '</a>'
        + '</div>'
        + '</div>';

    function isOpen() {
        return !!dialog && !dialog.hidden;
    }

    function isRtl() {
        return getComputedStyle(dialog).direction === 'rtl';
    }

    function build() {
        dialog = document.createElement('div');
        dialog.hidden = true;
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-label', T.dialog || '');
        dialog.setAttribute('data-shorts-backdrop', '');
        // touch-none: a swipe is a command here, never a scroll or a pull-to-refresh of the page behind.
        dialog.className = 'fixed inset-0 z-[100000] flex touch-none flex-col bg-inverse-surface/95 text-white';
        dialog.innerHTML = MARKUP;

        ui.count = dialog.querySelector('[data-shorts-count]');
        ui.status = dialog.querySelector('[data-shorts-status]');
        ui.close = dialog.querySelector('[data-shorts-close]');
        ui.frame = dialog.querySelector('[data-shorts-frame]');
        ui.title = dialog.querySelector('[data-shorts-title]');
        ui.watch = dialog.querySelector('[data-shorts-watch]');
        ui.steps = dialog.querySelectorAll('[data-shorts-go]');

        ui.close.setAttribute('aria-label', T.close || '');
        ui.watch.lastElementChild.textContent = T.watch || '';
        dialog.querySelector('button[data-shorts-go="-1"]').setAttribute('aria-label', T.previous || '');
        dialog.querySelector('button[data-shorts-go="1"]').setAttribute('aria-label', T.next || '');

        // A click on the dark area closes; a drag that only ends there (from the caption, say) is not one.
        var pressed = null;
        dialog.addEventListener('pointerdown', function (e) {
            pressed = e.target;
        });
        dialog.addEventListener('click', function (e) {
            var from = pressed || e.target;
            var step = e.target.closest('[data-shorts-go]');
            pressed = null;
            if (step) {
                go(parseInt(step.getAttribute('data-shorts-go'), 10));
            } else if (e.target.closest('[data-shorts-close]') || (from === e.target && e.target.hasAttribute('data-shorts-backdrop'))) {
                close();
            } else if (e.target.closest('[data-shorts-watch]') && canSteer()) {
                player.pauseVideo(); // it goes on in YouTube's tab
            }
        });

        // Swipes that start outside the iframe: sideways (RTL: swipe right = next), or up / down as in YouTube's own Shorts.
        var touch = null;
        dialog.addEventListener('touchstart', function (e) {
            touch = e.touches.length === 1 ? { x: e.touches[0].clientX, y: e.touches[0].clientY } : null;
        }, { passive: true });
        dialog.addEventListener('touchend', function (e) {
            if (!touch) return;
            var dx = e.changedTouches[0].clientX - touch.x;
            var dy = e.changedTouches[0].clientY - touch.y;
            touch = null;
            if (Math.abs(dx) >= 40 && Math.abs(dx) > Math.abs(dy)) {
                go((dx > 0) === isRtl() ? 1 : -1);
            } else if (Math.abs(dy) >= 60 && Math.abs(dy) > Math.abs(dx)) {
                go(dy < 0 ? 1 : -1);
            }
        });
        dialog.addEventListener('touchcancel', function () {
            touch = null;
        });

        document.addEventListener('keydown', onKeydown);
        // Tab leaves YouTube's player without asking this page: wherever the focus lands, it stays in the dialog.
        document.addEventListener('focusin', function (e) {
            if (isOpen() && !dialog.contains(e.target)) ui.close.focus();
        });

        document.body.appendChild(dialog);
    }

    function onKeydown(e) {
        if (!isOpen() || e.altKey || e.ctrlKey || e.metaKey) return;
        if (e.key === 'Tab') {
            trapFocus(e);
            return;
        }
        if (e.key === 'Escape') {
            close();
        } else if (e.key === 'ArrowLeft') {
            go(isRtl() ? 1 : -1); // RTL: left = next
        } else if (e.key === 'ArrowRight') {
            go(isRtl() ? -1 : 1);
        } else if (e.key === 'ArrowDown') {
            go(1);
        } else if (e.key === 'ArrowUp') {
            go(-1);
        } else {
            return;
        }
        e.preventDefault();
    }

    // Tab goes round inside the dialog.
    function trapFocus(e) {
        var stops = Array.prototype.filter.call(dialog.querySelectorAll('button, a[href], iframe'), function (el) {
            return el.getClientRects().length > 0;
        });
        var first = stops[0];
        var last = stops[stops.length - 1];
        var active = document.activeElement;
        if (!dialog.contains(active) || (!e.shiftKey && active === last)) {
            e.preventDefault();
            first.focus();
        } else if (e.shiftKey && active === first) {
            e.preventDefault();
            last.focus();
        }
    }

    // Opens the dialog on a thumbnail's video; false when the thumbnail has no usable video (the link then works as a link).
    function open(link) {
        if (!VIDEO_ID.test(link.getAttribute('data-short') || '')) return false;

        var root = link.closest('[data-shorts]') || document;
        var start = 0;
        playlist = [];
        Array.prototype.forEach.call(root.querySelectorAll('[data-short]'), function (el) {
            var id = el.getAttribute('data-short') || '';
            if (!VIDEO_ID.test(id)) return;
            if (el === link) start = playlist.length;
            playlist.push({ id: id, title: el.getAttribute('data-title') || '', url: el.href });
        });

        if (!dialog) build();
        opener = link;
        ui.steps.forEach(function (el) {
            el.hidden = playlist.length < 2;
        });
        pageOverflow = document.documentElement.style.overflow;
        document.documentElement.style.overflow = 'hidden';
        dialog.hidden = false;
        show(start);
        watcher = setInterval(watch, 300);
        ui.close.focus();
        return true;
    }

    function close() {
        if (!isOpen()) return;
        dialog.hidden = true;
        clearInterval(watcher);
        unmount(); // the video stops with its iframe
        document.documentElement.style.overflow = pageOverflow;
        if (opener && document.contains(opener)) opener.focus();
        opener = null;
    }

    // Buttons, keys and swipes go round: after the last video comes the first.
    function go(step) {
        if (playlist.length > 1) show(current + step);
    }

    function show(i) {
        var n = playlist.length;
        current = ((i % n) + n) % n;
        var video = playlist[current];
        ui.count.textContent = (current + 1) + ' / ' + n;
        ui.status.textContent = (T.counter || '%1$d / %2$d').replace('%1$d', current + 1).replace('%2$d', n);
        ui.title.textContent = video.title;
        ui.watch.href = video.url;

        seen = { id: '', time: 0 };
        endedAt = 0;
        if (canSteer()) {
            player.loadVideoById(video.id);
            iframe.title = video.title;
        } else {
            mount(video);
        }
    }

    // The player has its commands once YouTube's page has answered it.
    function canSteer() {
        return !!player && typeof player.loadVideoById === 'function';
    }

    // A new iframe, not a new src on the old one: navigating an iframe adds a step to the browser's Back button.
    function mount(video) {
        unmount();
        var frame = document.createElement('iframe');
        frame.className = 'absolute inset-0 h-full w-full rounded-2xl border-0';
        frame.title = video.title;
        frame.setAttribute('allow', 'autoplay; encrypted-media; picture-in-picture');
        frame.setAttribute('allowfullscreen', ''); // not "fullscreen" in allow as well: the browser warns about the pair
        frame.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin'); // YouTube refuses to play without a referrer
        frame.src = EMBED_URL + video.id + '?autoplay=1&playsinline=1&rel=0&modestbranding=1&enablejsapi=1&origin=' + encodeURIComponent(window.location.origin);
        frame.addEventListener('load', function () {
            if (frame !== iframe) return; // replaced meanwhile
            loaded = true;
            attach();
        });
        iframe = frame;
        ui.frame.insertBefore(frame, ui.frame.firstChild);
        loadApi();
    }

    function unmount() {
        if (player) {
            try {
                player.destroy(); // takes the iframe out as well
            } catch (err) {
                // Not attached all the way: the iframe goes below.
            }
        }
        player = null;
        loaded = false;
        if (iframe && iframe.parentNode) iframe.parentNode.removeChild(iframe);
        iframe = null;
    }

    // Loads YouTube's IFrame API once. It calls a single global function when it is there: one that another
    // script has put there keeps working.
    function loadApi() {
        if (apiRequested || (window.YT && window.YT.Player)) return;
        apiRequested = true;
        var previous = window.onYouTubeIframeAPIReady;
        window.onYouTubeIframeAPIReady = function () {
            try {
                if (typeof previous === 'function') previous.apply(this, arguments);
            } finally {
                attach();
            }
        };
        if (!document.querySelector('script[src^="' + API_URL + '"]')) {
            var script = document.createElement('script');
            script.src = API_URL;
            script.async = true;
            document.head.appendChild(script);
        }
    }

    // Attaches the API to the iframe that is playing. Not before YouTube's page is in it: the API talks to the
    // iframe with postMessage, and the console reports every message that finds another origin's page there.
    //
    // YouTube's page answers one player only, the first that asks. Google Analytics attaches its own to every
    // YouTube iframe to count the views, usually first: so the player the iframe already has is used (YT.get),
    // and Analytics does the same when it comes second.
    function attach() {
        if (player || !iframe || !loaded || !window.YT || !window.YT.Player) return;
        var frame = iframe;
        player = (frame.id && window.YT.get && window.YT.get(frame.id)) || new window.YT.Player(frame);
        player.addEventListener('onStateChange', function (e) {
            // "Ended" of the video on show (a late one of the video before it does not count): on to the next
            // one. Nothing follows the last.
            if (frame === iframe && e.data === window.YT.PlayerState.ENDED && playingId() === playlist[current].id) {
                endedAt = Date.now();
                if (current < playlist.length - 1) show(current + 1);
            }
        });
    }

    function playingId() {
        var video = canSteer() && player.getVideoData ? player.getVideoData() : null;
        return video ? video.video_id : '';
    }

    // YouTube plays a Short again when it is through: the first times without saying that it ended, later a
    // second after saying so. So the player's clock is watched as well: back at the start right after the last
    // two seconds is the end of the video too. The last video is then held at its start.
    function watch() {
        var id = playingId();
        var time = id && player.getCurrentTime ? player.getCurrentTime() : 0;
        var length = id && player.getDuration ? player.getDuration() : 0;
        var startedOver = id === seen.id && id === playlist[current].id && length > 4 && seen.time > length - 2 && time < 2;
        seen = { id: id, time: time };
        if (!startedOver) return;

        var replayed = endedAt && Date.now() - endedAt > 2000; // well after the end: the visitor pressed replay
        endedAt = 0;
        if (replayed) return;
        if (current < playlist.length - 1) {
            show(current + 1);
        } else {
            player.pauseVideo();
        }
    }

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
        var link = e.target.closest('[data-short]');
        if (link && open(link)) e.preventDefault();
    });

    function boot() {
        document.querySelectorAll('[data-shorts]').forEach(initRow);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
