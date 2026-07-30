<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_handle(function (array $container): array {
    $method = request_method();
    $user = sfc_current_user();

    if ($method === 'GET') {
        $threadId = int_or_null($_GET['threadId'] ?? null);
        $propertyId = int_or_null($_GET['propertyId'] ?? null);

        if ($threadId !== null && $threadId > 0) {
            return [
                'visit' => $container['visits']->findByThread($threadId, $user),
            ];
        }

        if ($propertyId === null || $propertyId < 1) {
            throw new InvalidArgumentException('A valid property id is required.');
        }

        return [
            'visit' => $container['visits']->latestForProperty($propertyId, $user),
        ];
    }

    if ($user === null || !in_array($user['role'] ?? 'guest', ['investor', 'seller', 'admin'], true)) {
        return [403, ['error' => 'A logged-in account is required to manage site visits.']];
    }

    if ($method === 'POST') {
        $input = read_json_input();
        $propertyId = int_or_null($input['propertyId'] ?? null);
        if ($propertyId === null || $propertyId < 1) {
            throw new InvalidArgumentException('A valid property id is required.');
        }

        $visit = $container['visits']->propose($propertyId, $user, $input);
        $container['line']->onVisitTransition(null, $visit, $user, 'propose');
        $threadPayload = $container['messages']->thread((int) $visit['threadId'], $user);

        return [
            'visit' => $visit,
            'thread' => $threadPayload['thread'] ?? null,
            'messages' => $threadPayload['messages'] ?? [],
        ];
    }

    if ($method === 'PATCH') {
        $input = read_json_input();
        $visitId = int_or_null($input['visitId'] ?? null);
        if ($visitId === null) {
            $threadId = int_or_null($input['threadId'] ?? null);
            if ($threadId !== null && $threadId > 0) {
                $existing = $container['visits']->findByThread($threadId, $user);
                $visitId = int_or_null($existing['id'] ?? null);
            }
        }

        if ($visitId === null || $visitId < 1) {
            throw new InvalidArgumentException('A valid visit id is required.');
        }

        $before = $container['visits']->findById($visitId, $user);
        if (!is_array($before)) {
            throw new OutOfBoundsException('Visit log not found.');
        }

        $visit = $container['visits']->applyAction($visitId, $user, $input);
        $container['line']->onVisitTransition($before, $visit, $user, (string) ($input['action'] ?? ''));
        $threadPayload = $container['messages']->thread((int) $visit['threadId'], $user);

        return [
            'visit' => $visit,
            'thread' => $threadPayload['thread'] ?? null,
            'messages' => $threadPayload['messages'] ?? [],
        ];
    }

    return [405, ['error' => 'Method not allowed.']];
});
