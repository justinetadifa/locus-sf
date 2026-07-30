<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_handle(function (array $container): array {
    $databaseName = $container['pdo']->query('SELECT DATABASE()')->fetchColumn();
    return [
        'status' => 'ok',
        'database' => [
            'connected' => true,
            'name' => $databaseName,
        ],
        'timestamp' => gmdate(DATE_ATOM),
    ];
});

