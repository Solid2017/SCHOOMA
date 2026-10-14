/**
 * SCHOOMA - Script principal du site public
 * Preloader, menu mobile, scroll-to-top, AOS, compteurs animes, formulaires AJAX
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        initPreloader();
        initMobileMenu();
        initScrollTop();
        initHeaderShadow();
        initAOS();
        initCounters();
        initContactForm();
        initNewsletterForm();
        initAdmissionForm();
    });

    /* ---------------- Preloader ---------------- */
    function initPreloader() {
        var preloader = document.getElementById('preloader');
        if (!preloader) return;
        window.addEventListener('load', function () {
            setTimeout(function () { preloader.classList.add('loaded'); }, 250);
        });
        // Fallback si l'evenement load ne se declenche pas
        setTimeout(function () { preloader.classList.add('loaded'); }, 3000);
    }

    /* ---------------- Menu mobile (hamburger) ---------------- */
    function initMobileMenu() {
        var toggle = document.getElementById('navToggle');
        var menu = document.getElementById('navMenu');
        var overlay = document.getElementById('navOverlay');
        if (!toggle || !menu) return;

        function closeMenu() {
            menu.classList.remove('open');
            toggle.classList.remove('active');
            toggle.setAttribute('aria-expanded', 'false');
            if (overlay) overlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        toggle.addEventListener('click', function () {
            var isOpen = menu.classList.toggle('open');
            toggle.classList.toggle('active', isOpen);
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (overlay) overlay.classList.toggle('active', isOpen);
            document.body.style.overflow = isOpen ? 'hidden' : '';
        });

        if (overlay) overlay.addEventListener('click', closeMenu);
        menu.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', closeMenu);
        });
    }

    /* ---------------- Scroll to top ---------------- */
    function initScrollTop() {
        var btn = document.getElementById('scrollTopBtn');
        if (!btn) return;
        window.addEventListener('scroll', function () {
            btn.classList.toggle('visible', window.scrollY > 400);
        });
        btn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    /* ---------------- Header shadow / shrink on scroll ---------------- */
    function initHeaderShadow() {
        var header = document.getElementById('siteHeader');
        if (!header) return;
        window.addEventListener('scroll', function () {
            header.style.boxShadow = window.scrollY > 20 ? '0 4px 18px rgba(0,0,0,0.1)' : '0 2px 12px rgba(0,0,0,0.06)';
        });
    }

    /* ---------------- AOS (scroll animations) ---------------- */
    function initAOS() {
        if (typeof AOS !== 'undefined') {
            AOS.init({ duration: 700, once: true, offset: 80, easing: 'ease-out-cubic' });
        }
    }

    /* ---------------- Compteurs animes ---------------- */
    function initCounters() {
        var counters = document.querySelectorAll('[data-count]');
        if (!counters.length) return;

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    animateCounter(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.4 });

        counters.forEach(function (el) { observer.observe(el); });
    }

    function animateCounter(el) {
        var target = parseInt(el.getAttribute('data-count'), 10) || 0;
        var duration = 1500;
        var start = 0;
        var startTime = null;

        function step(timestamp) {
            if (!startTime) startTime = timestamp;
            var progress = Math.min((timestamp - startTime) / duration, 1);
            var value = Math.floor(progress * (target - start) + start);
            el.textContent = value.toLocaleString('fr-FR');
            if (progress < 1) {
                requestAnimationFrame(step);
            } else {
                el.textContent = target.toLocaleString('fr-FR');
            }
        }
        requestAnimationFrame(step);
    }

    /* ---------------- Helpers formulaires ---------------- */
    function showFeedback(el, message, type) {
        if (!el) return;
        el.textContent = message;
        el.className = 'form-feedback ' + type;
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function postJSON(url, data) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(data)
        }).then(function (res) { return res.json(); });
    }

    /* ---------------- Formulaire de contact ---------------- */
    function initContactForm() {
        var form = document.getElementById('contactForm');
        if (!form) return;
        var feedback = document.getElementById('contactFeedback');

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var name = form.querySelector('[name="name"]').value.trim();
            var email = form.querySelector('[name="email"]').value.trim();
            var message = form.querySelector('[name="message"]').value.trim();

            if (name.length < 2 || !isValidEmail(email) || message.length < 10) {
                showFeedback(feedback, 'Veuillez remplir correctement tous les champs obligatoires.', 'error');
                return;
            }

            var submitBtn = form.querySelector('[type="submit"]');
            submitBtn.disabled = true;
            showFeedback(feedback, 'Envoi en cours...', '');

            postJSON('/api/contact.php', {
                name: name,
                email: email,
                phone: form.querySelector('[name="phone"]') ? form.querySelector('[name="phone"]').value.trim() : '',
                subject: form.querySelector('[name="subject"]') ? form.querySelector('[name="subject"]').value.trim() : '',
                message: message
            }).then(function (res) {
                showFeedback(feedback, res.message, res.success ? 'success' : 'error');
                if (res.success) form.reset();
            }).catch(function () {
                showFeedback(feedback, 'Une erreur est survenue. Veuillez reessayer.', 'error');
            }).finally(function () {
                submitBtn.disabled = false;
            });
        });
    }

    /* ---------------- Formulaire newsletter (footer) ---------------- */
    function initNewsletterForm() {
        var form = document.getElementById('newsletterForm');
        if (!form) return;
        var feedback = document.getElementById('newsletterFeedback');

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var email = document.getElementById('newsletterEmail').value.trim();
            if (!isValidEmail(email)) {
                showFeedback(feedback, 'Adresse email invalide.', 'error');
                return;
            }
            postJSON('/api/newsletter.php', { email: email }).then(function (res) {
                showFeedback(feedback, res.message, res.success ? 'success' : 'error');
                if (res.success) form.reset();
            }).catch(function () {
                showFeedback(feedback, 'Une erreur est survenue.', 'error');
            });
        });
    }

    /* ---------------- Formulaire demande d'admission ---------------- */
    function initAdmissionForm() {
        var form = document.getElementById('admissionForm');
        if (!form) return;
        var feedback = document.getElementById('admissionFeedback');

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var firstName = form.querySelector('[name="first_name"]').value.trim();
            var lastName = form.querySelector('[name="last_name"]').value.trim();
            var email = form.querySelector('[name="email"]').value.trim();
            var phone = form.querySelector('[name="phone"]').value.trim();

            if (firstName.length < 2 || lastName.length < 2 || !isValidEmail(email) || phone.length < 6) {
                showFeedback(feedback, 'Veuillez remplir correctement tous les champs obligatoires.', 'error');
                return;
            }

            var submitBtn = form.querySelector('[type="submit"]');
            submitBtn.disabled = true;
            showFeedback(feedback, 'Envoi en cours...', '');

            postJSON('/api/admissions.php', {
                first_name: firstName,
                last_name: lastName,
                email: email,
                phone: phone,
                program_wanted: form.querySelector('[name="program_wanted"]') ? form.querySelector('[name="program_wanted"]').value : '',
                message: form.querySelector('[name="message"]') ? form.querySelector('[name="message"]').value.trim() : ''
            }).then(function (res) {
                showFeedback(feedback, res.message, res.success ? 'success' : 'error');
                if (res.success) form.reset();
            }).catch(function () {
                showFeedback(feedback, 'Une erreur est survenue. Veuillez reessayer.', 'error');
            }).finally(function () {
                submitBtn.disabled = false;
            });
        });
    }
})();
