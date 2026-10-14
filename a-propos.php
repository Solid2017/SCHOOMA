<?php
require_once __DIR__ . '/includes/config.php';

$pageTitle       = 'A propos | ' . SITE_NAME;
$pageDescription = 'Decouvrez SCHOOMA, la plateforme SaaS qui accompagne les etablissements superieurs dans la gestion de leurs services universitaires.';
$about = getSiteContent('about');

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-banner">
    <div class="container">
        <h1>A propos de <?= SITE_NAME ?></h1>
        <p class="breadcrumb"><a href="<?= SITE_URL ?>/index.php">Accueil</a> / A propos</p>
    </div>
</section>

<section>
    <div class="container">
        <div class="split">
            <div data-aos="fade-right">
                <span class="eyebrow">Notre mission</span>
                <h2><?= sanitize($about['title'] ?? 'Notre mission') ?></h2>
                <p style="color:var(--gray-700);margin:16px 0;"><?= sanitize($about['content'] ?? '') ?></p>
                <p style="color:var(--gray-700);">Nous croyons que la reussite academique passe par une gestion administrative fluide, transparente et accessible a tous : direction, enseignants, etudiants et parents.</p>
            </div>
            <div class="split-img" data-aos="fade-left">
                <img src="<?= ASSETS_URL ?>/img/about-2.jpg" alt="Equipe SCHOOMA" loading="lazy" onerror="this.src='data:image/svg+xml;charset=UTF-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22600%22 height=%22440%22%3E%3Crect width=%22600%22 height=%22440%22 fill=%22%2372a0e0%22/%3E%3C/svg%3E'">
            </div>
        </div>
    </div>
</section>

<section class="bg-light">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <span class="eyebrow">Nos valeurs</span>
            <h2>Ce qui nous guide au quotidien</h2>
        </div>
        <div class="grid grid-4">
            <div class="feature-card" data-aos="fade-up">
                <div class="feature-icon"><i class="fa-solid fa-shield-halved"></i></div>
                <h3>Securite</h3>
                <p>Protection des donnees academiques et financieres de chaque etablissement.</p>
            </div>
            <div class="feature-card" data-aos="fade-up" data-aos-delay="100">
                <div class="feature-icon"><i class="fa-solid fa-bolt"></i></div>
                <h3>Simplicite</h3>
                <p>Une interface claire, pensee pour les equipes administratives et pedagogiques.</p>
            </div>
            <div class="feature-card" data-aos="fade-up" data-aos-delay="200">
                <div class="feature-icon"><i class="fa-solid fa-people-group"></i></div>
                <h3>Proximite</h3>
                <p>Un accompagnement personnalise pour chaque etablissement partenaire.</p>
            </div>
            <div class="feature-card" data-aos="fade-up" data-aos-delay="300">
                <div class="feature-icon"><i class="fa-solid fa-chart-line"></i></div>
                <h3>Performance</h3>
                <p>Des outils de pilotage pour ameliorer la reussite academique et financiere.</p>
            </div>
        </div>
    </div>
</section>

<section>
    <div class="container">
        <div class="cta-banner" data-aos="zoom-in">
            <h2>Envie de decouvrir SCHOOMA en action ?</h2>
            <p>Contactez notre equipe pour une presentation personnalisee.</p>
            <a href="<?= SITE_URL ?>/contact.php" class="btn btn-white">Nous contacter</a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
