<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_handle(function (array $container): array {
    $user = sfc_current_user();
    if (($user['role'] ?? null) !== 'investor') {
        return [403, ['error' => 'Only logged-in investor accounts can manage the shortlist cart.']];
    }

    $method = request_method();
    $investorUserId = (int) $user['id'];

    if ($method === 'GET') {
        return [
            'propertyIds' => $container['shortlists']->propertyIdsByInvestor($investorUserId),
        ];
    }

    $input = read_json_input();
    $propertyId = int_or_null($input['propertyId'] ?? null);
    if ($propertyId === null || $propertyId < 1) {
        throw new InvalidArgumentException('A valid property id is required.');
    }

    if ($method === 'POST') {
        return [
            'propertyIds' => $container['shortlists']->add($investorUserId, $propertyId),
            'added' => true,
        ];
    }

    if ($method === 'DELETE') {
        return [
            'propertyIds' => $container['shortlists']->remove($investorUserId, $propertyId),
            'removed' => true,
        ];
    }

    return [405, ['error' => 'Method not allowed.']];
});
