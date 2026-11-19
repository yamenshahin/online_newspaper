/**
 * Car comparison section: four slots, each a make > model > trim picker that turns into a compact card once
 * a car is chosen. "Compare" loads the side-by-side table of the chosen cars and mirrors them in the address
 * bar (?car_compare=ID,ID#car-compare, replaceState) so the comparison can be shared. A shared link arrives
 * with its slots filled and its table printed by the server: nothing is requested until the visitor acts.
 *
 * Hooks (data-compare-*): template-parts/sections/car_compare_section.php for the slots and the buttons,
 * get_car_compare_table_html() in inc/cars/compare.php for the table. The lists and the table come from the
 * public GET routes under helloCarCompare.restUrl (/models, /cars, /table); the strings from wp_localize_script.
 */
(function () {
	'use strict';

	const cfg = window.helloCarCompare || {};
	const text = cfg.strings || {};
	const MIN = parseInt(cfg.min, 10) || 2;
	const REDUCED_MOTION = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/** rest_url() may be pretty (/wp-json/...) or ?rest_route=..., so the route and its parameter go through URL. */
	function endpoint(route, name, value) {
		const url = new URL(cfg.restUrl, window.location.href);
		const plain = url.searchParams.get('rest_route');
		if (plain) {
			url.searchParams.set('rest_route', plain + '/' + route);
		} else {
			url.pathname = url.pathname.replace(/\/+$/, '') + '/' + route;
		}
		url.searchParams.set(name, String(value));
		return url.toString();
	}

	async function getJson(url) {
		const response = await fetch(url, { headers: { Accept: 'application/json' } });
		if (!response.ok) {
			throw new Error('HTTP ' + response.status);
		}
		return response.json();
	}

	/** Options of a dependent select: the placeholder, then the items (cars under their model year). No items = disabled. */
	function fill(select, placeholder, items) {
		select.textContent = '';
		select.appendChild(new Option(placeholder, ''));
		let group = null;
		items.forEach((item) => {
			const year = item.year ? String(item.year) : '';
			if (year && (!group || group.label !== year)) {
				group = document.createElement('optgroup');
				group.label = year;
				select.appendChild(group);
			}
			(year ? group : select).appendChild(new Option(item.label || item.name, String(item.id)));
		});
		select.disabled = !items.length;
	}

	/** Keys that step through a closed select. */
	function browses(e) {
		const key = e.key || '';
		return !e.altKey && (/^(Arrow|Home|End|Page)/.test(key) || (key.length === 1 && key !== ' '));
	}

	function init(root) {
		const result = root.querySelector('[data-compare-result]');
		const submit = root.querySelector('[data-compare-submit]');
		const clear = root.querySelector('[data-compare-clear]');
		const status = root.querySelector('[data-compare-status]');
		const spinner = root.querySelector('[data-compare-spinner]');
		const slotElements = [...root.querySelectorAll('[data-compare-slot]')];
		if (!cfg.restUrl || !result || !submit || !clear || !status || !slotElements.length) {
			return;
		}

		const slots = slotElements.map((el) => ({
			el,
			car: parseInt(el.dataset.car, 10) || 0,
			make: el.querySelector('[data-compare-make]'),
			model: el.querySelector('[data-compare-model]'),
			trim: el.querySelector('[data-compare-car]'),
			pick: el.querySelector('[data-compare-pick]'),
			chosen: el.querySelector('[data-compare-chosen]'),
			title: el.querySelector('[data-compare-title]'),
			sub: el.querySelector('[data-compare-sub]'),
			change: el.querySelector('[data-compare-change]'),
			wait: null, // the list the slot is waiting for
			timer: 0, // the pause before a list is asked for
			browsing: false,
		}));
		const lists = new Map(); // "models:12" / "cars:34" => promise of the list, kept for the visit
		let busy = false; // a table is being loaded
		let error = '';

		const chosen = () => slots.filter((slot) => slot.car).map((slot) => slot.car);

		/** Buttons, hint and options follow the slots. One car cannot fill two slots: it is disabled in the others. */
		function sync() {
			const ids = chosen();
			submit.disabled = busy || ids.length < MIN;
			clear.disabled = busy;
			if (spinner) {
				spinner.hidden = !busy;
			}
			result.setAttribute('aria-busy', busy ? 'true' : 'false');
			result.classList.toggle('opacity-50', busy);
			const message = error || (ids.length < MIN ? text.hint || '' : '');
			if (status.textContent !== message) {
				status.textContent = message; // only when it changes: screen readers announce every write
			}
			status.classList.toggle('text-error', error !== '');
			status.classList.toggle('text-on-surface-variant', error === '');
			slots.forEach((slot) => {
				[...slot.trim.options].forEach((option) => {
					const id = parseInt(option.value, 10) || 0;
					option.disabled = id > 0 && slots.some((other) => other !== slot && other.car === id);
				});
			});
		}

		/** Mirror the comparison in the address bar; `query` is "?car_compare=1,2" or '' (other parameters stay). */
		function setUrl(query) {
			const url = new URL(window.location.href);
			url.searchParams.delete(cfg.param);
			const search = [url.searchParams.toString(), query.replace(/^\?/, '')].filter(Boolean).join('&');
			// the fragment opens a shared link at the block, not at the top of a long page
			history.replaceState(history.state, '', url.pathname + (search ? '?' + search : '') + (query ? '#' + root.id : url.hash));
		}

		function list(route, name, id) {
			const key = route + ':' + id;
			if (!lists.has(key)) {
				lists.set(key, getJson(endpoint(route, name, id)).then((items) => {
					if (!Array.isArray(items)) {
						throw new Error('Unexpected response');
					}
					return items;
				}).catch((err) => {
					lists.delete(key); // a failed list can be asked for again
					throw err;
				}));
			}
			return lists.get(key);
		}

		/** Fill a dependent select from its list. On failure `parent` returns to its placeholder, so choosing it again retries. */
		async function load(slot, select, route, name, id, placeholder, parent) {
			const wait = (slot.wait = {});
			fill(select, text.loading || '', []);
			error = '';
			sync();
			try {
				const items = await list(route, name, id);
				if (slot.wait !== wait) {
					return; // the visitor chose something else meanwhile
				}
				fill(select, items.length ? placeholder : text.noOptions || placeholder, items);
			} catch (err) {
				if (slot.wait !== wait) {
					return;
				}
				fill(select, placeholder, []);
				parent.value = '';
				error = text.listError || '';
			}
			sync();
		}

		function choose(slot, id, title, sub) {
			error = ''; // the visitor moved on: an old failure is not news any more
			slot.car = id;
			slot.title.textContent = title;
			slot.sub.textContent = sub === title ? '' : sub; // a car without year or trim is listed by its name
			slot.pick.hidden = true;
			slot.chosen.hidden = false;
			sync();
		}

		/** Back to the selects as the visitor left them (make and model stay), ready for another trim. */
		function reopen(slot) {
			error = '';
			slot.car = 0;
			slot.trim.value = '';
			slot.chosen.hidden = true;
			slot.pick.hidden = false;
			sync();
		}

		/**
		 * Ask for a dependent list a moment after the choice: arrow keys and type-ahead on a closed select fire
		 * `change` on every step, and each list is a request. The select shows "loading" meanwhile.
		 */
		function loadSoon(slot, select, route, name, id, placeholder, parent) {
			clearTimeout(slot.timer);
			slot.wait = null; // a list still on its way is not wanted any more
			fill(select, text.loading || '', []);
			slot.timer = setTimeout(() => {
				load(slot, select, route, name, id, placeholder, parent);
			}, 250);
		}

		function resetSelects(slot) {
			clearTimeout(slot.timer);
			slot.wait = null;
			slot.make.value = '';
			fill(slot.model, text.chooseModel || '', []);
			fill(slot.trim, text.chooseCar || '', []);
		}

		function empty(slot) {
			resetSelects(slot);
			reopen(slot);
		}

		function show(html, query) {
			result.innerHTML = html;
			setUrl(query);
		}

		/** Load the table of the chosen cars (with fewer than two there is none to show); `reveal` scrolls to it. */
		async function compare(reveal) {
			const ids = chosen();
			error = '';
			if (ids.length < MIN) {
				show('', '');
				sync();
				return;
			}
			const hadFocus = document.activeElement === submit; // disabled while loading, the button loses the focus
			busy = true;
			sync();
			try {
				const data = await getJson(endpoint('table', 'ids', ids.join(',')));
				if (!data || typeof data.html !== 'string' || !Array.isArray(data.cars)) {
					throw new Error('Unexpected response');
				}
				// The slots take the names the table uses; a car that is no longer published leaves its slot.
				slots.forEach((slot) => {
					const car = data.cars.find((item) => item.id === slot.car);
					if (car) {
						slot.title.textContent = car.title;
						slot.sub.textContent = car.sub;
					} else if (ids.includes(slot.car)) {
						empty(slot);
					}
				});
				show(data.html, typeof data.url === 'string' ? data.url : '');
				if (reveal && data.html) {
					result.scrollIntoView({ behavior: REDUCED_MOTION ? 'auto' : 'smooth', block: 'start' });
				}
			} catch (err) {
				error = text.tableError || '';
			}
			busy = false;
			sync();
			if (hadFocus && !submit.disabled) {
				submit.focus({ preventScroll: true }); // keyboard users stay where they were, not at the top of the page
			}
		}

		slots.forEach((slot) => {
			slot.make.addEventListener('change', () => {
				const make = parseInt(slot.make.value, 10) || 0;
				fill(slot.trim, text.chooseCar || '', []);
				if (make) {
					loadSoon(slot, slot.model, 'models', 'make', make, text.chooseModel || '', slot.make);
				} else {
					clearTimeout(slot.timer);
					slot.wait = null;
					fill(slot.model, text.chooseModel || '', []);
				}
			});

			slot.model.addEventListener('change', () => {
				const model = parseInt(slot.model.value, 10) || 0;
				if (model) {
					loadSoon(slot, slot.trim, 'cars', 'model', model, text.chooseCar || '', slot.model);
				} else {
					clearTimeout(slot.timer);
					slot.wait = null;
					fill(slot.trim, text.chooseCar || '', []);
				}
			});

			// Arrow keys step through a closed select and fire `change` on every step: a keyboard choice is final
			// on Enter or when focus moves on, a pointer's at once.
			const commit = () => {
				const id = parseInt(slot.trim.value, 10) || 0;
				if (!id || slot.car) {
					return false;
				}
				const focused = document.activeElement === slot.trim;
				const name = (select) => (select.selectedOptions[0] ? select.selectedOptions[0].textContent : '');
				choose(slot, id, (name(slot.make) + ' ' + name(slot.model)).trim(), name(slot.trim));
				if (focused) {
					slot.change.focus();
				}
				return true;
			};
			slot.trim.addEventListener('pointerdown', () => {
				slot.browsing = false;
			});
			slot.trim.addEventListener('keydown', (e) => {
				slot.browsing = browses(e);
				if (e.key === 'Enter' && commit()) {
					e.preventDefault(); // or the same key press goes on to the "Change" button that took the focus
				}
			});
			slot.trim.addEventListener('change', () => {
				if (!slot.browsing) {
					commit();
				}
			});
			slot.trim.addEventListener('blur', () => {
				slot.browsing = false;
				commit();
			});

			slot.change.addEventListener('click', () => {
				reopen(slot);
				(slot.trim.disabled ? slot.make : slot.trim).focus();
			});

			resetSelects(slot); // a reload can bring back old choices without their lists
		});

		submit.addEventListener('click', () => {
			compare(true);
		});

		clear.addEventListener('click', () => {
			slots.forEach(empty);
			error = '';
			show('', '');
			sync();
		});

		// Remove button of a table column: its slot empties and the table follows the slots.
		result.addEventListener('click', async (e) => {
			const button = e.target.closest('[data-compare-remove]');
			if (!button || busy) {
				return;
			}
			const id = parseInt(button.dataset.compareRemove, 10) || 0;
			const index = [...result.querySelectorAll('[data-compare-remove]')].indexOf(button);
			const slot = slots.find((candidate) => candidate.car === id);
			if (slot) {
				empty(slot);
			}
			await compare(false);
			// Focus stays in the table while there is one, else it goes to the emptied slot (the slot itself: a
			// focused select would open its list on phones).
			const buttons = result.querySelectorAll('[data-compare-remove]');
			const next = buttons[Math.min(index, buttons.length - 1)] || (slot || slots.find((candidate) => !candidate.car) || slots[0]).el;
			next.focus();
		});

		sync();
	}

	function boot() {
		document.querySelectorAll('[data-car-compare]').forEach(init);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
