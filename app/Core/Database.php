<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private array $config;
    private ?PDO $pdo = null;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $host = (string) ($this->config['host'] ?? '127.0.0.1');
        $port = (int) ($this->config['port'] ?? 3306);
        $name = (string) ($this->config['name'] ?? '');
        $user = (string) ($this->config['user'] ?? '');
        $pass = (string) ($this->config['pass'] ?? '');
        $charset = (string) ($this->config['charset'] ?? 'utf8mb4');

        try {
            $serverPdo = new PDO(sprintf('mysql:host=%s;port=%d;charset=%s', $host, $port, $charset), $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            if ($name !== '') {
                $serverPdo->exec(
                    sprintf(
                        'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s_unicode_ci',
                        str_replace('`', '``', $name),
                        $charset,
                        $charset
                    )
                );
            }

            $this->pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset),
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            $this->pdo->exec("SET time_zone = '+00:00'");
        } catch (PDOException $exception) {
            throw new RuntimeException(
                'Unable to connect to MySQL. Confirm Apache/MySQL are running in XAMPP and that app/config.local.php matches your local credentials.',
                0,
                $exception
            );
        }

        return $this->pdo;
    }
}
