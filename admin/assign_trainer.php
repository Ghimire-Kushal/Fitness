<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(1);

$pdo = DB::conn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'assign') {
        $memberId = (int) ($_POST['member_id'] ?? 0);
        $trainerId = (int) ($_POST['trainer_id'] ?? 0);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND role_id = 3");
        $stmt->execute([$memberId]);
        $memberOk = (int) $stmt->fetchColumn() === 1;

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND role_id = 2");
        $stmt->execute([$trainerId]);
        $trainerOk = (int) $stmt->fetchColumn() === 1;

        if (!$memberOk || !$trainerOk) {
            flash('error', 'Please choose a valid member and trainer.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO member_trainers (member_id, trainer_id) VALUES (?, ?)");
                $stmt->execute([$memberId, $trainerId]);
                flash('success', 'Trainer assigned successfully.');
            } catch (PDOException $e) {
                flash('error', $e->getCode() === '23000' ? 'That trainer is already assigned to this member.' : 'Could not assign trainer.');
            }
        }
    }

    if ($action === 'remove') {
        $id = (int) ($_POST['assignment_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM member_trainers WHERE id = ?");
        $stmt->execute([$id]);
        flash($stmt->rowCount() > 0 ? 'success' : 'error', $stmt->rowCount() > 0 ? 'Assignment removed.' : 'Assignment not found.');
    }

    redirect(BASE_URL . '/admin/assign_trainer.php');
}

$members = $pdo->query("SELECT id, name, email FROM users WHERE role_id = 3 ORDER BY name")->fetchAll();
$trainers = $pdo->query(
    "SELECT u.id, u.name, u.email, tp.specialization
     FROM users u
     LEFT JOIN trainer_profiles tp ON tp.user_id = u.id
     WHERE u.role_id = 2
     ORDER BY u.name"
)->fetchAll();
$assignments = $pdo->query(
    "SELECT mt.id, mt.assigned_at, m.name AS member_name, m.email AS member_email,
            t.name AS trainer_name, t.email AS trainer_email, tp.specialization
     FROM member_trainers mt
     JOIN users m ON m.id = mt.member_id
     JOIN users t ON t.id = mt.trainer_id
     LEFT JOIN trainer_profiles tp ON tp.user_id = t.id
     ORDER BY mt.assigned_at DESC"
)->fetchAll();

$pageTitle = 'Assign Trainer';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head"><h1>Assign Trainer</h1></div>

<div class="card">
    <h2 class="card-title">New Assignment</h2>
    <form method="post" action="<?= BASE_URL ?>/admin/assign_trainer.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="assign">
        <div class="slot-select">
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
                <label>Trainer</label>
                <select name="trainer_id" required>
                    <option value="">Select trainer</option>
                    <?php foreach ($trainers as $t): ?>
                        <option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?><?= $t['specialization'] ? ' - ' . e($t['specialization']) : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <button class="btn btn-primary" type="submit">Assign</button>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <h2 class="card-title">Current Assignments</h2>
    <?php if (empty($assignments)): ?>
        <p class="muted">No trainer assignments yet.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Member</th><th>Trainer</th><th>Specialization</th><th>Assigned</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($assignments as $a): ?>
                <tr>
                    <td><?= e($a['member_name']) ?><br><span class="muted"><?= e($a['member_email']) ?></span></td>
                    <td><?= e($a['trainer_name']) ?><br><span class="muted"><?= e($a['trainer_email']) ?></span></td>
                    <td><?= e($a['specialization'] ?: '-') ?></td>
                    <td><?= e(date('M j, Y', strtotime($a['assigned_at']))) ?></td>
                    <td>
                        <form method="post" action="<?= BASE_URL ?>/admin/assign_trainer.php" onsubmit="return confirm('Remove this assignment?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="assignment_id" value="<?= (int) $a['id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
