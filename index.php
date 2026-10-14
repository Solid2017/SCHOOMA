<?php
require_once __DIR__ . '/includes/config.php';

$pageTitle       = 'SCHOOMA | Plateforme SaaS de gestion universitaire';
$pageDescription = 'SCHOOMA digitalise la gestion complete de votre etablissement superieur : inscriptions, paiements par tranches, enseignants, emplois du temps, notes et resultats annuels.';

$hero  = getSiteContent('hero');
$about = getSiteContent('about');
$posts = getPublishedPosts(3);

require_once __DIR__ . '/includes/header.php';
?>

<!-- ===== HERO ===== -->
<section class="hero">
    <div class="container">
        <div class="hero-content" data-aos="fade-up">
            <span class="hero-badge"><i class="fa-solid fa-graduation-cap"></i> SaaS dedie aux etablissements superieurs</span>
            <h1><?= sanitize($hero['title'] ?? 'Bienvenue a') ?> <span>SCHOOMA</span></h1>
            <p><?= sanitize($hero['content'] ?? SITE_TAGLINE) ?></p>
            <div class="hero-actions">
                <a href="<?= SITE_URL ?>/admissions.php" class="btn btn-accent">Demander une admission <i class="fa-solid fa-arrow-right"></i></a>
                <a href="<?= SITE_URL ?>/programmes.php" class="btn btn-outline" style="color:#fff;border-color:rgba(255,255,255,0.5)">Decouvrir nos programmes</a>
            </div>
            <div class="hero-stats">
                <div class="hero-stat"><div class="num"><span data-count="1200">0</span>+</div><div class="label">Etudiants inscrits</div></div>
                <div class="hero-stat"><div class="num"><span data-count="85">0</span>+</div><div class="label">Enseignants actifs</div></div>
                <div class="hero-stat"><div class="num"><span data-count="24">0</span></div><div class="label">Filieres</div></div>
            </div>
        </div>
    </div>
</section>

<!-- ===== SERVICES / MODULES ===== -->
<section id="services">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <span class="eyebrow">Nos services</span>
            <h2>Une gestion academique 100% digitale</h2>
            <p>SCHOOMA centralise l'ensemble des services universitaires de votre etablissement en une seule plateforme simple et securisee.</p>
        </div>
        <div class="grid grid-3">
            <div class="feature-card" data-aos="fade-up">
                <div class="feature-icon"><i class="fa-solid fa-user-plus"></i></div>
                <h3>Inscriptions des etudiants</h3>
                <p>Gerez les inscriptions par annee universitaire, filiere et niveau, avec dossiers numeriques complets.</p>
            </div>
            <div class="feature-card" data-aos="fade-up" data-aos-delay="100">
                <div class="feature-icon"><i class="fa-solid fa-money-check-dollar"></i></div>
                <h3>Paiements par tranches</h3>
                <p>Suivez en temps reel les tranches de scolarite payees, dues et en retard pour chaque etudiant.</p>
            </div>
            <div class="feature-card" data-aos="fade-up" data-aos-delay="200">
                <div class="feature-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
                <h3>Enseignants & paiements</h3>
                <p>Centralisez les affectations des enseignants, leurs heures de cours et le suivi de leur remuneration.</p>
            </div>
            <div class="feature-card" data-aos="fade-up">
                <div class="feature-icon"><i class="fa-solid fa-calendar-days"></i></div>
                <h3>Emplois du temps</h3>
                <p>Construisez et diffusez les emplois du temps hebdomadaires par filiere, salle et enseignant.</p>
            </div>
            <div class="feature-card" data-aos="fade-up" data-aos-delay="100">
                <div class="feature-icon"><i class="fa-solid fa-marker"></i></div>
                <h3>Gestion des notes</h3>
                <p>Saisissez les notes par matiere, semestre et type d'evaluation avec calcul automatique des moyennes.</p>
            </div>
            <div class="feature-card" data-aos="fade-up" data-aos-delay="200">
                <div class="feature-icon"><i class="fa-solid fa-award"></i></div>
                <h3>Resultats annuels</h3>
                <p>Publiez les resultats annuels, classements et decisions de jury en toute transparence.</p>
            </div>
        </div>
    </div>
</section>

<!-- ===== A PROPOS ===== -->
<section class="bg-light">
    <div class="container">
        <div class="split">
            <div class="split-img" data-aos="fade-right">
                <img src="<?= ASSETS_URL ?>/img/about.jpg" alt="Campus SCHOOMA" loading="lazy" onerror="this.src='data:image/svg+xml;charset=UTF-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22600%22 height=%22440%22%3E%3Crect width=%22600%22 height=%22440%22 fill=%22%230b3d91%22/%3E%3C/svg%3E'">
            </div>
            <div data-aos="fade-left">
                <span class="eyebrow">A propos</span>
                <h2><?= sanitize($about['title'] ?? 'Pourquoi choisir SCHOOMA ?') ?></h2>
                <p style="color:var(--gray-700);margin:16px 0 24px;"><?= sanitize($about['content'] ?? '') ?></p>
                <ul class="about-list">
                    <li><i class="fa-solid fa-circle-check"></i> Plateforme securisee et conforme aux exigences academiques</li>
                    <li><i class="fa-solid fa-circle-check"></i> Tableau de bord admin pour un pilotage global en temps reel</li>
                    <li><i class="fa-solid fa-circle-check"></i> Suivi financier precis des paiements et de la facturation</li>
                    <li><i class="fa-solid fa-circle-check"></i> Accessible sur tous les appareils, sans installation</li>
                </ul>
                <a href="<?= SITE_URL ?>/a-propos.php" class="btn btn-primary mt-2">En savoir plus</a>
            </div>
        </div>
    </div>
</section>

<!-- ===== COUNTERS ===== -->
<section class="counters">
    <div class="container">
        <div class="grid grid-4">
            <div class="counter-item"><div class="count"><span data-count="1200">0</span>+</div><p>Etudiants</p></div>
            <div class="counter-item"><div class="count"><span data-count="85">0</span>+</div><p>Enseignants</p></div>
            <div class="counter-item"><div class="count"><span data-count="24">0</span></div><p>Filieres</p></div>
            <div class="counter-item"><div class="count"><span data-count="98">0</span>%</div><p>Satisfaction</p></div>
        </div>
    </div>
</section>

<!-- ===== ACTUALITES ===== -->
<?php if (!empty($posts)): ?>
<section id="actualites">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <span class="eyebrow">Blog</span>
            <h2>Dernieres actualites</h2>
        </div>
        <div class="grid grid-3">
            <?php foreach ($posts as $post): ?>
            <article class="blog-card" data-aos="fade-up">
                <a href="<?= SITE_URL ?>/blog-article.php?slug=<?= urlencode($post['slug']) ?>">
                    <img src="<?= $post['image'] ? ASSETS_URL . '/uploads/' . sanitize($post['image']) : ASSETS_URL . '/img/blog-placeholder.jpg' ?>" alt="<?= sanitize($post['title']) ?>" loading="lazy" onerror="this.src='data:image/svg+xml;charset=UTF-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22200%22%3E%3Crect width=%22400%22 height=%22200%22 fill=%22%231c5fd6%22/%3E%3C/svg%3E'">
                </a>
                <div class="blog-card-body">
                    <div class="blog-meta"><i class="fa-regular fa-calendar"></i> <?= formatDate($post['published_at'] ?? $post['created_at']) ?></div>
                    <h3><a href="<?= SITE_URL ?>/blog-article.php?slug=<?= urlencode($post['slug']) ?>"><?= sanitize($post['title']) ?></a></h3>
                    <p><?= sanitize($post['excerpt'] ?? '') ?></p>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ===== CTA ===== -->
<section>
    <div class="container">
        <div class="cta-banner" data-aos="zoom-in">
            <h2>Pret a digitaliser la gestion de votre etablissement ?</h2>
            <p>Rejoignez les etablissements qui font confiance a SCHOOMA pour piloter leurs services universitaires.</p>
            <a href="<?= SITE_URL ?>/contact.php" class="btn btn-white">Contactez-nous</a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
