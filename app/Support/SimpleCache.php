<?php
declare(strict_types=1);

namespace App\Support;

final class SimpleCache
{
    public function __construct(private string $directory)
    {
    }

    public function get(string $key): mixed
    {
        $path = $this->path($key);
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        $expiresAt = (int) ($decoded['expiresAt'] ?? 0);
        if ($expiresAt < time()) {
            return null;
        }

        return $decoded['value'] ?? null;
    }

    public function put(string $key, mixed $value, int $ttlSeconds): void
    {
        $this->ensureDirectory();

        $payload = [
            'expiresAt' => time() + max(60, $ttlSeconds),
            'value' => $value,
        ];

        @file_put_contents(
            $this->path($key),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    public function remember(string $key, int $ttlSeconds, callable $resolver): mixed
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $resolver();
        $this->put($key, $value, $ttlSeconds);
        return $value;
    }

    private function path(string $key): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . sha1($key) . '.json';
    }

    private function ensureDirectory(): void
    {
        if (is_dir($this->directory)) {
            return;
        }

        @mkdir($this->directory, 0775, true);
    }
}
