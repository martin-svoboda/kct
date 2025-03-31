import React, {useEffect} from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const API_KEY = 'IVelrOn442cgk26I87WOwie-2jnq_fdhNT_o8qmT74o';

const Map = ({items}) => {
	useEffect(() => {
		// Reset mapy pokud existuje
		const existing = document.getElementById('map');
		if (existing._leaflet_id) {
			existing._leaflet_id = null;
		}

		const map = L.map('map', {
			minZoom: 9,
			maxZoom: 15,
			scrollWheelZoom: false,
		}).setView([49.9, 14.4], 11);

		const bounds = L.latLngBounds(
			[49.3, 13.0],
			[50.6, 15.8]
		);
		map.setMaxBounds(bounds);

		L.tileLayer(`https://api.mapy.cz/v1/maptiles/outdoor/256/{z}/{x}/{y}?apikey=${API_KEY}`, {
			minZoom: 9,
			maxZoom: 15,
			attribution: '<a href="https://api.mapy.cz/copyright" target="_blank">&copy; Seznam.cz a.s. a další</a>',
			bounds: bounds,
			noWrap: true,
		}).addTo(map);

		const LogoControl = L.Control.extend({
			options: {position: 'bottomleft'},
			onAdd: function () {
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

		// 🔴 Vlastní marker ikona
		const customIcon = L.icon({
			iconUrl: window.assets_url + '/img/marker.svg',
			iconSize: [25, 41],
			iconAnchor: [12, 41],
			popupAnchor: [0, -41],
		});

		// Přidání markerů z items
		const markersToAdd = (items || [])
			.filter(item => item.lat && item.lng)
			.map(item => {
				// Sestavení textu s datem (pokud existuje)
				let dateLine = '';
				if (item.formated_date && item.formated_date.day_name && item.formated_date.number && item.formated_date.year) {
					dateLine = `${item.formated_date.day_name} ${item.formated_date.number} ${item.formated_date.year}<br/>`;
				}

				// Sestavení popup obsahu
				const popupContent = `
      <strong>${item.title ?? ''}</strong><br/>
      ${dateLine}
      <a href="${item.permalink ?? '#'}" target="_blank">Detail</a>
    `;

				const marker = L.marker([item.lat, item.lng], {icon: customIcon})
					.addTo(map)
					.bindPopup(popupContent);

				marker.on('click', () => console.log(item));
				return marker;
			});

		if (markersToAdd.length > 0) {
			const group = new L.featureGroup(markersToAdd);
			map.fitBounds(group.getBounds().pad(0.2));
		}

		return () => {
			map.remove();
		};
	}, [items]);

	return <div id="map" style={{width: '100%', height: '500px'}}/>;
};

export default Map;
