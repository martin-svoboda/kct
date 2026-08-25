import React, {useState, useEffect} from 'react';
import DatePicker, {registerLocale} from 'react-datepicker';
import 'react-datepicker/dist/react-datepicker.css';
import cs from "date-fns/locale/cs";
import {apiGet} from "../../api";

registerLocale("cs", cs);
import DepartmentItem from "./DepartmentItem";
import Map from "../../Map";

export default function App() {
	const [displayedDepartments, setDisplayedDepartments] = useState([]);
	const [isLoading, setIsLoading] = useState(true);
	const [error, setError] = useState(false);
	const [reloadKey, setReloadKey] = useState(0);

	useEffect(() => {
		let ignore = false;
		setIsLoading(true);
		setError(false);

		const fetchDepartments = async () => {
			try {
				const response = await apiGet(`/departments`);
				if (ignore) return;
				setDisplayedDepartments(response);
			} catch (error) {
				if (ignore) return;
				console.error('Error fetching departments:', error);
				setError(true);
			} finally {
				if (!ignore) setIsLoading(false);
			}
		};

		fetchDepartments();

		return () => { ignore = true; };
	}, [reloadKey]);

	return (
		<>
			<Map items={displayedDepartments}/>
			<div className="container">
				<main id="primary" className="site-main">
					<div className="departments">
						{isLoading && <div id="loading">
							<div className={"spinner"}></div>
							Načítám...</div>}
						{!isLoading && error &&
							<div className="departments-error">
								Odbory se nepodařilo načíst.{' '}
								<button type="button" onClick={() => setReloadKey(key => key + 1)}>Zkusit znovu</button>
							</div>
						}
						{!isLoading && !error && displayedDepartments.length === 0 && <div>Nebyli nalezeny žádné odbory.</div>}
						{!isLoading && !error && displayedDepartments.length > 0 &&
							<ul className="departments-list">
								{displayedDepartments.map(item => <DepartmentItem key={item.id} item={item}/>)}
							</ul>
						}
					</div>
				</main>
			</div>
		</>
	);
}
