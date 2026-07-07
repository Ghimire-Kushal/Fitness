<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(1);

$pdo = DB::conn();
$role = (int) ($_GET['role'] ?? 0);
$params = [];
$where = '';
if (in_array($role, [1, 2, 3], true)) {
    $where = 'WHERE u.role_id = ?';
    $params[] = $role;
}

$stmt = $pdo->prepare(
    "SELECT u.id, u.name, u.email, u.phone, u.role_id, u.created_at,
            (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id) AS booking_count,
            (SELECT COUNT(*) FROM memberships m WHERE m.user_id = u.id AND m.status = 'active' AND m.end_date >= CURDATE()) AS active_membership
     FROM users u
     $where
     ORDER BY u.created_at DESC"
);
$stmt->execute($params);
$users = $stmt->fetchAll();

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
        <p class="muted">No users found.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Bookings</th><th>Membership</th><th>Joined</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= e($u['name']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><?= e($u['phone'] ?: '-') ?></td>
                    <td><span class="role-badge"><?= e(role_name((int) $u['role_id'])) ?></span></td>
                    <td><?= (int) $u['booking_count'] ?></td>
                    <td><?= (int) $u['active_membership'] > 0 ? '<span class="badge badge-open">Active</span>' : '<span class="muted">-</span>' ?></td>
                    <td><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
