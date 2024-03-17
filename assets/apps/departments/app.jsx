import React from 'react';
import ReactDOM from 'react-dom';
import App from './components/App';

document.querySelectorAll('[data-app="departments"]').forEach(root => {
	ReactDOM.render(<App />, root);
});

