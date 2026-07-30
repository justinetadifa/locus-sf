<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_handle(function (array $container): array {
    $user = sfc_current_user();
    if ($user === null || !isset($user['id'])) {
        return [403, ['error' => 'A logged-in account is required to view notifications.']];
    }

    $userId = (int) $user['id'];
    $method = request_method();

    if ($method === 'GET') {
        $limit = int_or_null($_GET['limit'] ?? null) ?? 40;

        return [
            'notifications' => $container['notifications']->feedForUser($userId, $limit),
            'unreadCount' => $container['notifications']->unreadCount($userId),
            'preferences' => $container['notifications']->preferencesForUser($userId),
            'generatedAt' => gmdate(DATE_ATOM),
        ];
    }

    if (!in_array($method, ['POST', 'PATCH', 'PUT'], true)) {
        return [405, ['error' => 'Method not allowed.']];
    }

    $input = read_json_input();
    $action = strtolower((string) ($input['action'] ?? ''));

    if ($action === 'markread') {
        $notificationId = int_or_null($input['notificationId'] ?? null);
        if ($notificationId === null || $notificationId < 1) {
            throw new InvalidArgumentException('A valid notification id is required.');
        }

        return [
            'notification' => $container['notifications']->markRead($userId, $notificationId),
            'unreadCount' => $container['notifications']->unreadCount($userId),
            'preferences' => $container['notifications']->preferencesForUser($userId),
        ];
    }

    if ($action === 'markallread') {
        return [
            'updatedCount' => $container['notifications']->markAllRead($userId),
            'unreadCount' => $container['notifications']->unreadCount($userId),
            'preferences' => $container['notifications']->preferencesForUser($userId),
        ];
    }

    if ($action === 'updatecadence') {
        $cadence = string_or_null($input['notificationCadence'] ?? $input['cadence'] ?? null);
        if ($cadence === null) {
            throw new InvalidArgumentException('A notification cadence is required.');
        }

        return [
            'preferences' => $container['notifications']->updateCadence($userId, $cadence),
            'unreadCount' => $container['notifications']->unreadCount($userId),
        ];
    }

    throw new InvalidArgumentException('Unsupported notification action.');
});
