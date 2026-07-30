<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\JsonData;
use InvalidArgumentException;
use OutOfBoundsException;
use PDO;
use Throwable;

final class PropertyRepository
{
    private const DEFAULT_CITY = 'San Fernando, La Union';
    private const APPROVAL_STATES = ['draft', 'pending_review', 'approved', 'rejected', 'archived'];
    private const READINESS_PILLAR_WEIGHTS = [
        'spatial' => 18,
        'infrastructure' => 22,
        'economic' => 22,
        'institutional' => 18,
        'legal' => 20,
    ];
    private const DOCUMENT_REQUIREMENTS = [
        ['key' => 'title_copy', 'label' => 'Title Copy'],
        ['key' => 'tax_declaration', 'label' => 'Tax Declaration'],
        ['key' => 'survey_plan', 'label' => 'Survey Plan'],
        ['key' => 'zoning_clearance', 'label' => 'Zoning Clearance'],
        ['key' => 'site_photos', 'label' => 'Site Photos'],
        ['key' => 'hazard_report', 'label' => 'Hazard / Environmental Report'],
    ];
    private const DOCUMENT_PROGRESS = [
        'missing' => 0,
        'requested' => 25,
        'submitted' => 75,
        'reviewed' => 100,
    ];
    private const UTILITY_STATUS_SCORES = [
        'full_ready' => 100,
        'power_water' => 82,
        'partial' => 62,
        'limited' => 40,
        'off_grid' => 18,
    ];
    private const UTILITY_STATUS_LABELS = [
        'full_ready' => 'Full Fiber / Power / Water',
        'power_water' => 'Power / Water Ready',
        'partial' => 'Partial Utility Service',
        'limited' => 'Limited Utility Service',
        'off_grid' => 'Off Grid',
    ];

    private PDO $pdo;
    private ?AuditLogRepository $auditLogs;

    public function __construct(PDO $pdo, ?AuditLogRepository $auditLogs = null)
    {
        $this->pdo = $pdo;
        $this->auditLogs = $auditLogs;
    }

    public function all(?array $user = null): array
    {
        $params = [];
        $sql = $this->baseSelect();
        $visibility = $this->visibilityCondition($user, $params);
        if ($visibility !== '') {
            $sql .= ' WHERE ' . $visibility;
        }
        $sql .= ' ORDER BY p.created_at DESC, p.id DESC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll();

        return $this->hydrateProperties($rows);
    }

    public function find(int $propertyId, ?array $user = null): array
    {
        return $this->hydrateProperties([$this->rawPropertyRow($propertyId, $user, true)])[0];
    }

    public function create(array $payload, ?array $actor = null): array
    {
        $property = $this->normalizePropertyPayload($payload);

        $statement = $this->pdo->prepare(
            'INSERT INTO properties (
                name, city, lat, lng, area, price, price_per_sqm, status, approval_state, score, type, corridor,
                tags_json, facilities_json, road_access, image_url, description, barangay, owner_contact_json,
                documents_json, seller_user_id, documents_reviewed_at, site_verified_at, last_confirmed_available_at,
                dist_to_road_km, utility_status, zoning_score, existing_land_use, zoning_classification,
                clup_allowed_uses_json, clup_conditional_uses_json, clup_restricted_uses_json, clup_source_reference,
                clup_verified_at, assessed_value_sqm, readiness_notes
            ) VALUES (
                :name, :city, :lat, :lng, :area, :price, :price_per_sqm, :status, :approval_state, :score, :type, :corridor,
                :tags_json, :facilities_json, :road_access, :image_url, :description, :barangay, :owner_contact_json,
                :documents_json, :seller_user_id, :documents_reviewed_at, :site_verified_at, :last_confirmed_available_at,
                :dist_to_road_km, :utility_status, :zoning_score, :existing_land_use, :zoning_classification,
                :clup_allowed_uses_json, :clup_conditional_uses_json, :clup_restricted_uses_json, :clup_source_reference,
                :clup_verified_at, :assessed_value_sqm, :readiness_notes
            )'
        );

        $this->pdo->beginTransaction();

        try {
            $statement->execute($property);
            $propertyId = (int) $this->pdo->lastInsertId();

            $this->syncPrimaryMedia($propertyId, $property['image_url'], $property['name']);
            $this->ensureDueDiligenceRecord($propertyId);
            $created = $this->rawPropertyRow($propertyId, null, false);
            $this->recordPropertyAudit('CREATE', $propertyId, null, $created, $actor);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $this->hydrateProperties([$this->rawPropertyRow($propertyId, null, false)])[0];
    }

    public function update(int $propertyId, array $payload, ?array $actor = null): array
    {
        $existing = $this->rawPropertyRow($propertyId, null, false);
        $property = $this->normalizePropertyPayload($payload, $existing);
        $property['id'] = $propertyId;

        $statement = $this->pdo->prepare(
            'UPDATE properties SET
                name = :name,
                city = :city,
                lat = :lat,
                lng = :lng,
                area = :area,
                price = :price,
                price_per_sqm = :price_per_sqm,
                status = :status,
                approval_state = :approval_state,
                score = :score,
                type = :type,
                corridor = :corridor,
                tags_json = :tags_json,
                facilities_json = :facilities_json,
                road_access = :road_access,
                image_url = :image_url,
                description = :description,
                barangay = :barangay,
                owner_contact_json = :owner_contact_json,
                documents_json = :documents_json,
                seller_user_id = :seller_user_id,
                documents_reviewed_at = :documents_reviewed_at,
                site_verified_at = :site_verified_at,
                last_confirmed_available_at = :last_confirmed_available_at,
                dist_to_road_km = :dist_to_road_km,
                utility_status = :utility_status,
                zoning_score = :zoning_score,
                existing_land_use = :existing_land_use,
                zoning_classification = :zoning_classification,
                clup_allowed_uses_json = :clup_allowed_uses_json,
                clup_conditional_uses_json = :clup_conditional_uses_json,
                clup_restricted_uses_json = :clup_restricted_uses_json,
                clup_source_reference = :clup_source_reference,
                clup_verified_at = :clup_verified_at,
                assessed_value_sqm = :assessed_value_sqm,
                readiness_notes = :readiness_notes
             WHERE id = :id'
        );

        $this->pdo->beginTransaction();

        try {
            $statement->execute($property);
            $this->syncPrimaryMedia($propertyId, $property['image_url'], $property['name']);
            $this->ensureDueDiligenceRecord($propertyId);
            $updated = $this->rawPropertyRow($propertyId, null, false);
            $this->recordPropertyAudit('EDIT', $propertyId, $existing, $updated, $actor);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $this->hydrateProperties([$this->rawPropertyRow($propertyId, null, false)])[0];
    }

    public function delete(int $propertyId, ?array $actor = null): void
    {
        $existing = $this->rawPropertyRow($propertyId, null, false);

        $statement = $this->pdo->prepare('DELETE FROM properties WHERE id = :id');
        $this->pdo->beginTransaction();

        try {
            $this->recordPropertyAudit('DELETE', $propertyId, $existing, null, $actor);
            $statement->execute(['id' => $propertyId]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function updateBarangay(int $propertyId, ?string $barangay): array
    {
        $statement = $this->pdo->prepare('UPDATE properties SET barangay = :barangay WHERE id = :id');
        $statement->execute([
            'barangay' => $barangay,
            'id' => $propertyId,
        ]);

        return $this->hydrateProperties([$this->rawPropertyRow($propertyId, null, false)])[0];
    }

    public function isOwnedBySeller(int $propertyId, int $sellerUserId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT id
             FROM properties
             WHERE id = :id
               AND seller_user_id = :seller_user_id
             LIMIT 1'
        );
        $statement->execute([
            'id' => $propertyId,
            'seller_user_id' => $sellerUserId,
        ]);

        return (bool) $statement->fetchColumn();
    }

    public function dueDiligenceState(int $propertyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT state_json FROM property_due_diligence WHERE property_id = :property_id LIMIT 1'
        );
        $statement->execute(['property_id' => $propertyId]);
        $row = $statement->fetch();

        if (!$row) {
            return [];
        }

        return $this->decodeJson($row['state_json'] ?? '{}');
    }

    public function saveDueDiligenceState(int $propertyId, array $state, ?array $actor = null): array
    {
        $before = $this->dueDiligenceState($propertyId);
        $statement = $this->pdo->prepare(
            'INSERT INTO property_due_diligence (property_id, state_json)
             VALUES (:property_id, :state_json)
             ON DUPLICATE KEY UPDATE state_json = VALUES(state_json), updated_at = CURRENT_TIMESTAMP'
        );
        $this->pdo->beginTransaction();

        try {
            $statement->execute([
                'property_id' => $propertyId,
                'state_json' => json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);

            $after = $this->dueDiligenceState($propertyId);
            if ($this->auditLogs !== null && $before !== $after) {
                $this->auditLogs->record(
                    isset($actor['id']) ? (int) $actor['id'] : null,
                    'EDIT',
                    'PROPERTY',
                    $propertyId,
                    [
                        'actorName' => (string) ($actor['name'] ?? 'Platform User'),
                        'actorRole' => (string) ($actor['role'] ?? 'system'),
                        'eventType' => 'DUE_DILIGENCE_EDIT',
                        'targetLabel' => $this->propertyTargetLabel($propertyId),
                        'summary' => 'Updated due diligence checklist and supporting readiness state.',
                        'before' => $before,
                        'after' => $after,
                        'changedFields' => $this->changedFields($before, $after),
                        'streamGroup' => 'all',
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

    public function voteTallies(int $propertyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT label, COUNT(*) AS votes
             FROM property_votes
             WHERE property_id = :property_id
             GROUP BY label
             ORDER BY votes DESC, label ASC'
        );
        $statement->execute(['property_id' => $propertyId]);

        $votes = [];
        foreach ($statement->fetchAll() as $row) {
            $votes[(string) $row['label']] = (int) $row['votes'];
        }

        return $votes;
    }

    public function castVote(int $propertyId, string $label): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO property_votes (property_id, label) VALUES (:property_id, :label)'
        );
        $statement->execute([
            'property_id' => $propertyId,
            'label' => $label,
        ]);

        return $this->voteTallies($propertyId);
    }

    public function mapViewport(array $properties): ?array
    {
        if ($properties === []) {
            return null;
        }

        $latitudes = array_map(static fn (array $property): float => (float) $property['lat'], $properties);
        $longitudes = array_map(static fn (array $property): float => (float) $property['lng'], $properties);

        return [
            'center' => [
                'lat' => round((min($latitudes) + max($latitudes)) / 2, 4),
                'lng' => round((min($longitudes) + max($longitudes)) / 2, 4),
            ],
            'bounds' => [
                'north' => max($latitudes),
                'south' => min($latitudes),
                'east' => max($longitudes),
                'west' => min($longitudes),
            ],
        ];
    }

    private function baseSelect(): string
    {
        return
            'SELECT
                p.*,
                seller.identity_verification_status AS seller_identity_verification_status,
                seller.identity_verified_at AS seller_identity_verified_at
             FROM properties p
             LEFT JOIN users seller ON seller.id = p.seller_user_id';
    }

    private function visibilityCondition(?array $user, array &$params): string
    {
        $role = (string) ($user['role'] ?? 'guest');
        $userId = (int) ($user['id'] ?? 0);

        if ($role === 'admin') {
            return '';
        }

        if ($role === 'seller' && $userId > 0) {
            $params['visible_seller_user_id'] = $userId;
            return '(p.approval_state = \'approved\' OR p.seller_user_id = :visible_seller_user_id)';
        }

        return 'p.approval_state = \'approved\'';
    }

    private function rawPropertyRow(int $propertyId, ?array $user = null, bool $enforceVisibility = true): array
    {
        $params = ['id' => $propertyId];
        $sql = $this->baseSelect() . ' WHERE p.id = :id';
        if ($enforceVisibility) {
            $visibility = $this->visibilityCondition($user, $params);
            if ($visibility !== '') {
                $sql .= ' AND ' . $visibility;
            }
        }
        $sql .= ' LIMIT 1';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        if (!$row) {
            throw new OutOfBoundsException('Property not found.');
        }

        return $row;
    }

    private function hydrateProperties(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $propertyIds = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $mediaMap = $this->mediaMap($propertyIds);
        $documentRequestSummaryMap = $this->documentRequestSummaryMap($propertyIds);
        $dueDiligenceSummaryMap = $this->dueDiligenceSummaryMap($propertyIds);
        $groundTruthSummaryMap = $this->groundTruthSummaryMap($propertyIds);
        $priceBenchmarkMap = $this->priceBenchmarkMap();

        return array_map(function (array $row) use ($mediaMap, $documentRequestSummaryMap, $dueDiligenceSummaryMap, $groundTruthSummaryMap, $priceBenchmarkMap): array {
            $propertyId = (int) $row['id'];
            $media = $mediaMap[$propertyId] ?? [];
            $documentStatuses = $this->normalizeDocumentStatuses($this->decodeJson($row['documents_json'] ?? '{}'));
            $documentCompletenessPct = $this->documentCompletenessPct($documentStatuses);
            $approvalState = $this->normalizeApprovalState((string) ($row['approval_state'] ?? 'approved'));
            $sellerIdentityStatus = $this->normalizeIdentityVerificationStatus((string) ($row['seller_identity_verification_status'] ?? 'unverified'));
            $documentsReviewedAt = $this->normalizeTimestamp($row['documents_reviewed_at'] ?? null);
            $siteVerifiedAt = $this->normalizeTimestamp($row['site_verified_at'] ?? null);
            $lastConfirmedAvailableAt = $this->normalizeTimestamp($row['last_confirmed_available_at'] ?? null);
            $updatedAt = $this->normalizeTimestamp($row['updated_at'] ?? null);
            $listingVerificationStatus = $this->listingVerificationStatus(
                $approvalState,
                $sellerIdentityStatus === 'verified',
                $documentsReviewedAt,
                $siteVerifiedAt,
                $documentCompletenessPct
            );
            $summary = $documentRequestSummaryMap[$propertyId] ?? [
                'count' => 0,
                'openCount' => 0,
                'pendingCount' => 0,
            ];
            $dueSummary = $dueDiligenceSummaryMap[$propertyId] ?? [
                'state' => [],
                'pct' => 0,
            ];
            $groundTruthSummary = $groundTruthSummaryMap[$propertyId] ?? [
                'multiplier' => 1.0,
                'visitCount' => 0,
                'latestVisitedAt' => null,
                'latestFieldAudit' => [],
                'latestAuditMultiplier' => null,
            ];
            $distToRoadKm = float_or_null($row['dist_to_road_km'] ?? null);
            $utilityStatus = $this->normalizeUtilityStatus($row['utility_status'] ?? null);
            $zoningScore = int_or_null($row['zoning_score'] ?? null);
            $clupAllowedUses = $this->decodeJson($row['clup_allowed_uses_json'] ?? '[]');
            $clupConditionalUses = $this->decodeJson($row['clup_conditional_uses_json'] ?? '[]');
            $clupRestrictedUses = $this->decodeJson($row['clup_restricted_uses_json'] ?? '[]');
            $clupVerifiedAt = $this->normalizeTimestamp($row['clup_verified_at'] ?? null);
            $pricePerSqm = $this->effectivePricePerSqm($row);
            $assessedValueSqm = $this->effectiveAssessedValueSqm($row['assessed_value_sqm'] ?? null, $pricePerSqm);
            $roadAccess = (int) $row['road_access'];
            $marketScore = (int) ($row['score'] ?? 82);
            $readinessMeta = $this->investmentReadiness([
                'id' => $propertyId,
                'type' => (string) $row['type'],
                'corridor' => (string) $row['corridor'],
                'barangay' => $row['barangay'] !== null ? (string) $row['barangay'] : null,
                'lat' => (float) $row['lat'],
                'lng' => (float) $row['lng'],
                'roadAccess' => $roadAccess,
                'pricePerSqm' => $pricePerSqm,
                'marketScore' => $marketScore,
                'approvalState' => $approvalState,
                'siteVerifiedAt' => $siteVerifiedAt,
                'documentsReviewedAt' => $documentsReviewedAt,
                'documentCompletenessPct' => $documentCompletenessPct,
                'listingVerificationStatus' => $listingVerificationStatus,
                'sellerIdentityStatus' => $sellerIdentityStatus,
                'distToRoadKm' => $distToRoadKm,
                'utilityStatus' => $utilityStatus,
                'zoningScore' => $zoningScore,
                'assessedValueSqm' => $assessedValueSqm,
                'readinessNotes' => string_or_null($row['readiness_notes'] ?? null),
                'dueDiligencePct' => (int) ($dueSummary['pct'] ?? 0),
                'groundTruthMultiplier' => (float) ($groundTruthSummary['multiplier'] ?? 1.0),
                'facilities' => $this->decodeJson($row['facilities_json'] ?? '[]'),
                'priceBenchmark' => $priceBenchmarkMap[(string) $row['type']] ?? ($priceBenchmarkMap['*'] ?? null),
            ]);

            return [
                'id' => $propertyId,
                'name' => (string) $row['name'],
                'propertyName' => (string) $row['name'],
                'city' => (string) ($row['city'] ?? self::DEFAULT_CITY),
                'lat' => (float) $row['lat'],
                'lng' => (float) $row['lng'],
                'area' => (float) $row['area'],
                'landArea' => (float) $row['area'],
                'price' => (int) $row['price'],
                'pricePerSqm' => $pricePerSqm,
                'status' => (string) $row['status'],
                'approvalState' => $approvalState,
                'marketScore' => $marketScore,
                'type' => (string) $row['type'],
                'propertyType' => (string) $row['type'],
                'corridor' => (string) $row['corridor'],
                'tags' => $this->decodeJson($row['tags_json'] ?? '[]'),
                'facilities' => $this->decodeJson($row['facilities_json'] ?? '[]'),
                'roadAccess' => $roadAccess,
                'imageUrl' => (string) $row['image_url'],
                'imagePath' => (string) $row['image_url'],
                'description' => (string) $row['description'],
                'barangay' => $row['barangay'] !== null ? (string) $row['barangay'] : null,
                'ownerContact' => $this->decodeJson($row['owner_contact_json'] ?? '{}'),
                'sellerUserId' => isset($row['seller_user_id']) ? int_or_null($row['seller_user_id']) : null,
                'sellerIdentityStatus' => $sellerIdentityStatus,
                'sellerIdentityVerifiedAt' => $this->normalizeTimestamp($row['seller_identity_verified_at'] ?? null),
                'documentsReviewedAt' => $documentsReviewedAt,
                'siteVerifiedAt' => $siteVerifiedAt,
                'lastConfirmedAvailableAt' => $lastConfirmedAvailableAt,
                'documentStatuses' => $documentStatuses,
                'documentChecklist' => self::DOCUMENT_REQUIREMENTS,
                'documentCompletenessPct' => $documentCompletenessPct,
                'listingVerificationStatus' => $listingVerificationStatus,
                'distToRoadKm' => $distToRoadKm,
                'utilityStatus' => $utilityStatus,
                'zoningScore' => $zoningScore,
                'clupProfile' => [
                    'existingLandUse' => string_or_null($row['existing_land_use'] ?? null),
                    'zoningClassification' => string_or_null($row['zoning_classification'] ?? null),
                    'allowedUses' => $clupAllowedUses,
                    'conditionalUses' => $clupConditionalUses,
                    'restrictedUses' => $clupRestrictedUses,
                    'sourceReference' => string_or_null($row['clup_source_reference'] ?? null),
                    'verifiedAt' => string_or_null($clupVerifiedAt),
                    'isVerified' => string_or_null($clupVerifiedAt) !== null,
                ],
                'assessedValueSqm' => $assessedValueSqm,
                'readinessNotes' => string_or_null($row['readiness_notes'] ?? null),
                'dueDiligencePct' => (int) ($dueSummary['pct'] ?? 0),
                'groundTruthMultiplier' => (float) ($groundTruthSummary['multiplier'] ?? 1.0),
                'groundTruthAdjustmentPct' => round((((float) ($groundTruthSummary['multiplier'] ?? 1.0)) - 1) * 100, 1),
                'groundTruthVisitCount' => (int) ($groundTruthSummary['visitCount'] ?? 0),
                'latestGroundTruthVisitAt' => $this->normalizeTimestamp($groundTruthSummary['latestVisitedAt'] ?? null),
                'latestFieldAudit' => is_array($groundTruthSummary['latestFieldAudit'] ?? null) ? $groundTruthSummary['latestFieldAudit'] : [],
                'latestFieldAuditMultiplier' => isset($groundTruthSummary['latestAuditMultiplier']) ? (float) $groundTruthSummary['latestAuditMultiplier'] : null,
                'investmentReadiness' => $readinessMeta,
                'trustBadges' => $this->trustBadges(
                    $sellerIdentityStatus === 'verified',
                    $documentsReviewedAt,
                    $siteVerifiedAt,
                    $lastConfirmedAvailableAt,
                    $updatedAt
                ),
                'recentlyUpdated' => $this->isRecentTimestamp($lastConfirmedAvailableAt ?: $updatedAt),
                'documentRequestCount' => (int) ($summary['count'] ?? 0),
                'openDocumentRequestCount' => (int) ($summary['openCount'] ?? 0),
                'pendingDocumentRequestCount' => (int) ($summary['pendingCount'] ?? 0),
                'media' => $media,
                'createdAt' => $this->normalizeTimestamp($row['created_at'] ?? null),
                'updatedAt' => $updatedAt,
            ];
        }, $rows);
    }

    private function mediaMap(array $propertyIds): array
    {
        $placeholders = implode(',', array_fill(0, count($propertyIds), '?'));
        $statement = $this->pdo->prepare(
            "SELECT id, property_id, kind, source, alt_text, sort_order
             FROM property_media
             WHERE property_id IN ({$placeholders})
             ORDER BY property_id ASC, sort_order ASC, id ASC"
        );
        $statement->execute($propertyIds);

        $map = [];
        foreach ($statement->fetchAll() as $row) {
            $propertyId = (int) $row['property_id'];
            $map[$propertyId] ??= [];
            $map[$propertyId][] = [
                'id' => (int) $row['id'],
                'kind' => (string) $row['kind'],
                'source' => (string) $row['source'],
                'url' => (string) $row['source'],
                'altText' => (string) ($row['alt_text'] ?? ''),
                'sortOrder' => (int) $row['sort_order'],
            ];
        }

        return $map;
    }

    private function groundTruthSummaryMap(array $propertyIds): array
    {
        if ($propertyIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($propertyIds), '?'));
        $summaryStatement = $this->pdo->prepare(
            "SELECT
                property_id,
                COUNT(*) AS visit_count,
                AVG(COALESCE(ground_truth_multiplier, 1)) AS avg_multiplier,
                MAX(COALESCE(visited_at, updated_at)) AS latest_visited_at
             FROM visit_logs
             WHERE property_id IN ({$placeholders})
               AND status = 'visited'
             GROUP BY property_id"
        );
        $summaryStatement->execute($propertyIds);

        $map = [];
        foreach ($summaryStatement->fetchAll() as $row) {
            $map[(int) $row['property_id']] = [
                'multiplier' => isset($row['avg_multiplier']) ? (float) $row['avg_multiplier'] : 1.0,
                'visitCount' => (int) ($row['visit_count'] ?? 0),
                'latestVisitedAt' => $row['latest_visited_at'] ?? null,
                'latestFieldAudit' => [],
                'latestAuditMultiplier' => null,
            ];
        }

        $auditStatement = $this->pdo->prepare(
            "SELECT property_id, field_audit_json, ground_truth_multiplier, visited_at, updated_at
             FROM visit_logs
             WHERE property_id IN ({$placeholders})
               AND field_audit_json IS NOT NULL
             ORDER BY property_id ASC, COALESCE(visited_at, updated_at) DESC, id DESC"
        );
        $auditStatement->execute($propertyIds);

        foreach ($auditStatement->fetchAll() as $row) {
            $propertyId = (int) $row['property_id'];
            if (isset($map[$propertyId]['latestFieldAudit']) && $map[$propertyId]['latestFieldAudit'] !== []) {
                continue;
            }

            $map[$propertyId] ??= [
                'multiplier' => 1.0,
                'visitCount' => 0,
                'latestVisitedAt' => $row['visited_at'] ?? $row['updated_at'] ?? null,
                'latestFieldAudit' => [],
                'latestAuditMultiplier' => null,
            ];
            $map[$propertyId]['latestFieldAudit'] = $this->decodeJson($row['field_audit_json'] ?? '{}');
            $map[$propertyId]['latestAuditMultiplier'] = $row['ground_truth_multiplier'] !== null
                ? (float) $row['ground_truth_multiplier']
                : null;
        }

        return $map;
    }

    private function documentRequestSummaryMap(array $propertyIds): array
    {
        if ($propertyIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($propertyIds), '?'));
        $statement = $this->pdo->prepare(
            "SELECT
                property_id,
                COUNT(*) AS total_count,
                SUM(CASE WHEN status IN ('requested', 'in_review') THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN status = 'requested' THEN 1 ELSE 0 END) AS pending_count
             FROM property_document_requests
             WHERE property_id IN ({$placeholders})
             GROUP BY property_id"
        );
        $statement->execute($propertyIds);

        $map = [];
        foreach ($statement->fetchAll() as $row) {
            $map[(int) $row['property_id']] = [
                'count' => (int) ($row['total_count'] ?? 0),
                'openCount' => (int) ($row['open_count'] ?? 0),
                'pendingCount' => (int) ($row['pending_count'] ?? 0),
            ];
        }

        return $map;
    }

    private function dueDiligenceSummaryMap(array $propertyIds): array
    {
        if ($propertyIds === []) {
            return [];
        }

        $items = $this->dueDiligenceItems();
        $placeholders = implode(',', array_fill(0, count($propertyIds), '?'));
        $statement = $this->pdo->prepare(
            "SELECT property_id, state_json
             FROM property_due_diligence
             WHERE property_id IN ({$placeholders})"
        );
        $statement->execute($propertyIds);

        $map = [];
        foreach ($statement->fetchAll() as $row) {
            $state = $this->decodeJson($row['state_json'] ?? '{}');
            $map[(int) $row['property_id']] = [
                'state' => $state,
                'pct' => $this->dueDiligencePct($state, $items),
            ];
        }

        return $map;
    }

    private function priceBenchmarkMap(): array
    {
        $statement = $this->pdo->query(
            'SELECT type, price, area, price_per_sqm
             FROM properties
             WHERE price > 0
               AND area > 0'
        );

        $groups = [];
        foreach ($statement->fetchAll() as $row) {
            $type = strtolower(trim((string) ($row['type'] ?? '')));
            $pricePerSqm = $this->effectivePricePerSqm($row);
            if ($pricePerSqm < 1) {
                continue;
            }

            $groups[$type] ??= [];
            $groups[$type][] = $pricePerSqm;
            $groups['*'] ??= [];
            $groups['*'][] = $pricePerSqm;
        }

        $benchmarks = [];
        foreach ($groups as $type => $prices) {
            sort($prices);
            $benchmarks[$type] = [
                'min' => min($prices),
                'max' => max($prices),
                'median' => $prices[(int) floor((count($prices) - 1) / 2)],
            ];
        }

        return $benchmarks;
    }

    private function normalizePropertyPayload(array $payload, ?array $existing = null): array
    {
        $name = string_or_null($payload['property_name'] ?? $payload['name'] ?? ($existing['name'] ?? null));
        if ($name === null) {
            throw new InvalidArgumentException('Property name is required.');
        }

        $city = string_or_null($payload['city'] ?? ($existing['city'] ?? null)) ?? self::DEFAULT_CITY;
        $barangay = string_or_null($payload['barangay'] ?? ($existing['barangay'] ?? null));
        $type = string_or_null($payload['property_type'] ?? $payload['type'] ?? ($existing['type'] ?? null));
        if ($type === null) {
            throw new InvalidArgumentException('Property type is required.');
        }

        $corridor = string_or_null($payload['corridor'] ?? ($existing['corridor'] ?? null)) ?? 'highway';
        $status = string_or_null($payload['status'] ?? ($existing['status'] ?? null)) ?? 'Available';
        $approvalState = $this->normalizeApprovalState((string) ($payload['approval_state'] ?? $payload['approvalState'] ?? ($existing['approval_state'] ?? 'approved')));
        $description = string_or_null($payload['description'] ?? ($existing['description'] ?? null));
        if ($description === null) {
            throw new InvalidArgumentException('Description is required.');
        }

        $price = int_or_null($payload['price'] ?? ($existing['price'] ?? null));
        if ($price === null || $price <= 0) {
            throw new InvalidArgumentException('Price must be greater than zero.');
        }

        $areaUnit = $this->normalizeLandAreaUnit($payload['land_area_unit'] ?? $payload['landAreaUnit'] ?? null);
        $area = $this->normalizeLandAreaValue(
            $payload['land_area'] ?? $payload['area'] ?? ($existing['area'] ?? null),
            $areaUnit
        );
        if ($area === null || $area <= 0) {
            throw new InvalidArgumentException('Land area must be greater than zero.');
        }

        $storedScore = int_or_null($payload['score'] ?? $payload['market_score'] ?? $payload['marketScore'] ?? ($existing['score'] ?? null));
        $storedScore = $this->clamp($storedScore ?? 82, 40, 100);

        $roadAccess = int_or_null($payload['road_access'] ?? $payload['roadAccess'] ?? ($existing['road_access'] ?? null));
        $roadAccess = $this->clamp($roadAccess ?? 85, 40, 100);

        $lat = float_or_null($payload['lat'] ?? ($existing['lat'] ?? null));
        $lng = float_or_null($payload['lng'] ?? ($existing['lng'] ?? null));
        if ($lat === null || $lng === null) {
            ['lat' => $lat, 'lng' => $lng] = $this->defaultCoordinates($corridor);
        }

        $imageUrl = string_or_null($payload['image_path'] ?? $payload['imageUrl'] ?? ($existing['image_url'] ?? null))
            ?? $this->defaultImagePath($type);

        $tags = $this->normalizeStringList(
            $payload['tags'] ?? $payload['tags_csv'] ?? $this->decodeExistingValue($existing['tags_json'] ?? null),
            $this->defaultTags($type, $corridor, $barangay)
        );
        $facilities = $this->normalizeStringList(
            $payload['facilities'] ?? $payload['facilities_csv'] ?? $this->decodeExistingValue($existing['facilities_json'] ?? null),
            $this->defaultFacilities($corridor, $type)
        );
        $ownerContact = $this->normalizeOwnerContact(
            $payload,
            $existing ? $this->decodeExistingValue($existing['owner_contact_json'] ?? null) : null,
            $name
        );
        $documents = $this->normalizeDocumentStatuses(
            $payload['document_statuses'] ?? $payload['documentStatuses'] ?? $this->decodeExistingValue($existing['documents_json'] ?? null)
        );
        $sellerUserId = int_or_null($payload['seller_user_id'] ?? $payload['sellerUserId'] ?? ($existing['seller_user_id'] ?? null));
        $documentsReviewedAt = $this->normalizeFlagTimestamp(
            $payload['documents_reviewed'] ?? $payload['documentsReviewed'] ?? null,
            $payload['documents_reviewed_at'] ?? $payload['documentsReviewedAt'] ?? null,
            $existing['documents_reviewed_at'] ?? null
        );
        $siteVerifiedAt = $this->normalizeFlagTimestamp(
            $payload['site_verified'] ?? $payload['siteVerified'] ?? null,
            $payload['site_verified_at'] ?? $payload['siteVerifiedAt'] ?? null,
            $existing['site_verified_at'] ?? null
        );
        $lastConfirmedAvailableAt = $this->normalizeTimestampInput(
            $payload['last_confirmed_available_at'] ?? $payload['lastConfirmedAvailableAt'] ?? ($existing['last_confirmed_available_at'] ?? gmdate('Y-m-d H:i:s')),
            $existing['last_confirmed_available_at'] ?? null
        );
        $distToRoadKm = $this->normalizeDistanceInput(
            $payload['dist_to_road_km'] ?? $payload['distToRoadKm'] ?? ($existing['dist_to_road_km'] ?? null)
        );
        $utilityStatus = $this->normalizeUtilityStatus(
            $payload['utility_status'] ?? $payload['utilityStatus'] ?? ($existing['utility_status'] ?? null)
        );
        $zoningScore = $this->normalizeNullableScore(
            $payload['zoning_score'] ?? $payload['zoningScore'] ?? ($existing['zoning_score'] ?? null)
        );
        $existingLandUse = string_or_null($payload['existing_land_use'] ?? $payload['existingLandUse'] ?? ($existing['existing_land_use'] ?? null));
        $zoningClassification = string_or_null($payload['zoning_classification'] ?? $payload['zoningClassification'] ?? ($existing['zoning_classification'] ?? null));
        $clupAllowedUses = $this->normalizeStringList(
            $payload['clup_allowed_uses'] ?? $payload['clupAllowedUses'] ?? $this->decodeExistingValue($existing['clup_allowed_uses_json'] ?? null),
            []
        );
        $clupConditionalUses = $this->normalizeStringList(
            $payload['clup_conditional_uses'] ?? $payload['clupConditionalUses'] ?? $this->decodeExistingValue($existing['clup_conditional_uses_json'] ?? null),
            []
        );
        $clupRestrictedUses = $this->normalizeStringList(
            $payload['clup_restricted_uses'] ?? $payload['clupRestrictedUses'] ?? $this->decodeExistingValue($existing['clup_restricted_uses_json'] ?? null),
            []
        );
        $clupSourceReference = string_or_null($payload['clup_source_reference'] ?? $payload['clupSourceReference'] ?? ($existing['clup_source_reference'] ?? null));
        $clupVerifiedAt = $this->normalizeFlagTimestamp(
            $payload['clup_verified'] ?? $payload['clupVerified'] ?? null,
            $payload['clup_verified_at'] ?? $payload['clupVerifiedAt'] ?? null,
            $existing['clup_verified_at'] ?? null
        );
        $assessedValueSqm = $this->normalizeNullableInt(
            $payload['assessed_value_sqm'] ?? $payload['assessedValueSqm'] ?? ($existing['assessed_value_sqm'] ?? null)
        );
        $readinessNotes = string_or_null($payload['readiness_notes'] ?? $payload['readinessNotes'] ?? ($existing['readiness_notes'] ?? null));

        return [
            'name' => $name,
            'city' => $city,
            'lat' => $lat,
            'lng' => $lng,
            'area' => round($area, 4),
            'price' => $price,
            'price_per_sqm' => $this->pricePerSqm($price, $area),
            'status' => $status,
            'approval_state' => $approvalState,
            'score' => $storedScore,
            'type' => $type,
            'corridor' => $corridor,
            'tags_json' => json_encode($tags, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'facilities_json' => json_encode($facilities, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'road_access' => $roadAccess,
            'image_url' => $imageUrl,
            'description' => $description,
            'barangay' => $barangay,
            'owner_contact_json' => json_encode($ownerContact, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'documents_json' => json_encode($documents, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'seller_user_id' => $sellerUserId,
            'documents_reviewed_at' => $documentsReviewedAt,
            'site_verified_at' => $siteVerifiedAt,
            'last_confirmed_available_at' => $lastConfirmedAvailableAt,
            'dist_to_road_km' => $distToRoadKm,
            'utility_status' => $utilityStatus,
            'zoning_score' => $zoningScore,
            'existing_land_use' => $existingLandUse,
            'zoning_classification' => $zoningClassification,
            'clup_allowed_uses_json' => json_encode($clupAllowedUses, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'clup_conditional_uses_json' => json_encode($clupConditionalUses, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'clup_restricted_uses_json' => json_encode($clupRestrictedUses, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'clup_source_reference' => $clupSourceReference,
            'clup_verified_at' => $clupVerifiedAt,
            'assessed_value_sqm' => $assessedValueSqm,
            'readiness_notes' => $readinessNotes,
        ];
    }

    private function decodeExistingValue(mixed $value): mixed
    {
        if (!is_string($value) || trim($value) === '') {
            return $value;
        }

        $decoded = json_decode($value, true);
        return $decoded !== null ? $decoded : $value;
    }

    private function normalizeLandAreaUnit(mixed $value): string
    {
        $normalized = strtolower(trim((string) ($value ?? 'ha')));
        return in_array($normalized, ['sqm', 'm2', 'square_meter', 'square_meters'], true)
            ? 'sqm'
            : 'ha';
    }

    private function normalizeLandAreaValue(mixed $value, string $unit = 'ha'): ?float
    {
        $area = float_or_null($value);
        if ($area === null) {
            return null;
        }

        if ($unit === 'sqm') {
            return $area / 10000;
        }

        return $area;
    }

    private function normalizeStringList(mixed $value, array $fallback): array
    {
        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return $fallback;
            }

            $decoded = json_decode($trimmed, true);
            $items = is_array($decoded)
                ? $decoded
                : (preg_split('/\s*,\s*/', $trimmed) ?: []);
        } else {
            return $fallback;
        }

        $clean = array_values(array_unique(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $items
        ))));

        return $clean !== [] ? $clean : $fallback;
    }

    private function normalizeDocumentStatuses(mixed $value): array
    {
        $defaults = [];
        foreach (self::DOCUMENT_REQUIREMENTS as $document) {
            $defaults[$document['key']] = 'missing';
        }

        $items = [];
        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $items = $decoded;
            }
        }
        foreach ($items as $key => $status) {
            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '' || !array_key_exists($normalizedKey, $defaults)) {
                continue;
            }

            $normalizedStatus = strtolower(trim((string) $status));
            $defaults[$normalizedKey] = array_key_exists($normalizedStatus, self::DOCUMENT_PROGRESS)
                ? $normalizedStatus
                : 'missing';
        }

        return $defaults;
    }

    private function documentCompletenessPct(array $documents): int
    {
        if ($documents === []) {
            return 0;
        }

        $total = 0;
        foreach ($documents as $status) {
            $total += self::DOCUMENT_PROGRESS[$status] ?? 0;
        }

        return (int) round($total / count($documents));
    }

    private function normalizeOwnerContact(array $payload, mixed $existing, string $propertyName): array
    {
        $existingContact = is_array($existing) ? $existing : [];
        $payloadContact = [];

        if (isset($payload['owner_contact']) && is_array($payload['owner_contact'])) {
            $payloadContact = $payload['owner_contact'];
        } elseif (isset($payload['ownerContact']) && is_array($payload['ownerContact'])) {
            $payloadContact = $payload['ownerContact'];
        }

        $name = string_or_null($payloadContact['name'] ?? $payload['owner_name'] ?? ($existingContact['name'] ?? null))
            ?? sprintf('%s Desk', $propertyName);
        $email = string_or_null($payloadContact['email'] ?? $payload['owner_email'] ?? ($existingContact['email'] ?? null))
            ?? 'portfolio@sfcelerate.local';
        $phone = string_or_null($payloadContact['phone'] ?? $payload['owner_phone'] ?? ($existingContact['phone'] ?? null))
            ?? '+63 917 555 0199';
        $responseSla = string_or_null($payloadContact['responseSla'] ?? $payload['owner_response_sla'] ?? ($existingContact['responseSla'] ?? null))
            ?? '24 HOURS';

        return [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'responseSla' => $responseSla,
        ];
    }

    private function defaultTags(string $type, string $corridor, ?string $barangay): array
    {
        $tags = match ($type) {
            'logistics' => ['Logistics Fit', 'Truck Access', 'Industrial Scale'],
            'hotel' => ['Tourism Ready', 'Destination Potential', 'Hospitality Fit'],
            'bpo' => ['Office Ready', 'Talent Access', 'Fiber Ready'],
            'manufacturing' => ['Industrial Fit', 'Utility Ready', 'Expansion Capacity'],
            default => ['Commercial Ready', 'Investor Grade', 'Strategic Location'],
        };

        $tags[] = match ($corridor) {
            'coastal' => 'Coastal Exposure',
            'downtown' => 'City Core Access',
            default => 'Highway Visibility',
        };

        if ($barangay !== null) {
            $tags[] = $barangay;
        }

        return array_values(array_unique($tags));
    }

    private function defaultFacilities(string $corridor, string $type): array
    {
        $facilities = match ($corridor) {
            'coastal' => ['Beach Access', 'Tourism Strip', 'Airport 15km'],
            'downtown' => ['CBD Access', 'Banks', 'Food Cluster'],
            default => ['Highway Access', 'Utilities', 'Distribution Routes'],
        };

        if ($type === 'bpo') {
            $facilities[] = 'Fiber Backbone';
        }
        if ($type === 'hotel') {
            $facilities[] = 'Hospitality Demand';
        }

        return array_values(array_unique($facilities));
    }

    private function defaultImagePath(string $type): string
    {
        return match ($type) {
            'hotel' => 'assets/images/LaFinns.png',
            'logistics', 'manufacturing' => 'assets/images/Property1.png',
            'bpo' => 'assets/images/Property3.png',
            default => 'assets/images/Property10.png',
        };
    }

    private function defaultCoordinates(string $corridor): array
    {
        return match ($corridor) {
            'coastal' => ['lat' => 16.6178, 'lng' => 120.3174],
            'downtown' => ['lat' => 16.6208, 'lng' => 120.3218],
            default => ['lat' => 16.6217, 'lng' => 120.3232],
        };
    }

    private function normalizeApprovalState(string $state): string
    {
        $normalized = strtolower(trim($state));
        if (!in_array($normalized, self::APPROVAL_STATES, true)) {
            return 'approved';
        }

        return $normalized;
    }

    private function normalizeIdentityVerificationStatus(string $state): string
    {
        $normalized = strtolower(trim($state));
        return in_array($normalized, ['unverified', 'pending', 'verified'], true)
            ? $normalized
            : 'unverified';
    }

    private function listingVerificationStatus(
        string $approvalState,
        bool $sellerVerified,
        string $documentsReviewedAt,
        string $siteVerifiedAt,
        int $documentCompletenessPct
    ): string {
        if (in_array($approvalState, ['draft', 'pending_review', 'rejected', 'archived'], true)) {
            return $approvalState;
        }

        if ($sellerVerified && $documentsReviewedAt !== '' && $siteVerifiedAt !== '') {
            return 'verified';
        }

        if ($sellerVerified || $documentsReviewedAt !== '' || $siteVerifiedAt !== '' || $documentCompletenessPct >= 50) {
            return 'partially_verified';
        }

        return 'unverified';
    }

    private function trustBadges(
        bool $sellerVerified,
        string $documentsReviewedAt,
        string $siteVerifiedAt,
        string $lastConfirmedAvailableAt,
        string $updatedAt
    ): array {
        $badges = [];
        if ($sellerVerified) {
            $badges[] = ['key' => 'verified_seller', 'label' => 'Verified Seller'];
        }
        if ($documentsReviewedAt !== '') {
            $badges[] = ['key' => 'documents_reviewed', 'label' => 'Documents Reviewed'];
        }
        if ($siteVerifiedAt !== '') {
            $badges[] = ['key' => 'site_verified', 'label' => 'Site Verified'];
        }
        if ($this->isRecentTimestamp($lastConfirmedAvailableAt ?: $updatedAt)) {
            $badges[] = ['key' => 'recently_updated', 'label' => 'Recently Updated'];
        }

        return $badges;
    }

    private function isRecentTimestamp(string $value, int $days = 14): bool
    {
        if ($value === '') {
            return false;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return false;
        }

        return $timestamp >= strtotime(sprintf('-%d days', $days));
    }

    private function normalizeFlagTimestamp(mixed $flag, mixed $value, mixed $existing): ?string
    {
        if ($flag !== null && $flag !== '') {
            $flagText = strtolower(trim((string) $flag));
            if (in_array($flagText, ['0', 'false', 'no'], true)) {
                return null;
            }
            if (in_array($flagText, ['1', 'true', 'yes'], true)) {
                return $this->normalizeTimestampInput($value, $existing) ?? gmdate('Y-m-d H:i:s');
            }
        }

        return $this->normalizeTimestampInput($value, $existing);
    }

    private function normalizeTimestampInput(mixed $value, mixed $existing = null): ?string
    {
        if ($value === null || $value === '') {
            $value = $existing;
        }

        $normalized = trim((string) ($value ?? ''));
        if ($normalized === '') {
            return null;
        }

        $timestamp = strtotime($normalized);
        if ($timestamp === false) {
            return null;
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private function normalizeDistanceInput(mixed $value): ?float
    {
        $distance = float_or_null($value);
        if ($distance === null || $distance < 0) {
            return null;
        }

        return round($distance, 2);
    }

    private function normalizeNullableScore(mixed $value): ?int
    {
        $score = int_or_null($value);
        if ($score === null) {
            return null;
        }

        return $this->clamp($score, 0, 100);
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        $number = int_or_null($value);
        if ($number === null || $number < 1) {
            return null;
        }

        return $number;
    }

    private function normalizeUtilityStatus(mixed $value): ?string
    {
        $normalized = strtolower(trim((string) ($value ?? '')));
        if ($normalized === '') {
            return null;
        }

        $normalized = str_replace(['-', ' '], '_', $normalized);
        return match ($normalized) {
            'full', 'ready', 'full_ready', 'full_fiber_/_power_/_water', 'full_fiber_power_water' => 'full_ready',
            'power_water', 'power_and_water' => 'power_water',
            'partial', 'partial_ready', 'partial_service' => 'partial',
            'limited', 'limited_service' => 'limited',
            'off_grid', 'offgrid' => 'off_grid',
            default => null,
        };
    }

    private function utilityStatusLabel(?string $status): string
    {
        return self::UTILITY_STATUS_LABELS[$status ?? ''] ?? 'Missing utility status';
    }

    private function dueDiligenceItems(): array
    {
        static $items = null;
        if ($items !== null) {
            return $items;
        }

        $meta = JsonData::meta();
        $rawItems = is_array($meta['dueDiligenceItems'] ?? null) ? $meta['dueDiligenceItems'] : [];
        $items = array_values(array_filter(array_map(static function (mixed $item): ?array {
            if (!is_array($item) || !isset($item['key'])) {
                return null;
            }

            return [
                'key' => (string) $item['key'],
                'label' => (string) ($item['label'] ?? self::humanizeIdentifier((string) $item['key'])),
            ];
        }, $rawItems)));

        return $items;
    }

    private function dueDiligencePct(array $state, array $items): int
    {
        if ($items === []) {
            return 0;
        }

        $complete = 0;
        foreach ($items as $item) {
            if (filter_var($state[$item['key']] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $complete++;
            }
        }

        return (int) round(($complete / count($items)) * 100);
    }

    private function investmentReadiness(array $context): array
    {
        $spatialIndicators = [
            $this->readinessIndicator(
                'dist_to_road',
                'Distance to Primary Road',
                $context['distToRoadKm'] !== null ? sprintf('%.2f km', (float) $context['distToRoadKm']) : 'Missing',
                $context['distToRoadKm'] !== null
                    ? $this->clamp((int) round(100 - min(72, (float) $context['distToRoadKm'] * 18)), 28, 100)
                    : null
            ),
            $this->readinessIndicator(
                'corridor_quality',
                'Corridor Quality',
                self::humanizeIdentifier((string) $context['corridor']),
                match ((string) $context['corridor']) {
                    'highway' => 88,
                    'downtown' => 84,
                    'coastal' => 78,
                    default => 70,
                }
            ),
            $this->readinessIndicator(
                'location_clarity',
                'Location Clarity',
                $context['barangay'] !== null && $context['barangay'] !== '' ? (string) $context['barangay'] : 'Barangay missing',
                $context['barangay'] !== null && $context['barangay'] !== '' ? 96 : 52
            ),
            $this->readinessIndicator(
                'map_confidence',
                'Map Confidence',
                ((float) $context['lat'] !== 0.0 || (float) $context['lng'] !== 0.0) ? 'Mapped coordinates available' : 'Mapped coordinates missing',
                ((float) $context['lat'] !== 0.0 || (float) $context['lng'] !== 0.0) ? 95 : 30
            ),
        ];

        $roadClass = $this->roadClassLabel((int) $context['roadAccess']);
        $utilityScore = $context['utilityStatus'] !== null ? (self::UTILITY_STATUS_SCORES[$context['utilityStatus']] ?? null) : null;
        $infrastructureIndicators = [
            $this->readinessIndicator(
                'road_access',
                'Road Access',
                sprintf('%d / 100', (int) $context['roadAccess']),
                (int) $context['roadAccess']
            ),
            $this->readinessIndicator(
                'road_class',
                'Road Class',
                $roadClass,
                $this->roadClassScore($roadClass)
            ),
            $this->readinessIndicator(
                'utility_status',
                'Utility Status',
                $this->utilityStatusLabel($context['utilityStatus']),
                $utilityScore
            ),
            $this->readinessIndicator(
                'service_coverage',
                'Service Coverage',
                $this->serviceCoverageLabel($context['facilities'] ?? []),
                $this->serviceCoverageScore($context['facilities'] ?? [], $context['utilityStatus'])
            ),
        ];

        $economicIndicators = [
            $this->readinessIndicator(
                'market_score',
                'Market Score',
                sprintf('%d / 100', (int) $context['marketScore']),
                (int) $context['marketScore']
            ),
            $this->readinessIndicator(
                'price_competitiveness',
                'Price Competitiveness',
                sprintf('PHP %s / sqm', number_format((int) $context['pricePerSqm'])),
                $this->priceCompetitivenessScore((int) $context['pricePerSqm'], is_array($context['priceBenchmark'] ?? null) ? $context['priceBenchmark'] : null)
            ),
            $this->readinessIndicator(
                'assessed_value_sqm',
                'Assessed Value / SQM',
                $context['assessedValueSqm'] !== null ? sprintf('PHP %s', number_format((int) $context['assessedValueSqm'])) : 'Missing assessed value',
                $context['assessedValueSqm'] !== null
                    ? $this->clamp((int) round(((int) $context['assessedValueSqm'] / max((int) $context['pricePerSqm'], 1)) * 100), 35, 100)
                    : null
            ),
            $this->readinessIndicator(
                'value_spread',
                'Value Spread',
                $context['assessedValueSqm'] !== null
                    ? sprintf('%s vs ask', ((int) $context['assessedValueSqm'] >= (int) $context['pricePerSqm']) ? 'At or above assessed' : 'Below assessed')
                    : 'Awaiting assessed benchmark',
                $context['assessedValueSqm'] !== null
                    ? $this->clamp((int) round(100 - (((int) $context['pricePerSqm'] - (int) $context['assessedValueSqm']) / max((int) $context['pricePerSqm'], 1)) * 100), 30, 100)
                    : null
            ),
        ];

        $institutionalIndicators = [
            $this->readinessIndicator(
                'zoning_score',
                'Zoning Score',
                $context['zoningScore'] !== null ? sprintf('%d / 100', (int) $context['zoningScore']) : 'Missing zoning score',
                $context['zoningScore']
            ),
            $this->readinessIndicator(
                'approval_state',
                'Approval State',
                self::humanizeIdentifier((string) $context['approvalState']),
                $this->approvalStateScore((string) $context['approvalState'])
            ),
            $this->readinessIndicator(
                'site_verified',
                'Site Verification',
                $context['siteVerifiedAt'] !== '' ? 'Site verified' : 'Site not verified',
                $context['siteVerifiedAt'] !== '' ? 100 : 34
            ),
            $this->readinessIndicator(
                'planning_fit',
                'Planning Fit',
                $this->planningFitLabel((string) $context['type'], (string) $context['corridor']),
                $this->planningFitScore((string) $context['type'], (string) $context['corridor'], $context['facilities'] ?? [])
            ),
        ];

        $legalIndicators = [
            $this->readinessIndicator(
                'dd_completion_pct',
                'Due Diligence Completion',
                sprintf('%d%% complete', (int) $context['dueDiligencePct']),
                (int) $context['dueDiligencePct']
            ),
            $this->readinessIndicator(
                'document_completeness_pct',
                'Document Completeness',
                sprintf('%d%% complete', (int) $context['documentCompletenessPct']),
                (int) $context['documentCompletenessPct']
            ),
            $this->readinessIndicator(
                'documents_reviewed',
                'Documents Reviewed',
                $context['documentsReviewedAt'] !== '' ? 'Reviewed by admin' : 'Pending review',
                $context['documentsReviewedAt'] !== '' ? 100 : 36
            ),
            $this->readinessIndicator(
                'legal_trust_state',
                'Legal Trust State',
                $this->legalTrustLabel((string) $context['listingVerificationStatus'], (string) $context['sellerIdentityStatus']),
                $this->legalTrustScore((string) $context['listingVerificationStatus'], (string) $context['sellerIdentityStatus'])
            ),
        ];

        $pillars = [
            'spatial' => $this->readinessPillar('spatial', 'Spatial', $spatialIndicators),
            'infrastructure' => $this->readinessPillar('infrastructure', 'Infrastructure', $infrastructureIndicators),
            'economic' => $this->readinessPillar('economic', 'Economic', $economicIndicators),
            'institutional' => $this->readinessPillar('institutional', 'Institutional', $institutionalIndicators),
            'legal' => $this->readinessPillar('legal', 'Legal', $legalIndicators),
        ];

        $weightedTotal = 0;
        $totalWeight = 0;
        $missingDataCount = 0;
        foreach ($pillars as $pillar) {
            $weightedTotal += (int) $pillar['score'] * (int) $pillar['weight'];
            $totalWeight += (int) $pillar['weight'];
            $missingDataCount += count($pillar['missingFields']);
        }

        $totalScore = $totalWeight > 0 ? (int) round($weightedTotal / $totalWeight) : 0;

        return [
            'totalScore' => $totalScore,
            'label' => $this->readinessLabel($totalScore),
            'status' => $this->readinessStatus($totalScore, $missingDataCount),
            'missingDataCount' => $missingDataCount,
            'lastComputedAt' => gmdate(DATE_ATOM),
            'pillars' => $pillars,
            'notes' => string_or_null($context['readinessNotes'] ?? null),
        ];
    }

    private function readinessPillar(string $key, string $label, array $indicators): array
    {
        $availableScores = [];
        $missingFields = [];
        foreach ($indicators as $indicator) {
            if ($indicator['missing']) {
                $missingFields[] = $indicator['label'];
                continue;
            }
            $availableScores[] = (int) $indicator['normalizedScore'];
        }

        $indicatorCount = max(count($indicators), 1);
        $availableCount = count($availableScores);
        $average = $availableCount > 0 ? (array_sum($availableScores) / $availableCount) : 0;
        $completenessRatio = $availableCount / $indicatorCount;
        $score = (int) round($average * $completenessRatio);

        return [
            'key' => $key,
            'label' => $label,
            'weight' => self::READINESS_PILLAR_WEIGHTS[$key] ?? 20,
            'score' => $score,
            'status' => $this->readinessStatus($score, count($missingFields)),
            'summary' => $this->pillarSummary($label, $score, count($missingFields)),
            'indicators' => $indicators,
            'missingFields' => $missingFields,
        ];
    }

    private function readinessIndicator(string $key, string $label, string $displayValue, ?int $normalizedScore): array
    {
        $score = $normalizedScore !== null ? $this->clamp($normalizedScore, 0, 100) : null;
        return [
            'key' => $key,
            'label' => $label,
            'displayValue' => $displayValue,
            'normalizedScore' => $score,
            'missing' => $score === null,
            'status' => $score === null ? 'missing' : $this->readinessStatus($score, 0),
        ];
    }

    private function readinessLabel(int $score): string
    {
        return match (true) {
            $score >= 80 => 'Highly Ready',
            $score >= 60 => 'Moderately Ready',
            default => 'Needs More Validation',
        };
    }

    private function readinessStatus(int $score, int $missingCount): string
    {
        if ($missingCount > 0 && $score < 70) {
            return 'incomplete';
        }

        return match (true) {
            $score >= 80 => 'strong',
            $score >= 60 => 'neutral',
            default => 'warning',
        };
    }

    private function pillarSummary(string $label, int $score, int $missingCount): string
    {
        if ($missingCount > 0) {
            return sprintf('%s has %d missing input%s.', $label, $missingCount, $missingCount === 1 ? '' : 's');
        }

        return match (true) {
            $score >= 80 => sprintf('%s is currently strong.', $label),
            $score >= 60 => sprintf('%s is usable but still uneven.', $label),
            default => sprintf('%s still needs validation.', $label),
        };
    }

    private function roadClassLabel(int $roadAccess): string
    {
        return match (true) {
            $roadAccess >= 90 => 'Primary',
            $roadAccess >= 75 => 'Secondary',
            default => 'Tertiary',
        };
    }

    private function roadClassScore(string $roadClass): int
    {
        return match (strtolower(trim($roadClass))) {
            'primary' => 100,
            'secondary' => 78,
            default => 58,
        };
    }

    private function serviceCoverageScore(array $facilities, ?string $utilityStatus): int
    {
        $keywords = ['utilities', 'fiber', 'power', 'water', 'backbone', 'transport', 'highway'];
        $hits = 0;
        foreach ($facilities as $facility) {
            $text = strtolower((string) $facility);
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    $hits++;
                    break;
                }
            }
        }

        $utilityScore = $utilityStatus !== null ? (self::UTILITY_STATUS_SCORES[$utilityStatus] ?? 52) : 52;
        return $this->clamp((int) round(min(100, 48 + ($hits * 10) + ($utilityScore * 0.32))), 20, 100);
    }

    private function serviceCoverageLabel(array $facilities): string
    {
        $filtered = array_values(array_filter(array_map(static fn (mixed $value): string => trim((string) $value), $facilities)));
        if ($filtered === []) {
            return 'Facility coverage not detailed';
        }

        return implode(' / ', array_slice($filtered, 0, 3));
    }

    private function priceCompetitivenessScore(int $pricePerSqm, ?array $benchmark): int
    {
        if ($benchmark === null) {
            return 70;
        }

        $min = (int) ($benchmark['min'] ?? $pricePerSqm);
        $max = (int) ($benchmark['max'] ?? $pricePerSqm);
        if ($max <= $min) {
            return 100;
        }

        return $this->clamp((int) round((($max - $pricePerSqm) / ($max - $min)) * 100), 0, 100);
    }

    private function approvalStateScore(string $approvalState): int
    {
        return match ($approvalState) {
            'approved' => 100,
            'pending_review' => 70,
            'draft' => 45,
            'rejected' => 18,
            'archived' => 10,
            default => 40,
        };
    }

    private function planningFitScore(string $type, string $corridor, array $facilities): int
    {
        $base = match ([$type, $corridor]) {
            ['logistics', 'highway'], ['manufacturing', 'highway'] => 92,
            ['hotel', 'coastal'] => 90,
            ['commercial', 'downtown'], ['commercial', 'highway'] => 86,
            ['bpo', 'downtown'], ['bpo', 'highway'] => 88,
            default => 72,
        };

        $facilityBoost = 0;
        foreach ($facilities as $facility) {
            $text = strtolower((string) $facility);
            if (str_contains($text, 'fiber') || str_contains($text, 'highway') || str_contains($text, 'utilities')) {
                $facilityBoost += 3;
            }
        }

        return $this->clamp($base + min($facilityBoost, 8), 0, 100);
    }

    private function planningFitLabel(string $type, string $corridor): string
    {
        return sprintf('%s aligned with %s corridor', self::humanizeIdentifier($type), self::humanizeIdentifier($corridor));
    }

    private function legalTrustScore(string $listingVerificationStatus, string $sellerIdentityStatus): int
    {
        $base = match ($listingVerificationStatus) {
            'verified' => 100,
            'partially_verified' => 72,
            'unverified' => 45,
            'pending_review' => 36,
            'draft' => 28,
            'rejected' => 15,
            'archived' => 10,
            default => 40,
        };

        if ($sellerIdentityStatus === 'verified') {
            $base += 6;
        } elseif ($sellerIdentityStatus === 'pending') {
            $base += 2;
        }

        return $this->clamp($base, 0, 100);
    }

    private function legalTrustLabel(string $listingVerificationStatus, string $sellerIdentityStatus): string
    {
        if ($listingVerificationStatus === 'verified') {
            return 'Verified listing';
        }

        if ($sellerIdentityStatus === 'verified') {
            return 'Seller verified, listing still completing checks';
        }

        return 'Trust state still building';
    }

    private function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }

    private function syncPrimaryMedia(int $propertyId, string $imageUrl, string $propertyName): void
    {
        $statement = $this->pdo->prepare(
            'SELECT id FROM property_media WHERE property_id = :property_id ORDER BY sort_order ASC, id ASC LIMIT 1'
        );
        $statement->execute(['property_id' => $propertyId]);
        $row = $statement->fetch();

        if ($row) {
            $update = $this->pdo->prepare(
                'UPDATE property_media
                 SET kind = :kind, source = :source, alt_text = :alt_text, sort_order = :sort_order
                 WHERE id = :id'
            );
            $update->execute([
                'id' => (int) $row['id'],
                'kind' => 'image',
                'source' => $imageUrl,
                'alt_text' => sprintf('%s listing image', $propertyName),
                'sort_order' => 0,
            ]);

            return;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO property_media (property_id, kind, source, alt_text, sort_order)
             VALUES (:property_id, :kind, :source, :alt_text, :sort_order)'
        );
        $insert->execute([
            'property_id' => $propertyId,
            'kind' => 'image',
            'source' => $imageUrl,
            'alt_text' => sprintf('%s listing image', $propertyName),
            'sort_order' => 0,
        ]);
    }

    private function ensureDueDiligenceRecord(int $propertyId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO property_due_diligence (property_id, state_json)
             VALUES (:property_id, :state_json)
             ON DUPLICATE KEY UPDATE property_id = property_id'
        );
        $statement->execute([
            'property_id' => $propertyId,
            'state_json' => '{}',
        ]);
    }

    private function recordPropertyAudit(string $baseActionType, int $propertyId, ?array $beforeRow, ?array $afterRow, ?array $actor): void
    {
        if ($this->auditLogs === null) {
            return;
        }

        $before = $beforeRow !== null ? $this->auditSnapshot($beforeRow) : null;
        $after = $afterRow !== null ? $this->auditSnapshot($afterRow) : null;

        if ($baseActionType === 'EDIT' && $before === $after) {
            return;
        }

        $beforeApproval = strtolower((string) ($before['approvalState'] ?? ''));
        $afterApproval = strtolower((string) ($after['approvalState'] ?? ''));
        $actionType = $baseActionType;
        $eventType = 'DATA_EDIT';

        if ($baseActionType === 'CREATE') {
            $eventType = 'LISTING_CREATE';
        } elseif ($baseActionType === 'DELETE') {
            $eventType = 'LISTING_DELETE';
        } elseif ($beforeApproval !== $afterApproval && $afterApproval !== '') {
            $actionType = 'APPROVE';
            $eventType = 'LISTING_APPROVAL';
        }

        $changedFields = $this->changedFields($before ?? [], $after ?? []);
        $streamGroup = $actionType === 'APPROVE'
            ? 'moderation'
            : ($this->isFinancialAudit($changedFields) ? 'financials' : 'all');

        $this->auditLogs->record(
            isset($actor['id']) ? (int) $actor['id'] : null,
            $actionType,
            'PROPERTY',
            $propertyId,
            [
                'actorName' => (string) ($actor['name'] ?? 'Platform User'),
                'actorRole' => (string) ($actor['role'] ?? 'system'),
                'eventType' => $eventType,
                'targetLabel' => $this->propertyTargetLabel($propertyId),
                'summary' => $this->propertyAuditSummary($actionType, $before ?? [], $after ?? [], $changedFields),
                'before' => $before,
                'after' => $after,
                'changedFields' => $changedFields,
                'streamGroup' => $streamGroup,
                'badge' => $actionType === 'DELETE' ? 'CRITICAL' : ($actionType === 'APPROVE' ? 'VERIFIED' : 'TRACE'),
            ]
        );
    }

    private function auditSnapshot(array $row): array
    {
        $pricePerSqm = $this->effectivePricePerSqm($row);

        return [
            'name' => (string) ($row['name'] ?? ''),
            'city' => (string) ($row['city'] ?? self::DEFAULT_CITY),
            'barangay' => string_or_null($row['barangay'] ?? null),
            'lat' => isset($row['lat']) ? (float) $row['lat'] : 0.0,
            'lng' => isset($row['lng']) ? (float) $row['lng'] : 0.0,
            'area' => isset($row['area']) ? (float) $row['area'] : 0.0,
            'price' => isset($row['price']) ? (int) $row['price'] : 0,
            'pricePerSqm' => $pricePerSqm,
            'status' => (string) ($row['status'] ?? ''),
            'approvalState' => (string) ($row['approval_state'] ?? ''),
            'marketScore' => isset($row['score']) ? (int) $row['score'] : 0,
            'type' => (string) ($row['type'] ?? ''),
            'corridor' => (string) ($row['corridor'] ?? ''),
            'tags' => $this->decodeJson($row['tags_json'] ?? '[]'),
            'facilities' => $this->decodeJson($row['facilities_json'] ?? '[]'),
            'roadAccess' => isset($row['road_access']) ? (int) $row['road_access'] : 0,
            'description' => (string) ($row['description'] ?? ''),
            'ownerContact' => $this->decodeJson($row['owner_contact_json'] ?? '{}'),
            'documents' => $this->normalizeDocumentStatuses($this->decodeJson($row['documents_json'] ?? '{}')),
            'sellerUserId' => isset($row['seller_user_id']) ? int_or_null($row['seller_user_id']) : null,
            'documentsReviewedAt' => $this->normalizeTimestamp($row['documents_reviewed_at'] ?? null),
            'siteVerifiedAt' => $this->normalizeTimestamp($row['site_verified_at'] ?? null),
            'lastConfirmedAvailableAt' => $this->normalizeTimestamp($row['last_confirmed_available_at'] ?? null),
            'distToRoadKm' => float_or_null($row['dist_to_road_km'] ?? null),
            'utilityStatus' => string_or_null($row['utility_status'] ?? null),
            'zoningScore' => int_or_null($row['zoning_score'] ?? null),
            'assessedValueSqm' => $this->effectiveAssessedValueSqm($row['assessed_value_sqm'] ?? null, $pricePerSqm),
            'readinessNotes' => string_or_null($row['readiness_notes'] ?? null),
        ];
    }

    private function changedFields(array $before, array $after): array
    {
        $keys = array_values(array_unique([...array_keys($before), ...array_keys($after)]));
        $changed = [];
        foreach ($keys as $key) {
            $left = $before[$key] ?? null;
            $right = $after[$key] ?? null;
            if (json_encode($left, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) === json_encode($right, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) {
                continue;
            }
            $changed[] = (string) $key;
        }

        return $changed;
    }

    private function isFinancialAudit(array $changedFields): bool
    {
        $financialFields = ['price', 'pricePerSqm', 'assessedValueSqm'];
        foreach ($changedFields as $field) {
            if (in_array((string) $field, $financialFields, true)) {
                return true;
            }
        }

        return false;
    }

    private function propertyAuditSummary(string $actionType, array $before, array $after, array $changedFields): string
    {
        if ($actionType === 'CREATE') {
            return sprintf('Created listing %s for ledger monitoring.', (string) ($after['name'] ?? 'Untitled Property'));
        }

        if ($actionType === 'DELETE') {
            return sprintf('Removed listing %s from the platform ledger.', (string) ($before['name'] ?? 'Untitled Property'));
        }

        if ($actionType === 'APPROVE') {
            return sprintf(
                'Approval state changed from %s to %s.',
                self::humanizeIdentifier((string) ($before['approvalState'] ?? 'draft')),
                self::humanizeIdentifier((string) ($after['approvalState'] ?? 'approved'))
            );
        }

        $priorityFields = ['pricePerSqm', 'price', 'roadAccess', 'zoningScore', 'status', 'utilityStatus'];
        foreach ($priorityFields as $field) {
            if (!in_array($field, $changedFields, true)) {
                continue;
            }

            return sprintf(
                '%s changed from %s to %s.',
                self::humanizeIdentifier($field),
                $this->auditValueLabel($before[$field] ?? null, $field),
                $this->auditValueLabel($after[$field] ?? null, $field)
            );
        }

        if ($changedFields !== []) {
            return sprintf(
                'Updated %s field%s on %s.',
                count($changedFields),
                count($changedFields) === 1 ? '' : 's',
                (string) ($after['name'] ?? $before['name'] ?? 'the listing')
            );
        }

        return sprintf('Reviewed %s with no material change.', (string) ($after['name'] ?? $before['name'] ?? 'the listing'));
    }

    private function auditValueLabel(mixed $value, string $field = ''): string
    {
        $normalizedField = strtolower(trim($field));

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
        }

        if ($value === null || $value === '') {
            return 'empty';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (in_array($normalizedField, ['price', 'pricepersqm', 'assessedvaluesqm'], true) && is_numeric($value)) {
            $suffix = $normalizedField === 'price' ? '' : ' / sqm';
            return sprintf('PHP %s%s', number_format((int) $value), $suffix);
        }

        if ($normalizedField === 'approvalstate' || str_ends_with($normalizedField, 'status')) {
            return self::humanizeIdentifier((string) $value);
        }

        return (string) $value;
    }

    private function effectivePricePerSqm(array $row): int
    {
        $price = isset($row['price']) ? (int) $row['price'] : 0;
        $area = isset($row['area']) ? (float) $row['area'] : 0.0;
        if ($price > 0 && $area > 0) {
            return $this->pricePerSqm($price, $area);
        }

        return max(1, (int) ($row['price_per_sqm'] ?? 1));
    }

    private function effectiveAssessedValueSqm(mixed $value, int $pricePerSqm): ?int
    {
        $assessedValueSqm = int_or_null($value);
        if ($assessedValueSqm === null) {
            return null;
        }

        if ($pricePerSqm > 0 && $assessedValueSqm > ($pricePerSqm * 3)) {
            return max(1, (int) round($pricePerSqm * 0.92));
        }

        return $assessedValueSqm;
    }

    private function pricePerSqm(int $price, float $area): int
    {
        return max(1, (int) round($price / max($area * 10000, 1)));
    }

    private function propertyTargetLabel(int $propertyId): string
    {
        return sprintf('PROP_ID: #SFLU-%03d', $propertyId);
    }

    private function decodeJson(?string $json): array
    {
        $decoded = json_decode((string) $json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeTimestamp(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return str_replace(' ', 'T', $value);
    }

    private static function humanizeIdentifier(string $value): string
    {
        $spaced = preg_replace('/([a-z])([A-Z])/', '$1 $2', $value) ?? $value;
        $normalized = trim(str_replace(['_', '-'], ' ', strtolower($spaced)));
        if ($normalized === '') {
            return '';
        }

        return preg_replace_callback('/\b([a-z])/', static fn (array $matches): string => strtoupper($matches[1]), $normalized) ?? $normalized;
    }
}
