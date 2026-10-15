<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/lmd.php';
requireRole(ROLES_SCHOOL_HEADS);
$db = getDB();
$workingClientId = resolveWorkingClientId();
$pageTitle = 'Resultats LMD';
$breadcrumb = 'Pedagogie / Resultats annuels (60 credits)';

$classesStmt = $db->prepare('SELECT c.id, c.name, c.level, c.academic_year_id, ay.label AS year_label FROM classes c JOIN academic_years ay ON ay.id = c.academic_year_id
                             WHERE c.client_id = ? ORDER BY ay.is_current DESC, c.name ASC');
$classesStmt->execute([$workingClientId]);
$classesList = array_column($classesStmt->fetchAll(), null, 'id');
$classId = (int) ($_GET['class_id'] ?? $_POST['class_id'] ?? (array_key_first($classesList) ?? 0));
if (!isset($classesList[$classId])) {
    $classId = (int) (array_key_first($classesList) ?? 0);
}
$class = $classesList[$classId] ?? null;
$baseUrl = "results.php?client_id=$workingClientId&class_id=$classId";

// Le Directeur des etudes calcule et enregistre les resultats ; la Direction generale les publie
$canCompute = hasRole(ROLES_ACADEMIC);
$canPublish = hasRole(ROLES_DIRECTION);
$validatedJury = null;
if ($class) {
    $stmt = $db->prepare('SELECT id, validated_at FROM juries WHERE client_id = ? AND class_id = ? AND academic_year_id = ? AND validated_at IS NOT NULL ORDER BY validated_at DESC LIMIT 1');
    $stmt->execute([$workingClientId, $classId, (int) $class['academic_year_id']]);
    $validatedJury = $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect($baseUrl);
    }
    $action = $_POST['action'] ?? '';
    if (($action === 'compute' && !$canCompute) || (in_array($action, ['publish', 'unpublish'], true) && !$canPublish)) {
        setFlash('error', $action === 'compute' ? 'Le calcul des resultats releve du Directeur des etudes.' : 'La publication des resultats releve de la Direction generale (DG / PCA).');
        redirect($baseUrl);
    }
    if ($action === 'compute' && $validatedJury) {
        setFlash('error', 'Resultats homologues par la Direction generale : demandez le retrait de la validation du jury avant tout recalcul.');
        redirect($baseUrl);
    }
    if ($class && in_array($action, ['compute', 'publish', 'unpublish'], true)) {
        $yearId = (int) $class['academic_year_id'];
        $data = lmdComputeClass($db, $workingClientId, $classId, $yearId);
        $publish = $action === 'compute' ? null : $action === 'publish';
        $count = lmdStoreClassResults($db, $workingClientId, $classId, $yearId, $data, null, $publish);
        $message = match ($action) {
            'compute' => "$count resultat(s) calcule(s) et enregistre(s).",
            'publish' => "$count resultat(s) publie(s) : consultables sur le site avec matricule et date de naissance.",
            default => "$count resultat(s) retire(s) de la publication.",
        };
        setFlash('success', $message . ($count < $data['stats']['effectif'] ? ' Les etudiants sans notes sur tous les semestres sont ignores.' : ''));
    }
    redirect($baseUrl);
}

$data = $class ? lmdComputeClass($db, $workingClientId, $classId, (int) $class['academic_year_id']) : null;
$semesterNumbers = $data ? array_keys($data['curriculum']) : [];

if ($data && ($_GET['export'] ?? '') === 'csv') {
    $headers = ['Rang', 'Matricule', 'Nom', 'Prenom'];
    foreach ($semesterNumbers as $n) {
        array_push($headers, "Moyenne S$n", "Credits S$n");
    }
    array_push($headers, 'Moyenne annuelle', 'Points jury', 'Moyenne finale', 'Credits /60', 'Decision', 'Mention');
    $csv = [];
    foreach ($data['rows'] as $row) {
        $r = $row['result'];
        $line = [$row['rank'] ?? '', $row['student']['matricule'], $row['student']['last_name'], $row['student']['first_name']];
        foreach ($semesterNumbers as $n) {
            array_push($line, lmdFormat($r['semesters'][$n]['average']), lmdFormatCredits($r['semesters'][$n]['acquired']));
        }
        array_push($line, lmdFormat($r['annual_average']), lmdFormat($r['jury_points']), lmdFormat($r['final_average']),
            lmdFormatCredits($r['credits_acquired']), lmdDecisionLabel($row['final_decision']), $row['mention'] ?? '');
        $csv[] = $line;
    }
    exportCsv('resultats-' . slugify($class['name']) . '.csv', $headers, $csv);
}

$juryStmt = $db->prepare('SELECT id, name, status FROM juries WHERE client_id = ? AND class_id = ? ORDER BY session_date DESC, id DESC LIMIT 1');
$juryStmt->execute([$workingClientId, $classId]);
$jury = $juryStmt->fetch();

require_once __DIR__ . '/inc_header.php';
?>

<?php require __DIR__ . '/inc_client_switcher.php'; ?>

<div class="admin-content-header">
    <div><h2>Résultats LMD</h2><div class="admin-breadcrumb">Moyennes, crédits capitalisés sur <?= LMD_YEAR_CREDITS ?> et décisions — calculés en direct à partir des notes.</div></div>
    <?php if ($class): ?>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="transcript.php?client_id=<?= $workingClientId ?>&class_id=<?= $classId ?>" target="_blank" class="btn btn-outline"><i class="fa-solid fa-print"></i> Imprimer tous les relevés</a>
        <a href="<?= e($baseUrl) ?>&export=csv" class="btn btn-outline"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
        <?php if ($jury): ?>
            <a href="jury.php?client_id=<?= $workingClientId ?>&id=<?= (int) $jury['id'] ?>" class="btn btn-primary"><i class="fa-solid fa-gavel"></i> Jury de délibération</a>
        <?php else: ?>
            <a href="juries.php?client_id=<?= $workingClientId ?>" class="btn btn-primary"><i class="fa-solid fa-gavel"></i> Constituer un jury</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($data): $st = $data['stats']; ?>
<div class="stat-cards">
    <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-users"></i></div><div><div class="stat-value"><?= $st['effectif'] ?></div><div class="stat-label">Étudiants</div></div></div>
    <div class="stat-card card-green"><div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div><div><div class="stat-value"><?= $st['admis'] ?></div><div class="stat-label">Admis (60 crédits)</div></div></div>
    <div class="stat-card card-orange"><div class="stat-icon"><i class="fa-solid fa-scale-balanced"></i></div><div><div class="stat-value"><?= $st['admis_dettes'] ?></div><div class="stat-label">Admis avec dettes</div></div></div>
    <div class="stat-card card-red"><div class="stat-icon"><i class="fa-solid fa-circle-xmark"></i></div><div><div class="stat-value"><?= $st['ajourne'] + $st['redouble'] + $st['exclu'] ?></div><div class="stat-label">Ajournés · réussite <?= $st['taux_reussite'] === null ? '-' : lmdFormat($st['taux_reussite'], 1) . ' %' ?></div></div></div>
</div>
<?php endif; ?>

<div class="admin-card">
    <div class="filters-bar">
        <form method="get">
            <input type="hidden" name="client_id" value="<?= $workingClientId ?>">
            <select class="form-control" name="class_id" onchange="this.form.submit()">
                <?php foreach ($classesList as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $classId ? 'selected' : '' ?>><?= e($c['name']) ?> — <?= e($c['level']) ?> (<?= e($c['year_label']) ?>)</option><?php endforeach; ?>
            </select>
        </form>
        <?php if ($class): ?>
            <?php
            $actions = [];
            if ($canCompute && !$validatedJury) {
                $actions['compute'] = ['Enregistrer les résultats', 'btn-primary', 'fa-calculator'];
            }
            if ($canPublish) {
                $actions['publish'] = ['Publier', 'btn-accent', 'fa-bullhorn'];
                $actions['unpublish'] = ['Dépublier', 'btn-outline', 'fa-eye-slash'];
            }
            foreach ($actions as $act => [$label, $btnClass, $icon]): ?>
                <form method="post" style="display:inline;" <?= $act !== 'compute' ? 'onsubmit="return confirm(\'Confirmer ?\');"' : '' ?>>
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="<?= $act ?>">
                    <input type="hidden" name="class_id" value="<?= $classId ?>">
                    <button type="submit" class="btn btn-sm <?= $btnClass ?>"><i class="fa-solid <?= $icon ?>"></i> <?= $label ?></button>
                </form>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($validatedJury): ?>
        <div class="alert alert-success"><i class="fa-solid fa-stamp"></i> Résultats homologués par la Direction générale le <?= formatDate($validatedJury['validated_at'], 'd/m/Y à H:i') ?> (<a href="jury.php?client_id=<?= $workingClientId ?>&id=<?= (int) $validatedJury['id'] ?>">voir le jury</a>).</div>
    <?php endif; ?>
    <?php if (!$data || !$data['curriculum']): ?>
        <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i> Aucune unité d'enseignement définie pour cette filière : configurez les UE et ECUE pour obtenir des résultats LMD.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr>
                <th>Rang</th><th>Étudiant</th>
                <?php foreach ($semesterNumbers as $n): ?><th>Moy. S<?= $n ?></th><th>Cr. S<?= $n ?></th><?php endforeach; ?>
                <th>Moy. ann.</th><th>Jury</th><th>Moy. finale</th><th>Crédits</th><th>Décision</th><th>Mention</th><th>Publié</th><th>Relevé</th>
            </tr></thead>
            <tbody>
            <?php foreach ($data['rows'] as $sid => $row): $r = $row['result']; ?>
                <tr>
                    <td><strong><?= $row['rank'] ?? '-' ?></strong></td>
                    <td><strong><?= e(mb_strtoupper($row['student']['last_name']) . ' ' . $row['student']['first_name']) ?></strong><br><small style="color:var(--gray-500);"><?= e($row['student']['matricule']) ?></small></td>
                    <?php foreach ($semesterNumbers as $n): $s = $r['semesters'][$n]; ?>
                        <td><?= lmdFormat($s['average']) ?></td>
                        <td class="st-<?= $s['status'] ?>"><?= lmdFormatCredits($s['acquired']) ?>/<?= lmdFormatCredits($s['credits']) ?></td>
                    <?php endforeach; ?>
                    <td><?= lmdFormat($r['annual_average']) ?></td>
                    <td><?= $r['jury_points'] > 0 ? '+' . lmdFormat($r['jury_points']) : '-' ?></td>
                    <td><strong><?= lmdFormat($r['final_average']) ?></strong></td>
                    <td><strong><?= lmdFormatCredits($r['credits_acquired']) ?>/<?= lmdFormatCredits($r['credits_total']) ?></strong><?= !$r['complete'] && $r['missing'] ? '<br><small style="color:var(--danger);">' . (int) $r['missing'] . ' note(s) manquante(s)</small>' : '' ?></td>
                    <td><span class="badge badge-<?= lmdDecisionBadge($row['final_decision']) ?>"><?= e(lmdDecisionLabel($row['final_decision'])) ?></span><?= !empty($row['stored']['jury_decision']) ? '<br><small>décision du jury</small>' : '' ?></td>
                    <td><?= e($row['mention'] ?? '-') ?></td>
                    <td><?= !empty($row['stored']['published']) ? '<span class="badge badge-success">Oui</span>' : '<span class="badge badge-gray">Non</span>' ?></td>
                    <td class="action-icons"><a class="view" title="Relevé de notes" target="_blank" href="transcript.php?client_id=<?= $workingClientId ?>&student_id=<?= $sid ?>"><i class="fa-solid fa-file-lines"></i></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$data['rows']): ?><tr><td colspan="<?= 10 + 2 * count($semesterNumbers) ?>" class="text-center">Aucun étudiant inscrit.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <p style="font-size:0.8rem;color:var(--gray-500);margin-top:12px;">Validation d'une UE si moyenne ≥ 10 ; compensation entre UE d'un même semestre<?= LMD_ANNUAL_COMPENSATION ? ' et entre les deux semestres de l\'année' : '' ?>. Admis avec dettes à partir de <?= LMD_CONDITIONAL_CREDITS ?> crédits. Les points de jury et décisions particulières se saisissent dans le jury de délibération.</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
