<?php
// ============================================================
// includes/functions.php
// Small helper functions — reused by every page.
// ============================================================

// Start the session once, safely (needed for auth)
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
 * e() = escape. ALWAYS use this when outputting, to stop XSS.
 * If a user entered <script>, this converts it to safe text.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * redirect() — sends the browser to another page, then stops the script.
 * exit is required, otherwise the code below it would still run.
 */
function redirect(string $path): void
{
    header("Location: $path");
    exit;
}

/**
 * flash() — stores/shows a one-time message.
 * - flash('success', 'Saved!')  → sets it
 * - flash('success')            → returns it and deletes it (shown only once)
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
 * old() — if the form submit failed, shows the previous value again in the form.
 */
function old(string $key, string $default = ''): string
{
    return e($_SESSION['old'][$key] ?? $default);
}

// ---------------- CSRF protection ----------------
// Protects forms from fake/forged submits.

/**
 * csrf_token() — creates a token in the session and returns it.
 * Put this token in a hidden field on every form.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/**
 * csrf_field() — returns ready-made hidden input HTML.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

/**
 * csrf_check() — checks whether the submitted token matches.
 * Blocks the request if it doesn't match.
 */
function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        die('Invalid CSRF token. Please go back and try again.');
    }
}