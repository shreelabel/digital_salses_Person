<?php
declare(strict_types=1);

/**
 * Test bootstrap — runs BEFORE the app bootstrap.
 * Points the app at an ISOLATED test database (slc_ai_sales_test) so the real
 * slc_ai_sales database is never touched. Creates the schema fresh each run.
 */
define('SLC_TESTING', true);

require __DIR__ . '/../src/bootstrap.php';

use SLC\Core\Env;
use SLC\Core\Database;
use SLC\Database\Installer;

Env::set('APP_KEY', 'test-app-key-for-slc-encryption-roundtrip');
Env::set('APP_DEBUG', 'true');
Env::set('DB_NAME', 'slc_ai_sales_test');
Env::set('DB_HOST', '127.0.0.1');
Env::set('DB_PORT', '3306');
Env::set('DB_USER', getenv('DB_USER') ?: 'root');
Env::set('DB_PASS', getenv('DB_PASS') ?: '');
Env::set('GEMINI_API_KEY', '');

Database::disconnect();

// Reset the test database to a known state.
try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;port=' . (getenv('DB_PORT') ?: 3306) . ';charset=utf8mb4',
        getenv('DB_USER') ?: 'root',
        getenv('DB_PASS') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec('DROP DATABASE IF EXISTS slc_ai_sales_test');
} catch (\Throwable $e) {
    // ignore — Installer will create it
}

$installer = new Installer([
    'host' => '127.0.0.1',
    'port' => (int) (getenv('DB_PORT') ?: 3306),
    'name' => 'slc_ai_sales_test',
    'user' => getenv('DB_USER') ?: 'root',
    'pass' => getenv('DB_PASS') ?: '',
]);
$installer->run();

Database::disconnect();

$installer->ensureAdminUser('Test Admin', 'admin@shreelabel.com', 'admin123');
Database::query("UPDATE slc_users SET email = 'admin@shreelabel.com', password_hash = '" . \SLC\Core\Security::hashPassword('admin123') . "' WHERE id = 1");
Database::query("INSERT IGNORE INTO slc_users (id, name, email, password_hash, role, is_active) VALUES (2, 'Sales Executive', 'user@shreelabel.com', '" . \SLC\Core\Security::hashPassword('user123') . "', 'user', 1)");
