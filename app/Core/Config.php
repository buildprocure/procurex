<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimal configuration reader.
 *
 * Values come from the process environment (Docker env_file / docker-compose
 * environment / real env vars). Nothing is hardcoded and nothing is committed.
 *
 * Required for mail:
 *   MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD,
 *   MAIL_FROM_ADDRESS, MAIL_FROM_NAME, MAIL_ENCRYPTION
 *
 * Required for links:
 *   APP_URL   e.g. https://app.buildprocure.com
 *
 * Required for payments (Stripe):
 *   STRIPE_SECRET_KEY, STRIPE_PUBLISHABLE_KEY, STRIPE_WEBHOOK_SECRET
 */
class Config
{
    /** @var array<string,string>|null */
    private static ?array $cache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        if (self::$cache === null) {
            self::$cache = [];
        }

        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $value = getenv($key);

        if ($value === false || $value === '') {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        }

        if ($value === null || $value === '') {
            self::$cache[$key] = $default ?? '';
            return $default;
        }

        self::$cache[$key] = (string) $value;
        return (string) $value;
    }

    /**
     * Fetch a value that the application cannot run without.
     *
     * @throws \RuntimeException when the variable is absent
     */
    public static function require(string $key): string
    {
        $value = self::get($key);

        if ($value === null || $value === '') {
            throw new \RuntimeException(
                "Missing required environment variable: {$key}"
            );
        }

        return $value;
    }

    public static function int(string $key, int $default): int
    {
        $value = self::get($key);
        return ($value === null || $value === '') ? $default : (int) $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    /** Base URL with any trailing slash removed. */
    public static function appUrl(): string
    {
        return rtrim(self::get('APP_URL', 'http://localhost') ?? '', '/');
    }

    /** Reset the cache. Test helper. */
    public static function flush(): void
    {
        self::$cache = null;
    }
}
