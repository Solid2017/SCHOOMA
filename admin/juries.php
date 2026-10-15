<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/lmd.php';
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE]);
$db = getDB();
$workingClientId = resolveWorkingClientId();
$pageTitle = 'Jurys de deliberation';
$breadcrumb = 'Pedagogie / Jurys';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect('juries.php?client_id=' . $workingClientId);
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = inputText($_POST['name'] ?? '', 150);
        $classId = (int) ($_POST['class_id'] ?? 0);
        $sessionType = ($_POST['session_type'] ?? '') === 'rattrapage' ? 'rattrapage' : 'normale';
        $sessionDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['session_date'] ?? '') ? $_POST['session_date'] : null;
        $location = inputText($_POST['location'] ?? '', 150) ?: null;

        $classStmt = $db->prepare('SELECT academic_year_id FROM classes WHERE id = ? AND client_id = ?');
        $classStmt->execute([$classId, $workingClientId]);
        $class = $classStmt->fetch();

        if ($name === '' || !$class) {
            setFlash('error', 'Intitule du jury et filiere valides requis.');
            redirect('juries.php?client_id=' . $workingClientId);
        }

        $db->beginTransaction();
        $stmt = $db->prepare('INSERT INTO juries (client_id, academic_year_id, class_id, name, session_type, session_date, location, status) VALUES (?,?,?,?,?,?,?,"planifie")');
        $stmt->execute([$workingClientId, $class['academic_year_id'], $classId, $name, $sessionType, $sessionDate, $location]);
        $juryId = (int) $db->lastInsertId();

        $teacherCheck = $db->prepare('SELECT id FROM teachers WHERE id = ? AND client_id = ?');
        $addMember = $db->prepare('INSERT IGNORE INTO jury_members (jury_id, teacher_id, role) VALUES (?,?,?)');
        $designations = [['president', [(int) ($_POST['president_id'] ?? 0)]], ['secretaire', [(int) ($_POST['secretary_id'] ?? 0)]], ['membre', array_map('intval', (array) ($_POST['members'] ?? []))]];
        foreach ($designations as [$role, $ids]) {
            foreach ($ids as $teacherId) {
                $teacherCheck->execute([$teacherId, $workingClientId]);
                if ($teacherId > 0 && $teacherCheck->fetch()) {
                    $addMember->execute([$juryId, $teacherId, $role]);
                }
            }
        }
        $db->commit();
        setFlash('success', 'Jury constitue. Completez si besoin sa composition puis lancez la deliberation.');
        redirect('jury.php?client_id=' . $workingClientId . '&id=' . $juryId);
    } elseif ($action === 'delete') {
        $stmt = $db->prepare('DELETE FROM juries WHERE id = ? AND client_id = ?');
        $stmt->execute([(int) ($_POST['id'] ?? 0), $workingClientId]);
        setFlash('success', 'Jury supprime.');
    }
    redirect('juries.php?client_id=' . $workingClientId);
}

$stmt = $db->prepare("SELECT j.*, c.name AS class_name, ay.label AS year_label,
        (SELECT COUNT(*) FROM jury_members jm WHERE jm.jury_id = j.id) AS members_count,
        (SELECT COALESCE(CONCAT(t.first_name, ' ', t.last_name), jm.full_name) FROM jury_members jm LEFT JOIN teachers t ON t.id = jm.teacher_id
         WHERE jm.jury_id = j.id AND jm.role = 'president' LIMIT 1) AS president
    FROM juries j JOIN classes c ON c.id = j.class_id JOIN academic_years ay ON ay.id = j.academic_year_id
    WHERE j.client_id = ? ORDER BY ay.start_date DESC, j.session_date DESC, c.name");
$stmt->execute([$workingClientId]);
$juries = $stmt->fetchAll();

$classesStmt = $db->prepare('SELECT c.id, c.name, ay.label AS year_label FROM classes c JOIN academic_years ay ON ay.id = c.academic_year_id WHERE c.client_id = ? ORDER BY ay.is_current DESC, c.name');
$classesStmt->execute([$workingClientId]);
$classesList = $classesStmt->fetchAll();

$teachersStmt = $db->prepare('SELECT id, first_name, last_name, specialty FROM teachers WHERE client_id = ? AND status = "actif" ORDER BY last_name, first_name');
$teachersStmt->execute([$workingClientId]);
$teachersList = $teachersStmt->fetchAll();

$statusLabels = ['planifie' => ['Planifié', 'info'], 'en_cours' => ['En délibération', 'warning'], 'cloture' => ['Clôturé', 'success']];

require_once __DIR__ . '/inc_header.php';
?>

<?php require __DIR__ . '/inc_client_switcher.php'; ?>

<div class="admin-content-header">
    <div><h2>Jurys de délibération</h2><div class="admin-breadcrumb">Un jury par filière et par session : composition désignée, délibération, procès-verbal.</div></div>
    <button class="btn btn-primary" data-modal-open="juryModal" <?= empty($classesList) ? 'disabled' : '' ?>><i class="fa-solid fa-plus"></i> Constituer un jury</button>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Jury</th><th>Filière</th><th>Session</th><th>Date</th><th>Président(e)</th><th>Membres</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($juries as $j): [$statusLabel, $statusBadge] = $statusLabels[$j['status']] ?? [$j['status'], 'gray']; ?>
                <tr>
                    <td><strong><a href="jury.php?client_id=<?= $workingClientId ?>&id=<?= (int) $j['id'] ?>"><?= e($j['name']) ?></a></strong></td>
                    <td><?= e($j['class_name']) ?><br><small style="color:var(--gray-500);"><?= e($j['year_label']) ?></small></td>
                    <td><?= $j['session_type'] === 'rattrapage' ? 'Rattrapage' : 'Normale' ?></td>
                    <td><?= formatDate($j['session_date']) ?></td>
                    <td><?= $j['president'] ? e($j['president']) : '<span class="badge badge-warning">À désigner</span>' ?></td>
                    <td><?= (int) $j['members_count'] ?></td>
                    <td><span class="badge badge-<?= $statusBadge ?>"><?= e($statusLabel) ?></span></td>
                    <td class="action-icons">
                        <a class="edit" title="Composition et délibération" href="jury.php?client_id=<?= $workingClientId ?>&id=<?= (int) $j['id'] ?>"><i class="fa-solid fa-gavel"></i></a>
                        <a class="view" title="Procès-verbal" target="_blank" href="pv.php?client_id=<?= $workingClientId ?>&jury_id=<?= (int) $j['id'] ?>"><i class="fa-solid fa-file-signature"></i></a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer ce jury ? Les points et décisions de jury déjà saisis sont conservés dans les résultats.');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $j['id'] ?>">
                            <button type="submit" class="delete" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$juries): ?><tr><td colspan="8" class="text-center">Aucun jury constitué.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="juryModal">
    <div class="modal-box">
        <form method="post">
            <div class="modal-header"><h3>Constituer un jury</h3><button type="button" class="modal-close" data-modal-close>&times;</button></div>
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create">
                <div class="form-group"><label>Intitulé *</label><input type="text" class="form-control" name="name" maxlength="150" value="Jury de délibération — Session normale" required></div>
                <div class="form-group"><label>Filière *</label>
                    <select class="form-control" name="class_id" required>
                        <?php foreach ($classesList as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?> (<?= e($c['year_label']) ?>)</option><?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group"><label>Session</label>
                        <select class="form-control" name="session_type"><option value="normale">Session normale</option><option value="rattrapage">Session de rattrapage</option></select>
                    </div>
                    <div class="form-group"><label>Date de délibération</label><input type="date" class="form-control" name="session_date"></div>
                </div>
                <div class="form-group"><label>Lieu</label><input type="text" class="form-control" name="location" maxlength="150" placeholder="Ex : Salle du conseil"></div>
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group"><label>Président(e) du jury</label>
                        <select class="form-control" name="president_id"><option value="0">— À désigner —</option>
                            <?php foreach ($teachersList as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['last_name'] . ' ' . $t['first_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Secrétaire</label>
                        <select class="form-control" name="secretary_id"><option value="0">— À désigner —</option>
                            <?php foreach ($teachersList as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['last_name'] . ' ' . $t['first_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Autres membres</label>
                    <select class="form-control" name="members[]" multiple size="6">
                        <?php foreach ($teachersList as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['last_name'] . ' ' . $t['first_name'] . ($t['specialty'] ? ' — ' . $t['specialty'] : '')) ?></option><?php endforeach; ?>
                    </select>
                    <small style="color:var(--gray-500);">Ctrl (ou Cmd) pour en sélectionner plusieurs. Les personnalités extérieures s'ajoutent ensuite depuis la fiche du jury.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-primary">Constituer le jury</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
