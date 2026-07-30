<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_handle(function (array $container): array {
    $method = request_method();
    $user = sfc_current_user();
    $propertyId = int_or_null($_GET['id'] ?? null);
    if ($propertyId === null || $propertyId < 1) {
        throw new InvalidArgumentException('A valid property id is required.');
    }

    if ($method === 'GET') {
        $property = $container['properties']->find($propertyId, $user);
        $property = $container['decisionEngine']->decorateProperty($property, [
                'voteSummary' => $container['votes']->summaryMap([$propertyId])[$propertyId] ?? [],
                'messageSummary' => $container['messages']->propertySummaryMap([$propertyId])[$propertyId] ?? [],
            ]);
        $property = $container['clup']->decorateProperty($property, string_or_null($_GET['investmentType'] ?? null));
        return ['property' => $property];
    }

    if ($method === 'PUT' || $method === 'PATCH') {
        if ($user === null || !in_array($user['role'] ?? 'guest', ['admin', 'seller'], true)) {
            return [403, ['error' => 'Only admin or seller accounts can update listings.']];
        }
        if (($user['role'] ?? null) === 'seller' && !$container['properties']->isOwnedBySeller($propertyId, (int) $user['id'])) {
            return [403, ['error' => 'Sellers can only update their own listings.']];
        }

        $beforeProperty = $container['properties']->find($propertyId, $user);
        $payload = read_request_input();
        $uploadedImagePath = store_uploaded_property_image($_FILES['image_file'] ?? null);
        if ($uploadedImagePath !== null) {
            $payload['image_path'] = $uploadedImagePath;
        }
        if (($user['role'] ?? null) === 'seller') {
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
            $payload['last_confirmed_available_at'] = $payload['last_confirmed_available_at'] ?? gmdate('Y-m-d H:i:s');
        } elseif (($user['role'] ?? null) === 'admin' && !isset($payload['seller_user_id'], $payload['sellerUserId'])) {
            $payload['seller_user_id'] = $container['properties']->find($propertyId, $user)['sellerUserId'] ?? $container['users']->defaultSellerId();
        }

        $property = $container['properties']->update($propertyId, $payload, $user);
        $sellerIdentityStatus = string_or_null($payload['seller_identity_verification_status'] ?? $payload['sellerIdentityVerificationStatus'] ?? null);
        if (($user['role'] ?? null) === 'admin' && $sellerIdentityStatus !== null && ($property['sellerUserId'] ?? null) !== null) {
            $container['users']->updateIdentityVerificationStatus((int) $property['sellerUserId'], $sellerIdentityStatus);
            $property = $container['properties']->find($propertyId, $user);
        }

        $container['line']->onListingUpdated($beforeProperty, $property, $user);

        $property = $container['decisionEngine']->decorateProperty($property, [
                'voteSummary' => $container['votes']->summaryMap([$propertyId])[$propertyId] ?? [],
                'messageSummary' => $container['messages']->propertySummaryMap([$propertyId])[$propertyId] ?? [],
            ]);
        $property = $container['clup']->decorateProperty($property);
        return ['property' => $property];
    }

    if ($method === 'DELETE') {
        if ($user === null || !in_array($user['role'] ?? 'guest', ['admin', 'seller'], true)) {
            return [403, ['error' => 'Only admin or seller accounts can delete listings.']];
        }
        if (($user['role'] ?? null) === 'seller' && !$container['properties']->isOwnedBySeller($propertyId, (int) $user['id'])) {
            return [403, ['error' => 'Sellers can only delete their own listings.']];
        }

        $container['properties']->delete($propertyId, $user);

        return [
            'propertyId' => $propertyId,
            'deleted' => true,
        ];
    }

    return [405, ['error' => 'Method not allowed.']];
});
