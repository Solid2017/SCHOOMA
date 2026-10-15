<?php
/**
 * SCHOOMA Admin - Header commun (a inclure apres auth.php + requireLogin/Role)
 * Variables attendues : $pageTitle, $breadcrumb (optionnel)
 */
$user = currentUser();
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$currentPage = ['jury.php' => 'juries.php'][$currentPage] ?? $currentPage;
$pageTitle = $pageTitle ?? 'Tableau de bord';

$navGroups = [
    'Pilotage' => [
        ['icon' => 'fa-gauge-high', 'label' => 'Tableau de bord', 'href' => 'index.php', 'roles' => [ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE, ROLE_ASSISTANT]],
    ],
    'Gestion SaaS' => [
        ['icon' => 'fa-building', 'label' => 'Ecoles clientes', 'href' => 'clients.php', 'roles' => [ROLE_SUPER_ADMIN]],
        ['icon' => 'fa-layer-group', 'label' => 'Plans d\'abonnement', 'href' => 'plans.php', 'roles' => [ROLE_SUPER_ADMIN]],
        ['icon' => 'fa-file-invoice-dollar', 'label' => 'Facturation', 'href' => 'invoices.php', 'roles' => [ROLE_SUPER_ADMIN]],
    ],
    'Structure academique' => [
        ['icon' => 'fa-calendar-week', 'label' => 'Annees universitaires', 'href' => 'academic_years.php', 'roles' => [ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE]],
        ['icon' => 'fa-sitemap', 'label' => 'Filieres / Classes', 'href' => 'classes.php', 'roles' => [ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE]],
        ['icon' => 'fa-layer-group', 'label' => 'Unites d\'enseignement', 'href' => 'teaching_units.php', 'roles' => [ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE]],
        ['icon' => 'fa-book', 'label' => 'Matieres (ECUE)', 'href' => 'subjects.php', 'roles' => [ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE]],
    ],
    'Scolarite' => [
        ['icon' => 'fa-user-graduate', 'label' => 'Etudiants & Inscriptions', 'href' => 'students.php', 'roles' => [ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE, ROLE_ASSISTANT]],
        ['icon' => 'fa-money-check-dollar', 'label' => 'Paiements (tranches)', 'href' => 'payments.php', 'roles' => [ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE, ROLE_ASSISTANT]],
    ],
    'Enseignants' => [
        ['icon' => 'fa-chalkboard-user', 'label' => 'Enseignants', 'href' => 'teachers.php', 'roles' => [ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE]],
        ['icon' => 'fa-sack-dollar', 'label' => 'Paiements enseignants', 'href' => 'teacher_payments.php', 'roles' => [ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE]],
    ],
    'Pedagogie' => [
        ['icon' => 'fa-calendar-days', 'label' => 'Emploi du temps', 'href' => 'schedule.php', 'roles' => [ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE, ROLE_ASSISTANT]],
        ['icon' => 'fa-marker', 'label' => 'Notes', 'href' => 'grades.php', 'roles' => [ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE, ROLE_ASSISTANT]],
        ['icon' => 'fa-award', 'label' => 'Resultats LMD & releves', 'href' => 'results.php', 'roles' => [ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE]],
        ['icon' => 'fa-users-rectangle', 'label' => 'Jurys de deliberation', 'href' => 'juries.php', 'roles' => [ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE]],
    ],
    'Site web' => [
        ['icon' => 'fa-envelope-open-text', 'label' => 'Messages', 'href' => 'contacts.php', 'roles' => [ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE]],
        ['icon' => 'fa-user-check', 'label' => 'Demandes d\'admission', 'href' => 'admission_requests.php', 'roles' => [ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE, ROLE_ASSISTANT]],
        ['icon' => 'fa-paper-plane', 'label' => 'Newsletter', 'href' => 'newsletter.php', 'roles' => [ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE]],
        ['icon' => 'fa-newspaper', 'label' => 'Blog / Actualites', 'href' => 'blog.php', 'roles' => [ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE]],
        ['icon' => 'fa-pen-to-square', 'label' => 'Contenu du site', 'href' => 'content.php', 'roles' => [ROLE_SUPER_ADMIN]],
    ],
    'Administration' => [
        ['icon' => 'fa-users-gear', 'label' => 'Utilisateurs & roles', 'href' => 'users.php', 'roles' => [ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE]],
        ['icon' => 'fa-key', 'label' => 'Mon profil', 'href' => 'profile.php', 'roles' => [ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE, ROLE_ASSISTANT]],
    ],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= sanitize($pageTitle) ?> | Admin <?= SITE_NAME ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/svg+xml" href="<?= ASSETS_URL ?>/img/logo-mark.svg">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
</head>
<body class="admin-body">
<div class="admin-wrapper">

    <aside class="admin-sidebar" id="adminSidebar">
        <a href="<?= ADMIN_URL ?>/index.php" class="admin-sidebar-brand">
            <img src="<?= ASSETS_URL ?>/img/logo-white.svg" alt="<?= SITE_NAME ?>" class="sidebar-logo">
        </a>
        <nav class="admin-nav">
            <?php foreach ($navGroups as $groupLabel => $items):
                $visibleItems = array_filter($items, fn($item) => in_array($user['role'], $item['roles'], true));
                if (empty($visibleItems)) continue;
            ?>
                <div class="admin-nav-section"><?= sanitize($groupLabel) ?></div>
                <?php foreach ($visibleItems as $item): ?>
                    <a href="<?= ADMIN_URL ?>/<?= $item['href'] ?>" class="<?= $currentPage === $item['href'] ? 'active' : '' ?>">
                        <i class="fa-solid <?= $item['icon'] ?>"></i> <?= sanitize($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
            <div class="admin-nav-section">&nbsp;</div>
            <a href="<?= ADMIN_URL ?>/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Deconnexion</a>
        </nav>
    </aside>

    <div class="nav-overlay" id="sidebarOverlay"></div>

    <div class="admin-main">
        <header class="admin-topbar">
            <div class="admin-topbar-left">
                <button class="sidebar-toggle" id="sidebarToggle" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
                <div>
                    <h1><?= sanitize($pageTitle) ?></h1>
                    <?php if (!empty($breadcrumb)): ?><div class="admin-breadcrumb"><?= sanitize($breadcrumb) ?></div><?php endif; ?>
                </div>
            </div>
            <div class="admin-user-menu">
                <div>
                    <strong style="font-size:0.88rem;display:block;"><?= sanitize($user['name']) ?></strong>
                    <span style="font-size:0.75rem;color:var(--gray-500);"><?= sanitize(roleLabel($user['role'])) ?></span>
                </div>
                <button class="avatar" id="userMenuToggle" aria-label="Menu utilisateur"><?= sanitize(strtoupper(substr($user['name'], 0, 1))) ?></button>
                <div class="admin-user-dropdown" id="userDropdown">
                    <a href="<?= ADMIN_URL ?>/profile.php"><i class="fa-solid fa-user"></i> Mon profil</a>
                    <a href="<?= SITE_URL ?>/index.php" target="_blank"><i class="fa-solid fa-globe"></i> Voir le site</a>
                    <a href="<?= ADMIN_URL ?>/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Deconnexion</a>
                </div>
            </div>
        </header>

        <div class="admin-content">
            <?php $flash = getFlash(); ?>
            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?>">
                    <i class="fa-solid fa-circle-info"></i> <?= sanitize($flash['message']) ?>
                </div>
            <?php endif; ?>
