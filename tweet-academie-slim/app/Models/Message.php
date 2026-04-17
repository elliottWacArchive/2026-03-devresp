<?php

declare(strict_types=1);

namespace App\Models;

final class Message extends BaseModel
{
    public function canMessage(int $fromUserId, array $targetUser, Follow $follows): bool
    {
        return $this->directMessageMode($fromUserId, $targetUser, $follows) !== 'denied';
    }

    public function directMessageMode(int $viewerId, array $targetUser, Follow $follows): string
    {
        $targetId = (int) $targetUser['id'];

        if ($viewerId === $targetId) {
            return 'denied';
        }

        if ($this->isBlockedEitherWay($viewerId, $targetId)) {
            return 'denied';
        }

        // IMPORTANT: c'est "il me suit", pas "je le suis"
        if ($follows->isFollowing($targetId, $viewerId)) {
            return 'accepted';
        }

        if (!empty($targetUser['allow_dm_from_anyone'])) {
            return 'pending';
        }

        return 'denied';
    }

    public function conversationListForUser(int $userId): array
{
    $sql = '
        SELECT c.*
        FROM conversations c
        INNER JOIN conversation_participants cp
            ON cp.conversation_id = c.id
        LEFT JOIN conversation_hidden_for_users chu
            ON chu.conversation_id = c.id
           AND chu.user_id = :hidden_user_id
        WHERE cp.user_id = :participant_user_id
          AND chu.id IS NULL
          AND (
                c.status = \'accepted\'
                OR (c.status = \'pending\' AND c.created_by = :created_by_user_id)
              )
        ORDER BY COALESCE(
            (
                SELECT MAX(m.created_at)
                FROM message m
                WHERE m.conversation_id = c.id
                  AND m.is_deleted = 0
                  AND m.deleted_for_all = 0
            ),
            c.created_at
        ) DESC
    ';

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
        'hidden_user_id' => $userId,
        'participant_user_id' => $userId,
        'created_by_user_id' => $userId,
    ]);

    $rows = $stmt->fetchAll() ?: [];

    $conversations = [];
    foreach ($rows as $row) {
        $conversations[] = $this->hydrateConversation($row, $userId);
    }

    return $conversations;
}

    public function requestConversationsForUser(int $userId): array
{
    $stmt = $this->db->prepare(
        'SELECT c.*
         FROM conversations c
         INNER JOIN conversation_participants cp
            ON cp.conversation_id = c.id
         LEFT JOIN conversation_hidden_for_users chu
            ON chu.conversation_id = c.id
           AND chu.user_id = :hidden_user_id
         WHERE cp.user_id = :user_id
           AND c.is_group = 0
           AND c.status = \'pending\'
           AND c.request_recipient_id = :request_recipient_id
           AND chu.id IS NULL
         ORDER BY c.created_at DESC'
    );

    $stmt->execute([
        'hidden_user_id' => $userId,
        'user_id' => $userId,
        'request_recipient_id' => $userId,
    ]);

    $rows = $stmt->fetchAll() ?: [];
    $result = [];

    foreach ($rows as $row) {
        $result[] = $this->hydrateConversation($row, $userId);
    }

    return $result;
}

    public function findConversationById(int $conversationId, int $viewerId): ?array
    {
        if (!$this->userIsConversationParticipant($viewerId, $conversationId)) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT *
             FROM conversations
             WHERE id = :id
             LIMIT 1'
        );

        $stmt->execute(['id' => $conversationId]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return $this->hydrateConversation($row, $viewerId);
    }

    public function findExistingDirectConversation(int $userA, int $userB): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*
             FROM conversations c
             INNER JOIN conversation_participants cp1
                ON cp1.conversation_id = c.id
             INNER JOIN conversation_participants cp2
                ON cp2.conversation_id = c.id
             WHERE c.is_group = 0
               AND cp1.user_id = :user_a
               AND cp2.user_id = :user_b
             LIMIT 1'
        );

        $stmt->execute([
            'user_a' => $userA,
            'user_b' => $userB,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createDirectConversation(int $creatorId, int $otherUserId): int
    {
        $existing = $this->findExistingDirectConversation($creatorId, $otherUserId);
        if ($existing) {
            return (int) $existing['id'];
        }

        $stmt = $this->db->prepare(
            'INSERT INTO conversations (created_by, name, image, is_group, status, created_at)
             VALUES (:created_by, NULL, NULL, 0, \'accepted\', NOW())'
        );

        $stmt->execute([
            'created_by' => $creatorId,
        ]);

        $conversationId = (int) $this->db->lastInsertId();

        $insertParticipant = $this->db->prepare(
            'INSERT INTO conversation_participants (conversation_id, user_id, joined_at)
             VALUES (:conversation_id, :user_id, NOW())'
        );

        foreach ([$creatorId, $otherUserId] as $participantId) {
            $insertParticipant->execute([
                'conversation_id' => $conversationId,
                'user_id' => $participantId,
            ]);
        }

        return $conversationId;
    }

    public function createDirectConversationWithStatus(int $viewerId, int $targetId, string $status): int
{
    $existing = $this->findExistingDirectConversation($viewerId, $targetId);
    if ($existing) {
        return (int) $existing['id'];
    }

    $recipientId = null;

    if ($status === 'pending') {
        $recipientId = $targetId;
    }

    $stmt = $this->db->prepare(
        'INSERT INTO conversations (created_by, name, image, is_group, status, request_recipient_id, created_at)
         VALUES (:created_by, NULL, NULL, 0, :status, :recipient, NOW())'
    );

    $stmt->execute([
        'created_by' => $viewerId,
        'status' => $status,
        'recipient' => $recipientId,
    ]);

    $conversationId = (int) $this->db->lastInsertId();

    $participantStmt = $this->db->prepare(
        'INSERT INTO conversation_participants (conversation_id, user_id, joined_at)
         VALUES (:conversation_id, :user_id, NOW())'
    );

    foreach ([$viewerId, $targetId] as $userId) {
        $participantStmt->execute([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
        ]);
    }

    return $conversationId;
}

    public function createGroupConversation(int $creatorId, array $participantIds, ?string $name = null): int
    {
        $participantIds = array_values(array_unique(array_map('intval', $participantIds)));
        $participantIds = array_values(array_filter($participantIds, fn (int $id): bool => $id > 0 && $id !== $creatorId));

        $stmt = $this->db->prepare(
            'INSERT INTO conversations (created_by, name, image, is_group, status, created_at)
             VALUES (:created_by, :name, NULL, 1, \'accepted\', NOW())'
        );

        $stmt->execute([
            'created_by' => $creatorId,
            'name' => ($name !== null && trim($name) !== '') ? trim($name) : null,
        ]);

        $conversationId = (int) $this->db->lastInsertId();

        $allParticipants = array_merge([$creatorId], $participantIds);

        $insertParticipant = $this->db->prepare(
            'INSERT INTO conversation_participants (conversation_id, user_id, joined_at)
             VALUES (:conversation_id, :user_id, NOW())'
        );

        foreach ($allParticipants as $participantId) {
            $insertParticipant->execute([
                'conversation_id' => $conversationId,
                'user_id' => $participantId,
            ]);
        }

        return $conversationId;
    }

    public function userIsConversationParticipant(int $userId, int $conversationId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1
             FROM conversation_participants
             WHERE conversation_id = :conversation_id
               AND user_id = :user_id
             LIMIT 1'
        );

        $stmt->execute([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function userIsConversationCreator(int $userId, int $conversationId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1
             FROM conversations
             WHERE id = :id
               AND created_by = :user_id
             LIMIT 1'
        );

        $stmt->execute([
            'id' => $conversationId,
            'user_id' => $userId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function getConversationParticipants(int $conversationId, ?int $excludeUserId = null): array
    {
        $sql = 'SELECT u.id, u.username, u.firstname, u.lastname, u.icon
                FROM conversation_participants cp
                INNER JOIN users u ON u.id = cp.user_id
                WHERE cp.conversation_id = :conversation_id';

        $params = ['conversation_id' => $conversationId];

        if ($excludeUserId !== null) {
            $sql .= ' AND u.id != :exclude_user_id';
            $params['exclude_user_id'] = $excludeUserId;
        }

        $sql .= ' ORDER BY u.username ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public function getConversationParticipantsIncludingViewer(int $conversationId): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.username, u.firstname, u.lastname, u.icon
             FROM conversation_participants cp
             INNER JOIN users u ON u.id = cp.user_id
             WHERE cp.conversation_id = :conversation_id
             ORDER BY u.username ASC'
        );

        $stmt->execute([
            'conversation_id' => $conversationId,
        ]);

        return $stmt->fetchAll() ?: [];
    }

    public function getDirectOtherUser(int $conversationId, int $viewerId): ?array
    {
        $participants = $this->getConversationParticipants($conversationId, $viewerId);
        return $participants[0] ?? null;
    }

    public function messagesForConversation(int $conversationId, int $viewerId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                m.*,
                fu.username AS from_username,
                fu.firstname AS from_firstname,
                fu.lastname AS from_lastname,
                fu.icon AS from_icon
             FROM message m
             INNER JOIN users fu ON fu.id = m.from_user_id
             WHERE m.conversation_id = :conversation_id
               AND m.is_deleted = 0
               AND m.deleted_for_all = 0
               AND NOT (m.from_user_id = :viewer_sender AND m.deleted_for_sender = 1)
               AND NOT (m.to_user_id = :viewer_receiver AND m.deleted_for_receiver = 1)
             ORDER BY m.created_at ASC'
        );

        $stmt->execute([
            'conversation_id' => $conversationId,
            'viewer_sender' => $viewerId,
            'viewer_receiver' => $viewerId,
        ]);

        return $stmt->fetchAll() ?: [];
    }

    public function sendToConversation(int $conversationId, int $fromUserId, string $message, ?string $image = null): int
    {
        $conversation = $this->findConversationRaw($conversationId);
        if (!$conversation) {
            return 0;
        }

        $toUserId = null;
        if ((int) $conversation['is_group'] === 0) {
            $other = $this->getDirectOtherUser($conversationId, $fromUserId);
            $toUserId = $other ? (int) $other['id'] : null;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO message (conversation_id, type, from_user_id, to_user_id, message, image, unread, is_deleted, deleted_for_sender, deleted_for_receiver, deleted_for_all, created_at)
             VALUES (:conversation_id, :type, :from_user_id, :to_user_id, :message, :image, 1, 0, 0, 0, 0, NOW())'
        );

        $this->unhideConversationForParticipants($conversationId);

        $stmt->execute([
            'conversation_id' => $conversationId,
            'type' => (int) $conversation['is_group'] === 1 ? 'group' : 'private',
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'message' => $message,
            'image' => $image,
        ]);

        $this->markConversationAsReadForUser($conversationId, $fromUserId);

        return (int) $this->db->lastInsertId();
    }

    public function editOwned(int $messageId, int $userId, string $message): void
    {
        $stmt = $this->db->prepare(
            'UPDATE message
             SET message = :message,
                 edited_at = NOW()
             WHERE id = :id
               AND from_user_id = :user_id
               AND is_deleted = 0'
        );

        $stmt->execute([
            'message' => $message,
            'id' => $messageId,
            'user_id' => $userId,
        ]);
    }

    public function deleteOwnedMessage(int $messageId, int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE message
             SET deleted_for_sender = 1
             WHERE id = :id
               AND from_user_id = :user_id'
        );

        $stmt->execute([
            'id' => $messageId,
            'user_id' => $userId,
        ]);
    }

    public function deleteForEveryone(int $messageId, int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE message
             SET deleted_for_all = 1
             WHERE id = :id
               AND from_user_id = :user_id'
        );

        $stmt->execute([
            'id' => $messageId,
            'user_id' => $userId,
        ]);
    }

    public function renameConversation(int $conversationId, int $userId, string $name): void
    {
        $conversation = $this->findConversationRaw($conversationId);
        if (!$conversation) {
            return;
        }

        if ((int) $conversation['is_group'] !== 1) {
            return;
        }

        if ((int) $conversation['created_by'] !== $userId) {
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE conversations
             SET name = :name
             WHERE id = :id'
        );

        $stmt->execute([
            'name' => trim($name),
            'id' => $conversationId,
        ]);
    }

    public function updateConversationImage(int $conversationId, int $userId, string $imagePath): void
    {
        $conversation = $this->findConversationRaw($conversationId);
        if (!$conversation) {
            return;
        }

        if ((int) $conversation['created_by'] !== $userId || (int) $conversation['is_group'] !== 1) {
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE conversations
             SET image = :image
             WHERE id = :id'
        );

        $stmt->execute([
            'image' => $imagePath,
            'id' => $conversationId,
        ]);
    }

    public function deleteConversationForEveryone(int $conversationId, int $userId): void
    {
        $conversation = $this->findConversationRaw($conversationId);
        if (!$conversation) {
            return;
        }

        if ((int) $conversation['created_by'] !== $userId) {
            return;
        }

        $deleteMessages = $this->db->prepare(
            'DELETE FROM message
             WHERE conversation_id = :conversation_id'
        );
        $deleteMessages->execute([
            'conversation_id' => $conversationId,
        ]);

        $deleteParticipants = $this->db->prepare(
            'DELETE FROM conversation_participants
             WHERE conversation_id = :conversation_id'
        );
        $deleteParticipants->execute([
            'conversation_id' => $conversationId,
        ]);

        $deleteHidden = $this->db->prepare(
            'DELETE FROM conversation_hidden_for_users
             WHERE conversation_id = :conversation_id'
        );
        $deleteHidden->execute([
            'conversation_id' => $conversationId,
        ]);

        $deleteConversation = $this->db->prepare(
            'DELETE FROM conversations
             WHERE id = :id'
        );
        $deleteConversation->execute([
            'id' => $conversationId,
        ]);
    }

    public function acceptConversationRequest(int $conversationId, int $viewerId): void
{
    $stmt = $this->db->prepare(
        'UPDATE conversations
         SET status = \'accepted\',
             request_recipient_id = NULL
         WHERE id = :conversation_id
           AND is_group = 0
           AND status = \'pending\'
           AND request_recipient_id = :viewer_id'
    );

    $stmt->execute([
        'conversation_id' => $conversationId,
        'viewer_id' => $viewerId,
    ]);
}

    public function deletePendingConversationRequest(int $conversationId, int $viewerId): void
{
    $stmt = $this->db->prepare(
        'SELECT *
         FROM conversations
         WHERE id = :conversation_id
           AND is_group = 0
           AND status = \'pending\'
           AND request_recipient_id = :viewer_id
         LIMIT 1'
    );

    $stmt->execute([
        'conversation_id' => $conversationId,
        'viewer_id' => $viewerId,
    ]);

    $conversation = $stmt->fetch();
    if (!$conversation) {
        return;
    }

    $deleteMessages = $this->db->prepare(
        'DELETE FROM message
         WHERE conversation_id = :conversation_id'
    );
    $deleteMessages->execute([
        'conversation_id' => $conversationId,
    ]);

    $deleteParticipants = $this->db->prepare(
        'DELETE FROM conversation_participants
         WHERE conversation_id = :conversation_id'
    );
    $deleteParticipants->execute([
        'conversation_id' => $conversationId,
    ]);

    $deleteHidden = $this->db->prepare(
        'DELETE FROM conversation_hidden_for_users
         WHERE conversation_id = :conversation_id'
    );
    $deleteHidden->execute([
        'conversation_id' => $conversationId,
    ]);

    $deleteConversation = $this->db->prepare(
        'DELETE FROM conversations
         WHERE id = :conversation_id'
    );
    $deleteConversation->execute([
        'conversation_id' => $conversationId,
    ]);
}

    public function blockUser(int $blockerId, int $blockedId): void
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO user_blocks (blocker_user_id, blocked_user_id, created_at)
             VALUES (:blocker, :blocked, NOW())'
        );

        $stmt->execute([
            'blocker' => $blockerId,
            'blocked' => $blockedId,
        ]);
    }

    public function unblockUser(int $blockerId, int $blockedId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM user_blocks
             WHERE blocker_user_id = :blocker
               AND blocked_user_id = :blocked'
        );

        $stmt->execute([
            'blocker' => $blockerId,
            'blocked' => $blockedId,
        ]);
    }

    public function isBlockedBy(int $blockerId, int $blockedId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1
             FROM user_blocks
             WHERE blocker_user_id = :blocker
               AND blocked_user_id = :blocked
             LIMIT 1'
        );

        $stmt->execute([
            'blocker' => $blockerId,
            'blocked' => $blockedId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function isBlockedEitherWay(int $userA, int $userB): bool
    {
        return $this->isBlockedBy($userA, $userB) || $this->isBlockedBy($userB, $userA);
    }

    private function findConversationRaw(int $conversationId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT *
             FROM conversations
             WHERE id = :id
             LIMIT 1'
        );

        $stmt->execute(['id' => $conversationId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function hydrateConversation(array $conversation, int $viewerId): array
{
    $conversationId = (int) $conversation['id'];

    $allParticipants = $this->getConversationParticipantsIncludingViewer($conversationId);
    $otherParticipants = $this->getConversationParticipants($conversationId, $viewerId);

    $displayName = '';
    $displaySubtitle = '';
    $displayImage = $conversation['image'] ?? null;
    $directUsername = null;

    if ((int) $conversation['is_group'] === 1) {
        $usernames = array_map(
            fn (array $participant): string => (string) $participant['username'],
            $otherParticipants
        );

        $displayName = trim((string) ($conversation['name'] ?? ''));
        if ($displayName === '') {
            $displayName = $usernames ? implode(', ', $usernames) : 'Group chat';
        }

        $displaySubtitle = count($allParticipants) . ' members';
    } else {
        $other = $otherParticipants[0] ?? null;

        if ($other) {
            $displayName = (string) $other['username'];
            $displaySubtitle = trim(($other['firstname'] ?? '') . ' ' . ($other['lastname'] ?? ''));
            $directUsername = $other['username'];

            if (empty($displayImage)) {
                $displayImage = $other['icon'] ?? null;
            }
        } else {
            $displayName = 'Direct chat';
        }
    }

    $status = $conversation['status'] ?? 'accepted';
    $requestRecipientId = isset($conversation['request_recipient_id']) ? (int) $conversation['request_recipient_id'] : 0;

    return [
        'id' => $conversationId,
        'created_by' => (int) $conversation['created_by'],
        'name' => $conversation['name'],
        'image' => $conversation['image'],
        'is_group' => (int) $conversation['is_group'],
        'status' => $status,
        'request_recipient_id' => $requestRecipientId,
        'created_at' => $conversation['created_at'],
        'participants' => $allParticipants,
        'display_name' => $displayName,
        'display_subtitle' => $displaySubtitle,
        'display_image' => $displayImage,
        'direct_username' => $directUsername,
        'is_owner' => (int) $conversation['created_by'] === $viewerId,
        'is_pending_for_me' => $status === 'pending' && $requestRecipientId === $viewerId,
        'is_pending_sent_by_me' => $status === 'pending' && (int) $conversation['created_by'] === $viewerId,
    ];
}

    public function deleteConversationForMe(int $conversationId, int $viewerId): void
    {
        $this->hideConversationForUser($conversationId, $viewerId);
    }

    public function hideConversationForUser(int $conversationId, int $viewerId): void
    {
        if (!$this->userIsConversationParticipant($viewerId, $conversationId)) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO conversation_hidden_for_users (conversation_id, user_id, hidden_at)
             VALUES (:conversation_id, :user_id, NOW())
             ON DUPLICATE KEY UPDATE hidden_at = VALUES(hidden_at)'
        );

        $stmt->execute([
            'conversation_id' => $conversationId,
            'user_id' => $viewerId,
        ]);
    }

    private function unhideConversationForParticipants(int $conversationId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM conversation_hidden_for_users
             WHERE conversation_id = :conversation_id'
        );

        $stmt->execute([
            'conversation_id' => $conversationId,
        ]);
    }

    public function addMemberToConversation(int $conversationId, int $viewerId, int $newUserId): void
    {
        $conversation = $this->findConversationRaw($conversationId);
        if (!$conversation || (int) $conversation['is_group'] !== 1) {
            return;
        }

        if (!$this->userIsConversationParticipant($viewerId, $conversationId)) {
            return;
        }

        if ($this->userIsConversationParticipant($newUserId, $conversationId)) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO conversation_participants (conversation_id, user_id, joined_at)
             VALUES (:conversation_id, :user_id, NOW())'
        );

        $stmt->execute([
            'conversation_id' => $conversationId,
            'user_id' => $newUserId,
        ]);
    }

    public function removeMemberFromConversation(int $conversationId, int $viewerId, int $targetUserId): void
    {
        $conversation = $this->findConversationRaw($conversationId);
        if (!$conversation || (int) $conversation['is_group'] !== 1) {
            return;
        }

        if ((int) $conversation['created_by'] !== $viewerId) {
            return;
        }

        if ((int) $conversation['created_by'] === $targetUserId) {
            return;
        }

        $stmt = $this->db->prepare(
            'DELETE FROM conversation_participants
             WHERE conversation_id = :conversation_id
               AND user_id = :user_id'
        );

        $stmt->execute([
            'conversation_id' => $conversationId,
            'user_id' => $targetUserId,
        ]);
    }

    public function unreadCountForUser(int $userId): int
{
    $stmt = $this->db->prepare(
        'SELECT COUNT(*)
         FROM message m
         INNER JOIN conversations c
            ON c.id = m.conversation_id
         INNER JOIN conversation_participants cp
            ON cp.conversation_id = c.id
           AND cp.user_id = :cp_user_id
         LEFT JOIN conversation_hidden_for_users chu
            ON chu.conversation_id = c.id
           AND chu.user_id = :chu_user_id
         WHERE c.status = \'accepted\'
           AND chu.id IS NULL
           AND m.from_user_id != :from_user_id
           AND m.is_deleted = 0
           AND m.deleted_for_all = 0
           AND NOT (m.to_user_id = :receiver_user_id AND m.deleted_for_receiver = 1)
           AND NOT (m.from_user_id = :sender_user_id AND m.deleted_for_sender = 1)
           AND (
                cp.last_read_message_id IS NULL
                OR m.id > cp.last_read_message_id
           )'
    );

    $stmt->execute([
        'cp_user_id' => $userId,
        'chu_user_id' => $userId,
        'from_user_id' => $userId,
        'receiver_user_id' => $userId,
        'sender_user_id' => $userId,
    ]);

    return (int) $stmt->fetchColumn();
}

public function markConversationAsReadForUser(int $conversationId, int $userId): void
{
    $stmt = $this->db->prepare(
        'SELECT MAX(id)
         FROM message
         WHERE conversation_id = :conversation_id
           AND is_deleted = 0
           AND deleted_for_all = 0'
    );

    $stmt->execute([
        'conversation_id' => $conversationId,
    ]);

    $lastMessageId = (int) ($stmt->fetchColumn() ?: 0);

    $update = $this->db->prepare(
        'UPDATE conversation_participants
         SET last_read_message_id = :last_read_message_id
         WHERE conversation_id = :conversation_id
           AND user_id = :user_id'
    );

    $update->execute([
        'last_read_message_id' => $lastMessageId > 0 ? $lastMessageId : null,
        'conversation_id' => $conversationId,
        'user_id' => $userId,
    ]);

    $legacy = $this->db->prepare(
        'UPDATE message
         SET unread = 0
         WHERE conversation_id = :conversation_id
           AND to_user_id = :user_id
           AND unread = 1'
    );

    $legacy->execute([
        'conversation_id' => $conversationId,
        'user_id' => $userId,
    ]);
}
}