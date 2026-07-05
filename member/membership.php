<?php
// ============================================================
// member/membership.php — Membership plan select / activate
// Features:
//   • Current active plan banner (days left sahit)
//   • Pricing cards (monthly / yearly, "Popular" ribbon)
//   • Smart action: naya activate / different plan ma switch / same plan renew
//   • Transaction le purano cancel + naya insert atomic banaucha
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_role(3);                          // Member matra

$pdo = DB::conn();
$uid = current_user()['id'];

// ------------------------------------------------------------
// Handle plan selection (activate / switch / renew)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $planId = (int) ($_POST['plan_id'] ?? 0);

    // 1) selected plan valid cha?
    $stmt = $pdo->prepare('SELECT * FROM membership_plans WHERE id = ?');
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();

    if (!$plan) {
        flash('error', 'Invalid plan selected.');
        redirect(BASE_URL . '/member/membership.php');
    }

    // 2) currently active membership cha ki?
    $stmt = $pdo->prepare(
        "SELECT * FROM memberships
         WHERE user_id = ? AND status = 'active' AND end_date >= CURDATE()
         ORDER BY end_date DESC LIMIT 1"
    );
    $stmt->execute([$uid]);
    $active = $stmt->fetch();

    $days = (int) $plan['duration_days'];

    try {
        // Sabai kaam ek transaction ma — beech ma fail bhaye kehi save hunna
        $pdo->beginTransaction();

        if ($active && (int) $active['plan_id'] === $planId) {
            // ---- RENEW: same plan → current end_date bata thap ----
            $newEnd = date('Y-m-d', strtotime($active['end_date'] . " +{$days} days"));
            $stmt = $pdo->prepare(
                'UPDATE memberships SET end_date = ? WHERE id = ?'
            );
            $stmt->execute([$newEnd, $active['id']]);
            $msg = 'Membership renewed! New expiry: ' . date('M j, Y', strtotime($newEnd));

        } else {
            // ---- NEW or SWITCH ----
            if ($active) {
                // purano active lai cancel (superseded)
                $stmt = $pdo->prepare(
                    "UPDATE memberships SET status = 'cancelled' WHERE id = ?"
                );
                $stmt->execute([$active['id']]);
            }
            $start = date('Y-m-d');
            $end   = date('Y-m-d', strtotime("+{$days} days"));
            $stmt = $pdo->prepare(
                "INSERT INTO memberships (user_id, plan_id, start_date, end_date, status)
                 VALUES (?, ?, ?, ?, 'active')"
            );
            $stmt->execute([$uid, $planId, $start, $end]);
            $msg = ($active ? 'Plan switched to ' : 'Membership activated: ')
                 . $plan['name'] . '. Valid till ' . date('M j, Y', strtotime($end));
        }

        $pdo->commit();
        flash('success', $msg);

    } catch (Throwable $ex) {
        // kunai error aaye sabai rollback — data consistent rahancha
        $pdo->rollBack();
        flash('error', 'Could not update membership. Please try again.');
    }

    redirect(BASE_URL . '/member/membership.php');
}

// ------------------------------------------------------------
// Load: current active membership (with plan detail)
// ------------------------------------------------------------
$stmt = $pdo->prepare(
    "SELECT m.id, m.start_date, m.end_date,
            mp.id AS plan_id, mp.name AS plan_name, mp.duration_type, mp.price
     FROM memberships m
     JOIN membership_plans mp ON mp.id = m.plan_id
     WHERE m.user_id = ? AND m.status = 'active' AND m.end_date >= CURDATE()
     ORDER BY m.end_date DESC LIMIT 1"
);
$stmt->execute([$uid]);
$current = $stmt->fetch();

// days left calculation
$daysLeft = null;
if ($current) {
    $daysLeft = (int) floor(
        (strtotime($current['end_date']) - strtotime(date('Y-m-d'))) / 86400
    );
}

// ------------------------------------------------------------
// Load: available plans (monthly pahile, ani price le sort)
// ------------------------------------------------------------
$plans = $pdo->query(
    "SELECT * FROM membership_plans
     ORDER BY FIELD(duration_type,'monthly','yearly'), price"
)->fetchAll();

// ------------------------------------------------------------
// Load: membership history (active bahek — past/cancelled/expired)
// ------------------------------------------------------------
$stmt = $pdo->prepare(
    "SELECT m.start_date, m.end_date, m.status, mp.name AS plan_name
     FROM memberships m
     JOIN membership_plans mp ON mp.id = m.plan_id
     WHERE m.user_id = ?
     ORDER BY m.created_at DESC"
);
$stmt->execute([$uid]);
$history = $stmt->fetchAll();

// Helper: plan ko feature list (name anusar generic perks)
function plan_features(array $plan): array
{
    $isPremium = stripos($plan['name'], 'premium') !== false;
    $isYearly  = $plan['duration_type'] === 'yearly';

    $features = ['Full gym equipment access', 'Locker room access'];
    if ($isPremium) {
        $features[] = 'Priority slot booking';
        $features[] = 'Personal trainer sessions';
        $features[] = 'Diet & nutrition consultation';
    } else {
        $features[] = 'Group session access';
    }
    if ($isYearly) {
        $features[] = 'Best value — save vs monthly';
    }
    return $features;
}

$pageTitle = 'Membership';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Membership</h1>
</div>

<!-- ================= CURRENT PLAN BANNER ================= -->
<?php if ($current): ?>
    <div class="mem-banner">
        <div>
            <div class="mem-banner-label">Your Active Plan</div>
            <div class="mem-banner-plan"><?= e($current['plan_name']) ?></div>
            <div class="mem-banner-dates">
                <?= e(date('M j, Y', strtotime($current['start_date']))) ?>
                &rarr;
                <?= e(date('M j, Y', strtotime($current['end_date']))) ?>
            </div>
        </div>
        <div class="mem-banner-days">
            <span class="mem-days-num"><?= max(0, $daysLeft) ?></span>
            <span class="mem-days-txt">days left</span>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        Tapai sanga active membership chaina. Tala bata ek plan choose garnus.
    </div>
<?php endif; ?>

<!-- ================= PRICING CARDS ================= -->
<div class="price-grid">
<?php foreach ($plans as $p):
    $isPremium = stripos($p['name'], 'premium') !== false;
    $isCurrent = $current && (int) $current['plan_id'] === (int) $p['id'];
    $perLabel  = $p['duration_type'] === 'yearly' ? '/year' : '/month';
?>
    <div class="price-card <?= $isPremium ? 'price-popular' : '' ?> <?= $isCurrent ? 'price-current' : '' ?>">
        <?php if ($isPremium): ?><div class="ribbon">Popular</div><?php endif; ?>

        <div class="price-name"><?= e($p['name']) ?></div>
        <div class="price-type badge <?= $p['duration_type'] === 'yearly' ? 'badge-done' : 'badge-open' ?>">
            <?= ucfirst(e($p['duration_type'])) ?>
        </div>

        <div class="price-amount">
            <span class="price-cur">Rs.</span>
            <span class="price-num"><?= number_format((float) $p['price']) ?></span>
            <span class="price-per"><?= $perLabel ?></span>
        </div>

        <ul class="price-features">
        <?php foreach (plan_features($p) as $f): ?>
            <li><?= e($f) ?></li>
        <?php endforeach; ?>
        </ul>

        <?php if ($isCurrent): ?>
            <form method="post" action="<?= BASE_URL ?>/member/membership.php">
                <?= csrf_field() ?>
                <input type="hidden" name="plan_id" value="<?= (int) $p['id'] ?>">
                <button class="btn btn-outline btn-block">Renew Plan</button>
            </form>
            <div class="price-current-tag">Current Plan</div>
        <?php else: ?>
            <form method="post" action="<?= BASE_URL ?>/member/membership.php"
                  onsubmit="return confirm('Select <?= e($p['name']) ?>?');">
                <?= csrf_field() ?>
                <input type="hidden" name="plan_id" value="<?= (int) $p['id'] ?>">
                <button class="btn btn-primary btn-block">
                    <?= $current ? 'Switch to this' : 'Select Plan' ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>

<!-- ================= HISTORY ================= -->
<?php if (!empty($history)): ?>
<div class="card">
    <h2 class="card-title">Membership History</h2>
    <table class="table">
        <thead>
            <tr><th>Plan</th><th>Start</th><th>End</th><th>Status</th></tr>
        </thead>
        <tbody>
        <?php foreach ($history as $h):
            $badge = match ($h['status']) {
                'active'    => 'badge badge-open',
                'expired'   => 'badge badge-pending',
                'cancelled' => 'badge badge-full',
                default     => 'badge',
            };
        ?>
            <tr>
                <td><?= e($h['plan_name']) ?></td>
                <td><?= e(date('M j, Y', strtotime($h['start_date']))) ?></td>
                <td><?= e(date('M j, Y', strtotime($h['end_date']))) ?></td>
                <td><span class="<?= $badge ?>"><?= e(ucfirst($h['status'])) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>