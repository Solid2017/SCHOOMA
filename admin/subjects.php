<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/lmd.php';
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE]);
$db = getDB();
$workingClientId = resolveWorkingClientId();
$pageTitle = 'Matieres (ECUE)';
$breadcrumb = 'Structure academique / Matieres';

$classesStmt = $db->prepare('SELECT c.id, c.name, c.level, ay.label AS year_label FROM classes c JOIN academic_years ay ON ay.id = c.academic_year_id
                             WHERE c.client_id = ? ORDER BY ay.is_current DESC, c.name ASC');
$classesStmt->execute([$workingClientId]);
$classesList = $classesStmt->fetchAll();
$classIds = array_map('intval', array_column($classesList, 'id'));
$classId = (int) ($_GET['class_id'] ?? $_POST['class_id'] ?? ($classIds[0] ?? 0));
if (!in_array($classId, $classIds, true)) {
    $classId = $classIds[0] ?? 0;
}
$baseUrl = 'subjects.php?client_id=' . $workingClientId . '&class_id=' . $classId;

$unitsStmt = $db->prepare('SELECT * FROM teaching_units WHERE client_id = ? AND class_id = ? ORDER BY semester, sort_order, code');
$unitsStmt->execute([$workingClientId, $classId]);
$unitsList = $unitsStmt->fetchAll();
$unitsById = array_column($unitsList, null, 'id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect($baseUrl);
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'save' && $classId > 0) {
        $id = (int) ($_POST['id'] ?? 0);
        $unitId = (int) ($_POST['teaching_unit_id'] ?? 0);
        $unitId = isset($unitsById[$unitId]) ? $unitId : null;
        $code = strtoupper(inputText($_POST['code'] ?? '', 20)) ?: null;
        $name = inputText($_POST['name'] ?? '', 150);
        $creditsRaw = trim($_POST['credits'] ?? '');
        $credits = $creditsRaw === '' ? null : max(0, (float) str_replace(',', '.', $creditsRaw));
        $coef = max(0.25, (float) str_replace(',', '.', $_POST['coefficient'] ?? '1'));

        if ($name === '') {
            setFlash('error', "L'intitule de la matiere est obligatoire.");
        } elseif ($id > 0) {
            $stmt = $db->prepare('UPDATE subjects SET teaching_unit_id=?, code=?, name=?, credits=?, coefficient=? WHERE id=? AND client_id=? AND class_id=?');
            $stmt->execute([$unitId, $code, $name, $credits, $coef, $id, $workingClientId, $classId]);
            setFlash('success', 'Matiere mise a jour.');
        } else {
            $stmt = $db->prepare('INSERT INTO subjects (client_id, class_id, teaching_unit_id, code, name, credits, coefficient) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$workingClientId, $classId, $unitId, $code, $name, $credits, $coef]);
            setFlash('success', 'Matiere creee.');
        }
    } elseif ($action === 'delete') {
        $stmt = $db->prepare('DELETE FROM subjects WHERE id = ? AND client_id = ?');
        $stmt->execute([(int) ($_POST['id'] ?? 0), $workingClientId]);
        setFlash('success', 'Matiere supprimee.');
    }
    redirect($baseUrl);
}

$stmt = $db->prepare('SELECT * FROM subjects WHERE client_id = ? AND class_id = ? ORDER BY code, name');
$stmt->execute([$workingClientId, $classId]);
$bySemester = [];
$orphans = [];
foreach ($stmt->fetchAll() as $subject) {
    $unit = $unitsById[$subject['teaching_unit_id']] ?? null;
    if ($unit) {
        $bySemester[(int) $unit['semester']][(int) $unit['id']][] = $subject;
    } else {
        $orphans[] = $subject;
    }
}

require_once __DIR__ . '/inc_header.php';
?>

<?php require __DIR__ . '/inc_client_switcher.php'; ?>

<div class="admin-content-header">
    <div><h2>Matières (ECUE)</h2><div class="admin-breadcrumb">Éléments constitutifs des unités d'enseignement. Note ECUE = CC × <?= (int) round(LMD_CC_WEIGHT * 100) ?>% + Examen × <?= (int) round((1 - LMD_CC_WEIGHT) * 100) ?>%.</div></div>
    <button class="btn btn-primary" data-modal-open="subjectModal" onclick="resetSubjectForm()" <?= $classId ? '' : 'disabled' ?>><i class="fa-solid fa-plus"></i> Nouvelle matière</button>
</div>

<div class="admin-card">
    <form class="filters-bar" method="get">
        <input type="hidden" name="client_id" value="<?= $workingClientId ?>">
        <label style="font-weight:600;font-size:0.85rem;">Filière :</label>
        <select class="form-control" name="class_id" onchange="this.form.submit()">
            <?php foreach ($classesList as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $classId ? 'selected' : '' ?>><?= e($c['name']) ?> — <?= e($c['level']) ?> (<?= e($c['year_label']) ?>)</option>
            <?php endforeach; ?>
        </select>
        <?php if ($classId): ?><a class="btn btn-outline btn-sm" href="teaching_units.php?client_id=<?= $workingClientId ?>&class_id=<?= $classId ?>"><i class="fa-solid fa-layer-group"></i> Unités d'enseignement</a><?php endif; ?>
    </form>

    <?php if (!$classesList): ?>
        <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i> Créez d'abord une filière.</div>
    <?php elseif (!$unitsList): ?>
        <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i> Créez d'abord les unités d'enseignement (UE) de cette filière : chaque matière doit appartenir à une UE pour compter dans le calcul LMD.</div>
    <?php endif; ?>

    <?php if ($orphans): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= count($orphans) ?> matière(s) sans UE : elles ne sont pas prises en compte dans les relevés ni dans les résultats LMD. Modifiez-les pour les rattacher à une UE.</div>
    <?php endif; ?>

    <?php foreach ($unitsList as $index => $unit):
        $semester = (int) $unit['semester'];
        $isFirstOfSemester = $index === 0 || (int) $unitsList[$index - 1]['semester'] !== $semester;
        $ecues = $bySemester[$semester][(int) $unit['id']] ?? []; ?>
        <?php if ($isFirstOfSemester): ?><h3 style="margin:22px 0 10px;font-family:var(--font-body);font-size:1rem;">Semestre <?= $semester ?></h3><?php endif; ?>
        <div class="table-responsive" style="margin-bottom:12px;">
            <table class="data-table">
                <thead><tr><th colspan="5" style="background:var(--primary-lighter);color:var(--primary-dark);"><?= e($unit['code']) ?> — <?= e($unit['name']) ?> · <?= lmdFormatCredits((float) $unit['credits']) ?> crédits</th></tr>
                <tr><th style="width:110px;">Code</th><th>Élément constitutif (ECUE)</th><th style="width:90px;">Crédits</th><th style="width:90px;">Coef.</th><th style="width:110px;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($ecues as $s): ?>
                    <tr>
                        <td><?= e($s['code'] ?? '-') ?></td>
                        <td><strong><?= e($s['name']) ?></strong></td>
                        <td><?= lmdFormatCredits($s['credits'] !== null ? (float) $s['credits'] : null) ?></td>
                        <td><?= lmdFormatCredits((float) $s['coefficient']) ?></td>
                        <td class="action-icons">
                            <button class="edit" title="Modifier" onclick='editSubject(<?= json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG) ?>)'><i class="fa-solid fa-pen"></i></button>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer cette matière et toutes ses notes ?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="class_id" value="<?= $classId ?>">
                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                <button type="submit" class="delete" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$ecues): ?><tr><td colspan="5" class="text-center"><span class="badge badge-warning">Aucun ECUE dans cette UE</span></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>

    <?php if ($orphans): ?>
        <h3 style="margin:22px 0 10px;font-family:var(--font-body);font-size:1rem;">Matières sans UE</h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Code</th><th>Matière</th><th>Coef.</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($orphans as $s): ?>
                    <tr>
                        <td><?= e($s['code'] ?? '-') ?></td>
                        <td><?= e($s['name']) ?></td>
                        <td><?= lmdFormatCredits((float) $s['coefficient']) ?></td>
                        <td class="action-icons"><button class="edit" title="Modifier" onclick='editSubject(<?= json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG) ?>)'><i class="fa-solid fa-pen"></i></button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="subjectModal">
    <div class="modal-box">
        <form method="post">
            <div class="modal-header"><h3 id="subjectModalTitle">Nouvelle matière</h3><button type="button" class="modal-close" data-modal-close>&times;</button></div>
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="class_id" value="<?= $classId ?>">
                <input type="hidden" name="id" id="subject_id" value="">
                <div class="form-group"><label>Unité d'enseignement *</label>
                    <select class="form-control" name="teaching_unit_id" id="subject_unit">
                        <?php foreach ($unitsList as $u): ?><option value="<?= (int) $u['id'] ?>">S<?= (int) $u['semester'] ?> · <?= e($u['code']) ?> — <?= e($u['name']) ?></option><?php endforeach; ?>
                        <option value="0">— Aucune (non comptée dans le LMD) —</option>
                    </select>
                </div>
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group"><label>Code</label><input type="text" class="form-control" name="code" id="subject_code" maxlength="20" placeholder="Ex : INF1011"></div>
                    <div class="form-group"><label>Intitulé *</label><input type="text" class="form-control" name="name" id="subject_name" maxlength="150" required></div>
                </div>
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group"><label>Crédits ECUE</label><input type="number" step="0.5" min="0" class="form-control" name="credits" id="subject_credits" value="3"></div>
                    <div class="form-group"><label>Coefficient (poids dans l'UE)</label><input type="number" step="0.25" min="0.25" class="form-control" name="coefficient" id="subject_coef" value="3"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetSubjectForm() {
    document.getElementById('subjectModalTitle').textContent = 'Nouvelle matière';
    ['subject_id', 'subject_code', 'subject_name'].forEach(function (id) { document.getElementById(id).value = ''; });
    document.getElementById('subject_credits').value = 3;
    document.getElementById('subject_coef').value = 3;
}
function editSubject(s) {
    document.getElementById('subjectModalTitle').textContent = 'Modifier la matière';
    document.getElementById('subject_id').value = s.id;
    document.getElementById('subject_unit').value = s.teaching_unit_id || 0;
    document.getElementById('subject_code').value = s.code || '';
    document.getElementById('subject_name').value = s.name;
    document.getElementById('subject_credits').value = s.credits === null ? '' : s.credits;
    document.getElementById('subject_coef').value = s.coefficient;
    document.getElementById('subjectModal').classList.add('open');
}
</script>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
