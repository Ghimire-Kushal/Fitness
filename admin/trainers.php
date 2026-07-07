<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(1);

$pdo = DB::conn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $trainerId = (int) ($_POST['trainer_id'] ?? 0);
    $specialization = trim($_POST['specialization'] ?? '');
    $experience = max(0, (int) ($_POST['experience_years'] ?? 0));
    $bio = trim($_POST['bio'] ?? '');

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND role_id = 2");
    $stmt->execute([$trainerId]);
    if ((int) $stmt->fetchColumn() !== 1) {
        flash('error', 'Trainer not found.');
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO trainer_profiles (user_id, specialization, bio, experience_years)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE specialization = VALUES(specialization), bio = VALUES(bio), experience_years = VALUES(experience_years)"
        );
        $stmt->execute([$trainerId, $specialization, $bio, $experience]);
        flash('success', 'Trainer profile saved.');
    }
    redirect(BASE_URL . '/admin/trainers.php');
}

$trainers = $pdo->query(
    "SELECT u.id, u.name, u.email, u.phone, tp.specialization, tp.bio, tp.experience_years,
            (SELECT COUNT(*) FROM member_trainers mt WHERE mt.trainer_id = u.id) AS member_count,
            (SELECT COUNT(*) FROM bookings b WHERE b.trainer_id = u.id AND b.status <> 'cancelled') AS appointment_count
     FROM users u
     LEFT JOIN trainer_profiles tp ON tp.user_id = u.id
     WHERE u.role_id = 2
     ORDER BY u.name"
)->fetchAll();

$pageTitle = 'Admin Trainers';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head"><h1>Trainers</h1></div>

<?php if (empty($trainers)): ?>
    <div class="card"><p class="muted">No trainer users found.</p></div>
<?php else: ?>
    <?php foreach ($trainers as $t): ?>
        <div class="card">
            <div class="card-head">
                <h2 class="card-title"><?= e($t['name']) ?></h2>
                <span class="muted"><?= e($t['email']) ?></span>
            </div>
            <div class="wp-summary">
                <div class="wp-summary-item"><span class="wp-sum-num"><?= (int) $t['member_count'] ?></span><span class="wp-sum-txt">Members</span></div>
                <div class="wp-summary-item"><span class="wp-sum-num"><?= (int) $t['appointment_count'] ?></span><span class="wp-sum-txt">Appointments</span></div>
                <div class="wp-summary-item"><span class="wp-sum-num"><?= (int) ($t['experience_years'] ?? 0) ?></span><span class="wp-sum-txt">Years</span></div>
            </div>
            <form method="post" action="<?= BASE_URL ?>/admin/trainers.php">
                <?= csrf_field() ?>
                <input type="hidden" name="trainer_id" value="<?= (int) $t['id'] ?>">
                <div class="slot-select">
                    <div class="form-group">
                        <label>Specialization</label>
                        <input type="text" name="specialization" value="<?= e($t['specialization']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Experience Years</label>
                        <input type="number" name="experience_years" min="0" value="<?= (int) ($t['experience_years'] ?? 0) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Bio</label>
                    <textarea name="bio" rows="3"><?= e($t['bio']) ?></textarea>
                </div>
                <button class="btn btn-primary btn-sm" type="submit">Save Profile</button>
            </form>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
