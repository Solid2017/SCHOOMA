<?php
require_once __DIR__ . '/includes/config.php';

$pageTitle       = 'Contact | ' . SITE_NAME;
$pageDescription = "Contactez l'equipe de " . SITE_NAME . ' pour toute question sur nos services et notre plateforme de gestion universitaire.';
$contactInfo = getSiteContent('contact_info');

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-banner">
    <div class="container">
        <h1>Contactez-nous</h1>
        <p class="breadcrumb"><a href="<?= SITE_URL ?>/index.php">Accueil</a> / Contact</p>
    </div>
</section>

<section>
    <div class="container">
        <div class="grid grid-3" style="margin-bottom:50px;">
            <div class="feature-card text-center" data-aos="fade-up">
                <div class="feature-icon" style="margin:0 auto 20px;"><i class="fa-solid fa-envelope"></i></div>
                <h3>Email</h3>
                <p><a href="mailto:<?= SITE_EMAIL ?>"><?= SITE_EMAIL ?></a></p>
            </div>
            <div class="feature-card text-center" data-aos="fade-up" data-aos-delay="100">
                <div class="feature-icon" style="margin:0 auto 20px;"><i class="fa-solid fa-phone"></i></div>
                <h3>Telephone</h3>
                <p><a href="tel:<?= preg_replace('/\s+/', '', SITE_PHONE) ?>"><?= SITE_PHONE ?></a></p>
            </div>
            <div class="feature-card text-center" data-aos="fade-up" data-aos-delay="200">
                <div class="feature-icon" style="margin:0 auto 20px;"><i class="fa-solid fa-location-dot"></i></div>
                <h3>Adresse</h3>
                <p><?= sanitize(SITE_ADDRESS) ?></p>
            </div>
        </div>

        <div class="split">
            <div data-aos="fade-right">
                <span class="eyebrow">Ecrivez-nous</span>
                <h2>Une question ? Un projet ?</h2>
                <p style="color:var(--gray-700);margin:16px 0;">Notre equipe vous repond sous 48h ouvrees. Vous pouvez aussi nous appeler directement pendant les heures de bureau.</p>
            </div>
            <div data-aos="fade-left">
                <div class="form-card">
                    <form id="contactForm" novalidate>
                        <div class="form-group">
                            <label for="name">Nom complet *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="grid grid-2" style="gap:16px;">
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Telephone</label>
                                <input type="tel" class="form-control" id="phone" name="phone">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="subject">Sujet</label>
                            <input type="text" class="form-control" id="subject" name="subject">
                        </div>
                        <div class="form-group">
                            <label for="message">Message *</label>
                            <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Envoyer le message <i class="fa-solid fa-paper-plane"></i></button>
                        <p class="form-feedback" id="contactFeedback" role="status"></p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
