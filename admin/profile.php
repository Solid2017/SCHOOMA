<?php
require_once __DIR__ . '/auth.php';
$user = requireLogin();
$db = getDB();
$pageTitle = 'Mon profil';
$breadcrumb = 'Administration / Profil';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect('profile.php');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'update_info') {
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        if ($name === '' || !isValidEmail($email)) {
            setFlash('error', 'Nom et email valides requis.');
        } else {
            $checkEmail = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $checkEmail->execute([$email, $user['id']]);
            if ($checkEmail->fetch()) {
                setFlash('error', 'Cet email est deja utilise par un autre compte.');
            } else {
                $stmt = $db->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
                $stmt->execute([$name, $email, $user['id']]);
                $_SESSION['admin_user']['name'] = $name;
                $_SESSION['admin_user']['email'] = $email;
                setFlash('success', 'Vos informations ont ete mises a jour.');
            }
        }
    } elseif ($action === 'update_password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($current, $row['password_hash'])) {
            setFlash('error', 'Mot de passe actuel incorrect.');
        } elseif (strlen($new) < 8) {
            setFlash('error', 'Le nouveau mot de passe doit contenir au moins 8 caracteres.');
        } elseif ($new !== $confirm) {
            setFlash('error', 'La confirmation ne correspond pas au nouveau mot de passe.');
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->execute([$hash, $user['id']]);
            setFlash('success', 'Mot de passe modifie avec succes.');
        }
    }
    redirect('profile.php');
}

$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

require_once __DIR__ . '/inc_header.php';
?>

<div class="grid grid-2">
    <div class="admin-card">
        <div class="admin-card-header"><h3>Informations personnelles</h3></div>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_info">
            <div class="form-group"><label>Nom complet *</label><input type="text" class="form-control" name="name" value="<?= sanitize($profile['name']) ?>" required></div>
            <div class="form-group"><label>Email *</label><input type="email" class="form-control" name="email" value="<?= sanitize($profile['email']) ?>" required></div>
            <div class="form-group"><label>Role</label><input type="text" class="form-control" value="<?= sanitize(roleLabel($profile['role'])) ?>" disabled></div>
            <button type="submit" class="btn btn-primary">Mettre a jour</button>
        </form>
    </div>

    <div class="admin-card">
        <div class="admin-card-header"><h3>Changer le mot de passe</h3></div>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_password">
            <div class="form-group"><label>Mot de passe actuel *</label><input type="password" class="form-control" name="current_password" required></div>
            <div class="form-group"><label>Nouveau mot de passe * (min. 8 caracteres)</label><input type="password" class="form-control" name="new_password" minlength="8" required></div>
            <div class="form-group"><label>Confirmer le nouveau mot de passe *</label><input type="password" class="form-control" name="confirm_password" minlength="8" required></div>
            <button type="submit" class="btn btn-primary">Changer le mot de passe</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
