<?php
require_once __DIR__ . '/auth.php';
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE]);
$db = getDB();
$user = currentUser();
$clientId = scopeClientId();
$pageTitle = 'Utilisateurs & roles';
$breadcrumb = 'Administration / Utilisateurs';

// Roles qu'un administrateur d'ecole a le droit d'assigner (jamais super_admin)
$assignableRoles = $clientId === null ? [ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE, ROLE_ASSISTANT] : [ROLE_ADMIN_ECOLE, ROLE_ASSISTANT];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect('users.php');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $role = in_array($_POST['role'] ?? '', $assignableRoles, true) ? $_POST['role'] : $assignableRoles[0];
        $status = in_array($_POST['status'] ?? '', ['actif', 'inactif'], true) ? $_POST['status'] : 'actif';
        $targetClientId = $clientId ?? ((int) ($_POST['client_id'] ?? 0) ?: null);

        if ($name === '' || !isValidEmail($email)) {
            setFlash('error', 'Veuillez renseigner un nom et un email valides.');
            redirect('users.php');
        }

        if ($id > 0) {
            // Empeche un admin ecole de modifier un utilisateur hors de son ecole
            if ($clientId !== null) {
                $check = $db->prepare('SELECT client_id FROM users WHERE id = ?');
                $check->execute([$id]);
                $target = $check->fetch();
                if (!$target || (int) $target['client_id'] !== $clientId) {
                    setFlash('error', 'Action non autorisee.');
                    redirect('users.php');
                }
            }
            $stmt = $db->prepare('UPDATE users SET name=?, email=?, role=?, status=? WHERE id=?');
            $stmt->execute([$name, $email, $role, $status, $id]);
            setFlash('success', 'Utilisateur mis a jour avec succes.');
        } else {
            $checkEmail = $db->prepare('SELECT id FROM users WHERE email = ?');
            $checkEmail->execute([$email]);
            if ($checkEmail->fetch()) {
                setFlash('error', 'Cet email est deja utilise.');
                redirect('users.php');
            }
            $tempPassword = bin2hex(random_bytes(4));
            $hash = password_hash($tempPassword, PASSWORD_BCRYPT);
            $stmt = $db->prepare('INSERT INTO users (client_id, name, email, password_hash, role, status) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$targetClientId, $name, $email, $hash, $role, $status]);
            setFlash('success', "Utilisateur cree. Mot de passe temporaire : $tempPassword (a communiquer de maniere securisee)");
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) $user['id']) {
            setFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            redirect('users.php');
        }
        $sql = 'DELETE FROM users WHERE id = ?';
        $params = [$id];
        if ($clientId !== null) {
            $sql .= ' AND client_id = ?';
            $params[] = $clientId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        setFlash('success', 'Utilisateur supprime.');
    } elseif ($action === 'reset_password') {
        $id = (int) ($_POST['id'] ?? 0);
        $tempPassword = bin2hex(random_bytes(4));
        $hash = password_hash($tempPassword, PASSWORD_BCRYPT);
        $sql = 'UPDATE users SET password_hash = ? WHERE id = ?';
        $params = [$hash, $id];
        if ($clientId !== null) {
            $sql .= ' AND client_id = ?';
            $params[] = $clientId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        setFlash('success', "Mot de passe reinitialise : $tempPassword");
    }
    redirect('users.php');
}

$sql = 'SELECT u.*, c.name AS client_name FROM users u LEFT JOIN clients c ON c.id = u.client_id WHERE 1=1';
$params = [];
if ($clientId !== null) {
    $sql .= ' AND u.client_id = ?';
    $params[] = $clientId;
}
$sql .= ' ORDER BY u.created_at DESC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$clientsList = $clientId === null ? $db->query('SELECT id, name FROM clients ORDER BY name ASC')->fetchAll() : [];

require_once __DIR__ . '/inc_header.php';
?>

<div class="admin-content-header">
    <div><h2>Utilisateurs & roles</h2></div>
    <button class="btn btn-primary" data-modal-open="userModal" onclick="resetUserForm()"><i class="fa-solid fa-plus"></i> Nouvel utilisateur</button>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Nom</th><th>Email</th><th>Role</th><?php if ($clientId === null): ?><th>Ecole</th><?php endif; ?><th>Statut</th><th>Derniere connexion</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?= sanitize($u['name']) ?></strong></td>
                    <td><?= sanitize($u['email']) ?></td>
                    <td><span class="badge badge-info"><?= sanitize(roleLabel($u['role'])) ?></span></td>
                    <?php if ($clientId === null): ?><td><?= sanitize($u['client_name'] ?? 'Plateforme SCHOOMA') ?></td><?php endif; ?>
                    <td><span class="badge badge-<?= $u['status'] === 'actif' ? 'success' : 'gray' ?>"><?= sanitize($u['status']) ?></span></td>
                    <td><?= $u['last_login'] ? formatDate($u['last_login'], 'd/m/Y H:i') : 'Jamais' ?></td>
                    <td class="action-icons">
                        <button class="edit" title="Modifier" onclick='editUser(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fa-solid fa-pen"></i></button>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Reinitialiser le mot de passe de cet utilisateur ?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="reset_password">
                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <button type="submit" class="view" title="Reinitialiser le mot de passe"><i class="fa-solid fa-key"></i></button>
                        </form>
                        <?php if ((int) $u['id'] !== (int) $user['id']): ?>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer cet utilisateur ?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <button type="submit" class="delete" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($users)): ?><tr><td colspan="7" class="text-center">Aucun utilisateur trouve.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="userModal">
    <div class="modal-box">
        <form method="post">
            <div class="modal-header"><h3 id="userModalTitle">Nouvel utilisateur</h3><button type="button" class="modal-close" data-modal-close>&times;</button></div>
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="user_id" value="">
                <div class="form-group"><label>Nom complet *</label><input type="text" class="form-control" name="name" id="user_name" required></div>
                <div class="form-group"><label>Email *</label><input type="email" class="form-control" name="email" id="user_email" required></div>
                <div class="form-group"><label>Role *</label>
                    <select class="form-control" name="role" id="user_role" required>
                        <?php foreach ($assignableRoles as $r): ?><option value="<?= $r ?>"><?= sanitize(roleLabel($r)) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <?php if ($clientId === null): ?>
                <div class="form-group"><label>Ecole (laisser vide pour le Super Admin SaaS)</label>
                    <select class="form-control" name="client_id" id="user_client_id">
                        <option value="">-- Plateforme SCHOOMA --</option>
                        <?php foreach ($clientsList as $c): ?><option value="<?= (int) $c['id'] ?>"><?= sanitize($c['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="form-group"><label>Statut</label>
                    <select class="form-control" name="status" id="user_status">
                        <option value="actif">Actif</option>
                        <option value="inactif">Inactif</option>
                    </select>
                </div>
                <p style="font-size:0.82rem;color:var(--gray-500);">Un mot de passe temporaire sera genere automatiquement a la creation.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetUserForm() {
    document.getElementById('userModalTitle').textContent = 'Nouvel utilisateur';
    document.getElementById('user_id').value = '';
    document.getElementById('user_name').value = '';
    document.getElementById('user_email').value = '';
    document.getElementById('user_status').value = 'actif';
}
function editUser(u) {
    document.getElementById('userModalTitle').textContent = "Modifier l'utilisateur";
    document.getElementById('user_id').value = u.id;
    document.getElementById('user_name').value = u.name;
    document.getElementById('user_email').value = u.email;
    document.getElementById('user_role').value = u.role;
    document.getElementById('user_status').value = u.status;
    var clientSelect = document.getElementById('user_client_id');
    if (clientSelect) clientSelect.value = u.client_id || '';
    document.getElementById('userModal').classList.add('open');
}
</script>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
