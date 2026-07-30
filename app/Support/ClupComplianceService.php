<?php
declare(strict_types=1);

namespace App\Support;

use App\Repositories\ClupGovernanceRepository;
use InvalidArgumentException;
use Throwable;

/**
 * Authoritative CLUP screening boundary.
 *
 * This service is deliberately conservative. Corridor heuristics are exposed only
 * as indicative planning context. PASS, CONDITIONAL, and FAIL are emitted only
 * when a versioned zoning dataset, an ordinance-backed rule, a uniquely matched
 * zone, and independently reviewed parcel evidence agree. Everything else is an
 * explicit UNVERIFIED hold.
 */
final class ClupComplianceService
{
    public const ENGINE_VERSION = 'clup-authority-v2.0.0';

    private const DISCLAIMER = 'Preliminary decision-support screening only. This result is not a locational clearance, zoning compliance certificate, permit, variance, or final LGU determination. Confirm the parcel and proposed activity with the City Planning and Development Office using the current adopted CLUP, authenticated Official Zoning Map, Zoning Ordinance, amendments, overlays, and field-verification process.';

    private const FALLBACK_USE_TYPES = [
        'commercial' => 'Commercial / Retail',
        'logistics' => 'Logistics / Warehousing',
        'hotel' => 'Tourism / Hospitality',
        'bpo' => 'Office / BPO',
        'manufacturing' => 'Light Manufacturing',
        'mixed_use' => 'Mixed-use Development',
        'hospital' => 'Hospital / Healthcare Facility',
        'university' => 'University / Educational Institution',
    ];

    /**
     * Planning heuristics only. These never produce an official compliance state.
     */
    private const INDICATIVE_CORRIDORS = [
        'downtown' => [
            'label' => 'Downtown Urban Core',
            'landUse' => 'Urban commercial and institutional context',
            'strategic' => true,
            'allowed' => ['commercial', 'bpo', 'mixed_use', 'hospital', 'university'],
            'conditional' => ['hotel', 'logistics'],
            'restricted' => ['manufacturing'],
        ],
        'highway' => [
            'label' => 'Highway Employment Corridor',
            'landUse' => 'Highway commercial and employment context',
            'strategic' => true,
            'allowed' => ['commercial', 'logistics', 'bpo', 'manufacturing'],
            'conditional' => ['hotel', 'mixed_use', 'hospital', 'university'],
            'restricted' => [],
        ],
        'coastal' => [
            'label' => 'Coastal Tourism Corridor',
            'landUse' => 'Tourism, fisheries, and coastal-settlement context',
            'strategic' => true,
            'allowed' => ['hotel'],
            'conditional' => ['commercial', 'bpo', 'mixed_use', 'hospital', 'university'],
            'restricted' => ['logistics', 'manufacturing'],
        ],
    ];

    private ?array $useTypeCache = null;
    private ?array $activeDatasetCache = null;
    private bool $activeDatasetLoaded = false;
    private array $profileCache = [];
    private array $zoneResolutionCache = [];
    private array $zoneRuleCache = [];
    private array $documentCache = [];

    public function __construct(private ClupGovernanceRepository $governance)
    {
    }

    public function catalog(): array
    {
        $types = $this->useTypes();
        $dataset = $this->activeDataset();

        return [
            'version' => self::ENGINE_VERSION,
            'investmentTypes' => array_map(static fn (array $type): string => $type['label'], $types),
            'useTypes' => array_values($types),
            'statuses' => [
                'PASS' => ['gate' => 'ELIGIBLE', 'label' => 'Pass'],
                'CONDITIONAL' => ['gate' => 'REVIEW_REQUIRED', 'label' => 'Conditional'],
                'UNVERIFIED' => ['gate' => 'HOLD', 'label' => 'Unverified'],
                'FAIL' => ['gate' => 'BLOCKED', 'label' => 'Fail'],
            ],
            'activeDataset' => $dataset === null ? null : [
                'id' => $dataset['id'],
                'name' => $dataset['name'],
                'versionLabel' => $dataset['versionLabel'],
                'sourceAgency' => $dataset['sourceAgency'],
                'sourceReference' => $dataset['sourceReference'],
                'sourceUrl' => $dataset['sourceUrl'],
                'effectiveDate' => $dataset['effectiveDate'],
                'checksumSha256' => $dataset['checksumSha256'],
                'activatedAt' => $dataset['activatedAt'],
            ],
            'evidenceRequirements' => [
                'versionedActiveDataset',
                'authenticatedZoningMapSource',
                'uniqueSiteZoneMatch',
                'ordinanceBackedExactUseRule',
                'independentlyReviewedSiteProfile',
                'hashedSupportingEvidence',
            ],
            'officialSourceStatus' => $dataset === null
                ? 'No authenticated zoning GIS dataset is active; compliance results remain UNVERIFIED.'
                : 'An activated zoning dataset is available; each site still requires a unique zone match and independently reviewed parcel evidence.',
            'disclaimer' => self::DISCLAIMER,
        ];
    }

    public function evaluate(array $property, ?string $proposedInvestmentType = null): array
    {
        $type = $this->normalizeType($proposedInvestmentType ?? (string) ($property['type'] ?? ''));
        $types = $this->useTypes();
        $typeLabel = $types[$type]['label'];
        $propertyId = (int) ($property['id'] ?? 0);
        $candidateSite = trim((string) ($property['name'] ?? '')) ?: 'Candidate site';
        $corridorKey = strtolower(trim((string) ($property['corridor'] ?? '')));
        $indicative = $this->indicativeContext($corridorKey, $type, $property);
        $dataset = $this->activeDataset();
        $profile = $propertyId > 0 ? $this->verifiedProfile($propertyId) : null;
        $resolution = $this->resolvePropertyZones($property);
        $matches = is_array($resolution['matches'] ?? null) ? $resolution['matches'] : [];
        $conflict = (bool) ($resolution['conflict'] ?? false);
        $zone = count($matches) === 1 ? $matches[0] : null;
        $rule = $zone !== null ? $this->activeRule((int) $zone['id'], $type) : null;
        $documents = $profile !== null ? $this->profileDocuments((int) $profile['id']) : [];

        $profileMatchesDataset = $profile !== null
            && $dataset !== null
            && (int) ($profile['datasetId'] ?? 0) === (int) $dataset['id'];
        $profileMatchesZone = $profile !== null
            && $zone !== null
            && (int) ($profile['zoneId'] ?? 0) === (int) $zone['id'];
        $independentReview = $profile !== null
            && (int) ($profile['preparedByUserId'] ?? 0) > 0
            && (int) ($profile['reviewedByUserId'] ?? 0) > 0
            && (int) $profile['preparedByUserId'] !== (int) $profile['reviewedByUserId'];
        $hasMappedEvidence = $this->hasDocumentType($documents, ['zoning_map_extract', 'locational_clearance', 'site_plan']);
        $hasOrdinanceEvidence = $this->hasDocumentType($documents, ['ordinance_extract', 'locational_clearance']);

        $evidenceFlags = [
            'ordinanceTextVerified' => $rule !== null && !empty($rule['ordinanceReference']) && $hasOrdinanceEvidence,
            'zoningMapVerified' => $dataset !== null && !empty($dataset['checksumSha256']) && !empty($dataset['sourceReference']),
            'uniqueZoneMatchVerified' => $zone !== null && !$conflict,
            'parcelMatchVerified' => $profileMatchesDataset && $profileMatchesZone && $hasMappedEvidence,
            'independentReviewCompleted' => $profile !== null && ($profile['reviewStatus'] ?? null) === 'verified' && $independentReview,
            'supportingEvidenceHashed' => $documents !== [],
            'amendmentChainVerified' => $rule !== null && !empty($rule['ordinanceReference']) && !empty($dataset['sourceReference'] ?? null),
            'overlaysVerified' => false,
            'fieldVerificationCompleted' => $this->hasDocumentType($documents, ['locational_clearance', 'barangay_certification']),
        ];

        $requiredEvidence = [
            'zoningMapVerified',
            'uniqueZoneMatchVerified',
            'parcelMatchVerified',
            'independentReviewCompleted',
            'supportingEvidenceHashed',
            'ordinanceTextVerified',
            'amendmentChainVerified',
        ];
        $missingEvidence = [];
        foreach ($requiredEvidence as $flag) {
            if (!$evidenceFlags[$flag]) {
                $missingEvidence[] = $this->evidenceLabel($flag);
            }
        }
        if ($conflict) {
            array_unshift($missingEvidence, 'Resolve the split-zone or overlapping-polygon conflict.');
        } elseif ($dataset !== null && $zone === null) {
            array_unshift($missingEvidence, 'Confirm that the candidate site lies within the authenticated zoning-map coverage.');
        }
        if ($rule === null) {
            $missingEvidence[] = sprintf('Record an effective ordinance rule for %s in the matched zone.', $typeLabel);
        }
        $missingEvidence = array_values(array_unique($missingEvidence));

        $authoritative = $dataset !== null && $profile !== null && $zone !== null && $rule !== null && $missingEvidence === [];
        $status = 'UNVERIFIED';
        $gate = 'HOLD';
        if ($authoritative) {
            $status = match ($rule['permissionStatus']) {
                'allowed' => 'PASS',
                'conditional' => 'CONDITIONAL',
                'restricted' => 'FAIL',
                default => 'UNVERIFIED',
            };
            $gate = match ($status) {
                'PASS' => 'ELIGIBLE',
                'CONDITIONAL' => 'REVIEW_REQUIRED',
                'FAIL' => 'BLOCKED',
                default => 'HOLD',
            };
        }

        $score = $authoritative
            ? $this->suitabilityScore($property, $status, $type, (bool) $indicative['strategic'])
            : null;
        $conditions = $rule !== null && is_array($rule['conditions'] ?? null) ? array_values($rule['conditions']) : [];
        $zoneLabel = $zone !== null
            ? trim(sprintf('%s — %s', (string) ($zone['zoneCode'] ?? ''), (string) ($zone['name'] ?? '')), " —")
            : ($profile['zoningClassification'] ?? null);
        $explanation = $this->explanation($status, $typeLabel, $zoneLabel, $indicative, $missingEvidence, $conditions);

        return [
            'version' => self::ENGINE_VERSION,
            'engineVersion' => self::ENGINE_VERSION,
            'evaluationId' => null,
            'evaluatedAt' => gmdate(DATE_ATOM),
            'status' => $status,
            'statusKey' => strtolower($status),
            'priorityGate' => $gate,
            'suitabilityScore' => $score,
            'indicativeSuitabilityScore' => $indicative['score'],
            'indicativeStatus' => $indicative['status'],
            'candidateSite' => $candidateSite,
            'propertyId' => $propertyId ?: null,
            'proposedInvestmentType' => $type,
            'proposedUseCode' => $type,
            'proposedInvestmentLabel' => $typeLabel,
            'existingLandUse' => $profile['existingLandUse'] ?? $indicative['landUse'],
            'zoningClassification' => $zoneLabel ?: 'Official zoning classification not verified',
            'zoneId' => $zone['id'] ?? null,
            'zoneCode' => $zone['zoneCode'] ?? null,
            'allowedUses' => $zone !== null ? $this->zoneUses((int) $zone['id'], 'allowed') : [],
            'conditionalUses' => $zone !== null ? $this->zoneUses((int) $zone['id'], 'conditional') : [],
            'restrictedUses' => $zone !== null ? $this->zoneUses((int) $zone['id'], 'restricted') : [],
            'conditions' => $conditions,
            'blockers' => $status === 'FAIL' ? ['The proposed use is restricted by the effective rule for the matched zone.'] : [],
            'missingEvidence' => $missingEvidence,
            'evidenceFlags' => $evidenceFlags,
            'strategicGrowthCorridor' => (bool) $indicative['strategic'],
            'strategicGrowthCorridorLabel' => $indicative['corridorLabel'],
            'explanation' => $explanation,
            'recommendedLguAction' => $this->recommendedAction($status, $conditions, $missingEvidence),
            'screeningBasis' => $authoritative
                ? 'Versioned zoning dataset, exact ordinance-use rule, unique map match, and independently reviewed site evidence'
                : 'Evidence-controlled preliminary screen; corridor context is shown only as a non-legal indicator',
            'evidenceLevel' => $authoritative ? 'VERIFIED' : ($profile !== null ? 'REVIEWED_INCOMPLETE' : 'UNVERIFIED'),
            'profileId' => $profile['id'] ?? null,
            'profileVersion' => $profile['versionNo'] ?? null,
            'profileReviewStatus' => $profile['reviewStatus'] ?? 'not_recorded',
            'verifiedAt' => $profile['reviewedAt'] ?? null,
            'verifiedBy' => $profile['reviewedByName'] ?? null,
            'sourceReference' => $rule['ordinanceReference'] ?? $profile['sourceReference'] ?? $dataset['sourceReference'] ?? null,
            'datasetId' => $dataset['id'] ?? null,
            'datasetName' => $dataset['name'] ?? null,
            'datasetVersion' => $dataset['versionLabel'] ?? null,
            'datasetChecksumSha256' => $dataset['checksumSha256'] ?? null,
            'isPreliminary' => true,
            'isLegalDetermination' => false,
            'disclaimer' => self::DISCLAIMER,
        ];
    }

    public function evaluateMatrix(array $property): array
    {
        $matrix = [];
        foreach (array_keys($this->useTypes()) as $useCode) {
            $matrix[$useCode] = $this->evaluate($property, $useCode);
        }
        return $matrix;
    }

    public function decorateProperty(array $property, ?string $selectedUse = null): array
    {
        $property['clupEvaluations'] = $this->evaluateMatrix($property);
        $use = $selectedUse !== null ? $this->normalizeType($selectedUse) : $this->normalizeType((string) ($property['type'] ?? ''));
        $property['clupCompliance'] = $property['clupEvaluations'][$use];
        return $property;
    }

    public function persistEvaluation(array $property, string $proposedUse, ?array $actor = null): array
    {
        $result = $this->evaluate($property, $proposedUse);
        $record = $this->governance->recordEvaluation($property, $result, $actor);
        $result['evaluationId'] = $record['id'];
        $result['persistedAt'] = $record['createdAt'];
        return $result;
    }

    private function useTypes(): array
    {
        if ($this->useTypeCache !== null) {
            return $this->useTypeCache;
        }
        $items = $this->governance->useTypes();
        $types = [];
        foreach ($items as $item) {
            $code = strtolower((string) ($item['code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $types[$code] = [
                'code' => $code,
                'label' => (string) ($item['label'] ?? $code),
                'category' => (string) ($item['category'] ?? 'other'),
                'description' => (string) ($item['description'] ?? ''),
            ];
        }
        if ($types === []) {
            foreach (self::FALLBACK_USE_TYPES as $code => $label) {
                $types[$code] = ['code' => $code, 'label' => $label, 'category' => 'other', 'description' => ''];
            }
        }
        return $this->useTypeCache = $types;
    }

    private function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));
        $aliases = [
            'tourism' => 'hotel',
            'resort' => 'hotel',
            'office' => 'bpo',
            'industrial' => 'manufacturing',
            'healthcare' => 'hospital',
            'education' => 'university',
        ];
        $type = $aliases[$type] ?? $type;
        if ($type === '' || !isset($this->useTypes()[$type])) {
            throw new InvalidArgumentException('Select a supported proposed investment use from the controlled CLUP catalog.');
        }
        return $type;
    }

    private function activeDataset(): ?array
    {
        if (!$this->activeDatasetLoaded) {
            $this->activeDatasetCache = $this->governance->activeDataset();
            $this->activeDatasetLoaded = true;
        }
        return $this->activeDatasetCache;
    }

    private function verifiedProfile(int $propertyId): ?array
    {
        if (!array_key_exists($propertyId, $this->profileCache)) {
            $this->profileCache[$propertyId] = $this->governance->verifiedProfile($propertyId);
        }
        return $this->profileCache[$propertyId];
    }

    private function resolvePropertyZones(array $property): array
    {
        $propertyId = (int) ($property['id'] ?? 0);
        $key = $propertyId > 0 ? (string) $propertyId : sprintf('%.7F:%.7F', (float) ($property['lat'] ?? 0), (float) ($property['lng'] ?? 0));
        if (!isset($this->zoneResolutionCache[$key])) {
            $lat = filter_var($property['lat'] ?? null, FILTER_VALIDATE_FLOAT);
            $lng = filter_var($property['lng'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($lat === false || $lng === false) {
                $this->zoneResolutionCache[$key] = ['dataset' => $this->activeDataset(), 'matches' => [], 'conflict' => false];
            } else {
                $this->zoneResolutionCache[$key] = $this->governance->resolveZones((float) $lat, (float) $lng);
            }
        }
        return $this->zoneResolutionCache[$key];
    }

    private function activeRule(int $zoneId, string $useCode): ?array
    {
        $key = $zoneId . ':' . $useCode;
        if (!array_key_exists($key, $this->zoneRuleCache)) {
            $this->zoneRuleCache[$key] = $this->governance->activeRule($zoneId, $useCode);
        }
        return $this->zoneRuleCache[$key];
    }

    private function zoneUses(int $zoneId, string $permission): array
    {
        $labels = [];
        foreach ($this->useTypes() as $code => $type) {
            $rule = $this->activeRule($zoneId, $code);
            if ($rule !== null && ($rule['permissionStatus'] ?? null) === $permission) {
                $labels[] = $type['label'];
            }
        }
        return $labels;
    }

    private function profileDocuments(int $profileId): array
    {
        if (!isset($this->documentCache[$profileId])) {
            $this->documentCache[$profileId] = $this->governance->evidenceDocuments($profileId);
        }
        return $this->documentCache[$profileId];
    }

    private function hasDocumentType(array $documents, array $types): bool
    {
        foreach ($documents as $document) {
            if (in_array($document['documentType'] ?? null, $types, true)) {
                return true;
            }
        }
        return false;
    }

    private function indicativeContext(string $corridorKey, string $type, array $property): array
    {
        $corridor = self::INDICATIVE_CORRIDORS[$corridorKey] ?? [
            'label' => 'Unclassified planning context',
            'landUse' => 'Existing land use requires verification',
            'strategic' => false,
            'allowed' => [],
            'conditional' => [],
            'restricted' => [],
        ];
        $status = in_array($type, $corridor['restricted'], true)
            ? 'INDICATIVE_CONFLICT'
            : (in_array($type, $corridor['allowed'], true) ? 'INDICATIVE_FIT' : 'INDICATIVE_REVIEW');
        $base = max(0, min(100, (int) ($property['zoningScore'] ?? 55)));
        $readiness = max(0, min(100, (int) ($property['investmentReadiness']['totalScore'] ?? 50)));
        $fit = $status === 'INDICATIVE_FIT' ? 85 : ($status === 'INDICATIVE_REVIEW' ? 60 : 30);
        $score = (int) round(($base * .45) + ($readiness * .25) + ($fit * .30));
        return [
            'status' => $status,
            'score' => max(0, min(100, $score)),
            'landUse' => $corridor['landUse'],
            'strategic' => (bool) $corridor['strategic'],
            'corridorLabel' => $corridor['label'],
        ];
    }

    private function suitabilityScore(array $property, string $status, string $type, bool $strategic): int
    {
        $zoning = max(0, min(100, (int) ($property['zoningScore'] ?? 50)));
        $readiness = max(0, min(100, (int) ($property['investmentReadiness']['totalScore'] ?? 50)));
        $existingType = strtolower(trim((string) ($property['type'] ?? '')));
        $useFit = $existingType === $type ? 100 : 78;
        $legalFit = match ($status) {
            'PASS' => 100,
            'CONDITIONAL' => 65,
            'FAIL' => 0,
            default => 40,
        };
        $strategicScore = $strategic ? 90 : 55;
        $score = (int) round(($legalFit * .45) + ($zoning * .20) + ($useFit * .15) + ($readiness * .15) + ($strategicScore * .05));
        return match ($status) {
            'FAIL' => min(39, $score),
            'CONDITIONAL' => max(40, min(74, $score)),
            'PASS' => max(75, min(100, $score)),
            default => max(0, min(100, $score)),
        };
    }

    private function explanation(string $status, string $typeLabel, ?string $zoneLabel, array $indicative, array $missingEvidence, array $conditions): string
    {
        if ($status === 'UNVERIFIED') {
            $reason = $missingEvidence[0] ?? 'Required official zoning evidence is incomplete.';
            return sprintf('%s cannot yet receive a legal compatibility conclusion for this site. %s The %s signal is contextual only and does not determine zoning compliance.', $typeLabel, $reason, $indicative['corridorLabel']);
        }
        if ($status === 'PASS') {
            return sprintf('%s is recorded as an allowed use under the effective ordinance rule for %s, supported by the active map version and independently reviewed site evidence.', $typeLabel, $zoneLabel ?: 'the matched zone');
        }
        if ($status === 'CONDITIONAL') {
            return sprintf('%s is conditionally permitted for %s, subject to %s.', $typeLabel, $zoneLabel ?: 'the matched zone', $conditions !== [] ? implode('; ', $conditions) : 'the ordinance and CPDO review conditions');
        }
        return sprintf('%s is recorded as a restricted use under the effective ordinance rule for %s. Strategic-corridor attractiveness cannot override that restriction.', $typeLabel, $zoneLabel ?: 'the matched zone');
    }

    private function recommendedAction(string $status, array $conditions, array $missingEvidence): string
    {
        return match ($status) {
            'PASS' => 'Advance to the formal CPDO locational-clearance and zoning-compliance workflow; verify development controls, overlays, parcel boundaries, and current amendment/effectivity records before commitment.',
            'CONDITIONAL' => 'Open an LGU conditions review, assign each ordinance condition to a responsible office, collect required clearances, and do not mark the site eligible until every condition is formally resolved.',
            'FAIL' => 'Do not prioritize the proposed use at this site. Redirect it to a compatible zone; treat any variance, exception, reclassification, or DAR conversion as a separate lawful process, not an assumed condition.',
            default => 'Place the recommendation on hold and complete the evidence queue: ' . implode(' ', array_slice($missingEvidence, 0, 3)),
        };
    }

    private function evidenceLabel(string $flag): string
    {
        return match ($flag) {
            'zoningMapVerified' => 'Import and activate an authenticated, checksummed Official Zoning Map dataset.',
            'uniqueZoneMatchVerified' => 'Resolve the site against exactly one official zoning polygon.',
            'parcelMatchVerified' => 'Attach reviewed parcel-to-zone evidence such as an authenticated map extract or locational-clearance record.',
            'independentReviewCompleted' => 'Submit the site profile for verification by a different authorized reviewer.',
            'supportingEvidenceHashed' => 'Attach at least one hashed official supporting document.',
            'ordinanceTextVerified' => 'Attach the applicable ordinance extract and link an exact use rule.',
            'amendmentChainVerified' => 'Verify the applicable ordinance and amendment chain for the matched parcel and use.',
            default => 'Complete the required CLUP evidence.',
        };
    }
}
