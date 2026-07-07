<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(1);

$pdo = DB::conn();
$allowedStatuses = ['pending', 'approved', 'completed', 'cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if (!in_array($status, $allowedStatuses, true)) {
        flash('error', 'Invalid booking status.');
    } else {
        $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $stmt->execute([$status, $bookingId]);
        flash($stmt->rowCount() > 0 ? 'success' : 'error', $stmt->rowCount() > 0 ? 'Booking updated.' : 'Booking not found or unchanged.');
    }
    redirect(BASE_URL . '/admin/bookings.php');
}

$statusFilter = $_GET['status'] ?? '';
$params = [];
$where = '';
if (in_array($statusFilter, $allowedStatuses, true)) {
    $where = 'WHERE b.status = ?';
    $params[] = $statusFilter;
}

$stmt = $pdo->prepare(
    "SELECT b.id, b.booking_type, b.status, b.created_at,
            m.name AS member_name, m.email AS member_email,
            t.name AS trainer_name,
            ts.slot_date, ts.start_time, ts.end_time
     FROM bookings b
     JOIN users m ON m.id = b.user_id
     JOIN time_slots ts ON ts.id = b.time_slot_id
     LEFT JOIN users t ON t.id = b.trainer_id
     $where
     ORDER BY ts.slot_date DESC, ts.start_time DESC"
);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$pageTitle = 'Admin Bookings';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Bookings</h1>
    <div class="quick-actions" style="margin:0;">
        <a class="btn btn-sm <?= $statusFilter === '' ? 'btn-primary' : 'btn-outline' ?>" href="<?= BASE_URL ?>/admin/bookings.php">All</a>
        <?php foreach ($allowedStatuses as $s): ?>
            <a class="btn btn-sm <?= $statusFilter === $s ? 'btn-primary' : 'btn-outline' ?>" href="<?= BASE_URL ?>/admin/bookings.php?status=<?= e($s) ?>"><?= e(ucfirst($s)) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <?php if (empty($bookings)): ?>
        <p class="muted">No bookings found.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Member</th><th>Date</th><th>Time</th><th>Type</th><th>Trainer</th><th>Status</th><th>Change</th></tr></thead>
            <tbody>
            <?php foreach ($bookings as $b): ?>
                <tr>
                    <td><?= e($b['member_name']) ?><br><span class="muted"><?= e($b['member_email']) ?></span></td>
                    <td><?= e(date('D, M j, Y', strtotime($b['slot_date']))) ?></td>
                    <td><?= e(date('g:i A', strtotime($b['start_time']))) ?> - <?= e(date('g:i A', strtotime($b['end_time']))) ?></td>
                    <td><?= $b['booking_type'] === 'trainer_appointment' ? 'Trainer Appt.' : 'Gym Session' ?></td>
                    <td><?= e($b['trainer_name'] ?: '-') ?></td>
                    <td><span class="badge badge-<?= e($b['status']) ?>"><?= e(ucfirst($b['status'])) ?></span></td>
                    <td>
                        <form method="post" action="<?= BASE_URL ?>/admin/bookings.php" class="slot-select">
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
