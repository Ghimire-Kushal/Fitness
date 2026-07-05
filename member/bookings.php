
<?php
// ============================================================
// member/bookings.php — "My Bookings"
// Sabai booking list, status tracking, ani cancel garne suvidha.
// Upcoming ra Past alag section ma dekhaucha.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_role(3);                         // Member matra

$pdo = DB::conn();
$uid = current_user()['id'];

// ------------------------------------------------------------
// Handle CANCEL — pending/approved upcoming booking matra cancel huncha
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    csrf_check();

    $bookingId = (int) ($_POST['booking_id'] ?? 0);

    // Multi-table UPDATE: aafno booking, cancellable status, ani upcoming matra
    $stmt = $pdo->prepare(
        "UPDATE bookings b
         JOIN time_slots ts ON ts.id = b.time_slot_id
         SET b.status = 'cancelled'
         WHERE b.id = ?
           AND b.user_id = ?
           AND b.status IN ('pending', 'approved')
           AND ts.slot_date >= CURDATE()"
    );
    $stmt->execute([$bookingId, $uid]);

    // rowCount() > 0 bhaye matra safal — natra invalid/already-done booking thiyo
    if ($stmt->rowCount() > 0) {
        flash('success', 'Booking cancelled successfully.');
    } else {
        flash('error', 'That booking could not be cancelled.');
    }
    redirect(BASE_URL . '/member/bookings.php');
}

// ------------------------------------------------------------
// Sabai booking ek query ma load (efficient) — pachi PHP ma split
// ------------------------------------------------------------
$stmt = $pdo->prepare(
    "SELECT b.id, b.booking_type, b.status, b.created_at,
            ts.slot_date, ts.start_time, ts.end_time,
            u.name AS trainer_name
     FROM bookings b
     JOIN time_slots ts ON ts.id = b.time_slot_id
     LEFT JOIN users u  ON u.id  = b.trainer_id
     WHERE b.user_id = ?
     ORDER BY ts.slot_date DESC, ts.start_time DESC"
);
$stmt->execute([$uid]);
$all = $stmt->fetchAll();

// Upcoming ra Past ma छुट्याउने
$today    = date('Y-m-d');
$upcoming = [];
$past     = [];
foreach ($all as $b) {
    if ($b['slot_date'] >= $today) {
        $upcoming[] = $b;
    } else {
        $past[] = $b;
    }
}
// Upcoming lai najik-dekhi-tadha (ascending) banaune
$upcoming = array_reverse($upcoming);

// ------------------------------------------------------------
// Helper: status anusar badge color (dashboard.php sanga milcha)
// function_exists le double-declare error rokcha
// ------------------------------------------------------------
if (!function_exists('status_badge')) {
    function status_badge(string $status): string
    {
        return match ($status) {
            'approved'  => 'badge badge-open',       // green
            'completed' => 'badge badge-done',       // blue
            'cancelled' => 'badge badge-full',       // red
            default     => 'badge badge-pending',    // yellow (pending)
        };
    }
}

// Chota helper: ek booking row ko HTML (upcoming/past dubai ma reuse)
function booking_row(array $b, bool $canCancel): void
{
    $typeLabel = $b['booking_type'] === 'trainer_appointment'
        ? 'Trainer Appt.' : 'Gym Session';
    ?>
    <tr>
        <td><?= e(date('D, M j, Y', strtotime($b['slot_date']))) ?></td>
        <td>
            <?= e(date('g:i A', strtotime($b['start_time']))) ?> –
            <?= e(date('g:i A', strtotime($b['end_time']))) ?>
        </td>
        <td><?= $typeLabel ?></td>
        <td><?= e($b['trainer_name'] ?: '—') ?></td>
        <td><span class="<?= status_badge($b['status']) ?>">
            <?= e(ucfirst($b['status'])) ?></span></td>
        <td>
            <?php if ($canCancel && in_array($b['status'], ['pending', 'approved'], true)): ?>
                <form method="post" action="<?= BASE_URL ?>/member/bookings.php"
                      onsubmit="return confirm('Cancel this booking?');"
                      style="margin:0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Cancel</button>
                </form>
            <?php else: ?>
                <span class="muted">—</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php
}

$pageTitle = 'My Bookings';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>My Bookings</h1>
    <a href="<?= BASE_URL ?>/member/book.php" class="btn btn-primary btn-sm">+ New Booking</a>
</div>

<!-- ================= UPCOMING ================= -->
<div class="card">
    <h2 class="card-title">Upcoming</h2>
    <?php if (empty($upcoming)): ?>
        <p class="muted">Kunai upcoming booking chaina.
           <a href="<?= BASE_URL ?>/member/book.php">Book one →</a></p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr><th>Date</th><th>Time</th><th>Type</th><th>Trainer</th>
                    <th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php foreach ($upcoming as $b) booking_row($b, true); ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- ================= PAST / HISTORY ================= -->
<div class="card">
    <h2 class="card-title">Past & History</h2>
    <?php if (empty($past)): ?>
        <p class="muted">No past bookings yet.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr><th>Date</th><th>Time</th><th>Type</th><th>Trainer</th>
                    <th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php foreach ($past as $b) booking_row($b, false); ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>