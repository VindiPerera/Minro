<?php
/**
 * Minro POS - Database Configuration
 */

// Check if installed, otherwise load generated config
if (file_exists(__DIR__ . '/db_config.php')) {
    require_once __DIR__ . '/db_config.php';
} else {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'minro_pos');
    define('INSTALLED', false);
}

// -------------------------------------------------------
// Application Constants
// -------------------------------------------------------
define('APP_NAME',    'Minro POS');
define('APP_VERSION', '1.0.0');
define('BASE_PATH',   dirname(__DIR__));
define('BASE_URL',    (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/'));

// -------------------------------------------------------
// Database Connection (PDO Singleton)
// -------------------------------------------------------
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER, DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:40px;text-align:center;background:#0f172a;color:#ef4444;min-height:100vh;">
                <h2>Database Connection Failed</h2><p>' . htmlspecialchars($e->getMessage()) . '</p>
                <a href="/setup/install.php" style="color:#60a5fa">Run Installer</a></div>');
        }
    }
    return $pdo;
}

// -------------------------------------------------------
// Load App Settings from DB
// -------------------------------------------------------
function getSettings(): array {
    static $settings = null;
    if ($settings === null) {
        try {
            $db = getDB();
            $rows = $db->query("SELECT setting_key, setting_value FROM app_settings")->fetchAll();
            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            $settings = [];
        }
    }
    return $settings;
}

function setting(string $key, string $default = ''): string {
    $s = getSettings();
    return $s[$key] ?? $default;
}
