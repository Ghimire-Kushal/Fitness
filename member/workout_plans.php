<?php
// ============================================================
// member/workout_plans.php — Member ko assigned workout plans (read-only)
// Features:
//   • Summary header (total plans + latest date)
//   • Live search filter (JS, title/trainer le khojne)
//   • Clean plan cards — details newline-aware (day-by-day)
//   • Sundar empty state
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_role(3);                          // Member matra

$pdo = DB::conn();
$uid = current_user()['id'];

// ------------------------------------------------------------
// Load: aafno sabai workout plans (naya pahile)
// assigned_by ko naam ra role pani liyeko
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
        <div class="wp-search-wrap">
            <input type="text" id="wpSearch" class="wp-search"
>
        </div>
    </div>

    <!-- ================= PLAN CARDS ================= -->
    <div id="wpList" class="wp-list">
    <?php foreach ($plans as $i => $p):
        // search ko lagi data (title + trainer) — lowercase
        $searchKey = strtolower($p['title'] . ' ' . ($p['assigned_by_name'] ?? ''));
    ?>
        <div class="wp-card" data-search="<?= e($searchKey) ?>">
            <div class="wp-card-head">
                <div class="wp-num">#<?= $total - $i ?></div>
                <div>
                    <div class="wp-title"><?= e($p['title']) ?></div>
                    <div class="wp-meta">
                        by <strong><?= e($p['assigned_by_name'] ?: 'Staff') ?></strong>
                        <?php if ($p['assigned_by_role']): ?>
                            <span class="role-badge"><?= e($p['assigned_by_role']) ?></span>
                        <?php endif; ?>
                        · <?= e(date('M j, Y', strtotime($p['created_at']))) ?>
                    </div>
                </div>
            </div>
            <div class="wp-details">
                <?= nl2br(e($p['details'])) ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <!-- search le kehi na bhetaye -->
    <div id="wpNoResult" class="muted" style="display:none; padding:1rem;">
        No plans match your search.
    </div>

    <script>
    // Live search filter — server hit nagari, turantai filter garcha
    (function () {
        const input = document.getElementById('wpSearch');
        const cards = document.querySelectorAll('#wpList .wp-card');
        const noRes = document.getElementById('wpNoResult');

        input.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            let visible = 0;
            cards.forEach(function (card) {
                const match = card.dataset.search.includes(q);
                card.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            noRes.style.display = visible === 0 ? 'block' : 'none';
        });
    })();
    </script>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>