<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/lmd.php';
$user = requireLogin();
$db = getDB();
$clientId = scopeClientId();
$pageTitle = 'Tableau de bord';
$breadcrumb = 'Vue globale de votre activite';
$isDirectorOfStudies = $user['role'] === ROLE_DIRECTEUR_ETUDES;
$isGeneralDirection = $user['role'] === ROLE_ADMIN_ECOLE;

if ($clientId === null) {
    // ---- Vue Super-admin SaaS : statistiques globales plateforme ----
    $totalClients   = (int) $db->query('SELECT COUNT(*) FROM clients')->fetchColumn();
    $activeClients  = (int) $db->query('SELECT COUNT(*) FROM clients WHERE status = "actif"')->fetchColumn();
    $totalRevenue   = (float) $db->query('SELECT COALESCE(SUM(amount),0) FROM invoices WHERE status = "payee"')->fetchColumn();
    $pendingInvoices = (int) $db->query('SELECT COUNT(*) FROM invoices WHERE status IN ("en_attente","en_retard")')->fetchColumn();

    $recentClients = $db->query('SELECT c.*, p.name AS plan_name FROM clients c LEFT JOIN subscription_plans p ON p.id = c.plan_id ORDER BY c.created_at DESC LIMIT 6')->fetchAll();
    $recentInvoices = $db->query('SELECT i.*, c.name AS client_name FROM invoices i JOIN clients c ON c.id = i.client_id ORDER BY i.created_at DESC LIMIT 6')->fetchAll();
} elseif ($isDirectorOfStudies) {
    // ---- Vue Directeur des etudes : pilotage academique (aucune donnee financiere) ----
    $breadcrumb = 'Pilotage academique';
    $academicYear = getCurrentAcademicYear($clientId);
    $ayId = (int) ($academicYear['id'] ?? 0);

    $count = static function (string $sql, array $params) use ($db): int {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    };
    $totalStudents = $count('SELECT COUNT(*) FROM students WHERE client_id = ? AND status = "actif"', [$clientId]);
    $totalTeachers = $count('SELECT COUNT(*) FROM teachers WHERE client_id = ? AND status = "actif"', [$clientId]);
    $totalClasses = $count('SELECT COUNT(*) FROM classes WHERE client_id = ? AND academic_year_id = ?', [$clientId, $ayId]);
    $totalSubjects = $count('SELECT COUNT(*) FROM subjects s JOIN classes c ON c.id = s.class_id WHERE s.client_id = ? AND c.academic_year_id = ?', [$clientId, $ayId]);
    $newAdmissions = $count('SELECT COUNT(*) FROM admission_requests WHERE client_id = ? AND status = "nouveau"', [$clientId]);

    // Maquettes et avancement de la saisie (une note d'examen par etudiant et par ECUE)
    $stmt = $db->prepare(
        'SELECT c.id, c.name, c.level,
            (SELECT COALESCE(SUM(credits), 0) FROM teaching_units tu WHERE tu.class_id = c.id AND tu.semester = 1) AS credits_s1,
            (SELECT COALESCE(SUM(credits), 0) FROM teaching_units tu WHERE tu.class_id = c.id AND tu.semester = 2) AS credits_s2,
            (SELECT COUNT(*) FROM subjects s WHERE s.class_id = c.id) AS subjects_count,
            (SELECT COUNT(*) FROM registrations r WHERE r.class_id = c.id AND r.academic_year_id = c.academic_year_id AND r.status <> "annulee") AS students_count,
            (SELECT COUNT(DISTINCT g.student_id, g.subject_id) FROM grades g JOIN subjects s2 ON s2.id = g.subject_id
              WHERE s2.class_id = c.id AND g.academic_year_id = c.academic_year_id AND g.eval_type = "examen") AS exams_entered
         FROM classes c WHERE c.client_id = ? AND c.academic_year_id = ? ORDER BY c.name'
    );
    $stmt->execute([$clientId, $ayId]);
    $classProgress = $stmt->fetchAll();

    $stmt = $db->prepare('SELECT j.id, j.name, j.status, j.session_date, j.validated_at, c.name AS class_name FROM juries j JOIN classes c ON c.id = j.class_id
                          WHERE j.client_id = ? AND j.academic_year_id = ? ORDER BY FIELD(j.status, "en_cours", "planifie", "cloture"), j.session_date, c.name');
    $stmt->execute([$clientId, $ayId]);
    $juriesList = $stmt->fetchAll();
} else {
    $academicYear = getCurrentAcademicYear($clientId);
    $ayId = $academicYear['id'] ?? 0;

    if ($isGeneralDirection) {
        // ---- Elements decisionnels du DG / PCA : deliberations a valider, synthese des resultats ----
        $breadcrumb = 'Pilotage decisionnel de l\'etablissement';
        $stmt = $db->prepare('SELECT j.id, j.name, j.deliberated_at, c.name AS class_name FROM juries j JOIN classes c ON c.id = j.class_id
                              WHERE j.client_id = ? AND j.status = "cloture" AND j.validated_at IS NULL ORDER BY j.deliberated_at');
        $stmt->execute([$clientId]);
        $pendingJuries = $stmt->fetchAll();

        $stmt = $db->prepare('SELECT c.id, c.name, COUNT(r.id) AS total,
                SUM(COALESCE(r.jury_decision, r.decision) IN ("admis", "admis_dettes")) AS passed, MAX(r.published) AS published
             FROM results r JOIN classes c ON c.id = r.class_id
             WHERE r.client_id = ? AND r.academic_year_id = ? GROUP BY c.id, c.name ORDER BY c.name');
        $stmt->execute([$clientId, $ayId]);
        $resultsByClass = $stmt->fetchAll();

        $stmt = $db->prepare('SELECT COALESCE(SUM(amount), 0) FROM teacher_payments WHERE client_id = ? AND status <> "paye"');
        $stmt->execute([$clientId]);
        $teacherPayDue = (float) $stmt->fetchColumn();
    }

    $stmt = $db->prepare('SELECT COUNT(*) FROM students WHERE client_id = ? AND status = "actif"');
    $stmt->execute([$clientId]);
    $totalStudents = (int) $stmt->fetchColumn();

    $stmt = $db->prepare('SELECT COUNT(*) FROM teachers WHERE client_id = ? AND status = "actif"');
    $stmt->execute([$clientId]);
    $totalTeachers = (int) $stmt->fetchColumn();

    $stmt = $db->prepare('SELECT COALESCE(SUM(p.amount_paid),0) FROM payments p WHERE p.client_id = ?');
    $stmt->execute([$clientId]);
    $totalCollected = (float) $stmt->fetchColumn();

    $stmt = $db->prepare('SELECT COALESCE(SUM(p.amount_due - p.amount_paid),0) FROM payments p WHERE p.client_id = ? AND p.status != "paye"');
    $stmt->execute([$clientId]);
    $totalDue = (float) $stmt->fetchColumn();

    $stmt = $db->prepare(
        'SELECT s.first_name, s.last_name, s.matricule, r.registration_date, c.name AS class_name
         FROM registrations r
         JOIN students s ON s.id = r.student_id
         JOIN classes c ON c.id = r.class_id
         WHERE r.client_id = ? ORDER BY r.created_at DESC LIMIT 6'
    );
    $stmt->execute([$clientId]);
    $recentRegistrations = $stmt->fetchAll();

    $stmt = $db->prepare('SELECT COUNT(*) FROM contacts WHERE is_read = 0');
    $stmt->execute();
    $unreadMessages = (int) $stmt->fetchColumn();

    $stmt = $db->prepare('SELECT COUNT(*) FROM admission_requests WHERE client_id = ? AND status = "nouveau"');
    $stmt->execute([$clientId]);
    $newAdmissions = (int) $stmt->fetchColumn();
}

require_once __DIR__ . '/inc_header.php';
?>

<?php if ($clientId === null): ?>
    <!-- ===================== DASHBOARD SUPER-ADMIN SAAS ===================== -->
    <div class="stat-cards">
        <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-building"></i></div><div><div class="stat-value"><?= $totalClients ?></div><div class="stat-label">Ecoles clientes</div></div></div>
        <div class="stat-card card-green"><div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div><div><div class="stat-value"><?= $activeClients ?></div><div class="stat-label">Clients actifs</div></div></div>
        <div class="stat-card card-orange"><div class="stat-icon"><i class="fa-solid fa-sack-dollar"></i></div><div><div class="stat-value"><?= formatMoney($totalRevenue) ?></div><div class="stat-label">Revenus encaisses</div></div></div>
        <div class="stat-card card-red"><div class="stat-icon"><i class="fa-solid fa-file-invoice"></i></div><div><div class="stat-value"><?= $pendingInvoices ?></div><div class="stat-label">Factures en attente</div></div></div>
    </div>

    <div class="grid grid-2">
        <div class="admin-card">
            <div class="admin-card-header"><h3>Dernieres ecoles inscrites</h3><a href="clients.php" class="btn btn-outline btn-sm">Voir tout</a></div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Ecole</th><th>Plan</th><th>Statut</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentClients as $c): ?>
                        <tr>
                            <td><?= sanitize($c['name']) ?></td>
                            <td><?= sanitize($c['plan_name'] ?? '-') ?></td>
                            <td><span class="badge badge-<?= $c['status'] === 'actif' ? 'success' : ($c['status'] === 'essai' ? 'info' : 'danger') ?>"><?= sanitize($c['status']) ?></span></td>
                            <td><?= formatDate($c['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentClients)): ?><tr><td colspan="4" class="text-center">Aucune ecole enregistree.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header"><h3>Dernieres factures</h3><a href="invoices.php" class="btn btn-outline btn-sm">Voir tout</a></div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>N°</th><th>Ecole</th><th>Montant</th><th>Statut</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentInvoices as $inv): ?>
                        <tr>
                            <td><?= sanitize($inv['invoice_number']) ?></td>
                            <td><?= sanitize($inv['client_name']) ?></td>
                            <td><?= formatMoney($inv['amount']) ?></td>
                            <td><span class="badge badge-<?= $inv['status'] === 'payee' ? 'success' : ($inv['status'] === 'en_retard' ? 'danger' : 'warning') ?>"><?= sanitize($inv['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentInvoices)): ?><tr><td colspan="4" class="text-center">Aucune facture enregistree.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($isDirectorOfStudies): ?>
    <!-- ===================== DASHBOARD DIRECTEUR DES ETUDES ===================== -->
    <div class="stat-cards">
        <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-user-graduate"></i></div><div><div class="stat-value"><?= $totalStudents ?></div><div class="stat-label">Etudiants actifs</div></div></div>
        <div class="stat-card card-orange"><div class="stat-icon"><i class="fa-solid fa-chalkboard-user"></i></div><div><div class="stat-value"><?= $totalTeachers ?></div><div class="stat-label">Enseignants actifs</div></div></div>
        <div class="stat-card card-green"><div class="stat-icon"><i class="fa-solid fa-sitemap"></i></div><div><div class="stat-value"><?= $totalClasses ?></div><div class="stat-label">Filieres <?= e($academicYear['label'] ?? '') ?></div></div></div>
        <div class="stat-card card-red"><div class="stat-icon"><i class="fa-solid fa-book"></i></div><div><div class="stat-value"><?= $totalSubjects ?></div><div class="stat-label">Matieres (ECUE)</div></div></div>
    </div>

    <div class="grid grid-2">
        <div class="admin-card">
            <div class="admin-card-header"><h3>Maquettes LMD et saisie des notes</h3><a href="grades.php" class="btn btn-outline btn-sm">Saisir des notes</a></div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Filiere</th><th>Credits S1 / S2</th><th>ECUE</th><th>Examens saisis</th></tr></thead>
                    <tbody>
                    <?php foreach ($classProgress as $cp):
                        $expected = (int) $cp['students_count'] * (int) $cp['subjects_count'];
                        $pct = $expected > 0 ? (int) round(100 * (int) $cp['exams_entered'] / $expected) : 0;
                        $maquetteOk = (float) $cp['credits_s1'] === 30.0 && (float) $cp['credits_s2'] === 30.0; ?>
                        <tr>
                            <td><strong><?= e($cp['name']) ?></strong><br><small style="color:var(--gray-500);"><?= (int) $cp['students_count'] ?> etudiant(s)</small></td>
                            <td><span class="badge badge-<?= $maquetteOk ? 'success' : 'warning' ?>"><?= lmdFormatCredits((float) $cp['credits_s1']) ?> / <?= lmdFormatCredits((float) $cp['credits_s2']) ?></span></td>
                            <td><?= (int) $cp['subjects_count'] ?></td>
                            <td><span class="badge badge-<?= $pct >= 100 ? 'success' : ($pct > 0 ? 'warning' : 'gray') ?>"><?= $pct ?> %</span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$classProgress): ?><tr><td colspan="4" class="text-center">Aucune filiere pour l'annee en cours.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header"><h3>Jurys de deliberation</h3><a href="juries.php" class="btn btn-outline btn-sm">Gerer</a></div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Filiere</th><th>Date</th><th>Statut</th></tr></thead>
                    <tbody>
                    <?php foreach ($juriesList as $j):
                        [$label, $badge] = $j['validated_at'] ? ['Valide par le DG', 'success'] : (['planifie' => ['Planifie', 'info'], 'en_cours' => ['En deliberation', 'warning'], 'cloture' => ['Transmis au DG', 'warning']][$j['status']] ?? [$j['status'], 'gray']); ?>
                        <tr>
                            <td><a href="jury.php?id=<?= (int) $j['id'] ?>"><?= e($j['class_name']) ?></a></td>
                            <td><?= formatDate($j['session_date']) ?></td>
                            <td><span class="badge badge-<?= $badge ?>"><?= $label ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$juriesList): ?><tr><td colspan="3" class="text-center">Aucun jury constitue.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p style="margin-top:14px;"><strong><?= $newAdmissions ?></strong> nouvelle(s) demande(s) d'admission - <a href="admission_requests.php">consulter</a></p>
        </div>
    </div>

<?php else: ?>
    <!-- ===================== DASHBOARD ECOLE (DG-PCA / Assistant) ===================== -->
    <?php if ($isGeneralDirection && $pendingJuries): ?>
        <div class="admin-card" style="border-left:4px solid var(--accent);">
            <div class="admin-card-header"><h3><i class="fa-solid fa-stamp"></i> Decisions en attente : <?= count($pendingJuries) ?> deliberation(s) a valider</h3></div>
            <ul style="display:flex;flex-direction:column;gap:8px;">
                <?php foreach ($pendingJuries as $pj): ?>
                    <li><a href="jury.php?id=<?= (int) $pj['id'] ?>"><strong><?= e($pj['class_name']) ?></strong> - <?= e($pj['name']) ?></a> <small style="color:var(--gray-500);">cloturee le <?= formatDate($pj['deliberated_at'], 'd/m/Y H:i') ?></small></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <div class="stat-cards">
        <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-user-graduate"></i></div><div><div class="stat-value"><?= $totalStudents ?></div><div class="stat-label">Etudiants actifs</div></div></div>
        <div class="stat-card card-orange"><div class="stat-icon"><i class="fa-solid fa-chalkboard-user"></i></div><div><div class="stat-value"><?= $totalTeachers ?></div><div class="stat-label">Enseignants actifs</div></div></div>
        <div class="stat-card card-green"><div class="stat-icon"><i class="fa-solid fa-money-bill-trend-up"></i></div><div><div class="stat-value"><?= formatMoney($totalCollected) ?></div><div class="stat-label">Paiements encaisses</div></div></div>
        <div class="stat-card card-red"><div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div><div><div class="stat-value"><?= formatMoney($totalDue) ?></div><div class="stat-label">Solde restant du</div></div></div>
    </div>

    <div class="grid grid-2">
        <div class="admin-card">
            <div class="admin-card-header"><h3>Dernieres inscriptions</h3><a href="students.php" class="btn btn-outline btn-sm">Voir tout</a></div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Etudiant</th><th>Matricule</th><th>Filiere</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentRegistrations as $r): ?>
                        <tr>
                            <td><?= sanitize($r['first_name'] . ' ' . $r['last_name']) ?></td>
                            <td><?= sanitize($r['matricule']) ?></td>
                            <td><?= sanitize($r['class_name']) ?></td>
                            <td><?= formatDate($r['registration_date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentRegistrations)): ?><tr><td colspan="4" class="text-center">Aucune inscription pour le moment.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header"><h3>Activite recente</h3></div>
            <ul style="display:flex;flex-direction:column;gap:16px;">
                <li style="display:flex;align-items:center;gap:12px;">
                    <span class="stat-icon" style="width:40px;height:40px;font-size:1rem;"><i class="fa-solid fa-envelope"></i></span>
                    <div><strong><?= $unreadMessages ?></strong> message(s) non lu(s) - <a href="contacts.php">consulter</a></div>
                </li>
                <li style="display:flex;align-items:center;gap:12px;">
                    <span class="stat-icon" style="width:40px;height:40px;font-size:1rem;background:#fef3e0;color:var(--accent);"><i class="fa-solid fa-user-check"></i></span>
                    <div><strong><?= $newAdmissions ?></strong> nouvelle(s) demande(s) d'admission - <a href="admission_requests.php">consulter</a></div>
                </li>
                <li style="display:flex;align-items:center;gap:12px;">
                    <span class="stat-icon" style="width:40px;height:40px;font-size:1rem;background:#e5f7ee;color:var(--success);"><i class="fa-solid fa-calendar-check"></i></span>
                    <div>Annee universitaire active : <strong><?= sanitize($academicYear['label'] ?? 'Non definie') ?></strong></div>
                </li>
            </ul>
            <div class="grid grid-2" style="margin-top:24px;gap:12px;">
                <a href="students.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Nouvel etudiant</a>
                <a href="payments.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-plus"></i> Nouveau paiement</a>
            </div>
        </div>
    </div>

    <?php if ($isGeneralDirection): ?>
    <div class="grid grid-2" style="margin-top:24px;">
        <div class="admin-card">
            <div class="admin-card-header"><h3>Resultats <?= e($academicYear['label'] ?? '') ?> par filiere</h3><a href="results.php" class="btn btn-outline btn-sm">Detail</a></div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Filiere</th><th>Etudiants</th><th>Reussite</th><th>Publication</th></tr></thead>
                    <tbody>
                    <?php foreach ($resultsByClass as $rc): $rate = (int) $rc['total'] > 0 ? 100 * (int) $rc['passed'] / (int) $rc['total'] : null; ?>
                        <tr>
                            <td><?= e($rc['name']) ?></td>
                            <td><?= (int) $rc['total'] ?></td>
                            <td><?= $rate === null ? '-' : number_format($rate, 1, ',', ' ') . ' %' ?></td>
                            <td><span class="badge badge-<?= $rc['published'] ? 'success' : 'gray' ?>"><?= $rc['published'] ? 'Publies' : 'Non publies' ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$resultsByClass): ?><tr><td colspan="4" class="text-center">Aucun resultat enregistre par le Directeur des etudes.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="admin-card">
            <div class="admin-card-header"><h3>Engagements financiers</h3></div>
            <ul style="display:flex;flex-direction:column;gap:16px;">
                <li><strong><?= formatMoney($teacherPayDue) ?></strong> de remunerations enseignants non soldees - <a href="teacher_payments.php">consulter</a></li>
                <li><strong><?= formatMoney($totalDue) ?></strong> de frais de scolarite restant a recouvrer - <a href="payments.php">consulter</a></li>
            </ul>
            <div class="grid grid-2" style="margin-top:24px;gap:12px;">
                <a href="users.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-users-gear"></i> Equipe de direction</a>
                <a href="juries.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-stamp"></i> Deliberations</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
