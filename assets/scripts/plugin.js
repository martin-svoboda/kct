document.addEventListener('DOMContentLoaded', function() {
	var obfuscatedEmails = document.querySelectorAll('.email-obfuscated');

	obfuscatedEmails.forEach(function(element) {
		var obfuscatedEmail = element.getAttribute('data-email').replace(" [zav] ", "@");
		element.innerHTML = '<a href="mailto:' + obfuscatedEmail + '">' + obfuscatedEmail + '</a>';
	});

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
