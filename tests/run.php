<?php
declare(strict_types=1);

/**
 * Standalone test runner — no Composer/PHPUnit required.
 * Usage:  php tests/run.php
 */
require __DIR__ . '/bootstrap.php';

require __DIR__ . '/TestCase.php';
require __DIR__ . '/TestRunner.php';

exit(\SLC\Tests\TestRunner::run(__DIR__));
