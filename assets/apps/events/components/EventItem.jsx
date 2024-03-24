import React from 'react';

const EventItem = ({ item }) => {
	return (
		<li>
			<a href={item.permalink} className="event" title={`${item.formated_date.day_name} ${item.formated_date.number} ${item.formated_date.year} ${item.title}` }>
				<div className="date">
					{item.formated_date && (
						<>
							<span className="day-name">{item.formated_date.day_name}</span>
							<span className="date-number">{item.formated_date.number}</span>
							<span className="date-year">{item.formated_date.year}</span>
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
