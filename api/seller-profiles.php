<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function seller_application_summary(array $profiles): array
{
    $summary = [
        'total' => count($profiles),
        'pendingReview' => 0,
        'verified' => 0,
        'rejected' => 0,
        'suspended' => 0,
        'draft' => 0,
    ];

    foreach ($profiles as $profile) {
        $status = strtolower((string) ($profile['applicationStatus'] ?? 'draft'));
        if ($status === 'pending_review') {
            $summary['pendingReview']++;
        } elseif ($status === 'verified') {
            $summary['verified']++;
        } elseif ($status === 'rejected') {
            $summary['rejected']++;
        } elseif ($status === 'suspended') {
            $summary['suspended']++;
        } else {
            $summary['draft']++;
        }
    }

    return $summary;
}

function seller_identity_status_from_application(string $applicationStatus): string
{
    return match (strtolower(trim($applicationStatus))) {
        'verified' => 'verified',
        'rejected' => 'rejected',
        'suspended' => 'suspended',
        'pending_review' => 'pending',
        default => 'unverified',
    };
}

api_handle(function (array $container): array {
    $user = sfc_current_user();
    if ($user === null || !isset($user['id'])) {
        return [403, ['error' => 'A logged-in account is required to manage seller profiles.']];
    }

    $method = request_method();
    $role = (string) ($user['role'] ?? 'guest');

    if ($method === 'GET') {
        if ($role === 'seller') {
            return [
                'profile' => $container['sellerProfiles']->findOrInitializeByUser($user),
                'generatedAt' => gmdate(DATE_ATOM),
            ];
        }

        if ($role !== 'admin') {
            return [403, ['error' => 'Only seller or admin accounts can view seller profiles.']];
        }

        $scope = strtolower(trim((string) ($_GET['scope'] ?? 'queue')));
        if ($scope === 'queue') {
            $status = string_or_null($_GET['status'] ?? null);
            $profiles = $container['sellerProfiles']->queue($status);

            return [
                'profiles' => $profiles,
                'summary' => seller_application_summary($profiles),
                'generatedAt' => gmdate(DATE_ATOM),
            ];
        }

        $targetUserId = int_or_null($_GET['userId'] ?? $_GET['sellerUserId'] ?? null);
        if ($targetUserId === null || $targetUserId < 1) {
            throw new InvalidArgumentException('A valid seller user id is required.');
        }

        $profile = $container['sellerProfiles']->findByUserId($targetUserId);
        if ($profile === null) {
            throw new OutOfBoundsException('Seller profile not found.');
        }

        return [
            'profile' => $profile,
            'generatedAt' => gmdate(DATE_ATOM),
        ];
    }

    if ($method === 'POST') {
        if ($role !== 'seller') {
            return [403, ['error' => 'Only seller accounts can submit seller verification details.']];
        }

        $payload = read_request_input();
        $submit = filter_var($payload['submit'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $action = strtolower((string) ($payload['action'] ?? ''));
        if ($action === 'submit') {
            $submit = true;
        }

        $profile = $container['sellerProfiles']->createOrUpdateForUser((int) $user['id'], $payload, $submit);
        $updatedUser = $container['users']->updateIdentityVerificationStatus(
            (int) $user['id'],
            seller_identity_status_from_application((string) ($profile['applicationStatus'] ?? 'draft'))
        );

        sfc_start_session();
        $_SESSION['sfc_user'] = sfc_user_session_payload($updatedUser);

        if ($submit) {
            $adminIds = array_values(array_filter(array_map(
                static fn (array $admin): int => (int) ($admin['id'] ?? 0),
                $container['users']->allByRole('admin')
            )));
            if ($adminIds !== []) {
                $container['notifications']->createForUsers($adminIds, [
                    'category' => 'operational',
                    'kind' => 'seller_application',
                    'priority' => 'high',
                    'tone' => 'info',
                    'icon' => 'seller',
                    'title' => 'Seller application ready for review',
                    'body' => sprintf(
                        '%s submitted seller verification details and is waiting for approval.',
                        $profile['legalName'] ?: $updatedUser['name']
                    ),
                    'actionLabel' => 'Open admin queue',
                    'actionUrl' => 'admin-dashboard.php',
                    'actorUserId' => (int) $updatedUser['id'],
                    'meta' => [
                        'sellerUserId' => (int) $updatedUser['id'],
                        'applicationStatus' => $profile['applicationStatus'] ?? 'pending_review',
                    ],
                ]);
            }
        }

        return [
            'profile' => $profile,
            'user' => sfc_user_session_payload($updatedUser),
            'generatedAt' => gmdate(DATE_ATOM),
        ];
    }

    if ($method === 'PATCH') {
        if ($role !== 'admin') {
            return [403, ['error' => 'Only admin accounts can review seller applications.']];
        }

        $payload = read_json_input();
        $targetUserId = int_or_null($payload['userId'] ?? $payload['sellerUserId'] ?? null);
        if ($targetUserId === null || $targetUserId < 1) {
            throw new InvalidArgumentException('A valid seller user id is required.');
        }

        $status = string_or_null($payload['status'] ?? $payload['applicationStatus'] ?? null);
        if ($status === null) {
            throw new InvalidArgumentException('A review status is required.');
        }

        $reviewNotes = string_or_null($payload['reviewNotes'] ?? $payload['review_notes'] ?? null);
        $profile = $container['sellerProfiles']->review($targetUserId, $status, (int) $user['id'], $reviewNotes);
        $updatedUser = $container['users']->updateIdentityVerificationStatus(
            $targetUserId,
            seller_identity_status_from_application((string) ($profile['applicationStatus'] ?? 'draft'))
        );

        $notificationTitle = match (strtolower($status)) {
            'verified' => 'Seller account verified',
            'rejected' => 'Seller application needs revision',
            'suspended' => 'Seller access suspended',
            default => 'Seller application updated',
        };
        $notificationBody = match (strtolower($status)) {
            'verified' => 'Your seller account is now verified. You can publish listings in the seller workspace.',
            'rejected' => 'Your seller application needs revision before it can be approved.',
            'suspended' => 'Your seller access has been suspended. Please contact the platform admin.',
            default => 'Your seller application status was updated by the admin team.',
        };
        if ($reviewNotes !== null) {
            $notificationBody .= ' Note: ' . $reviewNotes;
        }

        $container['notifications']->createForUsers([(int) $updatedUser['id']], [
            'category' => 'transactional',
            'kind' => 'seller_review',
            'priority' => strtolower($status) === 'verified' ? 'normal' : 'high',
            'tone' => strtolower($status) === 'verified' ? 'success' : 'system',
            'icon' => 'seller',
            'title' => $notificationTitle,
            'body' => $notificationBody,
            'actionLabel' => 'Open seller dashboard',
            'actionUrl' => 'seller-dashboard.php',
            'actorUserId' => (int) $user['id'],
            'meta' => [
                'sellerUserId' => (int) $updatedUser['id'],
                'applicationStatus' => $profile['applicationStatus'] ?? $status,
            ],
        ]);

        $profiles = $container['sellerProfiles']->queue();

        return [
            'profile' => $profile,
            'user' => [
                'id' => (int) $updatedUser['id'],
                'identityVerificationStatus' => $updatedUser['identityVerificationStatus'] ?? 'unverified',
                'identityVerifiedAt' => $updatedUser['identityVerifiedAt'] ?? null,
            ],
            'profiles' => $profiles,
            'summary' => seller_application_summary($profiles),
            'generatedAt' => gmdate(DATE_ATOM),
        ];
    }

    return [405, ['error' => 'Method not allowed.']];
});
