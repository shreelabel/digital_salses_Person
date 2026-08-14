<?php
declare(strict_types=1);

namespace SLC\Database;

use SLC\Core\Database;
use SLC\Core\Security;
use SLC\Core\Config;

/**
 * Idempotent installer. Creates the slc_ai_sales database (if permitted),
 * creates every table from schema.sql, and seeds defaults. Safe to re-run:
 * it never drops tables and never resets existing data or passwords.
 */
final class Installer
{
    /** Steps for UI reporting. */
    public array $log = [];

    /**
     * @param array{host:string,port:int,name:string,user:string,pass:string} $db
     */
    public function __construct(private array $db)
    {
    }

    public function run(): void
    {
        $this->ensureDatabase();
        $this->runSchema();
        $this->ensureImportColumns();
        $this->seedIntegrations();
        $this->seedProviders();
        $this->seedAiDefaults();
    }

    /** Ensure safe, non-destructive import columns exist on existing databases. */
    public function ensureImportColumns(): void
    {
        $pdo = Database::connect($this->db);
        $dbName = $this->db['name'];
        $addCol = function (string $table, string $col, string $definition) use ($pdo, $dbName) {
            $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table AND COLUMN_NAME = :col");
            $check->execute([':db' => $dbName, ':table' => $table, ':col' => $col]);
            if ((int)$check->fetchColumn() === 0) {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$definition}");
                $this->log[] = "Added column {$table}.{$col}";
            }
        };

        $addCol('slc_companies', 'assigned_to', 'INT UNSIGNED NULL AFTER `id`');
        $addCol('slc_companies', 'assigned_at', 'DATETIME NULL AFTER `assigned_to`');
        $addCol('slc_companies', 'apollo_account_id', 'VARCHAR(100) NULL AFTER `source`');
        $addCol('slc_companies', 'raw_data', 'JSON NULL AFTER `apollo_account_id`');

        $addCol('slc_contacts', 'assigned_to', 'INT UNSIGNED NULL AFTER `company_id`');
        $addCol('slc_contacts', 'assigned_at', 'DATETIME NULL AFTER `assigned_to`');
        $addCol('slc_contacts', 'apollo_contact_id', 'VARCHAR(100) NULL AFTER `source`');
        $addCol('slc_contacts', 'raw_data', 'JSON NULL AFTER `apollo_contact_id`');

        $addCol('slc_leads', 'assigned_to', 'INT UNSIGNED NULL AFTER `company_id`');
        $addCol('slc_leads', 'assigned_at', 'DATETIME NULL AFTER `assigned_to`');
        $addCol('slc_leads', 'import_batch_id', 'VARCHAR(64) NULL AFTER `notes`');
        $addCol('slc_leads', 'raw_data', 'JSON NULL AFTER `import_batch_id`');

        $addCol('slc_opportunities', 'assigned_to', 'INT UNSIGNED NULL AFTER `company_id`');
        $addCol('slc_opportunities', 'assigned_at', 'DATETIME NULL AFTER `assigned_to`');
        $addCol('slc_followups', 'assigned_to', 'INT UNSIGNED NULL AFTER `company_id`');
        $addCol('slc_followups', 'assigned_at', 'DATETIME NULL AFTER `assigned_to`');
        $addCol('slc_campaigns', 'assigned_to', 'INT UNSIGNED NULL AFTER `id`');
        $addCol('slc_campaigns', 'assigned_at', 'DATETIME NULL AFTER `assigned_to`');
    }

    /** Connect WITHOUT a db name and CREATE DATABASE IF NOT EXISTS. */
    private function ensureDatabase(): void
    {
        $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $this->db['host'], $this->db['port']);
        try {
            $pdo = new \PDO($dsn, $this->db['user'], $this->db['pass'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            $name = '`' . str_replace('`', '``', $this->db['name']) . '`';
            $pdo->exec("CREATE DATABASE IF NOT EXISTS {$name} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->log[] = "Database `{$this->db['name']}` is ready.";
        } catch (\PDOException $e) {
            $this->log[] = 'ERROR creating database: ' . $e->getMessage();
            throw $e;
        }
    }

    private function runSchema(): void
    {
        $sql = file_get_contents(SLC_ROOT . '/database/schema.sql');
        if ($sql === false) {
            throw new \RuntimeException('schema.sql not found.');
        }
        $pdo = Database::connect($this->db);
        // Execute statement-by-statement so multi-statement runs safely.
        $pdo->exec('USE `' . str_replace('`', '``', $this->db['name']) . '`');
        foreach ($this->splitStatements($sql) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || str_starts_with($stmt, '--')) {
                continue;
            }
            $pdo->exec($stmt);
        }
        $this->log[] = 'All tables created / verified.';
    }

    /** Split on ";" but only outside of nothing tricky (no DELIMITER/triggers used). */
    private function splitStatements(string $sql): array
    {
        // strip line comments
        $lines = preg_split('/\r\n|\r|\n/', $sql);
        $clean = [];
        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if ($trimmed !== '' && $trimmed[0] === '-') {
                continue;
            }
            $clean[] = $line;
        }
        $sql = implode("\n", $clean);
        return array_values(array_filter(array_map('trim', explode(';', $sql))));
    }

    private function seedIntegrations(): void
    {
        $rows = [
            ['Gemini', 'gemini', 'Configured through AI Settings', 'Standby'],
            ['FreeLLMAPI', 'freellmapi', 'Standby — not yet implemented', 'Standby'],
            ['9Router', '9router', 'Standby — not yet implemented', 'Standby'],
            ['Gmail', 'gmail', 'No sending is implemented (draft-only app)', 'Not Connected'],
            ['Hunter', 'hunter', 'Not Connected', 'Not Connected'],
            ['Apollo', 'apollo', 'Not Connected', 'Not Connected'],
            ['Google Places', 'google_places', 'Not Connected', 'Not Connected'],
        ];
        $pdo = Database::connect($this->db);
        foreach ($rows as $r) {
            $pdo->exec(
                'INSERT INTO slc_integrations (name, slug, description, status)
                 VALUES (' . $pdo->quote($r[0]) . ', ' . $pdo->quote($r[1]) . ', ' . $pdo->quote($r[2]) . ', ' . $pdo->quote($r[3]) . ')
                 ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)'
            );
        }
        $this->log[] = 'Integrations seeded (truthful statuses only).';
    }

    private function seedProviders(): void
    {
        $pdo = Database::connect($this->db);
        $providers = [
            // slug,        name,          role,       base_url,                                  model,           priority
            ['hunter',      'Hunter',      'discovery', 'https://api.hunter.io/v2',                null,            1],
            ['apollo',      'Apollo',      'enrichment','https://api.apollo.io/v1',                null,            2],
            ['freellmapi',  'FreeLLMAPI',  'ai',        'https://freellmapi-70n3.onrender.com/v1', 'auto',          1],
            ['9router',     '9Router',     'ai',        'https://ninerouter-4qb5.onrender.com/v1', '9ROUTER-COMBO', 2],
            ['gemini',      'Gemini',      'ai',        'https://generativelanguage.googleapis.com/v1beta', 'gemini-3.6-flash', 3],
        ];
        foreach ($providers as $p) {
            [$slug, $name, $role, $base, $model, $pri] = $p;
            $pdo->exec(
                'INSERT INTO slc_provider_config (slug, name, role, enabled, base_url, model, priority, last_status)
                 VALUES (' . $pdo->quote($slug) . ', ' . $pdo->quote($name) . ', ' . $pdo->quote($role) . ', 0, '
                 . $pdo->quote($base) . ', ' . ($model === null ? 'NULL' : $pdo->quote($model)) . ', ' . (int) $pri . ', "Not Connected")
                 ON DUPLICATE KEY UPDATE name = VALUES(name), role = VALUES(role), base_url = VALUES(base_url), priority = VALUES(priority)'
            );
        }
        $this->log[] = 'Provider defaults seeded (all disabled, no keys).';
    }

    private function seedAiDefaults(): void
    {
        $pdo = Database::connect($this->db);
        $defaults = [
            'gemini_model' => Config::geminiModel() ?: 'gemini-3.6-flash',
        ];
        foreach ($defaults as $k => $v) {
            $pdo->exec(
                'INSERT INTO slc_ai_settings (setting_key, setting_value, is_secret)
                 VALUES (' . $pdo->quote($k) . ', ' . $pdo->quote((string) $v) . ', 0)
                 ON DUPLICATE KEY UPDATE setting_key = setting_key'
            );
        }
        $this->log[] = 'AI default settings seeded.';
    }

    /**
     * Create the admin user idempotently. Does NOT overwrite an existing user
     * or reset any password unless $forceReset is true.
     */
    public function ensureAdminUser(string $name, string $email, string $password): array
    {
        $pdo = Database::connect($this->db);
        $exists = $pdo->prepare('SELECT id FROM slc_users WHERE email = :email AND deleted_at IS NULL');
        $exists->execute([':email' => strtolower(trim($email))]);
        if ($exists->fetch()) {
            $this->log[] = 'Admin user already exists — left unchanged.';
            return ['created' => false];
        }
        $hash = Security::hashPassword($password);
        $pdo->prepare(
            'INSERT INTO slc_users (name, email, password_hash, role, is_active)
             VALUES (:name, :email, :hash, :role, 1)'
        )->execute([
            ':name'  => $name,
            ':email' => strtolower(trim($email)),
            ':hash'  => $hash,
            ':role'  => 'admin',
        ]);
        $this->log[] = 'Admin user created (password stored as bcrypt hash only).';
        return ['created' => true];
    }
}
