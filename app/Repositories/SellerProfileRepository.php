<?php
declare(strict_types=1);

namespace App\Repositories;

use InvalidArgumentException;
use PDO;

final class SellerProfileRepository
{
    private const SELLER_TYPES = ['individual', 'company', 'broker'];
    private const APPLICATION_STATUSES = ['draft', 'pending_review', 'verified', 'rejected', 'suspended'];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByUserId(int $userId): ?array
    {
        if ($userId < 1) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT
                sp.user_id,
                sp.seller_type,
                sp.legal_name,
                sp.display_name,
                sp.phone,
                sp.company_name,
                sp.business_registration_no,
                sp.government_id_no,
                sp.address_line,
                sp.barangay,
                sp.city,
                sp.authorization_basis,
                sp.application_status,
                sp.review_notes,
                sp.submitted_at,
                sp.reviewed_at,
                sp.reviewed_by_user_id,
                sp.created_at,
                sp.updated_at,
                u.name AS user_name,
                u.email AS user_email,
                u.identity_verification_status,
                u.identity_verified_at,
                reviewer.name AS reviewed_by_name,
                COUNT(DISTINCT p.id) AS listing_count,
                SUM(CASE WHEN LOWER(COALESCE(p.approval_state, \'\')) = \'pending_review\' THEN 1 ELSE 0 END) AS pending_listing_count
             FROM seller_profiles sp
             INNER JOIN users u ON u.id = sp.user_id
             LEFT JOIN users reviewer ON reviewer.id = sp.reviewed_by_user_id
             LEFT JOIN properties p ON p.seller_user_id = sp.user_id
             WHERE sp.user_id = :user_id
             GROUP BY
                sp.user_id,
                sp.seller_type,
                sp.legal_name,
                sp.display_name,
                sp.phone,
                sp.company_name,
                sp.business_registration_no,
                sp.government_id_no,
                sp.address_line,
                sp.barangay,
                sp.city,
                sp.authorization_basis,
                sp.application_status,
                sp.review_notes,
                sp.submitted_at,
                sp.reviewed_at,
                sp.reviewed_by_user_id,
                sp.created_at,
                sp.updated_at,
                u.name,
                u.email,
                u.identity_verification_status,
                u.identity_verified_at,
                reviewer.name
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);

        $row = $statement->fetch();
        return is_array($row) ? $this->hydrate($row, true) : null;
    }

    public function findOrInitializeByUser(array $user): array
    {
        $userId = (int) ($user['id'] ?? 0);
        $profile = $this->findByUserId($userId);
        if ($profile !== null) {
            return $profile;
        }

        return $this->defaultProfile($this->sellerUser($userId, $user));
    }

    public function queue(?string $status = null): array
    {
        $where = '';
        $params = [];
        if ($status !== null && strtolower(trim($status)) !== 'all') {
            $where = 'WHERE sp.application_status = :application_status';
            $params['application_status'] = $this->normalizeApplicationStatus($status);
        }

        $statement = $this->pdo->prepare(
            'SELECT
                sp.user_id,
                sp.seller_type,
                sp.legal_name,
                sp.display_name,
                sp.phone,
                sp.company_name,
                sp.business_registration_no,
                sp.government_id_no,
                sp.address_line,
                sp.barangay,
                sp.city,
                sp.authorization_basis,
                sp.application_status,
                sp.review_notes,
                sp.submitted_at,
                sp.reviewed_at,
                sp.reviewed_by_user_id,
                sp.created_at,
                sp.updated_at,
                u.name AS user_name,
                u.email AS user_email,
                u.identity_verification_status,
                u.identity_verified_at,
                reviewer.name AS reviewed_by_name,
                COUNT(DISTINCT p.id) AS listing_count,
                SUM(CASE WHEN LOWER(COALESCE(p.approval_state, \'\')) = \'pending_review\' THEN 1 ELSE 0 END) AS pending_listing_count
             FROM seller_profiles sp
             INNER JOIN users u ON u.id = sp.user_id
             LEFT JOIN users reviewer ON reviewer.id = sp.reviewed_by_user_id
             LEFT JOIN properties p ON p.seller_user_id = sp.user_id
             ' . $where . '
             GROUP BY
                sp.user_id,
                sp.seller_type,
                sp.legal_name,
                sp.display_name,
                sp.phone,
                sp.company_name,
                sp.business_registration_no,
                sp.government_id_no,
                sp.address_line,
                sp.barangay,
                sp.city,
                sp.authorization_basis,
                sp.application_status,
                sp.review_notes,
                sp.submitted_at,
                sp.reviewed_at,
                sp.reviewed_by_user_id,
                sp.created_at,
                sp.updated_at,
                u.name,
                u.email,
                u.identity_verification_status,
                u.identity_verified_at,
                reviewer.name
             ORDER BY
                CASE sp.application_status
                    WHEN \'pending_review\' THEN 0
                    WHEN \'rejected\' THEN 1
                    WHEN \'verified\' THEN 2
                    WHEN \'suspended\' THEN 3
                    ELSE 4
                END,
                COALESCE(sp.submitted_at, sp.updated_at, sp.created_at) DESC,
                sp.user_id DESC'
        );
        $statement->execute($params);

        return array_map(
            fn (array $row): array => $this->hydrate($row, true),
            $statement->fetchAll()
        );
    }

    public function createOrUpdateForUser(int $userId, array $payload, bool $submit = false): array
    {
        $user = $this->sellerUser($userId);
        $existing = $this->findByUserId($userId);
        $normalized = $this->normalizePayload($payload, $user, $existing, $submit);

        $this->assertUniqueFields($normalized, $userId);

        if ($existing === null) {
            $statement = $this->pdo->prepare(
                'INSERT INTO seller_profiles (
                    user_id, seller_type, legal_name, display_name, phone, company_name,
                    business_registration_no, government_id_no, address_line, barangay, city,
                    authorization_basis, application_status, review_notes, submitted_at,
                    reviewed_at, reviewed_by_user_id
                 ) VALUES (
                    :user_id, :seller_type, :legal_name, :display_name, :phone, :company_name,
                    :business_registration_no, :government_id_no, :address_line, :barangay, :city,
                    :authorization_basis, :application_status, :review_notes, :submitted_at,
                    :reviewed_at, :reviewed_by_user_id
                 )'
            );
            $statement->execute(array_merge(['user_id' => $userId], $normalized));
        } else {
            $statement = $this->pdo->prepare(
                'UPDATE seller_profiles
                 SET seller_type = :seller_type,
                     legal_name = :legal_name,
                     display_name = :display_name,
                     phone = :phone,
                     company_name = :company_name,
                     business_registration_no = :business_registration_no,
                     government_id_no = :government_id_no,
                     address_line = :address_line,
                     barangay = :barangay,
                     city = :city,
                     authorization_basis = :authorization_basis,
                     application_status = :application_status,
                     review_notes = :review_notes,
                     submitted_at = :submitted_at,
                     reviewed_at = :reviewed_at,
                     reviewed_by_user_id = :reviewed_by_user_id
                 WHERE user_id = :user_id'
            );
            $statement->execute(array_merge(['user_id' => $userId], $normalized));
        }

        $profile = $this->findByUserId($userId);
        if ($profile === null) {
            throw new InvalidArgumentException('Unable to save the seller verification profile.');
        }

        return $profile;
    }

    public function review(int $userId, string $status, ?int $reviewedByUserId = null, ?string $reviewNotes = null): array
    {
        $profile = $this->findByUserId($userId);
        if ($profile === null) {
            throw new InvalidArgumentException('Seller profile not found.');
        }

        $normalizedStatus = $this->normalizeApplicationStatus($status);
        $reviewedAt = in_array($normalizedStatus, ['verified', 'rejected', 'suspended'], true)
            ? gmdate('Y-m-d H:i:s')
            : null;
        $statement = $this->pdo->prepare(
            'UPDATE seller_profiles
             SET application_status = :application_status,
                 review_notes = :review_notes,
                 reviewed_at = :reviewed_at,
                 reviewed_by_user_id = :reviewed_by_user_id,
                 submitted_at = CASE
                    WHEN :application_status = \'pending_review\'
                    THEN COALESCE(submitted_at, CURRENT_TIMESTAMP)
                    ELSE submitted_at
                 END
             WHERE user_id = :user_id'
        );
        $statement->execute([
            'user_id' => $userId,
            'application_status' => $normalizedStatus,
            'review_notes' => string_or_null($reviewNotes),
            'reviewed_at' => $reviewedAt,
            'reviewed_by_user_id' => $reviewedByUserId,
        ]);

        $updated = $this->findByUserId($userId);
        if ($updated === null) {
            throw new InvalidArgumentException('Seller profile not found.');
        }

        return $updated;
    }

    private function sellerUser(int $userId, ?array $fallback = null): array
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('A valid seller account is required.');
        }

        if (is_array($fallback) && (int) ($fallback['id'] ?? 0) === $userId && ($fallback['role'] ?? null) === 'seller') {
            return [
                'id' => $userId,
                'role' => 'seller',
                'name' => (string) ($fallback['name'] ?? ''),
                'email' => (string) ($fallback['email'] ?? ''),
                'identityVerificationStatus' => (string) ($fallback['identityVerificationStatus'] ?? 'unverified'),
                'identityVerifiedAt' => $fallback['identityVerifiedAt'] ?? null,
            ];
        }

        $statement = $this->pdo->prepare(
            'SELECT id, role, name, email, identity_verification_status, identity_verified_at
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $userId]);
        $row = $statement->fetch();
        if (!is_array($row) || ($row['role'] ?? null) !== 'seller') {
            throw new InvalidArgumentException('Seller account not found.');
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'role' => 'seller',
            'name' => (string) ($row['name'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'identityVerificationStatus' => (string) ($row['identity_verification_status'] ?? 'unverified'),
            'identityVerifiedAt' => $row['identity_verified_at'] !== null ? (string) $row['identity_verified_at'] : null,
        ];
    }

    private function normalizePayload(array $payload, array $user, ?array $existing, bool $submit): array
    {
        $sellerType = $this->normalizeSellerType((string) ($payload['seller_type'] ?? $payload['sellerType'] ?? ($existing['sellerType'] ?? 'individual')));
        $legalName = string_or_null($payload['legal_name'] ?? $payload['legalName'] ?? null)
            ?? string_or_null($existing['legalName'] ?? null)
            ?? string_or_null($user['name'] ?? null);
        $displayName = string_or_null($payload['display_name'] ?? $payload['displayName'] ?? null)
            ?? string_or_null($existing['displayName'] ?? null);
        $phone = string_or_null($payload['phone'] ?? null) ?? string_or_null($existing['phone'] ?? null);
        $companyName = string_or_null($payload['company_name'] ?? $payload['companyName'] ?? null)
            ?? string_or_null($existing['companyName'] ?? null);
        $businessRegistrationNo = string_or_null($payload['business_registration_no'] ?? $payload['businessRegistrationNo'] ?? null)
            ?? string_or_null($existing['businessRegistrationNo'] ?? null);
        $governmentIdNo = string_or_null($payload['government_id_no'] ?? $payload['governmentIdNo'] ?? null)
            ?? string_or_null($existing['governmentIdNo'] ?? null);
        $addressLine = string_or_null($payload['address_line'] ?? $payload['addressLine'] ?? null)
            ?? string_or_null($existing['addressLine'] ?? null);
        $barangay = string_or_null($payload['barangay'] ?? null) ?? string_or_null($existing['barangay'] ?? null);
        $city = string_or_null($payload['city'] ?? null)
            ?? string_or_null($existing['city'] ?? null)
            ?? 'San Fernando, La Union';
        $authorizationBasis = string_or_null($payload['authorization_basis'] ?? $payload['authorizationBasis'] ?? null)
            ?? string_or_null($existing['authorizationBasis'] ?? null);

        $applicationStatus = $existing['applicationStatus'] ?? $this->identityToApplicationStatus((string) ($user['identityVerificationStatus'] ?? 'unverified'));
        $reviewNotes = string_or_null($existing['reviewNotes'] ?? null);
        $submittedAt = $existing['submittedAt'] ?? null;
        $reviewedAt = $existing['reviewedAt'] ?? null;
        $reviewedByUserId = $existing['reviewedByUserId'] ?? null;

        if ($submit) {
            $this->validateSubmissionFields(
                $sellerType,
                $legalName,
                $phone,
                $governmentIdNo,
                $addressLine,
                $city,
                $authorizationBasis,
                $businessRegistrationNo
            );
            $applicationStatus = 'pending_review';
            $reviewNotes = null;
            $submittedAt = gmdate('Y-m-d H:i:s');
            $reviewedAt = null;
            $reviewedByUserId = null;
        } elseif (!in_array($applicationStatus, ['verified', 'suspended'], true)) {
            $applicationStatus = 'draft';
        }

        return [
            'seller_type' => $sellerType,
            'legal_name' => $legalName,
            'display_name' => $displayName,
            'phone' => $phone,
            'company_name' => $companyName,
            'business_registration_no' => $businessRegistrationNo,
            'government_id_no' => $governmentIdNo,
            'address_line' => $addressLine,
            'barangay' => $barangay,
            'city' => $city,
            'authorization_basis' => $authorizationBasis,
            'application_status' => $applicationStatus,
            'review_notes' => $reviewNotes,
            'submitted_at' => $submittedAt,
            'reviewed_at' => $reviewedAt,
            'reviewed_by_user_id' => $reviewedByUserId,
        ];
    }

    private function validateSubmissionFields(
        string $sellerType,
        ?string $legalName,
        ?string $phone,
        ?string $governmentIdNo,
        ?string $addressLine,
        ?string $city,
        ?string $authorizationBasis,
        ?string $businessRegistrationNo
    ): void {
        if ($legalName === null) {
            throw new InvalidArgumentException('A legal or business name is required.');
        }
        if ($phone === null) {
            throw new InvalidArgumentException('A seller contact number is required.');
        }
        if ($governmentIdNo === null) {
            throw new InvalidArgumentException('A government ID or license number is required.');
        }
        if ($addressLine === null) {
            throw new InvalidArgumentException('A business or mailing address is required.');
        }
        if ($city === null) {
            throw new InvalidArgumentException('A city is required.');
        }
        if ($authorizationBasis === null) {
            throw new InvalidArgumentException('Please describe your authority to represent the property.');
        }
        if (in_array($sellerType, ['company', 'broker'], true) && $businessRegistrationNo === null) {
            throw new InvalidArgumentException('A business registration or broker license number is required for this seller type.');
        }
    }

    private function assertUniqueFields(array $payload, int $userId): void
    {
        $this->assertUniqueValue('phone', $payload['phone'], $userId, 'That phone number is already attached to another seller account.');
        $this->assertUniqueValue('government_id_no', $payload['government_id_no'], $userId, 'That government ID or license number is already attached to another seller account.');
        $this->assertUniqueValue('business_registration_no', $payload['business_registration_no'], $userId, 'That business registration number is already attached to another seller account.');
    }

    private function assertUniqueValue(string $column, ?string $value, int $userId, string $message): void
    {
        if ($value === null) {
            return;
        }

        $statement = $this->pdo->prepare(
            sprintf(
                'SELECT user_id
                 FROM seller_profiles
                 WHERE %s = :value
                   AND user_id <> :user_id
                 LIMIT 1',
                $column
            )
        );
        $statement->execute([
            'value' => $value,
            'user_id' => $userId,
        ]);

        if ($statement->fetch()) {
            throw new InvalidArgumentException($message);
        }
    }

    private function defaultProfile(array $user): array
    {
        return [
            'userId' => (int) ($user['id'] ?? 0),
            'sellerType' => 'individual',
            'legalName' => (string) ($user['name'] ?? ''),
            'displayName' => (string) ($user['name'] ?? ''),
            'phone' => null,
            'companyName' => null,
            'businessRegistrationNo' => null,
            'governmentIdNo' => null,
            'addressLine' => null,
            'barangay' => null,
            'city' => 'San Fernando, La Union',
            'authorizationBasis' => null,
            'applicationStatus' => $this->identityToApplicationStatus((string) ($user['identityVerificationStatus'] ?? 'unverified')),
            'reviewNotes' => null,
            'submittedAt' => null,
            'reviewedAt' => null,
            'reviewedByUserId' => null,
            'reviewedByName' => null,
            'createdAt' => null,
            'updatedAt' => null,
            'name' => (string) ($user['name'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'identityVerificationStatus' => (string) ($user['identityVerificationStatus'] ?? 'unverified'),
            'identityVerifiedAt' => $user['identityVerifiedAt'] ?? null,
            'listingCount' => 0,
            'pendingListingCount' => 0,
            'hasProfile' => false,
        ];
    }

    private function hydrate(array $row, bool $hasProfile): array
    {
        return [
            'userId' => (int) ($row['user_id'] ?? 0),
            'sellerType' => $this->normalizeSellerType((string) ($row['seller_type'] ?? 'individual')),
            'legalName' => $row['legal_name'] !== null ? (string) $row['legal_name'] : '',
            'displayName' => $row['display_name'] !== null ? (string) $row['display_name'] : null,
            'phone' => $row['phone'] !== null ? (string) $row['phone'] : null,
            'companyName' => $row['company_name'] !== null ? (string) $row['company_name'] : null,
            'businessRegistrationNo' => $row['business_registration_no'] !== null ? (string) $row['business_registration_no'] : null,
            'governmentIdNo' => $row['government_id_no'] !== null ? (string) $row['government_id_no'] : null,
            'addressLine' => $row['address_line'] !== null ? (string) $row['address_line'] : null,
            'barangay' => $row['barangay'] !== null ? (string) $row['barangay'] : null,
            'city' => $row['city'] !== null ? (string) $row['city'] : 'San Fernando, La Union',
            'authorizationBasis' => $row['authorization_basis'] !== null ? (string) $row['authorization_basis'] : null,
            'applicationStatus' => $this->normalizeApplicationStatus((string) ($row['application_status'] ?? 'draft')),
            'reviewNotes' => $row['review_notes'] !== null ? (string) $row['review_notes'] : null,
            'submittedAt' => $row['submitted_at'] !== null ? (string) $row['submitted_at'] : null,
            'reviewedAt' => $row['reviewed_at'] !== null ? (string) $row['reviewed_at'] : null,
            'reviewedByUserId' => isset($row['reviewed_by_user_id']) ? int_or_null($row['reviewed_by_user_id']) : null,
            'reviewedByName' => $row['reviewed_by_name'] !== null ? (string) $row['reviewed_by_name'] : null,
            'createdAt' => $row['created_at'] !== null ? (string) $row['created_at'] : null,
            'updatedAt' => $row['updated_at'] !== null ? (string) $row['updated_at'] : null,
            'name' => (string) ($row['user_name'] ?? ''),
            'email' => (string) ($row['user_email'] ?? ''),
            'identityVerificationStatus' => (string) ($row['identity_verification_status'] ?? 'unverified'),
            'identityVerifiedAt' => $row['identity_verified_at'] !== null ? (string) $row['identity_verified_at'] : null,
            'listingCount' => (int) ($row['listing_count'] ?? 0),
            'pendingListingCount' => (int) ($row['pending_listing_count'] ?? 0),
            'hasProfile' => $hasProfile,
        ];
    }

    private function normalizeSellerType(string $sellerType): string
    {
        $normalized = strtolower(trim($sellerType));
        return in_array($normalized, self::SELLER_TYPES, true) ? $normalized : 'individual';
    }

    private function normalizeApplicationStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        return in_array($normalized, self::APPLICATION_STATUSES, true) ? $normalized : 'draft';
    }

    private function identityToApplicationStatus(string $identityStatus): string
    {
        return match (strtolower(trim($identityStatus))) {
            'verified' => 'verified',
            'rejected' => 'rejected',
            'suspended' => 'suspended',
            'pending' => 'pending_review',
            default => 'draft',
        };
    }
}
