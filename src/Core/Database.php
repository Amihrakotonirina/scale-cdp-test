<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{

    // Return a PDO connexion to the bdd, use env variables
    public static function connection(): PDO
    {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: 'cdp_test';
        $user = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASSWORD') ?: '';

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $name
        );

        return new PDO(
            $dsn,
            getenv('DB_USER') ?: 'root',
            getenv('DB_PASSWORD') ?: ''
        );
    }
}