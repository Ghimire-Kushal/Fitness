<?php
// ============================================================
// register.php — new member registration (online admission)
// Registers a new user → automatically becomes Member (role 3).
// ============================================================
require_once __DIR__ . '/includes/auth.php';

// Already logged in — don't allow registering again, send to dashboard
if (is_logged_in()) {
    redirect(dashboard_for(current_user()['role_id']));
}

// ---------- Handle form submit ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    // Keep the previous values in the form (except password)
    $_SESSION['old'] = ['name' => $name, 'email' => $email, 'phone' => $phone];

    // ---------- Validation ----------
    $errors = [];

    if ($name === '')  $errors[] = 'Name is required.';
    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($phone !== '' && !preg_match('/^[0-9]{7,15}$/', $phone)) {
        $errors[] = 'Phone must be 7–15 digits.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    // Check if the email already exists
    if (empty($errors)) {
        $pdo  = DB::conn();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'This email is already registered. Try logging in.';
        }
    }

    // ---------- If there are errors, go back ----------
    if (!empty($errors)) {
        flash('error', implode(' ', $errors));
        redirect(BASE_URL . '/register.php');
    }

    // ---------- Insert new member ----------
    $hash = password_hash($password, PASSWORD_DEFAULT);  // bcrypt

    $pdo  = DB::conn();
    $stmt = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, phone, role_id)
         VALUES (?, ?, ?, ?, ?)'   // 3 = Member
    );
    $stmt->execute([$name, $email, $hash, $phone , 3]);

    unset($_SESSION['old']);
    flash('success', 'Registration successful! Please log in.');
    redirect(BASE_URL . '/login.php');
}

$pageTitle = 'Register — Fitness Management System of Nepal';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <h1 class="auth-title">Create Account</h1>
        <p class="auth-sub">Register as a member.</p>

        <form method="post" action="<?= BASE_URL ?>/register.php">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name"
                       value="<?= old('name') ?>"
 required autofocus>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       value="<?= old('email') ?>"
 required>
            </div>

            <div class="form-group">
                <label for="phone">Phone <span class="opt">(optional)</span></label>
                <input type="text" id="phone" name="phone"
                       value="<?= old('phone') ?>"
>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
 required>
            </div>

            <div class="form-group">
                <label for="confirm">Confirm Password</label>
                <input type="password" id="confirm" name="confirm"
 required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Register</button>
        </form>

        <p class="auth-foot">
            Already have an account?
            <a href="<?= BASE_URL ?>/login.php">Login here</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>