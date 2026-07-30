<?php
declare(strict_types=1);

namespace App\Support;

use App\Repositories\AuditLogRepository;
use App\Repositories\DocumentRequestRepository;
use App\Repositories\MessageRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\PropertyRepository;
use App\Repositories\VisitLogRepository;
use App\Repositories\VoteOptionRepository;

final class PropertyCommandCenterService
{
    private PropertyRepository $properties;
    private VoteOptionRepository $votes;
    private MessageRepository $messages;
    private DocumentRequestRepository $documentRequests;
    private VisitLogRepository $visits;
    private NotificationRepository $notifications;
    private AuditLogRepository $auditLogs;
    private DecisionEngineService $decisionEngine;

    public function __construct(
        PropertyRepository $properties,
        VoteOptionRepository $votes,
        MessageRepository $messages,
        DocumentRequestRepository $documentRequests,
        VisitLogRepository $visits,
        NotificationRepository $notifications,
        AuditLogRepository $auditLogs,
        DecisionEngineService $decisionEngine
    ) {
        $this->properties = $properties;
        $this->votes = $votes;
        $this->messages = $messages;
        $this->documentRequests = $documentRequests;
        $this->visits = $visits;
        $this->notifications = $notifications;
        $this->auditLogs = $auditLogs;
        $this->decisionEngine = $decisionEngine;
    }

    public function build(int $propertyId, ?array $user = null): array
    {
        $property = $this->properties->find($propertyId, $user);
        $votes = $this->votes->voteTallies($propertyId, isset($user['id']) ? (int) $user['id'] : null);
        $dueState = $this->properties->dueDiligenceState($propertyId);
        $conversation = $this->messages->propertyConversation($propertyId, $user);
        $visit = is_array($conversation['thread'] ?? null) && isset($conversation['thread']['id'])
            ? $this->visits->findByThread((int) $conversation['thread']['id'], $user)
            : $this->visits->latestForProperty($propertyId, $user);
        $documentRequests = $this->documentRequests->listByProperty($propertyId, $user);
        $notifications = $this->canViewOperationalFeed($user)
            ? $this->notifications->feedForProperty((int) ($user['id'] ?? 0), $propertyId, 14)
            : [];
        $auditLogs = $this->auditLogs->forProperty($propertyId, 18);
        $blockers = $this->buildBlockers($property, $documentRequests, $visit, $conversation);
        $property = $this->decisionEngine->decorateProperty($property, [
            'votes' => is_array($votes['votes'] ?? null) ? $votes['votes'] : [],
            'conversationSummary' => $conversation['summary'] ?? [],
            'visit' => $visit,
            'documentRequests' => $documentRequests,
            'blockers' => $blockers,
        ]);

        return [
            'property' => $property,
            'votes' => $votes,
            'dueState' => $dueState,
            'conversation' => [
                'thread' => $conversation['thread'] ?? null,
                'messages' => $conversation['messages'] ?? [],
                'threads' => $conversation['threads'] ?? [],
                'summary' => $conversation['summary'] ?? ['threadCount' => 0, 'messageCount' => 0],
                'visit' => $visit,
            ],
            'documentRequests' => $documentRequests,
            'notifications' => $notifications,
            'auditLogs' => $auditLogs,
            'blockers' => $blockers,
            'timeline' => $this->buildTimeline($notifications, $auditLogs, $conversation['messages'] ?? [], $documentRequests, $visit),
            'trust' => $this->buildTrust($property, $auditLogs, $conversation, $documentRequests, $visit),
            'nextActions' => $this->buildNextActions($property, $blockers, $conversation, $documentRequests, $visit),
            'generatedAt' => gmdate('Y-m-d H:i:s'),
        ];
    }

    private function buildBlockers(array $property, array $documentRequests, ?array $visit, array $conversation): array
    {
        $items = [];
        $approvalState = strtolower((string) ($property['approvalState'] ?? 'approved'));
        if ($approvalState !== 'approved') {
            $items[] = [
                'severity' => in_array($approvalState, ['rejected', 'archived'], true) ? 'critical' : 'high',
                'title' => 'Approval workflow unresolved',
                'summary' => sprintf('Listing is currently %s and needs admin action before it can move with full confidence.', $this->humanize($approvalState)),
                'ownerRole' => 'admin',
                'source' => 'moderation',
                'actionLabel' => 'Review approval',
                'actionTarget' => 'trust',
            ];
        }

        if (strtolower((string) ($property['sellerIdentityStatus'] ?? 'unverified')) !== 'verified') {
            $items[] = [
                'severity' => 'high',
                'title' => 'Seller identity not yet verified',
                'summary' => 'Trust is still capped because seller identity has not reached verified status.',
                'ownerRole' => 'admin',
                'source' => 'trust',
                'actionLabel' => 'Verify seller',
                'actionTarget' => 'trust',
            ];
        }

        $documentCompletenessPct = (int) ($property['documentCompletenessPct'] ?? 0);
        if ($documentCompletenessPct < 100) {
            $items[] = [
                'severity' => $documentCompletenessPct < 60 ? 'high' : 'medium',
                'title' => 'Document packet still incomplete',
                'summary' => sprintf('%d%% of the due diligence packet is currently present. Missing files are still suppressing readiness.', $documentCompletenessPct),
                'ownerRole' => 'seller',
                'source' => 'documents',
                'actionLabel' => 'Resolve documents',
                'actionTarget' => 'documents',
            ];
        }

        $duePct = (int) ($property['dueDiligencePct'] ?? 0);
        if ($duePct < 100) {
            $items[] = [
                'severity' => $duePct < 65 ? 'high' : 'medium',
                'title' => 'Due diligence checklist incomplete',
                'summary' => sprintf('The readiness checklist is only %d%% complete, leaving unresolved operational and legal questions.', $duePct),
                'ownerRole' => 'seller',
                'source' => 'readiness',
                'actionLabel' => 'Open checklist',
                'actionTarget' => 'due-diligence',
            ];
        }

        if (string_or_null($property['siteVerifiedAt'] ?? null) === null) {
            $items[] = [
                'severity' => 'medium',
                'title' => 'Site verification not stamped',
                'summary' => 'No verified field confirmation has been attached to this listing yet.',
                'ownerRole' => 'seller',
                'source' => 'ground_truth',
                'actionLabel' => 'Schedule visit',
                'actionTarget' => 'visits',
            ];
        }

        $openDocumentRequests = array_values(array_filter(
            $documentRequests,
            static fn (array $request): bool => in_array((string) ($request['status'] ?? ''), ['requested', 'in_review'], true)
        ));
        if ($openDocumentRequests !== []) {
            $items[] = [
                'severity' => 'medium',
                'title' => 'Open document request queue',
                'summary' => sprintf('%d request%s still need response before the document flow is fully closed.', count($openDocumentRequests), count($openDocumentRequests) === 1 ? '' : 's'),
                'ownerRole' => 'seller',
                'source' => 'documents',
                'actionLabel' => 'Respond to requests',
                'actionTarget' => 'documents',
            ];
        }

        if ($visit === null) {
            $items[] = [
                'severity' => 'medium',
                'title' => 'Ground truth visit not yet scheduled',
                'summary' => 'Digital diligence exists, but there is no live logistics record tying this property to an on-site validation step yet.',
                'ownerRole' => 'investor',
                'source' => 'visits',
                'actionLabel' => 'Propose site visit',
                'actionTarget' => 'visits',
            ];
        } elseif (strtolower((string) ($visit['status'] ?? 'proposed')) === 'visited' && !($visit['fieldAuditComplete'] ?? false)) {
            $items[] = [
                'severity' => 'medium',
                'title' => 'Field audit still locked in the pipeline',
                'summary' => 'The walkthrough is complete, but the investor has not submitted the ground-truth audit yet.',
                'ownerRole' => 'investor',
                'source' => 'ground_truth',
                'actionLabel' => 'Submit field audit',
                'actionTarget' => 'visits',
            ];
        }

        if ((int) (($conversation['summary']['messageCount'] ?? 0)) < 1) {
            $items[] = [
                'severity' => 'low',
                'title' => 'No inquiry trail yet',
                'summary' => 'There is still no direct investor-seller conversation on record for this property.',
                'ownerRole' => 'investor',
                'source' => 'messaging',
                'actionLabel' => 'Open thread',
                'actionTarget' => 'messaging',
            ];
        }

        usort($items, fn (array $left, array $right): int => $this->severityWeight((string) ($right['severity'] ?? 'low')) <=> $this->severityWeight((string) ($left['severity'] ?? 'low')));

        return array_slice($items, 0, 6);
    }

    private function buildTimeline(array $notifications, array $auditLogs, array $messages, array $documentRequests, ?array $visit): array
    {
        $items = [];

        foreach ($notifications as $notification) {
            $items[] = [
                'id' => 'notification-' . (int) ($notification['id'] ?? 0),
                'kind' => 'notification',
                'tone' => (string) ($notification['tone'] ?? 'system'),
                'title' => (string) ($notification['title'] ?? 'Notification update'),
                'summary' => (string) ($notification['body'] ?? ''),
                'actorName' => (string) ($notification['actorName'] ?? 'LINE'),
                'actorRole' => 'system',
                'createdAt' => (string) ($notification['createdAt'] ?? ''),
                'badge' => strtoupper((string) ($notification['category'] ?? 'info')),
                'target' => ($notification['documentRequestId'] ?? null) !== null ? 'documents' : (($notification['threadId'] ?? null) !== null ? 'messaging' : 'overview'),
            ];
        }

        foreach ($auditLogs as $entry) {
            $items[] = [
                'id' => 'audit-' . (int) ($entry['id'] ?? 0),
                'kind' => 'audit',
                'tone' => $this->badgeTone((string) ($entry['badge'] ?? 'TRACE')),
                'title' => (string) ($entry['eventType'] ?? $entry['actionType'] ?? 'Audit Event'),
                'summary' => (string) ($entry['summary'] ?? ''),
                'actorName' => (string) ($entry['actorName'] ?? 'System'),
                'actorRole' => (string) ($entry['actorRole'] ?? 'system'),
                'createdAt' => (string) ($entry['createdAt'] ?? ''),
                'badge' => (string) ($entry['badge'] ?? 'TRACE'),
                'target' => 'audit',
                'auditId' => (int) ($entry['id'] ?? 0),
            ];
        }

        foreach (array_slice(array_reverse($messages), 0, 5) as $message) {
            $items[] = [
                'id' => 'message-' . (int) ($message['id'] ?? 0),
                'kind' => 'message',
                'tone' => (string) ($message['role'] ?? 'investor') === 'seller' ? 'info' : 'system',
                'title' => sprintf('%s sent a message', (string) ($message['senderName'] ?? 'Platform User')),
                'summary' => $this->truncate((string) ($message['text'] ?? ''), 140),
                'actorName' => (string) ($message['senderName'] ?? 'Platform User'),
                'actorRole' => (string) ($message['role'] ?? 'system'),
                'createdAt' => (string) ($message['createdAt'] ?? ''),
                'badge' => 'THREAD',
                'target' => 'messaging',
            ];
        }

        foreach (array_slice($documentRequests, 0, 5) as $request) {
            $status = strtolower((string) ($request['status'] ?? 'requested'));
            $items[] = [
                'id' => 'document-' . (int) ($request['id'] ?? 0),
                'kind' => 'document',
                'tone' => in_array($status, ['fulfilled'], true) ? 'success' : 'system',
                'title' => sprintf('Document request: %s', (string) ($request['documentName'] ?? 'Supporting file')),
                'summary' => sprintf('%s marked this request as %s.', (string) ($request['requesterName'] ?? 'A user'), $this->humanize($status)),
                'actorName' => (string) ($request['requesterName'] ?? 'Platform User'),
                'actorRole' => (string) ($request['requesterRole'] ?? 'system'),
                'createdAt' => (string) ($request['updatedAt'] ?? $request['createdAt'] ?? ''),
                'badge' => strtoupper($status),
                'target' => 'documents',
            ];
        }

        if (is_array($visit) && is_array($visit['activity'] ?? null)) {
            foreach (array_slice(array_reverse($visit['activity']), 0, 6) as $event) {
                $items[] = [
                    'id' => 'visit-' . md5(json_encode($event)),
                    'kind' => 'visit',
                    'tone' => $this->visitTone((string) ($event['status'] ?? $visit['status'] ?? 'proposed')),
                    'title' => (string) ($event['title'] ?? 'Visit update'),
                    'summary' => (string) ($event['summary'] ?? ''),
                    'actorName' => (string) ($event['actorName'] ?? 'Logistics Engine'),
                    'actorRole' => (string) ($event['actorRole'] ?? 'system'),
                    'createdAt' => (string) ($event['createdAt'] ?? $visit['updatedAt'] ?? ''),
                    'badge' => strtoupper((string) ($event['status'] ?? $visit['status'] ?? 'visit')),
                    'target' => 'visits',
                ];
            }
        }

        usort($items, fn (array $left, array $right): int => $this->timestampValue((string) ($right['createdAt'] ?? '')) <=> $this->timestampValue((string) ($left['createdAt'] ?? '')));

        return array_slice($items, 0, 18);
    }

    private function buildTrust(array $property, array $auditLogs, array $conversation, array $documentRequests, ?array $visit): array
    {
        $lastModeration = null;
        $lastApproval = null;

        foreach ($auditLogs as $entry) {
            if ($lastModeration === null && (string) ($entry['badge'] ?? '') === 'MODERATED') {
                $lastModeration = $entry;
            }
            if ($lastApproval === null && (string) ($entry['actionType'] ?? '') === 'APPROVE') {
                $lastApproval = $entry;
            }
        }

        return [
            'sellerIdentityStatus' => (string) ($property['sellerIdentityStatus'] ?? 'unverified'),
            'listingVerificationStatus' => (string) ($property['listingVerificationStatus'] ?? 'unverified'),
            'approvalState' => (string) ($property['approvalState'] ?? 'approved'),
            'documentCompletenessPct' => (int) ($property['documentCompletenessPct'] ?? 0),
            'dueDiligencePct' => (int) ($property['dueDiligencePct'] ?? 0),
            'auditLogCount' => count($auditLogs),
            'openDocumentRequestCount' => count(array_filter($documentRequests, static fn (array $request): bool => in_array((string) ($request['status'] ?? ''), ['requested', 'in_review'], true))),
            'messageCount' => (int) (($conversation['summary']['messageCount'] ?? 0)),
            'threadCount' => (int) (($conversation['summary']['threadCount'] ?? 0)),
            'fieldAuditComplete' => (bool) ($visit['fieldAuditComplete'] ?? false),
            'groundTruthVisitCount' => (int) ($property['groundTruthVisitCount'] ?? 0),
            'documentsReviewedAt' => string_or_null($property['documentsReviewedAt'] ?? null),
            'siteVerifiedAt' => string_or_null($property['siteVerifiedAt'] ?? null),
            'lastConfirmedAvailableAt' => string_or_null($property['lastConfirmedAvailableAt'] ?? null),
            'lastModeration' => $lastModeration,
            'lastApproval' => $lastApproval,
        ];
    }

    private function buildNextActions(array $property, array $blockers, array $conversation, array $documentRequests, ?array $visit): array
    {
        $investor = [];
        $seller = [];
        $admin = [];

        if (($conversation['thread'] ?? null) === null) {
            $investor[] = [
                'label' => 'Open direct thread',
                'reason' => 'There is still no investor inquiry trail attached to this property.',
                'urgency' => 'high',
                'target' => 'messaging',
            ];
        }

        if ($visit === null) {
            $investor[] = [
                'label' => 'Propose site visit',
                'reason' => 'Ground truth is still missing from the investment loop.',
                'urgency' => 'high',
                'target' => 'visits',
            ];
        } elseif (strtolower((string) ($visit['status'] ?? 'proposed')) === 'visited' && !($visit['fieldAuditComplete'] ?? false)) {
            $investor[] = [
                'label' => 'Submit field audit',
                'reason' => 'The walkthrough is complete, but the multiplier has not been finalized.',
                'urgency' => 'high',
                'target' => 'visits',
            ];
        }

        if ((int) ($property['documentCompletenessPct'] ?? 0) < 100) {
            $seller[] = [
                'label' => 'Upload missing files',
                'reason' => 'Document completeness is still below the fully investable threshold.',
                'urgency' => 'high',
                'target' => 'documents',
            ];
        }

        if (count(array_filter($documentRequests, static fn (array $request): bool => in_array((string) ($request['status'] ?? ''), ['requested', 'in_review'], true))) > 0) {
            $seller[] = [
                'label' => 'Respond to request queue',
                'reason' => 'Open diligence requests are waiting on seller-side action.',
                'urgency' => 'medium',
                'target' => 'documents',
            ];
        }

        if (is_array($visit) && in_array(strtolower((string) ($visit['status'] ?? 'proposed')), ['proposed', 'counter_offered', 'confirmed'], true)) {
            $seller[] = [
                'label' => 'Advance visit logistics',
                'reason' => 'The field visit has not cleared the logistics workflow yet.',
                'urgency' => 'medium',
                'target' => 'visits',
            ];
        }

        if (strtolower((string) ($property['approvalState'] ?? 'approved')) !== 'approved') {
            $admin[] = [
                'label' => 'Resolve approval state',
                'reason' => 'Moderation is still gating the listing.',
                'urgency' => 'high',
                'target' => 'trust',
            ];
        }

        if (strtolower((string) ($property['sellerIdentityStatus'] ?? 'unverified')) !== 'verified') {
            $admin[] = [
                'label' => 'Verify seller identity',
                'reason' => 'Trust cannot fully unlock until the seller is verified.',
                'urgency' => 'high',
                'target' => 'trust',
            ];
        }

        if ($admin === []) {
            $admin[] = [
                'label' => 'Review live ledger',
                'reason' => 'No critical governance blocker is open, so audit the recent operating trail.',
                'urgency' => 'normal',
                'target' => 'audit',
            ];
        }

        foreach ($blockers as $blocker) {
            $action = [
                'label' => (string) ($blocker['actionLabel'] ?? 'Resolve blocker'),
                'reason' => (string) ($blocker['summary'] ?? ''),
                'urgency' => (string) ($blocker['severity'] ?? 'medium'),
                'target' => (string) ($blocker['actionTarget'] ?? 'overview'),
            ];
            switch ((string) ($blocker['ownerRole'] ?? 'investor')) {
                case 'seller':
                    $seller[] = $action;
                    break;
                case 'admin':
                    $admin[] = $action;
                    break;
                default:
                    $investor[] = $action;
                    break;
            }
        }

        return [
            'investor' => array_slice($this->uniqueActions($investor), 0, 3),
            'seller' => array_slice($this->uniqueActions($seller), 0, 3),
            'admin' => array_slice($this->uniqueActions($admin), 0, 3),
        ];
    }

    private function uniqueActions(array $items): array
    {
        $seen = [];
        $unique = [];
        foreach ($items as $item) {
            $key = strtolower(sprintf('%s|%s', (string) ($item['label'] ?? ''), (string) ($item['target'] ?? '')));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $item;
        }

        return $unique;
    }

    private function badgeTone(string $badge): string
    {
        return match (strtoupper(trim($badge))) {
            'VERIFIED' => 'success',
            'CRITICAL' => 'danger',
            'MODERATED' => 'trend',
            default => 'system',
        };
    }

    private function visitTone(string $status): string
    {
        return match (strtolower(trim($status))) {
            'visited' => 'success',
            'in_progress' => 'trend',
            'confirmed' => 'info',
            'counter_offered' => 'trend',
            default => 'system',
        };
    }

    private function humanize(string $value): string
    {
        $normalized = trim(str_replace(['_', '-'], ' ', $value));
        return $normalized !== '' ? ucwords($normalized) : 'Unknown';
    }

    private function truncate(string $value, int $limit = 120): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(substr($value, 0, max(1, $limit - 3))) . '...';
    }

    private function timestampValue(string $timestamp): int
    {
        $value = strtotime($timestamp);
        return $value === false ? 0 : $value;
    }

    private function severityWeight(string $severity): int
    {
        return match (strtolower(trim($severity))) {
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            default => 1,
        };
    }

    private function canViewOperationalFeed(?array $user): bool
    {
        $role = (string) ($user['role'] ?? 'guest');
        return in_array($role, ['investor', 'seller', 'admin'], true) && isset($user['id']);
    }
}
