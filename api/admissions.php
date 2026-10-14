<?php
/**
 * API - Demande d'admission publique (reponse JSON)
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

$firstName = $input['first_name'] ?? '';
$lastName  = $input['last_name'] ?? '';
$email     = $input['email'] ?? '';
$phone     = $input['phone'] ?? '';
$program   = $input['program_wanted'] ?? '';
$message   = $input['message'] ?? '';

if (mb_strlen($firstName) < 2 || mb_strlen($lastName) < 2) {
    jsonResponse(false, 'Veuillez renseigner votre nom et prenom.', [], 422);
}
if (!isValidEmail($email)) {
    jsonResponse(false, 'Adresse email invalide.', [], 422);
}
if (mb_strlen(preg_replace('/\D/', '', $phone)) < 6) {
    jsonResponse(false, 'Numero de telephone invalide.', [], 422);
}

try {
    $stmt = getDB()->prepare(
        'INSERT INTO admission_requests (client_id, first_name, last_name, email, phone, program_wanted, message)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([CURRENT_CLIENT_ID, $firstName, $lastName, $email, $phone, $program ?: null, $message ?: null]);
    jsonResponse(true, "Votre demande d'admission a bien ete recue. Notre equipe vous recontactera sous 48h.");
} catch (PDOException $e) {
    jsonResponse(false, "Une erreur est survenue lors de l'envoi de votre demande. Veuillez reessayer.", [], 500);
}
