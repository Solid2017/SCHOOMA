<?php
require_once __DIR__ . '/auth.php';
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE, ROLE_ASSISTANT]);
$db = getDB();
$workingClientId = resolveWorkingClientId();
$pageTitle = "Demandes d'admission";
$breadcrumb = 'Site web / Admissions';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect('admission_requests.php?client_id=' . $workingClientId);
    }
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'update_status') {
        $status = in_array($_POST['status'] ?? '', ['nouveau', 'contacte', 'converti', 'rejete'], true) ? $_POST['status'] : 'nouveau';
        $db->prepare('UPDATE admission_requests SET status = ? WHERE id = ? AND client_id = ?')->execute([$status, $id, $workingClientId]);
        setFlash('success', 'Statut mis a jour.');
    } elseif ($action === 'delete') {
        $db->prepare('DELETE FROM admission_requests WHERE id = ? AND client_id = ?')->execute([$id, $workingClientId]);
        setFlash('success', 'Demande supprimee.');
    }
    redirect('admission_requests.php?client_id=' . $workingClientId);
}

$stmt = $db->prepare('SELECT * FROM admission_requests WHERE client_id = ? ORDER BY created_at DESC');
$stmt->execute([$workingClientId]);
$requests = $stmt->fetchAll();

require_once __DIR__ . '/inc_header.php';
?>

<?php require __DIR__ . '/inc_client_switcher.php'; ?>

<div class="admin-content-header"><div><h2>Demandes d'admission</h2></div></div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Candidat</th><th>Contact</th><th>Programme souhaite</th><th>Date</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($requests as $r): ?>
                <tr>
                    <td><strong><?= sanitize($r['first_name'] . ' ' . $r['last_name']) ?></strong></td>
                    <td><?= sanitize($r['email']) ?><br><small><?= sanitize($r['phone']) ?></small></td>
                    <td><?= sanitize($r['program_wanted'] ?? '-') ?></td>
                    <td><?= formatDate($r['created_at'], 'd/m/Y H:i') ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <select class="form-control" name="status" onchange="this.form.submit()" style="padding:6px 10px;width:auto;">
                                <?php foreach (['nouveau', 'contacte', 'converti', 'rejete'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                    <td class="action-icons">
                        <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer cette demande ?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <button type="submit" class="delete" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($requests)): ?><tr><td colspan="6" class="text-center">Aucune demande d'admission.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
