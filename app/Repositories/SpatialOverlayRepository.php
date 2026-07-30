<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SpatialOverlayRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function allActive(array $types = []): array
    {
        $normalizedTypes = array_values(array_filter(array_map(
            fn (mixed $type): string => $this->normalizeKey((string) $type),
            $types
        )));

        $sql = 'SELECT * FROM spatial_overlays WHERE is_active = 1';
        $params = [];
        if ($normalizedTypes !== []) {
            $placeholders = implode(',', array_fill(0, count($normalizedTypes), '?'));
            $sql .= " AND overlay_type IN ({$placeholders})";
            $params = $normalizedTypes;
        }
        $sql .= ' ORDER BY overlay_type ASC, name ASC, id ASC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return array_map(fn (array $row): array => $this->hydrateOverlay($row), $statement->fetchAll());
    }

    public function forProperties(array $properties, array $types = []): array
    {
        if ($properties === []) {
            return [];
        }

        $propertyIds = array_values(array_unique(array_map(
            static fn (array $property): int => (int) ($property['id'] ?? 0),
            $properties
        )));
        $barangayKeys = array_values(array_unique(array_filter(array_map(
            fn (array $property): string => $this->normalizeKey((string) ($property['barangay'] ?? '')),
            $properties
        ))));
        $corridorKeys = array_values(array_unique(array_filter(array_map(
            fn (array $property): string => $this->normalizeKey((string) ($property['corridor'] ?? '')),
            $properties
        ))));

        return array_values(array_filter(
            $this->allActive($types),
            function (array $overlay) use ($propertyIds, $barangayKeys, $corridorKeys): bool {
                $linkedPropertyId = (int) ($overlay['propertyId'] ?? 0);
                $overlayType = (string) ($overlay['overlayType'] ?? '');
                $matchKey = (string) ($overlay['matchKey'] ?? '');

                if ($linkedPropertyId > 0 && in_array($linkedPropertyId, $propertyIds, true)) {
                    return true;
                }

                return match ($overlayType) {
                    'barangay' => $matchKey !== '' && in_array($matchKey, $barangayKeys, true),
                    'corridor' => $matchKey !== '' && in_array($matchKey, $corridorKeys, true),
                    default => false,
                };
            }
        ));
    }

    private function hydrateOverlay(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? 'Spatial Overlay'),
            'slug' => (string) ($row['slug'] ?? ''),
            'overlayType' => (string) ($row['overlay_type'] ?? 'parcel'),
            'matchKey' => (string) ($row['match_key'] ?? ''),
            'geometryType' => (string) ($row['geometry_type'] ?? 'polygon'),
            'geometry' => $this->decodeJson($row['geometry_json'] ?? null),
            'style' => $this->decodeJson($row['style_json'] ?? null),
            'description' => (string) ($row['description'] ?? ''),
            'propertyId' => int_or_null($row['property_id'] ?? null),
            'isActive' => (bool) ($row['is_active'] ?? false),
            'createdAt' => string_or_null($row['created_at'] ?? null) ?? '',
            'updatedAt' => string_or_null($row['updated_at'] ?? null) ?? '',
        ];
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeKey(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/i', '-', strtolower($value)), '-');
    }
}
