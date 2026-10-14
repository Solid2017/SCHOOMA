<?php
/**
 * SCHOOMA - Header du site public (nav + preloader + SEO meta)
 * Variables optionnelles attendues avant l'inclusion :
 * $pageTitle, $pageDescription, $pageKeywords, $canonicalUrl, $ogImage
 */
$pageTitle       = $pageTitle ?? SITE_NAME . ' | ' . SITE_TAGLINE;
$pageDescription = $pageDescription ?? 'SCHOOMA est la plateforme SaaS qui digitalise la gestion des services universitaires : inscriptions, paiements par tranches, enseignants, emplois du temps, notes et resultats annuels.';
$pageKeywords    = $pageKeywords ?? 'SCHOOMA, gestion universitaire, SaaS ecole, inscription etudiant, logiciel scolarite, gestion des notes, emploi du temps universite';
$canonicalUrl    = $canonicalUrl ?? SITE_URL . $_SERVER['REQUEST_URI'];
$ogImage         = $ogImage ?? ASSETS_URL . '/img/og-cover.jpg';
$currentPage     = basename($_SERVER['SCRIPT_NAME']);
?><!DOCTYPE html>
<html lang="<?= SITE_LANG ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= sanitize($pageTitle) ?></title>
<meta name="description" content="<?= sanitize($pageDescription) ?>">
<meta name="keywords" content="<?= sanitize($pageKeywords) ?>">
<meta name="robots" content="index, follow">
<meta name="author" content="<?= SITE_NAME ?>">
<link rel="canonical" href="<?= sanitize($canonicalUrl) ?>">

<!-- Open Graph -->
<meta property="og:type" content="website">
<meta property="og:title" content="<?= sanitize($pageTitle) ?>">
<meta property="og:description" content="<?= sanitize($pageDescription) ?>">
<meta property="og:url" content="<?= sanitize($canonicalUrl) ?>">
<meta property="og:image" content="<?= sanitize($ogImage) ?>">
<meta property="og:site_name" content="<?= SITE_NAME ?>">
<meta property="og:locale" content="fr_FR">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= sanitize($pageTitle) ?>">
<meta name="twitter:description" content="<?= sanitize($pageDescription) ?>">
<meta name="twitter:image" content="<?= sanitize($ogImage) ?>">

<!-- Favicon -->
<link rel="icon" type="image/svg+xml" href="<?= ASSETS_URL ?>/img/logo-mark.svg">

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">

<!-- FontAwesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- AOS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.1/aos.css">

<!-- Styles -->
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">

<!-- Structured data (SEO) -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "EducationalOrganization",
  "name": "<?= SITE_NAME ?>",
  "url": "<?= SITE_URL ?>",
  "description": "<?= sanitize($pageDescription) ?>",
  "email": "<?= SITE_EMAIL ?>",
  "telephone": "<?= SITE_PHONE ?>"
}
</script>
</head>
<body>

<!-- Preloader -->
<div id="preloader" aria-hidden="true">
    <img src="<?= ASSETS_URL ?>/img/logo-white.svg" alt="<?= SITE_NAME ?>" class="loader-logo-img">
    <div class="loader-bar"><span></span></div>
</div>

<!-- Skip to content (accessibilite) -->
<a href="#main-content" class="skip-link">Aller au contenu principal</a>

<!-- ===== NAVBAR ===== -->
<header class="site-header" id="siteHeader">
    <nav class="navbar container">
        <a href="<?= SITE_URL ?>/index.php" class="navbar-brand" aria-label="<?= SITE_NAME ?> - Accueil">
            <img src="<?= ASSETS_URL ?>/img/logo.png" alt="<?= SITE_NAME ?>" class="navbar-logo">
        </a>

        <button class="navbar-toggle" id="navToggle" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="navMenu">
            <span></span><span></span><span></span>
        </button>

        <ul class="navbar-menu" id="navMenu">
            <li><a href="<?= SITE_URL ?>/index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">Accueil</a></li>
            <li><a href="<?= SITE_URL ?>/a-propos.php" class="<?= $currentPage === 'a-propos.php' ? 'active' : '' ?>">A propos</a></li>
            <li><a href="<?= SITE_URL ?>/programmes.php" class="<?= $currentPage === 'programmes.php' ? 'active' : '' ?>">Programmes</a></li>
            <li><a href="<?= SITE_URL ?>/blog.php" class="<?= in_array($currentPage, ['blog.php', 'blog-article.php']) ? 'active' : '' ?>">Actualites</a></li>
            <li><a href="<?= SITE_URL ?>/contact.php" class="<?= $currentPage === 'contact.php' ? 'active' : '' ?>">Contact</a></li>
            <li class="nav-cta-item">
                <a href="<?= SITE_URL ?>/admissions.php" class="btn btn-primary btn-sm">Demander une admission</a>
            </li>
            <li class="nav-cta-item">
                <a href="<?= ADMIN_URL ?>/login.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-right-to-bracket"></i> Espace Admin</a>
            </li>
        </ul>
    </nav>
</header>

<div class="nav-overlay" id="navOverlay"></div>

<main id="main-content">
