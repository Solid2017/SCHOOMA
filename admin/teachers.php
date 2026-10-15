<?php
require_once __DIR__ . '/auth.php';
requireRole(ROLES_SCHOOL_HEADS);
$db = getDB();
$workingClientId = resolveWorkingClientId();
$pageTitle = 'Enseignants';
$breadcrumb = 'Enseignants / Liste';
// Remuneration des enseignants : decision de la Direction generale (DG / PCA)
$canSetPay = hasRole(ROLES_DIRECTION);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect('teachers.php?client_id=' . $workingClientId);
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $matricule = sanitize($_POST['matricule'] ?? '');
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $specialty = sanitize($_POST['specialty'] ?? '');
        $hourlyRate = (float) ($_POST['hourly_rate'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['actif', 'inactif'], true) ? $_POST['status'] : 'actif';

        if ($firstName === '' || $lastName === '') {
            setFlash('error', 'Nom et prenom obligatoires.');
        } elseif ($id > 0) {
            if ($canSetPay) {
                $stmt = $db->prepare('UPDATE teachers SET matricule=?, first_name=?, last_name=?, email=?, phone=?, specialty=?, hourly_rate=?, status=? WHERE id=? AND client_id=?');
                $stmt->execute([$matricule, $firstName, $lastName, $email, $phone, $specialty, $hourlyRate, $status, $id, $workingClientId]);
            } else {
                $stmt = $db->prepare('UPDATE teachers SET matricule=?, first_name=?, last_name=?, email=?, phone=?, specialty=?, status=? WHERE id=? AND client_id=?');
                $stmt->execute([$matricule, $firstName, $lastName, $email, $phone, $specialty, $status, $id, $workingClientId]);
            }
            setFlash('success', 'Enseignant mis a jour.');
        } else {
            $stmt = $db->prepare('INSERT INTO teachers (client_id, matricule, first_name, last_name, email, phone, specialty, hourly_rate, status) VALUES (?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$workingClientId, $matricule, $firstName, $lastName, $email, $phone, $specialty, $canSetPay ? $hourlyRate : 0, $status]);
            setFlash('success', $canSetPay ? 'Enseignant ajoute.' : 'Enseignant ajoute. Sa remuneration sera fixee par la Direction generale.');
        }
    } elseif ($action === 'delete' && $canSetPay) {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM teachers WHERE id = ? AND client_id = ?');
        $stmt->execute([$id, $workingClientId]);
        setFlash('success', 'Enseignant supprime.');
    }
    redirect('teachers.php?client_id=' . $workingClientId);
}

$search = sanitize($_GET['q'] ?? '');
$sql = 'SELECT * FROM teachers WHERE client_id = ?';
$params = [$workingClientId];
if ($search !== '') {
    $sql .= ' AND (first_name LIKE ? OR last_name LIKE ? OR specialty LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
$sql .= ' ORDER BY created_at DESC';

if (($_GET['export'] ?? '') === 'csv') {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $csvRows = array_map(fn($t) => [$t['matricule'], $t['first_name'], $t['last_name'], $t['specialty'], $t['phone'], $t['email'], $canSetPay ? $t['hourly_rate'] : '-', $t['status']], $rows);
    exportCsv('enseignants.csv', ['Matricule', 'Prenom', 'Nom', 'Specialite', 'Telephone', 'Email', 'Taux horaire', 'Statut'], $csvRows);
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$teachers = $stmt->fetchAll();

require_once __DIR__ . '/inc_header.php';
?>

<?php require __DIR__ . '/inc_client_switcher.php'; ?>

<div class="admin-content-header">
    <div><h2>Enseignants</h2></div>
    <div style="display:flex;gap:10px;">
        <a href="?client_id=<?= $workingClientId ?>&export=csv" class="btn btn-outline"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
        <button class="btn btn-primary" data-modal-open="teacherModal" onclick="resetTeacherForm()"><i class="fa-solid fa-plus"></i> Nouvel enseignant</button>
    </div>
</div>

<div class="admin-card">
    <form class="filters-bar" method="get">
        <input type="hidden" name="client_id" value="<?= $workingClientId ?>">
        <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="text" class="form-control" name="q" placeholder="Nom ou specialite..." value="<?= sanitize($search) ?>"></div>
        <button class="btn btn-outline btn-sm" type="submit">Filtrer</button>
    </form>

    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Enseignant</th><th>Specialite</th><th>Contact</th><?php if ($canSetPay): ?><th>Taux horaire</th><?php endif; ?><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($teachers as $t): if (!$canSetPay) { unset($t['hourly_rate']); } ?>
                <tr>
                    <td><strong><?= sanitize($t['first_name'] . ' ' . $t['last_name']) ?></strong><br><small style="color:var(--gray-500);"><?= sanitize($t['matricule'] ?? '') ?></small></td>
                    <td><?= sanitize($t['specialty'] ?? '-') ?></td>
                    <td><?= sanitize($t['phone'] ?? '') ?><br><small><?= sanitize($t['email'] ?? '') ?></small></td>
                    <?php if ($canSetPay): ?><td><?= formatMoney($t['hourly_rate']) ?>/h</td><?php endif; ?>
                    <td><span class="badge badge-<?= $t['status'] === 'actif' ? 'success' : 'gray' ?>"><?= sanitize($t['status']) ?></span></td>
                    <td class="action-icons">
                        <button class="edit" title="Modifier" onclick='editTeacher(<?= json_encode($t, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fa-solid fa-pen"></i></button>
                        <?php if ($canSetPay): ?>
                        <a class="view" title="Paiements" href="teacher_payments.php?client_id=<?= $workingClientId ?>&teacher_id=<?= (int) $t['id'] ?>"><i class="fa-solid fa-sack-dollar"></i></a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer cet enseignant ?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                            <button type="submit" class="delete" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($teachers)): ?><tr><td colspan="<?= $canSetPay ? 6 : 5 ?>" class="text-center">Aucun enseignant trouve.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="teacherModal">
    <div class="modal-box">
        <form method="post">
            <div class="modal-header"><h3 id="teacherModalTitle">Nouvel enseignant</h3><button type="button" class="modal-close" data-modal-close>&times;</button></div>
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="teacher_id" value="">
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group"><label>Prenom *</label><input type="text" class="form-control" name="first_name" id="teacher_first_name" required></div>
                    <div class="form-group"><label>Nom *</label><input type="text" class="form-control" name="last_name" id="teacher_last_name" required></div>
                </div>
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group"><label>Matricule</label><input type="text" class="form-control" name="matricule" id="teacher_matricule"></div>
                    <div class="form-group"><label>Specialite</label><input type="text" class="form-control" name="specialty" id="teacher_specialty"></div>
                </div>
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group"><label>Telephone</label><input type="text" class="form-control" name="phone" id="teacher_phone"></div>
                    <div class="form-group"><label>Email</label><input type="email" class="form-control" name="email" id="teacher_email"></div>
                </div>
                <div class="grid grid-2" style="gap:16px;">
                    <?php if ($canSetPay): ?>
                    <div class="form-group"><label>Taux horaire (FCFA)</label><input type="number" step="0.01" min="0" class="form-control" name="hourly_rate" id="teacher_hourly_rate" value="0"></div>
                    <?php else: ?><input type="hidden" id="teacher_hourly_rate"><?php endif; ?>
                    <div class="form-group"><label>Statut</label>
                        <select class="form-control" name="status" id="teacher_status">
                            <option value="actif">Actif</option><option value="inactif">Inactif</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetTeacherForm() {
    document.getElementById('teacherModalTitle').textContent = 'Nouvel enseignant';
    ['teacher_id','teacher_first_name','teacher_last_name','teacher_matricule','teacher_specialty','teacher_phone','teacher_email'].forEach(function(id){ document.getElementById(id).value = ''; });
    document.getElementById('teacher_hourly_rate').value = 0;
    document.getElementById('teacher_status').value = 'actif';
}
function editTeacher(t) {
    document.getElementById('teacherModalTitle').textContent = "Modifier l'enseignant";
    document.getElementById('teacher_id').value = t.id;
    document.getElementById('teacher_first_name').value = t.first_name;
    document.getElementById('teacher_last_name').value = t.last_name;
    document.getElementById('teacher_matricule').value = t.matricule || '';
    document.getElementById('teacher_specialty').value = t.specialty || '';
    document.getElementById('teacher_phone').value = t.phone || '';
    document.getElementById('teacher_email').value = t.email || '';
    document.getElementById('teacher_hourly_rate').value = t.hourly_rate || 0;
    document.getElementById('teacher_status').value = t.status;
    document.getElementById('teacherModal').classList.add('open');
}
</script>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
