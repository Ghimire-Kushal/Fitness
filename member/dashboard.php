<?php
// ============================================================
// member/dashboard.php — Member's home page
// Shows: membership status, upcoming bookings, workout plans,
//            assigned trainer, and quick stats.
// Efficiency: all counts are fetched in one subquery-based query —
//             avoids repeated hits to the database.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_role(3);                        // Member-only access

$pdo  = DB::conn();
$uid  = current_user()['id'];
$name = current_user()['name'];

// ------------------------------------------------------------
// 1) ALL COUNTS — in one shot (efficient)
//    Uses subqueries instead of a separate query for each card.
// ------------------------------------------------------------
$stmt = $pdo->prepare(
    "SELECT
        -- total bookings (excluding cancelled)
        (SELECT COUNT(*) FROM bookings
           WHERE user_id = :u1 AND status <> 'cancelled')                       AS total_bookings,
        -- upcoming bookings
        (SELECT COUNT(*) FROM bookings b
           JOIN time_slots ts ON ts.id = b.time_slot_id
           WHERE b.user_id = :u2 AND ts.slot_date >= CURDATE()
             AND b.status <> 'cancelled')                                       AS upcoming_bookings,
        -- assigned workout plans
        (SELECT COUNT(*) FROM workout_plans WHERE member_id = :u3)              AS total_plans,
        -- assigned trainer count
        (SELECT COUNT(*) FROM member_trainers WHERE member_id = :u4)            AS trainer_count"
);
$stmt->execute([':u1' => $uid, ':u2' => $uid, ':u3' => $uid, ':u4' => $uid]);
$stats = $stmt->fetch();

// ------------------------------------------------------------
// 2) ACTIVE MEMBERSHIP — whether it exists + plan detail
// ------------------------------------------------------------
$stmt = $pdo->prepare(
    "SELECT m.start_date, m.end_date, m.status,
            mp.name AS plan_name, mp.duration_type, mp.price
     FROM memberships m
     JOIN membership_plans mp ON mp.id = m.plan_id
     WHERE m.user_id = ? AND m.status = 'active' AND m.end_date >= CURDATE()
     ORDER BY m.end_date DESC
     LIMIT 1"
);
$stmt->execute([$uid]);
$membership = $stmt->fetch();   // active plan, or false

// ------------------------------------------------------------
// 3) UPCOMING BOOKINGS — the nearest 5
// ------------------------------------------------------------
$stmt = $pdo->prepare(
    "SELECT b.booking_type, b.status,
            ts.slot_date, ts.start_time, ts.end_time,
            u.name AS trainer_name
     FROM bookings b
     JOIN time_slots ts ON ts.id = b.time_slot_id
     LEFT JOIN users u  ON u.id  = b.trainer_id
     WHERE b.user_id = ? AND ts.slot_date >= CURDATE()
       AND b.status <> 'cancelled'
     ORDER BY ts.slot_date, ts.start_time
     LIMIT 5"
);
$stmt->execute([$uid]);
$upcoming = $stmt->fetchAll();

// ------------------------------------------------------------
// 4) LATEST WORKOUT PLANS — 3 of them
// ------------------------------------------------------------
$stmt = $pdo->prepare(
    "SELECT wp.title, wp.details, wp.created_at, u.name AS assigned_by_name
     FROM workout_plans wp
     LEFT JOIN users u ON u.id = wp.assigned_by
     WHERE wp.member_id = ?
     ORDER BY wp.created_at DESC
     LIMIT 3"
);
$stmt->execute([$uid]);
$plans = $stmt->fetchAll();

// ------------------------------------------------------------
// Small helper: badge color class based on booking status
// ------------------------------------------------------------
function status_badge(string $status): string
{
    return match ($status) {
        'approved'  => 'badge badge-open',       // green
        'completed' => 'badge badge-done',       // blue
        'cancelled' => 'badge badge-full',       // red
        default     => 'badge badge-pending',    // yellow (pending)
    };
}

$pageTitle = 'Member Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Welcome, <?= e($name) ?></h1>
</div>

<!-- ================= STAT CARDS ================= -->
<div class="stat-grid">

    <div class="stat-card">
        <div class="stat-label">Membership</div>
        <?php if ($membership): ?>
            <div class="stat-value stat-ok"><?= e($membership['plan_name']) ?></div>
            <div class="stat-sub">
                Expires <?= e(date('M j, Y', strtotime($membership['end_date']))) ?>
            </div>
        <?php else: ?>
            <div class="stat-value stat-warn">Not Active</div>
            <div class="stat-sub">
                <a href="<?= BASE_URL ?>/member/membership.php">Choose a plan →</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="stat-card">
        <div class="stat-label">Upcoming Bookings</div>
        <div class="stat-value"><?= (int) $stats['upcoming_bookings'] ?></div>
        <div class="stat-sub"><?= (int) $stats['total_bookings'] ?> total</div>
    </div>

    <div class="stat-card">
        <div class="stat-label">Workout Plans</div>
        <div class="stat-value"><?= (int) $stats['total_plans'] ?></div>
        <div class="stat-sub">
            <a href="<?= BASE_URL ?>/member/workout_plans.php">View all →</a>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-label">My Trainer</div>
        <div class="stat-value"><?= (int) $stats['trainer_count'] ?></div>
        <div class="stat-sub">assigned</div>
    </div>

</div>

<!-- ================= QUICK ACTIONS ================= -->
<div class="quick-actions">
    <a href="<?= BASE_URL ?>/member/book.php" class="btn btn-primary">+ Book a Session</a>
    <a href="<?= BASE_URL ?>/member/membership.php" class="btn btn-outline">Membership</a>
    <a href="<?= BASE_URL ?>/profile.php" class="btn btn-outline">Edit Profile</a>
</div>

<!-- ================= UPCOMING BOOKINGS ================= -->
<div class="card">
    <div class="card-head">
        <h2 class="card-title">Upcoming Bookings</h2>
        <a href="<?= BASE_URL ?>/member/bookings.php" class="link-sm">See all</a>
    </div>

    <?php if (empty($upcoming)): ?>
        <p class="muted">No upcoming bookings.
           <a href="<?= BASE_URL ?>/member/book.php">Book one →</a></p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr><th>Date</th><th>Time</th><th>Type</th><th>Trainer</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php foreach ($upcoming as $b): ?>
                <tr>
                    <td><?= e(date('D, M j', strtotime($b['slot_date']))) ?></td>
                    <td>
                        <?= e(date('g:i A', strtotime($b['start_time']))) ?> –
                        <?= e(date('g:i A', strtotime($b['end_time']))) ?>
                    </td>
                    <td>
                        <?= $b['booking_type'] === 'trainer_appointment'
                              ? 'Trainer Appt.' : 'Gym Session' ?>
                    </td>
                    <td><?= e($b['trainer_name'] ?: '—') ?></td>
                    <td><span class="<?= status_badge($b['status']) ?>">
                        <?= e(ucfirst($b['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- ================= LATEST WORKOUT PLANS ================= -->
<div class="card">
    <div class="card-head">
        <h2 class="card-title">Latest Workout Plans</h2>
        <a href="<?= BASE_URL ?>/member/workout_plans.php" class="link-sm">See all</a>
    </div>

    <?php if (empty($plans)): ?>
        <p class="muted">No workout plan has been assigned yet.</p>
    <?php else: ?>
        <?php foreach ($plans as $p): ?>
            <div class="plan-row">
                <div class="plan-title"><?= e($p['title']) ?></div>
                <div class="plan-meta">
                    by <?= e($p['assigned_by_name'] ?: 'Staff') ?>
                    · <?= e(date('M j, Y', strtotime($p['created_at']))) ?>
                </div>
                <div class="plan-details">
                    <?= nl2br(e(mb_strimwidth($p['details'], 0, 160, '…'))) ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>