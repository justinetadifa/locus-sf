<?php
declare(strict_types=1);

namespace App\Support;

use PDO;
use Throwable;

final class AutoSeeder
{
    public static function seedIfNeeded(PDO $pdo): void
    {
        $userIds = self::seedUsers($pdo);
        self::seedVoteOptions($pdo, $userIds['admin@sfcelerate.local'] ?? null);
        self::seedProperties($pdo, $userIds['seller@sfcelerate.local'] ?? null);
        self::seedShowcaseItems($pdo, $userIds['admin@sfcelerate.local'] ?? null);
        self::seedThreadsAndMessages($pdo, $userIds);
        self::seedVisitLogs($pdo, $userIds);
        self::seedDocumentRequests($pdo, $userIds);
        self::seedScenarios($pdo);
        self::seedAuditLogs($pdo, $userIds);
        self::assignDefaultSeller($pdo, $userIds['seller@sfcelerate.local'] ?? null);
    }

    private static function seedUsers(PDO $pdo): array
    {
        $defaults = [
            [
                'role' => 'admin',
                'name' => 'SFC Admin',
                'email' => 'admin@sfcelerate.local',
                'password' => 'Admin123!',
                'identity_status' => 'verified',
                'identity_verified_at' => '2026-03-15 09:30:00',
            ],
            [
                'role' => 'seller',
                'name' => 'Seller Studio',
                'email' => 'seller@sfcelerate.local',
                'password' => 'Seller123!',
                'identity_status' => 'verified',
                'identity_verified_at' => '2026-03-16 10:00:00',
            ],
            [
                'role' => 'investor',
                'name' => 'Investor Resident Hub',
                'email' => 'investor@sfcelerate.local',
                'password' => 'Investor123!',
                'identity_status' => 'unverified',
                'identity_verified_at' => null,
            ],
            [
                'role' => 'investor',
                'name' => 'Maria Santos',
                'email' => 'maria.santos@sfcelerate.local',
                'password' => 'Investor123!',
                'identity_status' => 'unverified',
                'identity_verified_at' => null,
            ],
        ];

        $select = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
        $insert = $pdo->prepare(
            'INSERT INTO users (role, name, email, password_hash, identity_verification_status, identity_verified_at)
             VALUES (:role, :name, :email, :password_hash, :identity_verification_status, :identity_verified_at)'
        );
        $updateVerification = $pdo->prepare(
            'UPDATE users
             SET identity_verification_status = :identity_verification_status,
                 identity_verified_at = :identity_verified_at
             WHERE id = :id
               AND (
                    identity_verification_status IS NULL
                    OR TRIM(identity_verification_status) = \'\'
                    OR LOWER(identity_verification_status) = \'unverified\'
               )'
        );

        $userIds = [];
        foreach ($defaults as $user) {
            $select->execute(['email' => $user['email']]);
            $existingId = $select->fetchColumn();
            if ($existingId) {
                $userIds[$user['email']] = (int) $existingId;
                $updateVerification->execute([
                    'id' => (int) $existingId,
                    'identity_verification_status' => $user['identity_status'],
                    'identity_verified_at' => $user['identity_verified_at'],
                ]);
                continue;
            }

            $insert->execute([
                'role' => $user['role'],
                'name' => $user['name'],
                'email' => strtolower($user['email']),
                'password_hash' => password_hash($user['password'], PASSWORD_DEFAULT),
                'identity_verification_status' => $user['identity_status'],
                'identity_verified_at' => $user['identity_verified_at'],
            ]);
            $userIds[$user['email']] = (int) $pdo->lastInsertId();
        }

        return $userIds;
    }

    private static function seedVoteOptions(PDO $pdo, ?int $adminUserId): void
    {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM vote_options')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $meta = JsonData::meta();
        $labels = $meta['votePresets'] ?? [];
        $imageMap = [
            '7/11' => 'assets/images/vote-7-11.svg',
            'PRINTING SHOP' => 'assets/images/vote-printing-shop.svg',
            'CAFE' => 'assets/images/vote-cafe.svg',
            'PHARMACY' => 'assets/images/vote-pharmacy.svg',
        ];

        $insert = $pdo->prepare(
            'INSERT INTO vote_options (title, slug, description, image_url, is_active, sort_order, created_by_user_id)
             VALUES (:title, :slug, :description, :image_url, 1, :sort_order, :created_by_user_id)'
        );

        foreach (array_values($labels) as $index => $label) {
            $title = trim((string) $label);
            if ($title === '') {
                continue;
            }

            $insert->execute([
                'title' => $title,
                'slug' => self::slug($title),
                'description' => sprintf('Investor voting option for %s.', $title),
                'image_url' => $imageMap[strtoupper($title)] ?? null,
                'sort_order' => $index + 1,
                'created_by_user_id' => $adminUserId,
            ]);
        }
    }

    private static function seedProperties(PDO $pdo, ?int $defaultSellerId): void
    {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $properties = JsonData::properties();
        $insertProperty = $pdo->prepare(
            'INSERT INTO properties (
                id, name, city, lat, lng, area, price, price_per_sqm, status, approval_state, score, type, corridor,
                tags_json, facilities_json, road_access, image_url, description, barangay, owner_contact_json,
                documents_json, seller_user_id, documents_reviewed_at, site_verified_at, last_confirmed_available_at,
                dist_to_road_km, utility_status, zoning_score, assessed_value_sqm, readiness_notes
            ) VALUES (
                :id, :name, :city, :lat, :lng, :area, :price, :price_per_sqm, :status, :approval_state, :score, :type, :corridor,
                :tags_json, :facilities_json, :road_access, :image_url, :description, :barangay, :owner_contact_json,
                :documents_json, :seller_user_id, :documents_reviewed_at, :site_verified_at, :last_confirmed_available_at,
                :dist_to_road_km, :utility_status, :zoning_score, :assessed_value_sqm, :readiness_notes
            )'
        );
        $insertMedia = $pdo->prepare(
            'INSERT INTO property_media (property_id, kind, source, alt_text, sort_order)
             VALUES (:property_id, :kind, :source, :alt_text, :sort_order)'
        );
        $insertDueDiligence = $pdo->prepare(
            'INSERT INTO property_due_diligence (property_id, state_json) VALUES (:property_id, :state_json)'
        );

        $pdo->beginTransaction();

        try {
            foreach ($properties as $property) {
                $trustProfile = self::trustProfileForProperty((int) $property['id']);
                $readinessProfile = self::readinessProfileForProperty((int) $property['id'], $property);
                $insertProperty->execute([
                    'id' => (int) $property['id'],
                    'name' => (string) $property['name'],
                    'city' => (string) ($property['city'] ?? 'San Fernando, La Union'),
                    'lat' => (float) $property['lat'],
                    'lng' => (float) $property['lng'],
                    'area' => (float) $property['area'],
                    'price' => (int) $property['price'],
                    'price_per_sqm' => self::pricePerSqmForProperty($property),
                    'status' => (string) $property['status'],
                    'approval_state' => (string) ($trustProfile['approvalState'] ?? 'approved'),
                    'score' => self::defaultScore($property),
                    'type' => (string) $property['type'],
                    'corridor' => (string) $property['corridor'],
                    'tags_json' => json_encode($property['tags'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'facilities_json' => json_encode($property['facilities'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'road_access' => (int) $property['roadAccess'],
                    'image_url' => (string) $property['imageUrl'],
                    'description' => (string) $property['description'],
                    'barangay' => $property['barangay'] ?: null,
                    'owner_contact_json' => json_encode($property['ownerContact'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'documents_json' => json_encode($trustProfile['documents'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'seller_user_id' => $defaultSellerId,
                    'documents_reviewed_at' => $trustProfile['documentsReviewedAt'],
                    'site_verified_at' => $trustProfile['siteVerifiedAt'],
                    'last_confirmed_available_at' => $trustProfile['lastConfirmedAvailableAt'],
                    'dist_to_road_km' => $readinessProfile['distToRoadKm'],
                    'utility_status' => $readinessProfile['utilityStatus'],
                    'zoning_score' => $readinessProfile['zoningScore'],
                    'assessed_value_sqm' => $readinessProfile['assessedValueSqm'],
                    'readiness_notes' => $readinessProfile['readinessNotes'],
                ]);

                foreach (($property['media'] ?? []) as $media) {
                    $insertMedia->execute([
                        'property_id' => (int) $property['id'],
                        'kind' => (string) ($media['kind'] ?? 'image'),
                        'source' => (string) $media['source'],
                        'alt_text' => (string) ($media['altText'] ?? $property['name']),
                        'sort_order' => (int) ($media['sortOrder'] ?? 0),
                    ]);
                }

                $insertDueDiligence->execute([
                    'property_id' => (int) $property['id'],
                    'state_json' => json_encode(
                        self::dueDiligenceStateForProperty((int) $property['id']),
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    ),
                ]);
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    private static function seedShowcaseItems(PDO $pdo, ?int $adminUserId): void
    {
        $insert = $pdo->prepare(
            'INSERT INTO showcase_items (
                feature_type, title, slug, partner_label, summary, description, category, location_label, barangay,
                status, pipeline_mode, supply_signal, cover_image_url, primary_metric_label, primary_metric_value, secondary_metric_label, secondary_metric_value,
                investor_thesis, ideal_operator, avoidance_note,
                countdown_at, completion_target, related_property_id, is_published, is_featured, sort_order, created_by_user_id
            ) VALUES (
                :feature_type, :title, :slug, :partner_label, :summary, :description, :category, :location_label, :barangay,
                :status, :pipeline_mode, :supply_signal, :cover_image_url, :primary_metric_label, :primary_metric_value, :secondary_metric_label, :secondary_metric_value,
                :investor_thesis, :ideal_operator, :avoidance_note,
                :countdown_at, :completion_target, :related_property_id, :is_published, :is_featured, :sort_order, :created_by_user_id
            )'
        );
        $exists = $pdo->prepare('SELECT id FROM showcase_items WHERE slug = :slug LIMIT 1');

        $items = [
            [
                'feature_type' => 'offer_board',
                'title' => 'Poro Point Logistics Release',
                'slug' => 'poro-point-logistics-release',
                'partner_label' => 'City Investment Desk',
                'summary' => 'A corridor-facing logistics property prepared for admin-curated offer visibility.',
                'description' => 'Built for investors scanning timed opportunities near Poro Point, with clear frontage, strong access, and a disciplined release window.',
                'category' => 'Logistics',
                'location_label' => 'Poro Point, San Fernando',
                'barangay' => 'Poro',
                'status' => 'open',
                'pipeline_mode' => null,
                'supply_signal' => null,
                'cover_image_url' => 'assets/images/FabroBldg.png',
                'primary_metric_label' => 'Offer window',
                'primary_metric_value' => 'Closes soon',
                'secondary_metric_label' => 'Current offer',
                'secondary_metric_value' => 'PHP 78.2M',
                'investor_thesis' => null,
                'ideal_operator' => null,
                'avoidance_note' => null,
                'countdown_at' => '2026-04-12 18:00:00',
                'completion_target' => null,
                'related_property_id' => 1,
                'is_published' => 1,
                'is_featured' => 1,
                'sort_order' => 1,
                'created_by_user_id' => $adminUserId,
            ],
            [
                'feature_type' => 'offer_board',
                'title' => 'Downtown Commerce Offer Window',
                'slug' => 'downtown-commerce-offer-window',
                'partner_label' => 'Urban Portfolio Team',
                'summary' => 'A central-city commercial release staged for short-form offer tracking.',
                'description' => 'Designed for investors who want a cleaner read on timing, category, and current indicative pricing without opening the full listing stack first.',
                'category' => 'Commercial',
                'location_label' => 'City Center, San Fernando',
                'barangay' => 'Poblacion',
                'status' => 'closing_soon',
                'pipeline_mode' => null,
                'supply_signal' => null,
                'cover_image_url' => 'assets/images/LaFinns.png',
                'primary_metric_label' => 'Offer window',
                'primary_metric_value' => '48 hours left',
                'secondary_metric_label' => 'Indicative offer',
                'secondary_metric_value' => 'PHP 64.5M',
                'investor_thesis' => null,
                'ideal_operator' => null,
                'avoidance_note' => null,
                'countdown_at' => '2026-04-08 16:30:00',
                'completion_target' => null,
                'related_property_id' => 3,
                'is_published' => 1,
                'is_featured' => 0,
                'sort_order' => 2,
                'created_by_user_id' => $adminUserId,
            ],
            [
                'feature_type' => 'offer_board',
                'title' => 'Civic Belt Mixed-Use Offer Board',
                'slug' => 'civic-belt-mixed-use-offer-board',
                'partner_label' => 'Growth Sites Office',
                'summary' => 'A mixed-use opportunity surfaced through a softer, image-led offer board treatment.',
                'description' => 'Positioned for investors who want a polished first-pass card before stepping into the property dossier or compare workflow.',
                'category' => 'Mixed Use',
                'location_label' => 'Civic Belt, San Fernando',
                'barangay' => 'Catbangen',
                'status' => 'open',
                'pipeline_mode' => null,
                'supply_signal' => null,
                'cover_image_url' => 'assets/images/FerarenProperty.png',
                'primary_metric_label' => 'Offer window',
                'primary_metric_value' => 'Accepting briefs',
                'secondary_metric_label' => 'Target value',
                'secondary_metric_value' => 'PHP 91.0M',
                'investor_thesis' => null,
                'ideal_operator' => null,
                'avoidance_note' => null,
                'countdown_at' => '2026-04-18 12:00:00',
                'completion_target' => null,
                'related_property_id' => 7,
                'is_published' => 1,
                'is_featured' => 0,
                'sort_order' => 3,
                'created_by_user_id' => $adminUserId,
            ],
            [
                'feature_type' => 'city_pipeline',
                'title' => 'North Gateway Retail Commons',
                'slug' => 'north-gateway-retail-commons',
                'partner_label' => 'City Pipeline',
                'summary' => 'A future retail establishment cluster still moving through the city pipeline.',
                'description' => 'This entry represents an admin-curated future commercial node, useful for showing what is not yet built but already legible in the city story.',
                'category' => 'Retail',
                'location_label' => 'North Gateway, San Fernando',
                'barangay' => 'Biday',
                'status' => 'planned',
                'pipeline_mode' => 'future_project',
                'supply_signal' => null,
                'cover_image_url' => 'assets/images/Property3.png',
                'primary_metric_label' => 'Expected launch',
                'primary_metric_value' => 'Q4 2026',
                'secondary_metric_label' => 'Development stage',
                'secondary_metric_value' => 'Planned',
                'investor_thesis' => null,
                'ideal_operator' => null,
                'avoidance_note' => null,
                'countdown_at' => null,
                'completion_target' => '2026-11-20 09:00:00',
                'related_property_id' => null,
                'is_published' => 1,
                'is_featured' => 1,
                'sort_order' => 1,
                'created_by_user_id' => $adminUserId,
            ],
            [
                'feature_type' => 'city_pipeline',
                'title' => 'Coastal Suites and Events Hall',
                'slug' => 'coastal-suites-and-events-hall',
                'partner_label' => 'Development Watch',
                'summary' => 'A tourism-oriented pipeline entry for a not-yet-built hospitality project.',
                'description' => 'Presented as a premium future-facing card so users can explore developments still under preparation without mixing them into the live inventory.',
                'category' => 'Hospitality',
                'location_label' => 'Coastal Belt, San Fernando',
                'barangay' => 'Canaoay',
                'status' => 'approved',
                'pipeline_mode' => 'future_project',
                'supply_signal' => null,
                'cover_image_url' => 'assets/images/Property8.png',
                'primary_metric_label' => 'Expected opening',
                'primary_metric_value' => 'Early 2027',
                'secondary_metric_label' => 'Development stage',
                'secondary_metric_value' => 'Approved',
                'investor_thesis' => null,
                'ideal_operator' => null,
                'avoidance_note' => null,
                'countdown_at' => null,
                'completion_target' => '2027-02-15 10:00:00',
                'related_property_id' => null,
                'is_published' => 1,
                'is_featured' => 0,
                'sort_order' => 2,
                'created_by_user_id' => $adminUserId,
            ],
            [
                'feature_type' => 'city_pipeline',
                'title' => 'Innovation Yard Business Park',
                'slug' => 'innovation-yard-business-park',
                'partner_label' => 'Launch Monitor',
                'summary' => 'An office and light-industry pipeline project currently under construction.',
                'description' => 'Ideal for users who want a forward-looking development board showing what is coming next, without confusing it with active sell-side property inventory.',
                'category' => 'Business Park',
                'location_label' => 'Highway Spine, San Fernando',
                'barangay' => 'Sibuan-Otong',
                'status' => 'under_construction',
                'pipeline_mode' => 'future_project',
                'supply_signal' => null,
                'cover_image_url' => 'assets/images/Property10.png',
                'primary_metric_label' => 'Expected delivery',
                'primary_metric_value' => 'Mid 2027',
                'secondary_metric_label' => 'Development stage',
                'secondary_metric_value' => 'Under Construction',
                'investor_thesis' => null,
                'ideal_operator' => null,
                'avoidance_note' => null,
                'countdown_at' => null,
                'completion_target' => '2027-06-30 09:00:00',
                'related_property_id' => null,
                'is_published' => 1,
                'is_featured' => 0,
                'sort_order' => 3,
                'created_by_user_id' => $adminUserId,
            ],
            [
                'feature_type' => 'city_pipeline',
                'title' => 'Diagnostic Imaging and Specialty Clinic Hub',
                'slug' => 'diagnostic-imaging-and-specialty-clinic-hub',
                'partner_label' => 'City Gap Board',
                'summary' => 'A white-space healthcare establishment concept for operators tracking unmet or undersupplied city demand.',
                'description' => 'Use this as an investor brief for a business the city wants to attract, with operator guidance and duplicate-build caution visible up front.',
                'category' => 'Healthcare',
                'location_label' => 'City Core, San Fernando',
                'barangay' => 'Poblacion',
                'status' => 'priority',
                'pipeline_mode' => 'investment_gap',
                'supply_signal' => 'not_present',
                'cover_image_url' => 'assets/images/Property6.png',
                'primary_metric_label' => 'Gap level',
                'primary_metric_value' => 'High-priority need',
                'secondary_metric_label' => 'Tracked supply',
                'secondary_metric_value' => 'Not in tracked supply',
                'investor_thesis' => 'Residents currently leave the core commercial loop for higher-order diagnostics and specialist visits, making this a strong first-mover service opportunity.',
                'ideal_operator' => 'Healthcare group or diagnostic brand with outpatient capability',
                'avoidance_note' => 'Position as a specialist diagnostic anchor, not a generic clinic strip.',
                'countdown_at' => null,
                'completion_target' => null,
                'related_property_id' => null,
                'is_published' => 1,
                'is_featured' => 1,
                'sort_order' => 4,
                'created_by_user_id' => $adminUserId,
            ],
            [
                'feature_type' => 'city_pipeline',
                'title' => 'Cold Chain Food Hub and Distribution Center',
                'slug' => 'cold-chain-food-hub-and-distribution-center',
                'partner_label' => 'Investor Whitespace',
                'summary' => 'A logistics-oriented business gap for operators studying food distribution and cold-storage demand in San Fernando.',
                'description' => 'This concept helps the admin team signal that the city is looking for higher-value supply-chain infrastructure, not just another standard warehouse box.',
                'category' => 'Logistics',
                'location_label' => 'Highway Spine, San Fernando',
                'barangay' => 'Sibuan-Otong',
                'status' => 'validation',
                'pipeline_mode' => 'investment_gap',
                'supply_signal' => 'under_supplied',
                'cover_image_url' => 'assets/images/FabroBldg.png',
                'primary_metric_label' => 'Gap level',
                'primary_metric_value' => 'Expansion opportunity',
                'secondary_metric_label' => 'Tracked supply',
                'secondary_metric_value' => 'Under-supplied',
                'investor_thesis' => 'A cold-chain node would support food, retail, and hospitality operators while differentiating itself from generic storage supply.',
                'ideal_operator' => 'Cold-storage operator, food distributor, or integrated logistics tenant',
                'avoidance_note' => 'Avoid presenting this as another general warehouse play without refrigeration capability.',
                'countdown_at' => null,
                'completion_target' => null,
                'related_property_id' => null,
                'is_published' => 1,
                'is_featured' => 0,
                'sort_order' => 5,
                'created_by_user_id' => $adminUserId,
            ],
            [
                'feature_type' => 'city_pipeline',
                'title' => 'Family Entertainment and Indoor Activity Center',
                'slug' => 'family-entertainment-and-indoor-activity-center',
                'partner_label' => 'City Gap Board',
                'summary' => 'A consumer-facing establishment brief to help investors avoid overbuilding standard retail and instead fill a clearer leisure gap.',
                'description' => 'This is a city-pipeline strategy card for leisure operators who want to study family dwell time, weekend traffic, and differentiated entertainment programming.',
                'category' => 'Leisure',
                'location_label' => 'North Gateway, San Fernando',
                'barangay' => 'Biday',
                'status' => 'watchlist',
                'pipeline_mode' => 'investment_gap',
                'supply_signal' => 'under_supplied',
                'cover_image_url' => 'assets/images/Property4.png',
                'primary_metric_label' => 'Gap level',
                'primary_metric_value' => 'Monitor for launch',
                'secondary_metric_label' => 'Tracked supply',
                'secondary_metric_value' => 'Under-supplied',
                'investor_thesis' => 'An indoor family attraction can capture weekend spend without duplicating the city’s standard food-and-retail mix.',
                'ideal_operator' => 'Entertainment operator, edutainment brand, or family leisure developer',
                'avoidance_note' => 'Do not reduce this to a basic arcade; anchor it with destination programming.',
                'countdown_at' => null,
                'completion_target' => null,
                'related_property_id' => null,
                'is_published' => 1,
                'is_featured' => 0,
                'sort_order' => 6,
                'created_by_user_id' => $adminUserId,
            ],
        ];

        foreach ($items as $item) {
            $exists->execute(['slug' => $item['slug']]);
            if ($exists->fetchColumn()) {
                continue;
            }
            $insert->execute($item);
        }
    }

    private static function seedDocumentRequests(PDO $pdo, array $userIds): void
    {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM property_document_requests')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $sellerUserId = $userIds['seller@sfcelerate.local'] ?? null;
        $investorUserId = $userIds['maria.santos@sfcelerate.local'] ?? ($userIds['investor@sfcelerate.local'] ?? null);
        $adminUserId = $userIds['admin@sfcelerate.local'] ?? null;
        if ($sellerUserId === null || $investorUserId === null) {
            return;
        }

        $requests = [
            [
                'property_id' => 1,
                'requester_user_id' => $investorUserId,
                'seller_user_id' => $sellerUserId,
                'requester_name' => 'Maria Santos',
                'requester_role' => 'investor',
                'document_name' => 'Certified true copy of title',
                'note' => 'Please share the latest annotated title copy and any lien disclosures.',
                'status' => 'fulfilled',
                'response_note' => 'Title copy and supporting annotation notes were prepared for the next review call.',
                'resolved_at' => '2026-03-24 15:30:00',
            ],
            [
                'property_id' => 1,
                'requester_user_id' => $investorUserId,
                'seller_user_id' => $sellerUserId,
                'requester_name' => 'Maria Santos',
                'requester_role' => 'investor',
                'document_name' => 'Survey plan with road right-of-way',
                'note' => 'Need the current survey and right-of-way sketch before site visit.',
                'status' => 'in_review',
                'response_note' => 'Survey plan is being cross-checked with the municipal engineer.',
                'resolved_at' => null,
            ],
            [
                'property_id' => 2,
                'requester_user_id' => $adminUserId,
                'seller_user_id' => $sellerUserId,
                'requester_name' => 'SFC Admin',
                'requester_role' => 'admin',
                'document_name' => 'Hazard and flood screening report',
                'note' => 'Required before the listing can be marked fully reviewed for tourism investors.',
                'status' => 'requested',
                'response_note' => null,
                'resolved_at' => null,
            ],
        ];

        $insert = $pdo->prepare(
            'INSERT INTO property_document_requests (
                property_id, requester_user_id, seller_user_id, requester_name, requester_role,
                document_name, note, status, response_note, resolved_at
             ) VALUES (
                :property_id, :requester_user_id, :seller_user_id, :requester_name, :requester_role,
                :document_name, :note, :status, :response_note, :resolved_at
             )'
        );

        foreach ($requests as $request) {
            $insert->execute($request);
        }
    }

    private static function seedThreadsAndMessages(PDO $pdo, array $userIds): void
    {
        $threadCount = (int) $pdo->query('SELECT COUNT(*) FROM message_threads')->fetchColumn();
        if ($threadCount > 0) {
            return;
        }

        $samples = JsonData::sampleData();
        $defaultSellerId = $userIds['seller@sfcelerate.local'] ?? null;
        $defaultInvestorId = $userIds['maria.santos@sfcelerate.local'] ?? ($userIds['investor@sfcelerate.local'] ?? null);
        $defaultAdminId = $userIds['admin@sfcelerate.local'] ?? null;

        if ($defaultSellerId === null || $defaultInvestorId === null) {
            return;
        }

        $insertThread = $pdo->prepare(
            'INSERT INTO message_threads (property_id, investor_user_id, seller_user_id, subject, last_message_at)
             VALUES (:property_id, :investor_user_id, :seller_user_id, :subject, CURRENT_TIMESTAMP)'
        );
        $insertMessage = $pdo->prepare(
            'INSERT INTO property_messages (
                thread_id, property_id, sender_user_id, recipient_user_id, sender_name, role, text
             ) VALUES (
                :thread_id, :property_id, :sender_user_id, :recipient_user_id, :sender_name, :role, :text
             )'
        );
        $updateThread = $pdo->prepare(
            'UPDATE message_threads SET last_message_at = CURRENT_TIMESTAMP WHERE id = :id'
        );

        $pdo->beginTransaction();

        try {
            foreach (($samples['conversations'] ?? []) as $conversation) {
                $propertyId = (int) ($conversation['propertyId'] ?? 0);
                if ($propertyId < 1) {
                    continue;
                }

                $insertThread->execute([
                    'property_id' => $propertyId,
                    'investor_user_id' => $defaultInvestorId,
                    'seller_user_id' => $defaultSellerId,
                    'subject' => (string) ($conversation['title'] ?? 'Property conversation'),
                ]);

                $threadId = (int) $pdo->lastInsertId();
                foreach (($conversation['messages'] ?? []) as $message) {
                    $role = strtolower((string) ($message['role'] ?? 'investor'));
                    $senderUserId = match ($role) {
                        'admin' => $defaultAdminId,
                        'owner', 'seller' => $defaultSellerId,
                        default => $defaultInvestorId,
                    };
                    $recipientUserId = match ($role) {
                        'admin' => null,
                        'owner', 'seller' => $defaultInvestorId,
                        default => $defaultSellerId,
                    };

                    $insertMessage->execute([
                        'thread_id' => $threadId,
                        'property_id' => $propertyId,
                        'sender_user_id' => $senderUserId,
                        'recipient_user_id' => $recipientUserId,
                        'sender_name' => (string) ($message['senderName'] ?? 'Platform User'),
                        'role' => $role === 'owner' ? 'seller' : $role,
                        'text' => (string) ($message['text'] ?? ''),
                    ]);
                }

                $updateThread->execute(['id' => $threadId]);
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    private static function seedScenarios(PDO $pdo): void
    {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM investment_scenarios')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $samples = JsonData::sampleData();
        $insertScenario = $pdo->prepare(
            'INSERT INTO investment_scenarios (
                property_id, name, created_by, budget, sector, size, weights_json, assumptions_json, results_json
            ) VALUES (
                :property_id, :name, :created_by, :budget, :sector, :size, :weights_json, :assumptions_json, :results_json
            )'
        );

        foreach (($samples['scenarios'] ?? []) as $scenario) {
            $insertScenario->execute([
                'property_id' => (int) $scenario['propertyId'],
                'name' => (string) $scenario['name'],
                'created_by' => (string) ($scenario['createdBy'] ?? 'Local Analyst'),
                'budget' => isset($scenario['budget']) ? (int) $scenario['budget'] : null,
                'sector' => $scenario['sector'] ?? null,
                'size' => isset($scenario['size']) ? (float) $scenario['size'] : null,
                'weights_json' => json_encode($scenario['weights'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'assumptions_json' => json_encode($scenario['assumptions'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'results_json' => json_encode($scenario['results'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    private static function seedVisitLogs(PDO $pdo, array $userIds): void
    {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM visit_logs')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $sellerUserId = $userIds['seller@sfcelerate.local'] ?? null;
        $investorUserId = $userIds['maria.santos@sfcelerate.local'] ?? ($userIds['investor@sfcelerate.local'] ?? null);
        if ($sellerUserId === null || $investorUserId === null) {
            return;
        }

        $threadLookup = $pdo->prepare(
            'SELECT id
             FROM message_threads
             WHERE property_id = :property_id
               AND investor_user_id = :investor_user_id
             LIMIT 1'
        );

        $threadLookup->execute([
            'property_id' => 1,
            'investor_user_id' => $investorUserId,
        ]);
        $propertyOneThreadId = int_or_null($threadLookup->fetchColumn());

        $threadLookup->execute([
            'property_id' => 2,
            'investor_user_id' => $investorUserId,
        ]);
        $propertyTwoThreadId = int_or_null($threadLookup->fetchColumn());

        if ($propertyOneThreadId === null && $propertyTwoThreadId === null) {
            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO visit_logs (
                property_id, thread_id, investor_user_id, seller_user_id, investment_purpose,
                primary_start_at, primary_end_at, secondary_start_at, secondary_end_at,
                counter_start_at, counter_end_at, confirmed_start_at, confirmed_end_at,
                started_at, visited_at, status, field_audit_json, ground_truth_multiplier, activity_json
            ) VALUES (
                :property_id, :thread_id, :investor_user_id, :seller_user_id, :investment_purpose,
                :primary_start_at, :primary_end_at, :secondary_start_at, :secondary_end_at,
                :counter_start_at, :counter_end_at, :confirmed_start_at, :confirmed_end_at,
                :started_at, :visited_at, :status, :field_audit_json, :ground_truth_multiplier, :activity_json
            )'
        );

        if ($propertyOneThreadId !== null) {
            $insert->execute([
                'property_id' => 1,
                'thread_id' => $propertyOneThreadId,
                'investor_user_id' => $investorUserId,
                'seller_user_id' => $sellerUserId,
                'investment_purpose' => 'University Campus',
                'primary_start_at' => '2026-04-12 09:00:00',
                'primary_end_at' => '2026-04-12 11:00:00',
                'secondary_start_at' => '2026-04-13 13:00:00',
                'secondary_end_at' => '2026-04-13 15:00:00',
                'counter_start_at' => null,
                'counter_end_at' => null,
                'confirmed_start_at' => '2026-04-12 09:00:00',
                'confirmed_end_at' => '2026-04-12 11:00:00',
                'started_at' => null,
                'visited_at' => null,
                'status' => 'confirmed',
                'field_audit_json' => null,
                'ground_truth_multiplier' => null,
                'activity_json' => json_encode([
                    [
                        'kind' => 'proposed',
                        'title' => 'Site Visit Proposed',
                        'summary' => 'Maria Santos proposed primary and secondary windows for a University Campus thesis.',
                        'actorRole' => 'investor',
                        'actorName' => 'Maria Santos',
                        'status' => 'proposed',
                        'createdAt' => '2026-04-01 09:05:00',
                        'primaryWindow' => [
                            'startAt' => '2026-04-12 09:00:00',
                            'endAt' => '2026-04-12 11:00:00',
                        ],
                        'secondaryWindow' => [
                            'startAt' => '2026-04-13 13:00:00',
                            'endAt' => '2026-04-13 15:00:00',
                        ],
                        'purpose' => 'University Campus',
                    ],
                    [
                        'kind' => 'confirmed',
                        'title' => 'Ground Truth Scheduled',
                        'summary' => 'Seller Studio confirmed the primary site visit window.',
                        'actorRole' => 'seller',
                        'actorName' => 'Seller Studio',
                        'status' => 'confirmed',
                        'createdAt' => '2026-04-01 10:15:00',
                        'confirmedWindow' => [
                            'startAt' => '2026-04-12 09:00:00',
                            'endAt' => '2026-04-12 11:00:00',
                        ],
                    ],
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        }

        if ($propertyTwoThreadId !== null) {
            $fieldAudit = [
                'neighborhood_vibe' => 4,
                'utility_proximity' => 4,
                'expansion_feasibility' => 5,
                'notes' => 'Visual check confirmed strong tourism frontage with expansion room beyond the current resort envelope.',
            ];

            $insert->execute([
                'property_id' => 2,
                'thread_id' => $propertyTwoThreadId,
                'investor_user_id' => $investorUserId,
                'seller_user_id' => $sellerUserId,
                'investment_purpose' => 'Resort Due Diligence',
                'primary_start_at' => '2026-03-21 09:00:00',
                'primary_end_at' => '2026-03-21 11:00:00',
                'secondary_start_at' => '2026-03-22 13:00:00',
                'secondary_end_at' => '2026-03-22 15:00:00',
                'counter_start_at' => null,
                'counter_end_at' => null,
                'confirmed_start_at' => '2026-03-22 13:00:00',
                'confirmed_end_at' => '2026-03-22 15:00:00',
                'started_at' => '2026-03-22 13:05:00',
                'visited_at' => '2026-03-22 15:20:00',
                'status' => 'visited',
                'field_audit_json' => json_encode($fieldAudit, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'ground_truth_multiplier' => 1.11,
                'activity_json' => json_encode([
                    [
                        'kind' => 'proposed',
                        'title' => 'Site Visit Proposed',
                        'summary' => 'Maria Santos proposed a resort-focused visit with backup windows.',
                        'actorRole' => 'investor',
                        'actorName' => 'Maria Santos',
                        'status' => 'proposed',
                        'createdAt' => '2026-03-18 09:10:00',
                        'primaryWindow' => [
                            'startAt' => '2026-03-21 09:00:00',
                            'endAt' => '2026-03-21 11:00:00',
                        ],
                        'secondaryWindow' => [
                            'startAt' => '2026-03-22 13:00:00',
                            'endAt' => '2026-03-22 15:00:00',
                        ],
                        'purpose' => 'Resort Due Diligence',
                    ],
                    [
                        'kind' => 'confirmed',
                        'title' => 'Ground Truth Scheduled',
                        'summary' => 'Seller Studio confirmed the secondary site visit window.',
                        'actorRole' => 'seller',
                        'actorName' => 'Seller Studio',
                        'status' => 'confirmed',
                        'createdAt' => '2026-03-18 10:40:00',
                        'confirmedWindow' => [
                            'startAt' => '2026-03-22 13:00:00',
                            'endAt' => '2026-03-22 15:00:00',
                        ],
                    ],
                    [
                        'kind' => 'in_progress',
                        'title' => 'Ground Truth In Motion',
                        'summary' => 'Seller Studio marked the walkthrough as underway.',
                        'actorRole' => 'seller',
                        'actorName' => 'Seller Studio',
                        'status' => 'in_progress',
                        'createdAt' => '2026-03-22 13:05:00',
                    ],
                    [
                        'kind' => 'visited',
                        'title' => 'Visit Completed',
                        'summary' => 'Seller Studio marked the field walkthrough complete.',
                        'actorRole' => 'seller',
                        'actorName' => 'Seller Studio',
                        'status' => 'visited',
                        'createdAt' => '2026-03-22 15:20:00',
                    ],
                    [
                        'kind' => 'field_audit',
                        'title' => 'Field Audit Submitted',
                        'summary' => 'Maria Santos submitted the ground-truth audit. IAI multiplier is now 1.11.',
                        'actorRole' => 'investor',
                        'actorName' => 'Maria Santos',
                        'status' => 'visited',
                        'createdAt' => '2026-03-22 16:00:00',
                        'fieldAudit' => $fieldAudit,
                        'groundTruthMultiplier' => 1.11,
                    ],
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    private static function seedAuditLogs(PDO $pdo, array $userIds): void
    {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $adminUserId = $userIds['admin@sfcelerate.local'] ?? null;
        $mariaUserId = $userIds['maria.santos@sfcelerate.local'] ?? ($userIds['investor@sfcelerate.local'] ?? null);
        if ($adminUserId === null) {
            return;
        }

        $threadId = (int) ($pdo->query('SELECT COALESCE(MIN(id), 1) FROM message_threads')->fetchColumn() ?: 1);
        $insert = $pdo->prepare(
            'INSERT INTO audit_logs (
                actor_id, action_type, entity_type, entity_id, metadata, created_at
            ) VALUES (
                :actor_id, :action_type, :entity_type, :entity_id, :metadata, :created_at
            )'
        );

        $entries = [
            [
                'actor_id' => $adminUserId,
                'action_type' => 'APPROVE',
                'entity_type' => 'PROPERTY',
                'entity_id' => 4,
                'metadata' => [
                    'eventType' => 'LISTING_APPROVAL',
                    'targetLabel' => 'PROP_ID: #SFLU-004',
                    'summary' => 'Approval state changed from Pending Review to Approved.',
                    'badge' => 'VERIFIED',
                    'streamGroup' => 'moderation',
                    'changedFields' => ['approvalState'],
                    'before' => ['approvalState' => 'pending_review', 'name' => 'Property 1 - Industrial Zone'],
                    'after' => ['approvalState' => 'approved', 'name' => 'Property 1 - Industrial Zone'],
                ],
                'created_at' => '2026-04-02 10:24:12',
            ],
            [
                'actor_id' => $adminUserId,
                'action_type' => 'EDIT',
                'entity_type' => 'PROPERTY',
                'entity_id' => 1,
                'metadata' => [
                    'eventType' => 'DATA_EDIT',
                    'targetLabel' => 'PROP_ID: #SFLU-001',
                    'summary' => 'Price Per Sqm changed from PHP 847 / sqm to PHP 882 / sqm.',
                    'streamGroup' => 'financials',
                    'changedFields' => ['pricePerSqm', 'price'],
                    'before' => ['price' => 72000000, 'pricePerSqm' => 847, 'name' => 'Fabro Building Prime Lot'],
                    'after' => ['price' => 75000000, 'pricePerSqm' => 882, 'name' => 'Fabro Building Prime Lot'],
                ],
                'created_at' => '2026-04-02 09:15:01',
            ],
            [
                'actor_id' => $adminUserId,
                'action_type' => 'DELETE',
                'entity_type' => 'MESSAGE',
                'entity_id' => $threadId,
                'metadata' => [
                    'eventType' => 'MSG_RESOLVE',
                    'targetLabel' => sprintf('THREAD: #%d', $threadId),
                    'summary' => 'Flagged inappropriate content and cleared the thread for review.',
                    'badge' => 'MODERATED',
                    'streamGroup' => 'moderation',
                    'changedFields' => ['messageCount'],
                    'before' => ['messageCount' => 3],
                    'after' => ['messageCount' => 0, 'messagesCleared' => 3],
                ],
                'created_at' => '2026-04-02 08:05:44',
            ],
            [
                'actor_id' => $mariaUserId,
                'action_type' => 'EDIT',
                'entity_type' => 'VOTE',
                'entity_id' => 2,
                'metadata' => [
                    'eventType' => 'VOTE_SIGNAL',
                    'targetLabel' => 'PROP_ID: #SFLU-002',
                    'summary' => 'Vote pulse moved to Warehouse Or Logistics for LaFinns Beach Resort Land.',
                    'streamGroup' => 'all',
                    'changedFields' => ['votes', 'selectedVoteOptionId'],
                    'before' => ['votes' => ['WAREHOUSE OR LOGISTICS' => 2]],
                    'after' => ['votes' => ['WAREHOUSE OR LOGISTICS' => 3], 'selectedVoteOptionId' => 8],
                ],
                'created_at' => '2026-04-01 17:18:22',
            ],
        ];

        foreach ($entries as $entry) {
            if ($entry['actor_id'] === null) {
                continue;
            }

            $insert->execute([
                'actor_id' => $entry['actor_id'],
                'action_type' => $entry['action_type'],
                'entity_type' => $entry['entity_type'],
                'entity_id' => $entry['entity_id'],
                'metadata' => json_encode($entry['metadata'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_at' => $entry['created_at'],
            ]);
        }
    }

    private static function assignDefaultSeller(PDO $pdo, ?int $sellerUserId): void
    {
        if ($sellerUserId === null) {
            return;
        }

        $statement = $pdo->prepare(
            'UPDATE properties
             SET seller_user_id = :seller_user_id
             WHERE seller_user_id IS NULL'
        );
        $statement->execute(['seller_user_id' => $sellerUserId]);
    }

    private static function defaultScore(array $property): int
    {
        $score = isset($property['score']) && is_numeric($property['score'])
            ? (int) $property['score']
            : null;

        if ($score !== null) {
            return max(40, min(100, $score));
        }

        $roadAccess = isset($property['roadAccess']) && is_numeric($property['roadAccess'])
            ? (int) $property['roadAccess']
            : 85;

        return max(68, min(96, (int) round(($roadAccess * 0.65) + 22)));
    }

    private static function slug(string $value): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($value))) ?? '';
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'vote-option';
    }

    private static function pricePerSqmForProperty(array $property): int
    {
        $price = (int) ($property['price'] ?? 0);
        $area = (float) ($property['area'] ?? 0);
        if ($price < 1 || $area <= 0) {
            return max(1, (int) ($property['pricePerSqm'] ?? 1));
        }

        return max(1, (int) round($price / max($area * 10000, 1)));
    }

    private static function readinessProfileForProperty(int $propertyId, array $property): array
    {
        $pricePerSqm = self::pricePerSqmForProperty($property);

        $defaults = [
            'distToRoadKm' => max(0.2, round((100 - (int) ($property['roadAccess'] ?? 85)) / 18, 2)),
            'utilityStatus' => match ((string) ($property['type'] ?? 'commercial')) {
                'bpo' => 'full_ready',
                'hotel' => 'power_water',
                'manufacturing', 'logistics' => 'partial',
                default => 'partial',
            },
            'zoningScore' => max(45, min(100, ((int) ($property['score'] ?? 82)) - 4)),
            'assessedValueSqm' => max(1, (int) round($pricePerSqm * 0.92)),
            'readinessNotes' => 'Seeded readiness inputs for IRIE calibration.',
        ];

        $profiles = [
            1 => [
                'distToRoadKm' => 0.25,
                'utilityStatus' => 'full_ready',
                'zoningScore' => 94,
                'assessedValueSqm' => 811,
                'readinessNotes' => 'Logistics-ready frontage with strong highway adjacency and mature utility coverage.',
            ],
            2 => [
                'distToRoadKm' => 0.6,
                'utilityStatus' => 'power_water',
                'zoningScore' => 88,
                'assessedValueSqm' => 710,
                'readinessNotes' => 'Tourism parcel is attractive but still needs hazard documentation and full service confirmation.',
            ],
            3 => [
                'distToRoadKm' => 0.18,
                'utilityStatus' => 'full_ready',
                'zoningScore' => 90,
                'assessedValueSqm' => 797,
                'readinessNotes' => 'Downtown location is institutionally strong, but final legal review is still uneven.',
            ],
            4 => [
                'distToRoadKm' => 0.42,
                'utilityStatus' => 'partial',
                'zoningScore' => 78,
                'assessedValueSqm' => 666,
                'readinessNotes' => 'Industrial site still sits in pending review while documents are being assembled.',
            ],
            5 => [
                'distToRoadKm' => 0.35,
                'utilityStatus' => 'full_ready',
                'zoningScore' => 86,
                'assessedValueSqm' => 770,
                'readinessNotes' => 'Tech-oriented site benefits from fiber-ready positioning but still needs economic benchmark confirmation.',
            ],
            6 => [
                'distToRoadKm' => 1.1,
                'utilityStatus' => 'limited',
                'zoningScore' => 61,
                'assessedValueSqm' => 798,
                'readinessNotes' => 'Draft listing with incomplete planning and legal inputs.',
            ],
            7 => [
                'distToRoadKm' => 0.22,
                'utilityStatus' => 'full_ready',
                'zoningScore' => 92,
                'assessedValueSqm' => 755,
                'readinessNotes' => 'Mixed-use site is one of the strongest all-around readiness cases in the seed set.',
            ],
            8 => [
                'distToRoadKm' => 0.85,
                'utilityStatus' => 'partial',
                'zoningScore' => 76,
                'assessedValueSqm' => 777,
                'readinessNotes' => 'Coastal luxury play has upside, but infrastructure and legal risk remain visible.',
            ],
            9 => [
                'distToRoadKm' => 0.55,
                'utilityStatus' => 'limited',
                'zoningScore' => 58,
                'assessedValueSqm' => 672,
                'readinessNotes' => 'Listing rejected pending stronger institutional and zoning support.',
            ],
            10 => [
                'distToRoadKm' => 0.14,
                'utilityStatus' => 'full_ready',
                'zoningScore' => 93,
                'assessedValueSqm' => 771,
                'readinessNotes' => 'Business-park parcel is highly legible for office-led development and performs well across all pillars.',
            ],
        ];

        return array_replace($defaults, $profiles[$propertyId] ?? []);
    }

    private static function dueDiligenceStateForProperty(int $propertyId): array
    {
        $defaults = [
            'title' => false,
            'zoning' => false,
            'survey' => false,
            'rightofway' => false,
            'utilities' => false,
            'hazards' => false,
            'environment' => false,
            'permits' => false,
            'tax' => false,
            'valuation' => false,
        ];

        $profiles = [
            1 => [
                'title' => true,
                'zoning' => true,
                'survey' => true,
                'rightofway' => true,
                'utilities' => true,
                'hazards' => true,
                'environment' => false,
                'permits' => true,
                'tax' => true,
                'valuation' => true,
            ],
            2 => [
                'title' => true,
                'zoning' => true,
                'survey' => true,
                'rightofway' => true,
                'utilities' => true,
                'hazards' => false,
                'environment' => false,
                'permits' => true,
                'tax' => true,
                'valuation' => false,
            ],
            3 => [
                'title' => true,
                'zoning' => true,
                'survey' => true,
                'rightofway' => true,
                'utilities' => true,
                'hazards' => false,
                'environment' => false,
                'permits' => true,
                'tax' => true,
                'valuation' => true,
            ],
            4 => [
                'title' => true,
                'zoning' => false,
                'survey' => false,
                'rightofway' => true,
                'utilities' => true,
                'hazards' => false,
                'environment' => false,
                'permits' => false,
                'tax' => true,
                'valuation' => false,
            ],
            5 => [
                'title' => true,
                'zoning' => true,
                'survey' => true,
                'rightofway' => true,
                'utilities' => true,
                'hazards' => false,
                'environment' => false,
                'permits' => true,
                'tax' => true,
                'valuation' => true,
            ],
            6 => [
                'title' => false,
                'zoning' => false,
                'survey' => false,
                'rightofway' => false,
                'utilities' => true,
                'hazards' => false,
                'environment' => false,
                'permits' => false,
                'tax' => false,
                'valuation' => false,
            ],
            7 => [
                'title' => true,
                'zoning' => true,
                'survey' => true,
                'rightofway' => true,
                'utilities' => true,
                'hazards' => true,
                'environment' => true,
                'permits' => true,
                'tax' => true,
                'valuation' => true,
            ],
            8 => [
                'title' => true,
                'zoning' => true,
                'survey' => false,
                'rightofway' => true,
                'utilities' => true,
                'hazards' => false,
                'environment' => false,
                'permits' => true,
                'tax' => true,
                'valuation' => false,
            ],
            9 => [
                'title' => true,
                'zoning' => false,
                'survey' => false,
                'rightofway' => true,
                'utilities' => false,
                'hazards' => false,
                'environment' => false,
                'permits' => false,
                'tax' => true,
                'valuation' => false,
            ],
            10 => [
                'title' => true,
                'zoning' => true,
                'survey' => true,
                'rightofway' => true,
                'utilities' => true,
                'hazards' => true,
                'environment' => false,
                'permits' => true,
                'tax' => true,
                'valuation' => true,
            ],
        ];

        return array_replace($defaults, $profiles[$propertyId] ?? []);
    }

    private static function trustProfileForProperty(int $propertyId): array
    {
        $defaults = [
            'approvalState' => 'approved',
            'documents' => self::defaultDocumentStatuses(),
            'documentsReviewedAt' => null,
            'siteVerifiedAt' => null,
            'lastConfirmedAvailableAt' => '2026-03-28 10:00:00',
        ];

        $profiles = [
            1 => [
                'approvalState' => 'approved',
                'documents' => [
                    'title_copy' => 'reviewed',
                    'tax_declaration' => 'reviewed',
                    'survey_plan' => 'reviewed',
                    'zoning_clearance' => 'reviewed',
                    'site_photos' => 'reviewed',
                    'hazard_report' => 'submitted',
                ],
                'documentsReviewedAt' => '2026-03-24 09:00:00',
                'siteVerifiedAt' => '2026-03-22 13:30:00',
                'lastConfirmedAvailableAt' => '2026-03-30 08:15:00',
            ],
            2 => [
                'approvalState' => 'approved',
                'documents' => [
                    'title_copy' => 'submitted',
                    'tax_declaration' => 'submitted',
                    'survey_plan' => 'submitted',
                    'zoning_clearance' => 'reviewed',
                    'site_photos' => 'reviewed',
                    'hazard_report' => 'requested',
                ],
                'documentsReviewedAt' => null,
                'siteVerifiedAt' => '2026-03-18 16:10:00',
                'lastConfirmedAvailableAt' => '2026-03-29 11:20:00',
            ],
            3 => [
                'approvalState' => 'approved',
                'documents' => [
                    'title_copy' => 'reviewed',
                    'tax_declaration' => 'reviewed',
                    'survey_plan' => 'submitted',
                    'zoning_clearance' => 'submitted',
                    'site_photos' => 'reviewed',
                    'hazard_report' => 'submitted',
                ],
                'documentsReviewedAt' => '2026-03-11 15:45:00',
                'siteVerifiedAt' => null,
                'lastConfirmedAvailableAt' => '2026-03-12 10:30:00',
            ],
            4 => [
                'approvalState' => 'pending_review',
                'documents' => [
                    'title_copy' => 'submitted',
                    'tax_declaration' => 'submitted',
                    'survey_plan' => 'requested',
                    'zoning_clearance' => 'requested',
                    'site_photos' => 'submitted',
                    'hazard_report' => 'missing',
                ],
                'documentsReviewedAt' => null,
                'siteVerifiedAt' => null,
                'lastConfirmedAvailableAt' => '2026-03-27 14:00:00',
            ],
            5 => [
                'approvalState' => 'approved',
                'documents' => [
                    'title_copy' => 'submitted',
                    'tax_declaration' => 'submitted',
                    'survey_plan' => 'submitted',
                    'zoning_clearance' => 'submitted',
                    'site_photos' => 'reviewed',
                    'hazard_report' => 'submitted',
                ],
                'documentsReviewedAt' => null,
                'siteVerifiedAt' => '2026-03-20 09:45:00',
                'lastConfirmedAvailableAt' => '2026-03-25 09:15:00',
            ],
            6 => [
                'approvalState' => 'draft',
                'documents' => [
                    'title_copy' => 'missing',
                    'tax_declaration' => 'missing',
                    'survey_plan' => 'missing',
                    'zoning_clearance' => 'missing',
                    'site_photos' => 'submitted',
                    'hazard_report' => 'missing',
                ],
                'documentsReviewedAt' => null,
                'siteVerifiedAt' => null,
                'lastConfirmedAvailableAt' => '2026-03-10 08:00:00',
            ],
            7 => [
                'approvalState' => 'approved',
                'documents' => [
                    'title_copy' => 'reviewed',
                    'tax_declaration' => 'reviewed',
                    'survey_plan' => 'reviewed',
                    'zoning_clearance' => 'reviewed',
                    'site_photos' => 'reviewed',
                    'hazard_report' => 'reviewed',
                ],
                'documentsReviewedAt' => '2026-03-19 10:10:00',
                'siteVerifiedAt' => '2026-03-17 14:20:00',
                'lastConfirmedAvailableAt' => '2026-03-29 17:00:00',
            ],
            8 => [
                'approvalState' => 'approved',
                'documents' => [
                    'title_copy' => 'submitted',
                    'tax_declaration' => 'submitted',
                    'survey_plan' => 'requested',
                    'zoning_clearance' => 'submitted',
                    'site_photos' => 'reviewed',
                    'hazard_report' => 'requested',
                ],
                'documentsReviewedAt' => null,
                'siteVerifiedAt' => null,
                'lastConfirmedAvailableAt' => '2026-03-24 07:50:00',
            ],
            9 => [
                'approvalState' => 'rejected',
                'documents' => [
                    'title_copy' => 'submitted',
                    'tax_declaration' => 'requested',
                    'survey_plan' => 'requested',
                    'zoning_clearance' => 'missing',
                    'site_photos' => 'submitted',
                    'hazard_report' => 'missing',
                ],
                'documentsReviewedAt' => null,
                'siteVerifiedAt' => null,
                'lastConfirmedAvailableAt' => '2026-03-09 12:30:00',
            ],
            10 => [
                'approvalState' => 'approved',
                'documents' => [
                    'title_copy' => 'reviewed',
                    'tax_declaration' => 'reviewed',
                    'survey_plan' => 'submitted',
                    'zoning_clearance' => 'reviewed',
                    'site_photos' => 'reviewed',
                    'hazard_report' => 'submitted',
                ],
                'documentsReviewedAt' => '2026-03-21 11:30:00',
                'siteVerifiedAt' => '2026-03-21 15:00:00',
                'lastConfirmedAvailableAt' => '2026-03-30 09:40:00',
            ],
        ];

        return array_replace($defaults, $profiles[$propertyId] ?? []);
    }

    private static function defaultDocumentStatuses(): array
    {
        return [
            'title_copy' => 'missing',
            'tax_declaration' => 'missing',
            'survey_plan' => 'missing',
            'zoning_clearance' => 'missing',
            'site_photos' => 'missing',
            'hazard_report' => 'missing',
        ];
    }
}
