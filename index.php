<?php
// ============================================================
// index.php — main entry point / landing page
// Logged in → role dashboard. Naya visitor → welcome page.
// ============================================================
require_once __DIR__ . '/includes/auth.php';

// Already logged in bhaye, aafno dashboard ma pathaunee
if (is_logged_in()) {
    redirect(dashboard_for(current_user()['role_id']));
}

$pageTitle = 'Welcome — Fitness Management System';
require_once __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <h1 class="hero-title">Manage Your Gym, <span>Digitally</span>.</h1>
    <p class="hero-sub">
        Membership, bookings, trainers, ra workout plans —
        sabai euta simple platform ma. Manual register ko jhanjhat khatam.
    </p>
    <div class="hero-actions">
        <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary">Get Started</a>
        <a href="<?= BASE_URL ?>/login.php" class="btn btn-outline">Login</a>
    </div>
</section>

<section class="features">
    <div class="feature-card">
        <h3>Easy Membership</h3>
        <p>Register online and choose a monthly or yearly plan.</p>
    </div>
    <div class="feature-card">
        <h3>Smart Booking</h3>
        <p>Book gym sessions and trainer appointments — double-booking is prevented automatically.</p>
    </div>
    <div class="feature-card">
        <h3>Personal Workouts</h3>
        <p>View the personalized workout plan your trainer built for you, right from your dashboard.</p>
    </div>
    <div class="feature-card">
        <h3>Secure & Role-Based</h3>
        <p>Admin, Trainer, and Member — each role only gets access to what it needs.</p>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>