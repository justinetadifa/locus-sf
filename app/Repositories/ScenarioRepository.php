<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ScenarioRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listByProperty(int $propertyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM investment_scenarios
             WHERE property_id = :property_id
             ORDER BY created_at DESC, id DESC'
        );
        $statement->execute(['property_id' => $propertyId]);

        return array_map([$this, 'hydrate'], $statement->fetchAll());
    }

    public function create(array $payload): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO investment_scenarios (
                property_id, name, created_by, budget, sector, size, weights_json, assumptions_json, results_json
             ) VALUES (
                :property_id, :name, :created_by, :budget, :sector, :size, :weights_json, :assumptions_json, :results_json
             )'
        );
        $statement->execute([
            'property_id' => (int) $payload['propertyId'],
            'name' => (string) $payload['name'],
            'created_by' => $payload['createdBy'] ?: 'Local Analyst',
            'budget' => $payload['budget'],
            'sector' => $payload['sector'],
            'size' => $payload['size'],
            'weights_json' => json_encode($payload['weights'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'assumptions_json' => json_encode($payload['assumptions'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'results_json' => json_encode($payload['results'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $select = $this->pdo->prepare('SELECT * FROM investment_scenarios WHERE id = :id LIMIT 1');
        $select->execute(['id' => $id]);

        return $this->hydrate($select->fetch() ?: []);
    }

    private function hydrate(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'propertyId' => (int) ($row['property_id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'createdBy' => (string) ($row['created_by'] ?? 'Local Analyst'),
            'budget' => $row['budget'] !== null ? (int) $row['budget'] : null,
            'sector' => $row['sector'] !== null ? (string) $row['sector'] : null,
            'size' => $row['size'] !== null ? (float) $row['size'] : null,
            'weights' => $this->decodeJson($row['weights_json'] ?? '{}'),
            'assumptions' => $this->decodeJson($row['assumptions_json'] ?? '{}'),
            'results' => $this->decodeJson($row['results_json'] ?? '{}'),
            'createdAt' => (string) ($row['created_at'] ?? ''),
        ];
    }

    private function decodeJson(?string $json): array
    {
        $decoded = json_decode((string) $json, true);
        return is_array($decoded) ? $decoded : [];
    }
}
