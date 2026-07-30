<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_handle(function (array $container): array {
    $method = request_method();
    $user = sfc_current_user();
    $itemId = int_or_null($_GET['id'] ?? $_POST['id'] ?? null);

    if ($itemId === null || $itemId < 1) {
        throw new InvalidArgumentException('A valid showcase item id is required.');
    }

    if ($method === 'GET') {
        return [
            'item' => $container['showcase']->find($itemId, $user),
        ];
    }

    if (($user['role'] ?? null) !== 'admin') {
        return [403, ['error' => 'Only admin can manage showcase items.']];
    }

    if ($method === 'PUT' || $method === 'PATCH') {
        $payload = read_request_input();
        $uploadedImagePath = store_uploaded_showcase_image($_FILES['image_file'] ?? null);
        if ($uploadedImagePath !== null) {
            $payload['cover_image_url'] = $uploadedImagePath;
        }

        return [
            'item' => $container['showcase']->update($itemId, $payload, $user),
            'items' => $container['showcase']->all($user),
        ];
    }

    if ($method === 'DELETE') {
        $container['showcase']->delete($itemId);
        return [
            'itemId' => $itemId,
            'deleted' => true,
            'items' => $container['showcase']->all($user),
        ];
    }

    return [405, ['error' => 'Method not allowed.']];
});
