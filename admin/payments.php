<?php
require_once __DIR__ . '/auth.php';
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE, ROLE_ASSISTANT]);
$db = getDB();
$workingClientId = resolveWorkingClientId();
$pageTitle = 'Paiements par tranches';
$breadcrumb = 'Scolarite / Paiements';
$studentFilter = (int) ($_GET['student_id'] ?? 0);

function computePaymentStatus(float $due, float $paid): string
{
    if ($paid <= 0) return 'impaye';
    if ($paid >= $due) return 'paye';
    return 'partiel';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect('payments.php?client_id=' . $workingClientId);
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $registrationId = (int) ($_POST['registration_id'] ?? 0);
        $trancheNumber = (int) ($_POST['tranche_number'] ?? 1);
        $amountDue = (float) ($_POST['amount_due'] ?? 0);
        $amountPaid = (float) ($_POST['amount_paid'] ?? 0);
        $dueDate = $_POST['due_date'] ?: null;
        $paidDate = $_POST['paid_date'] ?: null;
        $method = in_array($_POST['method'] ?? '', ['especes', 'mobile_money', 'virement', 'cheque', 'autre'], true) ? $_POST['method'] : 'especes';
        $receipt = sanitize($_POST['receipt_number'] ?? '');
        $status = computePaymentStatus($amountDue, $amountPaid);

        if ($registrationId <= 0 || $amountDue <= 0) {
            setFlash('error', 'Etudiant et montant du sont obligatoires.');
        } else {
            if ($id > 0) {
                $stmt = $db->prepare('UPDATE payments SET tranche_number=?, amount_due=?, amount_paid=?, due_date=?, paid_date=?, method=?, receipt_number=?, status=? WHERE id=? AND client_id=?');
                $stmt->execute([$trancheNumber, $amountDue, $amountPaid, $dueDate, $paidDate, $method, $receipt, $status, $id, $workingClientId]);
                setFlash('success', 'Paiement mis a jour.');
            } else {
                $stmt = $db->prepare('INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([$workingClientId, $registrationId, $trancheNumber, $amountDue, $amountPaid, $dueDate, $paidDate, $method, $receipt, $status]);
                setFlash('success', 'Tranche de paiement enregistree.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM payments WHERE id = ? AND client_id = ?');
        $stmt->execute([$id, $workingClientId]);
        setFlash('success', 'Tranche supprimee.');
    }
    redirect('payments.php?client_id=' . $workingClientId . ($studentFilter ? '&student_id=' . $studentFilter : ''));
}

$sql = "SELECT p.*, s.first_name, s.last_name, s.matricule, s.id AS student_id, c.name AS class_name
        FROM payments p
        JOIN registrations r ON r.id = p.registration_id
        JOIN students s ON s.id = r.student_id
        JOIN classes c ON c.id = r.class_id
        WHERE p.client_id = ?";
$params = [$workingClientId];
if ($studentFilter > 0) {
    $sql .= ' AND s.id = ?';
    $params[] = $studentFilter;
}
$statusFilter = sanitize($_GET['status'] ?? '');
if ($statusFilter !== '') {
    $sql .= ' AND p.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY p.created_at DESC';

if (($_GET['export'] ?? '') === 'csv') {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $csvRows = array_map(fn($p) => [$p['matricule'], $p['first_name'] . ' ' . $p['last_name'], $p['class_name'], $p['tranche_number'], $p['amount_due'], $p['amount_paid'], $p['due_date'], $p['paid_date'], $p['status']], $rows);
    exportCsv('paiements.csv', ['Matricule', 'Etudiant', 'Filiere', 'Tranche', 'Montant du', 'Montant paye', 'Echeance', 'Date paiement', 'Statut'], $csvRows);
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

$regStmt = $db->prepare(
    "SELECT r.id, s.first_name, s.last_name, s.matricule, c.name AS class_name
     FROM registrations r JOIN students s ON s.id = r.student_id JOIN classes c ON c.id = r.class_id
     WHERE r.client_id = ? ORDER BY s.last_name ASC"
);
$regStmt->execute([$workingClientId]);
$registrations = $regStmt->fetchAll();

require_once __DIR__ . '/inc_header.php';
?>

<?php require __DIR__ . '/inc_client_switcher.php'; ?>

<div class="admin-content-header">
    <div><h2>Paiements par tranches</h2></div>
    <div style="display:flex;gap:10px;">
        <a href="?client_id=<?= $workingClientId ?>&export=csv" class="btn btn-outline"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
        <button class="btn btn-primary" data-modal-open="paymentModal" onclick="resetPaymentForm()"><i class="fa-solid fa-plus"></i> Nouvelle tranche</button>
    </div>
</div>

<div class="admin-card">
    <form class="filters-bar" method="get">
        <input type="hidden" name="client_id" value="<?= $workingClientId ?>">
        <select class="form-control" name="status" onchange="this.form.submit()">
            <option value="">Tous les statuts</option>
            <option value="impaye" <?= $statusFilter === 'impaye' ? 'selected' : '' ?>>Impaye</option>
            <option value="partiel" <?= $statusFilter === 'partiel' ? 'selected' : '' ?>>Partiel</option>
            <option value="paye" <?= $statusFilter === 'paye' ? 'selected' : '' ?>>Paye</option>
        </select>
        <?php if ($studentFilter): ?><a href="?client_id=<?= $workingClientId ?>" class="btn btn-outline btn-sm">Retirer le filtre etudiant</a><?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Etudiant</th><th>Filiere</th><th>Tranche</th><th>Montant du</th><th>Montant paye</th><th>Echeance</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($payments as $p): ?>
                <tr>
                    <td><?= sanitize($p['first_name'] . ' ' . $p['last_name']) ?> <br><small style="color:var(--gray-500);"><?= sanitize($p['matricule']) ?></small></td>
                    <td><?= sanitize($p['class_name']) ?></td>
                    <td>Tranche <?= (int) $p['tranche_number'] ?></td>
                    <td><?= formatMoney($p['amount_due']) ?></td>
                    <td><?= formatMoney($p['amount_paid']) ?></td>
                    <td><?= formatDate($p['due_date']) ?></td>
                    <td><span class="badge badge-<?= $p['status'] === 'paye' ? 'success' : ($p['status'] === 'partiel' ? 'warning' : 'danger') ?>"><?= sanitize($p['status']) ?></span></td>
                    <td class="action-icons">
                        <button class="edit" title="Modifier" onclick='editPayment(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fa-solid fa-pen"></i></button>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer cette tranche ?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button type="submit" class="delete" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($payments)): ?><tr><td colspan="8" class="text-center">Aucune tranche enregistree.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="paymentModal">
    <div class="modal-box">
        <form method="post">
            <div class="modal-header"><h3 id="paymentModalTitle">Nouvelle tranche</h3><button type="button" class="modal-close" data-modal-close>&times;</button></div>
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="payment_id" value="">
                <div class="form-group"><label>Etudiant (inscription) *</label>
                    <select class="form-control" name="registration_id" id="payment_registration_id" required>
                        <option value="">-- Choisir --</option>
                        <?php foreach ($registrations as $r): ?><option value="<?= (int) $r['id'] ?>"><?= sanitize($r['last_name'] . ' ' . $r['first_name']) ?> (<?= sanitize($r['matricule']) ?>) - <?= sanitize($r['class_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group"><label>N° de tranche</label><input type="number" min="1" class="form-control" name="tranche_number" id="payment_tranche_number" value="1"></div>
                    <div class="form-group"><label>Mode de paiement</label>
                        <select class="form-control" name="method" id="payment_method">
                            <option value="especes">Especes</option><option value="mobile_money">Mobile Money</option><option value="virement">Virement</option><option value="cheque">Cheque</option><option value="autre">Autre</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group"><label>Montant du (FCFA) *</label><input type="number" step="0.01" min="0" class="form-control" name="amount_due" id="payment_amount_due" required></div>
                    <div class="form-group"><label>Montant paye (FCFA)</label><input type="number" step="0.01" min="0" class="form-control" name="amount_paid" id="payment_amount_paid" value="0"></div>
                </div>
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group"><label>Date d'echeance</label><input type="date" class="form-control" name="due_date" id="payment_due_date"></div>
                    <div class="form-group"><label>Date de paiement</label><input type="date" class="form-control" name="paid_date" id="payment_paid_date"></div>
                </div>
                <div class="form-group"><label>N° de recu</label><input type="text" class="form-control" name="receipt_number" id="payment_receipt"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetPaymentForm() {
    document.getElementById('paymentModalTitle').textContent = 'Nouvelle tranche';
    document.getElementById('payment_id').value = '';
    document.getElementById('payment_registration_id').value = '';
    document.getElementById('payment_tranche_number').value = 1;
    document.getElementById('payment_amount_due').value = '';
    document.getElementById('payment_amount_paid').value = 0;
    document.getElementById('payment_due_date').value = '';
    document.getElementById('payment_paid_date').value = '';
    document.getElementById('payment_receipt').value = '';
}
function editPayment(p) {
    document.getElementById('paymentModalTitle').textContent = 'Modifier la tranche';
    document.getElementById('payment_id').value = p.id;
    document.getElementById('payment_registration_id').value = p.registration_id;
    document.getElementById('payment_tranche_number').value = p.tranche_number;
    document.getElementById('payment_method').value = p.method;
    document.getElementById('payment_amount_due').value = p.amount_due;
    document.getElementById('payment_amount_paid').value = p.amount_paid;
    document.getElementById('payment_due_date').value = p.due_date || '';
    document.getElementById('payment_paid_date').value = p.paid_date || '';
    document.getElementById('payment_receipt').value = p.receipt_number || '';
    document.getElementById('paymentModal').classList.add('open');
}
</script>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
