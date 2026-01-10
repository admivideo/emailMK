<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CampaignPublisher;
use PDO;

final class CampaignController
{
    public function __construct(private PDO $connection)
    {
    }

    public function list(): array
    {
        $statement = $this->connection->query(
            'SELECT id, name, subject, from_email, from_name, status, created_at, updated_at FROM campaigns ORDER BY created_at DESC'
        );

        return $statement->fetchAll();
    }

    public function create(array $payload): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO campaigns (name, subject, from_email, from_name, status) VALUES (:name, :subject, :from_email, :from_name, :status)'
        );

        $statement->execute([
            'name' => $payload['name'],
            'subject' => $payload['subject'],
            'from_email' => $payload['from_email'],
            'from_name' => $payload['from_name'],
            'status' => $payload['status'] ?? 'draft',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function update(int $campaignId, array $payload): void
    {
        $statement = $this->connection->prepare(
            'UPDATE campaigns SET name = :name, subject = :subject, from_email = :from_email, from_name = :from_name, status = :status WHERE id = :id'
        );

        $statement->execute([
            'id' => $campaignId,
            'name' => $payload['name'],
            'subject' => $payload['subject'],
            'from_email' => $payload['from_email'],
            'from_name' => $payload['from_name'],
            'status' => $payload['status'] ?? 'draft',
        ]);
    }

    public function delete(int $campaignId): void
    {
        $statement = $this->connection->prepare('DELETE FROM campaigns WHERE id = :id');
        $statement->execute(['id' => $campaignId]);
    }

    public function publish(int $campaignId, array $listIds, int $rateMs = 600): int
    {
        $publisher = new CampaignPublisher($this->connection);

        return $publisher->publish($campaignId, $listIds, $rateMs);
    }
}
