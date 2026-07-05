<?php
// ============================================================
// login.php — login form + handler
// attempt_login() use garcha, ani role anusar redirect garcha.
// ============================================================
require_once __DIR__ . '/includes/auth.php';

// Already logged in bhaye, form dekhauna jaruri chaina — dashboard ma pathau
if (is_logged_in()) {
    redirect(dashboard_for(current_user()['role_id']));
}

// ---------- Handle form submit ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();  // fake submit rok

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Purano email form ma rakhne (fail bhaye retype nagarnu parne)
    $_SESSION['old']['email'] = $email;

    if ($email === '' || $password === '') {
        flash('error', 'Please enter both email and password.');
        redirect(BASE_URL . '/login.php');
    }

    if (attempt_login($email, $password)) {
        unset($_SESSION['old']);  // success — purano clear
        flash('success', 'Welcome back, ' . current_user()['name'] . '!');
        redirect(dashboard_for(current_user()['role_id']));
    } else {
        // Note: "email OR password galat" — kun galat vanne dekhaudaina (security)
        flash('error', 'Invalid email or password.');
        redirect(BASE_URL . '/login.php');
    }
}

$pageTitle = 'Login — Fitness Management System';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <h1 class="auth-title">Login</h1>
        <p class="auth-sub">Aafno account ma login garnus.</p>

        <form method="post" action="<?= BASE_URL ?>/login.php">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       value="<?= old('email') ?>"
 required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
 required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>

        <p class="auth-foot">
            Don't have an account?
            <a href="<?= BASE_URL ?>/register.php">Register here</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>