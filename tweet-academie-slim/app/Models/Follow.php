<?php

declare(strict_types=1);

namespace App\Models;

final class Follow extends BaseModel
{
    public function isFollowing(int $followerId, int $followedId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1
             FROM follows
             WHERE following_user_id = :follower
               AND followed_user_id = :followed
             LIMIT 1'
        );

        $stmt->execute([
            'follower' => $followerId,
            'followed' => $followedId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function isMutualFollow(int $userA, int $userB): bool
    {
        return $this->isFollowing($userA, $userB) && $this->isFollowing($userB, $userA);
    }

    public function removeBothDirections(int $userA, int $userB): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM follows
             WHERE (following_user_id = :user_a AND followed_user_id = :user_b)
                OR (following_user_id = :user_b_alt AND followed_user_id = :user_a_alt)'
        );

        $stmt->execute([
            'user_a' => $userA,
            'user_b' => $userB,
            'user_b_alt' => $userB,
            'user_a_alt' => $userA,
        ]);
    }

    public function hasPendingRequest(int $requesterId, int $targetId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1
             FROM follow_requests
             WHERE requester_user_id = :requester
               AND target_user_id = :target
               AND status = :status
             LIMIT 1'
        );

        $stmt->execute([
            'requester' => $requesterId,
            'target' => $targetId,
            'status' => 'pending',
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function removePendingRequest(int $requesterId, int $targetId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM follow_requests
             WHERE requester_user_id = :requester
               AND target_user_id = :target
               AND status = :status'
        );

        $stmt->execute([
            'requester' => $requesterId,
            'target' => $targetId,
            'status' => 'pending',
        ]);
    }

    public function createFollowRequest(int $requesterId, int $targetId): void
    {
        if ($requesterId === $targetId) {
            return;
        }

        $deleteOld = $this->db->prepare(
            'DELETE FROM follow_requests
             WHERE requester_user_id = :requester
               AND target_user_id = :target'
        );

        $deleteOld->execute([
            'requester' => $requesterId,
            'target' => $targetId,
        ]);

        $stmt = $this->db->prepare(
            'INSERT INTO follow_requests (requester_user_id, target_user_id, status, created_at)
             VALUES (:requester, :target, :status, NOW())'
        );

        $stmt->execute([
            'requester' => $requesterId,
            'target' => $targetId,
            'status' => 'pending',
        ]);
    }

    public function acceptRequest(int $requestId, int $ownerId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT *
             FROM follow_requests
             WHERE id = :id
               AND target_user_id = :owner
               AND status = :status
             LIMIT 1'
        );

        $stmt->execute([
            'id' => $requestId,
            'owner' => $ownerId,
            'status' => 'pending',
        ]);

        $request = $stmt->fetch();
        if (!$request) {
            return false;
        }

        $insertFollow = $this->db->prepare(
            'INSERT INTO follows (following_user_id, followed_user_id, created_at)
             VALUES (:follower, :followed, NOW())'
        );

        $insertFollow->execute([
            'follower' => (int) $request['requester_user_id'],
            'followed' => (int) $request['target_user_id'],
        ]);

        $update = $this->db->prepare(
            'UPDATE follow_requests
             SET status = :status
             WHERE id = :id'
        );

        $update->execute([
            'status' => 'accepted',
            'id' => $requestId,
        ]);

        return true;
    }

    public function rejectRequest(int $requestId, int $ownerId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE follow_requests
             SET status = :status
             WHERE id = :id
               AND target_user_id = :owner
               AND status = :pending'
        );

        $stmt->execute([
            'status' => 'rejected',
            'id' => $requestId,
            'owner' => $ownerId,
            'pending' => 'pending',
        ]);

        return $stmt->rowCount() > 0;
    }

    public function pendingRequestsForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                fr.id,
                fr.requester_user_id,
                fr.created_at,
                u.username,
                u.firstname,
                u.lastname,
                u.icon
             FROM follow_requests fr
             INNER JOIN users u ON u.id = fr.requester_user_id
             WHERE fr.target_user_id = :target
               AND fr.status = :status
             ORDER BY fr.created_at DESC'
        );

        $stmt->execute([
            'target' => $userId,
            'status' => 'pending',
        ]);

        return $stmt->fetchAll() ?: [];
    }

    public function togglePublicFollow(int $followerId, int $followedId): bool
    {
        if ($followerId === $followedId) {
            return false;
        }

        if ($this->isFollowing($followerId, $followedId)) {
            $stmt = $this->db->prepare(
                'DELETE FROM follows
                 WHERE following_user_id = :follower
                   AND followed_user_id = :followed'
            );

            $stmt->execute([
                'follower' => $followerId,
                'followed' => $followedId,
            ]);

            return false;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO follows (following_user_id, followed_user_id, created_at)
             VALUES (:follower, :followed, NOW())'
        );

        $stmt->execute([
            'follower' => $followerId,
            'followed' => $followedId,
        ]);

        return true;
    }

    public function findRequestByIdForOwner(int $requestId, int $ownerId): ?array
{
    $stmt = $this->db->prepare(
        'SELECT *
         FROM follow_requests
         WHERE id = :id
           AND target_user_id = :owner
         LIMIT 1'
    );

    $stmt->execute([
        'id' => $requestId,
        'owner' => $ownerId,
    ]);

    return $stmt->fetch() ?: null;
}
}

