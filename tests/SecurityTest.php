<?php
declare(strict_types=1);

namespace SLC\Tests;

use SLC\Core\CSRF;
use SLC\Core\Validator;

class SecurityTest extends TestCase
{
    public function testCsrfTokenIsStableAndVerifiable(): void
    {
        $this->bootSession();
        $t1 = CSRF::token();
        $this->assertNotEmpty($t1);
        $this->assertTrue(CSRF::check($t1));
        $this->assertFalse(CSRF::check('garbage-token'));
    }

    public function testCsrfRejectsEmptyAndBadTokens(): void
    {
        $this->bootSession();
        CSRF::token();
        $this->assertFalse(CSRF::check(''));
        $this->assertFalse(CSRF::check(str_repeat('a', 64)));
    }

    public function testValidatorRequiredAndEmail(): void
    {
        $v = new Validator(['name' => '', 'email' => 'bad']);
        $v->required('name')->email('email');
        $this->assertTrue($v->fails());
        $errs = $v->errors();
        $this->assertNotEmpty($errs['name']);
        $this->assertNotEmpty($errs['email']);
    }

    public function testValidatorInAndInteger(): void
    {
        $v = new Validator(['status' => 'Bogus', 'n' => 999]);
        $v->in('status', ['New', 'Won'])->integer('n', 0, 5);
        $this->assertTrue($v->fails());
    }

    public function testValidatorPassesValidInput(): void
    {
        $v = new Validator(['name' => 'Ok', 'email' => 'a@b.com', 'n' => 3]);
        $v->required('name')->email('email')->integer('n', 0, 5)->in('email', ['a@b.com']);
        $this->assertTrue($v->passes());
    }

    public function testEnvNeverExposesRealKeyByDefault(): void
    {
        // The browser-facing settings view never returns the raw key (tested in
        // AiSettingsTest). Here we assert .env.example carries no real key.
        $env = file_get_contents(SLC_ROOT . '/.env.example');
        $this->assertStringContains('GEMINI_API_KEY=', $env);
        // line must be empty (no value) in the template
        preg_match('/^GEMINI_API_KEY=(.*)$/m', $env, $m);
        $this->assertEquals('', trim($m[1] ?? 'NOTEMPTY'));
    }
}
