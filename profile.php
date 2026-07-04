<?php
// ============================================================
// profile.php — view & update own details (sabai role ko lagi)
// ============================================================
require_once __DIR__ . '/includes/auth.php';
require_login();   // logged-in matra

$pdo = DB::conn();
$uid = current_user()['id'];

// ---------- Handle update ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // Password change (optional — bharyo bhane matra)
    $curPass = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $conPass = $_POST['confirm_password'] ?? '';

    $errors = [];

    // --- basic validation ---
    if ($name === '') $errors[] = 'Name is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email is required.';
    }
    if ($phone !== '' && !preg_match('/^[0-9]{7,15}$/', $phone)) {
        $errors[] = 'Phone must be 7–15 digits.';
    }

    // --- email unique check (aafno bahek aru sanga match bhayo ki) ---
    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
        $stmt->execute([$email, $uid]);
        if ($stmt->fetch()) {
            $errors[] = 'That email is already used by another account.';
        }
    }

    // --- password change requested? ---
    $changePass = ($curPass !== '' || $newPass !== '' || $conPass !== '');
    if ($changePass && empty($errors)) {
        // current password verify garne
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        $row = $stmt->fetch();

        if (!password_verify($curPass, $row['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($newPass) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        } elseif ($newPass !== $conPass) {
            $errors[] = 'New passwords do not match.';
        }
    }

    if (!empty($errors)) {
        flash('error', implode(' ', $errors));
        redirect(BASE_URL . '/profile.php');
    }

    // ---------- Save ----------
    if ($changePass) {
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            'UPDATE users SET name=?, email=?, phone=?, password_hash=? WHERE id=?'
        );
        $stmt->execute([$name, $email, $phone, $hash, $uid]);
    } else {
        $stmt = $pdo->prepare(
            'UPDATE users SET name=?, email=?, phone=? WHERE id=?'
        );
        $stmt->execute([$name, $email, $phone, $uid]);
    }

    // Session ma naya name/email update garne (navbar ma dekhincha)
    $_SESSION['user']['name']  = $name;
    $_SESSION['user']['email'] = $email;

    flash('success', 'Profile updated successfully.');
    redirect(BASE_URL . '/profile.php');
}

// ---------- Load current details ----------
$stmt = $pdo->prepare(
    'SELECT name, email, phone, role_id, created_at FROM users WHERE id = ?'
);
$stmt->execute([$uid]);
$me = $stmt->fetch();

$pageTitle = 'My Profile';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>My Profile</h1>
    <span class="role-badge"><?= role_name($me['role_id']) ?></span>
</div>

<div class="profile-grid">

    <!-- Details form -->
    <div class="card">
        <h2 class="card-title">Account Details</h2>
        <form method="post" action="<?= BASE_URL ?>/profile.php">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name"
                       value="<?= e($me['name']) ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       value="<?= e($me['email']) ?>" required>
            </div>
            <div class="form-group">
                <label for="phone">Phone <span class="opt">(optional)</span></label>
                <input type="text" id="phone" name="phone"
                       value="<?= e($me['phone']) ?>">
            </div>

            <hr class="divider">
            <p class="section-note">
                Password change garnu cha bhane matra tala bharnus.
            </p>

            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password"
                       placeholder="Leave blank to keep same">
            </div>
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password"
                       placeholder="At least 6 characters">
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password">
            </div>

            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>

    <!-- Info sidebar -->
    <div class="card card-info">
        <h2 class="card-title">Account Info</h2>
        <ul class="info-list">
            <li><span>Role</span><strong><?= role_name($me['role_id']) ?></strong></li>
            <li><span>Email</span><strong><?= e($me['email']) ?></strong></li>
            <li><span>Phone</span><strong><?= e($me['phone'] ?: '—') ?></strong></li>
            <li><span>Joined</span><strong><?= e(date('M j, Y', strtotime($me['created_at']))) ?></strong></li>
        </ul>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>