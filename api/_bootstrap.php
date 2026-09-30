<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (!function_exists('api_raw_json_response')) {
    function api_raw_json_response(array $payload, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

$apiDebug = false;

register_shutdown_function(static function () use (&$apiDebug): void {
    $error = error_get_last();
    if (!$error) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($error['type'] ?? 0, $fatalTypes, true)) {
        return;
    }

    error_log(sprintf(
        '[LOCUS-SF API fatal] %s in %s:%d',
        (string) ($error['message'] ?? 'Unknown fatal error'),
        (string) ($error['file'] ?? 'unknown file'),
        (int) ($error['line'] ?? 0)
    ));

    if (!headers_sent()) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $payload = ['error' => 'Internal server error.'];
        if ($apiDebug) {
            $payload = [
                'error' => 'Fatal PHP error.',
                'details' => $error['message'] ?? 'Unknown fatal error.',
                'file' => $error['file'] ?? null,
                'line' => $error['line'] ?? null,
            ];
        }
        api_raw_json_response($payload, 500);
    }
});

$apiConfig = require __DIR__ . '/../app/config.php';
$apiDebug = (bool) ($apiConfig['app']['debug'] ?? false);

require_once __DIR__ . '/../app/Support/auth.php';

$container = require __DIR__ . '/../app/bootstrap.php';

function app_container(): array
{
    global $container;
    return $container;
}

function api_handle(callable $callback): void
{
    try {
        $method = request_method();
        if ($method === 'OPTIONS') {
            respond_json(['ok' => true]);
            return;
        }
        if (!in_array($method, ['GET', 'HEAD'], true) && !sfc_verify_csrf_request()) {
            respond_json(['error' => 'Invalid or expired security token. Refresh the page and try again.'], 419);
            return;
        }
        $result = $callback(app_container());

        if (
            is_array($result) &&
            isset($result[0], $result[1]) &&
            count($result) === 2 &&
            is_int($result[0]) &&
            is_array($result[1])
        ) {
            respond_json($result[1], $result[0]);
            return;
        }

        if (is_array($result)) {
            respond_json($result);
            return;
        }

        respond_json(['error' => 'Invalid API response.'], 500);
    } catch (InvalidArgumentException $exception) {
        respond_json(['error' => $exception->getMessage()], 400);
    } catch (OutOfBoundsException $exception) {
        respond_json(['error' => $exception->getMessage()], 404);
    } catch (Throwable $exception) {
        error_log(sprintf(
            '[LOCUS-SF API exception] %s: %s in %s:%d',
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        ));

        $payload = ['error' => 'Internal server error.'];
        if ($GLOBALS['apiDebug'] ?? false) {
            $payload = [
                'error' => $exception->getMessage(),
                'type' => get_class($exception),
            ];
        }
        respond_json($payload, 500);
    }
}
