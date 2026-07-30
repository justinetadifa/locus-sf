<?php
declare(strict_types=1);

namespace App\Repositories;

use InvalidArgumentException;
use OutOfBoundsException;
use PDO;

final class DocumentRequestRepository
{
    private const REQUEST_STATUSES = ['requested', 'in_review', 'fulfilled', 'declined'];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listByProperty(int $propertyId, ?array $user): array
    {
        $property = $this->propertyRow($propertyId);
        if (!$this->canViewPropertyRequests($property, $user)) {
            return [];
        }

        $role = (string) ($user['role'] ?? 'guest');
        $params = ['property_id' => $propertyId];
        $where = 'WHERE r.property_id = :property_id';

        if ($role === 'investor') {
            $params['requester_user_id'] = (int) ($user['id'] ?? 0);
            $where .= ' AND (r.requester_user_id = :requester_user_id OR r.requester_role = \'admin\')';
        }

        $statement = $this->pdo->prepare(
            "SELECT
                r.id,
                r.property_id,
                r.requester_user_id,
                r.seller_user_id,
                r.requester_name,
                r.requester_role,
                r.document_name,
                r.note,
                r.status,
                r.response_note,
                r.created_at,
                r.updated_at,
                r.resolved_at,
                p.name AS property_name,
                p.barangay AS property_barangay
             FROM property_document_requests r
             INNER JOIN properties p ON p.id = r.property_id
             {$where}
             ORDER BY
                CASE WHEN r.status IN ('requested', 'in_review') THEN 0 ELSE 1 END,
                r.updated_at DESC,
                r.id DESC"
        );
        $statement->execute($params);

        return array_map([$this, 'hydrate'], $statement->fetchAll());
    }

    public function inbox(array $user): array
    {
        $role = (string) ($user['role'] ?? 'guest');
        $userId = (int) ($user['id'] ?? 0);
        if ($role === 'guest' || $userId < 1) {
            return [];
        }

        $params = [];
        $where = '';
        if ($role === 'seller') {
            $where = 'WHERE r.seller_user_id = :user_id';
            $params['user_id'] = $userId;
        } elseif ($role === 'investor') {
            $where = 'WHERE r.requester_user_id = :user_id';
            $params['user_id'] = $userId;
        }

        $statement = $this->pdo->prepare(
            "SELECT
                r.id,
                r.property_id,
                r.requester_user_id,
                r.seller_user_id,
                r.requester_name,
                r.requester_role,
                r.document_name,
                r.note,
                r.status,
                r.response_note,
                r.created_at,
                r.updated_at,
                r.resolved_at,
                p.name AS property_name,
                p.barangay AS property_barangay
             FROM property_document_requests r
             INNER JOIN properties p ON p.id = r.property_id
             {$where}
             ORDER BY
                CASE WHEN r.status IN ('requested', 'in_review') THEN 0 ELSE 1 END,
                r.updated_at DESC,
                r.id DESC"
        );
        $statement->execute($params);

        return array_map([$this, 'hydrate'], $statement->fetchAll());
    }

    public function create(int $propertyId, array $user, string $documentName, ?string $note = null): array
    {
        $property = $this->propertyRow($propertyId);
        if (!$this->canCreateRequest($property, $user)) {
            throw new InvalidArgumentException('You cannot request documents for this listing.');
        }

        $documentName = string_or_null($documentName);
        if ($documentName === null) {
            throw new InvalidArgumentException('A document request label is required.');
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO property_document_requests (
                property_id, requester_user_id, seller_user_id, requester_name, requester_role, document_name, note, status
             ) VALUES (
                :property_id, :requester_user_id, :seller_user_id, :requester_name, :requester_role, :document_name, :note, :status
             )'
        );
        $statement->execute([
            'property_id' => $propertyId,
            'requester_user_id' => int_or_null($user['id'] ?? null),
            'seller_user_id' => int_or_null($property['seller_user_id'] ?? null),
            'requester_name' => (string) ($user['name'] ?? 'Platform User'),
            'requester_role' => (string) ($user['role'] ?? 'guest'),
            'document_name' => $documentName,
            'note' => string_or_null($note),
            'status' => 'requested',
        ]);

        return $this->find((int) $this->pdo->lastInsertId(), $user);
    }

    public function updateStatus(int $requestId, array $user, string $status, ?string $responseNote = null): array
    {
        $request = $this->requestRow($requestId);
        if (!$this->canManageRequest($request, $user)) {
            throw new InvalidArgumentException('You do not have permission to update this document request.');
        }

        $normalizedStatus = $this->normalizeStatus($status);
        $statement = $this->pdo->prepare(
            'UPDATE property_document_requests
             SET status = :status,
                 response_note = :response_note,
                 resolved_at = :resolved_at
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $requestId,
            'status' => $normalizedStatus,
            'response_note' => string_or_null($responseNote),
            'resolved_at' => in_array($normalizedStatus, ['fulfilled', 'declined'], true) ? gmdate('Y-m-d H:i:s') : null,
        ]);

        return $this->find($requestId, $user);
    }

    public function find(int $requestId, ?array $user): array
    {
        $request = $this->requestRow($requestId);
        if (!$this->canViewRequest($request, $user)) {
            throw new InvalidArgumentException('You do not have access to this document request.');
        }

        return $this->hydrate($request);
    }

    private function requestRow(int $requestId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                r.id,
                r.property_id,
                r.requester_user_id,
                r.seller_user_id,
                r.requester_name,
                r.requester_role,
                r.document_name,
                r.note,
                r.status,
                r.response_note,
                r.created_at,
                r.updated_at,
                r.resolved_at,
                p.name AS property_name,
                p.barangay AS property_barangay,
                p.approval_state,
                p.seller_user_id AS property_seller_user_id
             FROM property_document_requests r
             INNER JOIN properties p ON p.id = r.property_id
             WHERE r.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $requestId]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            throw new OutOfBoundsException('Document request not found.');
        }

        return $row;
    }

    private function propertyRow(int $propertyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, approval_state, seller_user_id
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

    private function canViewPropertyRequests(array $property, ?array $user): bool
    {
        $role = (string) ($user['role'] ?? 'guest');
        $userId = (int) ($user['id'] ?? 0);

        return match ($role) {
            'admin' => true,
            'seller' => $userId > 0 && $userId === (int) ($property['seller_user_id'] ?? 0),
            'investor' => strtolower((string) ($property['approval_state'] ?? 'approved')) === 'approved',
            default => false,
        };
    }

    private function canCreateRequest(array $property, array $user): bool
    {
        $role = (string) ($user['role'] ?? 'guest');
        if ($role === 'admin') {
            return true;
        }

        return $role === 'investor' && strtolower((string) ($property['approval_state'] ?? 'approved')) === 'approved';
    }

    private function canManageRequest(array $request, array $user): bool
    {
        $role = (string) ($user['role'] ?? 'guest');
        $userId = (int) ($user['id'] ?? 0);

        return match ($role) {
            'admin' => true,
            'seller' => $userId > 0 && $userId === (int) ($request['property_seller_user_id'] ?? $request['seller_user_id'] ?? 0),
            default => false,
        };
    }

    private function canViewRequest(array $request, ?array $user): bool
    {
        $role = (string) ($user['role'] ?? 'guest');
        $userId = (int) ($user['id'] ?? 0);

        return match ($role) {
            'admin' => true,
            'seller' => $userId > 0 && $userId === (int) ($request['property_seller_user_id'] ?? $request['seller_user_id'] ?? 0),
            'investor' => $userId > 0 && $userId === (int) ($request['requester_user_id'] ?? 0),
            default => false,
        };
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        if (!in_array($normalized, self::REQUEST_STATUSES, true)) {
            throw new InvalidArgumentException('Invalid document request status.');
        }

        return $normalized;
    }

    private function hydrate(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'propertyId' => (int) ($row['property_id'] ?? 0),
            'propertyName' => (string) ($row['property_name'] ?? ''),
            'propertyBarangay' => $row['property_barangay'] !== null ? (string) $row['property_barangay'] : null,
            'requesterUserId' => isset($row['requester_user_id']) ? int_or_null($row['requester_user_id']) : null,
            'sellerUserId' => isset($row['seller_user_id']) ? int_or_null($row['seller_user_id']) : null,
            'requesterName' => (string) ($row['requester_name'] ?? ''),
            'requesterRole' => (string) ($row['requester_role'] ?? 'investor'),
            'documentName' => (string) ($row['document_name'] ?? ''),
            'note' => $row['note'] !== null ? (string) $row['note'] : null,
            'status' => $this->normalizeStatus((string) ($row['status'] ?? 'requested')),
            'responseNote' => $row['response_note'] !== null ? (string) $row['response_note'] : null,
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'updatedAt' => (string) ($row['updated_at'] ?? ''),
            'resolvedAt' => $row['resolved_at'] !== null ? (string) $row['resolved_at'] : null,
        ];
    }
}
