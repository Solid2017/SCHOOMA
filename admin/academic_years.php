<?php
require_once __DIR__ . '/auth.php';
requireRole(ROLES_SCHOOL_HEADS);
$db = getDB();
$workingClientId = resolveWorkingClientId();
$pageTitle = 'Annees universitaires';
$breadcrumb = 'Structure academique / Annees';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect('academic_years.php?client_id=' . $workingClientId);
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $label = sanitize($_POST['label'] ?? '');
        $start = $_POST['start_date'] ?? '';
        $end = $_POST['end_date'] ?? '';
        $isCurrent = isset($_POST['is_current']) ? 1 : 0;

        if ($label === '' || !$start || !$end) {
            setFlash('error', 'Tous les champs sont obligatoires.');
        } else {
            if ($isCurrent) {
                $db->prepare('UPDATE academic_years SET is_current = 0 WHERE client_id = ?')->execute([$workingClientId]);
            }
            if ($id > 0) {
                $stmt = $db->prepare('UPDATE academic_years SET label=?, start_date=?, end_date=?, is_current=? WHERE id=? AND client_id=?');
                $stmt->execute([$label, $start, $end, $isCurrent, $id, $workingClientId]);
                setFlash('success', 'Annee universitaire mise a jour.');
            } else {
                $stmt = $db->prepare('INSERT INTO academic_years (client_id, label, start_date, end_date, is_current) VALUES (?,?,?,?,?)');
                $stmt->execute([$workingClientId, $label, $start, $end, $isCurrent]);
                setFlash('success', 'Annee universitaire creee.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM academic_years WHERE id = ? AND client_id = ?');
        $stmt->execute([$id, $workingClientId]);
        setFlash('success', 'Annee universitaire supprimee.');
    }
    redirect('academic_years.php?client_id=' . $workingClientId);
}

$stmt = $db->prepare('SELECT * FROM academic_years WHERE client_id = ? ORDER BY start_date DESC');
$stmt->execute([$workingClientId]);
$years = $stmt->fetchAll();

require_once __DIR__ . '/inc_header.php';
?>

<?php require __DIR__ . '/inc_client_switcher.php'; ?>

<div class="admin-content-header">
    <div><h2>Annees universitaires</h2></div>
    <button class="btn btn-primary" data-modal-open="yearModal" onclick="resetYearForm()"><i class="fa-solid fa-plus"></i> Nouvelle annee</button>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Libelle</th><th>Debut</th><th>Fin</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($years as $y): ?>
                <tr>
                    <td><strong><?= sanitize($y['label']) ?></strong></td>
                    <td><?= formatDate($y['start_date']) ?></td>
                    <td><?= formatDate($y['end_date']) ?></td>
                    <td><?= $y['is_current'] ? '<span class="badge badge-success">Annee en cours</span>' : '<span class="badge badge-gray">Archivee</span>' ?></td>
                    <td class="action-icons">
                        <button class="edit" title="Modifier" onclick='editYear(<?= json_encode($y, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fa-solid fa-pen"></i></button>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer cette annee universitaire ?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $y['id'] ?>">
                            <button type="submit" class="delete" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($years)): ?><tr><td colspan="5" class="text-center">Aucune annee universitaire enregistree.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="yearModal">
    <div class="modal-box">
        <form method="post">
            <div class="modal-header"><h3 id="yearModalTitle">Nouvelle annee universitaire</h3><button type="button" class="modal-close" data-modal-close>&times;</button></div>
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="year_id" value="">
                <div class="form-group"><label>Libelle * (ex: 2026-2027)</label><input type="text" class="form-control" name="label" id="year_label" required></div>
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group"><label>Date de debut *</label><input type="date" class="form-control" name="start_date" id="year_start" required></div>
                    <div class="form-group"><label>Date de fin *</label><input type="date" class="form-control" name="end_date" id="year_end" required></div>
                </div>
                <div class="form-group"><label><input type="checkbox" name="is_current" id="year_current" style="width:auto;"> Definir comme annee en cours</label></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetYearForm() {
    document.getElementById('yearModalTitle').textContent = 'Nouvelle annee universitaire';
    document.getElementById('year_id').value = '';
    document.getElementById('year_label').value = '';
    document.getElementById('year_start').value = '';
    document.getElementById('year_end').value = '';
    document.getElementById('year_current').checked = false;
}
function editYear(y) {
    document.getElementById('yearModalTitle').textContent = "Modifier l'annee universitaire";
    document.getElementById('year_id').value = y.id;
    document.getElementById('year_label').value = y.label;
    document.getElementById('year_start').value = y.start_date;
    document.getElementById('year_end').value = y.end_date;
    document.getElementById('year_current').checked = y.is_current == 1;
    document.getElementById('yearModal').classList.add('open');
}
</script>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
