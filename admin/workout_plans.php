<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(1);

$pdo = DB::conn();
$adminId = current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $memberId = (int) ($_POST['member_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $details = trim($_POST['details'] ?? '');

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND role_id = 3");
        $stmt->execute([$memberId]);
        $memberOk = (int) $stmt->fetchColumn() === 1;

        if (!$memberOk || $title === '' || $details === '') {
            flash('error', 'Please choose a member and enter plan title/details.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO workout_plans (member_id, title, details, assigned_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$memberId, $title, $details, $adminId]);
            flash('success', 'Workout plan assigned.');
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['plan_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM workout_plans WHERE id = ?");
        $stmt->execute([$id]);
        flash($stmt->rowCount() > 0 ? 'success' : 'error', $stmt->rowCount() > 0 ? 'Workout plan deleted.' : 'Workout plan not found.');
    }

    redirect(BASE_URL . '/admin/workout_plans.php');
}

$members = $pdo->query("SELECT id, name, email FROM users WHERE role_id = 3 ORDER BY name")->fetchAll();
$plans = $pdo->query(
    "SELECT wp.id, wp.title, wp.details, wp.created_at,
            m.name AS member_name, m.email AS member_email,
            u.name AS assigned_by_name
     FROM workout_plans wp
     JOIN users m ON m.id = wp.member_id
     LEFT JOIN users u ON u.id = wp.assigned_by
     ORDER BY wp.created_at DESC"
)->fetchAll();

$pageTitle = 'Admin Workout Plans';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head"><h1>Workout Plans</h1></div>

<div class="card">
    <h2 class="card-title">Assign New Plan</h2>
    <form method="post" action="<?= BASE_URL ?>/admin/workout_plans.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="form-group">
            <label>Member</label>
            <select name="member_id" required>
                <option value="">Select member</option>
                <?php foreach ($members as $m): ?>
                    <option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?> (<?= e($m['email']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Title</label>
            <input type="text" name="title" maxlength="150" required>
        </div>
        <div class="form-group">
            <label>Details</label>
            <textarea name="details" rows="6" required></textarea>
        </div>
        <button class="btn btn-primary" type="submit">Assign Plan</button>
    </form>
</div>

<div class="card">
    <h2 class="card-title">Assigned Plans</h2>
    <?php if (empty($plans)): ?>
        <p class="muted">No workout plans assigned yet.</p>
    <?php else: ?>
        <div class="wp-list">
            <?php foreach ($plans as $p): ?>
                <div class="wp-card">
                    <div class="wp-card-head">
                        <h3 class="wp-title"><?= e($p['title']) ?></h3>
                        <form method="post" action="<?= BASE_URL ?>/admin/workout_plans.php" onsubmit="return confirm('Delete this plan?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="plan_id" value="<?= (int) $p['id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                        </form>
                    </div>
                    <div class="wp-meta">
                        <?= e($p['member_name']) ?> &middot; by <?= e($p['assigned_by_name'] ?: 'Staff') ?> &middot;
                        <?= e(date('M j, Y', strtotime($p['created_at']))) ?>
                    </div>
                    <div class="wp-details"><?= nl2br(e($p['details'])) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
