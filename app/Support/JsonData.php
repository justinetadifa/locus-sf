<?php
declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class JsonData
{
    private static array $cache = [];

    public static function meta(): array
    {
        return self::load('meta.json');
    }

    public static function properties(): array
    {
        return self::load('properties.json');
    }

    public static function sampleData(): array
    {
        return self::load('sample-data.json');
    }

    private static function load(string $fileName): array
    {
        if (array_key_exists($fileName, self::$cache)) {
            return self::$cache[$fileName];
        }

        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . $fileName;
        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException("Unable to load JSON seed file: {$fileName}");
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException("Invalid JSON seed file: {$fileName}");
        }

        self::$cache[$fileName] = $decoded;
        return $decoded;
    }
}
