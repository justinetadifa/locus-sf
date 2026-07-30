<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_handle(function (array $container): array {
    $limit = int_or_null($_GET['limit'] ?? null) ?? 4;

    return [
        'feed' => $container['external']->newsDigest($limit),
    ];
});
