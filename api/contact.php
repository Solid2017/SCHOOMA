<?php
/**
 * API - Reception du formulaire de contact (reponse JSON)
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

$name    = $input['name'] ?? '';
$email   = $input['email'] ?? '';
$phone   = $input['phone'] ?? '';
$subject = $input['subject'] ?? '';
$message = $input['message'] ?? '';

// Validation serveur
if (mb_strlen($name) < 2) {
    jsonResponse(false, 'Le nom doit contenir au moins 2 caracteres.', [], 422);
}
if (!isValidEmail($email)) {
    jsonResponse(false, 'Adresse email invalide.', [], 422);
}
if (mb_strlen($message) < 10) {
    jsonResponse(false, 'Le message doit contenir au moins 10 caracteres.', [], 422);
}

try {
    $stmt = getDB()->prepare(
        'INSERT INTO contacts (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$name, $email, $phone ?: null, $subject ?: null, $message]);
    jsonResponse(true, 'Votre message a bien ete envoye. Nous vous repondrons rapidement.');
} catch (PDOException $e) {
    jsonResponse(false, "Une erreur est survenue lors de l'envoi. Veuillez reessayer.", [], 500);
}
