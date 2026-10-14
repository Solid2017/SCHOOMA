<?php
require_once __DIR__ . '/auth.php';
$user = requireLogin();
$db = getDB();
$clientId = scopeClientId();
$pageTitle = 'Tableau de bord';
$breadcrumb = 'Vue globale de votre activite';

if ($clientId === null) {
    // ---- Vue Super-admin SaaS : statistiques globales plateforme ----
    $totalClients   = (int) $db->query('SELECT COUNT(*) FROM clients')->fetchColumn();
    $activeClients  = (int) $db->query('SELECT COUNT(*) FROM clients WHERE status = "actif"')->fetchColumn();
    $totalRevenue   = (float) $db->query('SELECT COALESCE(SUM(amount),0) FROM invoices WHERE status = "payee"')->fetchColumn();
    $pendingInvoices = (int) $db->query('SELECT COUNT(*) FROM invoices WHERE status IN ("en_attente","en_retard")')->fetchColumn();

    $recentClients = $db->query('SELECT c.*, p.name AS plan_name FROM clients c LEFT JOIN subscription_plans p ON p.id = c.plan_id ORDER BY c.created_at DESC LIMIT 6')->fetchAll();
    $recentInvoices = $db->query('SELECT i.*, c.name AS client_name FROM invoices i JOIN clients c ON c.id = i.client_id ORDER BY i.created_at DESC LIMIT 6')->fetchAll();
} else {
    $academicYear = getCurrentAcademicYear($clientId);
    $ayId = $academicYear['id'] ?? 0;

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

<?php else: ?>
    <!-- ===================== DASHBOARD ECOLE (Admin/Assistant) ===================== -->
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
<?php endif; ?>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
