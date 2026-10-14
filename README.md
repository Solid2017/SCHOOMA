# SCHOOMA

Plateforme SaaS de gestion des services universitaires (inscriptions, paiements par tranches, enseignants, emplois du temps, notes, resultats annuels) avec panel d'administration multi-etablissements.

- **Backend** : PHP 8+ (sans framework), PDO + requetes preparees
- **Frontend** : JavaScript vanilla, CSS custom, AOS.js, FontAwesome, Google Fonts (Montserrat + Playfair Display)
- **Base de donnees** : MySQL / MariaDB

## Deploiement sur hebergement mutualise (LWS)

1. Creez une base de donnees MySQL depuis le panel LWS et importez `database.sql` (phpMyAdmin ou ligne de commande).
2. Renseignez les identifiants de connexion dans `includes/config.php` (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).
3. Verifiez `SITE_URL` / `SITE_DOMAIN` dans `includes/config.php` (par defaut `schooma.igotech.tech`).
4. Deposez l'integralite des fichiers a la racine du domaine via FTP (le fichier `.htaccess` fourni protege les dossiers sensibles).
5. Passez `display_errors` a `0` (deja fait) et testez la connexion admin.

## Comptes par defaut (a changer immediatement)

| Role | Email | Mot de passe |
|---|---|---|
| Super Administrateur SaaS | superadmin@schooma.app | Admin@2026 |
| Administrateur Ecole (demo) | admin@schooma.igotech.tech | Admin@2026 |

Changez ces mots de passe depuis **Admin > Mon profil** des la premiere connexion.

## Structure du projet

```
/
├── index.php, a-propos.php, programmes.php, admissions.php, contact.php, blog.php, blog-article.php, resultats.php
├── sitemap.php, robots.txt, .htaccess
├── includes/        config.php (BDD + constantes), functions.php, header.php, footer.php
├── admin/           auth.php, login.php, layout (inc_header/inc_footer/inc_sidebar), index.php (dashboard) + un module par entite
├── api/             endpoints JSON publics (contact, newsletter, admissions)
├── assets/          css/style.css, js/main.js, uploads/
└── database.sql     script d'initialisation complet (schema + donnees de demo)
```

## Roles

- **Super Administrateur SaaS** : gere les ecoles clientes, les plans d'abonnement et la facturation.
- **Administrateur Ecole** : gere l'integralite des donnees academiques de son etablissement.
- **Assistant** : acces limite (inscriptions, paiements, emploi du temps, notes).
