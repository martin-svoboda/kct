import React from 'react';

const EventItem = ({ item }) => {
	const formatDate = (dateString) => {
		const date = new Date(dateString);
		return {
			dayName: date.toLocaleDateString('default', { weekday: 'long' }),
			dateNumber: date.toLocaleDateString('default', { day: 'numeric', month: 'numeric' }),
			dateYear: date.toLocaleDateString('default', { year: 'numeric' }),
		};
	};

	const { dayName, dateNumber, dateYear } = item.date ? formatDate(item.date) : {};

	return (
		<li>
			<a href={item.permalink} className="event" title={`${dayName} ${dateNumber} ${dateYear} ${item.title}` }>
				<div className="date">
					{item.date && (
						<>
							<span className="day-name">{dayName}</span>
							<span className="date-number">{dateNumber}</span>
							<span className="date-year">{dateYear}</span>
						</>
					)}
				</div>
				{item.image && item.image.url && (
					<img src={item.image.url} title={item.title} alt={item.title} />
				)}
				<div className="content">
					<h3>{item.year ? `${item.year}. ` : ''}{item.title}</h3>
					<p>
						{item.organiser?.name && `${item.organiser.name}, `}
						{item.place && `${item.place}${item.district ? `, okr. ${item.district}` : ''}`}
					</p>
					<p>
						{item.details && Array.isArray(item.details) &&
							item.details
								.filter(detail => !!(detail.km && detail.name && detail.km.length > 0))
								.map(detail => {
									const words = detail.name.split(" ");
									const acronym = words.map(word => word.charAt(0)).join("").toUpperCase();
									return `${acronym}: ${detail.km}`;
								})
								.join('; ')}
					</p>
				</div>
				<div className="icons">
					{item.details && Array.isArray(item.details) &&
						item.details
							.filter(detail => !!detail.icon)
							.map(detail => (
								<img
									key={detail.icon}
									src={detail.icon}
									title={detail.name}
									alt={detail.name}
									width="30"
									height="30"
								/>
							))}
				</div>
			</a>
		</li>
	);
};

export default EventItem;
