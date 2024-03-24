import React, {useEffect, useMemo} from 'react';
import { load, MapyCz } from 'wpify-mapy-cz';

const Map = ({items}) => {

	const markers = useMemo(() => {
		if (!items) {
			return [];
		}

		return items.filter(item => item.lat && item.lng).map(item => {
				return {
					id: item.id,
					title: item.title,
					longitude: item.lng,
					latitude: item.lat,
					layer: 'markers',
					//pin: 'https://placekitten.com/20/30',
					//pointer: true,
					card: { header: item.title, body: item.town, footer: `<a href=${item.permalink}>Detail odboru</a>` },
					click: (event, options, marker) => console.log(event, options, marker),
				};
			}
		);
	}, [items]);

	useEffect(() => {
		console.log(markers);
		const config = {
			element: document.getElementById('map'), // ID elementu pro zobrazení mapy
			mapType: 'DEF_TURIST',
			//center: { latitude: 50.11968806014661, longitude: 14.42896842864991 }, // Střed mapy
			//zoom: 13, // Úroveň přiblížení
			auto_center_zoom: true,
			default_controls: true,
			markers: markers,
		};

		// Load map with callback
		load(config, (mapycz) => {
			// Manipulace s mapou po načtení
		});
	}, [markers]);

	return <div id="map" style={{ width: '100%', height: '500px' }}></div>;
};

export default Map;
