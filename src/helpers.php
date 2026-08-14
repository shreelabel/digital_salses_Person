<?php
declare(strict_types=1);

/** Global view/escape helpers for templates. */

if (!function_exists('e')) {
    /** HTML-escape a value. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('je')) {
    /** JSON-encode + escape for safe embedding in a <script> JSON literal. */
    function je(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    }
}

if (!function_exists('money')) {
    function money($value): string
    {
        $v = (float) $value;
        return '₹' . number_format($v, 0);
    }
}

if (!function_exists('reltime')) {
    function reltime(?string $datetime): string
    {
        if (!$datetime) return '—';
        $t = strtotime($datetime);
        if ($t === false) return '—';
        $diff = time() - $t;
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 2592000) return floor($diff / 86400) . 'd ago';
        return date('M j, Y', $t);
    }
}
