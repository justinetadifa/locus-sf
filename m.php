<?php
declare(strict_types=1);

$propertyId = (int) ($_GET['p'] ?? 0);
$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/m.php'));
$basePath = rtrim(str_replace('/m.php', '', $scriptName), '/');
$basePath = $basePath === '' ? '' : $basePath;

$target = $propertyId > 0
    ? ($basePath . '/property-details.php?' . http_build_query(['id' => $propertyId]) . '#propertyMessagingSection')
    : ($basePath . '/property-explorer.php');

header('Location: ' . $target, true, 302);
exit;
