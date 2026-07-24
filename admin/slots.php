<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(1);

$pdo = DB::conn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $date = $_POST['slot_date'] ?? '';
        $start = $_POST['start_time'] ?? '';
        $end = $_POST['end_time'] ?? '';
        $capacity = max(1, (int) ($_POST['capacity'] ?? 1));

        if (!$date || !$start || !$end || $start >= $end) {
            flash('error', 'Please enter a valid date, time range, and capacity.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO time_slots (slot_date, start_time, end_time, capacity) VALUES (?, ?, ?, ?)");
                $stmt->execute([$date, $start, $end, $capacity]);
                flash('success', 'Time slot created.');
            } catch (PDOException $e) {
                flash('error', $e->getCode() === '23000' ? 'That time slot already exists.' : 'Could not create time slot.');
            }
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['slot_id'] ?? 0);

        try {
            $pdo->beginTransaction();

            // Cancel any active bookings tied to this slot instead of deleting them,
            // so booking history/records are preserved
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE time_slot_id = ? AND status <> 'cancelled'");
            $stmt->execute([$id]);
            $cancelledCount = $stmt->rowCount();

            $stmt = $pdo->prepare("DELETE FROM time_slots WHERE id = ?");
            $stmt->execute([$id]);
            $deleted = $stmt->rowCount() > 0;

            $pdo->commit();

            if ($deleted) {
                $msg = $cancelledCount > 0
                    ? "Time slot deleted. {$cancelledCount} booking(s) were cancelled."
                    : 'Time slot deleted.';
                flash('success', $msg);
            } else {
                flash('error', 'Time slot not found.');
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            flash('error', 'Could not delete time slot.');
        }
    }

    redirect(BASE_URL . '/admin/slots.php');
}

$slots = $pdo->query(
    "SELECT ts.*,
            (SELECT COUNT(*) FROM bookings b WHERE b.time_slot_id = ts.id AND b.status <> 'cancelled') AS booked
     FROM time_slots ts
     ORDER BY ts.slot_date DESC, ts.start_time DESC"
)->fetchAll();

$pageTitle = 'Admin Slots';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head"><h1>Time Slots</h1></div>

<div class="card">
    <h2 class="card-title">Create Slot</h2>
    <form method="post" action="<?= BASE_URL ?>/admin/slots.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="slot-select">
            <div class="form-group"><label>Date</label><input type="date" name="slot_date" min="<?= e(date('Y-m-d')) ?>" required></div>
            <div class="form-group"><label>Start</label><input type="time" name="start_time" required></div>
            <div class="form-group"><label>End</label><input type="time" name="end_time" required></div>
            <div class="form-group"><label>Capacity</label><input type="number" name="capacity" min="1" value="10" required></div>
            <div class="form-group"><label>&nbsp;</label><button class="btn btn-primary" type="submit">Create</button></div>
        </div>
    </form>
</div>

<div class="card">
    <h2 class="card-title">All Slots</h2>
    <?php if (empty($slots)): ?>
        <p class="muted">No slots created yet.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Date</th><th>Time</th><th>Capacity</th><th>Booked</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($slots as $s):
                // Calculate remaining seats
                // Capacity = maximum number of people allowed in the slot
                // Booked = number of active bookings already made
                $remaining = (int) $s['capacity'] - (int) $s['booked'];
            ?>
                <tr>
                    <!-- Display slot date -->
                    <td><?= e(date('D, M j, Y', strtotime($s['slot_date']))) ?></td>

                    <!-- Display start and end time -->
                    <td><?= e(date('g:i A', strtotime($s['start_time']))) ?> - <?= e(date('g:i A', strtotime($s['end_time']))) ?></td>

                    <!-- Display maximum capacity of the slot -->
                    <td><?= (int) $s['capacity'] ?></td>

                    <!-- Display number of users already booked -->
                    <td><?= (int) $s['booked'] ?></td>

                    <!-- Display availability status -->
                    <td>
                        <?php if ($remaining <= 0): ?>
                            <!-- Slot has reached maximum capacity -->
                            <span class="badge badge-full">Full</span>
                        <?php else: ?>
                            <!-- Display available seats remaining -->
                            <span class="badge badge-open"><?= $remaining ?> left</span>
                        <?php endif; ?>
                    </td>

                    <!-- Delete action available for every slot; bookings get cancelled first if any exist -->
                    <td>
                        <form method="post" action="<?= BASE_URL ?>/admin/slots.php"
                              onsubmit="return confirm('<?= (int) $s['booked'] > 0 ? 'This slot has ' . (int) $s['booked'] . ' active booking(s), which will be cancelled. Delete anyway?' : 'Delete this slot?' ?>');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="slot_id" value="<?= (int) $s['id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>