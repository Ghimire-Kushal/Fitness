<?php
// ============================================================
// login.php — login form + handler
// Uses attempt_login(), then redirects based on the user's role.
// ============================================================
require_once __DIR__ . '/includes/auth.php';

// Already logged in — no need to show the form, send to dashboard
if (is_logged_in()) {
    redirect(dashboard_for(current_user()['role_id']));
}

// ---------- Handle form submit ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();  // block forged submits

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Keep the entered email in the form (so it doesn't need retyping on failure)
    $_SESSION['old']['email'] = $email;

    if ($email === '' || $password === '') {
        flash('error', 'Please enter both email and password.');
        redirect(BASE_URL . '/login.php');
    }

    if (attempt_login($email, $password)) {
        unset($_SESSION['old']);  // success — clear old values
        flash('success', 'Welcome back, ' . current_user()['name'] . '!');
        redirect(dashboard_for(current_user()['role_id']));
    } else {
        // Note: deliberately vague ("email or password is wrong") — doesn't reveal which one (security)
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
        <p class="auth-sub">Log in to your account.</p>

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