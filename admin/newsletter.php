<?php
require_once __DIR__ . '/auth.php';
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE]);
$db = getDB();
$pageTitle = 'Newsletter';
$breadcrumb = 'Site web / Newsletter';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect('newsletter.php');
    }
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete') {
        $db->prepare('DELETE FROM newsletter_subscribers WHERE id = ?')->execute([$id]);
        setFlash('success', 'Abonne supprime.');
    } elseif ($action === 'toggle_status') {
        $stmt = $db->prepare('SELECT status FROM newsletter_subscribers WHERE id = ?');
        $stmt->execute([$id]);
        $current = $stmt->fetchColumn();
        $newStatus = $current === 'actif' ? 'desabonne' : 'actif';
        $db->prepare('UPDATE newsletter_subscribers SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
        setFlash('success', 'Statut mis a jour.');
    }
    redirect('newsletter.php');
}

if (($_GET['export'] ?? '') === 'csv') {
    $rows = $db->query('SELECT email, status, subscribed_at FROM newsletter_subscribers ORDER BY subscribed_at DESC')->fetchAll();
    $csvRows = array_map(fn($r) => [$r['email'], $r['status'], $r['subscribed_at']], $rows);
    exportCsv('newsletter.csv', ['Email', 'Statut', "Date d'inscription"], $csvRows);
}

$total = (int) $db->query('SELECT COUNT(*) FROM newsletter_subscribers')->fetchColumn();
$activeCount = (int) $db->query('SELECT COUNT(*) FROM newsletter_subscribers WHERE status = "actif"')->fetchColumn();
$subscribers = $db->query('SELECT * FROM newsletter_subscribers ORDER BY subscribed_at DESC')->fetchAll();

require_once __DIR__ . '/inc_header.php';
?>

<div class="admin-content-header">
    <div><h2>Newsletter</h2></div>
    <a href="?export=csv" class="btn btn-outline"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
</div>

<div class="stat-cards" style="grid-template-columns:repeat(2,1fr);max-width:500px;">
    <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-users"></i></div><div><div class="stat-value"><?= $total ?></div><div class="stat-label">Total abonnes</div></div></div>
    <div class="stat-card card-green"><div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div><div><div class="stat-value"><?= $activeCount ?></div><div class="stat-label">Actifs</div></div></div>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Email</th><th>Statut</th><th>Date d'inscription</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($subscribers as $s): ?>
                <tr>
                    <td><?= sanitize($s['email']) ?></td>
                    <td><span class="badge badge-<?= $s['status'] === 'actif' ? 'success' : 'gray' ?>"><?= sanitize($s['status']) ?></span></td>
                    <td><?= formatDate($s['subscribed_at'], 'd/m/Y H:i') ?></td>
                    <td class="action-icons">
                        <form method="post" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                            <button type="submit" class="view" title="Changer le statut"><i class="fa-solid fa-rotate"></i></button>
                        </form>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer cet abonne ?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                            <button type="submit" class="delete" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($subscribers)): ?><tr><td colspan="4" class="text-center">Aucun abonne.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
