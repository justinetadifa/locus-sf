<?php
declare(strict_types=1);

namespace App\Repositories;

use InvalidArgumentException;
use OutOfBoundsException;
use PDO;

final class VisitLogRepository
{
    private const STATUSES = ['proposed', 'counter_offered', 'confirmed', 'in_progress', 'visited'];
    private const AUDIT_FIELDS = ['neighborhood_vibe', 'utility_proximity', 'expansion_feasibility'];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function latestForProperty(int $propertyId, ?array $user): ?array
    {
        if (!$this->canResolveAccess($user)) {
            return null;
        }

        $params = ['property_id' => $propertyId];
        $where = ['v.property_id = :property_id'];
        $this->appendAccessFilter($where, $params, $user);

        $statement = $this->pdo->prepare(
            $this->baseSelect()
            . ' WHERE ' . implode(' AND ', $where)
            . " ORDER BY CASE WHEN v.status = 'visited' THEN 2 ELSE 1 END,
                       COALESCE(v.updated_at, v.created_at) DESC,
                       v.id DESC
               LIMIT 1"
        );
        $statement->execute($params);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrateVisit($row) : null;
    }

    public function findByThread(int $threadId, ?array $user): ?array
    {
        if (!$this->canResolveAccess($user)) {
            return null;
        }

        $params = ['thread_id' => $threadId];
        $where = ['v.thread_id = :thread_id'];
        $this->appendAccessFilter($where, $params, $user);

        $statement = $this->pdo->prepare(
            $this->baseSelect()
            . ' WHERE ' . implode(' AND ', $where)
            . ' LIMIT 1'
        );
        $statement->execute($params);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrateVisit($row) : null;
    }

    public function findById(int $visitId, ?array $user): ?array
    {
        if (!$this->canResolveAccess($user)) {
            return null;
        }

        $params = ['id' => $visitId];
        $where = ['v.id = :id'];
        $this->appendAccessFilter($where, $params, $user);

        $statement = $this->pdo->prepare(
            $this->baseSelect()
            . ' WHERE ' . implode(' AND ', $where)
            . ' LIMIT 1'
        );
        $statement->execute($params);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrateVisit($row) : null;
    }

    public function propose(int $propertyId, array $user, array $payload): array
    {
        if (($user['role'] ?? null) !== 'investor' || !isset($user['id'])) {
            throw new InvalidArgumentException('Only investor accounts can propose a site visit.');
        }

        $property = $this->propertyRow($propertyId);
        $sellerUserId = int_or_null($property['seller_user_id'] ?? null);
        if ($sellerUserId === null || $sellerUserId < 1) {
            throw new InvalidArgumentException('This property does not have an assigned seller yet.');
        }

        $investorUserId = (int) $user['id'];
        $threadId = $this->ensureThread($propertyId, $investorUserId, $sellerUserId, (string) $property['name']);
        $existing = $this->rawVisitByThread($threadId);
        if ($existing !== null) {
            throw new InvalidArgumentException('A logistics record already exists for this investor thread.');
        }

        $purpose = string_or_null($payload['investmentPurpose'] ?? $payload['investment_purpose'] ?? null);
        if ($purpose === null) {
            throw new InvalidArgumentException('An investment purpose is required.');
        }

        $primary = $this->requireWindow($payload, 'primary');
        $secondary = $this->requireWindow($payload, 'secondary');
        $createdAt = $this->now();
        $activity = [[
            'kind' => 'proposed',
            'title' => 'Site Visit Proposed',
            'summary' => sprintf(
                '%s proposed primary and secondary windows for a %s thesis.',
                (string) ($user['name'] ?? 'Investor'),
                $purpose
            ),
            'actorRole' => 'investor',
            'actorName' => (string) ($user['name'] ?? 'Investor'),
            'status' => 'proposed',
            'createdAt' => $createdAt,
            'primaryWindow' => $primary,
            'secondaryWindow' => $secondary,
            'purpose' => $purpose,
        ]];

        $statement = $this->pdo->prepare(
            'INSERT INTO visit_logs (
                property_id,
                thread_id,
                investor_user_id,
                seller_user_id,
                investment_purpose,
                primary_start_at,
                primary_end_at,
                secondary_start_at,
                secondary_end_at,
                status,
                activity_json
            ) VALUES (
                :property_id,
                :thread_id,
                :investor_user_id,
                :seller_user_id,
                :investment_purpose,
                :primary_start_at,
                :primary_end_at,
                :secondary_start_at,
                :secondary_end_at,
                :status,
                :activity_json
            )'
        );
        $statement->execute([
            'property_id' => $propertyId,
            'thread_id' => $threadId,
            'investor_user_id' => $investorUserId,
            'seller_user_id' => $sellerUserId,
            'investment_purpose' => $purpose,
            'primary_start_at' => $primary['startAt'],
            'primary_end_at' => $primary['endAt'],
            'secondary_start_at' => $secondary['startAt'],
            'secondary_end_at' => $secondary['endAt'],
            'status' => 'proposed',
            'activity_json' => json_encode($activity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        $visitId = (int) $this->pdo->lastInsertId();
        $this->touchThread($threadId);

        return $this->mustFindById($visitId);
    }

    public function applyAction(int $visitId, array $user, array $payload): array
    {
        $visit = $this->mustRawVisit($visitId);
        $this->assertCanAccessVisit($visit, $user);

        $action = strtolower(trim((string) ($payload['action'] ?? '')));
        if ($action === '') {
            throw new InvalidArgumentException('A visit action is required.');
        }

        $role = (string) ($user['role'] ?? 'guest');
        $actorName = (string) ($user['name'] ?? 'Platform User');
        $activity = $this->decodeJson($visit['activity_json'] ?? '[]');
        $status = strtolower((string) ($visit['status'] ?? 'proposed'));
        $confirmedWindow = $this->storedWindow(
            string_or_null($visit['confirmed_start_at'] ?? null),
            string_or_null($visit['confirmed_end_at'] ?? null)
        );
        $counterWindow = $this->storedWindow(
            string_or_null($visit['counter_start_at'] ?? null),
            string_or_null($visit['counter_end_at'] ?? null)
        );

        $updates = [];
        $activityEvent = null;

        switch ($action) {
            case 'counteroffer':
                if (!in_array($role, ['seller', 'admin'], true)) {
                    throw new InvalidArgumentException('Only seller or admin accounts can suggest a new time.');
                }
                if (!in_array($status, ['proposed', 'confirmed'], true)) {
                    throw new InvalidArgumentException('This visit cannot be counter-offered in its current state.');
                }
                $counter = $this->requireWindow($payload, 'counter');
                $updates = [
                    'status' => 'counter_offered',
                    'counter_start_at' => $counter['startAt'],
                    'counter_end_at' => $counter['endAt'],
                    'confirmed_start_at' => null,
                    'confirmed_end_at' => null,
                    'started_at' => null,
                ];
                $activityEvent = [
                    'kind' => 'counter_offered',
                    'title' => 'Seller Suggested New Time',
                    'summary' => sprintf('%s suggested a new logistics window for the field visit.', $actorName),
                    'actorRole' => $role,
                    'actorName' => $actorName,
                    'status' => 'counter_offered',
                    'createdAt' => $this->now(),
                    'previousWindow' => $confirmedWindow ?? $this->preferredWindow($visit),
                    'counterWindow' => $counter,
                ];
                break;

            case 'acceptcounter':
                if ($role !== 'investor') {
                    throw new InvalidArgumentException('Only the investor can accept a counter-offer.');
                }
                if ($status !== 'counter_offered' || $counterWindow === null) {
                    throw new InvalidArgumentException('There is no counter-offer to accept.');
                }
                $updates = [
                    'status' => 'confirmed',
                    'confirmed_start_at' => $counterWindow['startAt'],
                    'confirmed_end_at' => $counterWindow['endAt'],
                ];
                $activityEvent = [
                    'kind' => 'confirmed',
                    'title' => 'Counter Offer Accepted',
                    'summary' => sprintf('%s accepted the suggested visit slot.', $actorName),
                    'actorRole' => $role,
                    'actorName' => $actorName,
                    'status' => 'confirmed',
                    'createdAt' => $this->now(),
                    'confirmedWindow' => $counterWindow,
                ];
                break;

            case 'confirm':
                if (!in_array($role, ['seller', 'admin'], true)) {
                    throw new InvalidArgumentException('Only seller or admin accounts can confirm a site visit.');
                }
                if (!in_array($status, ['proposed', 'counter_offered'], true)) {
                    throw new InvalidArgumentException('This visit cannot be confirmed in its current state.');
                }
                $selection = strtolower(trim((string) ($payload['selection'] ?? ($status === 'counter_offered' ? 'counter' : 'primary'))));
                $window = $this->windowFromSelection($visit, $selection);
                if ($window === null) {
                    throw new InvalidArgumentException('A valid visit window selection is required.');
                }
                $updates = [
                    'status' => 'confirmed',
                    'confirmed_start_at' => $window['startAt'],
                    'confirmed_end_at' => $window['endAt'],
                ];
                $activityEvent = [
                    'kind' => 'confirmed',
                    'title' => 'Ground Truth Scheduled',
                    'summary' => sprintf('%s confirmed the site visit window.', $actorName),
                    'actorRole' => $role,
                    'actorName' => $actorName,
                    'status' => 'confirmed',
                    'createdAt' => $this->now(),
                    'confirmedWindow' => $window,
                ];
                break;

            case 'markinprogress':
                if (!in_array($role, ['seller', 'admin'], true)) {
                    throw new InvalidArgumentException('Only seller or admin accounts can mark a visit in progress.');
                }
                if ($status !== 'confirmed') {
                    throw new InvalidArgumentException('Only confirmed visits can move in progress.');
                }
                $updates = [
                    'status' => 'in_progress',
                    'started_at' => $this->now(),
                ];
                $activityEvent = [
                    'kind' => 'in_progress',
                    'title' => 'Ground Truth In Motion',
                    'summary' => sprintf('%s marked the visit as underway.', $actorName),
                    'actorRole' => $role,
                    'actorName' => $actorName,
                    'status' => 'in_progress',
                    'createdAt' => $this->now(),
                ];
                break;

            case 'markvisited':
                if (!in_array($role, ['seller', 'admin'], true)) {
                    throw new InvalidArgumentException('Only seller or admin accounts can complete a visit.');
                }
                if (!in_array($status, ['confirmed', 'in_progress'], true)) {
                    throw new InvalidArgumentException('This visit cannot be marked completed yet.');
                }
                $updates = [
                    'status' => 'visited',
                    'started_at' => string_or_null($visit['started_at'] ?? null) ?? $this->now(),
                    'visited_at' => $this->now(),
                ];
                $activityEvent = [
                    'kind' => 'visited',
                    'title' => 'Visit Completed',
                    'summary' => sprintf('%s marked the field walkthrough complete.', $actorName),
                    'actorRole' => $role,
                    'actorName' => $actorName,
                    'status' => 'visited',
                    'createdAt' => $this->now(),
                ];
                break;

            case 'submitaudit':
                if ($role !== 'investor') {
                    throw new InvalidArgumentException('Only the investor can submit the field audit.');
                }
                if ($status !== 'visited') {
                    throw new InvalidArgumentException('The field audit unlocks only after the visit is completed.');
                }
                $fieldAudit = $this->normalizeFieldAudit($payload);
                $multiplier = $this->groundTruthMultiplier($fieldAudit);
                $updates = [
                    'field_audit_json' => json_encode($fieldAudit, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'ground_truth_multiplier' => $multiplier,
                ];
                $activityEvent = [
                    'kind' => 'field_audit',
                    'title' => 'Field Audit Submitted',
                    'summary' => sprintf(
                        '%s submitted the ground-truth audit. IAI multiplier is now %s.',
                        $actorName,
                        number_format($multiplier, 2)
                    ),
                    'actorRole' => $role,
                    'actorName' => $actorName,
                    'status' => 'visited',
                    'createdAt' => $this->now(),
                    'fieldAudit' => $fieldAudit,
                    'groundTruthMultiplier' => $multiplier,
                ];
                break;

            default:
                throw new InvalidArgumentException('Unsupported visit action.');
        }

        if ($activityEvent !== null) {
            $activity[] = $activityEvent;
            $updates['activity_json'] = json_encode($activity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $this->persistUpdates($visitId, $updates);
        $this->touchThread((int) $visit['thread_id']);

        return $this->mustFindById($visitId);
    }

    private function persistUpdates(int $visitId, array $updates): void
    {
        if ($updates === []) {
            return;
        }

        $sets = [];
        $params = ['id' => $visitId];
        foreach ($updates as $column => $value) {
            $sets[] = sprintf('%s = :%s', $column, $column);
            $params[$column] = $value;
        }

        $statement = $this->pdo->prepare(
            'UPDATE visit_logs
             SET ' . implode(', ', $sets) . ',
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute($params);
    }

    private function ensureThread(int $propertyId, int $investorUserId, int $sellerUserId, string $propertyName): int
    {
        $statement = $this->pdo->prepare(
            'SELECT id
             FROM message_threads
             WHERE property_id = :property_id
               AND investor_user_id = :investor_user_id
             LIMIT 1'
        );
        $statement->execute([
            'property_id' => $propertyId,
            'investor_user_id' => $investorUserId,
        ]);
        $threadId = int_or_null($statement->fetchColumn());
        if ($threadId !== null) {
            return $threadId;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO message_threads (property_id, investor_user_id, seller_user_id, subject, last_message_at)
             VALUES (:property_id, :investor_user_id, :seller_user_id, :subject, CURRENT_TIMESTAMP)'
        );
        $insert->execute([
            'property_id' => $propertyId,
            'investor_user_id' => $investorUserId,
            'seller_user_id' => $sellerUserId,
            'subject' => sprintf('%s site visit logistics', $propertyName),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function touchThread(int $threadId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE message_threads
             SET last_message_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute(['id' => $threadId]);
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

    private function requireWindow(array $payload, string $prefix): array
    {
        $startAt = $this->normalizeTimestampInput(
            $payload[$prefix . 'StartAt'] ?? $payload[$prefix . '_start_at'] ?? null
        );
        $endAt = $this->normalizeTimestampInput(
            $payload[$prefix . 'EndAt'] ?? $payload[$prefix . '_end_at'] ?? null
        );

        if ($startAt === null || $endAt === null) {
            throw new InvalidArgumentException('Both start and end time are required for each visit window.');
        }

        if (strtotime($endAt) <= strtotime($startAt)) {
            throw new InvalidArgumentException('Each visit window must end after it starts.');
        }

        return [
            'startAt' => $startAt,
            'endAt' => $endAt,
        ];
    }

    private function windowFromSelection(array $visit, string $selection): ?array
    {
        return match ($selection) {
            'primary' => $this->storedWindow(
                string_or_null($visit['primary_start_at'] ?? null),
                string_or_null($visit['primary_end_at'] ?? null)
            ),
            'secondary' => $this->storedWindow(
                string_or_null($visit['secondary_start_at'] ?? null),
                string_or_null($visit['secondary_end_at'] ?? null)
            ),
            'counter' => $this->storedWindow(
                string_or_null($visit['counter_start_at'] ?? null),
                string_or_null($visit['counter_end_at'] ?? null)
            ),
            default => null,
        };
    }

    private function preferredWindow(array $visit): ?array
    {
        return $this->storedWindow(
            string_or_null($visit['primary_start_at'] ?? null),
            string_or_null($visit['primary_end_at'] ?? null)
        );
    }

    private function normalizeFieldAudit(array $payload): array
    {
        $scores = [];
        foreach (self::AUDIT_FIELDS as $field) {
            $value = int_or_null($payload[$field] ?? null);
            if ($value === null || $value < 1 || $value > 5) {
                throw new InvalidArgumentException('Field audit sliders must be scored from 1 to 5.');
            }
            $scores[$field] = $value;
        }

        $scores['notes'] = string_or_null($payload['notes'] ?? null);
        return $scores;
    }

    private function groundTruthMultiplier(array $fieldAudit): float
    {
        $total = 0;
        foreach (self::AUDIT_FIELDS as $field) {
            $total += (int) ($fieldAudit[$field] ?? 0);
        }

        $average = $total / max(count(self::AUDIT_FIELDS), 1);
        $normalized = ($average - 1) / 4;
        return round(0.88 + ($normalized * 0.28), 2);
    }

    private function baseSelect(): string
    {
        return
            'SELECT
                v.*,
                p.name AS property_name,
                p.city AS property_city,
                p.barangay AS property_barangay,
                investor.name AS investor_name,
                investor.email AS investor_email,
                seller.name AS seller_name,
                seller.email AS seller_email
             FROM visit_logs v
             INNER JOIN properties p ON p.id = v.property_id
             LEFT JOIN users investor ON investor.id = v.investor_user_id
             LEFT JOIN users seller ON seller.id = v.seller_user_id';
    }

    private function appendAccessFilter(array &$where, array &$params, ?array $user): void
    {
        $role = (string) ($user['role'] ?? 'guest');
        $userId = (int) ($user['id'] ?? 0);

        if ($role === 'admin') {
            return;
        }

        if ($role === 'seller' && $userId > 0) {
            $where[] = 'v.seller_user_id = :access_seller_user_id';
            $params['access_seller_user_id'] = $userId;
            return;
        }

        if ($role === 'investor' && $userId > 0) {
            $where[] = 'v.investor_user_id = :access_investor_user_id';
            $params['access_investor_user_id'] = $userId;
            return;
        }

        throw new InvalidArgumentException('A logged-in account is required to access visit logistics.');
    }

    private function canResolveAccess(?array $user): bool
    {
        $role = (string) ($user['role'] ?? 'guest');
        return in_array($role, ['investor', 'seller', 'admin'], true) && isset($user['id']);
    }

    private function assertCanAccessVisit(array $visit, array $user): void
    {
        $role = (string) ($user['role'] ?? 'guest');
        $userId = (int) ($user['id'] ?? 0);

        $allowed = match ($role) {
            'admin' => true,
            'seller' => $userId > 0 && $userId === (int) ($visit['seller_user_id'] ?? 0),
            'investor' => $userId > 0 && $userId === (int) ($visit['investor_user_id'] ?? 0),
            default => false,
        };

        if (!$allowed) {
            throw new InvalidArgumentException('You do not have access to this visit logistics record.');
        }
    }

    private function rawVisitByThread(int $threadId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM visit_logs
             WHERE thread_id = :thread_id
             LIMIT 1'
        );
        $statement->execute(['thread_id' => $threadId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function mustRawVisit(int $visitId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM visit_logs
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $visitId]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            throw new OutOfBoundsException('Visit log not found.');
        }

        return $row;
    }

    private function mustFindById(int $visitId): array
    {
        $statement = $this->pdo->prepare(
            $this->baseSelect()
            . ' WHERE v.id = :id
                LIMIT 1'
        );
        $statement->execute(['id' => $visitId]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            throw new OutOfBoundsException('Visit log not found.');
        }

        return $this->hydrateVisit($row);
    }

    private function hydrateVisit(array $row): array
    {
        $activity = array_values(array_filter(array_map(function (mixed $event): ?array {
            if (!is_array($event)) {
                return null;
            }

            $normalized = [
                'kind' => (string) ($event['kind'] ?? 'update'),
                'title' => (string) ($event['title'] ?? 'Visit update'),
                'summary' => (string) ($event['summary'] ?? ''),
                'actorRole' => (string) ($event['actorRole'] ?? 'system'),
                'actorName' => (string) ($event['actorName'] ?? 'Logistics Engine'),
                'status' => (string) ($event['status'] ?? 'proposed'),
                'createdAt' => $this->normalizeTimestamp($event['createdAt'] ?? null),
            ];

            foreach (['primaryWindow', 'secondaryWindow', 'counterWindow', 'previousWindow', 'confirmedWindow'] as $windowKey) {
                if (isset($event[$windowKey]) && is_array($event[$windowKey])) {
                    $normalized[$windowKey] = [
                        'startAt' => $this->normalizeTimestamp($event[$windowKey]['startAt'] ?? null),
                        'endAt' => $this->normalizeTimestamp($event[$windowKey]['endAt'] ?? null),
                    ];
                }
            }

            if (isset($event['fieldAudit']) && is_array($event['fieldAudit'])) {
                $normalized['fieldAudit'] = $event['fieldAudit'];
            }
            if (isset($event['groundTruthMultiplier'])) {
                $normalized['groundTruthMultiplier'] = (float) $event['groundTruthMultiplier'];
            }
            if (isset($event['purpose'])) {
                $normalized['purpose'] = (string) $event['purpose'];
            }

            return $normalized;
        }, $this->decodeJson($row['activity_json'] ?? '[]'))));

        $fieldAudit = $this->decodeJson($row['field_audit_json'] ?? '{}');
        $groundTruthMultiplier = isset($row['ground_truth_multiplier']) && $row['ground_truth_multiplier'] !== null
            ? (float) $row['ground_truth_multiplier']
            : 1.0;
        $status = strtolower((string) ($row['status'] ?? 'proposed'));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'propertyId' => (int) ($row['property_id'] ?? 0),
            'threadId' => (int) ($row['thread_id'] ?? 0),
            'investorUserId' => (int) ($row['investor_user_id'] ?? 0),
            'sellerUserId' => isset($row['seller_user_id']) ? int_or_null($row['seller_user_id']) : null,
            'propertyName' => (string) ($row['property_name'] ?? ''),
            'propertyCity' => (string) ($row['property_city'] ?? ''),
            'propertyBarangay' => $row['property_barangay'] !== null ? (string) $row['property_barangay'] : null,
            'investorName' => (string) ($row['investor_name'] ?? ''),
            'investorEmail' => (string) ($row['investor_email'] ?? ''),
            'sellerName' => (string) ($row['seller_name'] ?? ''),
            'sellerEmail' => (string) ($row['seller_email'] ?? ''),
            'investmentPurpose' => (string) ($row['investment_purpose'] ?? ''),
            'primaryWindow' => $this->hydrateWindow($row['primary_start_at'] ?? null, $row['primary_end_at'] ?? null),
            'secondaryWindow' => $this->hydrateWindow($row['secondary_start_at'] ?? null, $row['secondary_end_at'] ?? null),
            'counterWindow' => $this->hydrateWindow($row['counter_start_at'] ?? null, $row['counter_end_at'] ?? null),
            'confirmedWindow' => $this->hydrateWindow($row['confirmed_start_at'] ?? null, $row['confirmed_end_at'] ?? null),
            'activeWindow' => $this->hydrateWindow(
                $row['confirmed_start_at'] ?? $row['counter_start_at'] ?? $row['primary_start_at'] ?? null,
                $row['confirmed_end_at'] ?? $row['counter_end_at'] ?? $row['primary_end_at'] ?? null
            ),
            'status' => in_array($status, self::STATUSES, true) ? $status : 'proposed',
            'statusLabel' => $this->statusLabel($status),
            'startedAt' => $this->normalizeTimestamp($row['started_at'] ?? null),
            'visitedAt' => $this->normalizeTimestamp($row['visited_at'] ?? null),
            'fieldAudit' => $fieldAudit,
            'fieldAuditComplete' => $fieldAudit !== [],
            'groundTruthMultiplier' => $groundTruthMultiplier,
            'groundTruthAdjustmentPct' => round(($groundTruthMultiplier - 1) * 100, 1),
            'activity' => $activity,
            'createdAt' => $this->normalizeTimestamp($row['created_at'] ?? null),
            'updatedAt' => $this->normalizeTimestamp($row['updated_at'] ?? null),
        ];
    }

    private function hydrateWindow(mixed $startAt, mixed $endAt): ?array
    {
        $normalized = $this->storedWindow(string_or_null($startAt), string_or_null($endAt));
        if ($normalized === null) {
            return null;
        }

        return [
            'startAt' => $this->normalizeTimestamp($normalized['startAt']),
            'endAt' => $this->normalizeTimestamp($normalized['endAt']),
        ];
    }

    private function storedWindow(?string $startAt, ?string $endAt): ?array
    {
        if ($startAt === null || $endAt === null) {
            return null;
        }

        return [
            'startAt' => $startAt,
            'endAt' => $endAt,
        ];
    }

    private function statusLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            'proposed' => 'Proposed',
            'counter_offered' => 'Counter Offered',
            'confirmed' => 'Confirmed',
            'in_progress' => 'In Progress',
            'visited' => 'Visited',
            default => 'Proposed',
        };
    }

    private function normalizeTimestampInput(mixed $value): ?string
    {
        $raw = string_or_null($value);
        if ($raw === null) {
            return null;
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            throw new InvalidArgumentException('Invalid visit date/time.');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function normalizeTimestamp(mixed $value): string
    {
        $raw = string_or_null($value);
        if ($raw === null) {
            return '';
        }

        return str_replace(' ', 'T', $raw);
    }

    private function decodeJson(mixed $json): array
    {
        if (!is_string($json) || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
