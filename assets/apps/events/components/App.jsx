import React, { useState, useEffect } from 'react';
import DatePicker, { registerLocale } from 'react-datepicker';
import 'react-datepicker/dist/react-datepicker.css';
import cs from "date-fns/locale/cs";
import { apiGet } from "../../api";

registerLocale("cs", cs);
import EventItem from "./EventItem";
import Map from "../../Map";

export default function App() {
    const [displayedEvents, setDisplayedEvents] = useState([]);
    const [eventTypes, setEventTypes] = useState([]);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState(false);
    const [reloadKey, setReloadKey] = useState(0);

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
        let ignore = false;
        setIsLoading(true);
        setError(false);

        const fetchEvents = async () => {
            try {
                const response = await apiGet(`/events?dateFrom=${formatDate(filterCriteria.dateFrom)}&dateTo=${formatDate(filterCriteria.dateTo)}&type=${filterCriteria.type}`);
                if (ignore) return;
                setDisplayedEvents(response);
            } catch (error) {
                if (ignore) return;
                console.error('Error fetching events:', error);
                setError(true);
            } finally {
                if (!ignore) setIsLoading(false);
            }
        };

        fetchEvents();

        return () => { ignore = true; };
    }, [filterCriteria, reloadKey]);

    useEffect(() => {
        let ignore = false;

        const fetchEventTypes = async () => {
            try {
                const response = await apiGet(`/event-types`);
                if (ignore) return;
                const eventTypeArray = Object.values(response);
                setEventTypes(eventTypeArray);
            } catch (error) {
                console.error('Error fetching event types:', error);
            }
        };

        fetchEventTypes();

        return () => { ignore = true; };
    }, []);

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
                <main id="primary" className="site-main" style={{width:'100%'}}>
                    <div className="events">
                        {isLoading && <div id="loading"><div className={"spinner"} ></div> Načítám...</div>}
                        {!isLoading && error &&
                            <div className="events-error">
                                Akce se nepodařilo načíst.{' '}
                                <button type="button" onClick={() => setReloadKey(key => key + 1)}>Zkusit znovu</button>
                            </div>
                        }
                        {!isLoading && !error && displayedEvents.length === 0 && <div>k dispozici nejsou žádné akce.</div>}
                        {!isLoading && !error && displayedEvents.length > 0 &&
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
