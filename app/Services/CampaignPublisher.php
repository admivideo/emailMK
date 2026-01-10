<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use PDO;
use Throwable;

final class CampaignPublisher
{
    public function __construct(private PDO $connection)
    {
    }

    public function publish(int $campaignId, array $listIds, int $rateMs = 600): int
    {
        $listIds = array_values(array_filter(array_map('intval', $listIds)));

        if ($listIds === []) {
            return 0;
        }

        $this->connection->beginTransaction();

        try {
            $this->syncCampaignLists($campaignId, $listIds);

            $subscriberIds = $this->fetchSubscriberIds($listIds);
            if ($subscriberIds === []) {
                $this->connection->commit();
                return 0;
            }

            $insertMessage = $this->connection->prepare(
                'INSERT IGNORE INTO campaign_messages (campaign_id, subscriber_id, status) VALUES (:campaign_id, :subscriber_id, :status)'
            );
            $insertQueue = $this->connection->prepare(
                'INSERT INTO send_queue (campaign_message_id, status, scheduled_at) VALUES (:campaign_message_id, :status, :scheduled_at)'
            );

            $baseTimestamp = microtime(true);
            $created = 0;

            foreach ($subscriberIds as $index => $subscriberId) {
                $insertMessage->execute([
                    'campaign_id' => $campaignId,
                    'subscriber_id' => $subscriberId,
                    'status' => 'pending',
                ]);

                $campaignMessageId = (int) $this->connection->lastInsertId();
                if ($campaignMessageId === 0) {
                    continue;
                }

                $scheduledAt = $this->scheduleForIndex($baseTimestamp, $index, $rateMs);

                $insertQueue->execute([
                    'campaign_message_id' => $campaignMessageId,
                    'status' => 'pending',
                    'scheduled_at' => $scheduledAt,
                ]);

                $created++;
            }

            $updateCampaign = $this->connection->prepare(
                'UPDATE campaigns SET status = :status WHERE id = :id'
            );
            $updateCampaign->execute([
                'status' => 'published',
                'id' => $campaignId,
            ]);

            $this->connection->commit();

            return $created;
        } catch (Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }

    private function syncCampaignLists(int $campaignId, array $listIds): void
    {
        $deleteStatement = $this->connection->prepare('DELETE FROM campaign_lists WHERE campaign_id = :campaign_id');
        $deleteStatement->execute(['campaign_id' => $campaignId]);

        $insertStatement = $this->connection->prepare(
            'INSERT INTO campaign_lists (campaign_id, list_id) VALUES (:campaign_id, :list_id)'
        );

        foreach ($listIds as $listId) {
            $insertStatement->execute([
                'campaign_id' => $campaignId,
                'list_id' => $listId,
            ]);
        }
    }

    private function fetchSubscriberIds(array $listIds): array
    {
        $placeholders = implode(', ', array_fill(0, count($listIds), '?'));
        $statement = $this->connection->prepare(
            "SELECT DISTINCT subscriber_id FROM list_subscribers WHERE list_id IN ({$placeholders})"
        );
        $statement->execute($listIds);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function scheduleForIndex(float $baseTimestamp, int $index, int $rateMs): string
    {
        $scheduledTimestamp = $baseTimestamp + ($index * $rateMs / 1000);
        $scheduledAt = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6f', $scheduledTimestamp));

        if ($scheduledAt === false) {
            $scheduledAt = new DateTimeImmutable();
        }

        return $scheduledAt->format('Y-m-d H:i:s');
    }
}
