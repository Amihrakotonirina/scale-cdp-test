<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{

    // Return a PDO connexion to the bdd, use env variables
    public static function connection(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: '127.0.0.1',
            getenv('DB_PORT') ?: '3306',
            getenv('DB_NAME') ?: 'cdp_test'
        );

        return new PDO(
            $dsn,
            getenv('DB_USER') ?: 'root',
            getenv('DB_PASSWORD') ?: ''
        );
    }
}