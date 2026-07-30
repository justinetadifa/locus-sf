<?php
declare(strict_types=1);

namespace App\Repositories;

use InvalidArgumentException;
use OutOfBoundsException;
use PDO;
use Throwable;

final class ClupGovernanceRepository
{
    private const DATASET_STATUSES = ['draft', 'active', 'archived'];
    private const PROFILE_STATUSES = ['draft', 'submitted', 'verified', 'returned', 'superseded'];
    private const PERMISSION_STATUSES = ['allowed', 'conditional', 'restricted'];
    private const USE_CODES = ['commercial', 'logistics', 'hotel', 'bpo', 'manufacturing', 'mixed_use', 'hospital', 'university'];

    public function __construct(private PDO $pdo, private ?AuditLogRepository $auditLogs = null)
    {
    }

    public function catalog(): array
    {
        $datasets = $this->datasets();
        $activeDataset = null;
        foreach ($datasets as $dataset) {
            if ($dataset['status'] === 'active') {
                $activeDataset = $dataset;
                break;
            }
        }

        return [
            'useTypes' => $this->useTypes(),
            'datasets' => $datasets,
            'activeDataset' => $activeDataset,
            'zones' => $activeDataset ? $this->zones((int) $activeDataset['id'], false) : [],
            'rules' => $activeDataset ? $this->rules((int) $activeDataset['id']) : [],
            'reviewQueue' => $this->reviewQueue(),
        ];
    }

    public function useTypes(): array
    {
        $rows = $this->pdo->query(
            'SELECT code, label, category, description, is_active, created_at, updated_at
             FROM clup_use_types
             WHERE is_active = 1
             ORDER BY category ASC, label ASC'
        )->fetchAll();

        return array_map(static fn (array $row): array => [
            'code' => (string) $row['code'],
            'label' => (string) $row['label'],
            'category' => (string) $row['category'],
            'description' => (string) ($row['description'] ?? ''),
            'isActive' => (bool) $row['is_active'],
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'updatedAt' => (string) ($row['updated_at'] ?? ''),
        ], $rows);
    }

    public function datasets(): array
    {
        $rows = $this->pdo->query(
            'SELECT d.*,
                    creator.name AS creator_name,
                    activator.name AS activator_name,
                    (SELECT COUNT(*) FROM clup_zones z WHERE z.dataset_id = d.id) AS zone_count,
                    (SELECT COUNT(*) FROM clup_zone_use_rules r WHERE r.dataset_id = d.id) AS rule_count
             FROM clup_datasets d
             LEFT JOIN users creator ON creator.id = d.created_by_user_id
             LEFT JOIN users activator ON activator.id = d.activated_by_user_id
             ORDER BY FIELD(d.status, \'active\', \'draft\', \'archived\'), d.updated_at DESC, d.id DESC'
        )->fetchAll();

        return array_map([$this, 'hydrateDataset'], $rows);
    }

    public function activeDataset(): ?array
    {
        $statement = $this->pdo->query(
            "SELECT d.*,
                    creator.name AS creator_name,
                    activator.name AS activator_name,
                    (SELECT COUNT(*) FROM clup_zones z WHERE z.dataset_id = d.id) AS zone_count,
                    (SELECT COUNT(*) FROM clup_zone_use_rules r WHERE r.dataset_id = d.id) AS rule_count
             FROM clup_datasets d
             LEFT JOIN users creator ON creator.id = d.created_by_user_id
             LEFT JOIN users activator ON activator.id = d.activated_by_user_id
             WHERE d.status = 'active'
             ORDER BY d.activated_at DESC, d.id DESC
             LIMIT 1"
        );
        $row = $statement->fetch();
        return is_array($row) ? $this->hydrateDataset($row) : null;
    }

    public function createDataset(array $payload, array $actor): array
    {
        $name = $this->requiredText($payload['name'] ?? null, 'Dataset name', 180);
        $versionLabel = $this->requiredText($payload['versionLabel'] ?? $payload['version_label'] ?? null, 'Version label', 80);
        $sourceAgency = $this->requiredText($payload['sourceAgency'] ?? $payload['source_agency'] ?? null, 'Source agency', 180);
        $sourceReference = $this->requiredText($payload['sourceReference'] ?? $payload['source_reference'] ?? null, 'Source reference', 255);
        $sourceUrl = $this->optionalText($payload['sourceUrl'] ?? $payload['source_url'] ?? null, 500);
        if ($sourceUrl !== null && filter_var($sourceUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Source URL must be a valid URL.');
        }
        $effectiveDate = $this->normalizeDate($payload['effectiveDate'] ?? $payload['effective_date'] ?? null);
        $crs = $this->optionalText($payload['coordinateReferenceSystem'] ?? $payload['coordinate_reference_system'] ?? 'EPSG:4326', 80);
        $notes = $this->optionalText($payload['notes'] ?? null, 10000);

        $statement = $this->pdo->prepare(
            'INSERT INTO clup_datasets (
                name, version_label, status, source_agency, source_reference, source_url,
                effective_date, coordinate_reference_system, notes, created_by_user_id
             ) VALUES (
                :name, :version_label, \'draft\', :source_agency, :source_reference, :source_url,
                :effective_date, :coordinate_reference_system, :notes, :created_by_user_id
             )'
        );
        $statement->execute([
            'name' => $name,
            'version_label' => $versionLabel,
            'source_agency' => $sourceAgency,
            'source_reference' => $sourceReference,
            'source_url' => $sourceUrl,
            'effective_date' => $effectiveDate,
            'coordinate_reference_system' => $crs,
            'notes' => $notes,
            'created_by_user_id' => (int) ($actor['id'] ?? 0) ?: null,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $this->audit($actor, 'CREATE', 'CLUP_DATASET', $id, 'Created a draft CLUP dataset.', ['name' => $name, 'versionLabel' => $versionLabel]);
        return $this->dataset($id);
    }

    public function dataset(int $datasetId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT d.*,
                    creator.name AS creator_name,
                    activator.name AS activator_name,
                    (SELECT COUNT(*) FROM clup_zones z WHERE z.dataset_id = d.id) AS zone_count,
                    (SELECT COUNT(*) FROM clup_zone_use_rules r WHERE r.dataset_id = d.id) AS rule_count
             FROM clup_datasets d
             LEFT JOIN users creator ON creator.id = d.created_by_user_id
             LEFT JOIN users activator ON activator.id = d.activated_by_user_id
             WHERE d.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $datasetId]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            throw new OutOfBoundsException('CLUP dataset not found.');
        }
        return $this->hydrateDataset($row);
    }

    public function activateDataset(int $datasetId, array $actor): array
    {
        $dataset = $this->dataset($datasetId);
        if ((int) $dataset['zoneCount'] < 1) {
            throw new InvalidArgumentException('Import at least one valid zoning polygon before activation.');
        }
        if ((int) $dataset['ruleCount'] < 1) {
            throw new InvalidArgumentException('Add at least one ordinance-backed use rule before activation.');
        }
        if (!$dataset['sourceAgency'] || !$dataset['sourceReference']) {
            throw new InvalidArgumentException('Source agency and official reference are required before activation.');
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec("UPDATE clup_datasets SET status = 'archived' WHERE status = 'active'");
            $statement = $this->pdo->prepare(
                "UPDATE clup_datasets
                 SET status = 'active', activated_by_user_id = :actor_id, activated_at = CURRENT_TIMESTAMP
                 WHERE id = :id"
            );
            $statement->execute(['actor_id' => (int) ($actor['id'] ?? 0) ?: null, 'id' => $datasetId]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        $this->audit($actor, 'APPROVE', 'CLUP_DATASET', $datasetId, 'Activated a versioned CLUP zoning dataset.', ['previousStatus' => $dataset['status']]);
        return $this->dataset($datasetId);
    }

    public function importGeoJson(int $datasetId, array $geoJson, array $mapping, array $actor): array
    {
        $dataset = $this->dataset($datasetId);
        if ($dataset['status'] !== 'draft') {
            throw new InvalidArgumentException('Only draft datasets can be imported or replaced.');
        }
        if (($geoJson['type'] ?? null) !== 'FeatureCollection' || !is_array($geoJson['features'] ?? null)) {
            throw new InvalidArgumentException('Upload a valid GeoJSON FeatureCollection.');
        }
        $features = $geoJson['features'];
        if ($features === [] || count($features) > 5000) {
            throw new InvalidArgumentException('GeoJSON must contain between 1 and 5,000 zoning features.');
        }
        $codeField = $this->optionalText($mapping['codeField'] ?? null, 80) ?: 'zone_code';
        $nameField = $this->optionalText($mapping['nameField'] ?? null, 80) ?: 'name';
        $descriptionField = $this->optionalText($mapping['descriptionField'] ?? null, 80) ?: 'description';
        $normalized = [];
        $seenCodes = [];

        foreach ($features as $index => $feature) {
            if (!is_array($feature)) {
                throw new InvalidArgumentException(sprintf('Feature %d is invalid.', $index + 1));
            }
            $geometry = is_array($feature['geometry'] ?? null) ? $feature['geometry'] : [];
            $geometryType = (string) ($geometry['type'] ?? '');
            if (!in_array($geometryType, ['Polygon', 'MultiPolygon'], true)) {
                throw new InvalidArgumentException(sprintf('Feature %d must be a Polygon or MultiPolygon.', $index + 1));
            }
            $coordinates = $geometry['coordinates'] ?? null;
            if (!is_array($coordinates) || $coordinates === []) {
                throw new InvalidArgumentException(sprintf('Feature %d has no coordinates.', $index + 1));
            }
            $this->validateCoordinates($coordinates);
            $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $code = $this->zoneCode($properties[$codeField] ?? $properties['ZONE_CODE'] ?? $properties['code'] ?? null, $index + 1);
            if (isset($seenCodes[$code])) {
                throw new InvalidArgumentException(sprintf('Duplicate zone code "%s" in GeoJSON.', $code));
            }
            $seenCodes[$code] = true;
            $name = $this->optionalText($properties[$nameField] ?? $properties['ZONE_NAME'] ?? null, 180) ?: $code;
            $description = $this->optionalText($properties[$descriptionField] ?? null, 10000);
            $normalized[] = [
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'geometryType' => $geometryType,
                'geometry' => $geometry,
                'bbox' => $this->bbox($coordinates),
                'properties' => $properties,
            ];
        }

        $checksum = hash('sha256', json_encode($geoJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->pdo->beginTransaction();
        try {
            $delete = $this->pdo->prepare('DELETE FROM clup_zones WHERE dataset_id = :dataset_id');
            $delete->execute(['dataset_id' => $datasetId]);
            $insert = $this->pdo->prepare(
                'INSERT INTO clup_zones (
                    dataset_id, zone_code, name, description, geometry_type, geometry_json, bbox_json, properties_json
                 ) VALUES (
                    :dataset_id, :zone_code, :name, :description, :geometry_type, :geometry_json, :bbox_json, :properties_json
                 )'
            );
            foreach ($normalized as $zone) {
                $insert->execute([
                    'dataset_id' => $datasetId,
                    'zone_code' => $zone['code'],
                    'name' => $zone['name'],
                    'description' => $zone['description'],
                    'geometry_type' => $zone['geometryType'],
                    'geometry_json' => json_encode($zone['geometry'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'bbox_json' => json_encode($zone['bbox'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'properties_json' => json_encode($zone['properties'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]);
            }
            $update = $this->pdo->prepare('UPDATE clup_datasets SET checksum_sha256 = :checksum WHERE id = :id');
            $update->execute(['checksum' => $checksum, 'id' => $datasetId]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        $this->audit($actor, 'IMPORT', 'CLUP_DATASET', $datasetId, 'Imported and validated CLUP zoning polygons.', ['featureCount' => count($normalized), 'checksumSha256' => $checksum]);
        return ['dataset' => $this->dataset($datasetId), 'zones' => $this->zones($datasetId, false)];
    }

    public function zones(int $datasetId, bool $includeGeometry = true): array
    {
        $columns = $includeGeometry
            ? 'id, dataset_id, zone_code, name, description, geometry_type, geometry_json, bbox_json, properties_json, created_at, updated_at'
            : 'id, dataset_id, zone_code, name, description, geometry_type, NULL AS geometry_json, bbox_json, properties_json, created_at, updated_at';
        $statement = $this->pdo->prepare("SELECT {$columns} FROM clup_zones WHERE dataset_id = :dataset_id ORDER BY zone_code ASC");
        $statement->execute(['dataset_id' => $datasetId]);
        return array_map([$this, 'hydrateZone'], $statement->fetchAll());
    }

    public function rules(int $datasetId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT r.*, z.zone_code, z.name AS zone_name, u.label AS use_label
             FROM clup_zone_use_rules r
             INNER JOIN clup_zones z ON z.id = r.zone_id
             INNER JOIN clup_use_types u ON u.code = r.use_code
             WHERE r.dataset_id = :dataset_id
             ORDER BY z.zone_code ASC, u.label ASC'
        );
        $statement->execute(['dataset_id' => $datasetId]);
        return array_map([$this, 'hydrateRule'], $statement->fetchAll());
    }

    public function saveRule(array $payload, array $actor): array
    {
        $datasetId = (int) ($payload['datasetId'] ?? 0);
        $zoneId = (int) ($payload['zoneId'] ?? 0);
        $useCode = $this->useCode($payload['useCode'] ?? null);
        $permission = strtolower((string) ($payload['permissionStatus'] ?? ''));
        if (!in_array($permission, self::PERMISSION_STATUSES, true)) {
            throw new InvalidArgumentException('Permission status must be allowed, conditional, or restricted.');
        }
        $dataset = $this->dataset($datasetId);
        if ($dataset['status'] !== 'draft') {
            throw new InvalidArgumentException('Rules can only be changed in a draft dataset.');
        }
        $zoneStatement = $this->pdo->prepare('SELECT id FROM clup_zones WHERE id = :id AND dataset_id = :dataset_id');
        $zoneStatement->execute(['id' => $zoneId, 'dataset_id' => $datasetId]);
        if (!$zoneStatement->fetchColumn()) {
            throw new InvalidArgumentException('Selected zone does not belong to this dataset.');
        }
        $conditions = $this->normalizeConditions($payload['conditions'] ?? []);
        if ($permission === 'conditional' && $conditions === []) {
            throw new InvalidArgumentException('Conditional uses require at least one concrete condition.');
        }
        $ordinanceReference = $this->requiredText($payload['ordinanceReference'] ?? null, 'Ordinance reference', 255);
        $effectiveDate = $this->normalizeDate($payload['effectiveDate'] ?? null);
        $expiresAt = $this->normalizeDate($payload['expiresAt'] ?? null);

        $statement = $this->pdo->prepare(
            'INSERT INTO clup_zone_use_rules (
                dataset_id, zone_id, use_code, permission_status, conditions_json,
                ordinance_reference, effective_date, expires_at, created_by_user_id
             ) VALUES (
                :dataset_id, :zone_id, :use_code, :permission_status, :conditions_json,
                :ordinance_reference, :effective_date, :expires_at, :created_by_user_id
             )
             ON DUPLICATE KEY UPDATE
                permission_status = VALUES(permission_status), conditions_json = VALUES(conditions_json),
                ordinance_reference = VALUES(ordinance_reference), effective_date = VALUES(effective_date),
                expires_at = VALUES(expires_at), created_by_user_id = VALUES(created_by_user_id)'
        );
        $statement->execute([
            'dataset_id' => $datasetId,
            'zone_id' => $zoneId,
            'use_code' => $useCode,
            'permission_status' => $permission,
            'conditions_json' => json_encode($conditions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'ordinance_reference' => $ordinanceReference,
            'effective_date' => $effectiveDate,
            'expires_at' => $expiresAt,
            'created_by_user_id' => (int) ($actor['id'] ?? 0) ?: null,
        ]);
        $ruleId = (int) ($this->pdo->lastInsertId() ?: 0);
        $this->audit($actor, 'EDIT', 'CLUP_RULE', $ruleId ?: $zoneId, 'Saved an ordinance-backed zoning use rule.', ['datasetId' => $datasetId, 'zoneId' => $zoneId, 'useCode' => $useCode, 'permissionStatus' => $permission]);
        return ['rules' => $this->rules($datasetId)];
    }

    public function activeRule(int $zoneId, string $useCode): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT r.*, z.zone_code, z.name AS zone_name, u.label AS use_label
             FROM clup_zone_use_rules r
             INNER JOIN clup_zones z ON z.id = r.zone_id
             INNER JOIN clup_datasets d ON d.id = r.dataset_id AND d.status = 'active'
             INNER JOIN clup_use_types u ON u.code = r.use_code
             WHERE r.zone_id = :zone_id AND r.use_code = :use_code
               AND (r.effective_date IS NULL OR r.effective_date <= CURRENT_DATE)
               AND (r.expires_at IS NULL OR r.expires_at >= CURRENT_DATE)
             LIMIT 1"
        );
        $statement->execute(['zone_id' => $zoneId, 'use_code' => $this->useCode($useCode)]);
        $row = $statement->fetch();
        return is_array($row) ? $this->hydrateRule($row) : null;
    }

    public function resolveZones(float $lat, float $lng): array
    {
        $dataset = $this->activeDataset();
        if ($dataset === null) {
            return ['dataset' => null, 'matches' => [], 'conflict' => false];
        }
        $matches = [];
        foreach ($this->zones((int) $dataset['id'], true) as $zone) {
            $bbox = $zone['bbox'];
            if ($bbox && ($lng < $bbox['west'] || $lng > $bbox['east'] || $lat < $bbox['south'] || $lat > $bbox['north'])) {
                continue;
            }
            if ($this->geometryContainsPoint($zone['geometry'], $lng, $lat)) {
                $matches[] = $zone;
            }
        }
        return ['dataset' => $dataset, 'matches' => $matches, 'conflict' => count($matches) > 1];
    }

    public function currentProfile(int $propertyId): ?array
    {
        $statement = $this->pdo->prepare($this->profileSelect() . ' WHERE p.property_id = :property_id ORDER BY p.version_no DESC LIMIT 1');
        $statement->execute(['property_id' => $propertyId]);
        $row = $statement->fetch();
        return is_array($row) ? $this->hydrateProfile($row) : null;
    }

    public function verifiedProfile(int $propertyId): ?array
    {
        $statement = $this->pdo->prepare($this->profileSelect() . " WHERE p.property_id = :property_id AND p.review_status = 'verified' ORDER BY p.version_no DESC LIMIT 1");
        $statement->execute(['property_id' => $propertyId]);
        $row = $statement->fetch();
        return is_array($row) ? $this->hydrateProfile($row) : null;
    }

    public function profileHistory(int $propertyId): array
    {
        $statement = $this->pdo->prepare($this->profileSelect() . ' WHERE p.property_id = :property_id ORDER BY p.version_no DESC');
        $statement->execute(['property_id' => $propertyId]);
        return array_map([$this, 'hydrateProfile'], $statement->fetchAll());
    }

    public function saveProfileDraft(int $propertyId, array $payload, array $actor): array
    {
        $propertyStatement = $this->pdo->prepare('SELECT id, name, lat, lng FROM properties WHERE id = :id LIMIT 1');
        $propertyStatement->execute(['id' => $propertyId]);
        $property = $propertyStatement->fetch();
        if (!is_array($property)) {
            throw new OutOfBoundsException('Candidate site not found.');
        }
        $current = $this->currentProfile($propertyId);
        $actorId = (int) ($actor['id'] ?? 0);
        $canUpdate = $current !== null
            && in_array($current['reviewStatus'], ['draft', 'returned'], true)
            && (int) ($current['preparedByUserId'] ?? 0) === $actorId;
        $versionNo = $canUpdate ? (int) $current['versionNo'] : (int) ($current['versionNo'] ?? 0) + 1;
        $profileId = $canUpdate ? (int) $current['id'] : 0;

        $datasetId = int_or_null($payload['datasetId'] ?? null);
        $zoneId = int_or_null($payload['zoneId'] ?? null);
        if ($datasetId !== null) {
            $this->dataset($datasetId);
        }
        if ($zoneId !== null) {
            $zoneCheck = $this->pdo->prepare('SELECT id, dataset_id, zone_code, name FROM clup_zones WHERE id = :id LIMIT 1');
            $zoneCheck->execute(['id' => $zoneId]);
            $zone = $zoneCheck->fetch();
            if (!is_array($zone) || ($datasetId !== null && (int) $zone['dataset_id'] !== $datasetId)) {
                throw new InvalidArgumentException('Selected zoning polygon is not valid for the dataset.');
            }
            $datasetId = (int) $zone['dataset_id'];
            $payload['zoningClassification'] = $payload['zoningClassification'] ?? sprintf('%s — %s', $zone['zone_code'], $zone['name']);
        }
        $existingLandUse = $this->optionalText($payload['existingLandUse'] ?? null, 180);
        $zoningClassification = $this->optionalText($payload['zoningClassification'] ?? null, 180);
        $allowed = $this->normalizeUseList($payload['allowedUses'] ?? []);
        $conditional = $this->normalizeUseList($payload['conditionalUses'] ?? []);
        $restricted = $this->normalizeUseList($payload['restrictedUses'] ?? []);
        $this->assertDisjointUseLists($allowed, $conditional, $restricted);
        $sourceReference = $this->optionalText($payload['sourceReference'] ?? null, 255);
        $sourceDocumentPath = $this->optionalText($payload['sourceDocumentPath'] ?? null, 500);

        $params = [
            'property_id' => $propertyId,
            'version_no' => $versionNo,
            'dataset_id' => $datasetId,
            'zone_id' => $zoneId,
            'existing_land_use' => $existingLandUse,
            'zoning_classification' => $zoningClassification,
            'allowed_uses_json' => json_encode($allowed),
            'conditional_uses_json' => json_encode($conditional),
            'restricted_uses_json' => json_encode($restricted),
            'source_reference' => $sourceReference,
            'source_document_path' => $sourceDocumentPath,
            'prepared_by_user_id' => $actorId ?: null,
        ];
        if ($canUpdate) {
            $params['id'] = $profileId;
            $statement = $this->pdo->prepare(
                "UPDATE clup_site_profiles SET
                    dataset_id = :dataset_id, zone_id = :zone_id, existing_land_use = :existing_land_use,
                    zoning_classification = :zoning_classification, allowed_uses_json = :allowed_uses_json,
                    conditional_uses_json = :conditional_uses_json, restricted_uses_json = :restricted_uses_json,
                    source_reference = :source_reference, source_document_path = :source_document_path,
                    review_status = 'draft', review_notes = NULL, submitted_at = NULL,
                    reviewed_by_user_id = NULL, reviewed_at = NULL
                 WHERE id = :id"
            );
        } else {
            $statement = $this->pdo->prepare(
                "INSERT INTO clup_site_profiles (
                    property_id, version_no, dataset_id, zone_id, existing_land_use, zoning_classification,
                    allowed_uses_json, conditional_uses_json, restricted_uses_json, source_reference,
                    source_document_path, review_status, prepared_by_user_id
                 ) VALUES (
                    :property_id, :version_no, :dataset_id, :zone_id, :existing_land_use, :zoning_classification,
                    :allowed_uses_json, :conditional_uses_json, :restricted_uses_json, :source_reference,
                    :source_document_path, 'draft', :prepared_by_user_id
                 )"
            );
        }
        $statement->execute($params);
        $profileId = $canUpdate ? $profileId : (int) $this->pdo->lastInsertId();
        $this->recordReviewEvent($profileId, $canUpdate ? $current['reviewStatus'] : null, 'draft', 'draft_saved', null, $actorId ?: null);
        $this->audit($actor, 'EDIT', 'CLUP_PROFILE', $profileId, 'Saved a draft parcel CLUP evidence profile.', ['propertyId' => $propertyId, 'versionNo' => $versionNo]);
        return $this->profile($profileId);
    }

    public function submitProfile(int $profileId, array $actor): array
    {
        $profile = $this->profile($profileId);
        if (!in_array($profile['reviewStatus'], ['draft', 'returned'], true)) {
            throw new InvalidArgumentException('Only a draft or returned profile can be submitted.');
        }
        if ((int) ($profile['preparedByUserId'] ?? 0) !== (int) ($actor['id'] ?? 0)) {
            throw new InvalidArgumentException('Only the profile preparer can submit this version.');
        }
        $this->validateProfileForSubmission($profile);
        $statement = $this->pdo->prepare("UPDATE clup_site_profiles SET review_status = 'submitted', submitted_at = CURRENT_TIMESTAMP, review_notes = NULL WHERE id = :id");
        $statement->execute(['id' => $profileId]);
        $this->recordReviewEvent($profileId, $profile['reviewStatus'], 'submitted', 'submitted', null, (int) ($actor['id'] ?? 0) ?: null);
        $this->audit($actor, 'SUBMIT', 'CLUP_PROFILE', $profileId, 'Submitted parcel CLUP evidence for independent review.', ['propertyId' => $profile['propertyId'], 'versionNo' => $profile['versionNo']]);
        return $this->profile($profileId);
    }

    public function reviewProfile(int $profileId, string $decision, ?string $notes, array $actor): array
    {
        $profile = $this->profile($profileId);
        if ($profile['reviewStatus'] !== 'submitted') {
            throw new InvalidArgumentException('Only submitted profiles can be reviewed.');
        }
        $actorId = (int) ($actor['id'] ?? 0);
        if ($actorId < 1 || $actorId === (int) ($profile['preparedByUserId'] ?? 0)) {
            throw new InvalidArgumentException('A different authenticated admin must review this profile.');
        }
        $decision = strtolower(trim($decision));
        if (!in_array($decision, ['approve', 'return'], true)) {
            throw new InvalidArgumentException('Review decision must be approve or return.');
        }
        $notes = $this->optionalText($notes, 5000);
        if ($decision === 'return' && $notes === null) {
            throw new InvalidArgumentException('Return notes are required.');
        }
        if ($decision === 'approve') {
            $this->validateProfileForSubmission($profile);
            if ($this->evidenceDocuments($profileId) === []) {
                throw new InvalidArgumentException('At least one hashed supporting document is required before verification.');
            }
        }

        $this->pdo->beginTransaction();
        try {
            if ($decision === 'approve') {
                $supersede = $this->pdo->prepare("UPDATE clup_site_profiles SET review_status = 'superseded' WHERE property_id = :property_id AND review_status = 'verified' AND id <> :id");
                $supersede->execute(['property_id' => $profile['propertyId'], 'id' => $profileId]);
            }
            $status = $decision === 'approve' ? 'verified' : 'returned';
            $statement = $this->pdo->prepare(
                'UPDATE clup_site_profiles
                 SET review_status = :status, reviewed_by_user_id = :reviewer_id,
                     reviewed_at = CURRENT_TIMESTAMP, review_notes = :review_notes
                 WHERE id = :id'
            );
            $statement->execute(['status' => $status, 'reviewer_id' => $actorId, 'review_notes' => $notes, 'id' => $profileId]);
            if ($decision === 'approve') {
                $legacy = $this->pdo->prepare(
                    'UPDATE properties SET
                        existing_land_use = :existing_land_use,
                        zoning_classification = :zoning_classification,
                        clup_allowed_uses_json = :allowed_uses_json,
                        clup_conditional_uses_json = :conditional_uses_json,
                        clup_restricted_uses_json = :restricted_uses_json,
                        clup_source_reference = :source_reference,
                        clup_verified_at = CURRENT_TIMESTAMP
                     WHERE id = :property_id'
                );
                $legacy->execute([
                    'existing_land_use' => $profile['existingLandUse'],
                    'zoning_classification' => $profile['zoningClassification'],
                    'allowed_uses_json' => json_encode($profile['allowedUses']),
                    'conditional_uses_json' => json_encode($profile['conditionalUses']),
                    'restricted_uses_json' => json_encode($profile['restrictedUses']),
                    'source_reference' => $profile['sourceReference'],
                    'property_id' => $profile['propertyId'],
                ]);
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        $this->audit($actor, $decision === 'approve' ? 'APPROVE' : 'RETURN', 'CLUP_PROFILE', $profileId, $decision === 'approve' ? 'Independently verified parcel CLUP evidence.' : 'Returned parcel CLUP evidence for correction.', ['propertyId' => $profile['propertyId'], 'versionNo' => $profile['versionNo'], 'reviewNotes' => $notes]);
        $this->recordReviewEvent($profileId, 'submitted', $decision === 'approve' ? 'verified' : 'returned', $decision === 'approve' ? 'verified' : 'returned', $notes, $actorId);
        return $this->profile($profileId);
    }

    public function addEvidenceDocument(int $profileId, array $file, string $documentType, array $actor): array
    {
        $profile = $this->profile($profileId);
        if (!in_array($profile['reviewStatus'], ['draft', 'returned'], true)) {
            throw new InvalidArgumentException('Evidence can only be added to a draft or returned profile.');
        }
        if ((int) ($profile['preparedByUserId'] ?? 0) !== (int) ($actor['id'] ?? 0)) {
            throw new InvalidArgumentException('Only the profile preparer can add evidence to this version.');
        }
        $documentType = strtolower(trim($documentType));
        $allowedTypes = ['zoning_map_extract', 'ordinance_extract', 'locational_clearance', 'site_plan', 'barangay_certification', 'environmental_clearance', 'other'];
        if (!in_array($documentType, $allowedTypes, true)) {
            throw new InvalidArgumentException('Unsupported CLUP evidence document type.');
        }
        foreach (['originalName', 'storagePath', 'mimeType', 'fileSize', 'checksumSha256'] as $field) {
            if (!isset($file[$field]) || $file[$field] === '') {
                throw new InvalidArgumentException('Stored evidence metadata is incomplete.');
            }
        }
        $statement = $this->pdo->prepare(
            'INSERT INTO clup_evidence_documents (
                profile_id, document_type, original_name, storage_path, mime_type, file_size,
                checksum_sha256, uploaded_by_user_id
             ) VALUES (
                :profile_id, :document_type, :original_name, :storage_path, :mime_type, :file_size,
                :checksum_sha256, :uploaded_by_user_id
             )'
        );
        $statement->execute([
            'profile_id' => $profileId,
            'document_type' => $documentType,
            'original_name' => $file['originalName'],
            'storage_path' => $file['storagePath'],
            'mime_type' => $file['mimeType'],
            'file_size' => (int) $file['fileSize'],
            'checksum_sha256' => $file['checksumSha256'],
            'uploaded_by_user_id' => (int) ($actor['id'] ?? 0) ?: null,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $this->recordReviewEvent($profileId, $profile['reviewStatus'], $profile['reviewStatus'], 'evidence_added', $file['originalName'], (int) ($actor['id'] ?? 0) ?: null);
        $this->audit($actor, 'UPLOAD', 'CLUP_EVIDENCE', $id, 'Added hashed supporting evidence to a CLUP profile.', ['propertyId' => $profile['propertyId'], 'profileId' => $profileId, 'checksumSha256' => $file['checksumSha256']]);
        return ['document' => $this->evidenceDocument($id), 'documents' => $this->evidenceDocuments($profileId)];
    }

    public function evidenceDocuments(int $profileId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT d.*, u.name AS uploaded_by_name
             FROM clup_evidence_documents d
             LEFT JOIN users u ON u.id = d.uploaded_by_user_id
             WHERE d.profile_id = :profile_id ORDER BY d.id DESC'
        );
        $statement->execute(['profile_id' => $profileId]);
        return array_map([$this, 'hydrateEvidenceDocument'], $statement->fetchAll());
    }

    public function reviewEvents(int $profileId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT e.*, u.name AS actor_name
             FROM clup_review_events e
             LEFT JOIN users u ON u.id = e.actor_user_id
             WHERE e.profile_id = :profile_id ORDER BY e.id DESC'
        );
        $statement->execute(['profile_id' => $profileId]);
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'profileId' => (int) $row['profile_id'],
            'eventType' => (string) $row['event_type'],
            'fromStatus' => string_or_null($row['from_status'] ?? null),
            'toStatus' => (string) $row['to_status'],
            'notes' => string_or_null($row['notes'] ?? null),
            'actorUserId' => int_or_null($row['actor_user_id'] ?? null),
            'actorName' => string_or_null($row['actor_name'] ?? null) ?? 'System',
            'createdAt' => (string) $row['created_at'],
        ], $statement->fetchAll());
    }

    public function profileBundle(int $propertyId): array
    {
        $current = $this->currentProfile($propertyId);
        return [
            'current' => $current,
            'history' => $this->profileHistory($propertyId),
            'documents' => $current ? $this->evidenceDocuments((int) $current['id']) : [],
            'events' => $current ? $this->reviewEvents((int) $current['id']) : [],
        ];
    }

    public function profile(int $profileId): array
    {
        $statement = $this->pdo->prepare($this->profileSelect() . ' WHERE p.id = :id LIMIT 1');
        $statement->execute(['id' => $profileId]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            throw new OutOfBoundsException('CLUP profile not found.');
        }
        return $this->hydrateProfile($row);
    }

    public function reviewQueue(): array
    {
        $rows = $this->pdo->query($this->profileSelect() . " WHERE p.review_status = 'submitted' ORDER BY p.submitted_at ASC, p.id ASC")->fetchAll();
        return array_map([$this, 'hydrateProfile'], $rows);
    }

    public function recordEvaluation(array $property, array $result, ?array $actor = null): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO clup_evaluations (
                property_id, profile_id, dataset_id, proposed_use_code, compliance_status,
                suitability_score, evidence_level, engine_version, input_snapshot_json,
                result_snapshot_json, evaluated_by_user_id
             ) VALUES (
                :property_id, :profile_id, :dataset_id, :proposed_use_code, :compliance_status,
                :suitability_score, :evidence_level, :engine_version, :input_snapshot_json,
                :result_snapshot_json, :evaluated_by_user_id
             )'
        );
        $input = [
            'propertyId' => (int) ($property['id'] ?? 0),
            'candidateSite' => (string) ($property['name'] ?? ''),
            'lat' => $property['lat'] ?? null,
            'lng' => $property['lng'] ?? null,
            'proposedUseCode' => $result['proposedInvestmentType'] ?? null,
            'profileVersion' => $result['profileVersion'] ?? null,
            'datasetVersion' => $result['datasetVersion'] ?? null,
        ];
        $statement->execute([
            'property_id' => (int) $property['id'],
            'profile_id' => int_or_null($result['profileId'] ?? null),
            'dataset_id' => int_or_null($result['datasetId'] ?? null),
            'proposed_use_code' => $this->useCode($result['proposedInvestmentType'] ?? null),
            'compliance_status' => (string) ($result['status'] ?? 'UNVERIFIED'),
            'suitability_score' => int_or_null($result['suitabilityScore'] ?? null),
            'evidence_level' => (string) ($result['evidenceLevel'] ?? 'INFERRED'),
            'engine_version' => (string) ($result['version'] ?? 'unknown'),
            'input_snapshot_json' => json_encode($input, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'result_snapshot_json' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'evaluated_by_user_id' => (int) ($actor['id'] ?? 0) ?: null,
        ]);
        return ['id' => (int) $this->pdo->lastInsertId(), 'createdAt' => gmdate(DATE_ATOM), 'result' => $result];
    }

    public function createReport(string $type, string $title, array $snapshot, array $actor): array
    {
        $type = strtolower(trim($type));
        if (!in_array($type, ['priority_register', 'area_dossier', 'scenario_comparison', 'clup_compliance'], true)) {
            throw new InvalidArgumentException('Unsupported report type.');
        }
        $title = $this->requiredText($title, 'Report title', 255);
        $encoded = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $checksum = hash('sha256', $encoded);
        $number = sprintf('LOCUS-%s-%s-%04d', strtoupper(substr(str_replace('_', '', $type), 0, 5)), gmdate('Ymd'), random_int(1, 9999));
        $statement = $this->pdo->prepare(
            'INSERT INTO clup_reports (report_number, report_type, title, snapshot_json, checksum_sha256, generated_by_user_id)
             VALUES (:report_number, :report_type, :title, :snapshot_json, :checksum_sha256, :generated_by_user_id)'
        );
        $statement->execute([
            'report_number' => $number,
            'report_type' => $type,
            'title' => $title,
            'snapshot_json' => $encoded,
            'checksum_sha256' => $checksum,
            'generated_by_user_id' => (int) ($actor['id'] ?? 0) ?: null,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $this->audit($actor, 'GENERATE', 'CLUP_REPORT', $id, 'Generated an immutable CLUP decision report snapshot.', ['reportNumber' => $number, 'checksumSha256' => $checksum]);
        return ['id' => $id, 'reportNumber' => $number, 'reportType' => $type, 'title' => $title, 'checksumSha256' => $checksum, 'createdAt' => gmdate(DATE_ATOM), 'snapshot' => $snapshot];
    }

    public function reports(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $rows = $this->pdo->query(
            "SELECT r.*, generator.name AS generator_name, reviewer.name AS reviewer_name
             FROM clup_reports r
             LEFT JOIN users generator ON generator.id = r.generated_by_user_id
             LEFT JOIN users reviewer ON reviewer.id = r.reviewed_by_user_id
             ORDER BY r.id DESC LIMIT {$limit}"
        )->fetchAll();
        return array_map(function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'reportNumber' => (string) $row['report_number'],
                'reportType' => (string) $row['report_type'],
                'title' => (string) $row['title'],
                'status' => (string) $row['status'],
                'checksumSha256' => (string) $row['checksum_sha256'],
                'generatedByName' => (string) ($row['generator_name'] ?? 'System'),
                'reviewedByName' => string_or_null($row['reviewer_name'] ?? null),
                'reviewedAt' => string_or_null($row['reviewed_at'] ?? null),
                'createdAt' => (string) $row['created_at'],
                'snapshot' => $this->decodeJson($row['snapshot_json'] ?? '{}'),
            ];
        }, $rows);
    }

    private function profileSelect(): string
    {
        return 'SELECT p.*, property.name AS property_name, d.name AS dataset_name, d.version_label AS dataset_version,
                       d.status AS dataset_status, z.zone_code, z.name AS zone_name,
                       preparer.name AS preparer_name, reviewer.name AS reviewer_name
                FROM clup_site_profiles p
                INNER JOIN properties property ON property.id = p.property_id
                LEFT JOIN clup_datasets d ON d.id = p.dataset_id
                LEFT JOIN clup_zones z ON z.id = p.zone_id
                LEFT JOIN users preparer ON preparer.id = p.prepared_by_user_id
                LEFT JOIN users reviewer ON reviewer.id = p.reviewed_by_user_id';
    }

    private function hydrateDataset(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'versionLabel' => (string) $row['version_label'],
            'status' => in_array((string) $row['status'], self::DATASET_STATUSES, true) ? (string) $row['status'] : 'draft',
            'sourceAgency' => string_or_null($row['source_agency'] ?? null),
            'sourceReference' => string_or_null($row['source_reference'] ?? null),
            'sourceUrl' => string_or_null($row['source_url'] ?? null),
            'effectiveDate' => string_or_null($row['effective_date'] ?? null),
            'coordinateReferenceSystem' => string_or_null($row['coordinate_reference_system'] ?? null),
            'checksumSha256' => string_or_null($row['checksum_sha256'] ?? null),
            'notes' => string_or_null($row['notes'] ?? null),
            'zoneCount' => (int) ($row['zone_count'] ?? 0),
            'ruleCount' => (int) ($row['rule_count'] ?? 0),
            'createdByUserId' => int_or_null($row['created_by_user_id'] ?? null),
            'createdByName' => string_or_null($row['creator_name'] ?? null),
            'activatedByUserId' => int_or_null($row['activated_by_user_id'] ?? null),
            'activatedByName' => string_or_null($row['activator_name'] ?? null),
            'activatedAt' => string_or_null($row['activated_at'] ?? null),
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'updatedAt' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function hydrateZone(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'datasetId' => (int) $row['dataset_id'],
            'zoneCode' => (string) $row['zone_code'],
            'name' => (string) $row['name'],
            'description' => string_or_null($row['description'] ?? null),
            'geometryType' => (string) $row['geometry_type'],
            'geometry' => $this->decodeJson($row['geometry_json'] ?? null),
            'bbox' => $this->decodeJson($row['bbox_json'] ?? null),
            'properties' => $this->decodeJson($row['properties_json'] ?? null),
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'updatedAt' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function hydrateRule(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'datasetId' => (int) $row['dataset_id'],
            'zoneId' => (int) $row['zone_id'],
            'zoneCode' => (string) ($row['zone_code'] ?? ''),
            'zoneName' => (string) ($row['zone_name'] ?? ''),
            'useCode' => (string) $row['use_code'],
            'useLabel' => (string) ($row['use_label'] ?? $row['use_code']),
            'permissionStatus' => (string) $row['permission_status'],
            'conditions' => $this->decodeJson($row['conditions_json'] ?? null),
            'ordinanceReference' => string_or_null($row['ordinance_reference'] ?? null),
            'effectiveDate' => string_or_null($row['effective_date'] ?? null),
            'expiresAt' => string_or_null($row['expires_at'] ?? null),
            'createdByUserId' => int_or_null($row['created_by_user_id'] ?? null),
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'updatedAt' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function hydrateProfile(array $row): array
    {
        $reviewStatus = (string) ($row['review_status'] ?? 'draft');
        return [
            'id' => (int) $row['id'],
            'propertyId' => (int) $row['property_id'],
            'propertyName' => (string) ($row['property_name'] ?? ''),
            'versionNo' => (int) $row['version_no'],
            'datasetId' => int_or_null($row['dataset_id'] ?? null),
            'datasetName' => string_or_null($row['dataset_name'] ?? null),
            'datasetVersion' => string_or_null($row['dataset_version'] ?? null),
            'datasetStatus' => string_or_null($row['dataset_status'] ?? null),
            'zoneId' => int_or_null($row['zone_id'] ?? null),
            'zoneCode' => string_or_null($row['zone_code'] ?? null),
            'zoneName' => string_or_null($row['zone_name'] ?? null),
            'existingLandUse' => string_or_null($row['existing_land_use'] ?? null),
            'zoningClassification' => string_or_null($row['zoning_classification'] ?? null),
            'allowedUses' => $this->decodeJson($row['allowed_uses_json'] ?? '[]'),
            'conditionalUses' => $this->decodeJson($row['conditional_uses_json'] ?? '[]'),
            'restrictedUses' => $this->decodeJson($row['restricted_uses_json'] ?? '[]'),
            'sourceReference' => string_or_null($row['source_reference'] ?? null),
            'sourceDocumentPath' => string_or_null($row['source_document_path'] ?? null),
            'reviewStatus' => in_array($reviewStatus, self::PROFILE_STATUSES, true) ? $reviewStatus : 'draft',
            'preparedByUserId' => int_or_null($row['prepared_by_user_id'] ?? null),
            'preparedByName' => string_or_null($row['preparer_name'] ?? null),
            'submittedAt' => string_or_null($row['submitted_at'] ?? null),
            'reviewedByUserId' => int_or_null($row['reviewed_by_user_id'] ?? null),
            'reviewedByName' => string_or_null($row['reviewer_name'] ?? null),
            'reviewedAt' => string_or_null($row['reviewed_at'] ?? null),
            'reviewNotes' => string_or_null($row['review_notes'] ?? null),
            'isVerified' => $reviewStatus === 'verified',
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'updatedAt' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function validateProfileForSubmission(array $profile): void
    {
        if (!$profile['sourceReference']) {
            throw new InvalidArgumentException('An official source reference is required before submission.');
        }
        if (!$profile['zoningClassification'] && !$profile['zoneId']) {
            throw new InvalidArgumentException('A zoning classification or mapped zoning polygon is required.');
        }
        if ($profile['allowedUses'] === [] && $profile['conditionalUses'] === [] && $profile['restrictedUses'] === []) {
            throw new InvalidArgumentException('Record at least one allowed, conditional, or restricted use.');
        }
        if ($profile['datasetId'] !== null && $profile['datasetStatus'] !== 'active') {
            throw new InvalidArgumentException('A mapped profile must reference the currently active CLUP dataset before verification.');
        }
    }

    private function evidenceDocument(int $documentId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT d.*, u.name AS uploaded_by_name
             FROM clup_evidence_documents d
             LEFT JOIN users u ON u.id = d.uploaded_by_user_id
             WHERE d.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $documentId]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            throw new OutOfBoundsException('CLUP evidence document not found.');
        }
        return $this->hydrateEvidenceDocument($row);
    }

    private function hydrateEvidenceDocument(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'profileId' => (int) $row['profile_id'],
            'documentType' => (string) $row['document_type'],
            'originalName' => (string) $row['original_name'],
            'storagePath' => (string) $row['storage_path'],
            'mimeType' => (string) $row['mime_type'],
            'fileSize' => (int) $row['file_size'],
            'checksumSha256' => (string) $row['checksum_sha256'],
            'uploadedByUserId' => int_or_null($row['uploaded_by_user_id'] ?? null),
            'uploadedByName' => string_or_null($row['uploaded_by_name'] ?? null) ?? 'System',
            'createdAt' => (string) $row['created_at'],
        ];
    }

    private function recordReviewEvent(int $profileId, ?string $fromStatus, string $toStatus, string $eventType, ?string $notes, ?int $actorId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO clup_review_events (profile_id, event_type, from_status, to_status, notes, actor_user_id)
             VALUES (:profile_id, :event_type, :from_status, :to_status, :notes, :actor_user_id)'
        );
        $statement->execute([
            'profile_id' => $profileId,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'notes' => $notes,
            'actor_user_id' => $actorId,
        ]);
    }

    private function assertDisjointUseLists(array ...$lists): void
    {
        $seen = [];
        foreach ($lists as $list) {
            foreach ($list as $code) {
                if (isset($seen[$code])) {
                    throw new InvalidArgumentException(sprintf('Use "%s" cannot appear in more than one permission category.', $code));
                }
                $seen[$code] = true;
            }
        }
    }

    private function normalizeUseList(mixed $values): array
    {
        if (!is_array($values)) {
            $values = preg_split('/\s*,\s*/', trim((string) $values)) ?: [];
        }
        $normalized = [];
        foreach ($values as $value) {
            $code = strtolower(trim((string) $value));
            if ($code === '') {
                continue;
            }
            $code = $this->useCode($code);
            if (!in_array($code, $normalized, true)) {
                $normalized[] = $code;
            }
        }
        return $normalized;
    }

    private function normalizeConditions(mixed $conditions): array
    {
        if (!is_array($conditions)) {
            $conditions = preg_split('/\r?\n/', trim((string) $conditions)) ?: [];
        }
        $items = [];
        foreach ($conditions as $condition) {
            $text = $this->optionalText(is_array($condition) ? ($condition['text'] ?? null) : $condition, 500);
            if ($text !== null && !in_array($text, $items, true)) {
                $items[] = $text;
            }
        }
        return array_slice($items, 0, 50);
    }

    private function geometryContainsPoint(array $geometry, float $lng, float $lat): bool
    {
        $type = (string) ($geometry['type'] ?? '');
        $coordinates = $geometry['coordinates'] ?? [];
        if ($type === 'Polygon') {
            return $this->polygonContainsPoint($coordinates, $lng, $lat);
        }
        if ($type === 'MultiPolygon') {
            foreach ($coordinates as $polygon) {
                if ($this->polygonContainsPoint($polygon, $lng, $lat)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function polygonContainsPoint(array $rings, float $lng, float $lat): bool
    {
        if ($rings === [] || !$this->ringContainsPoint($rings[0] ?? [], $lng, $lat)) {
            return false;
        }
        foreach (array_slice($rings, 1) as $hole) {
            if ($this->ringContainsPoint($hole, $lng, $lat)) {
                return false;
            }
        }
        return true;
    }

    private function ringContainsPoint(array $ring, float $lng, float $lat): bool
    {
        $inside = false;
        $count = count($ring);
        if ($count < 4) {
            return false;
        }
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = (float) ($ring[$i][0] ?? 0);
            $yi = (float) ($ring[$i][1] ?? 0);
            $xj = (float) ($ring[$j][0] ?? 0);
            $yj = (float) ($ring[$j][1] ?? 0);
            $intersects = (($yi > $lat) !== ($yj > $lat))
                && ($lng < (($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1.0E-12)) + $xi);
            if ($intersects) {
                $inside = !$inside;
            }
        }
        return $inside;
    }

    private function validateCoordinates(array $coordinates, int $depth = 0): void
    {
        if ($depth > 5) {
            throw new InvalidArgumentException('GeoJSON coordinate nesting is invalid.');
        }
        foreach ($coordinates as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('GeoJSON coordinates must be arrays.');
            }
            if (isset($item[0], $item[1]) && is_numeric($item[0]) && is_numeric($item[1])) {
                $lng = (float) $item[0];
                $lat = (float) $item[1];
                if ($lng < -180 || $lng > 180 || $lat < -90 || $lat > 90) {
                    throw new InvalidArgumentException('GeoJSON coordinates must use valid EPSG:4326 longitude and latitude values.');
                }
                continue;
            }
            $this->validateCoordinates($item, $depth + 1);
        }
    }

    private function bbox(array $coordinates): array
    {
        $points = [];
        $collect = function (array $items) use (&$collect, &$points): void {
            foreach ($items as $item) {
                if (is_array($item) && isset($item[0], $item[1]) && is_numeric($item[0]) && is_numeric($item[1])) {
                    $points[] = [(float) $item[0], (float) $item[1]];
                } elseif (is_array($item)) {
                    $collect($item);
                }
            }
        };
        $collect($coordinates);
        $lngs = array_column($points, 0);
        $lats = array_column($points, 1);
        return ['west' => min($lngs), 'south' => min($lats), 'east' => max($lngs), 'north' => max($lats)];
    }

    private function zoneCode(mixed $value, int $fallbackIndex): string
    {
        $value = strtoupper(trim((string) ($value ?? '')));
        $value = preg_replace('/[^A-Z0-9._-]+/', '-', $value) ?: '';
        $value = trim($value, '-');
        return $value !== '' ? substr($value, 0, 80) : sprintf('ZONE-%03d', $fallbackIndex);
    }

    private function useCode(mixed $value): string
    {
        $code = strtolower(trim((string) ($value ?? '')));
        $aliases = [
            'tourism' => 'hotel',
            'resort' => 'hotel',
            'office' => 'bpo',
            'industrial' => 'manufacturing',
            'healthcare' => 'hospital',
            'education' => 'university',
        ];
        $code = $aliases[$code] ?? $code;
        if (!in_array($code, self::USE_CODES, true)) {
            throw new InvalidArgumentException('Unsupported investment use code.');
        }
        return $code;
    }

    private function requiredText(mixed $value, string $label, int $max): string
    {
        $text = $this->optionalText($value, $max);
        if ($text === null) {
            throw new InvalidArgumentException($label . ' is required.');
        }
        return $text;
    }

    private function optionalText(mixed $value, int $max): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text) > $max) {
            throw new InvalidArgumentException(sprintf('Value exceeds the %d character limit.', $max));
        }
        return $text;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $text);
        if (!$date || $date->format('Y-m-d') !== $text) {
            throw new InvalidArgumentException('Dates must use YYYY-MM-DD format.');
        }
        return $text;
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

    private function audit(array $actor, string $action, string $entity, int $entityId, string $summary, array $metadata = []): void
    {
        if ($this->auditLogs === null) {
            return;
        }
        $this->auditLogs->record((int) ($actor['id'] ?? 0) ?: null, $action, $entity, $entityId, [
            'actorName' => (string) ($actor['name'] ?? 'System'),
            'actorRole' => (string) ($actor['role'] ?? 'system'),
            'eventType' => $action . '_' . $entity,
            'summary' => $summary,
            'targetLabel' => $entity . ' #' . $entityId,
            'badge' => $action === 'APPROVE' ? 'VERIFIED' : 'TRACE',
            'streamGroup' => 'all',
            ...$metadata,
        ]);
    }
}
