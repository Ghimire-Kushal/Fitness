<?php
// ============================================================
// logout.php — Secure logout (BEST form)
// Steps (complete cleanup):
//   1. Session ka sabai variable clear
//   2. Session COOKIE browser bata delete (best practice)
//   3. Session destroy (server side)
//   4. Naya clean session ma goodbye flash rakhera login ma redirect
// ============================================================
require_once __DIR__ . '/includes/auth.php';   // session already start + BASE_URL

// 1) Sabai session data khali garne
$_SESSION = [];

// 2) Session cookie delete garne — cookie-based session ho bhane matra
//    (yo nagare browser ma purano session id basirahancha)
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

// 4) Ek naya clean session — goodbye message login page ma dekhaउन
session_start();
session_regenerate_id(true);            // naya id (session fixation rok)
flash('success', 'You have been logged out. See you soon!');

redirect(BASE_URL . '/login.php');