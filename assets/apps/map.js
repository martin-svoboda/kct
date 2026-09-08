/**
 * Vanilla Leaflet mapa (mapy.cz outdoor) pro archiv akcí i odborů.
 * Markery bere ze serveru přes globál `window.kctMarkers` (viz archive-*.php),
 * takže nedělá žádný REST dotaz. Po inicializaci vystaví `window.kctMap` s
 * metodou `setMarkers()` — používá ji AJAX filtr akcí k překreslení markerů.
 */
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const API_KEY = 'IVelrOn442cgk26I87WOwie-2jnq_fdhNT_o8qmT74o';

let map = null;
let markerGroup = null;
let customIcon = null;

function buildPopup(item) {
	const fd = item.formated_date;
	let dateLine = '';
	if (fd && fd.day_name && fd.number && fd.year) {
		dateLine = `${fd.day_name} ${fd.number} ${fd.year}<br/>`;
	}

	return `<strong>${item.title ?? ''}</strong><br/>${dateLine}<a href="${item.permalink ?? '#'}" target="_blank">Detail</a>`;
}

function setMarkers(items) {
	if (!map) {
		return;
	}

	if (markerGroup) {
		markerGroup.remove();
		markerGroup = null;
	}

	const markers = (items || [])
		.filter(item => item.lat && item.lng)
		.map(item => L.marker([item.lat, item.lng], { icon: customIcon }).bindPopup(buildPopup(item)));

	markerGroup = L.featureGroup(markers).addTo(map);

	if (markers.length > 0) {
		map.fitBounds(markerGroup.getBounds().pad(0.2));
	}
}

function init() {
	const el = document.getElementById('map');
	if (!el) {
		return;
	}

	customIcon = L.icon({
		iconUrl: (window.assets_url || '') + '/img/marker.svg',
		iconSize: [25, 41],
		iconAnchor: [12, 41],
		popupAnchor: [0, -41],
	});

	map = L.map('map', {
		minZoom: 9,
		maxZoom: 15,
		scrollWheelZoom: false,
	}).setView([49.9, 14.4], 11);

	// Volnější maxBounds (širší než oblast), aby při plné šířce nevznikaly
	// šedé pruhy po stranách.
	map.setMaxBounds(L.latLngBounds([48.5, 11.0], [51.5, 18.0]));

	// Bez `bounds` na dlaždicích → dlaždice vyplní celý kontejner (žádné šedé pruhy).
	L.tileLayer(`https://api.mapy.cz/v1/maptiles/outdoor/256/{z}/{x}/{y}?apikey=${API_KEY}`, {
		minZoom: 9,
		maxZoom: 15,
		attribution: '<a href="https://api.mapy.cz/copyright" target="_blank">&copy; Seznam.cz a.s. a další</a>',
		noWrap: true,
	}).addTo(map);

	const LogoControl = L.Control.extend({
		options: { position: 'bottomleft' },
		onAdd() {
			const container = L.DomUtil.create('div');
			const link = L.DomUtil.create('a', '', container);
			link.setAttribute('href', 'http://mapy.cz/');
			link.setAttribute('target', '_blank');
			link.innerHTML = '<img src="https://api.mapy.cz/img/api/logo.svg" />';
			L.DomEvent.disableClickPropagation(link);
			return container;
		},
	});
	new LogoControl().addTo(map);

	setMarkers(window.kctMarkers || []);

	// Veřejné API pro AJAX filtr akcí.
	window.kctMap = { setMarkers };
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', init);
} else {
	init();
}
