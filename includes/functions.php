<?php
/**
 * SCHOOMA - Fonctions utilitaires globales
 */

/** Nettoie une chaine (protection XSS de base) */
function sanitize(?string $value): string
{
    $value = trim($value ?? '');
    $value = stripslashes($value);
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/** Echappe une valeur pour l'affichage HTML (sans la modifier) */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Texte saisi dans un formulaire, a stocker tel quel (l'echappement se fait a l'affichage avec e()) */
function inputText(?string $value, int $maxLength = 255): string
{
    return mb_substr(trim(strip_tags($value ?? '')), 0, $maxLength);
}

/** Nettoie un tableau de donnees ($_POST par exemple) */
function sanitizeArray(array $data): array
{
    $clean = [];
    foreach ($data as $key => $value) {
        $clean[$key] = is_array($value) ? sanitizeArray($value) : sanitize((string) $value);
    }
    return $clean;
}

/** Valide un email */
function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** Genere un slug URL-friendly */
function slugify(string $text): string
{
    $text = trim($text);
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

/** Formate un montant en devise locale */
function formatMoney($amount): string
{
    return number_format((float) $amount, 0, ',', ' ') . ' FCFA';
}

/** Formate une date au format francais */
function formatDate(?string $date, string $format = 'd/m/Y'): string
{
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '-';
}

/** Redirection HTTP */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Reponse JSON standardisee (pour les endpoints /api) */
function jsonResponse(bool $success, string $message = '', array $data = [], int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Genere/verifie un jeton CSRF */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function verifyCsrf(?string $token): bool
{
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/** Messages flash (session) */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/** Recupere le contenu CMS d'une section */
function getSiteContent(string $key): ?array
{
    static $cache = [];
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $stmt = getDB()->prepare('SELECT * FROM site_content WHERE section_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    $cache[$key] = $row ?: null;
    return $cache[$key];
}

/** Recupere les articles de blog publies */
function getPublishedPosts(int $limit = 6): array
{
    $stmt = getDB()->prepare('SELECT * FROM blog_posts WHERE status = "publie" ORDER BY published_at DESC LIMIT ' . (int) $limit);
    $stmt->execute();
    return $stmt->fetchAll();
}

/** Export CSV generique */
function exportCsv(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel
    fputcsv($output, $headers, ';');
    foreach ($rows as $row) {
        fputcsv($output, $row, ';');
    }
    fclose($output);
    exit;
}
