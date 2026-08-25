import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './components/App';

document.querySelectorAll('[data-app="departments"]').forEach(root => {
	createRoot(root).render(<App />);
});
