import React, { useState, useEffect } from 'react';
import apiFetch from '@wordpress/api-fetch';
import DatePicker, { registerLocale } from 'react-datepicker';
import 'react-datepicker/dist/react-datepicker.css';
import cs from "date-fns/locale/cs";

registerLocale("cs", cs);
import EventItem from "./EventItem";
import Map from "./Map";

export default function App() {
    const [displayedEvents, setDisplayedEvents] = useState([]);
    const [eventTypes, setEventTypes] = useState([]);
    const [isLoading, setIsLoading] = useState(true);

    const today = new Date().toISOString().split('T')[0]; // Dnešní datum jako 'YYYY-MM-DD'
    const nextYear = new Date(new Date().setFullYear(new Date().getFullYear() + 1)).toISOString().split('T')[0]; // Datum za rok jako 'YYYY-MM-DD'

    const [filterCriteria, setFilterCriteria] = useState({
        dateFrom: new Date(today),
        dateTo: new Date(nextYear),
        type: '',
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
        setIsLoading(true);
        const fetchEvents = async () => {
            try {
                console.info(`Call endpoint: /kct/v1/events?dateFrom=${formatDate(filterCriteria.dateFrom)}&dateTo=${formatDate(filterCriteria.dateTo)}&type=${filterCriteria.type}` )
                const response = await apiFetch({ path: `/kct/v1/events?dateFrom=${formatDate(filterCriteria.dateFrom)}&dateTo=${formatDate(filterCriteria.dateTo)}&type=${filterCriteria.type}` });
                setDisplayedEvents(response);
                setIsLoading(false);
            } catch (error) {
                console.error('Error fetching events:', error);
                setIsLoading(false);
            }
        };

        fetchEvents();
    }, [filterCriteria]);

    useEffect(() => {
        const fetchEventTypes = async () => {
            try {
                console.info(`Call endpoint: /kct/v1/event-types` )
                const response = await apiFetch({ path: `/kct/v1/event-types` });
                const eventTypeArray = Object.values(response);
                setEventTypes(eventTypeArray);
            } catch (error) {
                console.error('Error fetching event types:', error);
            }
        };

        fetchEventTypes();
    }, []);

    console.log( displayedEvents )

    return (
        <>
            <Map items={displayedEvents}/>
            <div className="events-filter">
                <div className="container">
                    <div className="events-filter__field">
                        <label htmlFor="date-from">Od</label>
                        <DatePicker
                            wrapperClassName=""
                            className=""
                            locale="cs"
                            dateFormat="dd. M. yyyy"
                            selected={filterCriteria.dateFrom}
                            onChange={date => setFilterCriteria({ ...filterCriteria, dateFrom: date })}
                        />
                    </div>
                    <div className="events-filter__field">
                        <label htmlFor="date-to">Do</label>
                        <DatePicker
                            wrapperClassName=""
                            className=""
                            locale="cs"
                            dateFormat="dd. M. yyyy"
                            selected={filterCriteria.dateTo}
                            onChange={date => setFilterCriteria({ ...filterCriteria, dateTo: date })}
                        />
                    </div>
                    {eventTypes.length > 0 &&
                        <div className="events-filter__field">
                            <label htmlFor="type">Typ akce</label>
                            <select
                                value={filterCriteria.type}
                                onChange={event => setFilterCriteria({ ...filterCriteria, type: event.target.value })}
                            >
                                <option value="">Všechny</option>
                                {eventTypes.map(eventType => (
                                    <option key={eventType.detailid} value={eventType.detailid}>{eventType.name}</option>
                                ))}
                            </select>
                        </div>
                    }
                </div>
            </div>
            <div className="container">
                <main id="primary" className="site-main">
                    <div className="events">
                        {isLoading && <div id="loading"><div className={"spinner"} ></div> Načítám...</div>}
                        {!isLoading && displayedEvents.length === 0 && <div>k dispozici nejsou žádné akce.</div>}
                        {!isLoading && displayedEvents.length > 0 &&
                            <ul className="events-list">
                                {displayedEvents.map(item => <EventItem key={item.id} item={item} />)}
                            </ul>
                        }
                    </div>
                </main>
            </div>
        </>
    );
}
