<?php
declare(strict_types=1);

namespace App\Repositories;

use InvalidArgumentException;
use OutOfBoundsException;
use PDO;

final class NotificationRepository
{
    private const CADENCES = ['instant', 'daily_digest', 'weekly'];
    private const CATEGORIES = ['transactional', 'intelligence', 'operational'];
    private const PRIORITIES = ['high', 'normal', 'low'];
    private const TONES = ['success', 'info', 'trend', 'system'];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn();
    }

    public function feedForUser(int $userId, int $limit = 40): array
    {
        $this->ensurePreferenceRow($userId);
        $limit = max(1, min(100, $limit));

        $statement = $this->pdo->prepare(
            'SELECT
                n.id,
                n.user_id,
                n.actor_user_id,
                n.property_id,
                n.thread_id,
                n.document_request_id,
                n.category,
                n.kind,
                n.priority,
                n.tone,
                n.icon,
                n.title,
                n.body,
                n.action_label,
                n.action_url,
                n.meta_json,
                n.is_read,
                n.read_at,
                n.created_at,
                p.name AS property_name,
                p.barangay AS property_barangay,
                actor.name AS actor_name
             FROM notifications n
             LEFT JOIN properties p ON p.id = n.property_id
             LEFT JOIN users actor ON actor.id = n.actor_user_id
             WHERE n.user_id = :user_id
             ORDER BY n.created_at DESC, n.id DESC
             LIMIT ' . $limit
        );
        $statement->execute(['user_id' => $userId]);

        return array_map([$this, 'hydrate'], $statement->fetchAll());
    }

    public function unreadCount(int $userId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM notifications
             WHERE user_id = :user_id
               AND is_read = 0'
        );
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    public function feedForProperty(int $userId, int $propertyId, int $limit = 20): array
    {
        $this->ensurePreferenceRow($userId);
        $limit = max(1, min(60, $limit));

        $statement = $this->pdo->prepare(
            'SELECT
                n.id,
                n.user_id,
                n.actor_user_id,
                n.property_id,
                n.thread_id,
                n.document_request_id,
                n.category,
                n.kind,
                n.priority,
                n.tone,
                n.icon,
                n.title,
                n.body,
                n.action_label,
                n.action_url,
                n.meta_json,
                n.is_read,
                n.read_at,
                n.created_at,
                p.name AS property_name,
                p.barangay AS property_barangay,
                actor.name AS actor_name
             FROM notifications n
             LEFT JOIN properties p ON p.id = n.property_id
             LEFT JOIN users actor ON actor.id = n.actor_user_id
             WHERE n.user_id = :user_id
               AND n.property_id = :property_id
             ORDER BY n.created_at DESC, n.id DESC
             LIMIT ' . $limit
        );
        $statement->execute([
            'user_id' => $userId,
            'property_id' => $propertyId,
        ]);

        return array_map([$this, 'hydrate'], $statement->fetchAll());
    }

    public function preferencesForUser(int $userId): array
    {
        $this->ensurePreferenceRow($userId);

        $statement = $this->pdo->prepare(
            'SELECT user_id, notification_cadence, created_at, updated_at
             FROM user_preferences
             WHERE user_id = :user_id
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            throw new OutOfBoundsException('Notification preferences not found.');
        }

        return [
            'userId' => (int) ($row['user_id'] ?? 0),
            'notificationCadence' => $this->normalizeCadence((string) ($row['notification_cadence'] ?? 'instant')),
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'updatedAt' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    public function updateCadence(int $userId, string $cadence): array
    {
        $normalizedCadence = $this->normalizeCadence($cadence, true);
        $this->ensurePreferenceRow($userId);

        $statement = $this->pdo->prepare(
            'UPDATE user_preferences
             SET notification_cadence = :notification_cadence,
                 updated_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id'
        );
        $statement->execute([
            'user_id' => $userId,
            'notification_cadence' => $normalizedCadence,
        ]);

        return $this->preferencesForUser($userId);
    }

    public function createForUsers(array $userIds, array $payload): array
    {
        $recipientIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): int => (int) $value, $userIds),
            static fn (int $value): bool => $value > 0
        )));

        if ($recipientIds === []) {
            return [];
        }

        $normalized = $this->normalizePayload($payload);
        $insert = $this->pdo->prepare(
            'INSERT INTO notifications (
                user_id, actor_user_id, property_id, thread_id, document_request_id,
                category, kind, priority, tone, icon, title, body, action_label, action_url,
                meta_json, is_read, read_at, created_at
             ) VALUES (
                :user_id, :actor_user_id, :property_id, :thread_id, :document_request_id,
                :category, :kind, :priority, :tone, :icon, :title, :body, :action_label, :action_url,
                :meta_json, :is_read, :read_at, :created_at
             )'
        );

        foreach ($recipientIds as $userId) {
            $this->ensurePreferenceRow($userId);
            $insert->execute(array_merge(
                ['user_id' => $userId],
                $normalized
            ));
        }

        return $recipientIds;
    }

    public function markRead(int $userId, int $notificationId): array
    {
        $statement = $this->pdo->prepare(
            'UPDATE notifications
             SET is_read = 1,
                 read_at = COALESCE(read_at, CURRENT_TIMESTAMP)
             WHERE id = :id
               AND user_id = :user_id'
        );
        $statement->execute([
            'id' => $notificationId,
            'user_id' => $userId,
        ]);

        return $this->find($notificationId, $userId);
    }

    public function markAllRead(int $userId): int
    {
        $statement = $this->pdo->prepare(
            'UPDATE notifications
             SET is_read = 1,
                 read_at = COALESCE(read_at, CURRENT_TIMESTAMP)
             WHERE user_id = :user_id
               AND is_read = 0'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->rowCount();
    }

    public function find(int $notificationId, int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                n.id,
                n.user_id,
                n.actor_user_id,
                n.property_id,
                n.thread_id,
                n.document_request_id,
                n.category,
                n.kind,
                n.priority,
                n.tone,
                n.icon,
                n.title,
                n.body,
                n.action_label,
                n.action_url,
                n.meta_json,
                n.is_read,
                n.read_at,
                n.created_at,
                p.name AS property_name,
                p.barangay AS property_barangay,
                actor.name AS actor_name
             FROM notifications n
             LEFT JOIN properties p ON p.id = n.property_id
             LEFT JOIN users actor ON actor.id = n.actor_user_id
             WHERE n.id = :id
               AND n.user_id = :user_id
             LIMIT 1'
        );
        $statement->execute([
            'id' => $notificationId,
            'user_id' => $userId,
        ]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            throw new OutOfBoundsException('Notification not found.');
        }

        return $this->hydrate($row);
    }

    private function ensurePreferenceRow(int $userId): void
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('A valid user id is required.');
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO user_preferences (user_id, notification_cadence)
             VALUES (:user_id, :notification_cadence)
             ON DUPLICATE KEY UPDATE user_id = user_id'
        );
        $statement->execute([
            'user_id' => $userId,
            'notification_cadence' => 'instant',
        ]);
    }

    private function normalizePayload(array $payload): array
    {
        $category = $this->normalizeCategory((string) ($payload['category'] ?? 'transactional'));
        $kind = string_or_null($payload['kind'] ?? null) ?? 'update';
        $priority = $this->normalizePriority((string) ($payload['priority'] ?? 'normal'));
        $tone = $this->normalizeTone((string) ($payload['tone'] ?? 'system'));
        $title = string_or_null($payload['title'] ?? null);
        $body = string_or_null($payload['body'] ?? null);
        if ($title === null || $body === null) {
            throw new InvalidArgumentException('Notifications require both a title and body.');
        }

        $isRead = (int) filter_var($payload['is_read'] ?? $payload['isRead'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $createdAt = string_or_null($payload['created_at'] ?? $payload['createdAt'] ?? null) ?? gmdate('Y-m-d H:i:s');
        $readAt = $isRead === 1
            ? (string_or_null($payload['read_at'] ?? $payload['readAt'] ?? null) ?? $createdAt)
            : null;
        $meta = $payload['meta'] ?? $payload['meta_json'] ?? null;

        return [
            'actor_user_id' => int_or_null($payload['actor_user_id'] ?? $payload['actorUserId'] ?? null),
            'property_id' => int_or_null($payload['property_id'] ?? $payload['propertyId'] ?? null),
            'thread_id' => int_or_null($payload['thread_id'] ?? $payload['threadId'] ?? null),
            'document_request_id' => int_or_null($payload['document_request_id'] ?? $payload['documentRequestId'] ?? null),
            'category' => $category,
            'kind' => $kind,
            'priority' => $priority,
            'tone' => $tone,
            'icon' => string_or_null($payload['icon'] ?? null) ?? 'signal',
            'title' => $title,
            'body' => $body,
            'action_label' => string_or_null($payload['action_label'] ?? $payload['actionLabel'] ?? null),
            'action_url' => string_or_null($payload['action_url'] ?? $payload['actionUrl'] ?? null),
            'meta_json' => is_array($meta)
                ? json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : (string_or_null($meta) ?? null),
            'is_read' => $isRead,
            'read_at' => $readAt,
            'created_at' => $createdAt,
        ];
    }

    private function normalizeCadence(string $cadence, bool $strict = false): string
    {
        $normalized = strtolower(trim($cadence));
        if (!in_array($normalized, self::CADENCES, true)) {
            if ($strict) {
                throw new InvalidArgumentException('Invalid notification cadence.');
            }

            return 'instant';
        }

        return $normalized;
    }

    private function normalizeCategory(string $category): string
    {
        $normalized = strtolower(trim($category));
        return in_array($normalized, self::CATEGORIES, true) ? $normalized : 'transactional';
    }

    private function normalizePriority(string $priority): string
    {
        $normalized = strtolower(trim($priority));
        return in_array($normalized, self::PRIORITIES, true) ? $normalized : 'normal';
    }

    private function normalizeTone(string $tone): string
    {
        $normalized = strtolower(trim($tone));
        return in_array($normalized, self::TONES, true) ? $normalized : 'system';
    }

    private function hydrate(array $row): array
    {
        $meta = json_decode((string) ($row['meta_json'] ?? 'null'), true);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'userId' => (int) ($row['user_id'] ?? 0),
            'actorUserId' => isset($row['actor_user_id']) ? int_or_null($row['actor_user_id']) : null,
            'actorName' => $row['actor_name'] !== null ? (string) $row['actor_name'] : null,
            'propertyId' => isset($row['property_id']) ? int_or_null($row['property_id']) : null,
            'propertyName' => $row['property_name'] !== null ? (string) $row['property_name'] : null,
            'propertyBarangay' => $row['property_barangay'] !== null ? (string) $row['property_barangay'] : null,
            'threadId' => isset($row['thread_id']) ? int_or_null($row['thread_id']) : null,
            'documentRequestId' => isset($row['document_request_id']) ? int_or_null($row['document_request_id']) : null,
            'category' => $this->normalizeCategory((string) ($row['category'] ?? 'transactional')),
            'kind' => (string) ($row['kind'] ?? 'update'),
            'priority' => $this->normalizePriority((string) ($row['priority'] ?? 'normal')),
            'tone' => $this->normalizeTone((string) ($row['tone'] ?? 'system')),
            'icon' => (string) ($row['icon'] ?? 'signal'),
            'title' => (string) ($row['title'] ?? ''),
            'body' => (string) ($row['body'] ?? ''),
            'actionLabel' => $row['action_label'] !== null ? (string) $row['action_label'] : null,
            'actionUrl' => $row['action_url'] !== null ? (string) $row['action_url'] : null,
            'meta' => is_array($meta) ? $meta : [],
            'isRead' => (int) ($row['is_read'] ?? 0) === 1,
            'readAt' => $row['read_at'] !== null ? (string) $row['read_at'] : null,
            'createdAt' => (string) ($row['created_at'] ?? ''),
        ];
    }
}
