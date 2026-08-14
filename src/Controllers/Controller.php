<?php
declare(strict_types=1);

namespace SLC\Controllers;

use SLC\Core\Auth;
use SLC\Core\Response;
use SLC\Core\Validator;

/**
 * Base controller: request helpers shared by all controllers.
 */
abstract class Controller
{
    /** Read JSON request body (or fall back to form data) merged with $_GET. */
    protected function input(): array
    {
        $body = [];
        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }
        // POST form fields also accepted
        if (!empty($_POST)) {
            $body = array_merge($_POST, $body);
        }
        return $body;
    }

    protected function query(): array
    {
        return $_GET;
    }

    protected function intParam(string $key, ?int $default = null): ?int
    {
        $v = $_GET[$key] ?? null;
        if ($v === null || $v === '') {
            return $default;
        }
        return (int) $v;
    }

    protected function userId(): ?int
    {
        return Auth::id();
    }

    protected function activity(string $type, string $desc, ?int $companyId = null, ?int $leadId = null): void
    {
        Auth::logActivity($type, $desc, $companyId, $leadId);
    }
}
