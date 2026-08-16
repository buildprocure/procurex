<?php
declare(strict_types=1);

/**
 * PHPUnit bootstrap.
 *
 * Integration tests need a real MySQL database, because awardSupplier()
 * is built on mysqli transactions and multi-table writes that cannot be
 * meaningfully faked.
 *
 * Point these at a THROWAWAY database. The suite truncates tables between
 * tests. Never point TEST_MYSQL_DATABASE at production.
 *
 *   TEST_MYSQL_HOST      default: 127.0.0.1
 *   TEST_MYSQL_PORT      default: 3306
 *   TEST_MYSQL_DATABASE  default: ilife_test
 *   TEST_MYSQL_USER      default: root
 *   TEST_MYSQL_PASSWORD  default: (empty)
 *
 * Against the docker-compose stack:
 *   docker compose exec db mysql -uroot -p -e "CREATE DATABASE ilife_test"
 *   docker compose exec db sh -c 'mysqldump -uroot -p --no-data ilife | mysql -uroot -p ilife_test'
 *   docker compose exec app ./vendor/bin/phpunit
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('TEST_SUITE')) {
    define('TEST_SUITE', true);
}

/**
 * Shared connection for the whole run.
 */
function test_db(): mysqli
{
    static $conn = null;

    if ($conn instanceof mysqli) {
        return $conn;
    }

    $host = getenv('TEST_MYSQL_HOST') ?: '127.0.0.1';
    $port = (int) (getenv('TEST_MYSQL_PORT') ?: 3306);
    $db   = getenv('TEST_MYSQL_DATABASE') ?: 'ilife_test';
    $user = getenv('TEST_MYSQL_USER') ?: 'root';
    $pass = getenv('TEST_MYSQL_PASSWORD') ?: '';

    if (in_array($db, ['ilife', 'production', 'prod'], true)) {
        fwrite(STDERR,
            "\nREFUSING TO RUN: TEST_MYSQL_DATABASE is set to '{$db}'.\n" .
            "The suite truncates tables. Use a throwaway database.\n\n"
        );
        exit(1);
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $conn = new mysqli($host, $user, $pass, $db, $port);
    } catch (\Throwable $e) {
        fwrite(STDERR,
            "\nCannot connect to the test database.\n" .
            "  host={$host} port={$port} db={$db} user={$user}\n" .
            "  {$e->getMessage()}\n\n" .
            "Create it and load the schema, then re-run. See tests/bootstrap.php.\n\n"
        );
        exit(1);
    }

    $conn->set_charset('utf8mb4');

    return $conn;
}

/**
 * App code calls App\Core\DB::getConnection(). Point that at the test
 * connection so the class under test talks to the test database without
 * being modified for testability.
 */
if (class_exists(\App\Core\DB::class)) {
    $ref = new ReflectionClass(\App\Core\DB::class);

    foreach (['conn', 'connection', 'instance'] as $propName) {
        if ($ref->hasProperty($propName)) {
            $prop = $ref->getProperty($propName);
            $prop->setAccessible(true);
            $prop->setValue(null, test_db());
            break;
        }
    }
}
