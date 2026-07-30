<?php
declare(strict_types=1);

namespace App\Repositories;

use OutOfBoundsException;
use PDO;

final class AuditLogRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function record(?int $actorId, string $actionType, string $entityType, int $entityId, array $metadata = []): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO audit_logs (actor_id, action_type, entity_type, entity_id, metadata)
             VALUES (:actor_id, :action_type, :entity_type, :entity_id, :metadata)'
        );
        $statement->execute([
            'actor_id' => $actorId,
            'action_type' => $this->normalizeActionType($actionType),
            'entity_type' => $this->normalizeEntityType($entityType),
            'entity_id' => $entityId,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        return $this->find((int) $this->pdo->lastInsertId());
    }

    public function latestId(): int
    {
        return (int) $this->pdo->query('SELECT COALESCE(MAX(id), 0) FROM audit_logs')->fetchColumn();
    }

    public function recent(int $limit = 60, string $scope = 'all', ?int $afterId = null): array
    {
        $limit = max(1, min(200, $limit));
        $fetchLimit = max($limit * 3, 60);
        $normalizedScope = $this->normalizeScope($scope);
        $params = [];
        $where = '';

        if ($afterId !== null && $afterId > 0) {
            $where = 'WHERE l.id > :after_id';
            $params['after_id'] = $afterId;
        }

        $statement = $this->pdo->prepare(
            "SELECT
                l.id,
                l.actor_id,
                l.action_type,
                l.entity_type,
                l.entity_id,
                l.metadata,
                l.created_at,
                u.name AS actor_name,
                u.role AS actor_role
             FROM audit_logs l
             LEFT JOIN users u ON u.id = l.actor_id
             {$where}
             ORDER BY l.id DESC
             LIMIT {$fetchLimit}"
        );
        $statement->execute($params);

        $entries = array_map([$this, 'hydrate'], $statement->fetchAll());
        if ($normalizedScope !== 'all') {
            $entries = array_values(array_filter(
                $entries,
                static fn (array $entry): bool => (string) ($entry['scope'] ?? 'all') === $normalizedScope
            ));
        }

        return array_slice($entries, 0, $limit);
    }

    public function forProperty(int $propertyId, int $limit = 40): array
    {
        $propertyId = max(1, $propertyId);
        $limit = max(1, min(120, $limit));
        $entries = $this->recent(max($limit * 4, 80));

        $filtered = array_values(array_filter(
            $entries,
            fn (array $entry): bool => $this->entryMatchesProperty($entry, $propertyId)
        ));

        return array_slice($filtered, 0, $limit);
    }

    public function find(int $logId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                l.id,
                l.actor_id,
                l.action_type,
                l.entity_type,
                l.entity_id,
                l.metadata,
                l.created_at,
                u.name AS actor_name,
                u.role AS actor_role
             FROM audit_logs l
             LEFT JOIN users u ON u.id = l.actor_id
             WHERE l.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $logId]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            throw new OutOfBoundsException('Audit log not found.');
        }

        return $this->hydrate($row);
    }

    private function hydrate(array $row): array
    {
        $metadata = $this->decodeJson($row['metadata'] ?? '{}');
        $actionType = $this->normalizeActionType((string) ($row['action_type'] ?? 'EDIT'));
        $entityType = $this->normalizeEntityType((string) ($row['entity_type'] ?? 'PROPERTY'));
        $badge = $this->badgeFor($actionType, $entityType, $metadata);
        $scope = $this->scopeFor($actionType, $entityType, $metadata, $badge);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'actorId' => isset($row['actor_id']) ? int_or_null($row['actor_id']) : null,
            'actorName' => (string) ($row['actor_name'] ?? ($metadata['actorName'] ?? 'System')),
            'actorRole' => (string) ($row['actor_role'] ?? ($metadata['actorRole'] ?? 'system')),
            'actionType' => $actionType,
            'entityType' => $entityType,
            'entityId' => (int) ($row['entity_id'] ?? 0),
            'metadata' => $metadata,
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'eventType' => (string) ($metadata['eventType'] ?? $actionType),
            'summary' => (string) ($metadata['summary'] ?? $this->defaultSummary($actionType, $entityType, (int) ($row['entity_id'] ?? 0))),
            'targetLabel' => (string) ($metadata['targetLabel'] ?? sprintf('%s: #%d', $entityType, (int) ($row['entity_id'] ?? 0))),
            'badge' => $badge,
            'scope' => $scope,
        ];
    }

    private function normalizeActionType(string $actionType): string
    {
        $normalized = strtoupper(trim($actionType));
        return $normalized !== '' ? $normalized : 'EDIT';
    }

    private function normalizeEntityType(string $entityType): string
    {
        $normalized = strtoupper(trim($entityType));
        return $normalized !== '' ? $normalized : 'PROPERTY';
    }

    private function normalizeScope(string $scope): string
    {
        $normalized = strtolower(trim($scope));
        return in_array($normalized, ['all', 'financials', 'moderation'], true) ? $normalized : 'all';
    }

    private function badgeFor(string $actionType, string $entityType, array $metadata): string
    {
        $explicit = strtoupper(trim((string) ($metadata['badge'] ?? '')));
        if ($explicit !== '') {
            return $explicit;
        }

        if ($actionType === 'DELETE') {
            return 'CRITICAL';
        }

        if ($actionType === 'APPROVE') {
            return 'VERIFIED';
        }

        $eventType = strtoupper((string) ($metadata['eventType'] ?? ''));
        if ($entityType === 'MESSAGE' && ($eventType === 'MSG_RESOLVE' || $eventType === 'MSG_MODERATE')) {
            return 'MODERATED';
        }

        return 'TRACE';
    }

    private function scopeFor(string $actionType, string $entityType, array $metadata, string $badge): string
    {
        $explicit = strtolower(trim((string) ($metadata['streamGroup'] ?? '')));
        if (in_array($explicit, ['all', 'financials', 'moderation'], true)) {
            return $explicit;
        }

        if ($badge === 'CRITICAL' || $badge === 'MODERATED' || $entityType === 'MESSAGE') {
            return 'moderation';
        }

        $fieldList = array_map(
            static fn (mixed $field): string => strtolower((string) $field),
            is_array($metadata['changedFields'] ?? null) ? $metadata['changedFields'] : []
        );
        $financialFields = ['price', 'pricepersqm', 'assessedvaluesqm', 'budget', 'groundtruthmultiplier'];
        foreach ($fieldList as $field) {
            foreach ($financialFields as $financialField) {
                if (str_contains($field, $financialField)) {
                    return 'financials';
                }
            }
        }

        return 'all';
    }

    private function defaultSummary(string $actionType, string $entityType, int $entityId): string
    {
        return sprintf('%s %s #%d', $actionType, $entityType, $entityId);
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

    private function entryMatchesProperty(array $entry, int $propertyId): bool
    {
        if ((string) ($entry['entityType'] ?? '') === 'PROPERTY' && (int) ($entry['entityId'] ?? 0) === $propertyId) {
            return true;
        }

        $metadata = is_array($entry['metadata'] ?? null) ? $entry['metadata'] : [];
        if ((int) ($metadata['propertyId'] ?? 0) === $propertyId) {
            return true;
        }

        foreach (['before', 'after'] as $snapshotKey) {
            $snapshot = is_array($metadata[$snapshotKey] ?? null) ? $metadata[$snapshotKey] : [];
            if ((int) ($snapshot['propertyId'] ?? 0) === $propertyId) {
                return true;
            }
        }

        return false;
    }
}
