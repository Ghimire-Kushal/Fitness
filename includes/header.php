<?php
// ============================================================
// includes/header.php
// Har page ko top ma include garne. Role anusar nav dekhaucha.
// footer.php le yesle kholeko tags banda garcha.
// ============================================================

require_once __DIR__ . '/auth.php';   // session + current_user() ready

$user = current_user();               // logged-in user, or null
$base = BASE_URL;

// Page title — page le $pageTitle set garyo bhane tyo, natra default
$pageTitle = $pageTitle ?? 'Fitness Management System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">
</head>
<body>

    <nav class="navbar">
        <div class="container nav-inner">

            <!-- Brand / logo -->
            <a href="<?= $base ?>/index.php" class="nav-brand">
                Fitness<span>MS</span>
            </a>

            <ul class="nav-links">
            <?php if ($user): ?>

                <?php if ($user['role_id'] === 1): // ---- ADMIN ---- ?>
                    <li><a href="<?= $base ?>/admin/dashboard.php">Dashboard</a></li>
                    <li><a href="<?= $base ?>/admin/users.php">Users</a></li>
                    <li><a href="<?= $base ?>/admin/trainers.php">Trainers</a></li>
                    <li><a href="<?= $base ?>/admin/assign_trainer.php">Assign</a></li>
                    <li><a href="<?= $base ?>/admin/slots.php">Slots</a></li>
                    <li><a href="<?= $base ?>/admin/bookings.php">Bookings</a></li>
                    <li><a href="<?= $base ?>/admin/workout_plans.php">Workouts</a></li>

                <?php elseif ($user['role_id'] === 2): // ---- TRAINER ---- ?>
                    <li><a href="<?= $base ?>/trainer/dashboard.php">Dashboard</a></li>

                <?php else: // ---- MEMBER (role 3) ---- ?>
                    <li><a href="<?= $base ?>/member/dashboard.php">Dashboard</a></li>
                    <li><a href="<?= $base ?>/member/membership.php">Membership</a></li>
                    <li><a href="<?= $base ?>/member/book.php">Book</a></li>
                    <li><a href="<?= $base ?>/member/bookings.php">My Bookings</a></li>
                    <li><a href="<?= $base ?>/member/workout_plans.php">Workouts</a></li>
                <?php endif; ?>

                <!-- Sabai logged-in user ko lagi common -->
                <li><a href="<?= $base ?>/profile.php">Profile</a></li>
                <li class="nav-user">
                    <?= e($user['name']) ?>
                    <span class="role-badge"><?= role_name($user['role_id']) ?></span>
                </li>
                <li><a href="<?= $base ?>/logout.php" class="nav-logout">Logout</a></li>

            <?php else: // ---- NOT logged in ---- ?>
                <li><a href="<?= $base ?>/login.php">Login</a></li>
                <li><a href="<?= $base ?>/register.php" class="nav-cta">Register</a></li>
            <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Flash messages (success / error) -->
    <div class="container">
        <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success"><?= e($msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash('error')): ?>
            <div class="alert alert-error"><?= e($msg) ?></div>
        <?php endif; ?>
    </div>

    <main>
        <div class="container">
        <!-- Page content tala continue huncha; footer.php le banda garcha -->