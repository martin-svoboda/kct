import React, {useState, useEffect} from 'react';
import apiFetch from '@wordpress/api-fetch';
import DatePicker from 'react-datepicker';
import 'react-datepicker/dist/react-datepicker.css';
import EventItem from "./EventItem";
import Map from "./Map";

export default function App() {
	const [events, setEvents] = useState([]);
	const [displayedEvents, setDisplayedEvents] = useState(events);
	const [isLoading, setIsLoading] = useState(true);

	const today = new Date().toISOString().split('T')[0]; // Dnešní datum jako 'YYYY-MM-DD'
	const nextYear = new Date(new Date().setFullYear(new Date().getFullYear() + 1)).toISOString().split('T')[0]; // Datum za rok jako 'YYYY-MM-DD'

	const [filterCriteria, setFilterCriteria] = useState({
		dateFrom: new Date(today),
		dateTo: new Date(nextYear),
	});

	// Funkce na konverzi formátu data
	const formatDate = (date) => {
		const year = date.getFullYear();
		let month = date.getMonth() + 1;
		let day = date.getDate();

		// Přidání nuly před jednociferný měsíc nebo den
		if (month < 10) {
			month = `0${month}`;
		}
		if (day < 10) {
			day = `0${day}`;
		}

		return `${year}-${month}-${day}`;
	};

	useEffect(() => {
		const fetchEvents = async () => {
			try {
				const response = await apiFetch({path: '/kct/v1/events'});
				setEvents(response);
				setIsLoading(false); // Nastavení isLoading na false po načtení dat
			} catch (error) {
				console.error('Error fetching events:', error);
				setIsLoading(false); // Pokud dojde k chybě, také zastaví načítání
			}
		};

		fetchEvents();
	}, []);

	useEffect(() => {
		console.log(filterCriteria);
		if (!events) {
			return;
		}

		// Filter events when filter criteria change
		const filtered = events.filter(item => {
			return (
				(filterCriteria.dateFrom === null || item.date >= formatDate(filterCriteria.dateFrom)) &&
				(filterCriteria.dateTo === null || item.date <= formatDate(filterCriteria.dateTo))
			);
		});
		setDisplayedEvents(filtered);
	}, [filterCriteria, events]);


	return (
		<>
			<Map items={events}/>
			<div className="container">
				<main id="primary" className="site-main">
					<div className="events">
						<label htmlFor="date-from">Od data</label>
						<DatePicker wrapperClassName=""
									className=""
									dateFormat="yyyy-MM-dd"
									selected={filterCriteria.dateFrom}
									onChange={date => setFilterCriteria({
										...filterCriteria,
										dateFrom: date,
									})}
						/>
						<label htmlFor="date-to">Do data</label>
						<DatePicker wrapperClassName=""
									className=""
									dateFormat="yyyy-MM-dd"
									selected={filterCriteria.dateTo}
									onChange={date => setFilterCriteria({
										...filterCriteria,
										dateTo: date,
									})}
						/>
						{isLoading && <div>Načítám...</div>}
						<ul className="events-list">
							{
								displayedEvents.map(item => <>
									<EventItem item={item}/>
								</>)
							}
						</ul>
					</div>
				</main>
			</div>
		</>
	);
}
