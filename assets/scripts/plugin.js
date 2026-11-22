document.addEventListener('DOMContentLoaded', function() {
	initContactGuard();

	// Záložní přepínač mobilního menu pro šablonu, která si žádný nenese.
	//
	// Běží JEN tehdy, když tlačítko neukazuje přes `aria-controls` na jiný
	// prvek než samo menu. Šablona s vlastním vysouvacím panelem (ta naše
	// ukazuje na `#site-nav`) si menu ovládá sama a druhý přepínač jí do toho
	// mluvil: oba zapisovaly `aria-expanded`, tenhle si ho přečetl ještě před
	// cizí změnou a překlopil ho podruhé — takže otevřené menu hlásilo
	// odečítači obrazovky `false`, zavřené `true`, a v `#primary-menu`
	// zůstávalo po zavření `display: block`.
	const toggleButton = document.querySelector('.menu-toggle');
	const menu = document.getElementById('primary-menu');
	const controls = toggleButton ? toggleButton.getAttribute('aria-controls') : null;
	const ownsMenu = toggleButton && menu && (!controls || controls === 'primary-menu');

	if (ownsMenu) {
		toggleButton.addEventListener('click', function() {
			const isExpanded = toggleButton.getAttribute('aria-expanded') === 'true';

			// Přepnutí atributu aria-expanded
			toggleButton.setAttribute('aria-expanded', !isExpanded);

			// Přepnutí zobrazení menu
			if (!isExpanded) {
				menu.style.display = 'block';
			} else {
				menu.style.display = '';
			}
		});
	}

	initDepartmentsFilter();
});

/**
 * Složení kontaktních odkazů ze zakódovaných hodnot.
 *
 * E-mail ani telefon nejsou v HTML čitelně — ve zdroji leží jen hex, který
 * server složil XORem jednoho bajtu (prvním bajtem výstupu). Není to šifra,
 * klíč je ve stejné stránce; jde o to, aby ve zdroji nezbyl vzorek, na který
 * se dá napsat regulární výraz.
 *
 * Teprve tady vznikne skutečný odkaz s plným textem, takže kopírování,
 * odečítač obrazovky i klepnutí na číslo na mobilu fungují jako u obyčejného
 * odkazu. Kdo skripty nemá, vidí čitelnou zálohu v `<noscript>`.
 */
function initContactGuard() {
	const nodes = document.querySelectorAll('[data-kct-email], [data-kct-tel]');

	Array.prototype.forEach.call(nodes, function(node) {
		const isEmail = node.hasAttribute('data-kct-email');
		const value = decodeContact(node.getAttribute(isEmail ? 'data-kct-email' : 'data-kct-tel'));

		if (!value) {
			// Poškozená hodnota: `<noscript>` zálohu nahradit není čím, ale
			// smazat ji taky ne — ať tam zůstane, co tam je.
			return;
		}

		const link = document.createElement('a');

		// V odkazu `tel:` nemají mezery co dělat, zobrazené číslo si je nechá.
		link.setAttribute('href', isEmail ? 'mailto:' + value : 'tel:' + value.replace(/[^\d+]/g, ''));
		link.textContent = node.getAttribute('data-kct-label') || value;

		node.textContent = '';
		node.appendChild(link);
	});
}

/**
 * Rozebere hex zakódovaný ContactScanner::encode().
 *
 * Server hodnotu před XORem procentuálně zakódoval, takže se tady skládá
 * jen ASCII a diakritiku dorovná decodeURIComponent — po bajtech by se
 * UTF-8 muselo slepovat ručně.
 */
function decodeContact(hex) {
	if (!hex || hex.length < 4 || hex.length % 2 !== 0 || !/^[0-9a-fA-F]+$/.test(hex)) {
		return '';
	}

	const key = parseInt(hex.slice(0, 2), 16);
	let out = '';

	for (let i = 2; i < hex.length; i += 2) {
		out += String.fromCharCode(parseInt(hex.slice(i, i + 2), 16) ^ key);
	}

	try {
		return decodeURIComponent(out);
	} catch (e) {
		return '';
	}
}

/**
 * Hledání ve výpisu odborů.
 *
 * Odborů bývají u oblasti desítky a dosud se v nich hledalo jen očima. Filtruje
 * se v prohlížeči nad už vykresleným seznamem — server o tom nevědět nemusí a
 * odpověď je okamžitá.
 *
 * Políčko přidává teprve tenhle skript. Bez JS by neumělo nic a nefunkční
 * políčko je horší než žádné: seznam je i tak celý na stránce, takže se v něm
 * dá hledat prohlížečem.
 */
function initDepartmentsFilter() {
	const list = document.querySelector('[data-departments-filter]');
	const countEl = document.querySelector('[data-departments-count]');

	if (!list) {
		return;
	}

	const items = Array.prototype.slice.call(list.querySelectorAll('li[data-title]'));

	if (items.length < 8) {
		// U krátkého seznamu je políčko na obtíž — přehlédnout ho nejde a ušetří nic.
		return;
	}

	const wrap = document.createElement('div');
	wrap.className = 'departments-search';

	const id = 'departments-search-input';
	const label = document.createElement('label');
	label.setAttribute('for', id);
	label.textContent = (window.kct_departments_search_label || 'Hledat odbor');

	const input = document.createElement('input');
	input.type = 'search';
	input.id = id;
	input.autocomplete = 'off';
	input.placeholder = (window.kct_departments_search_placeholder || 'Název nebo obec');

	wrap.appendChild(label);
	wrap.appendChild(input);
	list.parentNode.insertBefore(wrap, countEl || list);

	// Diakritiku i velikost písmen srovnává server do `data-title`, aby se
	// „Říčany“ našly i po napsání „ricany“. Tady se stejně upraví jen dotaz.
	function normalize(text) {
		return text
			.toLowerCase()
			.normalize('NFD')
			.replace(/[\u0300-\u036f]/g, '');
	}

	function label_for(n) {
		const tpl = window.kct_departments_count || {};
		if (n === 1) {
			return (tpl.one || '%d odbor').replace('%d', n);
		}
		if (n >= 2 && n <= 4) {
			return (tpl.few || '%d odbory').replace('%d', n);
		}
		return (tpl.many || '%d odborů').replace('%d', n);
	}

	input.addEventListener('input', function() {
		const needle = normalize(input.value.trim());
		let shown = 0;

		items.forEach(function(li) {
			const match = !needle || li.getAttribute('data-title').indexOf(needle) !== -1;
			li.hidden = !match;
			if (match) {
				shown += 1;
			}
		});

		if (countEl) {
			countEl.textContent = label_for(shown);
		}
	});
}

/**
 * Náhled PDF propozic.
 *
 * Rámeček dostane adresu teprve tady, a jen tam, kde má smysl. Na mobilu se A4
 * zmenšená na šířku telefonu nepřečte, takže se náhled nechá zavřený a soubor
 * se vůbec nestahuje — nad ním je odkaz, který propozice otevře v prohlížeči
 * PDF, kde se dá zoomovat a listovat.
 *
 * Na desktopu se náhled načte hned. Kdo si ho na mobilu rozbalí, dostane ho taky
 * — jen si o to musí říct.
 */
function initPdfPreviews() {
	const previews = document.querySelectorAll('[data-pdf-preview]');

	if (!previews.length) {
		return;
	}

	const narrow = window.matchMedia && window.matchMedia('(max-width: 767px)').matches;

	previews.forEach(function(details) {
		const frame = details.querySelector('object[data-pdf]');

		if (!frame) {
			return;
		}

		const load = function() {
			if (!frame.getAttribute('data')) {
				frame.setAttribute('data', frame.getAttribute('data-pdf'));
			}
		};

		if (narrow) {
			details.open = false;
			details.addEventListener('toggle', function() {
				if (details.open) {
					load();
				}
			});
			return;
		}

		load();
	});
}

document.addEventListener('DOMContentLoaded', initPdfPreviews);
