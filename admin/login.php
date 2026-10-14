<?php
require_once __DIR__ . '/auth.php';

if (currentUser()) {
    redirect(ADMIN_URL . '/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Session expiree, veuillez reessayer.';
    } else {
        $email    = sanitize($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if (!isValidEmail($email) || $password === '') {
            $error = 'Veuillez renseigner un email valide et votre mot de passe.';
        } else {
            $stmt = getDB()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $error = 'Identifiants incorrects.';
            } elseif ($user['status'] !== 'actif') {
                $error = 'Votre compte est desactive. Contactez un administrateur.';
            } else {
                loginUser($user);
                $update = getDB()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
                $update->execute([$user['id']]);
                redirect(ADMIN_URL . '/index.php');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion Admin | <?= SITE_NAME ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/svg+xml" href="<?= ASSETS_URL ?>/img/logo-mark.svg">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
</head>
<body class="admin-body">
<div class="login-page">
    <div class="login-box">
        <a href="<?= SITE_URL ?>/index.php" class="navbar-brand"><img src="<?= ASSETS_URL ?>/img/logo.png" alt="<?= SITE_NAME ?>" class="login-logo"></a>
        <p class="subtitle">Panel d'administration - Connexion securisee</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?= sanitize($error) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= csrfField() ?>
            <div class="form-group">
                <label for="email">Adresse email</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= sanitize($_POST['email'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Se connecter <i class="fa-solid fa-right-to-bracket"></i></button>
        </form>
        <p class="text-center mt-2" style="font-size:0.82rem;"><a href="<?= SITE_URL ?>/index.php" style="color:var(--primary);font-weight:600;">&larr; Retour au site</a></p>
    </div>
</div>
</body>
</html>
