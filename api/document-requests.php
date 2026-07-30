<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_handle(function (array $container): array {
    $method = request_method();
    $user = sfc_current_user();

    if ($method === 'GET') {
        $scope = string_or_null($_GET['scope'] ?? null);
        if ($scope === 'inbox') {
            if ($user === null) {
                return [403, ['error' => 'A logged-in account is required to view document requests.']];
            }

            return [
                'requests' => $container['documentRequests']->inbox($user),
            ];
        }

        $propertyId = int_or_null($_GET['propertyId'] ?? null);
        if ($propertyId === null || $propertyId < 1) {
            throw new InvalidArgumentException('A valid property id is required.');
        }

        return [
            'requests' => $container['documentRequests']->listByProperty($propertyId, $user),
        ];
    }

    if ($method === 'POST') {
        if ($user === null || !in_array($user['role'] ?? 'guest', ['investor', 'admin'], true)) {
            return [403, ['error' => 'Only logged-in investor or admin accounts can request documents.']];
        }

        $input = read_json_input();
        $propertyId = int_or_null($input['propertyId'] ?? null);
        $documentName = string_or_null($input['documentName'] ?? null);
        $note = string_or_null($input['note'] ?? null);

        if ($propertyId === null || $propertyId < 1) {
            throw new InvalidArgumentException('A valid property id is required.');
        }

        $request = $container['documentRequests']->create($propertyId, $user, (string) $documentName, $note);
        $container['line']->onDocumentRequestCreated($request, $user);
        return [
            201,
            [
                'request' => $request,
                'requests' => $container['documentRequests']->listByProperty($propertyId, $user),
            ],
        ];
    }

    if ($method === 'PUT' || $method === 'PATCH') {
        if ($user === null || !in_array($user['role'] ?? 'guest', ['seller', 'admin'], true)) {
            return [403, ['error' => 'Only seller or admin accounts can update document requests.']];
        }

        $input = read_json_input();
        $requestId = int_or_null($input['requestId'] ?? null);
        $status = string_or_null($input['status'] ?? null);
        $responseNote = string_or_null($input['responseNote'] ?? null);

        if ($requestId === null || $requestId < 1) {
            throw new InvalidArgumentException('A valid document request id is required.');
        }
        if ($status === null) {
            throw new InvalidArgumentException('A document request status is required.');
        }

        $beforeRequest = $container['documentRequests']->find($requestId, $user);
        $request = $container['documentRequests']->updateStatus($requestId, $user, $status, $responseNote);
        $container['line']->onDocumentRequestUpdated($beforeRequest, $request, $user);

        return [
            'request' => $request,
            'requests' => $container['documentRequests']->listByProperty((int) $request['propertyId'], $user),
        ];
    }

    return [405, ['error' => 'Method not allowed.']];
});
