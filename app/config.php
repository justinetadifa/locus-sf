<?php
declare(strict_types=1);

$normalizeBoolean = static function (mixed $value, bool $fallback): bool {
    if (is_bool($value)) {
        return $value;
    }

    if ($value === null || $value === false || trim((string) $value) === '') {
        return $fallback;
    }

    $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $normalized ?? $fallback;
};

$defaults = [
    'app' => [
        'name' => 'SFCelerate',
        'environment' => getenv('APP_ENV') ?: 'local',
    ],
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_NAME') ?: 'sfcelerate_bizstart',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'services' => [
        'cache' => [
            'path' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'external',
        ],
        'maps' => [
            'tile_url' => 'https://basemaps.cartocdn.com/gl/positron-gl-style/style.json',
            'tile_attribution' => '&copy; CARTO &copy; OpenStreetMap contributors',
            'locationiq_key' => getenv('LOCATIONIQ_KEY') ?: '',
        ],
        'location' => [
            'locationiq_key' => getenv('LOCATIONIQ_KEY') ?: '',
        ],
        'market' => [
            'alpha_vantage_key' => getenv('ALPHA_VANTAGE_KEY') ?: '',
        ],
        'news' => [
            'newsapi_key' => getenv('NEWSAPI_KEY') ?: '',
            'query' => getenv('NEWSAPI_QUERY') ?: '("San Fernando" OR "La Union" OR Philippines) AND (business OR investment OR property OR infrastructure)',
        ],
        'ai' => [
            'provider' => getenv('AI_PROVIDER') ?: '',
            'gemini_key' => getenv('GEMINI_API_KEY') ?: '',
            'gemini_model' => getenv('GEMINI_MODEL') ?: 'gemini-2.5-flash',
            'openrouter_key' => getenv('OPENROUTER_API_KEY') ?: '',
            'openrouter_model' => getenv('OPENROUTER_MODEL') ?: '',
        ],
        'media' => [
            'cloudinary' => [
                'cloud_name' => getenv('CLOUDINARY_CLOUD_NAME') ?: '',
                'api_key' => getenv('CLOUDINARY_API_KEY') ?: '',
                'api_secret' => getenv('CLOUDINARY_API_SECRET') ?: '',
                'folder' => getenv('CLOUDINARY_FOLDER') ?: 'sfcelerate-bizstart/properties',
            ],
        ],
        'weather' => [
            'openweather_key' => getenv('OPENWEATHER_API_KEY') ?: '',
            'units' => getenv('OPENWEATHER_UNITS') ?: 'metric',
        ],
    ],
];

$localConfigPath = __DIR__ . '/config.local.php';
$local = [];
if (is_file($localConfigPath)) {
    $local = require $localConfigPath;
    if (is_array($local)) {
        $defaults = array_replace_recursive($defaults, $local);
    }
}

$environment = strtolower(trim((string) ($defaults['app']['environment'] ?? 'local')));
$environment = $environment !== '' ? $environment : 'local';
$defaults['app']['environment'] = $environment;

$appFlagDefaults = [
    'debug' => $environment !== 'production',
    'auto_migrate' => $environment !== 'production',
    'auto_seed' => $environment === 'local',
];
$appFlagEnvironmentVariables = [
    'debug' => 'APP_DEBUG',
    'auto_migrate' => 'APP_AUTO_MIGRATE',
    'auto_seed' => 'APP_AUTO_SEED',
];
$localAppConfig = is_array($local['app'] ?? null) ? $local['app'] : [];

foreach ($appFlagDefaults as $flag => $fallback) {
    $environmentValue = getenv($appFlagEnvironmentVariables[$flag]);
    if ($environmentValue !== false && trim((string) $environmentValue) !== '') {
        $defaults['app'][$flag] = $normalizeBoolean($environmentValue, $fallback);
        continue;
    }

    if (array_key_exists($flag, $localAppConfig)) {
        $defaults['app'][$flag] = $normalizeBoolean($localAppConfig[$flag], $fallback);
        continue;
    }

    $defaults['app'][$flag] = $fallback;
}

return $defaults;
