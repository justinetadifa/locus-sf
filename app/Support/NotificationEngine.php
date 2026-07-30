<?php
declare(strict_types=1);

namespace App\Support;

use App\Repositories\NotificationRepository;
use PDO;

final class NotificationEngine
{
    private PDO $pdo;
    private NotificationRepository $notifications;

    public function __construct(PDO $pdo, NotificationRepository $notifications)
    {
        $this->pdo = $pdo;
        $this->notifications = $notifications;
    }

    public function seedDemoNotificationsIfNeeded(): void
    {
        if ($this->notifications->countAll() > 0) {
            return;
        }

        $adminIds = $this->userIdsByRole('admin');
        $sellerId = $this->firstUserIdByRole('seller');
        $investorIds = $this->userIdsByRole('investor');
        $propertyOne = $this->propertyContext(1);
        $propertyTwo = $this->propertyContext(2);
        $propertyFour = $this->propertyContext(4);

        if ($adminIds !== [] && $propertyFour !== null) {
            $this->notifications->createForUsers($adminIds, [
                'category' => 'transactional',
                'kind' => 'listing_submitted',
                'priority' => 'high',
                'tone' => 'system',
                'icon' => 'shield',
                'title' => 'Listing Awaiting Review',
                'body' => sprintf(
                    '%s is still pending review and needs moderation before it can go live.',
                    $propertyFour['name']
                ),
                'action_label' => 'Review',
                'action_url' => sprintf('/admin-properties.php?edit=%d', (int) $propertyFour['id']),
                'property_id' => (int) $propertyFour['id'],
                'created_at' => '2026-04-01 08:10:00',
            ]);
        }

        if ($adminIds !== [] && $propertyTwo !== null) {
            $this->notifications->createForUsers($adminIds, [
                'category' => 'intelligence',
                'kind' => 'market_heat',
                'priority' => 'normal',
                'tone' => 'trend',
                'icon' => 'trend',
                'title' => 'Market Heat Update',
                'body' => sprintf(
                    'Voting for logistics around %s just spiked by 20%%. Momentum is building in the demand queue.',
                    $propertyTwo['barangay'] ?: $propertyTwo['name']
                ),
                'action_label' => 'Open Voting',
                'action_url' => sprintf('/voting-dashboard.php?property=%d', (int) $propertyTwo['id']),
                'property_id' => (int) $propertyTwo['id'],
                'created_at' => '2026-03-31 16:20:00',
            ]);
        }

        if ($sellerId !== null && $propertyOne !== null) {
            $this->notifications->createForUsers([$sellerId], [
                'category' => 'transactional',
                'kind' => 'new_inquiry',
                'priority' => 'high',
                'tone' => 'info',
                'icon' => 'chat',
                'title' => 'New Inquiry',
                'body' => sprintf(
                    'Maria Santos is asking about the expansion potential and title packet for %s.',
                    $propertyOne['name']
                ),
                'action_label' => 'View',
                'action_url' => sprintf('/property-details.php?id=%d', (int) $propertyOne['id']),
                'property_id' => (int) $propertyOne['id'],
                'created_at' => '2026-04-01 07:42:00',
            ]);

            $this->notifications->createForUsers([$sellerId], [
                'category' => 'operational',
                'kind' => 'due_diligence_request',
                'priority' => 'normal',
                'tone' => 'system',
                'icon' => 'file',
                'title' => 'Due Diligence Update',
                'body' => sprintf(
                    'Hazard and flood screening is still requested for %s. The tourism listing is waiting on supporting files.',
                    $propertyTwo !== null ? $propertyTwo['name'] : 'the property'
                ),
                'action_label' => 'Resolve',
                'action_url' => $propertyTwo !== null ? sprintf('/property-details.php?id=%d', (int) $propertyTwo['id']) : '/seller-dashboard.php',
                'property_id' => $propertyTwo !== null ? (int) $propertyTwo['id'] : null,
                'created_at' => '2026-03-31 11:15:00',
            ]);
        }

        if ($investorIds !== [] && $propertyOne !== null) {
            $this->notifications->createForUsers($investorIds, [
                'category' => 'transactional',
                'kind' => 'seller_reply',
                'priority' => 'high',
                'tone' => 'info',
                'icon' => 'chat',
                'title' => 'Seller Reply',
                'body' => sprintf(
                    'Seller Studio replied on %s and is ready to share the survey plan next.',
                    $propertyOne['name']
                ),
                'action_label' => 'Open Thread',
                'action_url' => sprintf('/property-details.php?id=%d', (int) $propertyOne['id']),
                'property_id' => (int) $propertyOne['id'],
                'created_at' => '2026-04-01 08:30:00',
            ]);

            $this->notifications->createForUsers($investorIds, [
                'category' => 'operational',
                'kind' => 'site_visit_reminder',
                'priority' => 'normal',
                'tone' => 'system',
                'icon' => 'site',
                'title' => 'Site Visit Reminder',
                'body' => sprintf(
                    'Bring survey, hazard, and right-of-way notes before the next site visit at %s.',
                    $propertyTwo !== null
                        ? (($propertyTwo['barangay'] ?? null) ?: $propertyTwo['name'])
                        : 'the coastal parcel'
                ),
                'action_label' => 'Review Checklist',
                'action_url' => $propertyTwo !== null ? sprintf('/property-details.php?id=%d', (int) $propertyTwo['id']) : '/investor-dashboard.php',
                'property_id' => $propertyTwo !== null ? (int) $propertyTwo['id'] : null,
                'created_at' => '2026-03-31 09:05:00',
            ]);

            $this->notifications->createForUsers($investorIds, [
                'category' => 'operational',
                'kind' => 'due_diligence_progress',
                'priority' => 'normal',
                'tone' => 'system',
                'icon' => 'pulse',
                'title' => 'Due Diligence Progress',
                'body' => sprintf(
                    '%s has moved to 80%% completion. Legal readiness is improving, but environmental validation is still open.',
                    $propertyOne['name']
                ),
                'action_label' => 'Review',
                'action_url' => sprintf('/property-details.php?id=%d', (int) $propertyOne['id']),
                'property_id' => (int) $propertyOne['id'],
                'created_at' => '2026-03-28 15:20:00',
            ]);
        }
    }

    public function onListingCreated(array $property, array $actor): void
    {
        $actorId = (int) ($actor['id'] ?? 0);
        $actorRole = (string) ($actor['role'] ?? 'guest');
        $propertyId = (int) ($property['id'] ?? 0);
        $propertyName = (string) ($property['name'] ?? $property['propertyName'] ?? 'Listing');
        $sellerUserId = int_or_null($property['sellerUserId'] ?? $property['seller_user_id'] ?? null);
        $approvalState = strtolower((string) ($property['approvalState'] ?? $property['approval_state'] ?? 'pending_review'));

        if ($actorRole === 'seller') {
            $this->notifyUsers($this->userIdsByRole('admin'), $actorId, [
                'actor_user_id' => $actorId,
                'category' => 'transactional',
                'kind' => 'listing_submitted',
                'priority' => 'high',
                'tone' => 'system',
                'icon' => 'shield',
                'title' => 'Listing Submitted',
                'body' => sprintf('%s submitted %s for review.', (string) ($actor['name'] ?? 'A seller'), $propertyName),
                'action_label' => 'Review',
                'action_url' => sprintf('/admin-properties.php?edit=%d', $propertyId),
                'property_id' => $propertyId,
            ]);

            return;
        }

        if ($actorRole === 'admin' && $sellerUserId !== null) {
            $this->notifyUsers([$sellerUserId], $actorId, [
                'actor_user_id' => $actorId,
                'category' => 'transactional',
                'kind' => 'listing_published',
                'priority' => 'high',
                'tone' => $approvalState === 'approved' ? 'success' : 'system',
                'icon' => $approvalState === 'approved' ? 'success' : 'shield',
                'title' => $approvalState === 'approved' ? 'Listing Approved' : 'Listing Saved',
                'body' => $approvalState === 'approved'
                    ? sprintf('%s is now live on the platform.', $propertyName)
                    : sprintf('%s has been created and is currently %s.', $propertyName, $this->approvalLabel($approvalState)),
                'action_label' => 'View',
                'action_url' => '/seller-dashboard.php',
                'property_id' => $propertyId,
            ]);
        }
    }

    public function onListingUpdated(array $before, array $after, array $actor): void
    {
        $actorId = (int) ($actor['id'] ?? 0);
        $beforeState = strtolower((string) ($before['approvalState'] ?? $before['approval_state'] ?? 'approved'));
        $afterState = strtolower((string) ($after['approvalState'] ?? $after['approval_state'] ?? 'approved'));
        if ($beforeState === $afterState) {
            return;
        }

        $sellerUserId = int_or_null($after['sellerUserId'] ?? $after['seller_user_id'] ?? null);
        $propertyId = (int) ($after['id'] ?? 0);
        $propertyName = (string) ($after['name'] ?? $after['propertyName'] ?? 'Listing');
        if ($sellerUserId === null) {
            return;
        }

        $payload = match ($afterState) {
            'approved' => [
                'category' => 'transactional',
                'kind' => 'listing_approved',
                'priority' => 'high',
                'tone' => 'success',
                'icon' => 'success',
                'title' => 'Listing Approved',
                'body' => sprintf('%s passed moderation and is now visible to investors.', $propertyName),
            ],
            'rejected' => [
                'category' => 'transactional',
                'kind' => 'listing_rejected',
                'priority' => 'high',
                'tone' => 'system',
                'icon' => 'shield',
                'title' => 'Listing Rejected',
                'body' => sprintf('%s was rejected during moderation. Open the seller workspace to review the listing and resubmit.', $propertyName),
            ],
            default => [
                'category' => 'operational',
                'kind' => 'listing_status',
                'priority' => 'normal',
                'tone' => 'system',
                'icon' => 'shield',
                'title' => 'Listing Status Changed',
                'body' => sprintf('%s moved from %s to %s.', $propertyName, $this->approvalLabel($beforeState), $this->approvalLabel($afterState)),
            ],
        };

        $this->notifyUsers(
            [$sellerUserId],
            $actorId,
            array_merge(
                [
                    'actor_user_id' => $actorId,
                ],
                $payload,
                [
                    'action_label' => 'View',
                    'action_url' => '/seller-dashboard.php',
                    'property_id' => $propertyId,
                ]
            )
        );
    }

    public function onMessageSent(array $messagePayload, array $actor, bool $newConversation = false): void
    {
        $thread = $messagePayload['thread'] ?? null;
        $message = $messagePayload['message'] ?? null;
        if (!is_array($thread) || !is_array($message)) {
            return;
        }

        $actorId = (int) ($actor['id'] ?? 0);
        $actorRole = (string) ($actor['role'] ?? 'guest');
        $propertyId = (int) ($thread['propertyId'] ?? 0);
        $threadId = (int) ($thread['id'] ?? 0);
        $propertyName = (string) ($thread['propertyName'] ?? 'this property');
        $excerpt = $this->excerpt((string) ($message['text'] ?? ''));

        if ($actorRole === 'investor' && isset($thread['sellerUserId'])) {
            $sellerUserId = (int) ($thread['sellerUserId'] ?? 0);
            $this->notifyUsers([$sellerUserId], $actorId, [
                'actor_user_id' => $actorId,
                'category' => 'transactional',
                'kind' => $newConversation ? 'new_inquiry' : 'investor_followup',
                'priority' => 'high',
                'tone' => 'info',
                'icon' => 'chat',
                'title' => $newConversation ? 'New Inquiry' : 'Investor Follow-up',
                'body' => sprintf('%s sent a message about %s. "%s"', (string) ($actor['name'] ?? 'An investor'), $propertyName, $excerpt),
                'action_label' => 'View',
                'action_url' => sprintf('/property-details.php?id=%d', $propertyId),
                'property_id' => $propertyId,
                'thread_id' => $threadId,
            ]);
        }

        if ($actorRole === 'seller' && isset($thread['investorUserId'])) {
            $investorUserId = (int) ($thread['investorUserId'] ?? 0);
            $this->notifyUsers([$investorUserId], $actorId, [
                'actor_user_id' => $actorId,
                'category' => 'transactional',
                'kind' => 'seller_reply',
                'priority' => 'high',
                'tone' => 'info',
                'icon' => 'chat',
                'title' => 'Seller Reply',
                'body' => sprintf('%s replied on %s. "%s"', (string) ($actor['name'] ?? 'The seller'), $propertyName, $excerpt),
                'action_label' => 'View',
                'action_url' => sprintf('/property-details.php?id=%d', $propertyId),
                'property_id' => $propertyId,
                'thread_id' => $threadId,
            ]);
        }
    }

    public function onDocumentRequestCreated(array $request, array $actor): void
    {
        $sellerUserId = int_or_null($request['sellerUserId'] ?? null);
        $propertyId = (int) ($request['propertyId'] ?? 0);
        $requestId = (int) ($request['id'] ?? 0);
        $actorId = (int) ($actor['id'] ?? 0);
        $documentName = (string) ($request['documentName'] ?? 'Requested file');
        $propertyName = (string) ($request['propertyName'] ?? 'the listing');
        $requesterName = (string) ($request['requesterName'] ?? 'A user');
        $note = string_or_null($request['note'] ?? null);

        $recipients = array_merge($sellerUserId !== null ? [$sellerUserId] : [], $this->userIdsByRole('admin'));
        $this->notifyUsers($recipients, $actorId, [
            'actor_user_id' => $actorId,
            'category' => 'operational',
            'kind' => 'due_diligence_request',
            'priority' => 'normal',
            'tone' => 'system',
            'icon' => 'file',
            'title' => 'Due Diligence Request',
            'body' => sprintf('%s requested "%s" for %s.%s', $requesterName, $documentName, $propertyName, $note !== null ? ' ' . $note : ''),
            'action_label' => 'View',
            'action_url' => sprintf('/property-details.php?id=%d', $propertyId),
            'property_id' => $propertyId,
            'document_request_id' => $requestId,
        ]);

        if ($note !== null && stripos($note, 'site visit') !== false) {
            $this->notifyUsers($sellerUserId !== null ? [$sellerUserId] : [], $actorId, [
                'actor_user_id' => $actorId,
                'category' => 'operational',
                'kind' => 'site_visit_reminder',
                'priority' => 'normal',
                'tone' => 'system',
                'icon' => 'site',
                'title' => 'Site Visit Reminder',
                'body' => sprintf('A site visit follow-up was mentioned for %s. Coordinate the document packet before the next walkthrough.', $propertyName),
                'action_label' => 'Review',
                'action_url' => sprintf('/property-details.php?id=%d', $propertyId),
                'property_id' => $propertyId,
                'document_request_id' => $requestId,
            ]);
        }
    }

    public function onDocumentRequestUpdated(array $before, array $after, array $actor): void
    {
        $beforeStatus = strtolower((string) ($before['status'] ?? 'requested'));
        $afterStatus = strtolower((string) ($after['status'] ?? 'requested'));
        if ($beforeStatus === $afterStatus) {
            return;
        }

        $actorId = (int) ($actor['id'] ?? 0);
        $requesterUserId = int_or_null($after['requesterUserId'] ?? null);
        $propertyId = (int) ($after['propertyId'] ?? 0);
        $propertyName = (string) ($after['propertyName'] ?? 'the listing');
        $requestId = (int) ($after['id'] ?? 0);
        $documentName = (string) ($after['documentName'] ?? 'requested file');

        if ($requesterUserId === null) {
            return;
        }

        $payload = match ($afterStatus) {
            'fulfilled' => [
                'tone' => 'success',
                'icon' => 'success',
                'title' => 'Request Fulfilled',
                'body' => sprintf('%s for %s is ready to review.', $documentName, $propertyName),
            ],
            'declined' => [
                'tone' => 'system',
                'icon' => 'shield',
                'title' => 'Request Declined',
                'body' => sprintf('%s for %s was declined. Check the response note for the latest guidance.', $documentName, $propertyName),
            ],
            default => [
                'tone' => 'system',
                'icon' => 'file',
                'title' => 'Due Diligence Update',
                'body' => sprintf('%s for %s moved to %s.', $documentName, $propertyName, $this->statusLabel($afterStatus)),
            ],
        };

        $this->notifyUsers(
            [$requesterUserId],
            $actorId,
            array_merge(
                [
                    'actor_user_id' => $actorId,
                    'category' => 'operational',
                    'kind' => 'due_diligence_status',
                    'priority' => 'normal',
                ],
                $payload,
                [
                    'action_label' => 'View',
                    'action_url' => sprintf('/property-details.php?id=%d', $propertyId),
                    'property_id' => $propertyId,
                    'document_request_id' => $requestId,
                ]
            )
        );
    }

    public function onDueDiligenceUpdated(int $propertyId, array $beforeState, array $afterState, array $actor): void
    {
        $beforePct = $this->completionPct($beforeState);
        $afterPct = $this->completionPct($afterState);
        if ($beforePct === $afterPct) {
            return;
        }

        $property = $this->propertyContext($propertyId);
        if ($property === null) {
            return;
        }

        $actorId = (int) ($actor['id'] ?? 0);
        $actorRole = (string) ($actor['role'] ?? 'guest');
        $recipients = [];
        if ($actorRole === 'seller') {
            $recipients = $this->userIdsByRole('admin');
        } elseif ($actorRole === 'admin' && isset($property['sellerUserId'])) {
            $recipients[] = (int) $property['sellerUserId'];
        }

        if ($afterPct > $beforePct) {
            $recipients = array_merge($recipients, $this->activeInterestUserIds($propertyId));
        }

        $this->notifyUsers($recipients, $actorId, [
            'actor_user_id' => $actorId,
            'category' => 'operational',
            'kind' => 'due_diligence_progress',
            'priority' => 'normal',
            'tone' => 'system',
            'icon' => 'pulse',
            'title' => 'Due Diligence Updated',
            'body' => sprintf('%s moved from %d%% to %d%% completion.', $property['name'], $beforePct, $afterPct),
            'action_label' => 'Review',
            'action_url' => sprintf('/property-details.php?id=%d', $propertyId),
            'property_id' => $propertyId,
        ]);
    }

    public function onVisitTransition(?array $before, array $after, array $actor, string $action = ''): void
    {
        $actorId = (int) ($actor['id'] ?? 0);
        $actorName = (string) ($actor['name'] ?? 'Platform User');
        $propertyId = (int) ($after['propertyId'] ?? 0);
        $propertyName = (string) ($after['propertyName'] ?? 'the property');
        $threadId = (int) ($after['threadId'] ?? 0);
        $sellerUserId = int_or_null($after['sellerUserId'] ?? null);
        $investorUserId = int_or_null($after['investorUserId'] ?? null);
        $purpose = (string) ($after['investmentPurpose'] ?? 'field diligence');
        $window = is_array($after['activeWindow'] ?? null) ? $after['activeWindow'] : null;
        $windowLabel = $window !== null ? $this->windowLabel($window) : 'the proposed window';
        $normalizedAction = strtolower(trim($action));
        $beforeStatus = strtolower((string) ($before['status'] ?? ''));
        $afterStatus = strtolower((string) ($after['status'] ?? 'proposed'));

        if ($before === null || $normalizedAction === 'propose') {
            $recipients = array_merge($sellerUserId !== null ? [$sellerUserId] : [], $this->userIdsByRole('admin'));
            $this->notifyUsers($recipients, $actorId, [
                'actor_user_id' => $actorId,
                'category' => 'operational',
                'kind' => 'site_visit_proposed',
                'priority' => 'high',
                'tone' => 'info',
                'icon' => 'site',
                'title' => 'Site Visit Proposed',
                'body' => sprintf('%s requested a site visit for %s with the thesis "%s".', $actorName, $propertyName, $purpose),
                'action_label' => 'Review',
                'action_url' => sprintf('/property-details.php?id=%d', $propertyId),
                'property_id' => $propertyId,
                'thread_id' => $threadId > 0 ? $threadId : null,
            ]);
            return;
        }

        if ($normalizedAction === 'counteroffer' && $investorUserId !== null) {
            $this->notifyUsers([$investorUserId], $actorId, [
                'actor_user_id' => $actorId,
                'category' => 'operational',
                'kind' => 'site_visit_counter',
                'priority' => 'high',
                'tone' => 'info',
                'icon' => 'site',
                'title' => 'Seller Suggested New Time',
                'body' => sprintf('%s proposed a new logistics window for %s: %s.', $actorName, $propertyName, $windowLabel),
                'action_label' => 'Open Thread',
                'action_url' => sprintf('/property-details.php?id=%d', $propertyId),
                'property_id' => $propertyId,
                'thread_id' => $threadId > 0 ? $threadId : null,
            ]);
            return;
        }

        if (in_array($normalizedAction, ['confirm', 'acceptcounter'], true) && $afterStatus === 'confirmed') {
            $recipients = $normalizedAction === 'confirm'
                ? ($investorUserId !== null ? [$investorUserId] : [])
                : ($sellerUserId !== null ? [$sellerUserId] : []);
            $this->notifyUsers($recipients, $actorId, [
                'actor_user_id' => $actorId,
                'category' => 'operational',
                'kind' => 'site_visit_confirmed',
                'priority' => 'high',
                'tone' => 'success',
                'icon' => 'success',
                'title' => 'Site Visit Confirmed',
                'body' => sprintf('%s is now scheduled for %s.', $propertyName, $windowLabel),
                'action_label' => 'Add to Calendar',
                'action_url' => sprintf('/property-details.php?id=%d', $propertyId),
                'property_id' => $propertyId,
                'thread_id' => $threadId > 0 ? $threadId : null,
            ]);
            return;
        }

        if ($normalizedAction === 'markvisited' && $beforeStatus !== 'visited') {
            $recipients = array_values(array_filter([
                $investorUserId,
                $sellerUserId,
            ]));
            $this->notifyUsers($recipients, $actorId, [
                'actor_user_id' => $actorId,
                'category' => 'operational',
                'kind' => 'site_visit_completed',
                'priority' => 'normal',
                'tone' => 'system',
                'icon' => 'site',
                'title' => 'Site Visit Completed',
                'body' => sprintf('The ground-truth visit for %s is complete. The field audit is ready to unlock.', $propertyName),
                'action_label' => 'Review',
                'action_url' => sprintf('/property-details.php?id=%d', $propertyId),
                'property_id' => $propertyId,
                'thread_id' => $threadId > 0 ? $threadId : null,
            ]);
            return;
        }

        if ($normalizedAction === 'submitaudit') {
            $recipients = array_merge(
                $sellerUserId !== null ? [$sellerUserId] : [],
                $this->userIdsByRole('admin')
            );
            $multiplier = number_format((float) ($after['groundTruthMultiplier'] ?? 1), 2);
            $this->notifyUsers($recipients, $actorId, [
                'actor_user_id' => $actorId,
                'category' => 'intelligence',
                'kind' => 'field_audit_submitted',
                'priority' => 'normal',
                'tone' => 'trend',
                'icon' => 'pulse',
                'title' => 'Field Audit Submitted',
                'body' => sprintf('%s submitted a ground-truth audit for %s. The IAI multiplier is now %s.', $actorName, $propertyName, $multiplier),
                'action_label' => 'Open Property',
                'action_url' => sprintf('/property-details.php?id=%d', $propertyId),
                'property_id' => $propertyId,
                'thread_id' => $threadId > 0 ? $threadId : null,
            ]);
        }
    }

    public function onVoteCast(int $propertyId, array $beforeVotes, array $afterVotes, array $actor, ?string $voteLabel = null): void
    {
        $property = $this->propertyContext($propertyId);
        if ($property === null) {
            return;
        }

        [$topLabel, $topCount] = $this->topVote($afterVotes);
        if ($topLabel === null || $topCount < 3 || !in_array($topCount, [3, 5, 10, 15, 20], true)) {
            return;
        }

        $beforeCount = (int) ($beforeVotes[$topLabel] ?? 0);
        $growthPct = $beforeCount > 0
            ? (int) round((($topCount - $beforeCount) / $beforeCount) * 100)
            : 100;
        if ($topCount > 3 && $growthPct < 20) {
            return;
        }

        $actorId = (int) ($actor['id'] ?? 0);
        $label = $voteLabel !== null && trim($voteLabel) !== '' ? trim($voteLabel) : $topLabel;
        $barangay = $property['barangay'] ?: $property['name'];
        $recipients = array_merge(
            $this->userIdsByRole('admin'),
            isset($property['sellerUserId']) ? [(int) $property['sellerUserId']] : [],
            $this->activeInterestUserIds($propertyId)
        );

        $this->notifyUsers($recipients, $actorId, [
            'actor_user_id' => $actorId,
            'category' => 'intelligence',
            'kind' => 'market_heat',
            'priority' => 'normal',
            'tone' => 'trend',
            'icon' => 'trend',
            'title' => 'Market Heat Update',
            'body' => sprintf('Voting for %s around %s just spiked by %d%%. The signal is now at %d votes.', $label, $barangay, max($growthPct, 20), $topCount),
            'action_label' => 'Open Voting',
            'action_url' => sprintf('/voting-dashboard.php?property=%d', $propertyId),
            'property_id' => $propertyId,
        ]);
    }

    private function notifyUsers(array $userIds, int $excludeUserId, array $payload): void
    {
        $filtered = array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): int => (int) $value, $userIds),
            static fn (int $value): bool => $value > 0 && $value !== $excludeUserId
        )));

        if ($filtered === []) {
            return;
        }

        $this->notifications->createForUsers($filtered, $payload);
    }

    private function propertyContext(int $propertyId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, barangay, seller_user_id
             FROM properties
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $propertyId]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'barangay' => $row['barangay'] !== null ? (string) $row['barangay'] : null,
            'sellerUserId' => isset($row['seller_user_id']) ? int_or_null($row['seller_user_id']) : null,
        ];
    }

    private function userIdsByRole(string $role): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id
             FROM users
             WHERE role = :role
             ORDER BY id ASC'
        );
        $statement->execute(['role' => $role]);

        return array_map(static fn (array $row): int => (int) $row['id'], $statement->fetchAll());
    }

    private function firstUserIdByRole(string $role): ?int
    {
        $statement = $this->pdo->prepare(
            'SELECT id
             FROM users
             WHERE role = :role
             ORDER BY id ASC
             LIMIT 1'
        );
        $statement->execute(['role' => $role]);

        return int_or_null($statement->fetchColumn());
    }

    private function activeInterestUserIds(int $propertyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT DISTINCT investor_user_id AS user_id
             FROM property_shortlists
             WHERE property_id = :property_id
             UNION
             SELECT DISTINCT investor_user_id AS user_id
             FROM message_threads
             WHERE property_id = :property_id_2'
        );
        $statement->execute([
            'property_id' => $propertyId,
            'property_id_2' => $propertyId,
        ]);

        return array_map(static fn (array $row): int => (int) ($row['user_id'] ?? 0), $statement->fetchAll());
    }

    private function completionPct(array $state): int
    {
        if ($state === []) {
            return 0;
        }

        $total = count($state);
        $complete = 0;
        foreach ($state as $value) {
            if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                $complete++;
            }
        }

        return (int) round(($complete / max($total, 1)) * 100);
    }

    private function topVote(array $votes): array
    {
        if ($votes === []) {
            return [null, 0];
        }

        arsort($votes);
        $label = array_key_first($votes);

        return [$label !== null ? (string) $label : null, (int) current($votes)];
    }

    private function excerpt(string $text, int $max = 120): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
        if (strlen($normalized) <= $max) {
            return $normalized;
        }

        return rtrim(substr($normalized, 0, max(0, $max - 3))) . '...';
    }

    private function approvalLabel(string $approvalState): string
    {
        return match (strtolower(trim($approvalState))) {
            'approved' => 'Approved',
            'pending_review' => 'Pending Review',
            'draft' => 'Draft',
            'rejected' => 'Rejected',
            'archived' => 'Archived',
            default => 'Updated',
        };
    }

    private function statusLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            'requested' => 'Requested',
            'in_review' => 'In Review',
            'fulfilled' => 'Fulfilled',
            'declined' => 'Declined',
            default => 'Updated',
        };
    }

    private function windowLabel(array $window): string
    {
        $startAt = string_or_null($window['startAt'] ?? null);
        if ($startAt === null) {
            return 'the selected window';
        }

        $timestamp = strtotime($startAt);
        if ($timestamp === false) {
            return 'the selected window';
        }

        return date('M j, Y | h:i A', $timestamp);
    }
}
