<?php
declare(strict_types=1);

namespace App\Repositories;

use InvalidArgumentException;
use OutOfBoundsException;
use PDO;
use Throwable;

final class MessageRepository
{
    private PDO $pdo;
    private ?AuditLogRepository $auditLogs;

    public function __construct(PDO $pdo, ?AuditLogRepository $auditLogs = null)
    {
        $this->pdo = $pdo;
        $this->auditLogs = $auditLogs;
    }

    public function propertyConversation(int $propertyId, ?array $user): array
    {
        $property = $this->propertyRow($propertyId);
        $summary = $this->propertySummary($propertyId);

        if ($user === null || !isset($user['id'], $user['role'])) {
            return [
                'thread' => null,
                'messages' => [],
                'threads' => [],
                'summary' => $summary,
            ];
        }

        $role = (string) $user['role'];
        $userId = (int) $user['id'];

        if ($role === 'investor') {
            $thread = $this->threadByPropertyAndInvestor($propertyId, $userId);
            return [
                'thread' => $thread,
                'messages' => $thread ? $this->messagesByThread((int) $thread['id']) : [],
                'threads' => [],
                'summary' => $summary,
            ];
        }

        if ($role === 'seller') {
            if ((int) ($property['seller_user_id'] ?? 0) !== $userId) {
                return [
                    'thread' => null,
                    'messages' => [],
                    'threads' => [],
                    'summary' => $summary,
                ];
            }

            $threads = $this->threadsByProperty($propertyId);
            $primary = $threads[0] ?? null;
            return [
                'thread' => $primary,
                'messages' => $primary ? $this->messagesByThread((int) $primary['id']) : [],
                'threads' => $threads,
                'summary' => $summary,
            ];
        }

        if ($role === 'admin') {
            $threads = $this->threadsByProperty($propertyId);
            $primary = $threads[0] ?? null;
            return [
                'thread' => $primary,
                'messages' => $primary ? $this->messagesByThread((int) $primary['id']) : [],
                'threads' => $threads,
                'summary' => $summary,
            ];
        }

        return [
            'thread' => null,
            'messages' => [],
            'threads' => [],
            'summary' => $summary,
        ];
    }

    public function inbox(array $user): array
    {
        $role = (string) ($user['role'] ?? 'guest');
        $userId = (int) ($user['id'] ?? 0);

        if ($role === 'seller') {
            return $this->threads('WHERE t.seller_user_id = :user_id', ['user_id' => $userId]);
        }

        if ($role === 'investor') {
            return $this->threads('WHERE t.investor_user_id = :user_id', ['user_id' => $userId]);
        }

        if ($role === 'admin') {
            return $this->threads('', []);
        }

        return [];
    }

    public function thread(int $threadId, array $user): array
    {
        $thread = $this->threadRow($threadId);
        if (!$this->canAccessThread($thread, $user)) {
            throw new InvalidArgumentException('You do not have access to this conversation.');
        }

        return [
            'thread' => $thread,
            'messages' => $this->messagesByThread($threadId),
        ];
    }

    public function sendToProperty(int $propertyId, array $user, string $text): array
    {
        $role = (string) ($user['role'] ?? 'guest');
        if ($role !== 'investor') {
            throw new InvalidArgumentException('Only investors can start a property conversation.');
        }

        $property = $this->propertyRow($propertyId);
        $sellerUserId = int_or_null($property['seller_user_id'] ?? null);
        if ($sellerUserId === null || $sellerUserId < 1) {
            throw new InvalidArgumentException('This property does not have an assigned seller account yet.');
        }

        $investorUserId = (int) ($user['id'] ?? 0);
        $thread = $this->threadByPropertyAndInvestor($propertyId, $investorUserId);
        $this->pdo->beginTransaction();

        try {
            if ($thread === null) {
                $threadId = $this->createThread($propertyId, $investorUserId, $sellerUserId, (string) ($property['name'] ?? 'Property conversation'));
            } else {
                $threadId = (int) $thread['id'];
            }

            $message = $this->createMessage($threadId, $propertyId, $investorUserId, $sellerUserId, (string) ($user['name'] ?? 'Investor'), 'investor', $text);
            $this->recordMessageAudit($user, $threadId, $propertyId, $message, $thread === null ? 'THREAD_OPEN' : 'MESSAGE_SEND');
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        $threadPayload = $this->thread($threadId, $user);

        return [
            'thread' => $threadPayload['thread'],
            'messages' => $threadPayload['messages'],
            'message' => $message,
        ];
    }

    public function replyToThread(int $threadId, array $user, string $text, ?int $recipientUserIdOverride = null): array
    {
        $thread = $this->threadRow($threadId);
        if (!$this->canAccessThread($thread, $user)) {
            throw new InvalidArgumentException('You do not have access to this conversation.');
        }

        $role = (string) ($user['role'] ?? 'guest');
        $senderUserId = (int) ($user['id'] ?? 0);
        $recipientUserId = match ($role) {
            'seller' => int_or_null($thread['investorUserId'] ?? null),
            'admin' => int_or_null($thread['sellerUserId'] ?? null) ?? int_or_null($thread['investorUserId'] ?? null),
            default => int_or_null($thread['sellerUserId'] ?? null),
        };

        if ($role === 'admin' && $recipientUserIdOverride !== null) {
            $allowedRecipientIds = array_values(array_unique(array_filter([
                int_or_null($thread['investorUserId'] ?? null),
                int_or_null($thread['sellerUserId'] ?? null),
            ], static fn (?int $value): bool => $value !== null && $value > 0)));

            if (!in_array($recipientUserIdOverride, $allowedRecipientIds, true)) {
                throw new InvalidArgumentException('Admin replies can only be sent to the investor or seller on this thread.');
            }

            $recipientUserId = $recipientUserIdOverride;
        }

        $this->pdo->beginTransaction();
        try {
            $message = $this->createMessage(
                $threadId,
                (int) $thread['propertyId'],
                $senderUserId > 0 ? $senderUserId : null,
                $recipientUserId,
                (string) ($user['name'] ?? 'Platform User'),
                $role,
                $text
            );
            $this->recordMessageAudit($user, $threadId, (int) $thread['propertyId'], $message, 'MESSAGE_SEND');
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
        $threadPayload = $this->thread($threadId, $user);

        return [
            'thread' => $threadPayload['thread'],
            'messages' => $threadPayload['messages'],
            'message' => $message,
        ];
    }

    public function clearThread(int $threadId, ?array $actor = null): void
    {
        $thread = $this->threadRow($threadId);
        $messages = $this->messagesByThread($threadId);
        $statement = $this->pdo->prepare('DELETE FROM property_messages WHERE thread_id = :thread_id');
        $this->pdo->beginTransaction();

        try {
            $statement->execute(['thread_id' => $threadId]);
            $this->touchThread($threadId);
            if ($this->auditLogs !== null) {
                $this->auditLogs->record(
                    isset($actor['id']) ? (int) $actor['id'] : null,
                    'DELETE',
                    'MESSAGE',
                    $threadId,
                    [
                        'actorName' => (string) ($actor['name'] ?? 'Admin Moderator'),
                        'actorRole' => (string) ($actor['role'] ?? 'admin'),
                        'eventType' => 'MSG_RESOLVE',
                        'targetLabel' => sprintf('THREAD: #%d', $threadId),
                        'summary' => 'Flagged thread content was cleared and the conversation was locked for review.',
                        'before' => [
                            'propertyId' => (int) ($thread['propertyId'] ?? 0),
                            'messageCount' => count($messages),
                            'messages' => array_slice($messages, -4),
                        ],
                        'after' => [
                            'propertyId' => (int) ($thread['propertyId'] ?? 0),
                            'messageCount' => 0,
                            'messagesCleared' => count($messages),
                        ],
                        'changedFields' => ['messageCount', 'messages'],
                        'streamGroup' => 'moderation',
                        'badge' => 'MODERATED',
                    ]
                );
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function propertySummary(int $propertyId): array
    {
        $threadStatement = $this->pdo->prepare('SELECT COUNT(*) FROM message_threads WHERE property_id = :property_id');
        $threadStatement->execute(['property_id' => $propertyId]);

        $messageStatement = $this->pdo->prepare('SELECT COUNT(*) FROM property_messages WHERE property_id = :property_id');
        $messageStatement->execute(['property_id' => $propertyId]);

        return [
            'threadCount' => (int) $threadStatement->fetchColumn(),
            'messageCount' => (int) $messageStatement->fetchColumn(),
        ];
    }

    public function propertySummaryMap(array $propertyIds): array
    {
        $propertyIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): int => (int) $value, $propertyIds),
            static fn (int $value): bool => $value > 0
        )));
        if ($propertyIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($propertyIds), '?'));
        $threadStatement = $this->pdo->prepare(
            "SELECT property_id, COUNT(*) AS thread_count
             FROM message_threads
             WHERE property_id IN ({$placeholders})
             GROUP BY property_id"
        );
        $threadStatement->execute($propertyIds);

        $messageStatement = $this->pdo->prepare(
            "SELECT property_id, COUNT(*) AS message_count
             FROM property_messages
             WHERE property_id IN ({$placeholders})
             GROUP BY property_id"
        );
        $messageStatement->execute($propertyIds);

        $map = [];
        foreach ($propertyIds as $propertyId) {
            $map[$propertyId] = [
                'threadCount' => 0,
                'messageCount' => 0,
            ];
        }

        foreach ($threadStatement->fetchAll() as $row) {
            $propertyId = (int) ($row['property_id'] ?? 0);
            $map[$propertyId]['threadCount'] = (int) ($row['thread_count'] ?? 0);
        }

        foreach ($messageStatement->fetchAll() as $row) {
            $propertyId = (int) ($row['property_id'] ?? 0);
            $map[$propertyId]['messageCount'] = (int) ($row['message_count'] ?? 0);
        }

        return $map;
    }

    private function threadByPropertyAndInvestor(int $propertyId, int $investorUserId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT t.id
             FROM message_threads t
             WHERE t.property_id = :property_id
               AND t.investor_user_id = :investor_user_id
             LIMIT 1'
        );
        $statement->execute([
            'property_id' => $propertyId,
            'investor_user_id' => $investorUserId,
        ]);

        $threadId = int_or_null($statement->fetchColumn());
        return $threadId ? $this->threadRow($threadId) : null;
    }

    private function threadsByProperty(int $propertyId): array
    {
        return $this->threads('WHERE t.property_id = :property_id', ['property_id' => $propertyId]);
    }

    private function threads(string $where, array $params): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                t.id,
                t.property_id,
                t.investor_user_id,
                t.seller_user_id,
                t.subject,
                t.last_message_at,
                t.created_at,
                t.updated_at,
                p.name AS property_name,
                p.image_url AS property_image_url,
                p.barangay AS property_barangay,
                investor.name AS investor_name,
                investor.email AS investor_email,
                seller.name AS seller_name,
                seller.email AS seller_email,
                (
                    SELECT COUNT(*)
                    FROM property_messages m
                    WHERE m.thread_id = t.id
                ) AS message_count,
                (
                    SELECT text
                    FROM property_messages m
                    WHERE m.thread_id = t.id
                    ORDER BY m.created_at DESC, m.id DESC
                    LIMIT 1
                ) AS last_message_text
             FROM message_threads t
             INNER JOIN properties p ON p.id = t.property_id
             LEFT JOIN users investor ON investor.id = t.investor_user_id
             LEFT JOIN users seller ON seller.id = t.seller_user_id
             {$where}
             ORDER BY COALESCE(t.last_message_at, t.updated_at, t.created_at) DESC, t.id DESC"
        );
        $statement->execute($params);

        return array_map([$this, 'hydrateThread'], $statement->fetchAll());
    }

    private function threadRow(int $threadId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                t.id,
                t.property_id,
                t.investor_user_id,
                t.seller_user_id,
                t.subject,
                t.last_message_at,
                t.created_at,
                t.updated_at,
                p.name AS property_name,
                p.image_url AS property_image_url,
                p.barangay AS property_barangay,
                investor.name AS investor_name,
                investor.email AS investor_email,
                seller.name AS seller_name,
                seller.email AS seller_email,
                (
                    SELECT COUNT(*)
                    FROM property_messages m
                    WHERE m.thread_id = t.id
                ) AS message_count,
                (
                    SELECT text
                    FROM property_messages m
                    WHERE m.thread_id = t.id
                    ORDER BY m.created_at DESC, m.id DESC
                    LIMIT 1
                ) AS last_message_text
             FROM message_threads t
             INNER JOIN properties p ON p.id = t.property_id
             LEFT JOIN users investor ON investor.id = t.investor_user_id
             LEFT JOIN users seller ON seller.id = t.seller_user_id
             WHERE t.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $threadId]);

        $row = $statement->fetch();
        if (!is_array($row)) {
            throw new OutOfBoundsException('Conversation thread not found.');
        }

        return $this->hydrateThread($row);
    }

    private function createThread(int $propertyId, int $investorUserId, int $sellerUserId, string $propertyName): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO message_threads (property_id, investor_user_id, seller_user_id, subject, last_message_at)
             VALUES (:property_id, :investor_user_id, :seller_user_id, :subject, CURRENT_TIMESTAMP)'
        );
        $statement->execute([
            'property_id' => $propertyId,
            'investor_user_id' => $investorUserId,
            'seller_user_id' => $sellerUserId,
            'subject' => sprintf('%s inquiry', $propertyName),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function createMessage(
        int $threadId,
        int $propertyId,
        ?int $senderUserId,
        ?int $recipientUserId,
        string $senderName,
        string $role,
        string $text
    ): array {
        $text = trim($text);
        if ($text === '') {
            throw new InvalidArgumentException('A message body is required.');
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO property_messages (thread_id, property_id, sender_user_id, recipient_user_id, sender_name, role, text)
             VALUES (:thread_id, :property_id, :sender_user_id, :recipient_user_id, :sender_name, :role, :text)'
        );
        $statement->execute([
            'thread_id' => $threadId,
            'property_id' => $propertyId,
            'sender_user_id' => $senderUserId,
            'recipient_user_id' => $recipientUserId,
            'sender_name' => $senderName,
            'role' => $role,
            'text' => $text,
        ]);

        $messageId = (int) $this->pdo->lastInsertId();
        $this->touchThread($threadId);
        $select = $this->pdo->prepare(
            'SELECT id, thread_id, property_id, sender_user_id, recipient_user_id, sender_name, role, text, created_at
             FROM property_messages
             WHERE id = :id
             LIMIT 1'
        );
        $select->execute(['id' => $messageId]);

        return $this->hydrateMessage($select->fetch() ?: []);
    }

    private function touchThread(int $threadId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE message_threads
             SET last_message_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute(['id' => $threadId]);
    }

    private function messagesByThread(int $threadId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, thread_id, property_id, sender_user_id, recipient_user_id, sender_name, role, text, created_at
             FROM property_messages
             WHERE thread_id = :thread_id
             ORDER BY created_at ASC, id ASC'
        );
        $statement->execute(['thread_id' => $threadId]);

        return array_map([$this, 'hydrateMessage'], $statement->fetchAll());
    }

    private function canAccessThread(array $thread, array $user): bool
    {
        $role = (string) ($user['role'] ?? 'guest');
        $userId = (int) ($user['id'] ?? 0);

        return match ($role) {
            'admin' => true,
            'seller' => $userId > 0 && $userId === (int) ($thread['sellerUserId'] ?? 0),
            'investor' => $userId > 0 && $userId === (int) ($thread['investorUserId'] ?? 0),
            default => false,
        };
    }

    private function propertyRow(int $propertyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, seller_user_id
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

    private function hydrateThread(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'propertyId' => (int) ($row['property_id'] ?? 0),
            'propertyName' => (string) ($row['property_name'] ?? ''),
            'propertyImageUrl' => (string) ($row['property_image_url'] ?? ''),
            'propertyBarangay' => $row['property_barangay'] !== null ? (string) $row['property_barangay'] : null,
            'investorUserId' => (int) ($row['investor_user_id'] ?? 0),
            'sellerUserId' => isset($row['seller_user_id']) ? int_or_null($row['seller_user_id']) : null,
            'investorName' => (string) ($row['investor_name'] ?? ''),
            'investorEmail' => (string) ($row['investor_email'] ?? ''),
            'sellerName' => (string) ($row['seller_name'] ?? ''),
            'sellerEmail' => (string) ($row['seller_email'] ?? ''),
            'subject' => $row['subject'] !== null ? (string) $row['subject'] : null,
            'lastMessageAt' => $row['last_message_at'] !== null ? (string) $row['last_message_at'] : null,
            'lastMessageText' => $row['last_message_text'] !== null ? (string) $row['last_message_text'] : null,
            'messageCount' => (int) ($row['message_count'] ?? 0),
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'updatedAt' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function hydrateMessage(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'threadId' => isset($row['thread_id']) ? int_or_null($row['thread_id']) : null,
            'propertyId' => (int) ($row['property_id'] ?? 0),
            'senderUserId' => isset($row['sender_user_id']) ? int_or_null($row['sender_user_id']) : null,
            'recipientUserId' => isset($row['recipient_user_id']) ? int_or_null($row['recipient_user_id']) : null,
            'senderName' => (string) ($row['sender_name'] ?? ''),
            'role' => (string) ($row['role'] ?? 'investor'),
            'text' => (string) ($row['text'] ?? ''),
            'createdAt' => (string) ($row['created_at'] ?? ''),
        ];
    }

    private function recordMessageAudit(array $actor, int $threadId, int $propertyId, array $message, string $eventType): void
    {
        if ($this->auditLogs === null) {
            return;
        }

        $summary = $eventType === 'THREAD_OPEN'
            ? sprintf('%s opened a new inquiry thread.', (string) ($actor['name'] ?? 'Platform User'))
            : sprintf('%s posted a new message in thread #%d.', (string) ($actor['name'] ?? 'Platform User'), $threadId);

        $this->auditLogs->record(
            isset($actor['id']) ? (int) $actor['id'] : null,
            'EDIT',
            'MESSAGE',
            (int) ($message['id'] ?? $threadId),
            [
                'actorName' => (string) ($actor['name'] ?? 'Platform User'),
                'actorRole' => (string) ($actor['role'] ?? 'system'),
                'eventType' => $eventType,
                'targetLabel' => sprintf('THREAD: #%d', $threadId),
                'summary' => $summary,
                'before' => [
                    'threadId' => $threadId,
                    'propertyId' => $propertyId,
                ],
                'after' => [
                    'threadId' => $threadId,
                    'propertyId' => $propertyId,
                    'messageId' => (int) ($message['id'] ?? 0),
                    'senderName' => (string) ($message['senderName'] ?? ''),
                    'role' => (string) ($message['role'] ?? ''),
                    'text' => (string) ($message['text'] ?? ''),
                    'createdAt' => (string) ($message['createdAt'] ?? ''),
                ],
                'changedFields' => ['text', 'threadId'],
                'streamGroup' => 'all',
            ]
        );
    }
}
