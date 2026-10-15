<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/lmd_view.php';

$pageTitle       = 'Resultats annuels | ' . SITE_NAME;
$pageDescription = 'Consultez votre releve de notes et vos resultats annuels (systeme LMD) avec votre matricule et votre date de naissance.';

$db = getDB();
$schools = $db->query("SELECT id, name, address, city, phone, email, logo FROM clients WHERE status IN ('actif','essai') ORDER BY name")->fetchAll();
$schoolsById = array_column($schools, null, 'id');
$schoolId = (int) ($_POST['school_id'] ?? CURRENT_CLIENT_ID);
if (!isset($schoolsById[$schoolId])) {
    $schoolId = CURRENT_CLIENT_ID;
}

$transcript = null;
$searched = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $searched = true;
    $matricule = inputText($_POST['matricule'] ?? '', 30);
    $dob = $_POST['dob'] ?? '';

    // Limite de tentatives par session (10 par quart d'heure)
    $attempts = array_filter($_SESSION['results_attempts'] ?? [], static fn($t) => $t > time() - 900);
    if (count($attempts) >= 10) {
        $error = 'Trop de tentatives. Veuillez reessayer dans quelques minutes.';
    } elseif ($matricule === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) || !isset($schoolsById[$schoolId])) {
        $error = 'Veuillez saisir votre etablissement, votre matricule et votre date de naissance.';
    } else {
        $attempts[] = time();
        $_SESSION['results_attempts'] = $attempts;

        $stmt = $db->prepare('SELECT r.student_id, r.class_id, r.academic_year_id FROM results r JOIN students s ON s.id = r.student_id
                              JOIN academic_years ay ON ay.id = r.academic_year_id
                              WHERE s.client_id = ? AND s.matricule = ? AND s.dob = ? AND r.published = 1
                              ORDER BY ay.start_date DESC LIMIT 1');
        $stmt->execute([$schoolId, $matricule, $dob]);
        $published = $stmt->fetch();

        if (!$published) {
            $error = 'Aucun resultat publie ne correspond a ces informations. Verifiez votre matricule et votre date de naissance, ou contactez la scolarite.';
        } else {
            $sid = (int) $published['student_id'];
            $classData = lmdComputeClass($db, $schoolId, (int) $published['class_id'], (int) $published['academic_year_id']);
            $row = $classData['rows'][$sid] ?? null;
            $reg = lmdStudentRegistration($db, $schoolId, $sid, (int) $published['academic_year_id']);
            if ($row && $reg) {
                $transcript = lmdTranscriptHtml([
                    'school' => $schoolsById[$schoolId],
                    'student' => $row['student'],
                    'registration' => $reg,
                    'result' => $row['result'],
                    'final_decision' => $row['final_decision'],
                    'mention' => $row['mention'],
                    'rank' => $row['rank'],
                    'effectif' => $classData['stats']['effectif'],
                    'president' => lmdJuryPresident($db, $schoolId, (int) $published['class_id'], (int) $published['academic_year_id']),
                    'director' => lmdSchoolOfficial($db, $schoolId, 'directeur_etudes'),
                    'validation' => lmdJuryValidation($db, $schoolId, (int) $published['class_id'], (int) $published['academic_year_id']),
                ]);
            } else {
                $error = 'Resultat indisponible pour le moment. Contactez la scolarite.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-banner">
    <div class="container">
        <h1>Résultats annuels</h1>
        <p class="breadcrumb"><a href="<?= SITE_URL ?>/index.php">Accueil</a> / Résultats</p>
    </div>
</section>

<section>
    <div class="container">
        <div class="form-card no-print" style="max-width:560px;margin:0 auto 40px;" data-aos="fade-up">
            <h3 class="text-center mb-2">Consulter mon relevé de notes</h3>
            <form method="post" novalidate>
                <?php if (count($schools) > 1): ?>
                <div class="form-group">
                    <label for="school_id">Établissement *</label>
                    <select class="form-control" id="school_id" name="school_id">
                        <?php foreach ($schools as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $schoolId ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                    <input type="hidden" name="school_id" value="<?= $schoolId ?>">
                <?php endif; ?>
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group">
                        <label for="matricule">Matricule *</label>
                        <input type="text" class="form-control" id="matricule" name="matricule" value="<?= e($_POST['matricule'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="dob">Date de naissance *</label>
                        <input type="date" class="form-control" id="dob" name="dob" value="<?= e($_POST['dob'] ?? '') ?>" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Afficher mes résultats <i class="fa-solid fa-magnifying-glass"></i></button>
            </form>
        </div>

        <?php if ($searched && $error): ?>
            <div class="alert alert-danger no-print" style="max-width:560px;margin:0 auto;"><i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?></div>
        <?php elseif ($transcript): ?>
            <div class="text-center no-print" style="margin-bottom:20px;">
                <button type="button" class="btn btn-outline" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimer / enregistrer en PDF</button>
            </div>
            <div style="overflow-x:auto;"><?= $transcript ?></div>
            <p class="text-center no-print" style="font-size:.85rem;color:var(--gray-500);">Document à titre informatif. Seul le relevé signé délivré par la scolarité fait foi.</p>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
