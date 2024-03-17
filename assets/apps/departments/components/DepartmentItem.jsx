import React from 'react';

const DepartmentItem = ({item}) => {
	return (
		<li>
			<a href={item.permalink} className="department"
			   title={item.title}>
				{item.image && item.image.url && (
					<img src={item.image.url} title={item.title} alt={item.title}/>
				)}
				<div className="content">
					<h3>{item.title}</h3>
					<p>
						{item.town}
					</p>
				</div>
			</a>
		</li>
	);
};

export default DepartmentItem;
