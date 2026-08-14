<?php
declare(strict_types=1);

/**
 * SLC AI Sales Agent — Installer
 * -----------------------------------------------------------------
 * Open in the browser: http://localhost/slc-ai-sales/setup.php
 *
 * What it does:
 *   1. Reads DB credentials from .env (fallback to the form on screen)
 *   2. Creates the slc_ai_sales database (IF NOT EXISTS)
 *   3. Creates all tables (idempotent, never drops data)
 *   4. Seeds truthful integration statuses + AI defaults
 *   5. Creates the admin user (bcrypt hashed) if it does not exist yet
 */

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/database/Installer.php';

use SLC\Core\Config;
use SLC\Core\Database;
use SLC\Database\Installer;

$done = false;
$error = null;
$log = [];
$adminEmail = 'gm.shreelabel@gmail.com';
$adminName = 'Shree Label Admin';

if (php_sapi_name() === 'cli') {
    runInstall([
        'host' => Config::get('DB_HOST', '127.0.0.1'),
        'port' => (int) Config::get('DB_PORT', 3306),
        'name' => Config::get('DB_NAME', 'slc_ai_sales'),
        'user' => Config::get('DB_USER', 'root'),
        'pass' => Config::get('DB_PASS', ''),
    ], 'Shree Label Admin', 'gm.shreelabel@gmail.com', 'gm.shreelabel@gmail.com');
    echo "\nInstaller log:\n - " . implode("\n - ", $log) . "\n";
    echo "\nAdmin account created. Sign in at the login page.\n";
    exit(0);
}

// ----- Web request -----
if (($_POST['action'] ?? '') === 'install') {
    $db = [
        'host' => trim($_POST['db_host'] ?? Config::get('DB_HOST', '127.0.0.1')),
        'port' => (int) ($_POST['db_port'] ?? Config::get('DB_PORT', 3306)),
        'name' => trim($_POST['db_name'] ?? Config::get('DB_NAME', 'slc_ai_sales')),
        'user' => trim($_POST['db_user'] ?? Config::get('DB_USER', 'root')),
        'pass' => (string) ($_POST['db_pass'] ?? Config::get('DB_PASS', '')),
    ];
    $adminEmail = trim($_POST['admin_email'] ?? $adminEmail);
    $adminName  = trim($_POST['admin_name'] ?? $adminName);
    $adminPass  = (string) ($_POST['admin_password'] ?? 'gm.shreelabel@gmail.com');
    try {
        runInstall($db, $adminName, $adminEmail, $adminPass);
        $done = true;
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

function runInstall(array $db, string $adminName, string $adminEmail, string $adminPass): void
{
    global $log;
    $installer = new Installer($db);
    $installer->run();
    if ($adminName && $adminEmail && $adminPass) {
        $installer->ensureAdminUser($adminName, $adminEmail, $adminPass);
    }
    $log = $installer->log;

    // Detect subfolder path
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath  = ($scriptDir === '/' || $scriptDir === '.') ? '' : $scriptDir;

    // Persist credentials to .env file
    $envPath = SLC_ROOT . '/.env';
    $envLines = [
        "DB_HOST={$db['host']}",
        "DB_PORT={$db['port']}",
        "DB_NAME={$db['name']}",
        "DB_USER={$db['user']}",
        "DB_PASS={$db['pass']}",
        "APP_BASE_PATH={$basePath}",
        "APP_ENV=production",
        "APP_DEBUG=false",
    ];
    @file_put_contents($envPath, implode("\n", $envLines) . "\n");

    // Update runtime environment variables
    \SLC\Core\Env::set('DB_HOST', $db['host']);
    \SLC\Core\Env::set('DB_PORT', (string)$db['port']);
    \SLC\Core\Env::set('DB_NAME', $db['name']);
    \SLC\Core\Env::set('DB_USER', $db['user']);
    \SLC\Core\Env::set('DB_PASS', $db['pass']);
    \SLC\Core\Env::set('APP_BASE_PATH', $basePath);
    Database::disconnect();

    // Verify schema directly on installer PDO connection
    $pdo = Database::connect($db);
    $check = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$db['name']}' AND TABLE_NAME = 'slc_users'");
    if ((int)$check->fetchColumn() === 0) {
        throw new \RuntimeException('Schema verification failed — tables missing.');
    }
}

$configured = [
    'db_host' => Config::get('DB_HOST', 'localhost'),
    'db_port' => Config::get('DB_PORT', 3306),
    'db_name' => Config::get('DB_NAME', 'slc_ai_sales'),
    'db_user' => Config::get('DB_USER', 'root'),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Setup & Installation — Shree Label Digital Sales Person</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', -apple-system, sans-serif; }

  body {
    min-height: 100vh;
    background: #090a0f;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    color: #0f172a;
  }

  .card {
    background: #ffffff;
    border-radius: 20px;
    max-width: 680px;
    width: 100%;
    padding: 40px;
    box-shadow: 0 30px 90px rgba(0, 0, 0, 0.6);
  }

  .brand-header {
    text-align: center;
    margin-bottom: 24px;
  }

  .brand-title {
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
  }

  .brand-sub {
    font-size: 12px;
    font-weight: 700;
    color: #e28743;
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-top: 2px;
  }

  .desc-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px 18px;
    font-size: 13.5px;
    color: #475569;
    margin-bottom: 24px;
    line-height: 1.5;
  }

  .section-title {
    font-size: 14px;
    font-weight: 800;
    color: #0f172a;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin: 20px 0 12px;
    padding-bottom: 6px;
    border-bottom: 2px solid #f1f5f9;
  }

  .form-group {
    margin-bottom: 16px;
  }

  .form-group label {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 6px;
  }

  .form-group input {
    width: 100%;
    padding: 12px 14px;
    border: 1.5px solid #cbd5e1;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 500;
    color: #0f172a;
    background: #ffffff;
    transition: border-color 0.2s, box-shadow 0.2s;
  }

  .form-group input:focus {
    outline: none;
    border-color: #e28743;
    box-shadow: 0 0 0 3.5px rgba(226, 135, 67, 0.15);
  }

  .grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
  }

  .btn-submit {
    margin-top: 24px;
    width: 100%;
    padding: 15px;
    border: none;
    border-radius: 12px;
    background: #e28743;
    color: #ffffff;
    font-size: 16px;
    font-weight: 800;
    cursor: pointer;
    transition: background 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
  }

  .btn-submit:hover {
    background: #d47632;
  }

  .alert {
    padding: 16px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 20px;
  }

  .alert-ok {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #16a34a;
  }

  .alert-err {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #dc2626;
  }

  .log-box {
    background: #0f172a;
    border-radius: 10px;
    padding: 16px;
    font-family: monospace;
    font-size: 12.5px;
    color: #38bdf8;
    max-height: 200px;
    overflow-y: auto;
    margin-top: 14px;
  }

  .log-box div {
    padding: 3px 0;
    border-bottom: 1px solid #1e293b;
  }

  .btn-login {
    display: block;
    text-align: center;
    margin-top: 18px;
    padding: 14px;
    background: #0f172a;
    color: #ffffff;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 700;
    font-size: 15px;
  }

  @media (max-width: 600px) {
    .grid-2 { grid-template-columns: 1fr; }
    .card { padding: 24px; }
  }
</style>
</head>
<body>

<div class="card">
  <div class="brand-header">
    <div class="brand-title">Shree Label</div>
    <div class="brand-sub">Digital Sales Person — Auto Installer</div>
  </div>

  <?php if ($done): ?>
    <div class="alert alert-ok">✅ Installation Completed Successfully! All database tables & Admin account initialized.</div>
    <div class="log-box">
      <?php foreach ($log as $l): ?>
        <div><?= htmlspecialchars($l) ?></div>
      <?php endforeach; ?>
    </div>
    <a class="btn-login" href="login.php?installed=1">Go to Login Page →</a>
  <?php else: ?>

    <div class="desc-box">
      💡 Enter your <b>Hostinger MySQL Database</b> credentials below to automatically create all 19 database tables and set up your initial Administrator account.
    </div>

    <?php if ($error): ?>
      <div class="alert alert-err">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <input type="hidden" name="action" value="install">

      <div class="section-title">1. Hostinger Database Details</div>

      <div class="grid-2">
        <div class="form-group">
          <label for="db_host">Database Host</label>
          <input id="db_host" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? $configured['db_host']) ?>" placeholder="localhost" required>
        </div>

        <div class="form-group">
          <label for="db_port">Database Port</label>
          <input id="db_port" name="db_port" value="<?= htmlspecialchars($_POST['db_port'] ?? (string)$configured['db_port']) ?>" placeholder="3306" required>
        </div>
      </div>

      <div class="form-group">
        <label for="db_name">Database Name</label>
        <input id="db_name" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? $configured['db_name']) ?>" placeholder="e.g. u123456789_shree_sales" required>
      </div>

      <div class="grid-2">
        <div class="form-group">
          <label for="db_user">Database User</label>
          <input id="db_user" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? $configured['db_user']) ?>" placeholder="e.g. u123456789_admin" required>
        </div>

        <div class="form-group">
          <label for="db_pass">Database Password</label>
          <input id="db_pass" name="db_pass" type="password" value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>" placeholder="Hostinger DB Password">
        </div>
      </div>

      <div class="section-title">2. Admin User Credentials</div>

      <div class="form-group">
        <label for="admin_name">Admin Full Name</label>
        <input id="admin_name" name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? $adminName) ?>" required>
      </div>

      <div class="grid-2">
        <div class="form-group">
          <label for="admin_email">Admin Email Address</label>
          <input id="admin_email" name="admin_email" type="email" value="<?= htmlspecialchars($_POST['admin_email'] ?? $adminEmail) ?>" required>
        </div>

        <div class="form-group">
          <label for="admin_password">Admin Password</label>
          <input id="admin_password" name="admin_password" type="password" value="<?= htmlspecialchars($_POST['admin_password'] ?? 'gm.shreelabel@gmail.com') ?>" required>
        </div>
      </div>

      <button type="submit" class="btn-submit">
        <span>🚀 Install System & Initialize Database</span>
      </button>
    </form>

  <?php endif; ?>
</div>

</body>
</html>
