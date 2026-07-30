<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_handle(function (array $container): array {
    $method = request_method();
    $user = sfc_current_user();

    if ($method === 'GET') {
        $includeInactive = ($user['role'] ?? null) === 'admin';
        return [
            'voteOptions' => $container['votes']->all($includeInactive),
        ];
    }

    if (($user['role'] ?? null) !== 'admin') {
        return [403, ['error' => 'Only admin can manage vote options.']];
    }

    $payload = read_request_input();
    $uploadedImagePath = store_uploaded_vote_option_image($_FILES['image_file'] ?? null);
    if ($uploadedImagePath !== null) {
        $payload['image_url'] = $uploadedImagePath;
    }

    if ($method === 'POST') {
        return [
            201,
            [
                'voteOption' => $container['votes']->create($payload, (int) $user['id']),
                'voteOptions' => $container['votes']->all(true),
            ],
        ];
    }

    $voteOptionId = int_or_null($_GET['id'] ?? $_POST['id'] ?? $payload['id'] ?? null);
    if ($voteOptionId === null || $voteOptionId < 1) {
        throw new InvalidArgumentException('A valid vote option id is required.');
    }

    if ($method === 'PUT' || $method === 'PATCH') {
        return [
            'voteOption' => $container['votes']->update($voteOptionId, $payload),
            'voteOptions' => $container['votes']->all(true),
        ];
    }

    if ($method === 'DELETE') {
        $container['votes']->delete($voteOptionId);
        return [
            'voteOptionId' => $voteOptionId,
            'deleted' => true,
            'voteOptions' => $container['votes']->all(true),
        ];
    }

    return [405, ['error' => 'Method not allowed.']];
});
