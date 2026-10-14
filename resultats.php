<?php
require_once __DIR__ . '/includes/config.php';

$pageTitle       = 'Resultats annuels | ' . SITE_NAME;
$pageDescription = 'Consultez vos resultats annuels en ligne grace a votre matricule etudiant.';

$result = null;
$searched = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $searched = true;
    $matricule = sanitize($_POST['matricule'] ?? '');

    if ($matricule === '') {
        $error = 'Veuillez saisir votre matricule.';
    } else {
        $stmt = getDB()->prepare(
            'SELECT r.*, s.first_name, s.last_name, s.matricule, c.name AS class_name, ay.label AS year_label
             FROM results r
             JOIN students s ON s.id = r.student_id
             JOIN classes c ON c.id = r.class_id
             JOIN academic_years ay ON ay.id = r.academic_year_id
             WHERE s.matricule = ? AND s.client_id = ? AND r.published = 1
             ORDER BY ay.start_date DESC LIMIT 1'
        );
        $stmt->execute([$matricule, CURRENT_CLIENT_ID]);
        $result = $stmt->fetch();
        if (!$result) {
            $error = 'Aucun resultat publie trouve pour ce matricule.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-banner">
    <div class="container">
        <h1>Resultats annuels</h1>
        <p class="breadcrumb"><a href="<?= SITE_URL ?>/index.php">Accueil</a> / Resultats</p>
    </div>
</section>

<section>
    <div class="container">
        <div class="form-card" style="max-width:520px;margin:0 auto 40px;" data-aos="fade-up">
            <h3 class="text-center mb-2">Consulter mes resultats</h3>
            <form method="post" novalidate>
                <div class="form-group">
                    <label for="matricule">Matricule etudiant *</label>
                    <input type="text" class="form-control" id="matricule" name="matricule" value="<?= sanitize($_POST['matricule'] ?? '') ?>" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Rechercher <i class="fa-solid fa-magnifying-glass"></i></button>
            </form>
        </div>

        <?php if ($searched): ?>
            <?php if ($error): ?>
                <div class="alert alert-danger" style="max-width:520px;margin:0 auto;"><i class="fa-solid fa-circle-exclamation"></i> <?= sanitize($error) ?></div>
            <?php elseif ($result): ?>
                <div class="admin-card" style="max-width:640px;margin:0 auto;" data-aos="fade-up">
                    <h3 style="margin-bottom:20px;"><?= sanitize($result['first_name'] . ' ' . $result['last_name']) ?> - <?= sanitize($result['matricule']) ?></h3>
                    <table class="data-table" style="width:100%;">
                        <tr><td><strong>Filiere</strong></td><td><?= sanitize($result['class_name']) ?></td></tr>
                        <tr><td><strong>Annee universitaire</strong></td><td><?= sanitize($result['year_label']) ?></td></tr>
                        <tr><td><strong>Moyenne generale</strong></td><td><?= number_format((float) $result['average'], 2) ?>/20</td></tr>
                        <tr><td><strong>Rang</strong></td><td><?= $result['class_rank'] ? sanitize((string) $result['class_rank']) : '-' ?></td></tr>
                        <tr><td><strong>Mention</strong></td><td><?= sanitize($result['mention'] ?? '-') ?></td></tr>
                        <tr><td><strong>Decision</strong></td><td>
                            <span class="badge badge-<?= $result['decision'] === 'admis' ? 'success' : ($result['decision'] === 'redouble' ? 'danger' : 'warning') ?>">
                                <?= sanitize(ucfirst($result['decision'])) ?>
                            </span>
                        </td></tr>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
