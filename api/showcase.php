<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_handle(function (array $container): array {
    $method = request_method();
    $user = sfc_current_user();

    if ($method === 'GET') {
        $featureType = string_or_null($_GET['featureType'] ?? $_GET['feature_type'] ?? null);
        return [
            'items' => $container['showcase']->all($user, $featureType),
        ];
    }

    if (($user['role'] ?? null) !== 'admin') {
        return [403, ['error' => 'Only admin can manage showcase items.']];
    }

    $payload = read_request_input();
    $uploadedImagePath = store_uploaded_showcase_image($_FILES['image_file'] ?? null);
    if ($uploadedImagePath !== null) {
        $payload['cover_image_url'] = $uploadedImagePath;
    }

    if ($method === 'POST') {
        return [
            201,
            [
                'item' => $container['showcase']->create($payload, $user),
                'items' => $container['showcase']->all($user),
            ],
        ];
    }

    return [405, ['error' => 'Method not allowed.']];
});
