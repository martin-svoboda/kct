import React, {useState, useEffect} from 'react';
import apiFetch from '@wordpress/api-fetch';
import DatePicker, {registerLocale} from 'react-datepicker';
import 'react-datepicker/dist/react-datepicker.css';
import cs from "date-fns/locale/cs";

registerLocale("cs", cs);
import DepartmentItem from "./DepartmentItem";
import Map from "../../Map";

export default function App() {
	const [displayedDepartments, setDisplayedDepartments] = useState([]);
	const [isLoading, setIsLoading] = useState(true);
	const fetchDepartments = async () => {
		try {
			console.info(`Call endpoint: /kct/v1/departments`)
			const response = await apiFetch({path: `/kct/v1/departments`});
			setDisplayedDepartments(response);
			setIsLoading(false);
		} catch (error) {
			console.error('Error fetching departments:', error);
			setIsLoading(false);
		}
	};

	if (displayedDepartments.length === 0) {
		fetchDepartments();
	}

	console.log(displayedDepartments)

	return (
		<>
			<Map items={displayedDepartments}/>
			<div className="container">
				<main id="primary" className="site-main">
					<div className="departments">
						{isLoading && <div id="loading">
							<div className={"spinner"}></div>
							Načítám...</div>}
						{!isLoading && displayedDepartments.length === 0 && <div>Nebyli nalezeny žádné odbory.</div>}
						{!isLoading && displayedDepartments.length > 0 &&
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
