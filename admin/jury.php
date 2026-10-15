<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/lmd.php';
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE]);
$db = getDB();
$workingClientId = resolveWorkingClientId();

$juryId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $db->prepare('SELECT j.*, c.name AS class_name, c.level AS class_level, ay.label AS year_label FROM juries j
                      JOIN classes c ON c.id = j.class_id JOIN academic_years ay ON ay.id = j.academic_year_id
                      WHERE j.id = ? AND j.client_id = ?');
$stmt->execute([$juryId, $workingClientId]);
$jury = $stmt->fetch();
if (!$jury) {
    setFlash('error', 'Jury introuvable.');
    redirect('juries.php?client_id=' . $workingClientId);
}
$classId = (int) $jury['class_id'];
$yearId = (int) $jury['academic_year_id'];
$closed = $jury['status'] === 'cloture';
$selfUrl = 'jury.php?client_id=' . $workingClientId . '&id=' . $juryId;
$pageTitle = 'Jury : ' . $jury['name'];
$breadcrumb = 'Pedagogie / Jurys / ' . $jury['class_name'];

$loadMembers = static function () use ($db, $juryId): array {
    $stmt = $db->prepare("SELECT jm.*, t.first_name, t.last_name, t.specialty FROM jury_members jm LEFT JOIN teachers t ON t.id = jm.teacher_id
                          WHERE jm.jury_id = ? ORDER BY FIELD(jm.role, 'president','vice_president','secretaire','rapporteur','membre'), t.last_name, jm.full_name");
    $stmt->execute([$juryId]);
    return $stmt->fetchAll();
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect($selfUrl);
    }
    $action = $_POST['action'] ?? '';
    $editable = !$closed || $action === 'reopen';
    if (!$editable) {
        setFlash('error', 'Ce jury est cloture : rouvrez-le pour modifier sa composition ou ses decisions.');
        redirect($selfUrl);
    }

    if ($action === 'update_info') {
        $name = inputText($_POST['name'] ?? '', 150);
        $stmt = $db->prepare('UPDATE juries SET name = ?, session_type = ?, session_date = ?, location = ?, observations = ? WHERE id = ? AND client_id = ?');
        $stmt->execute([
            $name !== '' ? $name : $jury['name'],
            ($_POST['session_type'] ?? '') === 'rattrapage' ? 'rattrapage' : 'normale',
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['session_date'] ?? '') ? $_POST['session_date'] : null,
            inputText($_POST['location'] ?? '', 150) ?: null,
            inputText($_POST['observations'] ?? '', 2000) ?: null,
            $juryId, $workingClientId,
        ]);
        setFlash('success', 'Informations du jury mises a jour.');
    } elseif ($action === 'add_member') {
        $role = array_key_exists($_POST['role'] ?? '', LMD_JURY_ROLES) ? $_POST['role'] : 'membre';
        $teacherId = (int) ($_POST['teacher_id'] ?? 0);
        $fullName = inputText($_POST['full_name'] ?? '', 150);
        $title = inputText($_POST['title'] ?? '', 150) ?: null;
        if ($teacherId > 0) {
            $check = $db->prepare('SELECT id FROM teachers WHERE id = ? AND client_id = ?');
            $check->execute([$teacherId, $workingClientId]);
            $teacherId = $check->fetch() ? $teacherId : 0;
        }
        if ($teacherId === 0 && $fullName === '') {
            setFlash('error', 'Choisissez un enseignant ou saisissez le nom d\'une personnalite exterieure.');
        } else {
            if (in_array($role, ['president', 'vice_president', 'secretaire'], true)) {
                $db->prepare('UPDATE jury_members SET role = "membre" WHERE jury_id = ? AND role = ?')->execute([$juryId, $role]);
            }
            $stmt = $db->prepare('INSERT INTO jury_members (jury_id, teacher_id, full_name, title, role) VALUES (?,?,?,?,?)
                                  ON DUPLICATE KEY UPDATE role = VALUES(role), title = VALUES(title)');
            $stmt->execute([$juryId, $teacherId ?: null, $teacherId ? null : $fullName, $title, $role]);
            setFlash('success', 'Membre designe.');
        }
    } elseif ($action === 'set_role') {
        $memberId = (int) ($_POST['member_id'] ?? 0);
        $role = array_key_exists($_POST['role'] ?? '', LMD_JURY_ROLES) ? $_POST['role'] : 'membre';
        if (in_array($role, ['president', 'vice_president', 'secretaire'], true)) {
            $db->prepare('UPDATE jury_members SET role = "membre" WHERE jury_id = ? AND role = ? AND id <> ?')->execute([$juryId, $role, $memberId]);
        }
        $db->prepare('UPDATE jury_members SET role = ? WHERE id = ? AND jury_id = ?')->execute([$role, $memberId, $juryId]);
        setFlash('success', 'Role mis a jour.');
    } elseif ($action === 'remove_member') {
        $db->prepare('DELETE FROM jury_members WHERE id = ? AND jury_id = ?')->execute([(int) ($_POST['member_id'] ?? 0), $juryId]);
        setFlash('success', 'Membre retire du jury.');
    } elseif ($action === 'deliberate' || $action === 'close') {
        $students = lmdComputeClass($db, $workingClientId, $classId, $yearId)['rows'];
        $upsert = $db->prepare('INSERT INTO results (client_id, student_id, class_id, academic_year_id, jury_points, jury_decision, observation, jury_id)
                                VALUES (?,?,?,?,?,?,?,?)
                                ON DUPLICATE KEY UPDATE class_id = VALUES(class_id), jury_points = VALUES(jury_points),
                                    jury_decision = VALUES(jury_decision), observation = VALUES(observation), jury_id = VALUES(jury_id)');
        $db->beginTransaction();
        foreach ($_POST['delib'] ?? [] as $studentId => $input) {
            $studentId = (int) $studentId;
            if (!isset($students[$studentId]) || !is_array($input)) {
                continue;
            }
            $points = max(0.0, min((float) LMD_MAX_JURY_POINTS, (float) str_replace(',', '.', (string) ($input['points'] ?? '0'))));
            $decision = array_key_exists($input['decision'] ?? '', LMD_DECISIONS) ? $input['decision'] : null;
            $upsert->execute([$workingClientId, $studentId, $classId, $yearId, round($points, 2), $decision, inputText($input['observation'] ?? '', 255) ?: null, $juryId]);
        }
        $canClose = $action === 'close' && array_filter($loadMembers(), static fn($m) => $m['role'] === 'president');
        $data = lmdComputeClass($db, $workingClientId, $classId, $yearId);
        lmdStoreClassResults($db, $workingClientId, $classId, $yearId, $data, $juryId, $canClose ? true : null);

        if ($canClose) {
            $db->prepare('UPDATE juries SET status = "cloture", deliberated_at = NOW(), session_date = COALESCE(session_date, CURDATE()) WHERE id = ?')->execute([$juryId]);
            setFlash('success', 'Deliberation cloturee : resultats publies. Le proces-verbal est pret a etre imprime.');
        } else {
            $db->prepare('UPDATE juries SET status = "en_cours" WHERE id = ? AND status = "planifie"')->execute([$juryId]);
            setFlash($action === 'close' ? 'error' : 'success', $action === 'close'
                ? 'Deliberation enregistree mais non cloturee : designez d\'abord le/la president(e) du jury.'
                : 'Deliberation enregistree.');
        }
        $db->commit();
        redirect($selfUrl . ($action === 'close' && !$canClose ? '#composition' : '#deliberation'));
    } elseif ($action === 'reopen') {
        $db->prepare('UPDATE juries SET status = "en_cours" WHERE id = ?')->execute([$juryId]);
        setFlash('success', 'Jury rouvert : les decisions peuvent etre modifiees (les resultats restent publies).');
    }
    redirect($selfUrl);
}

$members = $loadMembers();
$memberTeacherIds = array_filter(array_column($members, 'teacher_id'));
$teachersStmt = $db->prepare('SELECT id, first_name, last_name, specialty FROM teachers WHERE client_id = ? AND status = "actif" ORDER BY last_name, first_name');
$teachersStmt->execute([$workingClientId]);
$availableTeachers = array_filter($teachersStmt->fetchAll(), static fn($t) => !in_array($t['id'], $memberTeacherIds));
$hasRole = static fn(string $role): bool => (bool) array_filter($members, static fn($m) => $m['role'] === $role);

$data = lmdComputeClass($db, $workingClientId, $classId, $yearId);
$semesterNumbers = array_keys($data['curriculum']);
$st = $data['stats'];
$memberName = static fn(array $m): string => $m['teacher_id'] ? $m['first_name'] . ' ' . $m['last_name'] : (string) $m['full_name'];
$disabled = $closed ? 'disabled' : '';

require_once __DIR__ . '/inc_header.php';
?>

<div class="admin-content-header">
    <div>
        <h2><?= e($jury['name']) ?></h2>
        <div class="admin-breadcrumb"><?= e($jury['class_name']) ?> · <?= e($jury['class_level']) ?> · <?= e($jury['year_label']) ?> · session <?= $jury['session_type'] === 'rattrapage' ? 'de rattrapage' : 'normale' ?>
            · <?= $closed ? '<span class="badge badge-success">Clôturé le ' . formatDate($jury['deliberated_at'], 'd/m/Y H:i') . '</span>' : '<span class="badge badge-' . ($jury['status'] === 'en_cours' ? 'warning">En délibération' : 'info">Planifié') . '</span>' ?></div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="pv.php?client_id=<?= $workingClientId ?>&jury_id=<?= $juryId ?>" target="_blank" class="btn btn-outline"><i class="fa-solid fa-file-signature"></i> Procès-verbal</a>
        <a href="transcript.php?client_id=<?= $workingClientId ?>&class_id=<?= $classId ?>" target="_blank" class="btn btn-outline"><i class="fa-solid fa-print"></i> Relevés de la filière</a>
        <?php if ($closed): ?>
            <form method="post" onsubmit="return confirm('Rouvrir la délibération ?');"><?= csrfField() ?><input type="hidden" name="action" value="reopen"><input type="hidden" name="id" value="<?= $juryId ?>"><button class="btn btn-accent"><i class="fa-solid fa-lock-open"></i> Rouvrir</button></form>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-2" id="composition">
    <div class="admin-card">
        <div class="admin-card-header"><h3><i class="fa-solid fa-users"></i> Composition du jury</h3></div>
        <?php if (!$hasRole('president')): ?><div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> Aucun(e) président(e) désigné(e).</div><?php endif; ?>
        <?php if (!$hasRole('secretaire')): ?><div class="alert alert-info"><i class="fa-solid fa-circle-info"></i> Aucun(e) secrétaire désigné(e).</div><?php endif; ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Membre</th><th>Rôle</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($members as $m): ?>
                    <tr>
                        <td><strong><?= e($memberName($m)) ?></strong><br><small style="color:var(--gray-500);"><?= e($m['title'] ?: ($m['specialty'] ?? '') ?: ($m['teacher_id'] ? 'Enseignant' : 'Personnalité extérieure')) ?></small></td>
                        <td>
                            <form method="post"><?= csrfField() ?><input type="hidden" name="action" value="set_role"><input type="hidden" name="id" value="<?= $juryId ?>"><input type="hidden" name="member_id" value="<?= (int) $m['id'] ?>">
                                <select class="grid-select" name="role" onchange="this.form.submit()" <?= $disabled ?>>
                                    <?php foreach (LMD_JURY_ROLES as $key => $label): ?><option value="<?= $key ?>" <?= $m['role'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td class="action-icons">
                            <?php if (!$closed): ?>
                            <form method="post" onsubmit="return confirm('Retirer ce membre ?');"><?= csrfField() ?><input type="hidden" name="action" value="remove_member"><input type="hidden" name="id" value="<?= $juryId ?>"><input type="hidden" name="member_id" value="<?= (int) $m['id'] ?>"><button class="delete" title="Retirer"><i class="fa-solid fa-user-minus"></i></button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$members): ?><tr><td colspan="3" class="text-center">Aucun membre désigné.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!$closed): ?>
        <form method="post" style="margin-top:18px;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_member">
            <input type="hidden" name="id" value="<?= $juryId ?>">
            <div class="grid grid-2" style="gap:12px;">
                <div class="form-group"><label>Enseignant</label>
                    <select class="form-control" name="teacher_id"><option value="0">— Personnalité extérieure —</option>
                        <?php foreach ($availableTeachers as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['last_name'] . ' ' . $t['first_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Rôle</label>
                    <select class="form-control" name="role"><?php foreach (LMD_JURY_ROLES as $key => $label): ?><option value="<?= $key ?>" <?= $key === 'membre' ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
                </div>
                <div class="form-group"><label>Nom (si extérieur)</label><input type="text" class="form-control" name="full_name" maxlength="150" placeholder="Ex : Dr Hassan Abakar"></div>
                <div class="form-group"><label>Qualité / fonction</label><input type="text" class="form-control" name="title" maxlength="150" placeholder="Ex : Représentant du ministère"></div>
            </div>
            <button class="btn btn-outline btn-sm"><i class="fa-solid fa-user-plus"></i> Désigner</button>
        </form>
        <?php endif; ?>
    </div>

    <div class="admin-card">
        <div class="admin-card-header"><h3><i class="fa-solid fa-circle-info"></i> Session</h3></div>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_info">
            <input type="hidden" name="id" value="<?= $juryId ?>">
            <div class="form-group"><label>Intitulé</label><input type="text" class="form-control" name="name" maxlength="150" value="<?= e($jury['name']) ?>" <?= $disabled ?>></div>
            <div class="grid grid-2" style="gap:12px;">
                <div class="form-group"><label>Session</label>
                    <select class="form-control" name="session_type" <?= $disabled ?>><option value="normale">Normale</option><option value="rattrapage" <?= $jury['session_type'] === 'rattrapage' ? 'selected' : '' ?>>Rattrapage</option></select>
                </div>
                <div class="form-group"><label>Date</label><input type="date" class="form-control" name="session_date" value="<?= e($jury['session_date']) ?>" <?= $disabled ?>></div>
            </div>
            <div class="form-group"><label>Lieu</label><input type="text" class="form-control" name="location" maxlength="150" value="<?= e($jury['location']) ?>" <?= $disabled ?>></div>
            <div class="form-group"><label>Observations générales (reprises dans le PV)</label><textarea class="form-control" name="observations" rows="3" <?= $disabled ?>><?= e($jury['observations']) ?></textarea></div>
            <?php if (!$closed): ?><button class="btn btn-outline btn-sm">Enregistrer</button><?php endif; ?>
        </form>
        <div class="pv-stats" style="margin-top:18px;">
            <div><strong><?= $st['effectif'] ?></strong>Étudiants</div>
            <div><strong><?= $st['admis'] ?></strong>Admis</div>
            <div><strong><?= $st['admis_dettes'] ?></strong>Avec dettes</div>
            <div><strong><?= $st['ajourne'] + $st['redouble'] + $st['exclu'] ?></strong>Ajournés</div>
            <div><strong><?= $st['taux_reussite'] === null ? '-' : lmdFormat($st['taux_reussite'], 1) . '%' ?></strong>Réussite</div>
        </div>
    </div>
</div>

<div class="admin-card" id="deliberation">
    <div class="admin-card-header">
        <h3><i class="fa-solid fa-gavel"></i> Délibération</h3>
        <small style="color:var(--gray-500);">Points de jury : 0 à <?= lmdFormat((float) LMD_MAX_JURY_POINTS) ?> ajouté(s) à la moyenne annuelle. « Selon calcul » applique la règle LMD.</small>
    </div>
    <?php if (!$data['curriculum']): ?>
        <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i> Aucune UE définie pour cette filière.</div>
    <?php else: ?>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= $juryId ?>">
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Rang</th><th>Étudiant</th>
                    <?php foreach ($semesterNumbers as $n): ?><th>S<?= $n ?></th><?php endforeach; ?>
                    <th>Moy. ann.</th><th>Points jury</th><th>Moy. finale</th><th>Crédits</th><th>Décision</th><th>Observation</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($data['rows'] as $sid => $row): $r = $row['result']; $stored = $row['stored']; ?>
                    <tr>
                        <td><strong><?= $row['rank'] ?? '-' ?></strong></td>
                        <td><strong><?= e(mb_strtoupper($row['student']['last_name']) . ' ' . $row['student']['first_name']) ?></strong><br><small style="color:var(--gray-500);"><?= e($row['student']['matricule']) ?></small></td>
                        <?php foreach ($semesterNumbers as $n): $s = $r['semesters'][$n]; ?>
                            <td><?= lmdFormat($s['average']) ?><br><small class="st-<?= $s['status'] ?>"><?= lmdFormatCredits($s['acquired']) ?>/<?= lmdFormatCredits($s['credits']) ?> cr.</small></td>
                        <?php endforeach; ?>
                        <td><?= lmdFormat($r['annual_average']) ?></td>
                        <td><input type="text" inputmode="decimal" class="grid-input" style="width:64px;" name="delib[<?= $sid ?>][points]" value="<?= $r['jury_points'] > 0 ? e(rtrim(rtrim(number_format($r['jury_points'], 2, '.', ''), '0'), '.')) : '' ?>" placeholder="0" <?= $disabled ?>></td>
                        <td><strong><?= lmdFormat($r['final_average']) ?></strong></td>
                        <td><strong><?= lmdFormatCredits($r['credits_acquired']) ?>/<?= lmdFormatCredits($r['credits_total']) ?></strong><?= $r['missing'] ? '<br><small style="color:var(--danger);">' . (int) $r['missing'] . ' ABS</small>' : '' ?></td>
                        <td>
                            <select class="grid-select" name="delib[<?= $sid ?>][decision]" <?= $disabled ?>>
                                <option value="">Selon calcul : <?= e(lmdDecisionLabel($r['decision'])) ?></option>
                                <?php foreach (LMD_DECISIONS as $key => $label): ?><option value="<?= $key ?>" <?= ($stored['jury_decision'] ?? null) === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                            </select>
                            <?= $row['mention'] ? '<br><small>Mention ' . e($row['mention']) . '</small>' : '' ?>
                        </td>
                        <td><input type="text" class="grid-input" style="width:150px;" maxlength="255" name="delib[<?= $sid ?>][observation]" value="<?= e($stored['observation'] ?? '') ?>" <?= $disabled ?>></td>
                        <td class="action-icons"><a class="view" title="Relevé" target="_blank" href="transcript.php?client_id=<?= $workingClientId ?>&student_id=<?= $sid ?>"><i class="fa-solid fa-file-lines"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$data['rows']): ?><tr><td colspan="<?= 9 + count($semesterNumbers) ?>" class="text-center">Aucun étudiant inscrit.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!$closed && $data['rows']): ?>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px;">
            <button class="btn btn-primary" name="action" value="deliberate"><i class="fa-solid fa-floppy-disk"></i> Enregistrer la délibération</button>
            <button class="btn btn-accent" name="action" value="close" onclick="return confirm('Clôturer la délibération ? Les résultats de la filière seront publiés et le jury verrouillé.');"><i class="fa-solid fa-lock"></i> Clôturer et publier</button>
        </div>
        <?php if ($st['attente'] > 0): ?><p style="font-size:.85rem;color:var(--danger);margin-top:10px;"><?= $st['attente'] ?> étudiant(s) sans notes sur tous les semestres : décision en attente.</p><?php endif; ?>
        <?php endif; ?>
    </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
