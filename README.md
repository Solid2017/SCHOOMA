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
| Directeur General / PCA (ecole demo) | admin@schooma.igotech.tech | Admin@2026 |
| Directeur des etudes (ecole demo) | de@schooma.igotech.tech | Admin@2026 |

Changez ces mots de passe depuis **Admin > Mon profil** des la premiere connexion.

## Structure du projet

```
/
├── index.php, a-propos.php, programmes.php, admissions.php, contact.php, blog.php, blog-article.php, resultats.php
├── sitemap.php, robots.txt, .htaccess
├── includes/        config.php (BDD + constantes), functions.php, lmd.php (moteur LMD), lmd_view.php (releves), header.php, footer.php
├── admin/           auth.php, login.php, layout (inc_header/inc_footer/inc_sidebar), index.php (dashboard) + un module par entite
├── api/             endpoints JSON publics (contact, newsletter, admissions)
├── assets/          css/style.css, js/main.js, uploads/
├── database/        migrations/, seeds par ecole cliente, generateur
└── database.sql     script d'initialisation complet (schema + donnees de demo)
```

## Mise a jour d'une installation existante (systeme LMD)

Une base deja en production (installee avant le module LMD) doit recevoir **une seule fois** la migration :

1. Sauvegardez la base (phpMyAdmin > Exporter).
2. Importez, dans l'ordre et **une seule fois chacune**, les migrations non encore appliquees :
   - `database/migrations/001_lmd.sql` : unites d'enseignement, credits, resultats LMD, jurys enrichis ;
   - `database/migrations/002_roles.sql` : roles DG / PCA et Directeur des etudes, validation des deliberations.
3. Deposez les nouveaux fichiers **sans ecraser `includes/config.php`** (il contient vos identifiants de production).

Une installation neuve via `database.sql` contient deja ce schema : ne pas executer les migrations.

## Systeme LMD (60 credits par an)

- **Maquette** : chaque filiere est organisee en semestres de 30 credits, eux-memes composes d'unites d'enseignement (UE : fondamentale, methodologique, transversale, decouverte) regroupant des elements constitutifs (ECUE / matieres) dotes de credits et d'un coefficient. Gestion : *Admin > Unites d'enseignement* puis *Matieres (ECUE)*.
- **Notes** : saisie en grille par matiere (CC, examen, rattrapage). Note ECUE = CC x 40 % + Examen x 60 % ; la note de rattrapage remplace l'examen si elle est plus favorable (marquee `*`).
- **Validation** : une UE est validee si sa moyenne ponderee >= 10 (V), ou par compensation si la moyenne du semestre >= 10 (VC). Compensation annuelle : moyenne annuelle (+ points de jury) >= 10 => 60 credits acquis.
- **Decisions** : Admis(e) (60 credits), Admis(e) avec dettes (>= 45 credits), Ajourne(e) ; Redoublement / Exclusion sur decision du jury. Mentions Passable a Tres Bien.
- **Releves de notes** imprimables (A4) par etudiant ou par filiere, semestriels ou annuels : *Admin > Resultats LMD & releves*. Les etudiants consultent leur releve publie sur `resultats.php` (matricule + date de naissance).
- **Jurys de deliberation** : *Admin > Jurys de deliberation*. Composition designee (president, vice-president, secretaire, rapporteur, membres enseignants ou personnalites exterieures), saisie des points de jury (max. 1 point) et des decisions, cloture puis validation et publication par le DG / PCA (voir *Circuit des deliberations*), **proces-verbal** imprimable (A4 paysage) signe par les membres.
- Les seuils (ponderation CC, compensation, credits, points de jury) sont des constantes surchargeables dans `includes/config.php` (voir `includes/lmd.php`).

## Donnees de test par ecole cliente

Le dossier `database/` contient des scripts a importer **apres** `database.sql` (ou apres la migration) pour peupler une ecole cliente avec des donnees de demonstration realistes. Chaque script **supprime puis recree** l'ecole concernee (et uniquement elle) : il peut etre relance sans doublon, mais toute saisie faite entre-temps sur cette ecole sera perdue.

- `database/seed_shimt.sql` : institut **SHIMT** — 10 filieres de Licence 1 avec maquette LMD complete (120 UE, 220 ECUE, 2 x 30 credits par filiere), 12 enseignants, 40 etudiants, notes CC/examen/rattrapage, paiements, un jury par filiere. Comptes : DG `admin@shimt-institute.com` / `Shimt@2026`, Directeur des etudes `de@shimt-institute.com` / `DeShimt@2026`.
- `database/seed_iisan.sql` : **IISAN N'Djamena** (Institut International des Sciences et des Arts du Numerique) — 5 filieres numeriques (60 UE, 110 ECUE), 10 enseignants, 20 etudiants, notes, paiements et jurys. Comptes : DG `admin@iisan.td` / `Iisan@2026`, Directeur des etudes `de@iisan.td` / `DeIisan@2026`. Informations institutionnelles a verifier aupres de l'etablissement.
- `database/generate_seeds.php` : generateur (ligne de commande uniquement) de ces deux scripts : `php database/generate_seeds.php`.

Changez les mots de passe des comptes de demonstration des la premiere connexion.

## Roles

- **Super Administrateur SaaS** : gere les ecoles clientes, les plans d'abonnement et la facturation ; acces complet a toutes les ecoles.
- **Directeur General / PCA** (role `admin_ecole`, volet *decisionnel*) : finances (paiements, frais de scolarite des filieres), remuneration des enseignants, comptes utilisateurs de l'ecole, communication (site, blog, newsletter, messages), **validation et publication des resultats** apres deliberation. Consulte les resultats, PV et releves sans pouvoir modifier notes ni decisions.
- **Directeur des etudes** (role `directeur_etudes`, volet *academique*) : annees universitaires, filieres, UE et matieres, enseignants (hors remuneration), inscriptions (hors montants), emploi du temps, notes, calcul des resultats, constitution des jurys et conduite des deliberations. N'a acces a aucune donnee financiere.
- **Assistant** : scolarite courante (inscriptions, encaissements, emploi du temps, saisie des notes).

La fonction de chaque compte (ex. *Directeur General*, *President du Conseil d'Administration*, *Directeur des etudes*) est imprimee sur les documents officiels.

### Circuit des deliberations

1. Le **Directeur des etudes** constitue le jury, fait saisir les notes, delibere (points de jury, decisions) puis **cloture** : les notes de la filiere sont figees et le PV est transmis.
2. Le **DG / PCA** examine le PV puis **valide et publie** les resultats (consultables par les etudiants) ou **renvoie** la deliberation au Directeur des etudes.
3. Apres validation, seul le DG / PCA peut **retirer la validation** (depublication) ; le Directeur des etudes peut alors rouvrir le jury.

Le PV porte le visa « Vu et approuve » du DG / PCA ; les releves portent la signature du Directeur des etudes et la mention de validation.
