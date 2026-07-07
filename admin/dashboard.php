<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(1);

$pdo = DB::conn();
$stats = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM users WHERE role_id = 3) AS members,
        (SELECT COUNT(*) FROM users WHERE role_id = 2) AS trainers,
        (SELECT COUNT(*) FROM bookings WHERE status = 'pending') AS pending_bookings,
        (SELECT COUNT(*) FROM time_slots WHERE slot_date >= CURDATE()) AS upcoming_slots,
        (SELECT COUNT(*) FROM workout_plans) AS workout_plans,
        (SELECT COUNT(*) FROM memberships WHERE status = 'active' AND end_date >= CURDATE()) AS active_memberships"
)->fetch();

$recent = $pdo->query(
    "SELECT b.id, b.booking_type, b.status, b.created_at, u.name AS member_name,
            t.name AS trainer_name, ts.slot_date, ts.start_time
     FROM bookings b
     JOIN users u ON u.id = b.user_id
     JOIN time_slots ts ON ts.id = b.time_slot_id
     LEFT JOIN users t ON t.id = b.trainer_id
     ORDER BY b.created_at DESC
     LIMIT 6"
)->fetchAll();

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Admin Dashboard</h1>
</div>

<div class="stat-grid">
    <div class="stat-card"><span class="stat-label">Members</span><div class="stat-value"><?= (int) $stats['members'] ?></div></div>
    <div class="stat-card"><span class="stat-label">Trainers</span><div class="stat-value"><?= (int) $stats['trainers'] ?></div></div>
    <div class="stat-card"><span class="stat-label">Pending Bookings</span><div class="stat-value"><?= (int) $stats['pending_bookings'] ?></div></div>
    <div class="stat-card"><span class="stat-label">Upcoming Slots</span><div class="stat-value"><?= (int) $stats['upcoming_slots'] ?></div></div>
    <div class="stat-card"><span class="stat-label">Workout Plans</span><div class="stat-value"><?= (int) $stats['workout_plans'] ?></div></div>
    <div class="stat-card"><span class="stat-label">Active Memberships</span><div class="stat-value"><?= (int) $stats['active_memberships'] ?></div></div>
</div>

<div class="quick-actions">
    <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline">Users</a>
    <a href="<?= BASE_URL ?>/admin/assign_trainer.php" class="btn btn-primary">Assign Trainer</a>
    <a href="<?= BASE_URL ?>/admin/slots.php" class="btn btn-outline">Slots</a>
    <a href="<?= BASE_URL ?>/admin/bookings.php" class="btn btn-outline">Bookings</a>
</div>

<div class="card">
    <div class="card-head">
        <h2 class="card-title">Recent Bookings</h2>
        <a href="<?= BASE_URL ?>/admin/bookings.php" class="link-sm">See all</a>
    </div>
    <?php if (empty($recent)): ?>
        <p class="muted">No bookings yet.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Member</th><th>Date</th><th>Time</th><th>Type</th><th>Trainer</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $b): ?>
                <tr>
                    <td><?= e($b['member_name']) ?></td>
                    <td><?= e(date('M j, Y', strtotime($b['slot_date']))) ?></td>
                    <td><?= e(date('g:i A', strtotime($b['start_time']))) ?></td>
                    <td><?= $b['booking_type'] === 'trainer_appointment' ? 'Trainer Appt.' : 'Gym Session' ?></td>
                    <td><?= e($b['trainer_name'] ?: '-') ?></td>
                    <td><span class="badge badge-<?= e($b['status']) ?>"><?= e(ucfirst($b['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
