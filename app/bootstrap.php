<?php
declare(strict_types=1);

require_once __DIR__ . '/Support/helpers.php';
require_once __DIR__ . '/Support/SimpleCache.php';
require_once __DIR__ . '/Support/ExternalServices.php';
require_once __DIR__ . '/Core/Database.php';
require_once __DIR__ . '/Core/SchemaManager.php';
require_once __DIR__ . '/Core/ClupSchemaManager.php';
require_once __DIR__ . '/Support/JsonData.php';
require_once __DIR__ . '/Support/AutoSeeder.php';
require_once __DIR__ . '/Support/GoogleEarthService.php';
require_once __DIR__ . '/Support/DecisionEngineService.php';
require_once __DIR__ . '/Support/ClupComplianceService.php';
require_once __DIR__ . '/Support/PropertyCommandCenterService.php';
require_once __DIR__ . '/Repositories/AuditLogRepository.php';
require_once __DIR__ . '/Repositories/ClupGovernanceRepository.php';
require_once __DIR__ . '/Repositories/PropertyRepository.php';
require_once __DIR__ . '/Repositories/MessageRepository.php';
require_once __DIR__ . '/Repositories/ScenarioRepository.php';
require_once __DIR__ . '/Repositories/SellerProfileRepository.php';
require_once __DIR__ . '/Repositories/UserRepository.php';
require_once __DIR__ . '/Repositories/ShortlistRepository.php';
require_once __DIR__ . '/Repositories/VoteOptionRepository.php';
require_once __DIR__ . '/Repositories/ShowcaseRepository.php';
require_once __DIR__ . '/Repositories/DocumentRequestRepository.php';
require_once __DIR__ . '/Repositories/NotificationRepository.php';
require_once __DIR__ . '/Repositories/SpatialOverlayRepository.php';
require_once __DIR__ . '/Repositories/VisitLogRepository.php';
require_once __DIR__ . '/Support/NotificationEngine.php';

use App\Core\Database;
use App\Core\SchemaManager;
use App\Core\ClupSchemaManager;
use App\Repositories\AuditLogRepository;
use App\Repositories\ClupGovernanceRepository;
use App\Repositories\MessageRepository;
use App\Repositories\DocumentRequestRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\PropertyRepository;
use App\Repositories\ScenarioRepository;
use App\Repositories\SellerProfileRepository;
use App\Repositories\ShortlistRepository;
use App\Repositories\SpatialOverlayRepository;
use App\Repositories\ShowcaseRepository;
use App\Repositories\UserRepository;
use App\Repositories\VisitLogRepository;
use App\Repositories\VoteOptionRepository;
use App\Support\AutoSeeder;
use App\Support\DecisionEngineService;
use App\Support\ClupComplianceService;
use App\Support\ExternalServices;
use App\Support\GoogleEarthService;
use App\Support\NotificationEngine;
use App\Support\PropertyCommandCenterService;

$config = require __DIR__ . '/config.php';
$database = new Database($config['db']);
$pdo = $database->pdo();

$autoMigrate = (bool) ($config['app']['auto_migrate'] ?? false);
$autoSeed = (bool) ($config['app']['auto_seed'] ?? false);

if ($autoMigrate) {
    SchemaManager::ensure($pdo);
    ClupSchemaManager::ensure($pdo);
}
if ($autoSeed) {
    AutoSeeder::seedIfNeeded($pdo);
}
$auditLogs = new AuditLogRepository($pdo);
$clupGovernance = new ClupGovernanceRepository($pdo, $auditLogs);
$users = new UserRepository($pdo);
$sellerProfiles = new SellerProfileRepository($pdo);
$properties = new PropertyRepository($pdo, $auditLogs);
$messages = new MessageRepository($pdo, $auditLogs);
$documentRequests = new DocumentRequestRepository($pdo);
$scenarios = new ScenarioRepository($pdo);
$shortlists = new ShortlistRepository($pdo);
$votes = new VoteOptionRepository($pdo, $auditLogs);
$showcase = new ShowcaseRepository($pdo);
$notifications = new NotificationRepository($pdo);
$overlays = new SpatialOverlayRepository($pdo);
$visits = new VisitLogRepository($pdo);
$earth = new GoogleEarthService();
$external = new ExternalServices($config, \App\Support\JsonData::meta());
$decisionEngine = new DecisionEngineService();
$clup = new ClupComplianceService($clupGovernance);
$line = new NotificationEngine($pdo, $notifications);
if ($autoSeed) {
    $line->seedDemoNotificationsIfNeeded();
}
$commandCenter = new PropertyCommandCenterService(
    $properties,
    $votes,
    $messages,
    $documentRequests,
    $visits,
    $notifications,
    $auditLogs,
    $decisionEngine
);

return [
    'config' => $config,
    'pdo' => $pdo,
    'auditLogs' => $auditLogs,
    'clupGovernance' => $clupGovernance,
    'users' => $users,
    'sellerProfiles' => $sellerProfiles,
    'properties' => $properties,
    'messages' => $messages,
    'documentRequests' => $documentRequests,
    'scenarios' => $scenarios,
    'shortlists' => $shortlists,
    'votes' => $votes,
    'showcase' => $showcase,
    'notifications' => $notifications,
    'line' => $line,
    'overlays' => $overlays,
    'visits' => $visits,
    'earth' => $earth,
    'external' => $external,
    'decisionEngine' => $decisionEngine,
    'clup' => $clup,
    'commandCenter' => $commandCenter,
];
