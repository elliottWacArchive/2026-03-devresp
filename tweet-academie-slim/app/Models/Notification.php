<?php

declare(strict_types=1);

namespace App\Models;

final class Notification extends BaseModel
{
    public function create(
        int $receiverUserId,
        int $actorUserId,
        string $type,
        ?int $tweetId = null,
        ?int $replyTweetId = null,
        ?int $targetUserId = null,
        ?string $contentPreview = null
    ): void {
        if ($receiverUserId <= 0 || $actorUserId <= 0) {
            return;
        }

        if ($receiverUserId === $actorUserId) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO notifications (
                receiver_user_id,
                actor_user_id,
                type,
                tweet_id,
                reply_tweet_id,
                target_user_id,
                content_preview,
                is_read,
                created_at
             ) VALUES (
                :receiver_user_id,
                :actor_user_id,
                :type,
                :tweet_id,
                :reply_tweet_id,
                :target_user_id,
                :content_preview,
                0,
                NOW()
             )'
        );

        $stmt->execute([
            'receiver_user_id' => $receiverUserId,
            'actor_user_id' => $actorUserId,
            'type' => $type,
            'tweet_id' => $tweetId,
            'reply_tweet_id' => $replyTweetId,
            'target_user_id' => $targetUserId,
            'content_preview' => $contentPreview,
        ]);
    }

    public function unreadCountForUser(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM notifications
             WHERE receiver_user_id = :user_id
               AND is_read = 0'
        );

        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function listForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                n.*,
                u.username AS actor_username,
                u.firstname AS actor_firstname,
                u.lastname AS actor_lastname,
                u.icon AS actor_icon
             FROM notifications n
             INNER JOIN users u ON u.id = n.actor_user_id
             WHERE n.receiver_user_id = :user_id
             ORDER BY n.created_at DESC
             LIMIT 100'
        );

        $stmt->execute(['user_id' => $userId]);

        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$row) {
            $row['link'] = $this->buildNotificationLink($row);
        }
        unset($row);

        return $rows;
    }

    public function markAllAsReadForUser(int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE notifications
             SET is_read = 1
             WHERE receiver_user_id = :user_id
               AND is_read = 0'
        );

        $stmt->execute(['user_id' => $userId]);
    }

    public function markAsReadForUser(int $notificationId, int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE notifications
             SET is_read = 1
             WHERE id = :id
               AND receiver_user_id = :user_id'
        );

        $stmt->execute([
            'id' => $notificationId,
            'user_id' => $userId,
        ]);
    }

    public function findByIdForUser(int $notificationId, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                n.*,
                u.username AS actor_username,
                u.firstname AS actor_firstname,
                u.lastname AS actor_lastname,
                u.icon AS actor_icon
             FROM notifications n
             INNER JOIN users u ON u.id = n.actor_user_id
             WHERE n.id = :id
               AND n.receiver_user_id = :user_id
             LIMIT 1'
        );

        $stmt->execute([
            'id' => $notificationId,
            'user_id' => $userId,
        ]);

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $row['link'] = $this->buildNotificationLink($row);
        return $row;
    }

    private function buildNotificationLink(array $notification): string
{
    $type = (string) ($notification['type'] ?? '');

    if ($type === 'follow' && !empty($notification['actor_username'])) {
        return '/profile/' . rawurlencode((string) $notification['actor_username']);
    }

    if (!empty($notification['reply_tweet_id']) && !empty($notification['tweet_id'])) {
        return '/tweet/' . (int) $notification['tweet_id'] . '#reply-' . (int) $notification['reply_tweet_id'];
    }

    if (!empty($notification['tweet_id'])) {
        return '/tweet/' . (int) $notification['tweet_id'];
    }

    if (!empty($notification['actor_username'])) {
        return '/profile/' . rawurlencode((string) $notification['actor_username']);
    }

    return '/notifications';
}
}