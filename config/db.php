<?php
// ============================================================
// config/db.php
// Single database connection (PDO) for the whole app.
// Files don't open their own connection — they all use this one.
// ============================================================

class DB
{
    // Holds the one shared connection (singleton).
    private static ?PDO $instance = null;

    public static function conn(): PDO
    {
        // If a connection already exists, return that one — don't create a new one.
        if (self::$instance === null) {

            // ---- Local XAMPP / MAMP settings ----
            $host    = '127.0.0.1';   // localhost
            $port    = '3307';        // XAMPP MySQL (also used by phpMyAdmin)
            $dbname  = 'fitness';     // our database name
            $user    = 'root';        // XAMPP default user
            $pass    = '';            // XAMPP default = empty. MAMP = 'root'
            $charset = 'utf8mb4';

            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";

            $options = [
                // Errors throw exceptions instead of failing silently
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                // Fetch rows as associative arrays: $row['name']
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Use REAL prepared statements (security)
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // If the connection fails — stop everything, show a clean message
                die('Database connection failed: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }
}