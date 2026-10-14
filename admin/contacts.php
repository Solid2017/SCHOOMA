<?php
require_once __DIR__ . '/auth.php';
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE]);
$db = getDB();
$pageTitle = 'Messages de contact';
$breadcrumb = 'Site web / Messages';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect('contacts.php');
    }
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'mark_read') {
        $db->prepare('UPDATE contacts SET is_read = 1 WHERE id = ?')->execute([$id]);
    } elseif ($action === 'mark_unread') {
        $db->prepare('UPDATE contacts SET is_read = 0 WHERE id = ?')->execute([$id]);
    } elseif ($action === 'delete') {
        $db->prepare('DELETE FROM contacts WHERE id = ?')->execute([$id]);
        setFlash('success', 'Message supprime.');
    }
    redirect('contacts.php');
}

$filter = sanitize($_GET['filter'] ?? '');
$sql = 'SELECT * FROM contacts WHERE 1=1';
$params = [];
if ($filter === 'unread') { $sql .= ' AND is_read = 0'; }
elseif ($filter === 'read') { $sql .= ' AND is_read = 1'; }
$sql .= ' ORDER BY created_at DESC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$messages = $stmt->fetchAll();

require_once __DIR__ . '/inc_header.php';
?>

<div class="admin-content-header"><div><h2>Messages de contact</h2></div></div>

<div class="admin-card">
    <div class="filters-bar">
        <a href="?filter=" class="btn btn-sm <?= $filter === '' ? 'btn-primary' : 'btn-outline' ?>">Tous</a>
        <a href="?filter=unread" class="btn btn-sm <?= $filter === 'unread' ? 'btn-primary' : 'btn-outline' ?>">Non lus</a>
        <a href="?filter=read" class="btn btn-sm <?= $filter === 'read' ? 'btn-primary' : 'btn-outline' ?>">Lus</a>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Expediteur</th><th>Sujet</th><th>Message</th><th>Date</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($messages as $m): ?>
                <tr style="<?= $m['is_read'] ? '' : 'font-weight:600;' ?>">
                    <td><?= sanitize($m['name']) ?><br><small style="color:var(--gray-500);font-weight:400;"><?= sanitize($m['email']) ?></small></td>
                    <td><?= sanitize($m['subject'] ?? '-') ?></td>
                    <td style="max-width:280px;white-space:normal;"><?= sanitize(mb_strimwidth($m['message'], 0, 120, '...')) ?></td>
                    <td><?= formatDate($m['created_at'], 'd/m/Y H:i') ?></td>
                    <td><span class="badge badge-<?= $m['is_read'] ? 'gray' : 'info' ?>"><?= $m['is_read'] ? 'Lu' : 'Non lu' ?></span></td>
                    <td class="action-icons">
                        <form method="post" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="<?= $m['is_read'] ? 'mark_unread' : 'mark_read' ?>">
                            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                            <button type="submit" class="view" title="<?= $m['is_read'] ? 'Marquer non lu' : 'Marquer lu' ?>"><i class="fa-solid fa-<?= $m['is_read'] ? 'envelope' : 'envelope-open' ?>"></i></button>
                        </form>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer ce message ?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                            <button type="submit" class="delete" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($messages)): ?><tr><td colspan="6" class="text-center">Aucun message.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
