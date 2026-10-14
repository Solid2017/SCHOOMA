<?php
require_once __DIR__ . '/includes/config.php';

$pageTitle       = 'Nos programmes | ' . SITE_NAME;
$pageDescription = 'Decouvrez les filieres et programmes proposes par notre etablissement, propulse par la plateforme SCHOOMA.';

$stmt = getDB()->prepare(
    'SELECT c.*, ay.label AS year_label
     FROM classes c
     JOIN academic_years ay ON ay.id = c.academic_year_id
     WHERE c.client_id = ? AND ay.is_current = 1
     ORDER BY c.name ASC'
);
$stmt->execute([CURRENT_CLIENT_ID]);
$classes = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-banner">
    <div class="container">
        <h1>Nos programmes</h1>
        <p class="breadcrumb"><a href="<?= SITE_URL ?>/index.php">Accueil</a> / Programmes</p>
    </div>
</section>

<section>
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <span class="eyebrow">Formations</span>
            <h2>Filieres disponibles pour l'annee en cours</h2>
            <p>Toutes nos formations beneficient d'un suivi academique et administratif complet via SCHOOMA.</p>
        </div>

        <?php if (empty($classes)): ?>
            <p class="text-center" style="color:var(--gray-500);">Les programmes de la nouvelle annee universitaire seront publies prochainement. Contactez-nous pour plus d'informations.</p>
        <?php else: ?>
        <div class="grid grid-3">
            <?php foreach ($classes as $class): ?>
            <div class="feature-card" data-aos="fade-up">
                <div class="feature-icon"><i class="fa-solid fa-book-open"></i></div>
                <h3><?= sanitize($class['name']) ?></h3>
                <p><?= sanitize($class['level'] ?? '') ?></p>
                <p style="margin-top:10px;font-weight:600;color:var(--primary);">Annee <?= sanitize($class['year_label']) ?></p>
                <a href="<?= SITE_URL ?>/admissions.php?programme=<?= urlencode($class['name']) ?>" class="btn btn-outline btn-sm mt-2">Postuler</a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<section class="bg-light">
    <div class="container">
        <div class="cta-banner" data-aos="zoom-in">
            <h2>Une question sur nos programmes ?</h2>
            <p>Notre equipe pedagogique est disponible pour vous conseiller.</p>
            <a href="<?= SITE_URL ?>/contact.php" class="btn btn-white">Nous ecrire</a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
