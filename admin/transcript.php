<?php
/**
 * Releves de notes LMD imprimables : un etudiant (?student_id=) ou toute une filiere (?class_id=).
 * ?semester= vide pour le releve annuel, ou numero de semestre pour un releve semestriel.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/lmd_view.php';
requireRole(ROLES_SCHOOL_HEADS);
$db = getDB();
$workingClientId = resolveWorkingClientId();

$stmt = $db->prepare('SELECT * FROM clients WHERE id = ?');
$stmt->execute([$workingClientId]);
$school = $stmt->fetch();

$studentId = (int) ($_GET['student_id'] ?? 0);
$classId = (int) ($_GET['class_id'] ?? 0);
$semester = (int) ($_GET['semester'] ?? 0);

$targets = []; // [student_id => registration]
if ($studentId > 0) {
    $reg = lmdStudentRegistration($db, $workingClientId, $studentId, isset($_GET['academic_year_id']) ? (int) $_GET['academic_year_id'] : null);
    if ($reg) {
        $classId = (int) $reg['class_id'];
        $targets[$studentId] = $reg;
    }
} elseif ($classId > 0) {
    $stmt = $db->prepare('SELECT c.id AS class_id, c.academic_year_id, c.name AS class_name, c.level AS class_level, ay.label AS year_label
                          FROM classes c JOIN academic_years ay ON ay.id = c.academic_year_id WHERE c.id = ? AND c.client_id = ?');
    $stmt->execute([$classId, $workingClientId]);
    $classReg = $stmt->fetch();
}

$docs = [];
$classData = null;
if ($targets || !empty($classReg)) {
    $academicYearId = (int) ($targets ? reset($targets)['academic_year_id'] : $classReg['academic_year_id']);
    $classData = lmdComputeClass($db, $workingClientId, $classId, $academicYearId);
    $president = lmdJuryPresident($db, $workingClientId, $classId, $academicYearId);
    $director = lmdSchoolOfficial($db, $workingClientId, ROLE_DIRECTEUR_ETUDES);
    $validation = lmdJuryValidation($db, $workingClientId, $classId, $academicYearId);
    $rows = $targets ? array_intersect_key($classData['rows'], $targets) : $classData['rows'];
    foreach ($rows as $sid => $row) {
        $docs[] = lmdTranscriptHtml([
            'school' => $school,
            'student' => $row['student'],
            'registration' => $targets[$sid] ?? $classReg,
            'result' => $row['result'],
            'final_decision' => $row['final_decision'],
            'mention' => $row['mention'],
            'rank' => $row['rank'],
            'effectif' => $classData['stats']['effectif'],
            'semesters' => $semester > 0 ? [$semester] : null,
            'president' => $president,
            'director' => $director,
            'validation' => $validation,
        ]);
    }
}
$semesterNumbers = $classData ? array_keys($classData['curriculum']) : [];
$backUrl = ADMIN_URL . '/results.php?client_id=' . $workingClientId . '&class_id=' . $classId;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Relevés de notes | <?= e($school['name'] ?? SITE_NAME) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/svg+xml" href="<?= ASSETS_URL ?>/img/logo-mark.svg">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
</head>
<body class="print-page">
<div class="print-toolbar">
    <a href="<?= e($backUrl) ?>" class="btn btn-outline btn-sm" style="color:#fff;border-color:rgba(255,255,255,.5)">&larr; Retour aux résultats</a>
    <form method="get">
        <input type="hidden" name="client_id" value="<?= $workingClientId ?>">
        <?php if ($studentId): ?><input type="hidden" name="student_id" value="<?= $studentId ?>"><?php else: ?><input type="hidden" name="class_id" value="<?= $classId ?>"><?php endif; ?>
        <select class="form-control" name="semester" onchange="this.form.submit()">
            <option value="">Relevé annuel</option>
            <?php foreach ($semesterNumbers as $n): ?><option value="<?= (int) $n ?>" <?= $semester === (int) $n ? 'selected' : '' ?>>Relevé du semestre <?= (int) $n ?></option><?php endforeach; ?>
        </select>
        <span style="font-size:.85rem;"><?= count($docs) ?> relevé(s)</span>
        <button type="button" class="btn btn-accent btn-sm" onclick="window.print()">Imprimer</button>
    </form>
</div>
<?php if (!$docs): ?>
    <div class="lmd-doc"><p class="lmd-empty">Aucun relevé à imprimer : étudiant ou filière introuvable, ou aucun étudiant inscrit.</p></div>
<?php else: ?>
    <?= implode("\n", $docs) ?>
<?php endif; ?>
</body>
</html>
