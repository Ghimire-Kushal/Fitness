<?php
// ============================================================
// member/workout_plans.php — Member's assigned workout plans (read-only)
// Features:
//   • Summary header (total plans + latest date)
//   • Live search filter (JS, searches by title/trainer)
//   • Clean plan cards — details newline-aware (day-by-day)
//   • Nice empty state
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_role(3);                          // Member only

$pdo = DB::conn();
$uid = current_user()['id'];

// ------------------------------------------------------------
// Load: all of the member's own workout plans (newest first)
// Also fetches the assigned_by name and role
// ------------------------------------------------------------
$stmt = $pdo->prepare(
    "SELECT wp.id, wp.title, wp.details, wp.created_at,
            u.name AS assigned_by_name, r.name AS assigned_by_role
     FROM workout_plans wp
     LEFT JOIN users u ON u.id = wp.assigned_by
     LEFT JOIN roles r ON r.id = u.role_id
     WHERE wp.member_id = ?
     ORDER BY wp.created_at DESC"
);
$stmt->execute([$uid]);
$plans = $stmt->fetchAll();

$total  = count($plans);
$latest = $total > 0 ? $plans[0]['created_at'] : null;

$pageTitle = 'My Workout Plans';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>My Workout Plans</h1>
</div>

<?php if ($total === 0): ?>

    <!-- ================= EMPTY STATE ================= -->
    <div class="empty-state">
        <h2>No workout plans yet</h2>
        <p>You don't have any workout plans yet.
           They'll show up here once a trainer or admin creates one for you.</p>
        <a href="<?= BASE_URL ?>/member/dashboard.php" class="btn btn-primary">Back to Dashboard</a>
    </div>

<?php else: ?>

    <!-- ================= SUMMARY BAR ================= -->
    <div class="wp-summary">
        <div class="wp-summary-item">
            <span class="wp-sum-num"><?= $total ?></span>
            <span class="wp-sum-txt">Total Plan<?= $total === 1 ? '' : 's' ?></span>
        </div>
        <div class="wp-summary-item">
            <span class="wp-sum-num"><?= e(date('M j', strtotime($latest))) ?></span>
            <span class="wp-sum-txt">Latest Update</span>
        </div>
    </div>

    <!-- ================= PLAN CARDS ================= -->
    <div id="wpList" class="wp-list">
    <?php foreach ($plans as $i => $p):
        // data for search (title + trainer) — lowercase
        $searchKey = strtolower($p['title'] . ' ' . ($p['assigned_by_name'] ?? ''));
    ?>
        <div class="wp-card" data-search="<?= e($searchKey) ?>">
            <div class="wp-card-head">
                <div class="wp-num">#<?= $total - $i ?></div>
                <div class="wp-card-title-wrap">
                    <div class="wp-title"><?= e($p['title']) ?></div>
                    <div class="wp-meta">
                        <span>by <strong><?= e($p['assigned_by_name'] ?: 'Staff') ?></strong></span>
                        <?php if ($p['assigned_by_role']): ?>
                            <span class="role-badge"><?= e($p['assigned_by_role']) ?></span>
                        <?php endif; ?>
                        <span><?= e(date('M j, Y', strtotime($p['created_at']))) ?></span>
                    </div>
                </div>
            </div>
            <div class="wp-details">
                <?= nl2br(e($p['details'])) ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
