<?php
declare(strict_types=1);

namespace App\Repositories;

use InvalidArgumentException;
use OutOfBoundsException;
use PDO;
use Throwable;

final class VoteOptionRepository
{
    private PDO $pdo;
    private ?AuditLogRepository $auditLogs;

    public function __construct(PDO $pdo, ?AuditLogRepository $auditLogs = null)
    {
        $this->pdo = $pdo;
        $this->auditLogs = $auditLogs;
    }

    public function all(bool $includeInactive = false): array
    {
        $where = $includeInactive ? '' : 'WHERE is_active = 1';
        $rows = $this->pdo
            ->query(
                "SELECT id, title, slug, description, image_url, is_active, sort_order, created_by_user_id, created_at, updated_at
                 FROM vote_options
                 {$where}
                 ORDER BY is_active DESC, sort_order ASC, title ASC"
            )
            ->fetchAll();

        return array_map([$this, 'hydrate'], $rows);
    }

    public function find(int $voteOptionId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, title, slug, description, image_url, is_active, sort_order, created_by_user_id, created_at, updated_at
             FROM vote_options
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $voteOptionId]);

        $row = $statement->fetch();
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function resolve(?int $voteOptionId, ?string $label = null): ?array
    {
        if ($voteOptionId !== null && $voteOptionId > 0) {
            return $this->find($voteOptionId);
        }

        $label = string_or_null($label);
        if ($label === null) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT id, title, slug, description, image_url, is_active, sort_order, created_by_user_id, created_at, updated_at
             FROM vote_options
             WHERE LOWER(title) = LOWER(:title) OR slug = :slug
             LIMIT 1'
        );
        $statement->execute([
            'title' => $label,
            'slug' => $this->slug($label),
        ]);

        $row = $statement->fetch();
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function create(array $payload, ?int $createdByUserId = null): array
    {
        $normalized = $this->normalizePayload($payload);
        $statement = $this->pdo->prepare(
            'INSERT INTO vote_options (title, slug, description, image_url, is_active, sort_order, created_by_user_id)
             VALUES (:title, :slug, :description, :image_url, :is_active, :sort_order, :created_by_user_id)'
        );
        $statement->execute([
            ...$normalized,
            'created_by_user_id' => $createdByUserId,
        ]);

        return $this->find((int) $this->pdo->lastInsertId()) ?? $normalized;
    }

    public function update(int $voteOptionId, array $payload): array
    {
        $existing = $this->find($voteOptionId);
        if ($existing === null) {
            throw new InvalidArgumentException('Vote option not found.');
        }

        $normalized = $this->normalizePayload($payload, $existing);
        $statement = $this->pdo->prepare(
            'UPDATE vote_options
             SET title = :title,
                 slug = :slug,
                 description = :description,
                 image_url = :image_url,
                 is_active = :is_active,
                 sort_order = :sort_order
             WHERE id = :id'
        );
        $statement->execute([
            ...$normalized,
            'id' => $voteOptionId,
        ]);

        $labelUpdate = $this->pdo->prepare(
            'UPDATE property_votes
             SET label = :label
             WHERE vote_option_id = :vote_option_id'
        );
        $labelUpdate->execute([
            'label' => $normalized['title'],
            'vote_option_id' => $voteOptionId,
        ]);

        return $this->find($voteOptionId) ?? $existing;
    }

    public function delete(int $voteOptionId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE vote_options
             SET is_active = 0
             WHERE id = :id'
        );
        $statement->execute(['id' => $voteOptionId]);
    }

    public function voteTallies(int $propertyId, ?int $voterUserId = null): array
    {
        $this->assertPropertyExists($propertyId);

        $statement = $this->pdo->prepare(
            'SELECT COALESCE(vo.title, NULLIF(pv.label, \'\')) AS label, COUNT(*) AS votes
             FROM property_votes pv
             LEFT JOIN vote_options vo ON vo.id = pv.vote_option_id
             WHERE pv.property_id = :property_id
             GROUP BY COALESCE(vo.title, NULLIF(pv.label, \'\'))
             ORDER BY votes DESC, label ASC'
        );
        $statement->execute(['property_id' => $propertyId]);

        $votes = [];
        foreach ($statement->fetchAll() as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $votes[$label] = (int) $row['votes'];
        }

        $selectedVoteOptionId = null;
        if ($voterUserId !== null && $voterUserId > 0) {
            $selected = $this->pdo->prepare(
                'SELECT vote_option_id
                 FROM property_votes
                 WHERE property_id = :property_id
                   AND voter_user_id = :voter_user_id
                 LIMIT 1'
            );
            $selected->execute([
                'property_id' => $propertyId,
                'voter_user_id' => $voterUserId,
            ]);
            $selectedVoteOptionId = int_or_null($selected->fetchColumn());
        }

        return [
            'votes' => $votes,
            'selectedVoteOptionId' => $selectedVoteOptionId,
        ];
    }

    public function voteTalliesMap(array $propertyIds, ?int $voterUserId = null): array
    {
        $propertyIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): int => (int) $value, $propertyIds),
            static fn (int $value): bool => $value > 0
        )));
        if ($propertyIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($propertyIds), '?'));
        $statement = $this->pdo->prepare(
            "SELECT
                pv.property_id,
                COALESCE(vo.title, NULLIF(pv.label, '')) AS label,
                COUNT(*) AS votes
             FROM property_votes pv
             LEFT JOIN vote_options vo ON vo.id = pv.vote_option_id
             WHERE pv.property_id IN ({$placeholders})
             GROUP BY pv.property_id, COALESCE(vo.title, NULLIF(pv.label, ''))
             ORDER BY pv.property_id ASC, votes DESC, label ASC"
        );
        $statement->execute($propertyIds);

        $map = [];
        foreach ($propertyIds as $propertyId) {
            $map[$propertyId] = [
                'votes' => [],
                'selectedVoteOptionId' => null,
            ];
        }

        foreach ($statement->fetchAll() as $row) {
            $propertyId = (int) ($row['property_id'] ?? 0);
            $label = trim((string) ($row['label'] ?? ''));
            if ($propertyId < 1 || $label === '' || !isset($map[$propertyId])) {
                continue;
            }

            $map[$propertyId]['votes'][$label] = (int) ($row['votes'] ?? 0);
        }

        if ($voterUserId !== null && $voterUserId > 0) {
            $selected = $this->pdo->prepare(
                "SELECT property_id, vote_option_id
                 FROM property_votes
                 WHERE voter_user_id = ?
                   AND property_id IN ({$placeholders})"
            );
            $selected->execute(array_merge([$voterUserId], $propertyIds));

            foreach ($selected->fetchAll() as $row) {
                $propertyId = (int) ($row['property_id'] ?? 0);
                if (!isset($map[$propertyId])) {
                    continue;
                }

                $map[$propertyId]['selectedVoteOptionId'] = int_or_null($row['vote_option_id'] ?? null);
            }
        }

        return $map;
    }

    public function summaryMap(array $propertyIds): array
    {
        $propertyIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): int => (int) $value, $propertyIds),
            static fn (int $value): bool => $value > 0
        )));
        if ($propertyIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($propertyIds), '?'));
        $statement = $this->pdo->prepare(
            "SELECT
                pv.property_id,
                COALESCE(vo.title, NULLIF(pv.label, '')) AS label,
                COUNT(*) AS votes
             FROM property_votes pv
             LEFT JOIN vote_options vo ON vo.id = pv.vote_option_id
             WHERE pv.property_id IN ({$placeholders})
             GROUP BY pv.property_id, COALESCE(vo.title, NULLIF(pv.label, ''))
             ORDER BY pv.property_id ASC, votes DESC, label ASC"
        );
        $statement->execute($propertyIds);

        $map = [];
        foreach ($propertyIds as $propertyId) {
            $map[$propertyId] = [
                'totalVotes' => 0,
                'topNeed' => null,
                'dominantShare' => 0.0,
            ];
        }

        $topVotesByProperty = [];
        foreach ($statement->fetchAll() as $row) {
            $propertyId = (int) ($row['property_id'] ?? 0);
            $label = string_or_null($row['label'] ?? null);
            $votes = (int) ($row['votes'] ?? 0);
            if ($propertyId < 1 || $label === null || $votes < 1) {
                continue;
            }

            $map[$propertyId]['totalVotes'] += $votes;
            if (!isset($topVotesByProperty[$propertyId]) || $votes > $topVotesByProperty[$propertyId]) {
                $topVotesByProperty[$propertyId] = $votes;
                $map[$propertyId]['topNeed'] = $label;
            }
        }

        foreach ($map as $propertyId => $summary) {
            $topVotes = (int) ($topVotesByProperty[$propertyId] ?? 0);
            $totalVotes = (int) ($summary['totalVotes'] ?? 0);
            $map[$propertyId]['dominantShare'] = $totalVotes > 0
                ? round($topVotes / $totalVotes, 4)
                : 0.0;
        }

        return $map;
    }

    public function castVote(int $propertyId, int $voterUserId, ?int $voteOptionId = null, ?string $label = null): array
    {
        if ($voterUserId < 1) {
            throw new InvalidArgumentException('A valid investor account is required to vote.');
        }

        $property = $this->propertySummary($propertyId);
        $option = $this->resolve($voteOptionId, $label);
        if ($option === null && string_or_null($label) !== null) {
            $option = $this->create([
                'title' => string_or_null($label),
                'description' => 'Imported vote option',
                'is_active' => 1,
            ]);
        }

        if ($option === null) {
            throw new InvalidArgumentException('A valid vote option is required.');
        }

        $before = $this->voteTallies($propertyId, $voterUserId);
        $statement = $this->pdo->prepare(
            'INSERT INTO property_votes (property_id, vote_option_id, voter_user_id, label)
             VALUES (:property_id, :vote_option_id, :voter_user_id, :label)
             ON DUPLICATE KEY UPDATE
                vote_option_id = VALUES(vote_option_id),
                label = VALUES(label),
                created_at = CURRENT_TIMESTAMP'
        );
        $this->pdo->beginTransaction();

        try {
            $statement->execute([
                'property_id' => $propertyId,
                'vote_option_id' => (int) $option['id'],
                'voter_user_id' => $voterUserId,
                'label' => (string) $option['title'],
            ]);

            $after = $this->voteTallies($propertyId, $voterUserId);
            if ($this->auditLogs !== null) {
                $this->auditLogs->record(
                    $voterUserId,
                    'EDIT',
                    'VOTE',
                    $propertyId,
                    [
                        'eventType' => 'VOTE_SIGNAL',
                        'targetLabel' => sprintf('PROP_ID: #SFLU-%03d', $propertyId),
                        'summary' => sprintf('Vote pulse moved to %s for %s.', (string) ($option['title'] ?? 'Unlabeled Vote'), (string) ($property['name'] ?? 'the property')),
                        'before' => $before,
                        'after' => $after,
                        'changedFields' => ['votes', 'selectedVoteOptionId'],
                        'streamGroup' => 'all',
                        'propertyName' => (string) ($property['name'] ?? ''),
                    ]
                );
            }

            $this->pdo->commit();
            return $after;
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function normalizePayload(array $payload, ?array $existing = null): array
    {
        $title = string_or_null($payload['title'] ?? ($existing['title'] ?? null));
        if ($title === null) {
            throw new InvalidArgumentException('Vote option title is required.');
        }

        $description = string_or_null($payload['description'] ?? ($existing['description'] ?? null));
        $imageUrl = string_or_null($payload['image_url'] ?? $payload['imageUrl'] ?? ($existing['imageUrl'] ?? null));
        $sortOrder = int_or_null($payload['sort_order'] ?? $payload['sortOrder'] ?? ($existing['sortOrder'] ?? null))
            ?? $this->nextSortOrder();
        $isActive = isset($payload['is_active']) || isset($payload['isActive'])
            ? ((int) ($payload['is_active'] ?? $payload['isActive']) === 1 ? 1 : 0)
            : (int) ($existing['isActive'] ?? 1);

        return [
            'title' => $title,
            'slug' => $this->slug($title),
            'description' => $description,
            'image_url' => $imageUrl,
            'is_active' => $isActive,
            'sort_order' => $sortOrder,
        ];
    }

    private function nextSortOrder(): int
    {
        return (int) $this->pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM vote_options')->fetchColumn();
    }

    private function assertPropertyExists(int $propertyId): void
    {
        $statement = $this->pdo->prepare('SELECT id FROM properties WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $propertyId]);

        if (!$statement->fetchColumn()) {
            throw new OutOfBoundsException('Property not found.');
        }
    }

    private function propertySummary(int $propertyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name
             FROM properties
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $propertyId]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            throw new OutOfBoundsException('Property not found.');
        }

        return $row;
    }

    private function slug(string $value): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($value))) ?? '';
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : sprintf('option-%d', time());
    }

    private function hydrate(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'description' => $row['description'] !== null ? (string) $row['description'] : null,
            'imageUrl' => $row['image_url'] !== null ? (string) $row['image_url'] : null,
            'isActive' => (int) ($row['is_active'] ?? 1) === 1,
            'sortOrder' => (int) ($row['sort_order'] ?? 0),
            'createdByUserId' => isset($row['created_by_user_id']) ? (int) $row['created_by_user_id'] : null,
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'updatedAt' => (string) ($row['updated_at'] ?? ''),
        ];
    }
}
