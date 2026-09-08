/**
 * Progresivní AJAX filtr akcí na archivu /akce/.
 *
 * Bez JS: filtr je nativní <form method="get"> → odeslání reloadne stránku
 * s parametry v URL a PHP vykreslí odpovídající výpis (viz archive-akce.php).
 *
 * S JS: odchytneme submit/change, přes REST /kct/v1/events-list stáhneme HOTOVÉ
 * HTML výpisu + data markerů, prohodíme jen výpis a překreslíme mapu — bez reloadu.
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

	let controller = null;
	let timer = null;

	// Bez async/await schválně — vyhneme se regenerator-runtime a udržíme bundle malý.
	function apply() {
		const query = new URLSearchParams(new FormData(form)).toString();

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
				if (window.kctMap && typeof window.kctMap.setMarkers === 'function') {
					window.kctMap.setMarkers(data.markers || []);
				}
			})
			.catch(err => {
				if (err.name !== 'AbortError') {
					console.error('Filtr akcí selhal:', err);
				}
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
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initEventsFilter);
} else {
	initEventsFilter();
}
