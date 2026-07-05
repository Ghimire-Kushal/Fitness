<?php
// ============================================================
// includes/functions.php
// Chota helper functions — har page le reuse garcha.
// ============================================================

// Start the session once, safely (auth ko lagi chahincha)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Base path the app is served under (matches links in header.php etc.)
if (!defined('BASE_URL')) {
    $__base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    if (in_array(basename($__base), ['admin', 'member', 'trainer'], true)) {
        $__base = dirname($__base);
    }
    if ($__base === '/' || $__base === '\\') { $__base = ''; }
    define('BASE_URL', $__base);
}

/**
 * e() = escape. Output garda ALWAYS use this to stop XSS.
 * User le <script> haaleko cha bhane, safe text ma badalcha.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * redirect() — pathabata arko page ma pathaucha, ani script rokcha.
 * exit garnu jaruri cha, natra tala ko code chalcha.
 */
function redirect(string $path): void
{
    header("Location: $path");
    exit;
}

/**
 * flash() — one-time message store/show garcha.
 * - flash('success', 'Saved!')  → set garcha
 * - flash('success')            → return + delete garcha (once matra dekhcha)
 */
function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        // set mode
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    // get mode — read then remove
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

/**
 * old() — form submit fail bhaye, purano value form ma feri dekhaucha.
 */
function old(string $key, string $default = ''): string
{
    return e($_SESSION['old'][$key] ?? $default);
}

// ---------------- CSRF protection ----------------
// Forms lai fake/forged submit bata bachaucha.

/**
 * csrf_token() — session ma ek token banaucha ra return garcha.
 * Har form ma hidden field ma yo token halne.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/**
 * csrf_field() — ready-made hidden input HTML dincha.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

/**
 * csrf_check() — form submit huda token milyo ki milena check garcha.
 * Namilyo bhane request rokcha.
 */
function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        die('Invalid CSRF token. Please go back and try again.');
    }
}