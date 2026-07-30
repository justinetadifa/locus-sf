<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_handle(function (array $container): array {
    $propertyId = int_or_null($_GET['propertyId'] ?? null);
    if ($propertyId === null || $propertyId < 1) {
        throw new InvalidArgumentException('A valid property id is required.');
    }

    $property = $container['properties']->find($propertyId);
    $votes = $container['properties']->voteTallies($propertyId);

    return [
        'summary' => $container['external']->propertySummary($property, $votes),
    ];
});
