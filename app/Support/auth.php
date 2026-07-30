<?php
declare(strict_types=1);

use App\Repositories\UserRepository;
use App\Repositories\SellerProfileRepository;

function sfc_start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $secure = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function sfc_csrf_token(): string
{
    sfc_start_session();
    $token = $_SESSION['sfc_csrf_token'] ?? null;
    if (!is_string($token) || strlen($token) < 32) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['sfc_csrf_token'] = $token;
    }
    return $token;
}

function sfc_verify_csrf_token(mixed $token): bool
{
    $expected = sfc_csrf_token();
    return is_string($token) && $token !== '' && hash_equals($expected, $token);
}

function sfc_verify_csrf_request(): bool
{
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    $body = $_POST['_csrf'] ?? null;
    return sfc_verify_csrf_token(is_string($header) && $header !== '' ? $header : $body);
}

function sfc_require_csrf_form(): void
{
    if (!sfc_verify_csrf_request()) {
        throw new InvalidArgumentException('Your security token expired. Refresh the page and try again.');
    }
}

function sfc_app_container(): array
{
    static $container = null;
    if ($container === null) {
        $globalContainer = $GLOBALS['container'] ?? null;
        if (is_array($globalContainer) && isset($globalContainer['users'])) {
            $container = $globalContainer;
        } else {
            $container = require dirname(__DIR__) . '/bootstrap.php';
        }
    }

    return $container;
}

function sfc_user_repository(): UserRepository
{
    return sfc_app_container()['users'];
}

function sfc_seller_profile_repository(): SellerProfileRepository
{
    return sfc_app_container()['sellerProfiles'];
}

function sfc_demo_credentials(): array
{
    return [
        'admin' => [
            'email' => 'admin@sfcelerate.local',
            'password' => 'Admin123!',
            'name' => 'SFC Admin',
        ],
        'seller' => [
            'email' => 'seller@sfcelerate.local',
            'password' => 'Seller123!',
            'name' => 'Seller Studio',
        ],
        'investor' => [
            'email' => 'investor@sfcelerate.local',
            'password' => 'Investor123!',
            'name' => 'Investor Resident Hub',
        ],
    ];
}

function sfc_login(string $role, string $email, string $password): bool
{
    sfc_start_session();
    $user = sfc_user_repository()->authenticate($email, $password, $role);
    if ($user === null) {
        return false;
    }

    session_regenerate_id(true);
    unset($_SESSION['sfc_csrf_token']);
    $_SESSION['sfc_user'] = sfc_user_session_payload($user);
    $_SESSION['sfc_authenticated_at'] = time();
    $_SESSION['sfc_last_activity_at'] = time();
    sfc_csrf_token();
    return true;
}

function sfc_register_investor(string $name, string $email, string $password, string $confirmPassword): array
{
    sfc_start_session();

    $name = trim($name);
    $email = strtolower(trim($email));

    if ($name === '') {
        throw new InvalidArgumentException('Your full name is required.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('A valid email address is required.');
    }
    if (strlen($password) < 8) {
        throw new InvalidArgumentException('Password must be at least 8 characters.');
    }
    if ($password !== $confirmPassword) {
        throw new InvalidArgumentException('Password confirmation does not match.');
    }

    $user = sfc_user_repository()->create('investor', $name, $email, $password);
    session_regenerate_id(true);
    unset($_SESSION['sfc_csrf_token']);
    $_SESSION['sfc_user'] = sfc_user_session_payload($user);
    $_SESSION['sfc_authenticated_at'] = time();
    $_SESSION['sfc_last_activity_at'] = time();
    sfc_csrf_token();

    return $user;
}

function sfc_register_seller(array $payload): array
{
    sfc_start_session();

    $name = trim((string) ($payload['name'] ?? ''));
    $email = strtolower(trim((string) ($payload['email'] ?? '')));
    $password = (string) ($payload['password'] ?? '');
    $confirmPassword = (string) ($payload['confirm_password'] ?? $payload['confirmPassword'] ?? '');

    if ($name === '') {
        throw new InvalidArgumentException('Your full name is required.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('A valid email address is required.');
    }
    if (strlen($password) < 8) {
        throw new InvalidArgumentException('Password must be at least 8 characters.');
    }
    if ($password !== $confirmPassword) {
        throw new InvalidArgumentException('Password confirmation does not match.');
    }

    $user = sfc_user_repository()->create('seller', $name, $email, $password);
    $profile = sfc_seller_profile_repository()->createOrUpdateForUser((int) $user['id'], $payload, true);
    $user = sfc_user_repository()->updateIdentityVerificationStatus((int) $user['id'], 'pending');
    session_regenerate_id(true);
    unset($_SESSION['sfc_csrf_token']);
    $_SESSION['sfc_user'] = sfc_user_session_payload($user);
    $_SESSION['sfc_authenticated_at'] = time();
    $_SESSION['sfc_last_activity_at'] = time();
    sfc_csrf_token();

    $container = sfc_app_container();
    $adminIds = array_values(array_filter(array_map(
        static fn (array $admin): int => (int) ($admin['id'] ?? 0),
        $container['users']->allByRole('admin')
    )));
    if ($adminIds !== []) {
        $container['notifications']->createForUsers($adminIds, [
            'category' => 'operational',
            'kind' => 'seller_application',
            'priority' => 'high',
            'tone' => 'info',
            'icon' => 'seller',
            'title' => 'New seller application',
            'body' => sprintf(
                '%s submitted a seller verification profile for review.',
                $profile['legalName'] ?: $user['name']
            ),
            'actionLabel' => 'Review seller',
            'actionUrl' => 'admin-dashboard.php',
            'actorUserId' => (int) $user['id'],
            'meta' => [
                'sellerUserId' => (int) $user['id'],
                'applicationStatus' => $profile['applicationStatus'] ?? 'pending_review',
            ],
        ]);
    }

    return [
        'user' => $user,
        'profile' => $profile,
    ];
}

function sfc_logout(): void
{
    sfc_start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function sfc_current_user(): ?array
{
    sfc_start_session();
    $now = time();
    $authenticatedAt = (int) ($_SESSION['sfc_authenticated_at'] ?? $now);
    $lastActivityAt = (int) ($_SESSION['sfc_last_activity_at'] ?? $now);
    if (($now - $lastActivityAt) > 7200 || ($now - $authenticatedAt) > 43200) {
        sfc_logout();
        return null;
    }
    $_SESSION['sfc_last_activity_at'] = $now;
    $sessionUser = $_SESSION['sfc_user'] ?? null;
    if (!is_array($sessionUser)) {
        return null;
    }

    $userId = isset($sessionUser['id']) ? (int) $sessionUser['id'] : 0;
    if ($userId > 0) {
        $user = sfc_user_repository()->findById($userId);
        if ($user !== null) {
            $_SESSION['sfc_user'] = sfc_user_session_payload($user);
            return $_SESSION['sfc_user'];
        }
    }

    $email = isset($sessionUser['email']) ? (string) $sessionUser['email'] : '';
    if ($email !== '') {
        $user = sfc_user_repository()->findByEmail($email);
        if ($user !== null) {
            $_SESSION['sfc_user'] = sfc_user_session_payload($user);
            return $_SESSION['sfc_user'];
        }
    }

    unset($_SESSION['sfc_user']);
    return null;
}

function sfc_current_role(): ?string
{
    return sfc_current_user()['role'] ?? null;
}

function sfc_has_role(string|array $roles): bool
{
    $current = sfc_current_role();
    if ($current === null) {
        return false;
    }

    $allowed = is_array($roles) ? $roles : [$roles];
    return in_array($current, $allowed, true);
}

function sfc_require_role(string $role, string $redirectPath): void
{
    if (!sfc_has_role($role)) {
        header('Location: ' . $redirectPath);
        exit;
    }
}

function sfc_require_any_role(array $roles, string $redirectPath): void
{
    if (!sfc_has_role($roles)) {
        header('Location: ' . $redirectPath);
        exit;
    }
}

function sfc_user_session_payload(array $user): array
{
    return [
        'id' => (int) ($user['id'] ?? 0),
        'role' => (string) ($user['role'] ?? 'guest'),
        'name' => (string) ($user['name'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
        'identityVerificationStatus' => (string) ($user['identityVerificationStatus'] ?? 'unverified'),
        'identityVerifiedAt' => (string) ($user['identityVerifiedAt'] ?? ''),
    ];
}
