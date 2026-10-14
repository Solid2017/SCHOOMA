<?php
require_once __DIR__ . '/auth.php';
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE]);
$db = getDB();
$workingClientId = resolveWorkingClientId();
$pageTitle = 'Jurys';
$breadcrumb = 'Pedagogie / Jurys';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect('juries.php?client_id=' . $workingClientId);
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $classId = (int) ($_POST['class_id'] ?? 0);
        $sessionDate = $_POST['session_date'] ?: null;
        $status = in_array($_POST['status'] ?? '', ['planifie', 'en_cours', 'cloture'], true) ? $_POST['status'] : 'planifie';

        $classStmt = $db->prepare('SELECT academic_year_id FROM classes WHERE id = ? AND client_id = ?');
        $classStmt->execute([$classId, $workingClientId]);
        $class = $classStmt->fetch();

        if ($name === '' || !$class) {
            setFlash('error', 'Nom du jury et filiere valides requis.');
        } elseif ($id > 0) {
            $stmt = $db->prepare('UPDATE juries SET name=?, class_id=?, session_date=?, status=? WHERE id=? AND client_id=?');
            $stmt->execute([$name, $classId, $sessionDate, $status, $id, $workingClientId]);
            setFlash('success', 'Jury mis a jour.');
        } else {
            $stmt = $db->prepare('INSERT INTO juries (client_id, academic_year_id, class_id, name, session_date, status) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$workingClientId, $class['academic_year_id'], $classId, $name, $sessionDate, $status]);
            $juryId = (int) $db->lastInsertId();

            foreach (($_POST['members'] ?? []) as $teacherId) {
                $teacherId = (int) $teacherId;
                if ($teacherId > 0) {
                    $db->prepare('INSERT INTO jury_members (jury_id, teacher_id, role) VALUES (?,?,"membre")')->execute([$juryId, $teacherId]);
                }
            }
            setFlash('success', 'Jury cree avec succes.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM juries WHERE id = ? AND client_id = ?');
        $stmt->execute([$id, $workingClientId]);
        setFlash('success', 'Jury supprime.');
    }
    redirect('juries.php?client_id=' . $workingClientId);
}

$stmt = $db->prepare('SELECT j.*, c.name AS class_name FROM juries j JOIN classes c ON c.id = j.class_id WHERE j.client_id = ? ORDER BY j.session_date DESC');
$stmt->execute([$workingClientId]);
$juries = $stmt->fetchAll();

foreach ($juries as &$jury) {
    $memStmt = $db->prepare('SELECT t.first_name, t.last_name FROM jury_members jm JOIN teachers t ON t.id = jm.teacher_id WHERE jm.jury_id = ?');
    $memStmt->execute([$jury['id']]);
    $jury['members'] = $memStmt->fetchAll();
}
unset($jury);

$classesStmt = $db->prepare('SELECT id, name FROM classes WHERE client_id = ? ORDER BY name ASC');
$classesStmt->execute([$workingClientId]);
$classesList = $classesStmt->fetchAll();

$teachersStmt = $db->prepare('SELECT id, first_name, last_name FROM teachers WHERE client_id = ? AND status = "actif" ORDER BY last_name ASC');
$teachersStmt->execute([$workingClientId]);
$teachersList = $teachersStmt->fetchAll();

require_once __DIR__ . '/inc_header.php';
?>

<?php require __DIR__ . '/inc_client_switcher.php'; ?>

<div class="admin-content-header">
    <div><h2>Jurys</h2></div>
    <button class="btn btn-primary" data-modal-open="juryModal" <?= empty($classesList) ? 'disabled' : '' ?>><i class="fa-solid fa-plus"></i> Nouveau jury</button>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Jury</th><th>Filiere</th><th>Date de session</th><th>Membres</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($juries as $j): ?>
                <tr>
                    <td><strong><?= sanitize($j['name']) ?></strong></td>
                    <td><?= sanitize($j['class_name']) ?></td>
                    <td><?= formatDate($j['session_date']) ?></td>
                    <td><?= implode(', ', array_map(fn($m) => sanitize($m['first_name'] . ' ' . $m['last_name']), $j['members'])) ?: '-' ?></td>
                    <td><span class="badge badge-<?= $j['status'] === 'cloture' ? 'success' : ($j['status'] === 'en_cours' ? 'warning' : 'info') ?>"><?= sanitize($j['status']) ?></span></td>
                    <td class="action-icons">
                        <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer ce jury ?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $j['id'] ?>">
                            <button type="submit" class="delete" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($juries)): ?><tr><td colspan="6" class="text-center">Aucun jury enregistre.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="juryModal">
    <div class="modal-box">
        <form method="post">
            <div class="modal-header"><h3>Nouveau jury</h3><button type="button" class="modal-close" data-modal-close>&times;</button></div>
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save">
                <div class="form-group"><label>Nom du jury *</label><input type="text" class="form-control" name="name" placeholder="Ex: Jury de deliberation - Session 1" required></div>
                <div class="form-group"><label>Filiere *</label>
                    <select class="form-control" name="class_id" required>
                        <?php foreach ($classesList as $c): ?><option value="<?= (int) $c['id'] ?>"><?= sanitize($c['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Date de session</label><input type="date" class="form-control" name="session_date"></div>
                <div class="form-group"><label>Statut</label>
                    <select class="form-control" name="status">
                        <option value="planifie">Planifie</option><option value="en_cours">En cours</option><option value="cloture">Cloture</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Membres du jury</label>
                    <select class="form-control" name="members[]" multiple size="5">
                        <?php foreach ($teachersList as $t): ?><option value="<?= (int) $t['id'] ?>"><?= sanitize($t['last_name'] . ' ' . $t['first_name']) ?></option><?php endforeach; ?>
                    </select>
                    <small style="color:var(--gray-500);">Maintenez Ctrl (ou Cmd) pour selectionner plusieurs membres.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-primary">Creer le jury</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
