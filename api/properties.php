<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_handle(function (array $container): array {
    $method = request_method();
    $user = sfc_current_user();

    if ($method === 'GET') {
        $properties = $container['properties']->all($user);
        $propertyIds = array_values(array_filter(array_map(
            static fn (array $property): int => (int) ($property['id'] ?? 0),
            $properties
        )));
        return [
            'properties' => $container['decisionEngine']->decorateProperties($properties, [
                'voteSummaries' => $container['votes']->summaryMap($propertyIds),
                'messageSummaries' => $container['messages']->propertySummaryMap($propertyIds),
            ]),
        ];
    }

    if ($method === 'POST') {
        if ($user === null || !in_array($user['role'] ?? 'guest', ['admin', 'seller'], true)) {
            return [403, ['error' => 'Only admin or seller accounts can create listings.']];
        }

        $payload = read_request_input();
        $uploadedImagePath = store_uploaded_property_image($_FILES['image_file'] ?? null);
        if ($uploadedImagePath !== null) {
            $payload['image_path'] = $uploadedImagePath;
        }
        if (($user['role'] ?? null) === 'seller') {
            if (strtolower((string) ($user['identityVerificationStatus'] ?? 'unverified')) !== 'verified') {
                return [403, ['error' => 'Your seller account is still pending verification. Complete your seller profile and wait for admin approval before publishing listings.']];
            }

            unset(
                $payload['approval_state'],
                $payload['approvalState'],
                $payload['documents_reviewed'],
                $payload['documentsReviewed'],
                $payload['documents_reviewed_at'],
                $payload['documentsReviewedAt'],
                $payload['site_verified'],
                $payload['siteVerified'],
                $payload['site_verified_at'],
                $payload['siteVerifiedAt'],
                $payload['dist_to_road_km'],
                $payload['distToRoadKm'],
                $payload['utility_status'],
                $payload['utilityStatus'],
                $payload['zoning_score'],
                $payload['zoningScore'],
                $payload['existing_land_use'],
                $payload['existingLandUse'],
                $payload['zoning_classification'],
                $payload['zoningClassification'],
                $payload['clup_allowed_uses'],
                $payload['clupAllowedUses'],
                $payload['clup_conditional_uses'],
                $payload['clupConditionalUses'],
                $payload['clup_restricted_uses'],
                $payload['clupRestrictedUses'],
                $payload['clup_source_reference'],
                $payload['clupSourceReference'],
                $payload['clup_verified'],
                $payload['clupVerified'],
                $payload['clup_verified_at'],
                $payload['clupVerifiedAt'],
                $payload['clup_profile'],
                $payload['clupProfile'],
                $payload['clup_compliance'],
                $payload['clupCompliance'],
                $payload['evidence_level'],
                $payload['evidenceLevel'],
                $payload['source_document_path'],
                $payload['sourceDocumentPath'],
                $payload['review_status'],
                $payload['reviewStatus'],
                $payload['dataset_id'],
                $payload['datasetId'],
                $payload['zone_id'],
                $payload['zoneId'],
                $payload['profile_id'],
                $payload['profileId'],
                $payload['assessed_value_sqm'],
                $payload['assessedValueSqm'],
                $payload['readiness_notes'],
                $payload['readinessNotes'],
                $payload['document_statuses'],
                $payload['documentStatuses'],
                $payload['seller_identity_verification_status'],
                $payload['sellerIdentityVerificationStatus']
            );
            $payload['seller_user_id'] = (int) $user['id'];
            $payload['owner_email'] = $payload['owner_email'] ?? $user['email'];
            $payload['owner_name'] = $payload['owner_name'] ?? $user['name'];
            $payload['approval_state'] = 'pending_review';
            $payload['last_confirmed_available_at'] = $payload['last_confirmed_available_at'] ?? gmdate('Y-m-d H:i:s');
        } elseif (!isset($payload['seller_user_id'], $payload['sellerUserId'])) {
            $payload['seller_user_id'] = $container['users']->defaultSellerId();
        }

        if (($user['role'] ?? null) === 'admin' && !isset($payload['last_confirmed_available_at'], $payload['lastConfirmedAvailableAt'])) {
            $payload['last_confirmed_available_at'] = gmdate('Y-m-d H:i:s');
        }

        $property = $container['properties']->create($payload, $user);
        $sellerIdentityStatus = string_or_null($payload['seller_identity_verification_status'] ?? $payload['sellerIdentityVerificationStatus'] ?? null);
        if (($user['role'] ?? null) === 'admin' && $sellerIdentityStatus !== null && ($property['sellerUserId'] ?? null) !== null) {
            $container['users']->updateIdentityVerificationStatus((int) $property['sellerUserId'], $sellerIdentityStatus);
            $property = $container['properties']->find((int) $property['id'], $user);
        }

        $container['line']->onListingCreated($property, $user);

        return [
            201,
            [
                'property' => $container['decisionEngine']->decorateProperty($property, [
                    'voteSummary' => $container['votes']->summaryMap([(int) ($property['id'] ?? 0)])[(int) ($property['id'] ?? 0)] ?? [],
                    'messageSummary' => $container['messages']->propertySummaryMap([(int) ($property['id'] ?? 0)])[(int) ($property['id'] ?? 0)] ?? [],
                ]),
            ],
        ];
    }

    return [405, ['error' => 'Method not allowed.']];
});
