<?php
require_once __DIR__ . '/auth.php';
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE, ROLE_ASSISTANT]);
$db = getDB();
$workingClientId = resolveWorkingClientId();
$pageTitle = 'Gestion des notes';
$breadcrumb = 'Pedagogie / Notes';

$classesStmt = $db->prepare('SELECT c.id, c.name, c.academic_year_id, ay.label AS year_label FROM classes c JOIN academic_years ay ON ay.id = c.academic_year_id WHERE c.client_id = ? ORDER BY ay.is_current DESC, c.name ASC');
$classesStmt->execute([$workingClientId]);
$classesList = $classesStmt->fetchAll();

$classId = (int) ($_GET['class_id'] ?? ($classesList[0]['id'] ?? 0));
$subjectId = (int) ($_GET['subject_id'] ?? 0);
$term = in_array($_GET['term'] ?? '', ['semestre1', 'semestre2'], true) ? $_GET['term'] : 'semestre1';
$evalType = in_array($_GET['eval_type'] ?? '', ['devoir', 'examen', 'tp', 'rattrapage'], true) ? $_GET['eval_type'] : 'devoir';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect("grades.php?client_id=$workingClientId&class_id=$classId&subject_id=$subjectId&term=$term&eval_type=$evalType");
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'bulk_save') {
        $postClassId = (int) ($_POST['class_id'] ?? 0);
        $postSubjectId = (int) ($_POST['subject_id'] ?? 0);
        $postTerm = $_POST['term'] ?? 'semestre1';
        $postEvalType = $_POST['eval_type'] ?? 'devoir';
        $maxValue = (float) ($_POST['max_value'] ?? 20);
        $evalDate = $_POST['eval_date'] ?: date('Y-m-d');
        $classInfo = null;
        foreach ($classesList as $c) { if ((int) $c['id'] === $postClassId) { $classInfo = $c; break; } }

        if (!$classInfo || $postSubjectId <= 0) {
            setFlash('error', 'Filiere et matiere obligatoires.');
        } else {
            $count = 0;
            $stmt = $db->prepare('INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (?,?,?,?,?,?,?,?,?)');
            foreach (($_POST['grades'] ?? []) as $studentId => $value) {
                $value = trim((string) $value);
                if ($value === '' || !is_numeric($value)) continue;
                $stmt->execute([$workingClientId, (int) $studentId, $postSubjectId, $classInfo['academic_year_id'], $postTerm, $postEvalType, (float) $value, $maxValue, $evalDate]);
                $count++;
            }
            setFlash('success', "$count note(s) enregistree(s) avec succes.");
        }
        $classId = $postClassId; $subjectId = $postSubjectId; $term = $postTerm; $evalType = $postEvalType;
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM grades WHERE id = ? AND client_id = ?');
        $stmt->execute([$id, $workingClientId]);
        setFlash('success', 'Note supprimee.');
    }
    redirect("grades.php?client_id=$workingClientId&class_id=$classId&subject_id=$subjectId&term=$term&eval_type=$evalType");
}

$subjectsStmt = $db->prepare('SELECT id, name FROM subjects WHERE client_id = ? AND class_id = ? ORDER BY name ASC');
$subjectsStmt->execute([$workingClientId, $classId]);
$subjectsList = $subjectsStmt->fetchAll();
if (!$subjectId && !empty($subjectsList)) {
    $subjectId = (int) $subjectsList[0]['id'];
}

$studentsStmt = $db->prepare(
    'SELECT s.id, s.matricule, s.first_name, s.last_name
     FROM students s JOIN registrations r ON r.student_id = s.id
     WHERE r.client_id = ? AND r.class_id = ? AND s.status = "actif" ORDER BY s.last_name ASC'
);
$studentsStmt->execute([$workingClientId, $classId]);
$studentsList = $studentsStmt->fetchAll();

$existingGrades = [];
if ($subjectId) {
    $gStmt = $db->prepare(
        'SELECT g.*, s.first_name, s.last_name, s.matricule FROM grades g JOIN students s ON s.id = g.student_id
         WHERE g.client_id = ? AND g.subject_id = ? AND g.term = ? AND g.eval_type = ?
         ORDER BY g.eval_date DESC, s.last_name ASC'
    );
    $gStmt->execute([$workingClientId, $subjectId, $term, $evalType]);
    $existingGrades = $gStmt->fetchAll();
}

require_once __DIR__ . '/inc_header.php';
?>

<?php require __DIR__ . '/inc_client_switcher.php'; ?>

<div class="admin-content-header"><div><h2>Gestion des notes</h2></div></div>

<div class="admin-card">
    <form class="filters-bar" method="get">
        <input type="hidden" name="client_id" value="<?= $workingClientId ?>">
        <select class="form-control" name="class_id" onchange="this.form.submit()">
            <?php foreach ($classesList as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $classId ? 'selected' : '' ?>><?= sanitize($c['name']) ?> (<?= sanitize($c['year_label']) ?>)</option><?php endforeach; ?>
        </select>
        <select class="form-control" name="subject_id" onchange="this.form.submit()">
            <?php foreach ($subjectsList as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $subjectId ? 'selected' : '' ?>><?= sanitize($s['name']) ?></option><?php endforeach; ?>
        </select>
        <select class="form-control" name="term" onchange="this.form.submit()">
            <option value="semestre1" <?= $term === 'semestre1' ? 'selected' : '' ?>>Semestre 1</option>
            <option value="semestre2" <?= $term === 'semestre2' ? 'selected' : '' ?>>Semestre 2</option>
        </select>
        <select class="form-control" name="eval_type" onchange="this.form.submit()">
            <option value="devoir" <?= $evalType === 'devoir' ? 'selected' : '' ?>>Devoir</option>
            <option value="tp" <?= $evalType === 'tp' ? 'selected' : '' ?>>TP</option>
            <option value="examen" <?= $evalType === 'examen' ? 'selected' : '' ?>>Examen</option>
            <option value="rattrapage" <?= $evalType === 'rattrapage' ? 'selected' : '' ?>>Rattrapage</option>
        </select>
    </form>

    <?php if (empty($subjectsList)): ?>
        <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i> Aucune matiere pour cette filiere. Ajoutez des matieres avant de saisir des notes.</div>
    <?php elseif (empty($studentsList)): ?>
        <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i> Aucun etudiant actif inscrit dans cette filiere.</div>
    <?php else: ?>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="bulk_save">
        <input type="hidden" name="class_id" value="<?= $classId ?>">
        <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
        <input type="hidden" name="term" value="<?= $term ?>">
        <input type="hidden" name="eval_type" value="<?= $evalType ?>">
        <div class="grid grid-2" style="gap:16px;max-width:420px;margin-bottom:16px;">
            <div class="form-group"><label>Date de l'evaluation</label><input type="date" class="form-control" name="eval_date" value="<?= date('Y-m-d') ?>"></div>
            <div class="form-group"><label>Note sur</label><input type="number" step="0.5" class="form-control" name="max_value" value="20"></div>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Matricule</th><th>Etudiant</th><th style="width:140px;">Note</th></tr></thead>
                <tbody>
                <?php foreach ($studentsList as $s): ?>
                    <tr>
                        <td><?= sanitize($s['matricule']) ?></td>
                        <td><?= sanitize($s['first_name'] . ' ' . $s['last_name']) ?></td>
                        <td><input type="number" step="0.25" min="0" class="form-control" name="grades[<?= (int) $s['id'] ?>]" placeholder="-"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="submit" class="btn btn-primary mt-2"><i class="fa-solid fa-floppy-disk"></i> Enregistrer les notes</button>
    </form>
    <?php endif; ?>
</div>

<?php if (!empty($existingGrades)): ?>
<div class="admin-card">
    <div class="admin-card-header"><h3>Notes deja saisies (<?= sanitize($term) ?> - <?= sanitize($evalType) ?>)</h3></div>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Etudiant</th><th>Note</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($existingGrades as $g): ?>
                <tr>
                    <td><?= sanitize($g['first_name'] . ' ' . $g['last_name']) ?> (<?= sanitize($g['matricule']) ?>)</td>
                    <td><?= sanitize((string) $g['grade_value']) ?>/<?= sanitize((string) $g['max_value']) ?></td>
                    <td><?= formatDate($g['eval_date']) ?></td>
                    <td class="action-icons">
                        <form method="post" onsubmit="return confirm('Supprimer cette note ?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                            <button type="submit" class="delete" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
