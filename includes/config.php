<?php
/**
 * SCHOOMA - Configuration generale
 * Connexion BDD (PDO) + constantes du site
 */

// ---- Environnement ---------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', '0'); // mettre '1' uniquement en developpement local

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- Constantes BDD ----------------------------------------------------
define('DB_HOST', 'localhost'); // sur LWS, utiliser '127.0.0.1' si 'localhost' echoue (socket vs TCP)
define('DB_NAME', 'schooma_db');
define('DB_USER', 'schooma_user');
define('DB_PASS', 'CHANGE_ME_IN_PRODUCTION');
define('DB_CHARSET', 'utf8mb4');

// ---- Constantes application --------------------------------------------
define('SITE_NAME', 'SCHOOMA');
define('SITE_TAGLINE', "Reussir l'annee universitaire");
define('SITE_DOMAIN', 'schooma.igotech.tech');
define('SITE_URL', 'https://schooma.igotech.tech');
define('ADMIN_URL', SITE_URL . '/admin');
define('SITE_EMAIL', 'contact@schooma.igotech.tech');
define('SITE_PHONE', '+000 00 00 00 00');
define('SITE_ADDRESS', 'Adresse a definir, Ville, Pays');
define('SITE_LANG', 'fr');
define('ASSETS_URL', SITE_URL . '/assets');
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL', ASSETS_URL . '/uploads/');

// Etablissement (client SaaS) rattache a ce nom de domaine public.
// Sur un deploiement multi-etablissements, cette valeur peut etre resolue
// dynamiquement via le nom de domaine (table clients.slug).
define('CURRENT_CLIENT_ID', 1);

// ---- Connexion PDO -------------------------------------------------------
function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die('Erreur de connexion a la base de donnees. Veuillez reessayer plus tard.');
        }
    }
    return $pdo;
}

require_once __DIR__ . '/functions.php';
