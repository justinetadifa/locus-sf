<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_handle(function (array $container): array {
    return [
        'snapshot' => $container['external']->marketSnapshot(),
    ];
});
