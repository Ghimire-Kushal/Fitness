<?php
// test.php — temporary connection check. Delete after testing.
require_once __DIR__ . '/db.php';

try {
    $pdo = DB::conn();
    echo "✅ Connected to the 'fitness' database successfully!<br>";

    // Show how many tables exist
    $count = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = 'fitness'"
    )->fetchColumn();
    echo "Tables found: " . $count . "<br>";

    // Show how many roles (will be 0 since you insert manuallyy)
    $roles = $pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
    echo "Rows in roles table: " . $roles;

} catch (Throwable $e) {
    echo "❌ Connection failed: " . $e->getMessage();
}