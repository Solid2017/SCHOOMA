<?php
require_once __DIR__ . '/auth.php';
requireRole(ROLES_DIRECTION);
$db = getDB();
$workingClientId = resolveWorkingClientId();
$pageTitle = 'Paiements enseignants';
$breadcrumb = 'Enseignants / Paiements';
$teacherFilter = (int) ($_GET['teacher_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect('teacher_payments.php?client_id=' . $workingClientId);
    }
    $action = $_POST['action'] ?? '';
    $academicYear = getCurrentAcademicYear($workingClientId);
    $ayId = $academicYear['id'] ?? null;

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $teacherId = (int) ($_POST['teacher_id'] ?? 0);
        $period = sanitize($_POST['period'] ?? '');
        $amount = (float) ($_POST['amount'] ?? 0);
        $hoursDone = (float) ($_POST['hours_done'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['en_attente', 'paye'], true) ? $_POST['status'] : 'en_attente';
        $paidDate = $status === 'paye' ? ($_POST['paid_date'] ?: date('Y-m-d')) : null;
        $note = sanitize($_POST['note'] ?? '');

        if ($teacherId <= 0 || $period === '' || $amount <= 0 || !$ayId) {
            setFlash('error', "Enseignant, periode et montant sont obligatoires (verifiez qu'une annee universitaire est active).");
        } elseif ($id > 0) {
            $stmt = $db->prepare('UPDATE teacher_payments SET teacher_id=?, period=?, amount=?, hours_done=?, status=?, paid_date=?, note=? WHERE id=? AND client_id=?');
            $stmt->execute([$teacherId, $period, $amount, $hoursDone, $status, $paidDate, $note, $id, $workingClientId]);
            setFlash('success', 'Paiement enseignant mis a jour.');
        } else {
            $stmt = $db->prepare('INSERT INTO teacher_payments (client_id, teacher_id, academic_year_id, period, amount, hours_done, status, paid_date, note) VALUES (?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$workingClientId, $teacherId, $ayId, $period, $amount, $hoursDone, $status, $paidDate, $note]);
            setFlash('success', 'Paiement enseignant enregistre.');
        }
    } elseif ($action === 'mark_paid') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('UPDATE teacher_payments SET status="paye", paid_date=? WHERE id=? AND client_id=?');
        $stmt->execute([date('Y-m-d'), $id, $workingClientId]);
        setFlash('success', 'Paiement marque comme effectue.');
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM teacher_payments WHERE id = ? AND client_id = ?');
        $stmt->execute([$id, $workingClientId]);
        setFlash('success', 'Paiement supprime.');
    }
    redirect('teacher_payments.php?client_id=' . $workingClientId . ($teacherFilter ? '&teacher_id=' . $teacherFilter : ''));
}

$sql = 'SELECT tp.*, t.first_name, t.last_name, t.matricule FROM teacher_payments tp JOIN teachers t ON t.id = tp.teacher_id WHERE tp.client_id = ?';
$params = [$workingClientId];
if ($teacherFilter > 0) {
    $sql .= ' AND t.id = ?';
    $params[] = $teacherFilter;
}
$sql .= ' ORDER BY tp.created_at DESC';

if (($_GET['export'] ?? '') === 'csv') {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $csvRows = array_map(fn($p) => [$p['matricule'], $p['first_name'] . ' ' . $p['last_name'], $p['period'], $p['hours_done'], $p['amount'], $p['status'], $p['paid_date'] ?? '-'], $rows);
    exportCsv('paiements_enseignants.csv', ['Matricule', 'Enseignant', 'Periode', 'Heures', 'Montant', 'Statut', 'Date paiement'], $csvRows);
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$teacherPayments = $stmt->fetchAll();

$teachersStmt = $db->prepare('SELECT id, first_name, last_name, matricule, hourly_rate FROM teachers WHERE client_id = ? AND status = "actif" ORDER BY last_name ASC');
$teachersStmt->execute([$workingClientId]);
$teachersList = $teachersStmt->fetchAll();

require_once __DIR__ . '/inc_header.php';
?>

<?php require __DIR__ . '/inc_client_switcher.php'; ?>

<div class="admin-content-header">
    <div><h2>Paiements enseignants</h2></div>
    <div style="display:flex;gap:10px;">
        <a href="?client_id=<?= $workingClientId ?>&export=csv" class="btn btn-outline"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
        <button class="btn btn-primary" data-modal-open="tpModal" onclick="resetTpForm()"><i class="fa-solid fa-plus"></i> Nouveau paiement</button>
    </div>
</div>

<?php if ($teacherFilter): ?><a href="teacher_payments.php?client_id=<?= $workingClientId ?>" class="btn btn-outline btn-sm mb-2">Retirer le filtre enseignant</a><?php endif; ?>

<div class="admin-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Enseignant</th><th>Periode</th><th>Heures</th><th>Montant</th><th>Statut</th><th>Date paiement</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($teacherPayments as $p): ?>
                <tr>
                    <td><strong><?= sanitize($p['first_name'] . ' ' . $p['last_name']) ?></strong></td>
                    <td><?= sanitize($p['period']) ?></td>
                    <td><?= sanitize((string) $p['hours_done']) ?></td>
                    <td><?= formatMoney($p['amount']) ?></td>
                    <td><span class="badge badge-<?= $p['status'] === 'paye' ? 'success' : 'warning' ?>"><?= sanitize($p['status']) ?></span></td>
                    <td><?= formatDate($p['paid_date']) ?></td>
                    <td class="action-icons">
                        <?php if ($p['status'] !== 'paye'): ?>
                        <form method="post" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="mark_paid">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button type="submit" class="view" title="Marquer paye"><i class="fa-solid fa-check"></i></button>
                        </form>
                        <?php endif; ?>
                        <button class="edit" title="Modifier" onclick='editTp(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fa-solid fa-pen"></i></button>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer ce paiement ?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button type="submit" class="delete" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($teacherPayments)): ?><tr><td colspan="7" class="text-center">Aucun paiement enregistre.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="tpModal">
    <div class="modal-box">
        <form method="post">
            <div class="modal-header"><h3 id="tpModalTitle">Nouveau paiement</h3><button type="button" class="modal-close" data-modal-close>&times;</button></div>
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="tp_id" value="">
                <div class="form-group"><label>Enseignant *</label>
                    <select class="form-control" name="teacher_id" id="tp_teacher_id" required onchange="autoFillRate()">
                        <option value="">-- Choisir --</option>
                        <?php foreach ($teachersList as $t): ?><option value="<?= (int) $t['id'] ?>" data-rate="<?= (float) $t['hourly_rate'] ?>"><?= sanitize($t['last_name'] . ' ' . $t['first_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group"><label>Periode * (ex: 2026-01)</label><input type="text" class="form-control" name="period" id="tp_period" placeholder="AAAA-MM" required></div>
                    <div class="form-group"><label>Heures effectuees</label><input type="number" step="0.5" min="0" class="form-control" name="hours_done" id="tp_hours" oninput="computeAmount()"></div>
                </div>
                <div class="form-group"><label>Montant (FCFA) *</label><input type="number" step="0.01" min="0" class="form-control" name="amount" id="tp_amount" required></div>
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group"><label>Statut</label>
                        <select class="form-control" name="status" id="tp_status">
                            <option value="en_attente">En attente</option><option value="paye">Paye</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Date de paiement</label><input type="date" class="form-control" name="paid_date" id="tp_paid_date"></div>
                </div>
                <div class="form-group"><label>Note</label><input type="text" class="form-control" name="note" id="tp_note"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
var tpRate = 0;
function autoFillRate() {
    var sel = document.getElementById('tp_teacher_id');
    var opt = sel.options[sel.selectedIndex];
    tpRate = opt ? parseFloat(opt.getAttribute('data-rate')) || 0 : 0;
    computeAmount();
}
function computeAmount() {
    var hours = parseFloat(document.getElementById('tp_hours').value) || 0;
    if (tpRate > 0 && hours > 0) document.getElementById('tp_amount').value = (tpRate * hours).toFixed(2);
}
function resetTpForm() {
    document.getElementById('tpModalTitle').textContent = 'Nouveau paiement';
    document.getElementById('tp_id').value = '';
    document.getElementById('tp_period').value = '';
    document.getElementById('tp_hours').value = '';
    document.getElementById('tp_amount').value = '';
    document.getElementById('tp_status').value = 'en_attente';
    document.getElementById('tp_paid_date').value = '';
    document.getElementById('tp_note').value = '';
}
function editTp(p) {
    document.getElementById('tpModalTitle').textContent = 'Modifier le paiement';
    document.getElementById('tp_id').value = p.id;
    document.getElementById('tp_teacher_id').value = p.teacher_id;
    document.getElementById('tp_period').value = p.period;
    document.getElementById('tp_hours').value = p.hours_done;
    document.getElementById('tp_amount').value = p.amount;
    document.getElementById('tp_status').value = p.status;
    document.getElementById('tp_paid_date').value = p.paid_date || '';
    document.getElementById('tp_note').value = p.note || '';
    document.getElementById('tpModal').classList.add('open');
}
</script>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
