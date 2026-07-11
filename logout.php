<?php
// ============================================================
// logout.php — Secure logout (BEST form)
// Steps (complete cleanup):
//   1. Clear all session variables
//   2. Delete the session COOKIE from the browser (best practice)
//   3. Destroy the session (server side)
//   4. Start a new clean session, set a goodbye flash, and redirect to login
// ============================================================
require_once __DIR__ . '/includes/auth.php';   // session already start + BASE_URL

// 1) Clear all session data
$_SESSION = [];

// 2) Delete the session cookie — only applies if using cookie-based sessions
//    (skipping this leaves the old session id sitting in the browser)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,                 // past time = delete
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// 3) Server side session file destroy
session_destroy();

// 4) Start a new clean session — so the goodbye message can show on the login page
session_start();
session_regenerate_id(true);            // new id (prevents session fixation)
flash('success', 'You have been logged out. See you soon!');

redirect(BASE_URL . '/login.php');