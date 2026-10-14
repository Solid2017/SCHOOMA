<?php
/**
 * API - Inscription a la newsletter (reponse JSON)
 */
require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Methode non autorisee.', [], 405);
}

$raw = json_decode(file_get_contents('php://input'), true);
if (!is_array($raw)) {
    $raw = $_POST;
}
$input = sanitizeArray(is_array($raw) ? $raw : []);
$email = $input['email'] ?? '';

if (!isValidEmail($email)) {
    jsonResponse(false, 'Adresse email invalide.', [], 422);
}

try {
    $db = getDB();
    $check = $db->prepare('SELECT id, status FROM newsletter_subscribers WHERE email = ?');
    $check->execute([$email]);
    $existing = $check->fetch();

    if ($existing) {
        if ($existing['status'] === 'actif') {
            jsonResponse(true, 'Vous etes deja abonne a notre newsletter.');
        }
        $update = $db->prepare('UPDATE newsletter_subscribers SET status = "actif" WHERE id = ?');
        $update->execute([$existing['id']]);
        jsonResponse(true, 'Votre abonnement a ete reactive. Merci !');
    }

    $stmt = $db->prepare('INSERT INTO newsletter_subscribers (email) VALUES (?)');
    $stmt->execute([$email]);
    jsonResponse(true, 'Merci pour votre inscription a notre newsletter !');
} catch (PDOException $e) {
    jsonResponse(false, 'Une erreur est survenue. Veuillez reessayer.', [], 500);
}
