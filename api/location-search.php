<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_handle(function (array $container): array {
    $query = string_or_null($_GET['q'] ?? null);
    if ($query === null) {
        throw new InvalidArgumentException('A search query is required.');
    }

    return [
        'search' => $container['external']->geocodeSearch($query, $container['properties']->all()),
    ];
});
