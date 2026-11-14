/**
 * Progresivní AJAX filtr akcí na archivu /akce/.
 *
 * Bez JS: filtr je nativní <form method="get"> → odeslání reloadne stránku
 * s parametry v URL a PHP vykreslí odpovídající výpis (viz archive-akce.php).
 *
 * S JS: odchytneme submit/change, přes REST /kct/v1/events-list stáhneme HOTOVÉ
 * HTML výpisu + data markerů, prohodíme jen výpis a překreslíme mapu — bez reloadu.
 *
 * Tentýž endpoint dotahuje i další část výpisu (initEventsMore): karty se
 * připojí pod už načtené, nic nad nimi se nepřekresluje a stránka se nehne.
 */

function apiBase() {
	const base = (typeof window !== 'undefined' && window.kct_api_url) ? window.kct_api_url : '/wp-json/kct/v1';
	return String(base).replace(/\/+$/, '');
}

function initEventsFilter() {
	const form = document.querySelector('[data-events-filter]');
	const list = document.querySelector('.events-main .events');

	if (!form || !list) {
		return;
	}

	const countEl = document.querySelector('[data-events-count]');
	const dateFrom = form.querySelector('[name="date_from"]');
	const dateTo = form.querySelector('[name="date_to"]');

	let controller = null;
	let timer = null;

	// Konec nejde nastavit před začátek. PHP tu vazbu vypisuje při načtení,
	// tady se obnovuje po každé změně — jinak by po prvním přefiltrování
	// (které stránku nereloaduje) zůstaly hranice stát na původních datech.
	function syncDateBounds() {
		if (dateFrom && dateTo) {
			dateFrom.max = dateTo.value || '';
			dateTo.min = dateFrom.value || '';
		}
	}

	function say(text) {
		if (countEl) {
			countEl.textContent = text;
		}
	}

	// Bez async/await schválně — vyhneme se regenerator-runtime a udržíme bundle malý.
	function apply() {
		const query = new URLSearchParams(new FormData(form)).toString();

		syncDateBounds();

		// Zrcadli filtr do URL (sdílení/zpět), bez reloadu.
		window.history.replaceState(null, '', query ? `?${query}` : window.location.pathname);

		if (controller) {
			controller.abort();
		}
		controller = new AbortController();
		list.classList.add('is-loading');

		fetch(`${apiBase()}/events-list?${query}`, {
			signal: controller.signal,
			credentials: 'omit',
			headers: { Accept: 'application/json' },
		})
			.then(res => {
				if (!res.ok) {
					throw new Error(`events-list ${res.status}`);
				}
				return res.json();
			})
			.then(data => {
				list.innerHTML = data.html || '';
				say(data.countLabel || '');
				// Výpis je nový, takže i tlačítko pod ním — hlídač musí na to nové.
				watchMore();
				if (window.kctMap && typeof window.kctMap.setMarkers === 'function') {
					window.kctMap.setMarkers(data.markers || []);
				}
			})
			.catch(err => {
				if (err.name === 'AbortError') {
					return;
				}

				// Dosud se chyba napsala jen do konzole: návštěvníkovi zůstal na
				// obrazovce starý výpis a nic mu neřeklo, že nesouhlasí s filtrem.
				console.error('Filtr akcí selhal:', err);
				say(window.kct_filter_error || 'Filtr se nepodařilo použít. Zkuste to prosím znovu.');
			})
			.finally(() => list.classList.remove('is-loading'));
	}

	form.addEventListener('submit', event => {
		event.preventDefault();
		apply();
	});

	// Změna kteréhokoli pole → přefiltruj (lehký debounce kvůli datumům).
	form.addEventListener('change', () => {
		clearTimeout(timer);
		timer = setTimeout(apply, 120);
	});

	syncDateBounds();
	initFilterBox();
}

/**
 * Dotahování dalších akcí pod výpis.
 *
 * Výpis se nesmí hnout: karty, které už někdo čte, zůstávají na místě a další
 * část se přidá pod ně. Proto se nic nepřekresluje a nikam se neroluje.
 *
 * Dotahuje se samo, jakmile se konec výpisu přiblíží na dosah obrazovky
 * (IntersectionObserver s rezervou, aby na čekání nedošlo). Tlačítko pod
 * výpisem zůstává pro prohlížeč bez IntersectionObserveru, pro ovládání
 * klávesnicí a pro druhý pokus po chybě; bez JavaScriptu je to obyčejný odkaz
 * na další část výpisu.
 */
let watchMore = () => {};

function initEventsMore() {
	const list = document.querySelector('.events-main .events');

	if (!list) {
		return;
	}

	const form = document.querySelector('[data-events-filter]');
	let observer = null;
	let loading = false;

	const region = () => list.querySelector('[data-events-more]');

	function query(page) {
		const params = form ? new URLSearchParams(new FormData(form)) : new URLSearchParams();
		params.set('kct_page', String(page));
		return params.toString();
	}

	// Bez async/await schválně — vyhneme se regenerator-runtime a udržíme bundle malý.
	function load() {
		const box = region();
		const page = box ? parseInt(box.dataset.next, 10) : 0;

		if (!box || loading || !page) {
			return;
		}

		const button = box.querySelector('a');

		loading = true;
		box.classList.add('is-loading');

		if (button) {
			button.textContent = window.kct_more_loading || 'Načítám…';
		}

		fetch(`${apiBase()}/events-list?${query(page)}`, {
			credentials: 'omit',
			headers: { Accept: 'application/json' },
		})
			.then(res => {
				if (!res.ok) {
					throw new Error(`events-list ${res.status}`);
				}
				return res.json();
			})
			.then(data => {
				const ul = list.querySelector('.events-list');

				if (ul && data.items) {
					ul.insertAdjacentHTML('beforeend', data.items);
				}

				// Tlačítko nahradí stav, který poslal server: buď tlačítko na další
				// část se zbývajícím počtem, nebo nic, když už nic nezbývá.
				box.outerHTML = data.more || '';
				loading = false;
				watchMore();
			})
			.catch(err => {
				// Tichý neúspěch by vypadal jako konec výpisu — zbytek akcí by se
				// ztratil a nikdo by se o tom nedozvěděl.
				console.error('Další akce se nepodařilo načíst:', err);
				loading = false;
				box.classList.remove('is-loading');
				box.classList.add('has-error');

				if (button) {
					button.textContent = window.kct_more_error || 'Další akce se nenačetly. Zkuste to prosím znovu.';
				}
			});
	}

	watchMore = function () {
		if (observer) {
			observer.disconnect();
			observer = null;
		}

		const box = region();

		if (!box || typeof window.IntersectionObserver !== 'function') {
			return;
		}

		observer = new window.IntersectionObserver(entries => {
			if (entries.some(entry => entry.isIntersecting)) {
				load();
			}
		// Rezerva se počítá na obrazovku navíc: karty se mají dotáhnout dřív, než
		// se někdo doroluje na konec, jinak by čekal u prázdna.
		}, { rootMargin: '1200px 0px' });

		observer.observe(box);
	};

	// Delegovaně, protože tlačítko se po každém dotažení nahrazuje novým.
	list.addEventListener('click', event => {
		const link = event.target.closest('[data-events-more] a');

		if (!link) {
			return;
		}

		event.preventDefault();
		load();
	});

	watchMore();
}

/**
 * Filtr na úzké obrazovce.
 *
 * Server vypisuje `<details open>`, aby byl filtr bez JavaScriptu prostě vidět.
 * Tady se na mobilu zavře — výpis je to, pro co lidé na stránku chodí, a filtr
 * ho tlačil pod okraj obrazovky.
 *
 * Zavře se i tehdy, když je filtr nastavený: zavřený box v záhlaví píše, podle
 * čeho se filtruje, takže se ta informace neztratí.
 */
function initFilterBox() {
	const details = document.querySelector('[data-events-filter-box]');

	if (!details || !window.matchMedia) {
		return;
	}

	if (window.matchMedia('(max-width: 767px)').matches) {
		details.open = false;
	}
}

function initEventsArchive() {
	initEventsFilter();
	initEventsMore();
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initEventsArchive);
} else {
	initEventsArchive();
}
