<?php
declare(strict_types=1);

namespace SLC\Core;

/**
 * Lightweight input validator. Usage:
 *   $v = new Validator($_POST);
 *   $v->required('name')->maxLength('name', 200)
 *     ->email('email');
 *   if ($v->fails()) { ... $v->errors() ... }
 */
final class Validator
{
    private array $errors = [];

    public function __construct(private array $data)
    {
    }

    public function required(string $field): self
    {
        $val = $this->data[$field] ?? null;
        if ($val === null || (is_string($val) && trim($val) === '')) {
            $this->addError($field, ucfirst($field) . ' is required.');
        }
        return $this;
    }

    public function in(string $field, array $allowed): self
    {
        $val = $this->data[$field] ?? null;
        if ($val !== null && $val !== '' && !in_array($val, $allowed, true)) {
            $this->addError($field, ucfirst($field) . ' has an invalid value.');
        }
        return $this;
    }

    public function maxLength(string $field, int $max): self
    {
        $val = $this->data[$field] ?? null;
        if (is_string($val) && mb_strlen($val) > $max) {
            $this->addError($field, ucfirst($field) . " must be <= {$max} characters.");
        }
        return $this;
    }

    public function minLength(string $field, int $min): self
    {
        $val = $this->data[$field] ?? null;
        if (is_string($val) && mb_strlen($val) < $min) {
            $this->addError($field, ucfirst($field) . " must be >= {$min} characters.");
        }
        return $this;
    }

    public function email(string $field): self
    {
        $val = $this->data[$field] ?? null;
        if ($val !== null && $val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, ucfirst($field) . ' must be a valid email address.');
        }
        return $this;
    }

    public function url(string $field): self
    {
        $val = $this->data[$field] ?? null;
        if ($val !== null && $val !== '' && !filter_var($val, FILTER_VALIDATE_URL)) {
            $this->addError($field, ucfirst($field) . ' must be a valid URL.');
        }
        return $this;
    }

    public function integer(string $field, ?int $min = null, ?int $max = null): self
    {
        $val = $this->data[$field] ?? null;
        if ($val === null || $val === '') {
            return $this;
        }
        if (!filter_var($val, FILTER_VALIDATE_INT) && $val !== '0' && $val !== 0) {
            $this->addError($field, ucfirst($field) . ' must be a whole number.');
            return $this;
        }
        $i = (int) $val;
        if ($min !== null && $i < $min) {
            $this->addError($field, ucfirst($field) . " must be >= {$min}.");
        }
        if ($max !== null && $i > $max) {
            $this->addError($field, ucfirst($field) . " must be <= {$max}.");
        }
        return $this;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private function addError(string $field, string $msg): void
    {
        $this->errors[$field][] = $msg;
    }
}
