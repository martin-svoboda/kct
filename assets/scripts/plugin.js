document.addEventListener('DOMContentLoaded', function() {
	var obfuscatedEmails = document.querySelectorAll('.email-obfuscated');

	obfuscatedEmails.forEach(function(element) {
		var obfuscatedEmail = element.getAttribute('data-email').replace(" [zav] ", "@");
		element.innerHTML = '<a href="mailto:' + obfuscatedEmail + '">' + obfuscatedEmail + '</a>';
	});

	const toggleButton = document.querySelector('.menu-toggle');
	const menu = document.getElementById('primary-menu');

	toggleButton.addEventListener('click', function() {
		const isExpanded = toggleButton.getAttribute('aria-expanded') === 'true';

		// Přepnutí atributu aria-expanded
		toggleButton.setAttribute('aria-expanded', !isExpanded);

		// Přepnutí zobrazení menu
		if (!isExpanded) {
			menu.style.display = 'block';
		} else {
			menu.style.display = '';
		}
	});
});
