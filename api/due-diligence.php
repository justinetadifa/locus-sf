<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_handle(function (array $container): array {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $user = sfc_current_user();

    if ($method === 'GET') {
        $propertyId = int_or_null($_GET['propertyId'] ?? null);
        if ($propertyId === null || $propertyId < 1) {
            throw new InvalidArgumentException('A valid property id is required.');
        }

        return [
            'state' => $container['properties']->dueDiligenceState($propertyId),
        ];
    }

    if ($method !== 'POST') {
        return [405, ['error' => 'Method not allowed.']];
    }

    $input = read_json_input();
    $propertyId = int_or_null($input['propertyId'] ?? null);
    if ($propertyId === null || $propertyId < 1) {
        throw new InvalidArgumentException('A valid property id is required.');
    }

    if ($user === null || !in_array($user['role'] ?? 'guest', ['admin', 'seller'], true)) {
        return [403, ['error' => 'Only admin or the assigned seller can update due diligence.']];
    }
    if (($user['role'] ?? null) === 'seller' && !$container['properties']->isOwnedBySeller($propertyId, (int) $user['id'])) {
        return [403, ['error' => 'Sellers can only update due diligence for their own listings.']];
    }

    $state = $input['state'] ?? [];
    if (!is_array($state)) {
        throw new InvalidArgumentException('Due diligence state must be an object.');
    }

    $beforeState = $container['properties']->dueDiligenceState($propertyId);
    $afterState = $container['properties']->saveDueDiligenceState($propertyId, $state, $user);
    $container['line']->onDueDiligenceUpdated($propertyId, $beforeState, $afterState, $user);

    return [
        'state' => $afterState,
    ];
});
