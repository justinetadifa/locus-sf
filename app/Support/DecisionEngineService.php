<?php
declare(strict_types=1);

namespace App\Support;

final class DecisionEngineService
{
    private const DEFAULT_PERSONA = 'balanced';

    private const PERSONAS = [
        'balanced' => [
            'label' => 'Balanced Desk',
            'shortLabel' => 'Balanced',
            'description' => 'General opportunity posture balancing price, readiness, and proof.',
            'weights' => [
                'financial' => 24,
                'demand' => 12,
                'readiness' => 20,
                'location' => 16,
                'risk' => 13,
                'personal' => 15,
            ],
            'typeScores' => [
                'commercial' => 90,
                'logistics' => 84,
                'bpo' => 82,
                'hotel' => 76,
                'manufacturing' => 78,
                '*' => 74,
            ],
            'corridorScores' => [
                'downtown' => 90,
                'highway' => 86,
                'coastal' => 78,
                '*' => 74,
            ],
            'budgetAnchor' => 70000000,
            'areaIdeal' => ['min' => 3.0, 'max' => 12.0],
            'uncertaintyTarget' => 28,
            'buyConfidence' => 72,
        ],
        'conservative_income' => [
            'label' => 'Conservative Income',
            'shortLabel' => 'Income',
            'description' => 'Prefers cleaner proof, stronger readiness, and lower execution risk.',
            'weights' => [
                'financial' => 22,
                'demand' => 8,
                'readiness' => 24,
                'location' => 14,
                'risk' => 20,
                'personal' => 12,
            ],
            'typeScores' => [
                'commercial' => 94,
                'bpo' => 88,
                'logistics' => 72,
                'hotel' => 68,
                'manufacturing' => 64,
                '*' => 70,
            ],
            'corridorScores' => [
                'downtown' => 92,
                'highway' => 80,
                'coastal' => 66,
                '*' => 72,
            ],
            'budgetAnchor' => 60000000,
            'areaIdeal' => ['min' => 2.0, 'max' => 9.0],
            'uncertaintyTarget' => 16,
            'buyConfidence' => 78,
        ],
        'growth_focused' => [
            'label' => 'Growth Focused',
            'shortLabel' => 'Growth',
            'description' => 'Accepts moderate risk when momentum and upside are visible.',
            'weights' => [
                'financial' => 26,
                'demand' => 16,
                'readiness' => 14,
                'location' => 18,
                'risk' => 10,
                'personal' => 16,
            ],
            'typeScores' => [
                'logistics' => 90,
                'commercial' => 86,
                'manufacturing' => 84,
                'hotel' => 82,
                'bpo' => 78,
                '*' => 74,
            ],
            'corridorScores' => [
                'highway' => 92,
                'downtown' => 88,
                'coastal' => 82,
                '*' => 76,
            ],
            'budgetAnchor' => 85000000,
            'areaIdeal' => ['min' => 4.0, 'max' => 18.0],
            'uncertaintyTarget' => 36,
            'buyConfidence' => 68,
        ],
        'small_business_operator' => [
            'label' => 'Owner Operator',
            'shortLabel' => 'Operator',
            'description' => 'Prioritizes operating practicality, utilities, and direct location fit.',
            'weights' => [
                'financial' => 18,
                'demand' => 18,
                'readiness' => 20,
                'location' => 20,
                'risk' => 10,
                'personal' => 14,
            ],
            'typeScores' => [
                'commercial' => 96,
                'bpo' => 84,
                'hotel' => 74,
                'logistics' => 58,
                'manufacturing' => 52,
                '*' => 72,
            ],
            'corridorScores' => [
                'downtown' => 94,
                'highway' => 76,
                'coastal' => 72,
                '*' => 74,
            ],
            'budgetAnchor' => 45000000,
            'areaIdeal' => ['min' => 1.5, 'max' => 8.0],
            'uncertaintyTarget' => 24,
            'buyConfidence' => 70,
        ],
        'speculative_early_mover' => [
            'label' => 'Speculative Early Mover',
            'shortLabel' => 'Speculative',
            'description' => 'Will move earlier when location and upside look exceptional.',
            'weights' => [
                'financial' => 20,
                'demand' => 20,
                'readiness' => 10,
                'location' => 20,
                'risk' => 8,
                'personal' => 22,
            ],
            'typeScores' => [
                'logistics' => 88,
                'hotel' => 86,
                'manufacturing' => 84,
                'commercial' => 82,
                'bpo' => 72,
                '*' => 76,
            ],
            'corridorScores' => [
                'coastal' => 92,
                'highway' => 90,
                'downtown' => 84,
                '*' => 76,
            ],
            'budgetAnchor' => 90000000,
            'areaIdeal' => ['min' => 3.0, 'max' => 20.0],
            'uncertaintyTarget' => 50,
            'buyConfidence' => 62,
        ],
    ];

    public function personas(): array
    {
        $items = [];
        foreach (self::PERSONAS as $key => $profile) {
            $items[] = [
                'key' => $key,
                'label' => (string) $profile['label'],
                'shortLabel' => (string) ($profile['shortLabel'] ?? $profile['label']),
                'description' => (string) ($profile['description'] ?? ''),
            ];
        }

        return $items;
    }

    public function decorateProperties(array $properties, array $options = []): array
    {
        $voteSummaries = is_array($options['voteSummaries'] ?? null) ? $options['voteSummaries'] : [];
        $messageSummaries = is_array($options['messageSummaries'] ?? null) ? $options['messageSummaries'] : [];
        $activePersona = $this->normalizePersona($options['activePersona'] ?? null);

        return array_map(function (array $property) use ($voteSummaries, $messageSummaries, $activePersona): array {
            $propertyId = (int) ($property['id'] ?? 0);

            return $this->decorateProperty($property, [
                'voteSummary' => $voteSummaries[$propertyId] ?? [],
                'messageSummary' => $messageSummaries[$propertyId] ?? [],
                'activePersona' => $activePersona,
            ]);
        }, $properties);
    }

    public function decorateProperty(array $property, array $options = []): array
    {
        $property['decision'] = $this->build($property, $options);
        return $property;
    }

    public function build(array $property, array $options = []): array
    {
        $context = $this->buildContext($property, $options);
        $personas = [];

        foreach (self::PERSONAS as $key => $profile) {
            $personas[$key] = $this->scorePersona($property, $context, $key, $profile);
        }

        $activePersona = $this->normalizePersona($options['activePersona'] ?? null);
        $active = $personas[$activePersona] ?? $personas[self::DEFAULT_PERSONA];

        return [
            'version' => 'decision-engine-mvp',
            'activePersona' => $activePersona,
            'label' => $active['label'],
            'shortLabel' => $active['shortLabel'],
            'score' => $active['score'],
            'confidence' => $active['confidence'],
            'confidenceLabel' => $this->confidenceLabel((int) $active['confidence']),
            'statusKey' => $active['statusKey'],
            'statusLabel' => $active['statusLabel'],
            'toneLabel' => $active['toneLabel'],
            'summary' => $active['summary'],
            'nextAction' => $active['nextAction'],
            'reasons' => $active['reasons'],
            'components' => $active['components'],
            'personas' => $personas,
            'evidence' => [
                'voteTotal' => $context['voteTotal'],
                'messageCount' => $context['messageCount'],
                'threadCount' => $context['threadCount'],
                'groundTruthVisitCount' => $context['groundTruthVisitCount'],
                'openDocumentRequestCount' => $context['openDocumentRequestCount'],
                'freshnessDays' => $context['freshnessDays'],
            ],
        ];
    }

    private function buildContext(array $property, array $options): array
    {
        $voteSummary = is_array($options['voteSummary'] ?? null) ? $options['voteSummary'] : $this->voteSummaryFromVotes($options['votes'] ?? []);
        $messageSummary = is_array($options['messageSummary'] ?? null) ? $options['messageSummary'] : (is_array($options['conversationSummary'] ?? null) ? $options['conversationSummary'] : []);
        $documentRequests = is_array($options['documentRequests'] ?? null) ? $options['documentRequests'] : [];
        $visit = is_array($options['visit'] ?? null) ? $options['visit'] : null;
        $blockers = is_array($options['blockers'] ?? null) ? $options['blockers'] : [];
        $lastConfirmed = string_or_null($property['lastConfirmedAvailableAt'] ?? $property['updatedAt'] ?? null);

        $voteTotal = (int) ($voteSummary['totalVotes'] ?? 0);
        $topNeed = string_or_null($voteSummary['topNeed'] ?? null);
        $dominantShare = (float) ($voteSummary['dominantShare'] ?? 0.0);
        $messageCount = (int) ($messageSummary['messageCount'] ?? 0);
        $threadCount = (int) ($messageSummary['threadCount'] ?? 0);
        $openDocumentRequestCount = $documentRequests !== []
            ? count(array_filter($documentRequests, static fn (array $request): bool => in_array(strtolower((string) ($request['status'] ?? 'requested')), ['requested', 'in_review'], true)))
            : (int) ($property['openDocumentRequestCount'] ?? 0);

        $freshnessDays = $this->daysSince($lastConfirmed);
        $freshnessScore = $this->freshnessScore($freshnessDays);
        $groundTruthVisitCount = (int) ($property['groundTruthVisitCount'] ?? 0);
        $visitStatus = strtolower((string) ($visit['status'] ?? ($groundTruthVisitCount > 0 ? 'visited' : 'unscheduled')));
        $fieldAuditComplete = (bool) ($visit['fieldAuditComplete'] ?? false);
        $groundTruthMultiplier = (float) ($visit['groundTruthMultiplier'] ?? $property['groundTruthMultiplier'] ?? 1.0);

        $dataFields = [
            int_or_null($property['pricePerSqm'] ?? null),
            int_or_null($property['assessedValueSqm'] ?? null),
            float_or_null($property['distToRoadKm'] ?? null),
            string_or_null($property['utilityStatus'] ?? null),
            int_or_null($property['zoningScore'] ?? null),
            string_or_null($property['documentsReviewedAt'] ?? null),
            string_or_null($property['siteVerifiedAt'] ?? null),
            $lastConfirmed,
            int_or_null($property['dueDiligencePct'] ?? null),
            int_or_null($property['documentCompletenessPct'] ?? null),
        ];
        $availableFields = count(array_filter($dataFields, static fn (mixed $value): bool => $value !== null && $value !== ''));
        $dataCompleteness = (int) round(($availableFields / max(count($dataFields), 1)) * 100);

        $approvalState = strtolower((string) ($property['approvalState'] ?? 'approved'));
        $sellerIdentityStatus = strtolower((string) ($property['sellerIdentityStatus'] ?? 'unverified'));
        $dueDiligencePct = $this->clamp((int) ($property['dueDiligencePct'] ?? 0));
        $documentCompletenessPct = $this->clamp((int) ($property['documentCompletenessPct'] ?? 0));

        $criticalBlockers = [];
        if ($approvalState !== 'approved') {
            $criticalBlockers[] = 'approval';
        }
        if ($dueDiligencePct < 55) {
            $criticalBlockers[] = 'due_diligence';
        }
        if ($documentCompletenessPct < 50) {
            $criticalBlockers[] = 'documents';
        }
        if ($sellerIdentityStatus !== 'verified' && $documentCompletenessPct < 75) {
            $criticalBlockers[] = 'seller_identity';
        }
        if ($freshnessScore < 45) {
            $criticalBlockers[] = 'freshness';
        }

        foreach ($blockers as $blocker) {
            $source = strtolower((string) ($blocker['source'] ?? ''));
            if ($source !== '') {
                $criticalBlockers[] = $source;
            }
        }

        return [
            'voteTotal' => $voteTotal,
            'topNeed' => $topNeed,
            'dominantShare' => $dominantShare,
            'messageCount' => $messageCount,
            'threadCount' => $threadCount,
            'openDocumentRequestCount' => $openDocumentRequestCount,
            'freshnessDays' => $freshnessDays,
            'freshnessScore' => $freshnessScore,
            'groundTruthVisitCount' => $groundTruthVisitCount,
            'visitStatus' => $visitStatus,
            'fieldAuditComplete' => $fieldAuditComplete,
            'groundTruthMultiplier' => $groundTruthMultiplier,
            'dataCompleteness' => $dataCompleteness,
            'criticalBlockers' => array_values(array_unique($criticalBlockers)),
        ];
    }

    private function scorePersona(array $property, array $context, string $personaKey, array $profile): array
    {
        $financial = $this->scoreFinancial($property);
        $demand = $this->scoreDemand($context);
        $readiness = $this->scoreReadiness($property);
        $location = $this->scoreLocation($property);
        $risk = $this->scoreRisk($property, $context);
        $personal = $this->scorePersonalFit($property, $context, $profile);

        $components = [
            'financial' => $financial,
            'demand' => $demand,
            'readiness' => $readiness,
            'location' => $location,
            'risk' => $risk,
            'personal' => $personal,
        ];
        $score = $this->weightedScore($components, $profile['weights'] ?? []);
        $confidence = $this->scoreConfidence($property, $context);
        [$statusKey, $statusLabel, $toneLabel] = $this->classify($property, $context, $score, $confidence, $profile);
        $nextAction = $this->nextAction($property, $context);
        $reasons = $this->reasons($components, $property, $context, $nextAction);

        return [
            'key' => $personaKey,
            'label' => (string) ($profile['label'] ?? 'Persona'),
            'shortLabel' => (string) ($profile['shortLabel'] ?? $profile['label'] ?? 'Persona'),
            'score' => $score,
            'confidence' => $confidence,
            'confidenceLabel' => $this->confidenceLabel($confidence),
            'statusKey' => $statusKey,
            'statusLabel' => $statusLabel,
            'toneLabel' => $toneLabel,
            'summary' => $this->summaryLine($statusKey, $score, $confidence, $nextAction),
            'nextAction' => $nextAction,
            'reasons' => $reasons,
            'components' => [
                ['key' => 'financial', 'label' => 'Financial Attractiveness', 'score' => $financial],
                ['key' => 'demand', 'label' => 'Demand Signal', 'score' => $demand],
                ['key' => 'readiness', 'label' => 'Readiness', 'score' => $readiness],
                ['key' => 'location', 'label' => 'Strategic Location Fit', 'score' => $location],
                ['key' => 'risk', 'label' => 'Risk Control', 'score' => $risk],
                ['key' => 'personal', 'label' => 'Persona Fit', 'score' => $personal],
            ],
        ];
    }

    private function scoreFinancial(array $property): int
    {
        $pricePerSqm = max(1, (int) ($property['pricePerSqm'] ?? 0));
        $marketScore = $this->clamp((int) ($property['marketScore'] ?? $property['score'] ?? 0));
        $assessedValueSqm = int_or_null($property['assessedValueSqm'] ?? null);

        if ($assessedValueSqm === null || $assessedValueSqm < 1) {
            $priceCompetitiveness = $this->clamp((int) round(($marketScore * 0.65) + 18));
            $assessedSpread = $this->clamp((int) round(($marketScore * 0.55) + 22));
        } else {
            $ratio = $assessedValueSqm / max($pricePerSqm, 1);
            $priceCompetitiveness = $this->clamp((int) round(55 + (($ratio - 0.8) * 85)));
            $spreadPct = (($assessedValueSqm - $pricePerSqm) / max($pricePerSqm, 1)) * 100;
            $assessedSpread = $this->clamp((int) round(55 + ($spreadPct * 0.8)));
        }

        return $this->clamp((int) round(
            ($priceCompetitiveness * 0.40)
            + ($assessedSpread * 0.35)
            + ($marketScore * 0.25)
        ));
    }

    private function scoreDemand(array $context): int
    {
        $voteVolume = $this->clamp((int) round((int) $context['voteTotal'] * 12));
        $voteAlignment = (int) $context['voteTotal'] > 0
            ? $this->clamp((int) round(58 + ((float) $context['dominantShare'] * 32)))
            : 44;
        $messageTraction = $this->clamp((int) round((int) $context['messageCount'] * 18));
        $visitTraction = match ((string) $context['visitStatus']) {
            'visited' => (bool) $context['fieldAuditComplete'] ? 100 : 84,
            'in_progress' => 76,
            'confirmed' => 68,
            'counter_offered' => 55,
            'proposed' => 46,
            default => ((int) $context['groundTruthVisitCount'] > 0 ? 74 : 24),
        };

        return $this->clamp((int) round(
            ($voteVolume * 0.50)
            + ($voteAlignment * 0.25)
            + ($messageTraction * 0.15)
            + ($visitTraction * 0.10)
        ));
    }

    private function scoreReadiness(array $property): int
    {
        $approvalScore = match (strtolower((string) ($property['approvalState'] ?? 'approved'))) {
            'approved' => 100,
            'pending_review' => 56,
            'draft' => 42,
            'rejected' => 12,
            'archived' => 6,
            default => 40,
        };
        $documentsReviewed = string_or_null($property['documentsReviewedAt'] ?? null) !== null ? 100 : 34;
        $siteVerified = string_or_null($property['siteVerifiedAt'] ?? null) !== null ? 100 : 30;
        $dueDiligencePct = $this->clamp((int) ($property['dueDiligencePct'] ?? 0));
        $documentCompletenessPct = $this->clamp((int) ($property['documentCompletenessPct'] ?? 0));

        return $this->clamp((int) round(
            ($dueDiligencePct * 0.35)
            + ($documentCompletenessPct * 0.30)
            + ($approvalScore * 0.15)
            + ($documentsReviewed * 0.10)
            + ($siteVerified * 0.10)
        ));
    }

    private function scoreLocation(array $property): int
    {
        $corridorFit = match (strtolower((string) ($property['corridor'] ?? ''))) {
            'downtown' => 92,
            'highway' => 88,
            'coastal' => 78,
            default => 72,
        };
        $roadAccess = $this->clamp((int) ($property['roadAccess'] ?? 0));
        $utilityReadiness = match (strtolower((string) ($property['utilityStatus'] ?? ''))) {
            'full_ready' => 100,
            'power_water' => 82,
            'partial' => 62,
            'limited' => 40,
            'off_grid' => 18,
            default => 48,
        };
        $zoningScore = int_or_null($property['zoningScore'] ?? null) ?? 55;
        $roadDistanceScore = float_or_null($property['distToRoadKm'] ?? null) !== null
            ? $this->clamp((int) round(100 - (float) $property['distToRoadKm'] * 18), 28, 100)
            : 55;

        return $this->clamp((int) round(
            ($corridorFit * 0.30)
            + ($roadAccess * 0.25)
            + ($utilityReadiness * 0.20)
            + ($zoningScore * 0.15)
            + ($roadDistanceScore * 0.10)
        ));
    }

    private function scoreRisk(array $property, array $context): int
    {
        $listingVerification = match (strtolower((string) ($property['listingVerificationStatus'] ?? 'unverified'))) {
            'verified', 'approved' => 100,
            'reviewing', 'partially_verified', 'pending_review' => 72,
            'draft' => 36,
            'rejected', 'archived' => 10,
            default => 34,
        };
        $sellerVerification = match (strtolower((string) ($property['sellerIdentityStatus'] ?? 'unverified'))) {
            'verified' => 100,
            'pending' => 64,
            default => 32,
        };
        $documentsReviewed = string_or_null($property['documentsReviewedAt'] ?? null) !== null ? 100 : 34;
        $siteVerified = string_or_null($property['siteVerifiedAt'] ?? null) !== null ? 100 : 32;
        $trustScore = $this->clamp((int) round(
            ($listingVerification * 0.35)
            + ($sellerVerification * 0.25)
            + ($documentsReviewed * 0.20)
            + ($siteVerified * 0.20)
        ));

        $fieldValidation = match ((string) $context['visitStatus']) {
            'visited' => (bool) $context['fieldAuditComplete'] ? 100 : 82,
            'in_progress' => 76,
            'confirmed' => 64,
            'counter_offered' => 52,
            'proposed' => 46,
            default => ((int) $context['groundTruthVisitCount'] > 0 ? 74 : 30),
        };
        if ((float) $context['groundTruthMultiplier'] > 1.04) {
            $fieldValidation = $this->clamp($fieldValidation + 6);
        }

        $blockerClearance = 100;
        $blockerClearance -= min(42, (int) $context['openDocumentRequestCount'] * 14);
        if (strtolower((string) ($property['approvalState'] ?? 'approved')) !== 'approved') {
            $blockerClearance -= 24;
        }
        if ((int) ($property['dueDiligencePct'] ?? 0) < 60) {
            $blockerClearance -= 14;
        } elseif ((int) ($property['dueDiligencePct'] ?? 0) < 80) {
            $blockerClearance -= 6;
        }
        if ((int) ($property['documentCompletenessPct'] ?? 0) < 60) {
            $blockerClearance -= 14;
        } elseif ((int) ($property['documentCompletenessPct'] ?? 0) < 80) {
            $blockerClearance -= 6;
        }
        if (strtolower((string) ($property['sellerIdentityStatus'] ?? 'unverified')) !== 'verified') {
            $blockerClearance -= 10;
        }
        $blockerClearance = $this->clamp($blockerClearance);

        return $this->clamp((int) round(
            ($trustScore * 0.30)
            + ((int) $context['freshnessScore'] * 0.25)
            + ($fieldValidation * 0.25)
            + ($blockerClearance * 0.20)
        ));
    }

    private function scorePersonalFit(array $property, array $context, array $profile): int
    {
        $type = strtolower((string) ($property['type'] ?? ''));
        $corridor = strtolower((string) ($property['corridor'] ?? ''));
        $typeScores = is_array($profile['typeScores'] ?? null) ? $profile['typeScores'] : [];
        $corridorScores = is_array($profile['corridorScores'] ?? null) ? $profile['corridorScores'] : [];
        $intentMatch = (int) round(
            (($typeScores[$type] ?? $typeScores['*'] ?? 70) * 0.60)
            + (($corridorScores[$corridor] ?? $corridorScores['*'] ?? 72) * 0.40)
        );

        $budgetAnchor = max(1, (int) ($profile['budgetAnchor'] ?? 70000000));
        $price = max(1, (int) ($property['price'] ?? 0));
        $budgetFit = $price <= $budgetAnchor
            ? 100
            : $this->clamp((int) round(100 - ((($price - $budgetAnchor) / $budgetAnchor) * 75)));

        $ideal = is_array($profile['areaIdeal'] ?? null) ? $profile['areaIdeal'] : ['min' => 2.0, 'max' => 10.0];
        $area = (float) ($property['area'] ?? 0.0);
        if ($area >= (float) ($ideal['min'] ?? 2.0) && $area <= (float) ($ideal['max'] ?? 10.0)) {
            $sizeFit = 100;
        } elseif ($area < (float) ($ideal['min'] ?? 2.0)) {
            $sizeFit = $this->clamp((int) round(70 - (((float) ($ideal['min'] ?? 2.0) - $area) * 12)));
        } else {
            $sizeFit = $this->clamp((int) round(88 - (($area - (float) ($ideal['max'] ?? 10.0)) * 6)));
        }

        $uncertainty = 100 - $this->clamp((int) round((((int) $context['freshnessScore']) + $this->scoreReadiness($property) + $this->scoreRisk($property, $context)) / 3));
        $targetUncertainty = (int) ($profile['uncertaintyTarget'] ?? 28);
        $riskHorizonFit = $this->clamp((int) round(100 - (abs($uncertainty - $targetUncertainty) * 2)));

        return $this->clamp((int) round(
            ($intentMatch * 0.35)
            + ($budgetFit * 0.25)
            + ($sizeFit * 0.20)
            + ($riskHorizonFit * 0.20)
        ));
    }

    private function scoreConfidence(array $property, array $context): int
    {
        $listingVerification = match (strtolower((string) ($property['listingVerificationStatus'] ?? 'unverified'))) {
            'verified', 'approved' => 100,
            'reviewing', 'partially_verified', 'pending_review' => 72,
            default => 34,
        };
        $sellerVerification = match (strtolower((string) ($property['sellerIdentityStatus'] ?? 'unverified'))) {
            'verified' => 100,
            'pending' => 64,
            default => 32,
        };
        $verification = $this->clamp((int) round(
            ($listingVerification * 0.55)
            + ($sellerVerification * 0.45)
        ));

        $groundTruth = match ((string) $context['visitStatus']) {
            'visited' => (bool) $context['fieldAuditComplete'] ? 100 : 82,
            'in_progress' => 72,
            'confirmed' => 62,
            default => ((int) $context['groundTruthVisitCount'] > 0 ? 74 : 28),
        };
        if ((float) $context['groundTruthMultiplier'] > 1.04) {
            $groundTruth = $this->clamp($groundTruth + 4);
        }

        return $this->clamp((int) round(
            ((int) $context['dataCompleteness'] * 0.35)
            + ($verification * 0.30)
            + ($groundTruth * 0.20)
            + ((int) $context['freshnessScore'] * 0.15)
        ));
    }

    private function classify(array $property, array $context, int $score, int $confidence, array $profile): array
    {
        $criticalBlockers = (array) ($context['criticalBlockers'] ?? []);
        $status = strtolower((string) ($property['status'] ?? 'available'));
        $availableToTransact = !in_array($status, ['reserved'], true);
        $buyConfidence = (int) ($profile['buyConfidence'] ?? 72);

        if ($score < 58) {
            return ['low_priority', 'Low Priority', 'Low Queue Priority'];
        }

        if ($criticalBlockers !== [] && $score >= 65) {
            return ['needs_verification', 'Needs Verification', 'Verification Gate'];
        }

        if ($score >= 82 && $confidence >= $buyConfidence && $availableToTransact) {
            return ['buy_now', 'Buy Now', 'Prime Move'];
        }

        if ($score >= 74) {
            return ['strong_watch', 'Strong Watch', 'Momentum Watch'];
        }

        if ($score >= 58 && ($this->scoreLocation($property) >= 76 || $this->scoreDemand($context) >= 62)) {
            return ['speculative', 'Speculative', 'Frontier Bet'];
        }

        return ['needs_verification', 'Needs Verification', 'Verification Gate'];
    }

    private function nextAction(array $property, array $context): array
    {
        $approvalState = strtolower((string) ($property['approvalState'] ?? 'approved'));
        if ($approvalState !== 'approved') {
            return [
                'label' => 'Resolve approval state',
                'target' => 'trust',
                'summary' => 'The listing still needs admin clearance before conviction can fully unlock.',
            ];
        }

        if ((int) $context['openDocumentRequestCount'] > 0 || (int) ($property['documentCompletenessPct'] ?? 0) < 100) {
            return [
                'label' => 'Request or clear missing documents',
                'target' => 'documents',
                'summary' => 'The document packet is still suppressing confidence and readiness.',
            ];
        }

        if ((int) ($property['dueDiligencePct'] ?? 0) < 100) {
            return [
                'label' => 'Close the diligence checklist',
                'target' => 'due-diligence',
                'summary' => 'A partially open diligence stack is still limiting certainty.',
            ];
        }

        if ((int) $context['groundTruthVisitCount'] < 1 || in_array((string) $context['visitStatus'], ['proposed', 'confirmed', 'counter_offered', 'unscheduled'], true)) {
            return [
                'label' => 'Book site visit',
                'target' => 'visits',
                'summary' => 'Ground-truth validation is the cleanest next move from browsing to action.',
            ];
        }

        if ((int) $context['messageCount'] < 1) {
            return [
                'label' => 'Open direct thread',
                'target' => 'messaging',
                'summary' => 'There is still no investor-seller trail attached to this opportunity.',
            ];
        }

        return [
            'label' => 'Advance to negotiation',
            'target' => 'messaging',
            'summary' => 'The proof stack is good enough to move into structured commercial discussion.',
        ];
    }

    private function reasons(array $components, array $property, array $context, array $nextAction): array
    {
        $items = [];

        if ((int) $components['financial'] >= 74) {
            $items[] = 'Pricing posture still looks attractive against visible benchmarks.';
        }
        if ((int) $components['demand'] >= 62) {
            $items[] = 'Demand pulse and inquiry activity suggest this location is drawing attention.';
        }
        if ((int) $components['readiness'] >= 70) {
            $items[] = 'Readiness and diligence posture are strong enough to support a real move.';
        }
        if ((int) $components['location'] >= 76) {
            $items[] = 'Corridor fit, access, and utility context support the site thesis.';
        }
        if ((int) $components['risk'] >= 72) {
            $items[] = 'Trust, freshness, and field validation are supporting conviction.';
        }
        if ((int) $components['personal'] >= 72) {
            $items[] = 'This opportunity fits the selected investor persona better than a generic ranking would suggest.';
        }
        if ((int) $context['freshnessScore'] < 45) {
            $items[] = 'Availability confirmation is getting stale and is capping confidence.';
        }
        if ((int) $context['openDocumentRequestCount'] > 0) {
            $items[] = 'Open document workflow items are still holding the proof stack back.';
        }
        if ((int) $context['groundTruthVisitCount'] < 1) {
            $items[] = 'Ground-truth validation has not been completed yet.';
        }
        if (strtolower((string) ($property['approvalState'] ?? 'approved')) !== 'approved') {
            $items[] = 'Approval workflow is still unresolved.';
        }

        $items[] = (string) ($nextAction['summary'] ?? 'The next action is still open.');

        return array_values(array_slice(array_unique($items), 0, 3));
    }

    private function summaryLine(string $statusKey, int $score, int $confidence, array $nextAction): string
    {
        $action = (string) ($nextAction['label'] ?? 'review');

        return match ($statusKey) {
            'buy_now' => sprintf('Clear decision signal at %d with %d%% confidence. The next smart move is to %s.', $score, $confidence, strtolower($action)),
            'strong_watch' => sprintf('Momentum is visible, but one more operating step should raise conviction. The next move is to %s.', strtolower($action)),
            'needs_verification' => sprintf('The opportunity is interesting, but confidence is not yet strong enough. Start by %s.', strtolower($action)),
            'speculative' => sprintf('Upside is visible, but this still behaves like a frontier bet. Start by %s.', strtolower($action)),
            default => sprintf('Current proof and fit do not justify queue priority. If revisited, begin by %s.', strtolower($action)),
        };
    }

    private function weightedScore(array $components, array $weights): int
    {
        $totalWeight = array_sum(array_map('intval', $weights));
        if ($totalWeight <= 0) {
            return 0;
        }

        $weighted = 0.0;
        foreach ($components as $key => $score) {
            $weighted += (float) $score * ((int) ($weights[$key] ?? 0));
        }

        return $this->clamp((int) round($weighted / $totalWeight));
    }

    private function confidenceLabel(int $confidence): string
    {
        return match (true) {
            $confidence >= 80 => 'High Confidence',
            $confidence >= 65 => 'Medium Confidence',
            default => 'Low Confidence',
        };
    }

    private function voteSummaryFromVotes(mixed $votes): array
    {
        if (!is_array($votes)) {
            return [
                'totalVotes' => 0,
                'topNeed' => null,
                'dominantShare' => 0.0,
            ];
        }

        $normalized = [];
        foreach ($votes as $label => $count) {
            $normalized[(string) $label] = (int) $count;
        }
        arsort($normalized);

        $totalVotes = array_sum($normalized);
        $topNeed = $normalized !== [] ? array_key_first($normalized) : null;
        $topVotes = $topNeed !== null ? (int) ($normalized[$topNeed] ?? 0) : 0;

        return [
            'totalVotes' => $totalVotes,
            'topNeed' => $topNeed,
            'dominantShare' => $totalVotes > 0 ? round($topVotes / $totalVotes, 4) : 0.0,
        ];
    }

    private function normalizePersona(?string $persona): string
    {
        $normalized = strtolower(trim((string) $persona));
        return array_key_exists($normalized, self::PERSONAS) ? $normalized : self::DEFAULT_PERSONA;
    }

    private function freshnessScore(?int $daysSince): int
    {
        if ($daysSince === null) {
            return 35;
        }

        return match (true) {
            $daysSince <= 7 => 100,
            $daysSince <= 30 => 80,
            $daysSince <= 45 => 60,
            default => 35,
        };
    }

    private function daysSince(?string $timestamp): ?int
    {
        if ($timestamp === null) {
            return null;
        }

        $target = strtotime($timestamp);
        if ($target === false) {
            return null;
        }

        $seconds = time() - $target;
        if ($seconds < 0) {
            return 0;
        }

        return (int) floor($seconds / 86400);
    }

    private function clamp(int $value, int $min = 0, int $max = 100): int
    {
        return max($min, min($max, $value));
    }
}
