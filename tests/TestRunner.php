<?php
declare(strict_types=1);

namespace SLC\Tests;

/**
 * Discovers every *Test.php in this directory, runs all test* methods,
 * and prints a simple pass/fail report. No external dependency required.
 */
final class TestRunner
{
    public static function run(string $dir): int
    {
        // Snapshot provider config and AI settings to avoid wiping user's keys during test runs
        $providerSnapshot = null;
        $aiSnapshot = null;
        try {
            $providerSnapshot = \SLC\Core\Database::fetchAll('SELECT * FROM slc_provider_config');
            $aiSnapshot = \SLC\Core\Database::fetchAll('SELECT * FROM slc_ai_settings');
        } catch (\Throwable $e) {
            // ignore if tables not present yet
        }

        $files = glob($dir . '/*Test.php');
        sort($files);
        $total = 0; $passed = 0; $failed = 0;
        $failures = [];

        try {
            foreach ($files as $file) {
                $class = 'SLC\\Tests\\' . basename($file, '.php');
                if (!class_exists($class)) {
                    require $file;
                }
                if (!class_exists($class)) {
                    continue;
                }
                $instance = new $class();
                $methods = array_filter(get_class_methods($instance), fn($m) => str_starts_with($m, 'test'));
                foreach ($methods as $method) {
                    $total++;
                    try {
                        $instance->runSetUp();
                        $instance->$method();
                        echo "  \033[32m✓\033[0m {$class}::{$method}\n";
                        $passed++;
                    } catch (\AssertionError $e) {
                        $failed++;
                        $failures[] = ['test' => "{$class}::{$method}", 'msg' => $e->getMessage()];
                        echo "  \033[31m✗\033[0m {$class}::{$method} — {$e->getMessage()}\n";
                    } catch (\Throwable $e) {
                        $failed++;
                        $failures[] = ['test' => "{$class}::{$method}", 'msg' => $e->getMessage()];
                        echo "  \033[31m✗\033[0m {$class}::{$method} — " . get_class($e) . ": {$e->getMessage()}\n";
                    }
                }
            }
        } finally {
            // Restore snapshot
            if ($providerSnapshot !== null) {
                try {
                    foreach ($providerSnapshot as $row) {
                        \SLC\Core\Database::query(
                            'UPDATE slc_provider_config SET enabled = :en, api_key_enc = :k, base_url = :b, model = :m, priority = :p, last_status = :ls, last_tested_at = :lt WHERE id = :id',
                            [
                                'en' => $row['enabled'],
                                'k'  => $row['api_key_enc'],
                                'b'  => $row['base_url'],
                                'm'  => $row['model'],
                                'p'  => $row['priority'],
                                'ls' => $row['last_status'],
                                'lt' => $row['last_tested_at'],
                                'id' => $row['id'],
                            ]
                        );
                    }
                } catch (\Throwable $e) {}
            }
            if ($aiSnapshot !== null) {
                try {
                    foreach ($aiSnapshot as $row) {
                        \SLC\Core\Database::query(
                            'UPDATE slc_ai_settings SET setting_value = :v, is_secret = :sec WHERE id = :id',
                            [
                                'v'   => $row['setting_value'],
                                'sec' => $row['is_secret'] ?? 0,
                                'id'  => $row['id'],
                            ]
                        );
                    }
                } catch (\Throwable $e) {}
            }
        }

        echo "\n" . str_repeat('─', 50) . "\n";
        $color = $failed === 0 ? '32' : '31';
        echo "\033[{$color}m" . ($failed === 0 ? 'PASS' : 'FAIL') . "\033[0m";
        echo " — {$passed}/{$total} tests passed";
        if ($failed) {
            echo ", {$failed} failed";
        }
        echo "\n";

        if ($failures) {
            echo "\nFailures:\n";
            foreach ($failures as $f) {
                echo "  • {$f['test']}: {$f['msg']}\n";
            }
        }
        return $failed === 0 ? 0 : 1;
    }
}
