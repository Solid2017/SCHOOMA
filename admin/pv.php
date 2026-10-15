<?php
/** Proces-verbal de deliberation imprimable (A4 paysage) */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/lmd_view.php';
requireRole(ROLES_SCHOOL_HEADS);
$db = getDB();
$workingClientId = resolveWorkingClientId();

$juryId = (int) ($_GET['jury_id'] ?? 0);
$stmt = $db->prepare('SELECT j.*, c.name AS class_name, c.level AS class_level, ay.label AS year_label,
                             u.name AS validator_name, u.job_title AS validator_title
                      FROM juries j
                      JOIN classes c ON c.id = j.class_id JOIN academic_years ay ON ay.id = j.academic_year_id
                      LEFT JOIN users u ON u.id = j.validated_by
                      WHERE j.id = ? AND j.client_id = ?');
$stmt->execute([$juryId, $workingClientId]);
$jury = $stmt->fetch();
if (!$jury) {
    http_response_code(404);
    die('Jury introuvable.');
}

$stmt = $db->prepare('SELECT * FROM clients WHERE id = ?');
$stmt->execute([$workingClientId]);
$school = $stmt->fetch();

$stmt = $db->prepare("SELECT jm.*, t.first_name, t.last_name, t.specialty FROM jury_members jm LEFT JOIN teachers t ON t.id = jm.teacher_id
                      WHERE jm.jury_id = ? ORDER BY FIELD(jm.role, 'president','vice_president','secretaire','rapporteur','membre'), t.last_name, jm.full_name");
$stmt->execute([$juryId]);
$members = $stmt->fetchAll();
$memberName = static fn(array $m): string => $m['teacher_id'] ? $m['first_name'] . ' ' . mb_strtoupper($m['last_name']) : (string) $m['full_name'];
$president = array_values(array_filter($members, static fn($m) => $m['role'] === 'president'))[0] ?? null;

$data = lmdComputeClass($db, $workingClientId, (int) $jury['class_id'], (int) $jury['academic_year_id']);
$semesterNumbers = array_keys($data['curriculum']);
$st = $data['stats'];
$closed = $jury['status'] === 'cloture';
$validated = $jury['validated_at'] !== null;
// Signataire du visa : le DG / PCA ayant valide, sinon le premier compte de direction de l'ecole
$approver = $validated && $jury['validator_name']
    ? ['name' => $jury['validator_name'], 'job_title' => $jury['validator_title']]
    : lmdSchoolOfficial($db, $workingClientId, ROLE_ADMIN_ECOLE);
$approverTitle = $approver['job_title'] ?? '' ?: 'Le Directeur Général / PCA';
$sessionLabel = $jury['session_type'] === 'rattrapage' ? 'Session de rattrapage' : 'Session normale';
$dateLabel = $jury['session_date'] ? formatDate($jury['session_date']) : '……/……/…………';
$city = $school['city'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PV de délibération — <?= e($jury['class_name']) ?></title>
<meta name="robots" content="noindex, nofollow">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
<style>
@page { size: A4 landscape; margin: 9mm; }
.pv-draft { text-align: center; color: var(--danger); font-weight: 800; letter-spacing: 2px; border: 2px dashed var(--danger); padding: 4px; margin-bottom: 10px; }
</style>
</head>
<body class="print-page">
<div class="print-toolbar">
    <a href="<?= ADMIN_URL ?>/jury.php?client_id=<?= $workingClientId ?>&id=<?= $juryId ?>" class="btn btn-outline btn-sm" style="color:#fff;border-color:rgba(255,255,255,.5)">&larr; Retour au jury</a>
    <span style="font-size:.85rem;"><?= $validated ? 'Délibération validée par la Direction générale' : ($closed ? 'Délibération clôturée, en attente de validation' : 'Projet : délibération non clôturée') ?></span>
    <button type="button" class="btn btn-accent btn-sm" onclick="window.print()">Imprimer</button>
</div>

<article class="lmd-doc landscape">
    <?= lmdSchoolHeaderHtml($school, 'Procès-verbal de délibération', ['Année universitaire ' . $jury['year_label'], $sessionLabel]) ?>
    <?php if (!$closed): ?><div class="pv-draft">PROJET — DÉLIBÉRATION NON CLÔTURÉE</div>
    <?php elseif (!$validated): ?><div class="pv-draft">EN ATTENTE DE VALIDATION PAR LA DIRECTION GÉNÉRALE</div><?php endif; ?>

    <p class="pv-text">
        Le <?= e($dateLabel) ?><?= $jury['location'] ? ', en ' . e($jury['location']) : '' ?>, le jury de délibération de la filière
        <strong><?= e($jury['class_name']) ?></strong> (<?= e($jury['class_level'] ?: 'niveau non précisé') ?>), régulièrement constitué,
        s'est réuni<?= $president ? ' sous la présidence de <strong>' . e($memberName($president)) . '</strong>' : '' ?>
        pour délibérer sur les résultats de l'année universitaire <?= e($jury['year_label']) ?>, conformément au système LMD
        (<?= LMD_YEAR_CREDITS ?> crédits par année, 30 par semestre).
    </p>

    <div class="pv-section-title">Composition du jury</div>
    <table class="lmd-identity">
        <?php foreach ($members as $m): ?>
            <tr><th><?= e(LMD_JURY_ROLES[$m['role']] ?? $m['role']) ?></th><td><?= e($memberName($m)) ?></td><td><?= e($m['title'] ?: ($m['specialty'] ?? '') ?: ($m['teacher_id'] ? 'Enseignant' : 'Personnalité extérieure')) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$members): ?><tr><td>Aucun membre désigné.</td></tr><?php endif; ?>
    </table>

    <div class="pv-section-title">Résultats</div>
    <table class="lmd-table pv-results">
        <thead><tr>
            <th>N°</th><th>Matricule</th><th>Nom et prénom(s)</th>
            <?php foreach ($semesterNumbers as $n): ?><th>Moy. S<?= $n ?></th><th>Cr. S<?= $n ?></th><?php endforeach; ?>
            <th>Moy. ann.</th><th>Pts jury</th><th>Moy. finale</th><th>Crédits</th><th>Rang</th><th>Décision</th><th>Mention</th><th>Observation</th>
        </tr></thead>
        <tbody>
        <?php $i = 0; foreach ($data['rows'] as $row): $r = $row['result']; $i++; ?>
            <tr>
                <td><?= $i ?></td>
                <td><?= e($row['student']['matricule']) ?></td>
                <td><?= e(mb_strtoupper($row['student']['last_name']) . ' ' . $row['student']['first_name']) ?></td>
                <?php foreach ($semesterNumbers as $n): $s = $r['semesters'][$n]; ?>
                    <td class="num"><?= lmdFormat($s['average']) ?></td><td><?= lmdFormatCredits($s['acquired']) ?></td>
                <?php endforeach; ?>
                <td class="num"><?= lmdFormat($r['annual_average']) ?></td>
                <td class="num"><?= $r['jury_points'] > 0 ? '+' . lmdFormat($r['jury_points']) : '' ?></td>
                <td class="num"><strong><?= lmdFormat($r['final_average']) ?></strong></td>
                <td><strong><?= lmdFormatCredits($r['credits_acquired']) ?>/<?= lmdFormatCredits($r['credits_total']) ?></strong></td>
                <td><?= $row['rank'] ?? '-' ?></td>
                <td><strong><?= e(lmdDecisionLabel($row['final_decision'])) ?></strong></td>
                <td><?= e($row['mention'] ?? '') ?></td>
                <td style="text-align:left;"><?= e($row['stored']['observation'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="pv-stats">
        <div><strong><?= $st['effectif'] ?></strong>Inscrits</div>
        <div><strong><?= $st['admis'] ?></strong>Admis</div>
        <div><strong><?= $st['admis_dettes'] ?></strong>Admis avec dettes</div>
        <div><strong><?= $st['ajourne'] ?></strong>Ajournés</div>
        <div><strong><?= $st['redouble'] + $st['exclu'] ?></strong>Redoublants / exclus</div>
        <div><strong><?= $st['attente'] ?></strong>En attente</div>
        <div><strong><?= $st['taux_reussite'] === null ? '-' : lmdFormat($st['taux_reussite'], 1) . ' %' ?></strong>Taux de réussite</div>
    </div>

    <?php if ($jury['observations']): ?>
        <div class="pv-section-title">Observations du jury</div>
        <p class="pv-text"><?= nl2br(e($jury['observations'])) ?></p>
    <?php endif; ?>

    <p class="pv-text" style="margin-top:12px;">En foi de quoi, le présent procès-verbal a été établi et signé par les membres du jury.</p>
    <footer class="lmd-doc-footer">
        <div class="lmd-place">Fait<?= $city !== '' ? ' à ' . e($city) : '' ?>, le <?= e($jury['session_date'] ? formatDate($jury['session_date']) : date('d/m/Y')) ?></div>
        <div class="lmd-signatures">
            <?php foreach ($members as $m): ?>
                <div><span><?= e(LMD_JURY_ROLES[$m['role']] ?? $m['role']) ?></span><small><?= e($memberName($m)) ?></small><em>&nbsp;</em></div>
            <?php endforeach; ?>
        </div>
        <div class="pv-approval">
            <span>Vu et approuvé<?= $validated ? ' le ' . e(formatDate($jury['validated_at'])) : '' ?></span>
            <strong><?= e($approverTitle) ?></strong>
            <small><?= e($approver['name'] ?? '') ?></small>
        </div>
        <p class="lmd-disclaimer">Procès-verbal généré par <?= e(SITE_NAME) ?> — <?= e($school['name']) ?><?= $closed ? ' — délibération clôturée le ' . e(formatDate($jury['deliberated_at'], 'd/m/Y à H:i')) : '' ?><?= $validated ? ' — validée et publiée par la Direction générale le ' . e(formatDate($jury['validated_at'], 'd/m/Y à H:i')) : '' ?>.</p>
    </footer>
</article>
</body>
</html>
