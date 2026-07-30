<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use App\Support\JsonData;

api_handle(function (array $container): array {
    $meta = JsonData::meta();
    $user = sfc_current_user();
    $properties = $container['properties']->all($user);
    $propertyIds = array_values(array_filter(array_map(
        static fn (array $property): int => (int) ($property['id'] ?? 0),
        $properties
    )));
    $voteSummaries = $container['votes']->summaryMap($propertyIds);
    $messageSummaries = $container['messages']->propertySummaryMap($propertyIds);
    $properties = $container['decisionEngine']->decorateProperties($properties, [
        'voteSummaries' => $voteSummaries,
        'messageSummaries' => $messageSummaries,
    ]);
    $properties = array_map(
        static fn (array $property): array => $container['clup']->decorateProperty($property),
        $properties
    );
    $meta['services'] = $container['external']->describeServices($meta['services'] ?? []);
    $meta['services']['maps']['viewport'] = $container['properties']->mapViewport($properties);
    $meta['decisionPersonas'] = $container['decisionEngine']->personas();
    $meta['clup'] = $container['clup']->catalog();

    return [
        'meta' => $meta,
        'properties' => $properties,
        'stats' => [
            'activeInquiries' => (int) ($meta['dashboard']['activeInquiries'] ?? 0),
            'globalReach' => (int) ($meta['dashboard']['globalReach'] ?? 0),
            'marketSnapshot' => $meta['services']['marketData'] ?? null,
        ],
        'generatedAt' => gmdate(DATE_ATOM),
    ];
});
