<?php
require_once __DIR__ . '/auth.php';
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE]);
$db = getDB();
$workingClientId = resolveWorkingClientId();
$pageTitle = 'Matieres';
$breadcrumb = 'Structure academique / Matieres';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect('subjects.php?client_id=' . $workingClientId);
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $classId = (int) ($_POST['class_id'] ?? 0);
        $coef = (float) ($_POST['coefficient'] ?? 1);

        if ($name === '' || $classId <= 0) {
            setFlash('error', 'Nom et filiere requis.');
        } else {
            if ($id > 0) {
                $stmt = $db->prepare('UPDATE subjects SET name=?, class_id=?, coefficient=? WHERE id=? AND client_id=?');
                $stmt->execute([$name, $classId, $coef, $id, $workingClientId]);
                setFlash('success', 'Matiere mise a jour.');
            } else {
                $stmt = $db->prepare('INSERT INTO subjects (client_id, class_id, name, coefficient) VALUES (?,?,?,?)');
                $stmt->execute([$workingClientId, $classId, $name, $coef]);
                setFlash('success', 'Matiere creee.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM subjects WHERE id = ? AND client_id = ?');
        $stmt->execute([$id, $workingClientId]);
        setFlash('success', 'Matiere supprimee.');
    }
    redirect('subjects.php?client_id=' . $workingClientId);
}

$stmt = $db->prepare(
    'SELECT s.*, c.name AS class_name FROM subjects s JOIN classes c ON c.id = s.class_id
     WHERE s.client_id = ? ORDER BY c.name ASC, s.name ASC'
);
$stmt->execute([$workingClientId]);
$subjects = $stmt->fetchAll();

$classesStmt = $db->prepare('SELECT id, name FROM classes WHERE client_id = ? ORDER BY name ASC');
$classesStmt->execute([$workingClientId]);
$classesList = $classesStmt->fetchAll();

require_once __DIR__ . '/inc_header.php';
?>

<?php require __DIR__ . '/inc_client_switcher.php'; ?>

<div class="admin-content-header">
    <div><h2>Matieres</h2></div>
    <button class="btn btn-primary" data-modal-open="subjectModal" onclick="resetSubjectForm()" <?= empty($classesList) ? 'disabled title="Creez une filiere d\'abord"' : '' ?>><i class="fa-solid fa-plus"></i> Nouvelle matiere</button>
</div>

<?php if (empty($classesList)): ?>
    <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i> Veuillez d'abord creer une filiere avant d'ajouter des matieres.</div>
<?php endif; ?>

<div class="admin-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Matiere</th><th>Filiere</th><th>Coefficient</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($subjects as $s): ?>
                <tr>
                    <td><strong><?= sanitize($s['name']) ?></strong></td>
                    <td><?= sanitize($s['class_name']) ?></td>
                    <td><?= sanitize((string) $s['coefficient']) ?></td>
                    <td class="action-icons">
                        <button class="edit" title="Modifier" onclick='editSubject(<?= json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fa-solid fa-pen"></i></button>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer cette matiere ?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                            <button type="submit" class="delete" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($subjects)): ?><tr><td colspan="4" class="text-center">Aucune matiere enregistree.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="subjectModal">
    <div class="modal-box">
        <form method="post">
            <div class="modal-header"><h3 id="subjectModalTitle">Nouvelle matiere</h3><button type="button" class="modal-close" data-modal-close>&times;</button></div>
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="subject_id" value="">
                <div class="form-group"><label>Nom de la matiere *</label><input type="text" class="form-control" name="name" id="subject_name" required></div>
                <div class="form-group"><label>Filiere *</label>
                    <select class="form-control" name="class_id" id="subject_class" required>
                        <?php foreach ($classesList as $c): ?><option value="<?= (int) $c['id'] ?>"><?= sanitize($c['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Coefficient</label><input type="number" step="0.5" min="0" class="form-control" name="coefficient" id="subject_coef" value="1"></div>
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
    document.getElementById('subjectModalTitle').textContent = 'Nouvelle matiere';
    document.getElementById('subject_id').value = '';
    document.getElementById('subject_name').value = '';
    document.getElementById('subject_coef').value = 1;
}
function editSubject(s) {
    document.getElementById('subjectModalTitle').textContent = 'Modifier la matiere';
    document.getElementById('subject_id').value = s.id;
    document.getElementById('subject_name').value = s.name;
    document.getElementById('subject_class').value = s.class_id;
    document.getElementById('subject_coef').value = s.coefficient;
    document.getElementById('subjectModal').classList.add('open');
}
</script>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
