/**
 * Volání veřejného KČT REST API prostým fetchem, BEZ nonce.
 *
 * Endpointy /kct/v1/* jsou registrované s permission_callback => __return_true,
 * takže žádnou autentizaci nepotřebují. Dřív se volaly přes @wordpress/api-fetch,
 * který ke každému requestu automaticky přidával nonce (hlavička X-WP-Nonce).
 * Nonce má životnost jen 12–24 h a je vložený do stránky při jejím renderu — pod
 * cache stránek proto vyprší a WordPress pak vrací 403 "rest_cookie_invalid_nonce"
 * i pro veřejné endpointy (kontrola nonce v jádře běží globálně, ještě před
 * permission_callback). Prostý fetch bez nonce se z pohledu WP tváří jako
 * anonymní request a tenhle problém vůbec nemá.
 */

const trimTrailingSlash = (value) => String(value).replace(/\/+$/, '');

/**
 * Základ REST URL. Přednostně z globálu kct_api_url (rest_url('kct/v1'),
 * viz Frontend::setup_assets) — je multisite/permalink bezpečný.
 * Fallback pro jistotu, kdyby global chyběl.
 */
const getBaseUrl = () => {
	if (typeof window !== 'undefined' && window.kct_api_url) {
		return trimTrailingSlash(window.kct_api_url);
	}

	return '/wp-json/kct/v1';
};

/**
 * GET na veřejný KČT endpoint. Vrací naparsované JSON, nebo vyhodí chybu.
 *
 * @param {string} path Cesta v rámci namespace, např. '/events?dateFrom=...'.
 * @returns {Promise<any>}
 */
export async function apiGet(path) {
	const base   = getBaseUrl();
	const suffix = path.startsWith('/') ? path : `/${path}`;

	const response = await fetch(`${base}${suffix}`, {
		method:      'GET',
		credentials: 'omit',
		headers:     { Accept: 'application/json' },
	});

	if (!response.ok) {
		throw new Error(`KČT API request failed (${response.status})`);
	}

	return response.json();
}
