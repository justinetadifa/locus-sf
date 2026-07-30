<?php
declare(strict_types=1);

namespace App\Repositories;

use InvalidArgumentException;
use OutOfBoundsException;
use PDO;

final class ShowcaseRepository
{
    private const FEATURE_TYPES = ['offer_board', 'city_pipeline'];
    private const DEFAULT_IMAGE = 'assets/images/Property10.png';

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(?array $user = null, ?string $featureType = null): array
    {
        $params = [];
        $clauses = [];

        $normalizedFeatureType = $this->normalizeFeatureType($featureType, true);
        if ($normalizedFeatureType !== null) {
            $clauses[] = 's.feature_type = :feature_type';
            $params['feature_type'] = $normalizedFeatureType;
        }

        if (($user['role'] ?? null) !== 'admin') {
            $clauses[] = 's.is_published = 1';
        }

        $sql = $this->baseSelect();
        if ($clauses !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }
        $sql .= ' ORDER BY s.feature_type ASC, s.is_featured DESC, s.sort_order ASC, s.created_at DESC, s.id DESC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return array_map([$this, 'hydrate'], $statement->fetchAll());
    }

    public function find(int $showcaseId, ?array $user = null): array
    {
        $params = ['id' => $showcaseId];
        $sql = $this->baseSelect() . ' WHERE s.id = :id';
        if (($user['role'] ?? null) !== 'admin') {
            $sql .= ' AND s.is_published = 1';
        }
        $sql .= ' LIMIT 1';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        if (!is_array($row)) {
            throw new OutOfBoundsException('Showcase item not found.');
        }

        return $this->hydrate($row);
    }

    public function create(array $payload, ?array $actor = null): array
    {
        $item = $this->normalizePayload($payload, null, $actor);
        $statement = $this->pdo->prepare(
            'INSERT INTO showcase_items (
                feature_type, title, slug, partner_label, summary, description, category, location_label, barangay,
                status, pipeline_mode, supply_signal, cover_image_url, primary_metric_label, primary_metric_value, secondary_metric_label, secondary_metric_value,
                investor_thesis, ideal_operator, avoidance_note,
                countdown_at, completion_target, related_property_id, is_published, is_featured, sort_order, created_by_user_id
            ) VALUES (
                :feature_type, :title, :slug, :partner_label, :summary, :description, :category, :location_label, :barangay,
                :status, :pipeline_mode, :supply_signal, :cover_image_url, :primary_metric_label, :primary_metric_value, :secondary_metric_label, :secondary_metric_value,
                :investor_thesis, :ideal_operator, :avoidance_note,
                :countdown_at, :completion_target, :related_property_id, :is_published, :is_featured, :sort_order, :created_by_user_id
            )'
        );
        $statement->execute($item);

        return $this->find((int) $this->pdo->lastInsertId(), ['role' => 'admin']);
    }

    public function update(int $showcaseId, array $payload, ?array $actor = null): array
    {
        $existing = $this->find($showcaseId, ['role' => 'admin']);
        $item = $this->normalizePayload($payload, $existing, $actor, $showcaseId);
        $item['id'] = $showcaseId;
        unset($item['created_by_user_id']);

        $statement = $this->pdo->prepare(
            'UPDATE showcase_items
             SET feature_type = :feature_type,
                 title = :title,
                 slug = :slug,
                 partner_label = :partner_label,
                 summary = :summary,
                 description = :description,
                 category = :category,
                 location_label = :location_label,
                 barangay = :barangay,
                 status = :status,
                 pipeline_mode = :pipeline_mode,
                 supply_signal = :supply_signal,
                 cover_image_url = :cover_image_url,
                 primary_metric_label = :primary_metric_label,
                 primary_metric_value = :primary_metric_value,
                 secondary_metric_label = :secondary_metric_label,
                 secondary_metric_value = :secondary_metric_value,
                 investor_thesis = :investor_thesis,
                 ideal_operator = :ideal_operator,
                 avoidance_note = :avoidance_note,
                 countdown_at = :countdown_at,
                 completion_target = :completion_target,
                 related_property_id = :related_property_id,
                 is_published = :is_published,
                 is_featured = :is_featured,
                 sort_order = :sort_order,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute($item);

        return $this->find($showcaseId, ['role' => 'admin']);
    }

    public function delete(int $showcaseId): void
    {
        $statement = $this->pdo->prepare('DELETE FROM showcase_items WHERE id = :id');
        $statement->execute(['id' => $showcaseId]);
    }

    private function baseSelect(): string
    {
        return
            'SELECT
                s.*,
                p.name AS related_property_name
             FROM showcase_items s
             LEFT JOIN properties p ON p.id = s.related_property_id';
    }

    private function hydrate(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'featureType' => (string) ($row['feature_type'] ?? 'offer_board'),
            'title' => (string) ($row['title'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'partnerLabel' => string_or_null($row['partner_label'] ?? null),
            'summary' => (string) ($row['summary'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'category' => string_or_null($row['category'] ?? null),
            'locationLabel' => string_or_null($row['location_label'] ?? null),
            'barangay' => string_or_null($row['barangay'] ?? null),
            'status' => (string) ($row['status'] ?? ''),
            'pipelineMode' => string_or_null($row['pipeline_mode'] ?? null),
            'supplySignal' => string_or_null($row['supply_signal'] ?? null),
            'coverImageUrl' => (string) ($row['cover_image_url'] ?? self::DEFAULT_IMAGE),
            'primaryMetricLabel' => string_or_null($row['primary_metric_label'] ?? null),
            'primaryMetricValue' => string_or_null($row['primary_metric_value'] ?? null),
            'secondaryMetricLabel' => string_or_null($row['secondary_metric_label'] ?? null),
            'secondaryMetricValue' => string_or_null($row['secondary_metric_value'] ?? null),
            'investorThesis' => string_or_null($row['investor_thesis'] ?? null),
            'idealOperator' => string_or_null($row['ideal_operator'] ?? null),
            'avoidanceNote' => string_or_null($row['avoidance_note'] ?? null),
            'countdownAt' => $this->normalizeTimestamp($row['countdown_at'] ?? null),
            'completionTarget' => $this->normalizeTimestamp($row['completion_target'] ?? null),
            'relatedPropertyId' => int_or_null($row['related_property_id'] ?? null),
            'relatedPropertyName' => string_or_null($row['related_property_name'] ?? null),
            'isPublished' => (int) ($row['is_published'] ?? 0) === 1,
            'isFeatured' => (int) ($row['is_featured'] ?? 0) === 1,
            'sortOrder' => (int) ($row['sort_order'] ?? 0),
            'createdByUserId' => int_or_null($row['created_by_user_id'] ?? null),
            'createdAt' => $this->normalizeTimestamp($row['created_at'] ?? null),
            'updatedAt' => $this->normalizeTimestamp($row['updated_at'] ?? null),
        ];
    }

    private function normalizePayload(array $payload, ?array $existing = null, ?array $actor = null, ?int $currentId = null): array
    {
        $featureType = $this->normalizeFeatureType(
            $payload['feature_type'] ?? $payload['featureType'] ?? ($existing['featureType'] ?? null)
        );
        $title = string_or_null($payload['title'] ?? ($existing['title'] ?? null));
        if ($title === null) {
            throw new InvalidArgumentException('Showcase title is required.');
        }

        $summary = string_or_null($payload['summary'] ?? ($existing['summary'] ?? null)) ?? $title;
        $description = string_or_null($payload['description'] ?? ($existing['description'] ?? null)) ?? $summary;
        $relatedPropertyId = int_or_null($payload['related_property_id'] ?? $payload['relatedPropertyId'] ?? ($existing['relatedPropertyId'] ?? null));
        if ($relatedPropertyId !== null && !$this->propertyExists($relatedPropertyId)) {
            throw new InvalidArgumentException('Related property does not exist.');
        }

        $pipelineMode = $featureType === 'city_pipeline'
            ? $this->normalizePipelineMode($payload['pipeline_mode'] ?? $payload['pipelineMode'] ?? ($existing['pipelineMode'] ?? null))
            : null;
        $supplySignal = $featureType === 'city_pipeline'
            ? $this->normalizeSupplySignal(
                $payload['supply_signal'] ?? $payload['supplySignal'] ?? ($existing['supplySignal'] ?? null),
                $pipelineMode !== 'investment_gap'
            )
            : null;

        return [
            'feature_type' => $featureType,
            'title' => $title,
            'slug' => $this->uniqueSlug($payload['slug'] ?? $title, $currentId),
            'partner_label' => string_or_null($payload['partner_label'] ?? $payload['partnerLabel'] ?? ($existing['partnerLabel'] ?? null)),
            'summary' => $summary,
            'description' => $description,
            'category' => string_or_null($payload['category'] ?? ($existing['category'] ?? null)),
            'location_label' => string_or_null($payload['location_label'] ?? $payload['locationLabel'] ?? ($existing['locationLabel'] ?? null)) ?? 'San Fernando, La Union',
            'barangay' => string_or_null($payload['barangay'] ?? ($existing['barangay'] ?? null)),
            'status' => string_or_null($payload['status'] ?? ($existing['status'] ?? null)) ?? $this->defaultStatusForFeature($featureType),
            'pipeline_mode' => $pipelineMode,
            'supply_signal' => $supplySignal,
            'cover_image_url' => string_or_null($payload['cover_image_url'] ?? $payload['coverImageUrl'] ?? $payload['image_url'] ?? ($existing['coverImageUrl'] ?? null)) ?? self::DEFAULT_IMAGE,
            'primary_metric_label' => string_or_null($payload['primary_metric_label'] ?? $payload['primaryMetricLabel'] ?? ($existing['primaryMetricLabel'] ?? null)) ?? $this->defaultPrimaryMetricLabel($featureType),
            'primary_metric_value' => string_or_null($payload['primary_metric_value'] ?? $payload['primaryMetricValue'] ?? ($existing['primaryMetricValue'] ?? null)),
            'secondary_metric_label' => string_or_null($payload['secondary_metric_label'] ?? $payload['secondaryMetricLabel'] ?? ($existing['secondaryMetricLabel'] ?? null)) ?? $this->defaultSecondaryMetricLabel($featureType),
            'secondary_metric_value' => string_or_null($payload['secondary_metric_value'] ?? $payload['secondaryMetricValue'] ?? ($existing['secondaryMetricValue'] ?? null)),
            'investor_thesis' => $featureType === 'city_pipeline'
                ? string_or_null($payload['investor_thesis'] ?? $payload['investorThesis'] ?? ($existing['investorThesis'] ?? null))
                : null,
            'ideal_operator' => $featureType === 'city_pipeline'
                ? string_or_null($payload['ideal_operator'] ?? $payload['idealOperator'] ?? ($existing['idealOperator'] ?? null))
                : null,
            'avoidance_note' => $featureType === 'city_pipeline'
                ? string_or_null($payload['avoidance_note'] ?? $payload['avoidanceNote'] ?? ($existing['avoidanceNote'] ?? null))
                : null,
            'countdown_at' => $this->normalizeDateTimeInput($payload['countdown_at'] ?? $payload['countdownAt'] ?? ($existing['countdownAt'] ?? null)),
            'completion_target' => $this->normalizeDateTimeInput($payload['completion_target'] ?? $payload['completionTarget'] ?? ($existing['completionTarget'] ?? null)),
            'related_property_id' => $relatedPropertyId,
            'is_published' => $this->booleanInt($payload['is_published'] ?? $payload['isPublished'] ?? ($existing['isPublished'] ?? 1)),
            'is_featured' => $this->booleanInt($payload['is_featured'] ?? $payload['isFeatured'] ?? ($existing['isFeatured'] ?? 0)),
            'sort_order' => int_or_null($payload['sort_order'] ?? $payload['sortOrder'] ?? ($existing['sortOrder'] ?? null)) ?? $this->nextSortOrder($featureType),
            'created_by_user_id' => int_or_null($payload['created_by_user_id'] ?? $payload['createdByUserId'] ?? ($existing['createdByUserId'] ?? ($actor['id'] ?? null))),
        ];
    }

    private function normalizeFeatureType(?string $value, bool $allowNull = false): ?string
    {
        $normalized = string_or_null($value);
        if ($normalized === null) {
            if ($allowNull) {
                return null;
            }
            return 'offer_board';
        }

        $normalized = strtolower($normalized);
        if (!in_array($normalized, self::FEATURE_TYPES, true)) {
            throw new InvalidArgumentException('Invalid showcase feature type.');
        }

        return $normalized;
    }

    private function propertyExists(int $propertyId): bool
    {
        $statement = $this->pdo->prepare('SELECT id FROM properties WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $propertyId]);
        return (bool) $statement->fetchColumn();
    }

    private function uniqueSlug(mixed $value, ?int $currentId = null): string
    {
        $base = preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim((string) $value))) ?? '';
        $base = trim($base, '-');
        $base = $base !== '' ? $base : 'showcase-item';
        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($slug, $currentId)) {
            $slug = sprintf('%s-%d', $base, $suffix);
            $suffix++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $currentId = null): bool
    {
        $sql = 'SELECT id FROM showcase_items WHERE slug = :slug';
        $params = ['slug' => $slug];

        if ($currentId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $currentId;
        }

        $sql .= ' LIMIT 1';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return (bool) $statement->fetchColumn();
    }

    private function nextSortOrder(string $featureType): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) + 1
             FROM showcase_items
             WHERE feature_type = :feature_type'
        );
        $statement->execute(['feature_type' => $featureType]);

        return (int) $statement->fetchColumn();
    }

    private function defaultStatusForFeature(string $featureType): string
    {
        return $featureType === 'city_pipeline' ? 'planned' : 'open';
    }

    private function normalizePipelineMode(?string $value): string
    {
        $normalized = strtolower(string_or_null($value) ?? '');
        if ($normalized === '' || $normalized === 'project') {
            return 'future_project';
        }

        if (!in_array($normalized, ['future_project', 'investment_gap'], true)) {
            throw new InvalidArgumentException('Invalid city pipeline mode.');
        }

        return $normalized;
    }

    private function normalizeSupplySignal(?string $value, bool $allowNull = false): ?string
    {
        $normalized = strtolower(string_or_null($value) ?? '');
        if ($normalized === '') {
            return $allowNull ? null : 'under_supplied';
        }

        if (!in_array($normalized, ['not_present', 'under_supplied', 'balanced', 'crowded'], true)) {
            throw new InvalidArgumentException('Invalid supply signal.');
        }

        return $normalized;
    }

    private function defaultPrimaryMetricLabel(string $featureType): string
    {
        return $featureType === 'city_pipeline' ? 'Expected launch' : 'Offer window';
    }

    private function defaultSecondaryMetricLabel(string $featureType): string
    {
        return $featureType === 'city_pipeline' ? 'Development stage' : 'Current offer';
    }

    private function normalizeDateTimeInput(mixed $value): ?string
    {
        $normalized = string_or_null($value);
        if ($normalized === null) {
            return null;
        }

        $timestamp = strtotime($normalized);
        if ($timestamp === false) {
            throw new InvalidArgumentException('Invalid showcase date value.');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function normalizeTimestamp(mixed $value): ?string
    {
        $normalized = string_or_null($value);
        if ($normalized === null) {
            return null;
        }

        return str_replace(' ', 'T', $normalized);
    }

    private function booleanInt(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
    }
}
