<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_handle(function (array $container): array {
    $propertyId = int_or_null($_GET['propertyId'] ?? null);
    if ($propertyId !== null && $propertyId > 0) {
        $property = $container['properties']->find($propertyId);

        return [
            'weather' => $container['external']->weatherContext(
                (float) ($property['lat'] ?? 16.6208),
                (float) ($property['lng'] ?? 120.3218),
                (string) ($property['barangay'] ?? ($property['city'] ?? 'San Fernando, La Union'))
            ),
        ];
    }

    $lat = float_or_null($_GET['lat'] ?? null);
    $lng = float_or_null($_GET['lng'] ?? null);
    if ($lat === null || $lng === null) {
        throw new InvalidArgumentException('A valid propertyId or lat/lng pair is required.');
    }

    $label = string_or_null($_GET['label'] ?? null) ?? 'San Fernando, La Union';

    return [
        'weather' => $container['external']->weatherContext($lat, $lng, $label),
    ];
});
