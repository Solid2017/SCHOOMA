</main>

<!-- ===== FOOTER ===== -->
<footer class="site-footer">
    <div class="footer-top container">
        <div class="footer-col footer-about" data-aos="fade-up">
            <a href="<?= SITE_URL ?>/index.php" class="navbar-brand footer-brand">
                <img src="<?= ASSETS_URL ?>/img/logo-white.svg" alt="<?= SITE_NAME ?>" class="navbar-logo">
            </a>
            <p><?= SITE_TAGLINE ?>. Digitalisez la gestion de votre etablissement : inscriptions, paiements, enseignants, notes et resultats en un seul outil.</p>
            <div class="social-links">
                <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
                <a href="#" aria-label="Twitter / X"><i class="fa-brands fa-x-twitter"></i></a>
                <a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
            </div>
        </div>

        <div class="footer-col" data-aos="fade-up" data-aos-delay="100">
            <h4>Navigation</h4>
            <ul>
                <li><a href="<?= SITE_URL ?>/index.php">Accueil</a></li>
                <li><a href="<?= SITE_URL ?>/a-propos.php">A propos</a></li>
                <li><a href="<?= SITE_URL ?>/programmes.php">Programmes</a></li>
                <li><a href="<?= SITE_URL ?>/blog.php">Actualites</a></li>
                <li><a href="<?= SITE_URL ?>/contact.php">Contact</a></li>
            </ul>
        </div>

        <div class="footer-col" data-aos="fade-up" data-aos-delay="200">
            <h4>Services</h4>
            <ul>
                <li><a href="<?= SITE_URL ?>/admissions.php">Demande d'admission</a></li>
                <li><a href="<?= ADMIN_URL ?>/login.php">Espace administration</a></li>
                <li><a href="<?= SITE_URL ?>/programmes.php">Nos formations</a></li>
            </ul>
        </div>

        <div class="footer-col footer-newsletter" data-aos="fade-up" data-aos-delay="300">
            <h4>Newsletter</h4>
            <p>Recevez nos actualites et informations importantes.</p>
            <form id="newsletterForm" class="newsletter-form" novalidate>
                <input type="email" name="email" id="newsletterEmail" placeholder="Votre adresse email" required>
                <button type="submit" aria-label="S'abonner"><i class="fa-solid fa-paper-plane"></i></button>
            </form>
            <p class="form-feedback" id="newsletterFeedback" role="status"></p>
        </div>
    </div>

    <div class="footer-bottom">
        <div class="container footer-bottom-inner">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. Tous droits reserves.</p>
            <p><?= SITE_DOMAIN ?></p>
        </div>
    </div>
</footer>

<button id="scrollTopBtn" aria-label="Retour en haut">
    <i class="fa-solid fa-arrow-up"></i>
</button>

<!-- Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.1/aos.js"></script>
<script src="<?= ASSETS_URL ?>/js/main.js"></script>
</body>
</html>
