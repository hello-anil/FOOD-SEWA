<?php

declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!function_exists('db')) {
    function db(): mysqli
    {
        static $connection = null;

        if ($connection instanceof mysqli) {
            return $connection;
        }

        $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        $connection->set_charset('utf8mb4');

        return $connection;
    }
}
