<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_handle(function (array $container): array {
    $method = request_method();
    $user = sfc_current_user();

    if ($method === 'GET' && !isset($_GET['propertyId'])) {
        return ['catalog' => $container['clup']->catalog()];
    }

    $input = $method === 'POST' ? read_json_input() : $_GET;
    $persist = $method === 'POST' && filter_var($input['persist'] ?? false, FILTER_VALIDATE_BOOLEAN);
    if ($persist && $user === null) {
        return [401, ['error' => 'Sign in before saving a CLUP evaluation snapshot.']];
    }

    $pairs = [];
    if (is_array($input['pairs'] ?? null)) {
        foreach ($input['pairs'] as $pair) {
            if (!is_array($pair)) {
                throw new InvalidArgumentException('Each batch item must contain a candidate site and proposed use.');
            }
            $pairs[] = $pair;
        }
    } elseif (is_array($input['propertyIds'] ?? null)) {
        $use = string_or_null($input['investmentType'] ?? $input['proposedUseCode'] ?? null);
        foreach ($input['propertyIds'] as $propertyId) {
            $pairs[] = ['propertyId' => $propertyId, 'investmentType' => $use];
        }
    } else {
        $pairs[] = $input;
    }

    if ($pairs === [] || count($pairs) > 100) {
        throw new InvalidArgumentException('Evaluate between 1 and 100 candidate site/use pairs per request.');
    }

    $evaluations = [];
    foreach ($pairs as $pair) {
        $propertyId = int_or_null($pair['propertyId'] ?? null);
        $use = string_or_null($pair['investmentType'] ?? $pair['proposedUseCode'] ?? null);
        if ($propertyId === null || $propertyId < 1 || $use === null) {
            throw new InvalidArgumentException('Candidate site and proposed investment use are required for every evaluation.');
        }

        $property = $container['properties']->find($propertyId, $user);
        $result = $persist
            ? $container['clup']->persistEvaluation($property, $use, $user)
            : $container['clup']->evaluate($property, $use);
        $evaluations[$propertyId . ':' . $result['proposedInvestmentType']] = $result;
    }

    $response = [
        'evaluations' => $evaluations,
        'count' => count($evaluations),
        'engineVersion' => \App\Support\ClupComplianceService::ENGINE_VERSION,
        'generatedAt' => gmdate(DATE_ATOM),
    ];
    if (count($evaluations) === 1) {
        $response['compliance'] = reset($evaluations);
    }
    return $response;
});
