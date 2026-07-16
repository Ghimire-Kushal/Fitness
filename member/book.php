<?php
// ============================================================
// member/book.php — gym session + trainer appointment booking
// Prevents double-booking via: DB UNIQUE (user_id, time_slot_id, booking_type)
//                     + capacity check + per-trainer check.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_role(3);                       // Member only

$pdo = DB::conn();
$uid = current_user()['id'];

// ---------- Handle booking submit ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $slotId    = (int) ($_POST['time_slot_id'] ?? 0);
    $type      = $_POST['booking_type'] ?? '';
    $trainerId = (int) ($_POST['trainer_id'] ?? 0);

    $errors = [];

    // 1) booking type valid?
    if (!in_array($type, ['gym_session', 'trainer_appointment'], true)) {
        $errors[] = 'Invalid booking type.';
    }

    // 2) slot exists & not in the past?
    $slot = null;
    if ($slotId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM time_slots WHERE id = ?');
        $stmt->execute([$slotId]);
        $slot = $stmt->fetch();
    }
    if (!$slot) {
        $errors[] = 'Selected time slot not found.';
    } elseif ($slot['slot_date'] < date('Y-m-d')) {
        $errors[] = 'That time slot is already in the past.';
    }

    // 3) extra checks for a trainer appointment
    $trainerToStore = null;
    if ($type === 'trainer_appointment' && empty($errors)) {
        if ($trainerId <= 0) {
            $errors[] = 'Please choose a trainer for the appointment.';
        } else {
            // is this trainer actually assigned to you?
            $stmt = $pdo->prepare(
                'SELECT 1 FROM member_trainers WHERE member_id = ? AND trainer_id = ?'
            );
            $stmt->execute([$uid, $trainerId]);
            if (!$stmt->fetch()) {
                $errors[] = 'That trainer is not assigned to you.';
            } else {
                // is this trainer already booked for that same slot?
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM bookings
                     WHERE time_slot_id = ? AND trainer_id = ?
                       AND booking_type = 'trainer_appointment'
                       AND status <> 'cancelled'"
                );
                $stmt->execute([$slotId, $trainerId]);
                if ((int) $stmt->fetchColumn() >= 1) {
                    $errors[] = 'This trainer is already booked for that slot.';
                } else {
                    $trainerToStore = $trainerId;
                }
            }
        }
    }

    // 4) capacity check (both booking types count toward slot capacity)
    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM bookings
             WHERE time_slot_id = ? AND status <> 'cancelled'"
        );
        $stmt->execute([$slotId]);
        $booked = (int) $stmt->fetchColumn();
        if ($booked >= (int) $slot['capacity']) {
            $errors[] = 'Sorry, that time slot is already full.';
        }
    }

    if (!empty($errors)) {
        flash('error', implode(' ', $errors));
        redirect(BASE_URL . '/member/book.php');
    }

    // 5) insert — UNIQUE constraint prevents a duplicate from the same member
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO bookings (user_id, time_slot_id, booking_type, trainer_id, status)
             VALUES (?, ?, ?, ?, "pending")'
        );
        $stmt->execute([$uid, $slotId, $type, $trainerToStore]);
        flash('success', 'Booking requested! Status: pending. An admin will approve it.');
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            // duplicate (UNIQUE) violation
            flash('error', 'You have already booked this slot for that booking type.');
        } else {
            flash('error', 'Could not create booking. Please try again.');
        }
    }
    redirect(BASE_URL . '/member/book.php');
}

// ---------- Load data for display ----------

// upcoming slots + how many are already booked
$slots = $pdo->query(
    "SELECT ts.*,
        (SELECT COUNT(*) FROM bookings b
         WHERE b.time_slot_id = ts.id AND b.status <> 'cancelled') AS booked
     FROM time_slots ts
     WHERE ts.slot_date >= CURDATE()
     ORDER BY ts.slot_date, ts.start_time"
)->fetchAll();

// trainers assigned to you
$stmt = $pdo->prepare(
    "SELECT u.id, u.name, tp.specialization
     FROM member_trainers mt
     JOIN users u ON u.id = mt.trainer_id
     LEFT JOIN trainer_profiles tp ON tp.user_id = u.id
     WHERE mt.member_id = ?
     ORDER BY u.name"
);
$stmt->execute([$uid]);
$myTrainers = $stmt->fetchAll();

$pageTitle = 'Book a Session';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Book a Session</h1>
    <a href="<?= BASE_URL ?>/member/bookings.php" class="btn btn-outline btn-sm">My Bookings</a>
</div>

<?php if (empty($myTrainers)): ?>
    <div class="alert alert-info">
        You don't have a trainer assigned yet — an admin must assign one before
        you can book a trainer appointment. You can still book a gym session.
    </div>
<?php endif; ?>

<?php if (empty($slots)): ?>
    <div class="card">
        <p>No time slots available right now. They'll appear here once an admin adds one.</p>
    </div>
<?php else: ?>

    <div class="slot-grid">
    <?php foreach ($slots as $s):
        $remaining = (int) $s['capacity'] - (int) $s['booked'];
        $isFull    = $remaining <= 0;
    ?>
        <div class="card slot-card <?= $isFull ? 'slot-full' : '' ?>">
            <div class="slot-date">
                <?= e(date('D, M j, Y', strtotime($s['slot_date']))) ?>
            </div>
            <div class="slot-time">
                <?= e(date('g:i A', strtotime($s['start_time']))) ?>
                &ndash;
                <?= e(date('g:i A', strtotime($s['end_time']))) ?>
            </div>

            <div class="slot-cap">
                <?php if ($isFull): ?>
                    <span class="badge badge-full">Full</span>
                <?php else: ?>
                    <span class="badge badge-open"><?= $remaining ?> spot<?= $remaining === 1 ? '' : 's' ?> left</span>
                <?php endif; ?>
            </div>

            <?php if (!$isFull): ?>
            <form method="post" action="<?= BASE_URL ?>/member/book.php" class="slot-form">
                <?= csrf_field() ?>
                <input type="hidden" name="time_slot_id" value="<?= (int) $s['id'] ?>">

                <label class="mini-label">Booking type</label>
                <select name="booking_type" class="slot-field" required>
                    <option value="gym_session">Gym Session</option>
                    <?php if (!empty($myTrainers)): ?>
                        <option value="trainer_appointment">Trainer Appointment</option>
                    <?php endif; ?>
                </select>

                <?php if (!empty($myTrainers)): ?>
                    <label class="mini-label">Trainer (only for appointments)</label>
                    <select name="trainer_id" class="slot-field">
                        <option value="0">— Select trainer —</option>
                        <?php foreach ($myTrainers as $t): ?>
                            <option value="<?= (int) $t['id'] ?>">
                                <?= e($t['name']) ?><?= $t['specialization'] ? ' (' . e($t['specialization']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary btn-block btn-sm">Book</button>
            </form>
            <?php else: ?>
                <p class="slot-full-note">This slot is fully booked.</p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
