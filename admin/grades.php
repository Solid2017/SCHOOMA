<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/lmd.php';
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE, ROLE_ASSISTANT]);
$db = getDB();
$workingClientId = resolveWorkingClientId();
$pageTitle = 'Saisie des notes';
$breadcrumb = 'Pedagogie / Notes (CC, Examen, Rattrapage)';

$classesStmt = $db->prepare('SELECT c.id, c.name, c.level, c.academic_year_id, ay.label AS year_label FROM classes c JOIN academic_years ay ON ay.id = c.academic_year_id
                             WHERE c.client_id = ? ORDER BY ay.is_current DESC, c.name ASC');
$classesStmt->execute([$workingClientId]);
$classesList = array_column($classesStmt->fetchAll(), null, 'id');
$classId = (int) ($_GET['class_id'] ?? $_POST['class_id'] ?? (array_key_first($classesList) ?? 0));
if (!isset($classesList[$classId])) {
    $classId = (int) (array_key_first($classesList) ?? 0);
}
$class = $classesList[$classId] ?? null;

$subjectsStmt = $db->prepare('SELECT s.*, tu.semester, tu.code AS ue_code, tu.name AS ue_name FROM subjects s
                              LEFT JOIN teaching_units tu ON tu.id = s.teaching_unit_id
                              WHERE s.client_id = ? AND s.class_id = ? ORDER BY tu.semester IS NULL, tu.semester, tu.sort_order, tu.code, s.code, s.name');
$subjectsStmt->execute([$workingClientId, $classId]);
$subjectsList = array_column($subjectsStmt->fetchAll(), null, 'id');
$subjectId = (int) ($_GET['subject_id'] ?? $_POST['subject_id'] ?? 0);
if (!isset($subjectsList[$subjectId])) {
    $subjectId = (int) (array_key_first($subjectsList) ?? 0);
}
$subject = $subjectsList[$subjectId] ?? null;
$baseUrl = "grades.php?client_id=$workingClientId&class_id=$classId&subject_id=$subjectId";

$students = [];
if ($class) {
    $stmt = $db->prepare('SELECT s.id, s.matricule, s.first_name, s.last_name, s.status FROM students s JOIN registrations r ON r.student_id = s.id
                          WHERE r.client_id = ? AND r.class_id = ? AND r.academic_year_id = ? AND r.status <> "annulee" ORDER BY s.last_name, s.first_name');
    $stmt->execute([$workingClientId, $classId, $class['academic_year_id']]);
    $students = array_column($stmt->fetchAll(), null, 'id');
}

// Colonnes de saisie => type d'evaluation stocke (le CC saisi remplace les devoirs et TP)
$columns = [
    'cc' => ['label' => 'CC', 'store' => 'devoir', 'replaces' => ['devoir', 'tp']],
    'exam' => ['label' => 'Examen', 'store' => 'examen', 'replaces' => ['examen']],
    'ratt' => ['label' => 'Rattrapage', 'store' => 'rattrapage', 'replaces' => ['rattrapage']],
];
$normalize = static fn(string $v): string => trim(str_replace(',', '.', $v));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect($baseUrl);
    }
    if (($_POST['action'] ?? '') === 'save_grid' && $class && $subject) {
        $term = $subject['semester'] !== null ? lmdSemesterTerm((int) $subject['semester']) : 'semestre1';
        $evalDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['eval_date'] ?? '') ? $_POST['eval_date'] : date('Y-m-d');
        $delete = $db->prepare('DELETE FROM grades WHERE client_id = ? AND student_id = ? AND subject_id = ? AND academic_year_id = ? AND eval_type = ?');
        $insert = $db->prepare('INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (?,?,?,?,?,?,?,20,?)');
        $saved = 0;
        $errors = 0;

        $db->beginTransaction();
        foreach ($_POST['grades'] ?? [] as $studentId => $values) {
            $studentId = (int) $studentId;
            if (!isset($students[$studentId]) || !is_array($values)) {
                continue;
            }
            foreach ($columns as $key => $col) {
                $raw = $normalize((string) ($values[$key] ?? ''));
                if ($raw === $normalize((string) ($_POST['previous'][$studentId][$key] ?? ''))) {
                    continue; // valeur inchangee
                }
                if ($raw !== '' && (!is_numeric($raw) || (float) $raw < 0 || (float) $raw > 20)) {
                    $errors++;
                    continue;
                }
                foreach ($col['replaces'] as $type) {
                    $delete->execute([$workingClientId, $studentId, $subjectId, $class['academic_year_id'], $type]);
                }
                if ($raw !== '') {
                    $insert->execute([$workingClientId, $studentId, $subjectId, $class['academic_year_id'], $term, $col['store'], round((float) $raw, 2), $evalDate]);
                }
                $saved++;
            }
        }
        $db->commit();
        setFlash($errors ? 'error' : 'success', "$saved note(s) mise(s) a jour" . ($errors ? ", $errors valeur(s) invalide(s) ignoree(s) (attendu : 0 a 20)." : '.'));
    }
    redirect($baseUrl);
}

$grades = $class ? lmdLoadGrades($db, $workingClientId, (int) $class['academic_year_id'], $classId) : [];
$orphan = $subject && $subject['teaching_unit_id'] === null;

require_once __DIR__ . '/inc_header.php';
?>

<?php require __DIR__ . '/inc_client_switcher.php'; ?>

<div class="admin-content-header">
    <div><h2>Saisie des notes</h2><div class="admin-breadcrumb">Notes sur 20 · Note ECUE = CC × <?= (int) round(LMD_CC_WEIGHT * 100) ?>% + Examen × <?= (int) round((1 - LMD_CC_WEIGHT) * 100) ?>% · le rattrapage remplace l'examen s'il est plus favorable.</div></div>
    <?php if ($class): ?><a class="btn btn-outline" href="results.php?client_id=<?= $workingClientId ?>&class_id=<?= $classId ?>"><i class="fa-solid fa-award"></i> Résultats LMD</a><?php endif; ?>
</div>

<div class="admin-card">
    <form class="filters-bar" method="get">
        <input type="hidden" name="client_id" value="<?= $workingClientId ?>">
        <select class="form-control" name="class_id" onchange="this.form.subject_id.value='';this.form.submit()">
            <?php foreach ($classesList as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $classId ? 'selected' : '' ?>><?= e($c['name']) ?> (<?= e($c['year_label']) ?>)</option><?php endforeach; ?>
        </select>
        <select class="form-control" name="subject_id" onchange="this.form.submit()" style="max-width:440px;">
            <?php $currentGroup = null;
            foreach ($subjectsList as $s):
                $group = $s['semester'] !== null ? 'Semestre ' . $s['semester'] . ' · ' . $s['ue_code'] . ' — ' . $s['ue_name'] : 'Sans UE';
                if ($group !== $currentGroup):
                    echo $currentGroup !== null ? '</optgroup>' : '';
                    echo '<optgroup label="' . e($group) . '">';
                    $currentGroup = $group;
                endif; ?>
                <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $subjectId ? 'selected' : '' ?>><?= e(($s['code'] ? $s['code'] . ' — ' : '') . $s['name']) ?></option>
            <?php endforeach;
            echo $currentGroup !== null ? '</optgroup>' : ''; ?>
        </select>
    </form>

    <?php if (!$subject): ?>
        <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i> Aucune matière pour cette filière. Définissez d'abord les UE et leurs ECUE.</div>
    <?php elseif (!$students): ?>
        <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i> Aucun étudiant inscrit dans cette filière pour <?= e($class['year_label']) ?>.</div>
    <?php else: ?>
        <?php if ($orphan): ?><div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> Cette matière n'est rattachée à aucune UE : ses notes ne comptent pas dans le calcul LMD.</div><?php endif; ?>
        <p style="margin-bottom:14px;"><strong><?= e($subject['name']) ?></strong>
            <?php if (!$orphan): ?> · Semestre <?= (int) $subject['semester'] ?> · <?= e($subject['ue_code']) ?> · <?= lmdFormatCredits($subject['credits'] !== null ? (float) $subject['credits'] : null) ?> crédit(s) · coef. <?= lmdFormatCredits((float) $subject['coefficient']) ?><?php endif; ?>
        </p>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_grid">
            <input type="hidden" name="class_id" value="<?= $classId ?>">
            <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
            <div class="filters-bar">
                <label style="font-weight:600;font-size:0.85rem;" for="eval_date">Date d'évaluation</label>
                <input type="date" class="form-control" id="eval_date" name="eval_date" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Matricule</th><th>Étudiant</th><th>CC /20</th><th>Examen /20</th><th>Rattrapage /20</th><th>Note finale</th><th>Résultat</th></tr></thead>
                    <tbody>
                    <?php foreach ($students as $sid => $st):
                        $mark = lmdSubjectMark($grades[$sid][$subjectId] ?? []); ?>
                        <tr>
                            <td><?= e($st['matricule']) ?></td>
                            <td><strong><?= e(mb_strtoupper($st['last_name']) . ' ' . $st['first_name']) ?></strong><?= $st['status'] !== 'actif' ? ' <span class="badge badge-gray">' . e($st['status']) . '</span>' : '' ?></td>
                            <?php foreach ($columns as $key => $col):
                                $value = $mark[$key] === null ? '' : rtrim(rtrim(number_format($mark[$key], 2, '.', ''), '0'), '.'); ?>
                                <td>
                                    <input type="text" inputmode="decimal" class="grid-input" name="grades[<?= $sid ?>][<?= $key ?>]" value="<?= e($value) ?>" placeholder="-" aria-label="<?= e($col['label']) ?>">
                                    <input type="hidden" name="previous[<?= $sid ?>][<?= $key ?>]" value="<?= e($value) ?>">
                                </td>
                            <?php endforeach; ?>
                            <td><strong><?= $mark['final'] === null ? '-' : lmdFormat($mark['final']) . ($mark['session'] === 2 ? '*' : '') ?></strong></td>
                            <td><?php if ($mark['final'] !== null): ?><span class="badge badge-<?= $mark['final'] >= LMD_PASS_MARK ? 'success' : 'danger' ?>"><?= $mark['final'] >= LMD_PASS_MARK ? '≥ 10' : '< 10' ?></span><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p style="font-size:0.8rem;color:var(--gray-500);margin-top:10px;">* note obtenue en session de rattrapage. Vider une case efface la note correspondante.</p>
            <button type="submit" class="btn btn-primary mt-2"><i class="fa-solid fa-floppy-disk"></i> Enregistrer les notes</button>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
