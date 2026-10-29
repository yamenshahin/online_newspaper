/**
 * Car directory: gallery thumbnails -> main image, full-screen lightbox (keyboard + swipe,
 * RTL-aware), numeric sortable tables, "read more" toggle, active spec tab.
 *
 * Hooks (data-car-*) are printed by inc/cars/helpers.php and inc/cars/fields.php; the styles live
 * in src/input.css (@layer components, scoped under .car-directory). Enqueued on car screens only.
 */
(function () {
	'use strict';

	const root = () => document.querySelector('.car-directory') || document.body;
	const isRtl = (el) => getComputedStyle(el).direction === 'rtl';

	/** Center a child in a horizontal scroller without touching the page scroll (scrollIntoView would). */
	function centerIn(scroller, child) {
		if (!scroller || !child) {
			return;
		}
		scroller.scrollTo({ left: child.offsetLeft - (scroller.clientWidth - child.offsetWidth) / 2, behavior: 'smooth' });
	}

	// ------------------------------------------------------------------ images of a container

	/** [{thumb, full, orig, alt, el}] of every [data-full] item (thumbnail buttons) in a container. */
	function itemsOf(container) {
		return [...container.querySelectorAll('[data-full]')].map((el) => {
			const img = el.tagName === 'IMG' ? el : el.querySelector('img');
			return {
				thumb: (img && img.currentSrc) || (img && img.src) || el.dataset.full,
				full: el.dataset.full,
				orig: el.dataset.orig || el.dataset.full,
				alt: (img && img.alt) || el.getAttribute('aria-label') || '',
				el,
			};
		});
	}

	// ------------------------------------------------------------------ hero gallery

	const Gallery = {
		root: null,
		current: 0,
		init() {
			this.root = document.querySelector('[data-car-gallery]');
		},
		items() {
			if (!this.root) {
				return [];
			}
			const thumbs = this.root.querySelector('[data-car-thumbs]');
			const items = thumbs ? itemsOf(thumbs) : [];
			if (items.length) {
				return items;
			}
			const main = this.root.querySelector('[data-car-main]');
			return main && main.src ? [{ thumb: main.src, full: main.src, orig: main.dataset.orig || main.src, alt: main.alt, el: main }] : [];
		},
		show(i) {
			const items = this.items();
			if (!items.length) {
				return;
			}
			this.current = (i + items.length) % items.length;
			const main = this.root.querySelector('[data-car-main]');
			if (main && main.src !== items[this.current].full) {
				main.src = items[this.current].full;
			}
			items.forEach((it, n) => it.el.classList.toggle('is-active', n === this.current));
			const count = this.root.querySelector('[data-car-count]');
			if (count) {
				count.textContent = `${this.current + 1} / ${items.length}`;
			}
		},
	};

	// ------------------------------------------------------------------ full-screen viewer

	const Lightbox = {
		el: null,
		items: [],
		current: 0,
		onClose: null,
		lastFocus: null,
		build() {
			const el = document.createElement('div');
			el.className = 'car-lightbox';
			el.hidden = true;
			el.setAttribute('role', 'dialog');
			el.setAttribute('aria-modal', 'true');
			el.setAttribute('aria-label', 'معرض الصور');
			el.innerHTML = '<div class="car-lb-top"><span class="car-lb-title"></span><span class="car-lb-count car-ltr"></span>'
				+ '<button type="button" class="car-lb-btn car-lb-close" aria-label="إغلاق"><i class="fas fa-xmark" aria-hidden="true"></i></button></div>'
				+ '<div class="car-lb-stage"><button type="button" class="car-lb-btn car-lb-prev" aria-label="السابق"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>'
				+ '<img class="car-lb-pic" alt="">'
				+ '<button type="button" class="car-lb-btn car-lb-next" aria-label="التالي"><i class="fas fa-chevron-left" aria-hidden="true"></i></button></div>'
				+ '<div class="car-lb-strip"></div>';
			el.addEventListener('click', (e) => {
				const thumb = e.target.closest('[data-lb]');
				if (e.target.closest('.car-lb-close') || e.target === el || e.target.classList.contains('car-lb-stage')) {
					this.close();
				} else if (e.target.closest('.car-lb-next')) {
					this.go(1);
				} else if (e.target.closest('.car-lb-prev')) {
					this.go(-1);
				} else if (thumb) {
					this.show(+thumb.dataset.lb);
				}
			});
			// swipe on touch screens (RTL: swipe right = next)
			let x0 = null;
			el.addEventListener('touchstart', (e) => { x0 = e.touches[0].clientX; }, { passive: true });
			el.addEventListener('touchend', (e) => {
				if (x0 === null) {
					return;
				}
				const dx = e.changedTouches[0].clientX - x0;
				if (Math.abs(dx) > 50) {
					this.go((dx > 0) === isRtl(el) ? 1 : -1);
				}
				x0 = null;
			});
			root().appendChild(el);
			this.el = el;
		},
		open(items, index, title, onClose) {
			if (!items.length) {
				return;
			}
			if (!this.el) {
				this.build();
			}
			this.items = items;
			this.onClose = onClose || null;
			this.lastFocus = document.activeElement;
			const h1 = document.querySelector('h1');
			this.el.querySelector('.car-lb-title').textContent = title || (h1 ? h1.textContent.trim() : '');
			const strip = this.el.querySelector('.car-lb-strip');
			strip.textContent = '';
			items.forEach((it, i) => {
				const b = document.createElement('button');
				b.type = 'button';
				b.className = 'car-lb-thumb';
				b.dataset.lb = String(i);
				b.setAttribute('aria-label', `صورة ${i + 1}`);
				const img = document.createElement('img');
				img.className = 'car-lb-thumb-pic';
				img.src = it.thumb;
				img.alt = '';
				img.loading = 'lazy';
				b.appendChild(img);
				strip.appendChild(b);
			});
			strip.hidden = items.length < 2;
			this.el.querySelector('.car-lb-prev').hidden = items.length < 2;
			this.el.querySelector('.car-lb-next').hidden = items.length < 2;
			this.el.hidden = false;
			document.body.classList.add('car-lightbox-open');
			this.show(index);
			this.el.querySelector('.car-lb-close').focus();
		},
		close() {
			if (!this.el || this.el.hidden) {
				return;
			}
			this.el.hidden = true;
			document.body.classList.remove('car-lightbox-open');
			if (this.onClose) {
				this.onClose(this.current); // the page image follows where the viewer was left
			}
			if (this.lastFocus && typeof this.lastFocus.focus === 'function') {
				this.lastFocus.focus();
			}
		},
		go(step) {
			this.show(this.current + step);
		},
		show(i) {
			const n = this.items.length;
			if (!n) {
				return;
			}
			this.current = ((i % n) + n) % n;
			const it = this.items[this.current];
			const pic = this.el.querySelector('.car-lb-pic');
			pic.src = it.orig;
			pic.alt = it.alt;
			this.el.querySelector('.car-lb-count').textContent = `${this.current + 1} / ${n}`;
			this.el.querySelectorAll('[data-lb]').forEach((x, k) => x.classList.toggle('is-active', k === this.current));
			centerIn(this.el.querySelector('.car-lb-strip'), this.el.querySelector(`[data-lb="${this.current}"]`));
			[1, -1].forEach((d) => { // preload the neighbours
				const nb = this.items[(this.current + d + n) % n];
				if (nb && nb.orig) {
					new Image().src = nb.orig;
				}
			});
		},
	};

	document.addEventListener('keydown', (e) => {
		if (!Lightbox.el || Lightbox.el.hidden) {
			return;
		}
		const rtl = isRtl(Lightbox.el);
		if (e.key === 'Escape') {
			Lightbox.close();
		} else if (e.key === 'ArrowLeft') {
			Lightbox.go(rtl ? 1 : -1); // RTL: left = next
		} else if (e.key === 'ArrowRight') {
			Lightbox.go(rtl ? -1 : 1);
		} else {
			return;
		}
		e.preventDefault();
	});

	// ------------------------------------------------------------------ sortable tables

	function sortBy(th) {
		const table = th.closest('table');
		const body = table && table.tBodies[0];
		if (!body) {
			return;
		}
		const col = [...th.parentNode.children].indexOf(th);
		const asc = th.getAttribute('aria-sort') !== 'ascending';
		th.parentNode.querySelectorAll('th[data-sort]').forEach((h) => {
			h.setAttribute('aria-sort', 'none');
			h.classList.remove('is-asc', 'is-desc');
		});
		th.setAttribute('aria-sort', asc ? 'ascending' : 'descending');
		th.classList.add(asc ? 'is-asc' : 'is-desc');
		const val = (r) => {
			const v = parseFloat(r.cells[col] ? r.cells[col].dataset.v : '');
			return Number.isFinite(v) ? v : (asc ? Infinity : -Infinity); // rows without a value go last
		};
		[...body.rows].sort((a, b) => (asc ? val(a) - val(b) : val(b) - val(a))).forEach((r) => body.appendChild(r));
	}

	// ------------------------------------------------------------------ read more

	function toggleMore(btn) {
		const id = btn.getAttribute('aria-controls');
		const target = (id && document.getElementById(id)) || btn.previousElementSibling;
		if (!target) {
			return;
		}
		const open = target.classList.toggle('is-open');
		btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		btn.textContent = open ? (btn.dataset.labelOpen || 'إخفاء') : (btn.dataset.labelClosed || '...اقرأ المزيد');
	}

	// ------------------------------------------------------------------ spec tabs

	function setActiveTab(link) {
		if (!link) {
			return;
		}
		const nav = link.closest('[data-car-spec-tabs]');
		nav.querySelectorAll('a').forEach((a) => {
			a.classList.toggle('is-active', a === link);
			if (a === link) {
				a.setAttribute('aria-current', 'true');
			} else {
				a.removeAttribute('aria-current');
			}
		});
		centerIn(nav, link);
	}

	function initSpecTabs() {
		const nav = document.querySelector('[data-car-spec-tabs]');
		if (!nav || !('IntersectionObserver' in window)) {
			return;
		}
		const links = [...nav.querySelectorAll('a[href^="#"]')];
		const sections = links.map((a) => document.getElementById(a.getAttribute('href').slice(1))).filter(Boolean);
		if (!sections.length) {
			return;
		}
		const io = new IntersectionObserver((entries) => {
			entries.forEach((en) => {
				if (en.isIntersecting) {
					setActiveTab(links.find((a) => a.getAttribute('href') === `#${en.target.id}`));
				}
			});
		}, { rootMargin: '-35% 0px -55% 0px' });
		sections.forEach((s) => io.observe(s));
	}

	// ------------------------------------------------------------------ events

	document.addEventListener('click', (e) => {
		const more = e.target.closest('[data-car-more]');
		if (more) {
			toggleMore(more);
			return;
		}
		const thumb = e.target.closest('[data-car-thumbs] [data-full]');
		if (thumb && Gallery.root && Gallery.root.contains(thumb)) {
			Gallery.show(parseInt(thumb.dataset.index, 10) || 0);
			return;
		}
		const open = e.target.closest('[data-car-open]');
		if (open && Gallery.root && Gallery.root.contains(open)) {
			Lightbox.open(Gallery.items(), Gallery.current, '', (i) => Gallery.show(i));
			return;
		}
		const item = e.target.closest('[data-car-lightbox] [data-full]');
		if (item) {
			const items = itemsOf(item.closest('[data-car-lightbox]'));
			Lightbox.open(items, Math.max(0, items.findIndex((it) => it.el === item)), item.getAttribute('aria-label') || '');
			return;
		}
		const th = e.target.closest('table[data-car-sortable] th[data-sort]');
		if (th) {
			sortBy(th);
			return;
		}
		const tab = e.target.closest('[data-car-spec-tabs] a[href^="#"]');
		if (tab) {
			const target = document.getElementById(tab.getAttribute('href').slice(1));
			if (target) {
				e.preventDefault();
				target.scrollIntoView({ behavior: 'smooth', block: 'start' });
				history.replaceState(null, '', tab.getAttribute('href'));
				setActiveTab(tab);
			}
		}
	});

	document.addEventListener('keydown', (e) => {
		if (e.key !== 'Enter' && e.key !== ' ') {
			return;
		}
		const th = e.target.closest && e.target.closest('table[data-car-sortable] th[data-sort]');
		if (th) {
			e.preventDefault();
			sortBy(th);
		}
	});

	function init() {
		Gallery.init();
		initSpecTabs();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
