<?php

declare(strict_types=1);

namespace Sample;

/**
 * Tiny .env loader. No third-party dependency.
 *
 * Reads `KEY=value` pairs (one per line, blank lines and `#` comments
 * ignored), populates `$_ENV` and `getenv()`. Values can be quoted with
 * `"..."` or `'...'`; quotes are stripped. No interpolation, no exports —
 * keep it boring.
 */
final class Env
{
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($line, 0, $eq));
            $val = trim(substr($line, $eq + 1));
            if (
                (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))
            ) {
                $val = substr($val, 1, -1);
            }

            // Don't override values already set in the real environment.
            if (getenv($key) !== false) {
                continue;
            }
            $_ENV[$key] = $val;
            putenv("{$key}={$val}");
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $v = getenv($key);
        return $v === false ? $default : $v;
    }
}
