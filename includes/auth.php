<?php
// ============================================================
// includes/auth.php
// Login, logout, session user, and role-based access (RBAC).
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';   // session already started

/**
 * attempt_login() — checks email + password.
 * On success, stores the user in the session and returns true.
 */
function attempt_login(string $email, string $password): bool
{
    $pdo = DB::conn();

    // Prepared statement — safe from SQL injection
    $stmt = $pdo->prepare(
        'SELECT id, name, email, password_hash, role_id
         FROM users WHERE email = ?'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // User not found, OR password doesn't match → fail
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    // Success — store only minimal info in the session (no password)
    $_SESSION['user'] = [
        'id'      => $user['id'],
        'name'    => $user['name'],
        'email'   => $user['email'],
        'role_id' => (int) $user['role_id'],
    ];

    // Security: regenerate session id after login (prevents session fixation)
    session_regenerate_id(true);

    return true;
}

/**
 * logout() — clears the whole session.
 */
function logout(): void
{
    $_SESSION = [];
    session_destroy();
}

/**
 * current_user() — the logged-in user's array, or null.
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
 * role_name() — converts a role_id to text.
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
 * require_login() — sends to the login page if not logged in.
 * Call this at the top of every protected page.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'Please log in first.');
        redirect('/fitness-management-system/login.php');
    }
}

/**
 * require_role() — blocks access if the user doesn't have the right role.
 * Example: require_role(1)  → Admin only
 *          require_role([1,2]) → Admin or Trainer
 */
function require_role(int|array $allowed): void
{
    require_login();  // check login first

    $roleId  = current_user()['role_id'];
    $allowed = (array) $allowed;   // wrap a single int into an array

    if (!in_array($roleId, $allowed, true)) {
        http_response_code(403);
        die('403 Forbidden — you do not have access to this page.');
    }
}

/**
 * dashboard_for() — which dashboard to go to based on role.
 */
function dashboard_for(int $roleId): string
{
    $base = BASE_URL;
    return match ($roleId) {
        1 => "$base/admin/dashboard.php",
        2 => "$base/trainer/dashboard.php",
        3 => "$base/member/dashboard.php",
        default => "$base/login.php",
    };
}