<?php
require_once __DIR__ . '/auth.php';
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE]);
$db = getDB();
$workingClientId = resolveWorkingClientId();
$pageTitle = 'Resultats annuels';
$breadcrumb = 'Pedagogie / Resultats';

$classesStmt = $db->prepare('SELECT c.id, c.name, c.academic_year_id, ay.label AS year_label FROM classes c JOIN academic_years ay ON ay.id = c.academic_year_id WHERE c.client_id = ? ORDER BY ay.is_current DESC, c.name ASC');
$classesStmt->execute([$workingClientId]);
$classesList = $classesStmt->fetchAll();
$classId = (int) ($_GET['class_id'] ?? ($classesList[0]['id'] ?? 0));

$currentClass = null;
foreach ($classesList as $c) { if ((int) $c['id'] === $classId) { $currentClass = $c; break; } }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect("results.php?client_id=$workingClientId&class_id=$classId");
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'compute' && $currentClass) {
        $ayId = $currentClass['academic_year_id'];
        $studentsStmt = $db->prepare('SELECT s.id FROM students s JOIN registrations r ON r.student_id = s.id WHERE r.client_id = ? AND r.class_id = ? AND s.status = "actif"');
        $studentsStmt->execute([$workingClientId, $classId]);
        $studentIds = array_column($studentsStmt->fetchAll(), 'id');

        $averages = [];
        foreach ($studentIds as $sid) {
            $gStmt = $db->prepare(
                'SELECT g.grade_value, g.max_value, sub.coefficient
                 FROM grades g JOIN subjects sub ON sub.id = g.subject_id
                 WHERE g.client_id = ? AND g.student_id = ? AND g.academic_year_id = ? AND sub.class_id = ?'
            );
            $gStmt->execute([$workingClientId, $sid, $ayId, $classId]);
            $grades = $gStmt->fetchAll();
            if (empty($grades)) continue;

            $weightedSum = 0; $coefSum = 0;
            foreach ($grades as $g) {
                $normalized = $g['max_value'] > 0 ? ($g['grade_value'] / $g['max_value']) * 20 : 0;
                $weightedSum += $normalized * $g['coefficient'];
                $coefSum += $g['coefficient'];
            }
            $averages[$sid] = $coefSum > 0 ? round($weightedSum / $coefSum, 2) : 0;
        }

        arsort($averages);
        $rank = 0; $lastAvg = null; $sameRankCount = 0;
        foreach ($averages as $sid => $avg) {
            $rank++;
            $decision = $avg >= 10 ? 'admis' : 'ajourne';
            $mention = $avg >= 16 ? 'Tres Bien' : ($avg >= 14 ? 'Bien' : ($avg >= 12 ? 'Assez Bien' : ($avg >= 10 ? 'Passable' : null)));

            $check = $db->prepare('SELECT id FROM results WHERE student_id = ? AND academic_year_id = ?');
            $check->execute([$sid, $ayId]);
            $existing = $check->fetch();

            if ($existing) {
                $stmt = $db->prepare('UPDATE results SET class_id=?, average=?, class_rank=?, decision=?, mention=? WHERE id=?');
                $stmt->execute([$classId, $avg, $rank, $decision, $mention, $existing['id']]);
            } else {
                $stmt = $db->prepare('INSERT INTO results (client_id, student_id, class_id, academic_year_id, average, class_rank, decision, mention, published) VALUES (?,?,?,?,?,?,?,?,0)');
                $stmt->execute([$workingClientId, $sid, $classId, $ayId, $avg, $rank, $decision, $mention]);
            }
        }
        setFlash('success', count($averages) . ' resultat(s) calcule(s) a partir des notes saisies.');
    } elseif ($action === 'update_result') {
        $id = (int) ($_POST['id'] ?? 0);
        $decision = in_array($_POST['decision'] ?? '', ['admis', 'ajourne', 'redouble', 'exclu'], true) ? $_POST['decision'] : 'ajourne';
        $mention = sanitize($_POST['mention'] ?? '');
        $published = isset($_POST['published']) ? 1 : 0;
        $stmt = $db->prepare('UPDATE results SET decision=?, mention=?, published=? WHERE id=? AND client_id=?');
        $stmt->execute([$decision, $mention, $published, $id, $workingClientId]);
        setFlash('success', 'Resultat mis a jour.');
    } elseif ($action === 'publish_all') {
        $stmt = $db->prepare('UPDATE results SET published = 1 WHERE class_id = ? AND client_id = ?');
        $stmt->execute([$classId, $workingClientId]);
        setFlash('success', 'Tous les resultats de cette filiere ont ete publies.');
    }
    redirect("results.php?client_id=$workingClientId&class_id=$classId");
}

if (($_GET['export'] ?? '') === 'csv' && $currentClass) {
    $stmt = $db->prepare('SELECT r.*, s.matricule, s.first_name, s.last_name FROM results r JOIN students s ON s.id = r.student_id WHERE r.client_id = ? AND r.class_id = ? ORDER BY r.class_rank ASC');
    $stmt->execute([$workingClientId, $classId]);
    $rows = $stmt->fetchAll();
    $csvRows = array_map(fn($r) => [$r['matricule'], $r['first_name'] . ' ' . $r['last_name'], $r['average'], $r['class_rank'], $r['decision'], $r['mention'], $r['published'] ? 'Oui' : 'Non'], $rows);
    exportCsv('resultats.csv', ['Matricule', 'Etudiant', 'Moyenne', 'Rang', 'Decision', 'Mention', 'Publie'], $csvRows);
}

$results = [];
if ($currentClass) {
    $stmt = $db->prepare('SELECT r.*, s.matricule, s.first_name, s.last_name FROM results r JOIN students s ON s.id = r.student_id WHERE r.client_id = ? AND r.class_id = ? ORDER BY r.class_rank ASC');
    $stmt->execute([$workingClientId, $classId]);
    $results = $stmt->fetchAll();
}

require_once __DIR__ . '/inc_header.php';
?>

<?php require __DIR__ . '/inc_client_switcher.php'; ?>

<div class="admin-content-header">
    <div><h2>Resultats annuels</h2></div>
    <div style="display:flex;gap:10px;">
        <a href="?client_id=<?= $workingClientId ?>&class_id=<?= $classId ?>&export=csv" class="btn btn-outline"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
        <form method="post" style="display:inline;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="compute">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-calculator"></i> Calculer les moyennes</button>
        </form>
        <?php if (!empty($results)): ?>
        <form method="post" style="display:inline;" onsubmit="return confirm('Publier tous les resultats de cette filiere ?');">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="publish_all">
            <button type="submit" class="btn btn-accent"><i class="fa-solid fa-bullhorn"></i> Publier tout</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="admin-card">
    <form class="filters-bar" method="get">
        <input type="hidden" name="client_id" value="<?= $workingClientId ?>">
        <select class="form-control" name="class_id" onchange="this.form.submit()">
            <?php foreach ($classesList as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $classId ? 'selected' : '' ?>><?= sanitize($c['name']) ?> (<?= sanitize($c['year_label']) ?>)</option><?php endforeach; ?>
        </select>
    </form>

    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Rang</th><th>Etudiant</th><th>Moyenne</th><th>Decision</th><th>Mention</th><th>Publie</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($results as $r): ?>
                <tr>
                    <td><strong><?= $r['class_rank'] ? (int) $r['class_rank'] : '-' ?></strong></td>
                    <td><?= sanitize($r['first_name'] . ' ' . $r['last_name']) ?> (<?= sanitize($r['matricule']) ?>)</td>
                    <td><?= number_format((float) $r['average'], 2) ?>/20</td>
                    <td colspan="4">
                        <form method="post" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update_result">
                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <select class="form-control" name="decision" style="width:auto;padding:8px 10px;">
                                <?php foreach (['admis', 'ajourne', 'redouble', 'exclu'] as $d): ?>
                                    <option value="<?= $d ?>" <?= $r['decision'] === $d ? 'selected' : '' ?>><?= ucfirst($d) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" class="form-control" name="mention" value="<?= sanitize($r['mention'] ?? '') ?>" placeholder="Mention" style="width:140px;padding:8px 10px;">
                            <label style="display:flex;align-items:center;gap:6px;font-size:0.85rem;"><input type="checkbox" name="published" <?= $r['published'] ? 'checked' : '' ?> style="width:auto;"> Publie</label>
                            <button type="submit" class="btn btn-outline btn-sm">Enregistrer</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($results)): ?><tr><td colspan="7" class="text-center">Aucun resultat calcule pour cette filiere. Cliquez sur "Calculer les moyennes".</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
