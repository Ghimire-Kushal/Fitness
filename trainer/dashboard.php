<?php
// ============================================================
// trainer/dashboard.php — Trainer's home page
// Shows: assigned members, upcoming appointments (with
//            status update), and quick stats.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_role(2);                        // Trainer-only access

$pdo  = DB::conn();
$uid  = current_user()['id'];
$name = current_user()['name'];

$allowedStatuses = ['pending', 'approved', 'completed', 'cancelled'];

// ------------------------------------------------------------
// POST — the trainer can change the status of their own appointment
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $status    = $_POST['status'] ?? '';

    if (!in_array($status, $allowedStatuses, true)) {
        flash('error', 'Invalid booking status.');
    } else {
        // A trainer can only update their own appointments
        $stmt = $pdo->prepare(
            "UPDATE bookings SET status = ?
             WHERE id = ? AND trainer_id = ? AND booking_type = 'trainer_appointment'"
        );
        $stmt->execute([$status, $bookingId, $uid]);
        flash($stmt->rowCount() > 0 ? 'success' : 'error',
              $stmt->rowCount() > 0 ? 'Appointment updated.' : 'Appointment not found.');
    }
    redirect(BASE_URL . '/trainer/dashboard.php');
}

// ------------------------------------------------------------
// 1) ALL COUNTS — in one shot
// ------------------------------------------------------------
$stmt = $pdo->prepare(
    "SELECT
        (SELECT COUNT(*) FROM member_trainers WHERE trainer_id = :t1)                       AS total_members,
        (SELECT COUNT(*) FROM bookings
           WHERE trainer_id = :t2 AND status <> 'cancelled')                                AS total_appointments,
        (SELECT COUNT(*) FROM bookings b
           JOIN time_slots ts ON ts.id = b.time_slot_id
           WHERE b.trainer_id = :t3 AND ts.slot_date >= CURDATE()
             AND b.status <> 'cancelled')                                                   AS upcoming_appointments,
        (SELECT COUNT(*) FROM bookings
           WHERE trainer_id = :t4 AND status = 'pending')                                   AS pending_appointments"
);
$stmt->execute([':t1' => $uid, ':t2' => $uid, ':t3' => $uid, ':t4' => $uid]);
$stats = $stmt->fetch();

// ------------------------------------------------------------
// 2) ASSIGNED MEMBERS
// ------------------------------------------------------------
$stmt = $pdo->prepare(
    "SELECT u.id, u.name, u.email, u.phone, mt.assigned_at
     FROM member_trainers mt
     JOIN users u ON u.id = mt.member_id
     WHERE mt.trainer_id = ?
     ORDER BY u.name"
);
$stmt->execute([$uid]);
$members = $stmt->fetchAll();

// ------------------------------------------------------------
// 3) UPCOMING APPOINTMENTS — the nearest 10
// ------------------------------------------------------------
$stmt = $pdo->prepare(
    "SELECT b.id, b.status, ts.slot_date, ts.start_time, ts.end_time,
            u.name AS member_name, u.email AS member_email
     FROM bookings b
     JOIN time_slots ts ON ts.id = b.time_slot_id
     JOIN users u ON u.id = b.user_id
     WHERE b.trainer_id = ? AND b.booking_type = 'trainer_appointment'
       AND ts.slot_date >= CURDATE() AND b.status <> 'cancelled'
     ORDER BY ts.slot_date, ts.start_time
     LIMIT 10"
);
$stmt->execute([$uid]);
$upcoming = $stmt->fetchAll();

$pageTitle = 'Trainer Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Welcome, <?= e($name) ?></h1>
</div>

<!-- ================= STAT CARDS ================= -->
<div class="stat-grid">

    <div class="stat-card">
        <div class="stat-label">Assigned Members</div>
        <div class="stat-value"><?= (int) $stats['total_members'] ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-label">Upcoming Appointments</div>
        <div class="stat-value"><?= (int) $stats['upcoming_appointments'] ?></div>
        <div class="stat-sub"><?= (int) $stats['total_appointments'] ?> total</div>
    </div>

    <div class="stat-card">
        <div class="stat-label">Pending Approval</div>
        <div class="stat-value"><?= (int) $stats['pending_appointments'] ?></div>
    </div>

</div>

<!-- ================= QUICK ACTIONS ================= -->
<div class="quick-actions">
    <a href="<?= BASE_URL ?>/profile.php" class="btn btn-outline">Edit Profile</a>
</div>

<!-- ================= UPCOMING APPOINTMENTS ================= -->
<div class="card">
    <div class="card-head">
        <h2 class="card-title">Upcoming Appointments</h2>
    </div>

    <?php if (empty($upcoming)): ?>
        <p class="muted">No upcoming appointments.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr><th>Date</th><th>Time</th><th>Member</th><th>Status</th><th>Change</th></tr>
            </thead>
            <tbody>
            <?php foreach ($upcoming as $b): ?>
                <tr>
                    <td><?= e(date('D, M j', strtotime($b['slot_date']))) ?></td>
                    <td>
                        <?= e(date('g:i A', strtotime($b['start_time']))) ?> –
                        <?= e(date('g:i A', strtotime($b['end_time']))) ?>
                    </td>
                    <td><?= e($b['member_name']) ?><br><span class="muted"><?= e($b['member_email']) ?></span></td>
                    <td><span class="badge badge-<?= e($b['status']) ?>"><?= e(ucfirst($b['status'])) ?></span></td>
                    <td>
                        <form method="post" action="<?= BASE_URL ?>/trainer/dashboard.php" class="slot-select">
                            <?= csrf_field() ?>
                            <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                            <select name="status">
                                <?php foreach ($allowedStatuses as $s): ?>
                                    <option value="<?= e($s) ?>" <?= $b['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-primary btn-sm" type="submit">Save</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- ================= ASSIGNED MEMBERS ================= -->
<div class="card">
    <div class="card-head">
        <h2 class="card-title">Assigned Members</h2>
    </div>

    <?php if (empty($members)): ?>
        <p class="muted">No members assigned yet.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr><th>Name</th><th>Email</th><th>Phone</th><th>Assigned On</th></tr>
            </thead>
            <tbody>
            <?php foreach ($members as $m): ?>
                <tr>
                    <td><?= e($m['name']) ?></td>
                    <td><?= e($m['email']) ?></td>
                    <td><?= e($m['phone'] ?: '—') ?></td>
                    <td><?= e(date('M j, Y', strtotime($m['assigned_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
