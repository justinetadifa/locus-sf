<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_handle(function (array $container): array {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $user = sfc_current_user();

    if ($method === 'GET') {
        $propertyId = int_or_null($_GET['propertyId'] ?? null);
        $idsParam = string_or_null($_GET['ids'] ?? $_GET['propertyIds'] ?? null);
        if ($propertyId === null && $idsParam !== null) {
            $propertyIds = array_values(array_unique(array_filter(
                array_map(static fn (string $value): int => (int) trim($value), explode(',', $idsParam)),
                static fn (int $value): bool => $value > 0
            )));
            if ($propertyIds === []) {
                throw new InvalidArgumentException('At least one valid property id is required.');
            }

            return [
                'tallies' => $container['votes']->voteTalliesMap($propertyIds, $user['id'] ?? null),
            ];
        }

        if ($propertyId === null || $propertyId < 1) {
            throw new InvalidArgumentException('A valid property id is required.');
        }

        $voteState = $container['votes']->voteTallies($propertyId, $user['id'] ?? null);
        return [
            'votes' => $voteState['votes'],
            'selectedVoteOptionId' => $voteState['selectedVoteOptionId'],
        ];
    }

    if ($method !== 'POST') {
        return [405, ['error' => 'Method not allowed.']];
    }

    if (($user['role'] ?? null) !== 'investor') {
        return [403, ['error' => 'Only logged-in investor accounts can cast votes.']];
    }

    $input = read_json_input();
    $propertyId = int_or_null($input['propertyId'] ?? null);
    $voteOptionId = int_or_null($input['voteOptionId'] ?? null);
    $label = string_or_null($input['label'] ?? null);

    if ($propertyId === null || $propertyId < 1) {
        throw new InvalidArgumentException('A valid property id is required.');
    }
    if ($voteOptionId === null && $label === null) {
        throw new InvalidArgumentException('A vote option is required.');
    }

    $beforeVoteState = $container['votes']->voteTallies($propertyId, (int) $user['id']);
    $voteState = $container['votes']->castVote($propertyId, (int) $user['id'], $voteOptionId, $label);
    $resolvedOption = $container['votes']->resolve($voteOptionId, $label);
    $resolvedLabel = $resolvedOption['title'] ?? $label;
    $container['line']->onVoteCast(
        $propertyId,
        $beforeVoteState['votes'] ?? [],
        $voteState['votes'] ?? [],
        $user,
        $resolvedLabel
    );

    return [
        'votes' => $voteState['votes'],
        'selectedVoteOptionId' => $voteState['selectedVoteOptionId'],
    ];
});
