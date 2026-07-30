<?php
declare(strict_types=1);

namespace App\Repositories;

use InvalidArgumentException;
use PDO;
use PDOException;

final class UserRepository
{
    private const IDENTITY_VERIFICATION_STATUSES = ['unverified', 'pending', 'verified', 'rejected', 'suspended'];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $userId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, role, name, email, password_hash, identity_verification_status, identity_verified_at, created_at, updated_at
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $userId]);

        $row = $statement->fetch();
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, role, name, email, password_hash, identity_verification_status, identity_verified_at, created_at, updated_at
             FROM users
             WHERE LOWER(email) = LOWER(:email)
             LIMIT 1'
        );
        $statement->execute(['email' => trim($email)]);

        $row = $statement->fetch();
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function authenticate(string $email, string $password, ?string $role = null): ?array
    {
        $user = $this->findByEmail($email);
        if ($user === null) {
            return null;
        }

        if ($role !== null && $user['role'] !== $role) {
            return null;
        }

        $hash = (string) ($user['passwordHash'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            return null;
        }

        return $user;
    }

    public function create(string $role, string $name, string $email, string $password): array
    {
        $name = trim($name);
        $email = strtolower(trim($email));
        if ($name === '') {
            throw new InvalidArgumentException('Name is required.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid email address is required.');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters.');
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO users (role, name, email, password_hash, identity_verification_status, identity_verified_at)
             VALUES (:role, :name, :email, :password_hash, :identity_verification_status, :identity_verified_at)'
        );

        try {
            $identityStatus = $role === 'seller' ? 'pending' : 'unverified';
            $statement->execute([
                'role' => $role,
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'identity_verification_status' => $identityStatus,
                'identity_verified_at' => $identityStatus === 'verified' ? gmdate('Y-m-d H:i:s') : null,
            ]);
        } catch (PDOException $exception) {
            if ((int) $exception->getCode() === 23000) {
                throw new InvalidArgumentException('That email is already registered.');
            }

            throw $exception;
        }

        $userId = (int) $this->pdo->lastInsertId();
        $user = $this->findById($userId);

        if ($user === null) {
            throw new InvalidArgumentException('Unable to create the account.');
        }

        return $user;
    }

    public function updateIdentityVerificationStatus(int $userId, string $status): array
    {
        $normalized = $this->normalizeIdentityVerificationStatus($status);

        $statement = $this->pdo->prepare(
            'UPDATE users
             SET identity_verification_status = :identity_verification_status,
                 identity_verified_at = :identity_verified_at
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $userId,
            'identity_verification_status' => $normalized,
            'identity_verified_at' => $normalized === 'verified' ? gmdate('Y-m-d H:i:s') : null,
        ]);

        $user = $this->findById($userId);
        if ($user === null) {
            throw new InvalidArgumentException('User not found.');
        }

        return $user;
    }

    public function allByRole(string $role): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, role, name, email, password_hash, identity_verification_status, identity_verified_at, created_at, updated_at
             FROM users
             WHERE role = :role
             ORDER BY name ASC, email ASC'
        );
        $statement->execute(['role' => $role]);

        return array_map([$this, 'hydrate'], $statement->fetchAll());
    }

    public function firstByRole(string $role): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, role, name, email, password_hash, identity_verification_status, identity_verified_at, created_at, updated_at
             FROM users
             WHERE role = :role
             ORDER BY id ASC
             LIMIT 1'
        );
        $statement->execute(['role' => $role]);

        $row = $statement->fetch();
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function defaultSellerId(): ?int
    {
        return $this->firstByRole('seller')['id'] ?? null;
    }

    private function hydrate(array $row): array
    {
        $identityVerifiedAt = $row['identity_verified_at'] ?? null;

        return [
            'id' => (int) ($row['id'] ?? 0),
            'role' => (string) ($row['role'] ?? 'guest'),
            'name' => (string) ($row['name'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'passwordHash' => (string) ($row['password_hash'] ?? ''),
            'identityVerificationStatus' => $this->normalizeIdentityVerificationStatus((string) ($row['identity_verification_status'] ?? 'unverified')),
            'identityVerifiedAt' => $identityVerifiedAt !== null ? (string) $identityVerifiedAt : null,
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'updatedAt' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function normalizeIdentityVerificationStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        if (!in_array($normalized, self::IDENTITY_VERIFICATION_STATUSES, true)) {
            return 'unverified';
        }

        return $normalized;
    }
}
