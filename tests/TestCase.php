<?php
declare(strict_types=1);

namespace SLC\Tests;

use SLC\Core\Session;

/**
 * Minimal test case with assertion helpers + a session stub so Auth can be
 * exercised in CLI. Designed to run under the bundled TestRunner (no phpunit
 * dependency) while staying PHPUnit-compatible in spirit.
 */
abstract class TestCase
{
    public int $assertions = 0;
    public array $errors = [];

    /** Set up before each test method. Override as needed. */
    protected function setUp(): void
    {
    }

    /** Public entry-point the TestRunner calls (keeps setUp() protected). */
    public function runSetUp(): void
    {
        $this->setUp();
    }

    protected function pass(string $msg = ''): void
    {
        $this->assertions++;
    }

    protected function fail(string $message): void
    {
        throw new \AssertionError($message);
    }

    protected function assertTrue($cond, string $msg = 'expected true'): void
    {
        $this->assertions++;
        if ($cond !== true) {
            throw new \AssertionError($msg);
        }
    }

    protected function assertFalse($cond, string $msg = 'expected false'): void
    {
        $this->assertions++;
        if ($cond !== false) {
            throw new \AssertionError($msg);
        }
    }

    protected function assertEquals($expected, $actual, string $msg = ''): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            throw new \AssertionError($msg ?: "Expected " . json_encode($expected) . " got " . json_encode($actual));
        }
    }

    protected function assertCount(int $expected, $arr, string $msg = ''): void
    {
        $this->assertions++;
        $c = is_countable($arr) ? count($arr) : 0;
        if ($c !== $expected) {
            throw new \AssertionError($msg ?: "Expected count {$expected} got {$c}");
        }
    }

    protected function assertNotEmpty($value, string $msg = 'expected non-empty'): void
    {
        $this->assertions++;
        if (empty($value)) {
            throw new \AssertionError($msg);
        }
    }

    protected function assertEmpty($value, string $msg = 'expected empty'): void
    {
        $this->assertions++;
        if (!empty($value)) {
            throw new \AssertionError($msg);
        }
    }

    protected function assertNull($value, string $msg = 'expected null'): void
    {
        $this->assertions++;
        if ($value !== null) {
            throw new \AssertionError($msg);
        }
    }

    protected function assertNotNull($value, string $msg = 'expected not null'): void
    {
        $this->assertions++;
        if ($value === null) {
            throw new \AssertionError($msg);
        }
    }

    protected function assertContains($needle, array $haystack, string $msg = ''): void
    {
        $this->assertions++;
        if (!in_array($needle, $haystack, true)) {
            throw new \AssertionError($msg ?: "Expected array to contain " . json_encode($needle));
        }
    }

    protected function assertArrayNotHasKey($key, array $array, string $msg = ''): void
    {
        $this->assertions++;
        if (array_key_exists($key, $array)) {
            throw new \AssertionError($msg ?: "Expected array NOT to have key '{$key}'");
        }
    }

    protected function assertStringContains(string $needle, string $haystack, string $msg = ''): void
    {
        $this->assertions++;
        if (strpos($haystack, $needle) === false) {
            throw new \AssertionError($msg ?: "Expected string to contain '{$needle}'");
        }
    }

    protected function assertStringNotContains(string $needle, string $haystack, string $msg = ''): void
    {
        $this->assertions++;
        if (strpos($haystack, $needle) !== false) {
            throw new \AssertionError($msg ?: "Expected string NOT to contain '{$needle}'");
        }
    }

    protected function assertGreaterThan($expected, $actual, string $msg = ''): void
    {
        $this->assertions++;
        if (!($actual > $expected)) {
            throw new \AssertionError($msg ?: "Expected {$actual} > {$expected}");
        }
    }

    /** CLI-safe session start so Auth logic can be tested. */
    protected function bootSession(): void
    {
        Session::start();
    }
}
