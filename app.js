// Portfolio website interactions

const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// ------------------------------------------
// Scroll reveal
// ------------------------------------------
const revealElements = document.querySelectorAll('.reveal');

if ('IntersectionObserver' in window && !prefersReducedMotion) {
	const revealObserver = new IntersectionObserver((entries, observer) => {
		entries.forEach(entry => {
			if (entry.isIntersecting) {
				entry.target.classList.add('show');
				observer.unobserve(entry.target);
			}
		});
	}, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

	revealElements.forEach(el => revealObserver.observe(el));
} else {
	revealElements.forEach(el => el.classList.add('show'));
}

// ------------------------------------------
// Lazy image placeholders: stop the shimmer once loaded
// ------------------------------------------
document.querySelectorAll('.project-thumb img').forEach(img => {
	const done = () => img.parentElement.classList.add('loaded');
	if (img.complete) done();
	else {
		img.addEventListener('load', done, { once: true });
		img.addEventListener('error', done, { once: true });
	}
});

// ------------------------------------------
// Header: border on scroll + mobile menu
// ------------------------------------------
const header = document.querySelector('.site-header');
const navToggle = document.querySelector('.nav-toggle');
const navMenu = document.getElementById('nav-menu');

const onScroll = () => header.classList.toggle('scrolled', window.scrollY > 8);
window.addEventListener('scroll', onScroll, { passive: true });
onScroll();

function setMenu(open) {
	navMenu.classList.toggle('open', open);
	navToggle.setAttribute('aria-expanded', String(open));
	navToggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
}

navToggle.addEventListener('click', () => setMenu(!navMenu.classList.contains('open')));
navMenu.querySelectorAll('a').forEach(a => a.addEventListener('click', () => setMenu(false)));
document.addEventListener('keydown', e => { if (e.key === 'Escape') setMenu(false); });

// ------------------------------------------
// Highlight the nav link of the section in view
// ------------------------------------------
const navLinks = [...document.querySelectorAll('.nav-links a')];
const sections = navLinks
	.map(a => document.querySelector(a.getAttribute('href')))
	.filter(Boolean);

if ('IntersectionObserver' in window) {
	const sectionObserver = new IntersectionObserver(entries => {
		entries.forEach(entry => {
			if (!entry.isIntersecting) return;
			navLinks.forEach(a => a.classList.toggle('active', a.getAttribute('href') === '#' + entry.target.id));
		});
	}, { rootMargin: '-45% 0px -50% 0px' });

	sections.forEach(s => sectionObserver.observe(s));
}

// ------------------------------------------
// Resume: if resume.pdf is uploaded next to index.html, link to it.
// Otherwise the button keeps pointing to LinkedIn.
// ------------------------------------------
fetch('resume.pdf', { method: 'HEAD' })
	.then(res => {
		const type = res.headers.get('content-type') || '';
		if (!res.ok || !type.includes('pdf')) return;
		document.querySelectorAll('.js-resume').forEach(a => {
			a.href = 'resume.pdf';
			a.setAttribute('download', 'Semih_B_Ceylan_Resume.pdf');
			a.removeAttribute('target');
		});
	})
	.catch(() => {});

// ------------------------------------------
// Footer year
// ------------------------------------------
const yearEl = document.getElementById('year');
if (yearEl) yearEl.textContent = new Date().getFullYear();

// ------------------------------------------
// Contact form (sends to contact.php)
// ------------------------------------------
const form = document.getElementById('contact-form');

if (form) {
	const statusEl = form.querySelector('.form-status');
	const submitBtn = form.querySelector('button[type="submit"]');
	const btnLabel = submitBtn.querySelector('.btn-label');
	const FALLBACK_EMAIL = 'semihbceylan@gmail.com';

	form.elements.started.value = Math.floor(Date.now() / 1000);

	const setStatus = (message, type) => {
		statusEl.textContent = message;
		statusEl.className = 'form-status' + (type ? ' ' + type : '');
	};

	const validate = () => {
		let firstInvalid = null;
		['name', 'email', 'message'].forEach(name => {
			const input = form.elements[name];
			const value = input.value.trim();
			let valid = value.length > 0;
			if (name === 'email') valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
			if (name === 'message') valid = value.length >= 10;
			input.closest('.field').classList.toggle('invalid', !valid);
			if (!valid && !firstInvalid) firstInvalid = input;
		});
		if (firstInvalid) firstInvalid.focus();
		return !firstInvalid;
	};

	form.addEventListener('input', e => {
		const field = e.target.closest('.field');
		if (field) field.classList.remove('invalid');
	});

	form.addEventListener('submit', async e => {
		e.preventDefault();

		if (!validate()) {
			setStatus('Please fill in your name, a valid email and a message (at least 10 characters).', 'error');
			return;
		}

		submitBtn.disabled = true;
		btnLabel.textContent = 'Sending…';
		setStatus('', '');

		try {
			const res = await fetch(form.action, {
				method: 'POST',
				body: new FormData(form),
				headers: { 'Accept': 'application/json' }
			});
			const data = await res.json().catch(() => ({}));

			if (res.ok && data.ok) {
				form.reset();
				form.elements.started.value = Math.floor(Date.now() / 1000);
				setStatus(data.message || 'Thanks! Your message has been sent.', 'success');
			} else {
				setStatus(data.message || `Something went wrong. Please email me directly at ${FALLBACK_EMAIL}.`, 'error');
			}
		} catch (err) {
			setStatus(`Couldn't send right now. Please email me directly at ${FALLBACK_EMAIL}.`, 'error');
		} finally {
			submitBtn.disabled = false;
			btnLabel.textContent = 'Send message';
		}
	});
}
