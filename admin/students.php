<?php
require_once __DIR__ . '/auth.php';
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE, ROLE_ASSISTANT]);
$db = getDB();
$workingClientId = resolveWorkingClientId();
$pageTitle = 'Etudiants & Inscriptions';
$breadcrumb = 'Scolarite / Etudiants';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect('students.php?client_id=' . $workingClientId);
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $matricule = sanitize($_POST['matricule'] ?? '');
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $dob = $_POST['dob'] ?: null;
        $gender = in_array($_POST['gender'] ?? '', ['M', 'F'], true) ? $_POST['gender'] : null;
        $phone = sanitize($_POST['phone'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['actif', 'inactif', 'diplome', 'abandon'], true) ? $_POST['status'] : 'actif';
        $classId = (int) ($_POST['class_id'] ?? 0);
        $amountTotal = (float) ($_POST['amount_total'] ?? 0);

        if ($matricule === '' || $firstName === '' || $lastName === '' || $classId <= 0) {
            setFlash('error', 'Matricule, nom, prenom et filiere sont obligatoires.');
            redirect('students.php?client_id=' . $workingClientId);
        }

        $classStmt = $db->prepare('SELECT academic_year_id FROM classes WHERE id = ? AND client_id = ?');
        $classStmt->execute([$classId, $workingClientId]);
        $class = $classStmt->fetch();
        if (!$class) {
            setFlash('error', 'Filiere invalide.');
            redirect('students.php?client_id=' . $workingClientId);
        }

        try {
            $db->beginTransaction();
            if ($id > 0) {
                $stmt = $db->prepare('UPDATE students SET matricule=?, first_name=?, last_name=?, dob=?, gender=?, phone=?, email=?, address=?, status=? WHERE id=? AND client_id=?');
                $stmt->execute([$matricule, $firstName, $lastName, $dob, $gender, $phone, $email, $address, $status, $id, $workingClientId]);
                $studentId = $id;

                $regStmt = $db->prepare('SELECT id FROM registrations WHERE student_id = ? AND academic_year_id = ?');
                $regStmt->execute([$studentId, $class['academic_year_id']]);
                $reg = $regStmt->fetch();
                if ($reg) {
                    $db->prepare('UPDATE registrations SET class_id=?, amount_total=? WHERE id=?')->execute([$classId, $amountTotal, $reg['id']]);
                } else {
                    $db->prepare('INSERT INTO registrations (client_id, student_id, class_id, academic_year_id, registration_date, amount_total) VALUES (?,?,?,?,?,?)')
                       ->execute([$workingClientId, $studentId, $classId, $class['academic_year_id'], date('Y-m-d'), $amountTotal]);
                }
                setFlash('success', 'Etudiant mis a jour avec succes.');
            } else {
                $stmt = $db->prepare('INSERT INTO students (client_id, matricule, first_name, last_name, dob, gender, phone, email, address, status) VALUES (?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([$workingClientId, $matricule, $firstName, $lastName, $dob, $gender, $phone, $email, $address, $status]);
                $studentId = (int) $db->lastInsertId();

                $db->prepare('INSERT INTO registrations (client_id, student_id, class_id, academic_year_id, registration_date, amount_total) VALUES (?,?,?,?,?,?)')
                   ->execute([$workingClientId, $studentId, $classId, $class['academic_year_id'], date('Y-m-d'), $amountTotal]);
                setFlash('success', 'Etudiant inscrit avec succes.');
            }
            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();
            setFlash('error', 'Ce matricule existe deja pour cette ecole.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM students WHERE id = ? AND client_id = ?');
        $stmt->execute([$id, $workingClientId]);
        setFlash('success', 'Etudiant supprime.');
    }
    redirect('students.php?client_id=' . $workingClientId);
}

$search = sanitize($_GET['q'] ?? '');
$classFilter = (int) ($_GET['class_id'] ?? 0);
$statusFilter = sanitize($_GET['status'] ?? '');

$sql = 'SELECT s.*, cl.name AS class_name, cl.id AS current_class_id, ay.label AS year_label,
        (SELECT reg.amount_total FROM registrations reg WHERE reg.student_id = s.id ORDER BY reg.created_at DESC LIMIT 1) AS amount_total,
        (SELECT COALESCE(SUM(p.amount_paid),0) FROM payments p JOIN registrations r2 ON r2.id = p.registration_id WHERE r2.student_id = s.id) AS amount_paid
        FROM students s
        LEFT JOIN registrations reg2 ON reg2.id = (SELECT id FROM registrations r WHERE r.student_id = s.id ORDER BY r.created_at DESC LIMIT 1)
        LEFT JOIN classes cl ON cl.id = reg2.class_id
        LEFT JOIN academic_years ay ON ay.id = reg2.academic_year_id
        WHERE s.client_id = ?';
$params = [$workingClientId];

if ($search !== '') {
    $sql .= ' AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.matricule LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($classFilter > 0) {
    $sql .= ' AND cl.id = ?';
    $params[] = $classFilter;
}
if ($statusFilter !== '') {
    $sql .= ' AND s.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY s.created_at DESC';

if (($_GET['export'] ?? '') === 'csv') {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $csvRows = array_map(fn($s) => [$s['matricule'], $s['first_name'], $s['last_name'], $s['class_name'] ?? '-', $s['status'], $s['phone'], $s['email'], $s['amount_total'] ?? 0, $s['amount_paid'] ?? 0], $rows);
    exportCsv('etudiants.csv', ['Matricule', 'Prenom', 'Nom', 'Filiere', 'Statut', 'Telephone', 'Email', 'Montant du', 'Montant paye'], $csvRows);
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$classesStmt = $db->prepare('SELECT c.id, c.name, c.tuition_amount FROM classes c JOIN academic_years ay ON ay.id = c.academic_year_id WHERE c.client_id = ? ORDER BY ay.is_current DESC, c.name ASC');
$classesStmt->execute([$workingClientId]);
$classesList = $classesStmt->fetchAll();

require_once __DIR__ . '/inc_header.php';
?>

<?php require __DIR__ . '/inc_client_switcher.php'; ?>

<div class="admin-content-header">
    <div><h2>Etudiants & Inscriptions</h2></div>
    <div style="display:flex;gap:10px;">
        <a href="?client_id=<?= $workingClientId ?>&export=csv&q=<?= urlencode($search) ?>&class_id=<?= $classFilter ?>&status=<?= urlencode($statusFilter) ?>" class="btn btn-outline"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
        <button class="btn btn-primary" data-modal-open="studentModal" onclick="resetStudentForm()" <?= empty($classesList) ? 'disabled title="Creez une filiere d\'abord"' : '' ?>><i class="fa-solid fa-plus"></i> Nouvel etudiant</button>
    </div>
</div>

<?php if (empty($classesList)): ?>
    <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i> Veuillez d'abord creer une annee universitaire et une filiere avant d'inscrire des etudiants.</div>
<?php endif; ?>

<div class="admin-card">
    <form class="filters-bar" method="get">
        <input type="hidden" name="client_id" value="<?= $workingClientId ?>">
        <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="text" class="form-control" name="q" placeholder="Nom, prenom ou matricule..." value="<?= sanitize($search) ?>"></div>
        <select class="form-control" name="class_id">
            <option value="0">Toutes les filieres</option>
            <?php foreach ($classesList as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $classFilter === (int) $c['id'] ? 'selected' : '' ?>><?= sanitize($c['name']) ?></option><?php endforeach; ?>
        </select>
        <select class="form-control" name="status">
            <option value="">Tous les statuts</option>
            <?php foreach (['actif', 'inactif', 'diplome', 'abandon'] as $st): ?><option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-outline btn-sm" type="submit">Filtrer</button>
    </form>

    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Matricule</th><th>Etudiant</th><th>Filiere</th><th>Annee</th><th>Scolarite</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td><?= sanitize($s['matricule']) ?></td>
                    <td><strong><?= sanitize($s['first_name'] . ' ' . $s['last_name']) ?></strong></td>
                    <td><?= sanitize($s['class_name'] ?? '-') ?></td>
                    <td><?= sanitize($s['year_label'] ?? '-') ?></td>
                    <td><?= formatMoney($s['amount_paid'] ?? 0) ?> / <?= formatMoney($s['amount_total'] ?? 0) ?></td>
                    <td><span class="badge badge-<?= $s['status'] === 'actif' ? 'success' : ($s['status'] === 'diplome' ? 'info' : 'gray') ?>"><?= sanitize($s['status']) ?></span></td>
                    <td class="action-icons">
                        <button class="edit" title="Modifier" onclick='editStudent(<?= json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fa-solid fa-pen"></i></button>
                        <a class="view" title="Paiements" href="payments.php?client_id=<?= $workingClientId ?>&student_id=<?= (int) $s['id'] ?>"><i class="fa-solid fa-money-bill"></i></a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer cet etudiant et toutes ses donnees associees ?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                            <button type="submit" class="delete" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($students)): ?><tr><td colspan="7" class="text-center">Aucun etudiant trouve.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="studentModal">
    <div class="modal-box">
        <form method="post">
            <div class="modal-header"><h3 id="studentModalTitle">Nouvel etudiant</h3><button type="button" class="modal-close" data-modal-close>&times;</button></div>
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="student_id" value="">
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group"><label>Matricule *</label><input type="text" class="form-control" name="matricule" id="student_matricule" required></div>
                    <div class="form-group"><label>Statut</label>
                        <select class="form-control" name="status" id="student_status">
                            <option value="actif">Actif</option><option value="inactif">Inactif</option><option value="diplome">Diplome</option><option value="abandon">Abandon</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group"><label>Prenom *</label><input type="text" class="form-control" name="first_name" id="student_first_name" required></div>
                    <div class="form-group"><label>Nom *</label><input type="text" class="form-control" name="last_name" id="student_last_name" required></div>
                </div>
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group"><label>Date de naissance</label><input type="date" class="form-control" name="dob" id="student_dob"></div>
                    <div class="form-group"><label>Genre</label>
                        <select class="form-control" name="gender" id="student_gender">
                            <option value="">-</option><option value="M">Masculin</option><option value="F">Feminin</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group"><label>Telephone</label><input type="text" class="form-control" name="phone" id="student_phone"></div>
                    <div class="form-group"><label>Email</label><input type="email" class="form-control" name="email" id="student_email"></div>
                </div>
                <div class="form-group"><label>Adresse</label><input type="text" class="form-control" name="address" id="student_address"></div>
                <hr style="margin:18px 0;border-color:var(--gray-300);">
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group"><label>Filiere (inscription) *</label>
                        <select class="form-control" name="class_id" id="student_class_id" required onchange="autoFillTuition()">
                            <option value="">-- Choisir --</option>
                            <?php foreach ($classesList as $c): ?><option value="<?= (int) $c['id'] ?>" data-tuition="<?= (float) $c['tuition_amount'] ?>"><?= sanitize($c['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Montant total scolarite (FCFA)</label><input type="number" step="0.01" min="0" class="form-control" name="amount_total" id="student_amount_total"></div>
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
function autoFillTuition() {
    var sel = document.getElementById('student_class_id');
    var opt = sel.options[sel.selectedIndex];
    var tuition = opt ? opt.getAttribute('data-tuition') : 0;
    if (tuition) document.getElementById('student_amount_total').value = tuition;
}
function resetStudentForm() {
    document.getElementById('studentModalTitle').textContent = 'Nouvel etudiant';
    ['student_id','student_matricule','student_first_name','student_last_name','student_dob','student_phone','student_email','student_address','student_amount_total'].forEach(function(id){ document.getElementById(id).value = ''; });
    document.getElementById('student_status').value = 'actif';
    document.getElementById('student_gender').value = '';
    document.getElementById('student_class_id').value = '';
}
function editStudent(s) {
    document.getElementById('studentModalTitle').textContent = "Modifier l'etudiant";
    document.getElementById('student_id').value = s.id;
    document.getElementById('student_matricule').value = s.matricule;
    document.getElementById('student_first_name').value = s.first_name;
    document.getElementById('student_last_name').value = s.last_name;
    document.getElementById('student_dob').value = s.dob || '';
    document.getElementById('student_gender').value = s.gender || '';
    document.getElementById('student_phone').value = s.phone || '';
    document.getElementById('student_email').value = s.email || '';
    document.getElementById('student_address').value = s.address || '';
    document.getElementById('student_status').value = s.status;
    document.getElementById('student_class_id').value = s.current_class_id || '';
    document.getElementById('student_amount_total').value = s.amount_total || 0;
    document.getElementById('studentModal').classList.add('open');
}
</script>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
