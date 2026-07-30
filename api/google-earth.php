<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $container = app_container();
    $user = sfc_current_user();

    $propertyRepository = $container['properties'];
    $overlayRepository = $container['overlays'];
    $earth = $container['earth'];

    $propertyId = isset($_GET['propertyId']) ? (int) $_GET['propertyId'] : 0;
    $ids = array_values(array_filter(array_map(
        static fn (string $value): int => (int) trim($value),
        explode(',', (string) ($_GET['ids'] ?? ''))
    )));
    if ($propertyId > 0) {
        $ids[] = $propertyId;
    }
    $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));

    if ($ids === []) {
        throw new InvalidArgumentException('At least one property id is required.');
    }

    $scope = trim((string) ($_GET['scope'] ?? 'properties'));
    $requestedFormat = strtolower(trim((string) ($_GET['format'] ?? 'kml')));
    $format = in_array($requestedFormat, ['kml', 'kmz'], true) ? $requestedFormat : 'kml';
    $includeOverlays = !isset($_GET['includeOverlays']) || filter_var($_GET['includeOverlays'], FILTER_VALIDATE_BOOLEAN);
    $overlayTypes = array_values(array_filter(array_map(
        static fn (string $value): string => trim(strtolower($value)),
        explode(',', (string) ($_GET['overlayTypes'] ?? ''))
    )));

    $availableProperties = $propertyRepository->all($user);
    $propertyMap = [];
    foreach ($availableProperties as $property) {
        $propertyMap[(int) ($property['id'] ?? 0)] = $property;
    }

    $properties = [];
    foreach ($ids as $id) {
        if (isset($propertyMap[$id])) {
            $properties[] = $propertyMap[$id];
        }
    }

    if ($properties === []) {
        throw new OutOfBoundsException('No visible properties matched the requested export.');
    }

    $votesMap = [];
    foreach ($properties as $property) {
        $id = (int) ($property['id'] ?? 0);
        if ($id > 0) {
            $votesMap[$id] = $propertyRepository->voteTallies($id);
        }
    }

    $overlays = $includeOverlays ? $overlayRepository->forProperties($properties, $overlayTypes) : [];
    $kml = $earth->buildKml($properties, [
        'scope' => $scope,
        'votesMap' => $votesMap,
        'overlays' => $overlays,
    ]);

    $binary = null;
    $actualFormat = $format;
    if ($format === 'kmz') {
        $binary = $earth->buildKmzBinary($kml);
        if ($binary === null) {
            $actualFormat = 'kml';
        }
    }

    $fileName = $earth->exportFileName($properties, $scope, $actualFormat);
    if (!headers_sent()) {
        http_response_code(200);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . addslashes($fileName) . '"');
    }

    if ($actualFormat === 'kmz' && $binary !== null) {
        if (!headers_sent()) {
            header('Content-Type: application/vnd.google-earth.kmz');
            header('Content-Length: ' . strlen($binary));
        }
        echo $binary;
        return;
    }

    if (!headers_sent()) {
        header('Content-Type: application/vnd.google-earth.kml+xml; charset=utf-8');
        header('Content-Length: ' . strlen($kml));
    }
    echo $kml;
} catch (InvalidArgumentException $exception) {
    respond_json(['error' => $exception->getMessage()], 400);
} catch (OutOfBoundsException $exception) {
    respond_json(['error' => $exception->getMessage()], 404);
} catch (Throwable $exception) {
    respond_json([
        'error' => $exception->getMessage(),
        'type' => get_class($exception),
    ], 500);
}
