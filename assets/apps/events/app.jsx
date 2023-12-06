import React from 'react';
import ReactDOM from 'react-dom';
import App from './components/App';

document.querySelectorAll('[data-app="events"]').forEach(root => {
	ReactDOM.render(<App />, root);
});

