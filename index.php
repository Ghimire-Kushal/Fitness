<?php
// ============================================================
// index.php — main entry point / landing page
// Logged in → role dashboard. Naya visitor → welcome page.
// ============================================================
require_once __DIR__ . '/includes/auth.php';

// Already logged in bhaye, aafno dashboard ma pathaune
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
        <div class="feature-icon">📝</div>
        <h3>Easy Membership</h3>
        <p>Online register garnus ra monthly ya yearly plan choose garnus.</p>
    </div>
    <div class="feature-card">
        <div class="feature-icon">📅</div>
        <h3>Smart Booking</h3>
        <p>Gym sessions ra trainer appointments book garnus — double-booking automatic rokincha.</p>
    </div>
    <div class="feature-card">
        <div class="feature-icon">🏋️</div>
        <h3>Personal Workouts</h3>
        <p>Trainer le banaeko personalized workout plan aafno dashboard ma herna sakincha.</p>
    </div>
    <div class="feature-card">
        <div class="feature-icon">🔒</div>
        <h3>Secure & Role-Based</h3>
        <p>Admin, Trainer, ra Member — har role le aafno matra access paucha.</p>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>