document.addEventListener('DOMContentLoaded', function() {
	var obfuscatedEmails = document.querySelectorAll('.email-obfuscated');
	obfuscatedEmails.forEach(function(element) {
		var obfuscatedEmail = element.getAttribute('data-email').replace(" [zav] ", "@");
		element.innerHTML = '<a href="mailto:' + obfuscatedEmail + '">' + obfuscatedEmail + '</a>';
	});
});
