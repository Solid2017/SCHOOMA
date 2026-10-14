<?php
require_once __DIR__ . '/auth.php';
requireRole([ROLE_SUPER_ADMIN]);
$db = getDB();
$pageTitle = 'Ecoles clientes';
$breadcrumb = 'Gestion SaaS / Ecoles';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect('clients.php');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $planId = (int) ($_POST['plan_id'] ?? 0) ?: null;
        $status = in_array($_POST['status'] ?? '', ['actif', 'suspendu', 'essai', 'resilie'], true) ? $_POST['status'] : 'essai';

        if ($name === '' || !isValidEmail($email)) {
            setFlash('error', 'Nom et email valides requis.');
            redirect('clients.php');
        }

        if ($id > 0) {
            $stmt = $db->prepare('UPDATE clients SET name=?, email=?, phone=?, address=?, plan_id=?, status=? WHERE id=?');
            $stmt->execute([$name, $email, $phone, $address, $planId, $status, $id]);
            setFlash('success', 'Ecole mise a jour avec succes.');
        } else {
            $slug = slugify($name) . '-' . substr(md5(uniqid('', true)), 0, 5);
            $stmt = $db->prepare('INSERT INTO clients (name, slug, email, phone, address, plan_id, status, trial_ends_at) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->execute([$name, $slug, $email, $phone, $address, $planId, $status, date('Y-m-d', strtotime('+30 days'))]);
            $newClientId = (int) $db->lastInsertId();

            // Creation automatique du compte administrateur de l'ecole
            $adminName = sanitize($_POST['admin_name'] ?? ($name . ' Admin'));
            $adminEmail = sanitize($_POST['admin_email'] ?? $email);
            $tempPassword = bin2hex(random_bytes(4));
            $hash = password_hash($tempPassword, PASSWORD_BCRYPT);

            $checkEmail = $db->prepare('SELECT id FROM users WHERE email = ?');
            $checkEmail->execute([$adminEmail]);
            if (!$checkEmail->fetch()) {
                $stmt = $db->prepare('INSERT INTO users (client_id, name, email, password_hash, role, status) VALUES (?,?,?,?,?,"actif")');
                $stmt->execute([$newClientId, $adminName, $adminEmail, $hash, ROLE_ADMIN_ECOLE]);
                setFlash('success', "Ecole creee avec succes. Compte admin : $adminEmail / mot de passe temporaire : $tempPassword");
            } else {
                setFlash('success', 'Ecole creee avec succes. (Un utilisateur existait deja avec cet email, aucun compte admin cree automatiquement.)');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM clients WHERE id = ?');
        $stmt->execute([$id]);
        setFlash('success', 'Ecole supprimee.');
    }
    redirect('clients.php');
}

$search = sanitize($_GET['q'] ?? '');
$sql = 'SELECT c.*, p.name AS plan_name FROM clients c LEFT JOIN subscription_plans p ON p.id = c.plan_id WHERE 1=1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (c.name LIKE ? OR c.email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= ' ORDER BY c.created_at DESC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

$plans = $db->query('SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY price ASC')->fetchAll();

require_once __DIR__ . '/inc_header.php';
?>

<div class="admin-content-header">
    <div><h2>Ecoles clientes</h2></div>
    <button class="btn btn-primary" data-modal-open="clientModal" onclick="resetClientForm()"><i class="fa-solid fa-plus"></i> Nouvelle ecole</button>
</div>

<div class="admin-card">
    <form class="filters-bar" method="get">
        <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="text" class="form-control" name="q" placeholder="Rechercher une ecole..." value="<?= sanitize($search) ?>"></div>
        <button class="btn btn-outline btn-sm" type="submit">Filtrer</button>
    </form>

    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Ecole</th><th>Email</th><th>Plan</th><th>Statut</th><th>Inscrite le</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($clients as $c): ?>
                <tr>
                    <td><strong><?= sanitize($c['name']) ?></strong></td>
                    <td><?= sanitize($c['email']) ?></td>
                    <td><?= sanitize($c['plan_name'] ?? '-') ?></td>
                    <td><span class="badge badge-<?= $c['status'] === 'actif' ? 'success' : ($c['status'] === 'essai' ? 'info' : ($c['status'] === 'suspendu' ? 'warning' : 'danger')) ?>"><?= sanitize($c['status']) ?></span></td>
                    <td><?= formatDate($c['created_at']) ?></td>
                    <td class="action-icons">
                        <button class="edit" title="Modifier" onclick='editClient(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fa-solid fa-pen"></i></button>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer cette ecole et toutes ses donnees ?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <button type="submit" class="delete" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($clients)): ?><tr><td colspan="6" class="text-center">Aucune ecole trouvee.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="clientModal">
    <div class="modal-box">
        <form method="post">
            <div class="modal-header"><h3 id="clientModalTitle">Nouvelle ecole</h3><button type="button" class="modal-close" data-modal-close>&times;</button></div>
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="client_id" value="">
                <div class="form-group"><label>Nom de l'ecole *</label><input type="text" class="form-control" name="name" id="client_name" required></div>
                <div class="form-group"><label>Email de contact *</label><input type="email" class="form-control" name="email" id="client_email" required></div>
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group"><label>Telephone</label><input type="text" class="form-control" name="phone" id="client_phone"></div>
                    <div class="form-group"><label>Plan</label>
                        <select class="form-control" name="plan_id" id="client_plan_id">
                            <option value="">-- Aucun --</option>
                            <?php foreach ($plans as $p): ?><option value="<?= (int) $p['id'] ?>"><?= sanitize($p['name']) ?> (<?= formatMoney($p['price']) ?>)</option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group"><label>Adresse</label><input type="text" class="form-control" name="address" id="client_address"></div>
                <div class="form-group"><label>Statut</label>
                    <select class="form-control" name="status" id="client_status">
                        <option value="essai">Essai</option>
                        <option value="actif">Actif</option>
                        <option value="suspendu">Suspendu</option>
                        <option value="resilie">Resilie</option>
                    </select>
                </div>
                <div id="clientAdminFields">
                    <hr style="margin:18px 0;border-color:var(--gray-300);">
                    <p style="font-size:0.85rem;color:var(--gray-500);margin-bottom:12px;">Un compte administrateur d'ecole sera cree automatiquement avec un mot de passe temporaire.</p>
                    <div class="form-group"><label>Nom de l'administrateur</label><input type="text" class="form-control" name="admin_name" placeholder="Ex: Directeur des etudes"></div>
                    <div class="form-group"><label>Email de l'administrateur</label><input type="email" class="form-control" name="admin_email" placeholder="Laisser vide pour reutiliser l'email de contact"></div>
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
function resetClientForm() {
    document.getElementById('clientModalTitle').textContent = 'Nouvelle ecole';
    document.getElementById('client_id').value = '';
    document.getElementById('client_name').value = '';
    document.getElementById('client_email').value = '';
    document.getElementById('client_phone').value = '';
    document.getElementById('client_plan_id').value = '';
    document.getElementById('client_address').value = '';
    document.getElementById('client_status').value = 'essai';
    document.getElementById('clientAdminFields').style.display = 'block';
}
function editClient(c) {
    document.getElementById('clientModalTitle').textContent = "Modifier l'ecole";
    document.getElementById('client_id').value = c.id;
    document.getElementById('client_name').value = c.name;
    document.getElementById('client_email').value = c.email;
    document.getElementById('client_phone').value = c.phone || '';
    document.getElementById('client_plan_id').value = c.plan_id || '';
    document.getElementById('client_address').value = c.address || '';
    document.getElementById('client_status').value = c.status;
    document.getElementById('clientAdminFields').style.display = 'none';
    document.getElementById('clientModal').classList.add('open');
}
</script>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
