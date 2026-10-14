<?php
require_once __DIR__ . '/auth.php';
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE]);
$db = getDB();
$workingClientId = resolveWorkingClientId();
$pageTitle = 'Filieres / Classes';
$breadcrumb = 'Structure academique / Filieres';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect('classes.php?client_id=' . $workingClientId);
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $level = sanitize($_POST['level'] ?? '');
        $ayId = (int) ($_POST['academic_year_id'] ?? 0);
        $tuition = (float) ($_POST['tuition_amount'] ?? 0);

        if ($name === '' || $ayId <= 0) {
            setFlash('error', 'Nom et annee universitaire requis.');
        } else {
            if ($id > 0) {
                $stmt = $db->prepare('UPDATE classes SET name=?, level=?, academic_year_id=?, tuition_amount=? WHERE id=? AND client_id=?');
                $stmt->execute([$name, $level, $ayId, $tuition, $id, $workingClientId]);
                setFlash('success', 'Filiere mise a jour.');
            } else {
                $stmt = $db->prepare('INSERT INTO classes (client_id, academic_year_id, name, level, tuition_amount) VALUES (?,?,?,?,?)');
                $stmt->execute([$workingClientId, $ayId, $name, $level, $tuition]);
                setFlash('success', 'Filiere creee.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM classes WHERE id = ? AND client_id = ?');
        $stmt->execute([$id, $workingClientId]);
        setFlash('success', 'Filiere supprimee.');
    }
    redirect('classes.php?client_id=' . $workingClientId);
}

$stmt = $db->prepare(
    'SELECT c.*, ay.label AS year_label, (SELECT COUNT(*) FROM registrations r WHERE r.class_id = c.id) AS students_count
     FROM classes c JOIN academic_years ay ON ay.id = c.academic_year_id
     WHERE c.client_id = ? ORDER BY ay.start_date DESC, c.name ASC'
);
$stmt->execute([$workingClientId]);
$classes = $stmt->fetchAll();

$yearsStmt = $db->prepare('SELECT * FROM academic_years WHERE client_id = ? ORDER BY start_date DESC');
$yearsStmt->execute([$workingClientId]);
$years = $yearsStmt->fetchAll();

require_once __DIR__ . '/inc_header.php';
?>

<?php require __DIR__ . '/inc_client_switcher.php'; ?>

<div class="admin-content-header">
    <div><h2>Filieres / Classes</h2></div>
    <button class="btn btn-primary" data-modal-open="classModal" onclick="resetClassForm()" <?= empty($years) ? 'disabled title="Creez une annee universitaire d\'abord"' : '' ?>><i class="fa-solid fa-plus"></i> Nouvelle filiere</button>
</div>

<?php if (empty($years)): ?>
    <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i> Veuillez d'abord creer une annee universitaire avant d'ajouter des filieres.</div>
<?php endif; ?>

<div class="admin-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Filiere</th><th>Niveau</th><th>Annee</th><th>Frais de scolarite</th><th>Etudiants</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($classes as $c): ?>
                <tr>
                    <td><strong><?= sanitize($c['name']) ?></strong></td>
                    <td><?= sanitize($c['level'] ?? '-') ?></td>
                    <td><?= sanitize($c['year_label']) ?></td>
                    <td><?= formatMoney($c['tuition_amount']) ?></td>
                    <td><?= (int) $c['students_count'] ?></td>
                    <td class="action-icons">
                        <button class="edit" title="Modifier" onclick='editClass(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fa-solid fa-pen"></i></button>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer cette filiere ?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <button type="submit" class="delete" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($classes)): ?><tr><td colspan="6" class="text-center">Aucune filiere enregistree.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="classModal">
    <div class="modal-box">
        <form method="post">
            <div class="modal-header"><h3 id="classModalTitle">Nouvelle filiere</h3><button type="button" class="modal-close" data-modal-close>&times;</button></div>
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="class_id" value="">
                <div class="form-group"><label>Nom de la filiere *</label><input type="text" class="form-control" name="name" id="class_name" required></div>
                <div class="form-group"><label>Niveau</label><input type="text" class="form-control" name="level" id="class_level" placeholder="Ex: Licence 1"></div>
                <div class="form-group"><label>Annee universitaire *</label>
                    <select class="form-control" name="academic_year_id" id="class_year" required>
                        <?php foreach ($years as $y): ?><option value="<?= (int) $y['id'] ?>"><?= sanitize($y['label']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Frais de scolarite (FCFA)</label><input type="number" step="0.01" min="0" class="form-control" name="tuition_amount" id="class_tuition" value="0"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetClassForm() {
    document.getElementById('classModalTitle').textContent = 'Nouvelle filiere';
    document.getElementById('class_id').value = '';
    document.getElementById('class_name').value = '';
    document.getElementById('class_level').value = '';
    document.getElementById('class_tuition').value = 0;
}
function editClass(c) {
    document.getElementById('classModalTitle').textContent = 'Modifier la filiere';
    document.getElementById('class_id').value = c.id;
    document.getElementById('class_name').value = c.name;
    document.getElementById('class_level').value = c.level || '';
    document.getElementById('class_year').value = c.academic_year_id;
    document.getElementById('class_tuition').value = c.tuition_amount;
    document.getElementById('classModal').classList.add('open');
}
</script>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
