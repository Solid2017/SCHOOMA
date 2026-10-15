<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/lmd.php';
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE]);
$db = getDB();
$workingClientId = resolveWorkingClientId();
$pageTitle = "Unités d'enseignement (UE)";
$breadcrumb = 'Structure academique / UE';

$classesStmt = $db->prepare('SELECT c.id, c.name, c.level, ay.label AS year_label FROM classes c JOIN academic_years ay ON ay.id = c.academic_year_id
                             WHERE c.client_id = ? ORDER BY ay.is_current DESC, c.name ASC');
$classesStmt->execute([$workingClientId]);
$classesList = $classesStmt->fetchAll();
$classIds = array_map('intval', array_column($classesList, 'id'));
$classId = (int) ($_GET['class_id'] ?? $_POST['class_id'] ?? ($classIds[0] ?? 0));
if (!in_array($classId, $classIds, true)) {
    $classId = $classIds[0] ?? 0;
}
$baseUrl = 'teaching_units.php?client_id=' . $workingClientId . '&class_id=' . $classId;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect($baseUrl);
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'save' && $classId > 0) {
        $id = (int) ($_POST['id'] ?? 0);
        $semester = max(1, min(10, (int) ($_POST['semester'] ?? 1)));
        $code = strtoupper(inputText($_POST['code'] ?? '', 20));
        $name = inputText($_POST['name'] ?? '', 150);
        $category = array_key_exists($_POST['category'] ?? '', LMD_UE_CATEGORIES) ? $_POST['category'] : 'fondamentale';
        $credits = max(0, min(30, (float) str_replace(',', '.', $_POST['credits'] ?? '0')));
        $sortOrder = max(0, (int) ($_POST['sort_order'] ?? 0));

        if ($code === '' || $name === '' || $credits <= 0) {
            setFlash('error', 'Code, intitule et credits (> 0) sont obligatoires.');
        } elseif ($id > 0) {
            $stmt = $db->prepare('UPDATE teaching_units SET semester=?, code=?, name=?, category=?, credits=?, sort_order=? WHERE id=? AND client_id=? AND class_id=?');
            $stmt->execute([$semester, $code, $name, $category, $credits, $sortOrder, $id, $workingClientId, $classId]);
            setFlash('success', 'UE mise a jour.');
        } else {
            $stmt = $db->prepare('INSERT INTO teaching_units (client_id, class_id, semester, code, name, category, credits, sort_order) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->execute([$workingClientId, $classId, $semester, $code, $name, $category, $credits, $sortOrder]);
            setFlash('success', 'UE creee. Ajoutez maintenant ses elements constitutifs (ECUE) dans Matieres.');
        }
    } elseif ($action === 'delete') {
        $stmt = $db->prepare('DELETE FROM teaching_units WHERE id = ? AND client_id = ?');
        $stmt->execute([(int) ($_POST['id'] ?? 0), $workingClientId]);
        setFlash('success', 'UE supprimee (ses matieres sont conservees, sans UE).');
    }
    redirect($baseUrl);
}

$units = [];
if ($classId) {
    $stmt = $db->prepare('SELECT tu.*, (SELECT COUNT(*) FROM subjects s WHERE s.teaching_unit_id = tu.id) AS ecue_count
                          FROM teaching_units tu WHERE tu.client_id = ? AND tu.class_id = ? ORDER BY tu.semester, tu.sort_order, tu.code');
    $stmt->execute([$workingClientId, $classId]);
    foreach ($stmt->fetchAll() as $ue) {
        $units[(int) $ue['semester']][] = $ue;
    }
}
$nextSemester = $units ? max(array_keys($units)) : 1;

require_once __DIR__ . '/inc_header.php';
?>

<?php require __DIR__ . '/inc_client_switcher.php'; ?>

<div class="admin-content-header">
    <div><h2>Unités d'enseignement (UE)</h2><div class="admin-breadcrumb">Système LMD : 30 crédits par semestre, 60 crédits par année.</div></div>
    <button class="btn btn-primary" data-modal-open="ueModal" onclick="resetUeForm()" <?= $classId ? '' : 'disabled' ?>><i class="fa-solid fa-plus"></i> Nouvelle UE</button>
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
        <?php if ($classId): ?><a class="btn btn-outline btn-sm" href="subjects.php?client_id=<?= $workingClientId ?>&class_id=<?= $classId ?>"><i class="fa-solid fa-book"></i> Matières (ECUE)</a><?php endif; ?>
    </form>

    <?php if (!$classesList): ?>
        <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i> Créez d'abord une filière.</div>
    <?php elseif (!$units): ?>
        <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i> Aucune UE pour cette filière. Créez les UE du semestre 1 puis du semestre 2 (30 crédits chacun).</div>
    <?php endif; ?>

    <?php foreach ($units as $semester => $semUnits):
        $total = array_sum(array_map(fn($u) => (float) $u['credits'], $semUnits)); ?>
        <h3 style="margin:22px 0 10px;font-family:var(--font-body);font-size:1rem;">
            Semestre <?= (int) $semester ?>
            <span class="credit-check <?= abs($total - 30) < 0.01 ? 'ok' : 'ko' ?>">— <?= lmdFormatCredits($total) ?> / 30 crédits <?= abs($total - 30) < 0.01 ? '✓' : '(à ajuster)' ?></span>
        </h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Ordre</th><th>Code</th><th>Unité d'enseignement</th><th>Catégorie</th><th>Crédits</th><th>ECUE</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($semUnits as $ue): ?>
                    <tr>
                        <td><?= (int) $ue['sort_order'] ?></td>
                        <td><strong><?= e($ue['code']) ?></strong></td>
                        <td><?= e($ue['name']) ?></td>
                        <td><span class="badge badge-info"><?= e(LMD_UE_CATEGORIES[$ue['category']] ?? $ue['category']) ?></span></td>
                        <td><?= lmdFormatCredits((float) $ue['credits']) ?></td>
                        <td><?= (int) $ue['ecue_count'] ?: '<span class="badge badge-warning">0</span>' ?></td>
                        <td class="action-icons">
                            <button class="edit" title="Modifier" onclick='editUe(<?= json_encode($ue, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG) ?>)'><i class="fa-solid fa-pen"></i></button>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer cette UE ? Ses matières seront conservées mais ne compteront plus dans le calcul LMD.');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="class_id" value="<?= $classId ?>">
                                <input type="hidden" name="id" value="<?= (int) $ue['id'] ?>">
                                <button type="submit" class="delete" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
</div>

<div class="modal-overlay" id="ueModal">
    <div class="modal-box">
        <form method="post">
            <div class="modal-header"><h3 id="ueModalTitle">Nouvelle UE</h3><button type="button" class="modal-close" data-modal-close>&times;</button></div>
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="class_id" value="<?= $classId ?>">
                <input type="hidden" name="id" id="ue_id" value="">
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group"><label>Semestre *</label><input type="number" min="1" max="10" class="form-control" name="semester" id="ue_semester" value="<?= $nextSemester ?>" required></div>
                    <div class="form-group"><label>Code *</label><input type="text" class="form-control" name="code" id="ue_code" maxlength="20" placeholder="Ex : INF101" required></div>
                </div>
                <div class="form-group"><label>Intitulé *</label><input type="text" class="form-control" name="name" id="ue_name" maxlength="150" required></div>
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group"><label>Catégorie</label>
                        <select class="form-control" name="category" id="ue_category">
                            <?php foreach (LMD_UE_CATEGORIES as $key => $label): ?><option value="<?= $key ?>"><?= e($label) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Crédits *</label><input type="number" step="0.5" min="0.5" max="30" class="form-control" name="credits" id="ue_credits" value="6" required></div>
                </div>
                <div class="form-group"><label>Ordre d'affichage</label><input type="number" min="0" class="form-control" name="sort_order" id="ue_sort" value="0"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetUeForm() {
    document.getElementById('ueModalTitle').textContent = 'Nouvelle UE';
    ['ue_id', 'ue_code', 'ue_name'].forEach(function (id) { document.getElementById(id).value = ''; });
    document.getElementById('ue_credits').value = 6;
    document.getElementById('ue_sort').value = 0;
    document.getElementById('ue_category').value = 'fondamentale';
}
function editUe(ue) {
    document.getElementById('ueModalTitle').textContent = "Modifier l'UE";
    document.getElementById('ue_id').value = ue.id;
    document.getElementById('ue_semester').value = ue.semester;
    document.getElementById('ue_code').value = ue.code;
    document.getElementById('ue_name').value = ue.name;
    document.getElementById('ue_category').value = ue.category;
    document.getElementById('ue_credits').value = ue.credits;
    document.getElementById('ue_sort').value = ue.sort_order;
    document.getElementById('ueModal').classList.add('open');
}
</script>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
