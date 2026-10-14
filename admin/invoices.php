<?php
require_once __DIR__ . '/auth.php';
requireRole([ROLE_SUPER_ADMIN]);
$db = getDB();
$pageTitle = 'Facturation';
$breadcrumb = 'Gestion SaaS / Facturation';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect('invoices.php');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $clientId = (int) ($_POST['client_id'] ?? 0);
        $planId = (int) ($_POST['plan_id'] ?? 0) ?: null;
        $amount = (float) ($_POST['amount'] ?? 0);
        $periodStart = $_POST['period_start'] ?? date('Y-m-d');
        $periodEnd = $_POST['period_end'] ?? date('Y-m-d', strtotime('+1 year'));
        $status = in_array($_POST['status'] ?? '', ['en_attente', 'payee', 'en_retard', 'annulee'], true) ? $_POST['status'] : 'en_attente';

        if ($clientId <= 0 || $amount <= 0) {
            setFlash('error', 'Veuillez selectionner une ecole et un montant valide.');
        } else {
            $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
            $paidAt = $status === 'payee' ? date('Y-m-d') : null;
            $stmt = $db->prepare('INSERT INTO invoices (client_id, plan_id, invoice_number, amount, period_start, period_end, status, paid_at) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->execute([$clientId, $planId, $invoiceNumber, $amount, $periodStart, $periodEnd, $status, $paidAt]);
            setFlash('success', "Facture $invoiceNumber creee avec succes.");
        }
    } elseif ($action === 'mark_paid') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('UPDATE invoices SET status = "payee", paid_at = ? WHERE id = ?');
        $stmt->execute([date('Y-m-d'), $id]);
        setFlash('success', 'Facture marquee comme payee.');
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM invoices WHERE id = ?');
        $stmt->execute([$id]);
        setFlash('success', 'Facture supprimee.');
    }
    redirect('invoices.php');
}

if (($_GET['export'] ?? '') === 'csv') {
    $rows = $db->query('SELECT i.invoice_number, c.name, i.amount, i.status, i.period_start, i.period_end, i.paid_at FROM invoices i JOIN clients c ON c.id = i.client_id ORDER BY i.created_at DESC')->fetchAll();
    $csvRows = array_map(fn($r) => [$r['invoice_number'], $r['name'], $r['amount'], $r['status'], $r['period_start'], $r['period_end'], $r['paid_at'] ?? '-'], $rows);
    exportCsv('factures.csv', ['Numero', 'Ecole', 'Montant', 'Statut', 'Debut periode', 'Fin periode', 'Payee le'], $csvRows);
}

$statusFilter = sanitize($_GET['status'] ?? '');
$sql = 'SELECT i.*, c.name AS client_name FROM invoices i JOIN clients c ON c.id = i.client_id WHERE 1=1';
$params = [];
if ($statusFilter !== '') {
    $sql .= ' AND i.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY i.created_at DESC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

$clientsList = $db->query('SELECT id, name, plan_id FROM clients ORDER BY name ASC')->fetchAll();
$plansList = $db->query('SELECT id, name, price FROM subscription_plans ORDER BY price ASC')->fetchAll();

require_once __DIR__ . '/inc_header.php';
?>

<div class="admin-content-header">
    <div><h2>Facturation</h2></div>
    <div style="display:flex;gap:10px;">
        <a href="?export=csv<?= $statusFilter ? '&status=' . urlencode($statusFilter) : '' ?>" class="btn btn-outline"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
        <button class="btn btn-primary" data-modal-open="invoiceModal"><i class="fa-solid fa-plus"></i> Nouvelle facture</button>
    </div>
</div>

<div class="admin-card">
    <form class="filters-bar" method="get">
        <select class="form-control" name="status" onchange="this.form.submit()">
            <option value="">Tous les statuts</option>
            <option value="en_attente" <?= $statusFilter === 'en_attente' ? 'selected' : '' ?>>En attente</option>
            <option value="payee" <?= $statusFilter === 'payee' ? 'selected' : '' ?>>Payee</option>
            <option value="en_retard" <?= $statusFilter === 'en_retard' ? 'selected' : '' ?>>En retard</option>
            <option value="annulee" <?= $statusFilter === 'annulee' ? 'selected' : '' ?>>Annulee</option>
        </select>
    </form>

    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>N° Facture</th><th>Ecole</th><th>Montant</th><th>Periode</th><th>Statut</th><th>Payee le</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td><?= sanitize($inv['invoice_number']) ?></td>
                    <td><?= sanitize($inv['client_name']) ?></td>
                    <td><?= formatMoney($inv['amount']) ?></td>
                    <td><?= formatDate($inv['period_start']) ?> - <?= formatDate($inv['period_end']) ?></td>
                    <td><span class="badge badge-<?= $inv['status'] === 'payee' ? 'success' : ($inv['status'] === 'en_retard' ? 'danger' : ($inv['status'] === 'annulee' ? 'gray' : 'warning')) ?>"><?= sanitize($inv['status']) ?></span></td>
                    <td><?= formatDate($inv['paid_at']) ?></td>
                    <td class="action-icons">
                        <?php if ($inv['status'] !== 'payee'): ?>
                        <form method="post" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="mark_paid">
                            <input type="hidden" name="id" value="<?= (int) $inv['id'] ?>">
                            <button type="submit" class="view" title="Marquer payee"><i class="fa-solid fa-check"></i></button>
                        </form>
                        <?php endif; ?>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer cette facture ?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $inv['id'] ?>">
                            <button type="submit" class="delete" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($invoices)): ?><tr><td colspan="7" class="text-center">Aucune facture trouvee.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="invoiceModal">
    <div class="modal-box">
        <form method="post">
            <div class="modal-header"><h3>Nouvelle facture</h3><button type="button" class="modal-close" data-modal-close>&times;</button></div>
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save">
                <div class="form-group"><label>Ecole *</label>
                    <select class="form-control" name="client_id" required>
                        <option value="">-- Choisir --</option>
                        <?php foreach ($clientsList as $c): ?><option value="<?= (int) $c['id'] ?>"><?= sanitize($c['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Plan associe</label>
                    <select class="form-control" name="plan_id">
                        <option value="">-- Aucun --</option>
                        <?php foreach ($plansList as $p): ?><option value="<?= (int) $p['id'] ?>" data-price="<?= (float) $p['price'] ?>"><?= sanitize($p['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Montant (FCFA) *</label><input type="number" step="0.01" min="0" class="form-control" name="amount" required></div>
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group"><label>Debut de periode</label><input type="date" class="form-control" name="period_start" value="<?= date('Y-m-d') ?>"></div>
                    <div class="form-group"><label>Fin de periode</label><input type="date" class="form-control" name="period_end" value="<?= date('Y-m-d', strtotime('+1 year')) ?>"></div>
                </div>
                <div class="form-group"><label>Statut</label>
                    <select class="form-control" name="status">
                        <option value="en_attente">En attente</option>
                        <option value="payee">Payee</option>
                        <option value="en_retard">En retard</option>
                        <option value="annulee">Annulee</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-primary">Creer la facture</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
