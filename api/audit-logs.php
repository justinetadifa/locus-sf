<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_handle(function (array $container): array {
    $user = sfc_current_user();
    if (($user['role'] ?? null) !== 'admin') {
        return [403, ['error' => 'Only admin can access the audit ledger.']];
    }

    $limit = max(1, min(120, (int) ($_GET['limit'] ?? 60)));
    $scope = string_or_null($_GET['scope'] ?? null) ?? 'all';
    $afterId = int_or_null($_GET['afterId'] ?? null);

    return [
        'logs' => $container['auditLogs']->recent($limit, $scope, $afterId),
        'latestId' => $container['auditLogs']->latestId(),
        'scope' => $scope,
    ];
});
