import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './components/App';

document.querySelectorAll('[data-app="events"]').forEach(root => {
	createRoot(root).render(<App />);
});
