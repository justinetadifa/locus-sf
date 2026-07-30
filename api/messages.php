<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_handle(function (array $container): array {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $user = sfc_current_user();
    $attachVisit = static function (array $payload, ?array $currentUser, ?int $propertyId = null) use ($container): array {
        $thread = is_array($payload['thread'] ?? null) ? $payload['thread'] : null;
        $visit = null;
        if ($thread !== null && isset($thread['id'])) {
            $visit = $container['visits']->findByThread((int) $thread['id'], $currentUser);
        } elseif ($propertyId !== null && $propertyId > 0) {
            $visit = $container['visits']->latestForProperty($propertyId, $currentUser);
        }

        $payload['visit'] = $visit;
        return $payload;
    };

    if ($method === 'GET') {
        $scope = string_or_null($_GET['scope'] ?? null);
        $threadId = int_or_null($_GET['threadId'] ?? null);
        $propertyId = int_or_null($_GET['propertyId'] ?? null);

        if ($scope === 'inbox') {
            if ($user === null || !in_array($user['role'] ?? 'guest', ['investor', 'seller', 'admin'], true)) {
                return [403, ['error' => 'A logged-in account is required to view the conversation inbox.']];
            }

            return [
                'threads' => $container['messages']->inbox($user),
            ];
        }

        if ($threadId !== null && $threadId > 0) {
            if ($user === null) {
                return [403, ['error' => 'A logged-in account is required to view this conversation.']];
            }

            return $attachVisit($container['messages']->thread($threadId, $user), $user);
        }

        if ($propertyId === null || $propertyId < 1) {
            throw new InvalidArgumentException('A valid property id is required.');
        }

        return $attachVisit($container['messages']->propertyConversation($propertyId, $user), $user, $propertyId);
    }

    if ($method === 'DELETE') {
        if (($user['role'] ?? null) !== 'admin') {
            return [403, ['error' => 'Only admin can clear a conversation thread.']];
        }

        $input = read_json_input();
        $threadId = int_or_null($input['threadId'] ?? null);
        if ($threadId === null || $threadId < 1) {
            throw new InvalidArgumentException('A valid thread id is required.');
        }

        $container['messages']->clearThread($threadId, $user);
        return ['threadId' => $threadId, 'cleared' => true];
    }

    if ($method !== 'POST') {
        return [405, ['error' => 'Method not allowed.']];
    }

    if ($user === null || !in_array($user['role'] ?? 'guest', ['investor', 'seller', 'admin'], true)) {
        return [403, ['error' => 'A logged-in account is required to send a message.']];
    }

    $input = read_json_input();
    $threadId = int_or_null($input['threadId'] ?? null);
    $propertyId = int_or_null($input['propertyId'] ?? null);
    $recipientUserId = int_or_null($input['recipientUserId'] ?? $input['recipient_user_id'] ?? null);
    $text = string_or_null($input['text'] ?? null);

    if ($text === null) {
        throw new InvalidArgumentException('A message body is required.');
    }

    if ($threadId !== null && $threadId > 0) {
        $result = $container['messages']->replyToThread($threadId, $user, $text, $recipientUserId);
        $container['line']->onMessageSent($result, $user, false);
        return $attachVisit($result, $user);
    }

    if ($propertyId === null || $propertyId < 1) {
        throw new InvalidArgumentException('A valid property id is required.');
    }

    $existingConversation = $container['messages']->propertyConversation($propertyId, $user);
    $isNewConversation = !is_array($existingConversation['thread'] ?? null);
    $result = $container['messages']->sendToProperty($propertyId, $user, $text);
    $container['line']->onMessageSent($result, $user, $isNewConversation);

    return $attachVisit($result, $user, $propertyId);
});
