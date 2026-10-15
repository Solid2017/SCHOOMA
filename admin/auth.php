<?php
/**
 * SCHOOMA Admin - Authentification, session et helpers de roles
 * A inclure en tout debut de chaque page admin protegee.
 */
require_once __DIR__ . '/../includes/config.php';

const ROLE_SUPER_ADMIN      = 'super_admin';
const ROLE_ADMIN_ECOLE      = 'admin_ecole';      // Directeur General / PCA : aspects decisionnels
const ROLE_DIRECTEUR_ETUDES = 'directeur_etudes'; // Directeur des etudes : aspects academiques
const ROLE_ASSISTANT        = 'assistant';

// Groupes de roles utilises pour les controles d'acces
const ROLES_DIRECTION = [ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE];                        // decisions, finances, publication
const ROLES_ACADEMIC  = [ROLE_SUPER_ADMIN, ROLE_DIRECTEUR_ETUDES];                   // maquettes, notes, deliberations
const ROLES_SCHOOL_HEADS = [ROLE_SUPER_ADMIN, ROLE_ADMIN_ECOLE, ROLE_DIRECTEUR_ETUDES]; // consultation commune

/** Utilisateur actuellement connecte (ou null) */
function currentUser(): ?array
{
    return $_SESSION['admin_user'] ?? null;
}

/** Force la connexion, sinon redirige vers le login */
function requireLogin(): array
{
    $user = currentUser();
    if (!$user) {
        redirect(ADMIN_URL . '/login.php');
    }
    return $user;
}

/** Restreint l'acces a certains roles */
function requireRole(array $roles): void
{
    $user = requireLogin();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:60px;text-align:center;"><h1>Acces refuse</h1><p>Vous n\'avez pas les droits necessaires pour acceder a cette page.</p><a href="' . ADMIN_URL . '/index.php">Retour au tableau de bord</a></div>');
    }
}

/** L'utilisateur connecte a-t-il l'un de ces roles ? */
function hasRole(array $roles): bool
{
    $user = currentUser();
    return $user !== null && in_array($user['role'], $roles, true);
}

function isSuperAdmin(): bool
{
    $user = currentUser();
    return $user && $user['role'] === ROLE_SUPER_ADMIN;
}

/**
 * Retourne l'identifiant du client (ecole) sur lequel l'utilisateur doit etre
 * cantonne. NULL pour le super-admin (acces a toutes les ecoles / SaaS global).
 */
function scopeClientId(): ?int
{
    $user = currentUser();
    if (!$user) {
        return null;
    }
    return $user['role'] === ROLE_SUPER_ADMIN ? null : (int) $user['client_id'];
}

/** Connecte un utilisateur (apres verification du mot de passe) */
function loginUser(array $user): void
{
    $_SESSION['admin_user'] = [
        'id'        => (int) $user['id'],
        'name'      => $user['name'],
        'email'     => $user['email'],
        'role'      => $user['role'],
        'job_title' => $user['job_title'] ?? null,
        'client_id' => $user['client_id'] !== null ? (int) $user['client_id'] : null,
        'photo'     => $user['photo'],
    ];
    session_regenerate_id(true);
}

function logoutUser(): void
{
    unset($_SESSION['admin_user']);
    session_regenerate_id(true);
}

/** Nom lisible d'un role */
function roleLabel(string $role): string
{
    return match ($role) {
        ROLE_SUPER_ADMIN      => 'Super Administrateur',
        ROLE_ADMIN_ECOLE      => 'Directeur General / PCA',
        ROLE_DIRECTEUR_ETUDES => 'Directeur des etudes',
        ROLE_ASSISTANT        => 'Assistant',
        default               => ucfirst($role),
    };
}

/** Description courte des attributions d'un role (formulaire utilisateurs) */
function roleDescription(string $role): string
{
    return match ($role) {
        ROLE_SUPER_ADMIN      => 'Plateforme SaaS : ecoles clientes, abonnements, facturation, acces a toutes les ecoles.',
        ROLE_ADMIN_ECOLE      => 'Decisionnel : finances, frais de scolarite, remuneration des enseignants, utilisateurs, communication, validation et publication des resultats.',
        ROLE_DIRECTEUR_ETUDES => 'Academique : filieres, UE et matieres, emploi du temps, notes, resultats, jurys de deliberation et releves.',
        ROLE_ASSISTANT        => 'Scolarite : inscriptions, encaissement des paiements, emploi du temps, saisie des notes.',
        default               => '',
    };
}

/**
 * Determine le client (ecole) sur lequel travailler pour les modules
 * academiques/pedagogiques. Pour un utilisateur d'ecole : toujours leur
 * propre ecole. Pour un super_admin : l'ecole choisie via ?client_id=, ou la
 * premiere ecole disponible par defaut (le super-admin peut ainsi consulter
 * et gerer les donnees de n'importe quelle ecole cliente).
 */
function resolveWorkingClientId(): ?int
{
    $scoped = scopeClientId();
    if ($scoped !== null) {
        return $scoped;
    }
    $requested = (int) ($_GET['client_id'] ?? 0);
    if ($requested > 0) {
        return $requested;
    }
    $stmt = getDB()->query('SELECT id FROM clients ORDER BY name ASC LIMIT 1');
    $first = $stmt->fetchColumn();
    return $first ? (int) $first : null;
}

/** Recupere l'annee universitaire courante pour le client actif */
function getCurrentAcademicYear(?int $clientId): ?array
{
    if ($clientId === null) {
        return null;
    }
    $stmt = getDB()->prepare('SELECT * FROM academic_years WHERE client_id = ? AND is_current = 1 ORDER BY start_date DESC LIMIT 1');
    $stmt->execute([$clientId]);
    return $stmt->fetch() ?: null;
}
