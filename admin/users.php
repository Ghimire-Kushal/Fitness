<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(1);

$pdo = DB::conn();
ensure_default_membership_plans($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'activate_membership') {
        $memberId = (int) ($_POST['member_id'] ?? 0);
        $planId = (int) ($_POST['plan_id'] ?? 0);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND role_id = 3");
        $stmt->execute([$memberId]);
        $memberOk = (int) $stmt->fetchColumn() === 1;

        $stmt = $pdo->prepare("SELECT * FROM membership_plans WHERE id = ?");
        $stmt->execute([$planId]);
        $plan = $stmt->fetch();

        if (!$memberOk || !$plan) {
            flash('error', 'Please choose a valid member and membership plan.');
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    "UPDATE memberships
                     SET status = 'cancelled'
                     WHERE user_id = ? AND status = 'active' AND end_date >= CURDATE()"
                );
                $stmt->execute([$memberId]);

                $start = date('Y-m-d');
                $end = date('Y-m-d', strtotime('+' . (int) $plan['duration_days'] . ' days'));
                $stmt = $pdo->prepare(
                    "INSERT INTO memberships (user_id, plan_id, start_date, end_date, status)
                     VALUES (?, ?, ?, ?, 'active')"
                );
                $stmt->execute([$memberId, $planId, $start, $end]);

                $pdo->commit();
                flash('success', 'Membership activated successfully.');
            } catch (Throwable $ex) {
                $pdo->rollBack();
                flash('error', 'Could not activate membership.');
            }
        }
    }

    redirect(BASE_URL . '/admin/users.php');
}

$role = (int) ($_GET['role'] ?? 0);
$params = [];
$where = '';
if (in_array($role, [1, 2, 3], true)) {
    $where = 'WHERE u.role_id = ?';
    $params[] = $role;
}

//user all table query with booking count and active membership status

$stmt = $pdo->prepare(
    "SELECT u.id, u.name, u.email, u.phone, u.role_id, u.created_at,
            (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id) AS booking_count,
            (SELECT COUNT(*) FROM memberships m WHERE m.user_id = u.id AND m.status = 'active' AND m.end_date >= CURDATE()) AS active_membership,
            (SELECT mp.name
             FROM memberships m
             JOIN membership_plans mp ON mp.id = m.plan_id
             WHERE m.user_id = u.id AND m.status = 'active' AND m.end_date >= CURDATE()
             ORDER BY m.end_date DESC
             LIMIT 1) AS active_plan
     FROM users u
     $where
     ORDER BY u.created_at DESC"
);
$stmt->execute($params);
$users = $stmt->fetchAll();
$plans = $pdo->query("SELECT id, name, duration_type, price FROM membership_plans ORDER BY FIELD(duration_type,'monthly','yearly'), price")->fetchAll();

$pageTitle = 'Admin Users';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Users</h1>
    <div class="quick-actions" style="margin:0;">
        <a class="btn btn-sm <?= $role === 0 ? 'btn-primary' : 'btn-outline' ?>" href="<?= BASE_URL ?>/admin/users.php">All</a>
        <a class="btn btn-sm <?= $role === 1 ? 'btn-primary' : 'btn-outline' ?>" href="<?= BASE_URL ?>/admin/users.php?role=1">Admins</a>
        <a class="btn btn-sm <?= $role === 2 ? 'btn-primary' : 'btn-outline' ?>" href="<?= BASE_URL ?>/admin/users.php?role=2">Trainers</a>
        <a class="btn btn-sm <?= $role === 3 ? 'btn-primary' : 'btn-outline' ?>" href="<?= BASE_URL ?>/admin/users.php?role=3">Members</a>
    </div>
</div>

<div class="card">
    <?php if (empty($users)): ?>
        <p class="muted">No users found ...</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Bookings</th><th>Membership</th><th>Joined</th></tr></thead>
            <tbody>
<!-- create the user foreach -->
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= e($u['name']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><?= e($u['phone'] ?: '-') ?></td>
                    <td><span class="role-badge"><?= e(role_name((int) $u['role_id'])) ?></span></td>
                    <td><?= (int) $u['booking_count'] ?></td>
                    <td>
                        <?php if ((int) $u['active_membership'] > 0): ?>
                            <span class="badge badge-open">Active</span>
                            <span class="muted"><?= e($u['active_plan']) ?></span>
                        <?php elseif ((int) $u['role_id'] === 3 && !empty($plans)): ?>
                            <form method="post" action="<?= BASE_URL ?>/admin/users.php" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="activate_membership">
                                <input type="hidden" name="member_id" value="<?= (int) $u['id'] ?>">
                                <select name="plan_id" required>
                                    <?php foreach ($plans as $p): ?>
                                        <option value="<?= (int) $p['id'] ?>">
                                            <?= e($p['name']) ?> - <?= e(ucfirst($p['duration_type'])) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-primary btn-sm" type="submit">Activate</button>
                            </form>
                        <?php else: ?>
                            <span class="muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
