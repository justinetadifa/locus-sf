<?php
declare(strict_types=1);

namespace App\Repositories;

use OutOfBoundsException;
use PDO;

final class ShortlistRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function propertyIdsByInvestor(int $investorUserId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT property_id
             FROM property_shortlists
             WHERE investor_user_id = :investor_user_id
             ORDER BY created_at DESC, id DESC'
        );
        $statement->execute(['investor_user_id' => $investorUserId]);

        return array_map(static fn (array $row): int => (int) $row['property_id'], $statement->fetchAll());
    }

    public function add(int $investorUserId, int $propertyId): array
    {
        $this->assertPropertyExists($propertyId);

        $statement = $this->pdo->prepare(
            'INSERT INTO property_shortlists (investor_user_id, property_id)
             VALUES (:investor_user_id, :property_id)
             ON DUPLICATE KEY UPDATE created_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'investor_user_id' => $investorUserId,
            'property_id' => $propertyId,
        ]);

        return $this->propertyIdsByInvestor($investorUserId);
    }

    public function remove(int $investorUserId, int $propertyId): array
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM property_shortlists
             WHERE investor_user_id = :investor_user_id
               AND property_id = :property_id'
        );
        $statement->execute([
            'investor_user_id' => $investorUserId,
            'property_id' => $propertyId,
        ]);

        return $this->propertyIdsByInvestor($investorUserId);
    }

    private function assertPropertyExists(int $propertyId): void
    {
        $statement = $this->pdo->prepare('SELECT id FROM properties WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $propertyId]);

        if (!$statement->fetchColumn()) {
            throw new OutOfBoundsException('Property not found.');
        }
    }
}
