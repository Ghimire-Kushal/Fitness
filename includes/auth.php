<?php
// ============================================================
// includes/auth.php
// Login, logout, session user, ra role-based access (RBAC).
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';   // session already start hunca

/**
 * attempt_login() — email + password check garcha.
 * Milyo bhane session ma user rakhcha, true return garcha.
 */
function attempt_login(string $email, string $password): bool
{
    $pdo = DB::conn();

    // Prepared statement — SQL injection bata safe
    $stmt = $pdo->prepare(
        'SELECT id, name, email, password_hash, role_id
         FROM users WHERE email = ?'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // User bhetiyena, OR password milena → fail
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    // Success — session ma minimum info matra rakhne (password chaina)
    $_SESSION['user'] = [
        'id'      => $user['id'],
        'name'    => $user['name'],
        'email'   => $user['email'],
        'role_id' => (int) $user['role_id'],
    ];

    // Security: login pachi session id badalne (session fixation rok)
    session_regenerate_id(true);

    return true;
}

/**
 * logout() — session sabai clear garcha.
 */
function logout(): void
{
    $_SESSION = [];
    session_destroy();
}

/**
 * current_user() — logged-in user ko array, or null.
 */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * is_logged_in() — true/false.
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

/**
 * role_name() — role_id lai text ma badalcha.
 */
function role_name(?int $roleId = null): string
{
    $roleId = $roleId ?? (current_user()['role_id'] ?? 0);
    return match ($roleId) {
        1 => 'Admin',
        2 => 'Trainer',
        3 => 'Member',
        default => 'Guest',
    };
}

// ---------------- Access guards ----------------

/**
 * require_login() — login nabhaye login page ma pathaucha.
 * Har protected page ko top ma yo call garne.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'Please log in first.');
        redirect('/fitness-management-system/login.php');
    }
}

/**
 * require_role() — thik role nabhaye rokcha.
 * Example: require_role(1)  → Admin matra
 *          require_role([1,2]) → Admin ya Trainer
 */
function require_role(int|array $allowed): void
{
    require_login();  // pahile login check

    $roleId  = current_user()['role_id'];
    $allowed = (array) $allowed;   // int aayo bhane array banaune

    if (!in_array($roleId, $allowed, true)) {
        http_response_code(403);
        die('403 Forbidden — you do not have access to this page.');
    }
}

/**
 * dashboard_for() — role anusar kun dashboard ma janne.
 */
function dashboard_for(int $roleId): string
{
    $base = '/fitness-management-system';
    return match ($roleId) {
        1 => "$base/admin/dashboard.php",
        2 => "$base/trainer/dashboard.php",
        3 => "$base/member/dashboard.php",
        default => "$base/login.php",
    };
}