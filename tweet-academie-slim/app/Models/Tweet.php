<?php

declare(strict_types=1);

namespace App\Models;

final class Tweet extends BaseModel
{
    public function createOriginal(
    int $userId,
    string $tweet,
    ?string $image = null,
    string $replyPermission = 'everyone',
    ?string $imageShortCode = null
): int
{
    $stmt = $this->db->prepare(
        'INSERT INTO tweets (user_id, parent_id, type, tweet, image, mention, reply_permission, is_deleted, created_at)
         VALUES (:user_id, NULL, :type, :tweet, :image, :mention, :reply_permission, 0, NOW())'
    );

    $stmt->execute([
        'user_id' => $userId,
        'type' => 'original',
        'tweet' => $tweet,
        'image' => $image,
        'mention' => $this->extractMentions($tweet),
        'reply_permission' => $replyPermission,
    ]);

    $tweetId = (int) $this->db->lastInsertId();
    $this->syncHashtags($tweetId, $tweet);

    return $tweetId;
}

    public function createReply(
    int $userId,
    int $parentId,
    string $tweet,
    ?string $image = null,
    ?string $imageShortCode = null
): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO tweets (user_id, parent_id, type, tweet, image, mention, is_deleted, created_at)
             VALUES (:user_id, :parent_id, :type, :tweet, :image, :mention, 0, NOW())'
        );

        $stmt->execute([
            'user_id' => $userId,
            'parent_id' => $parentId,
            'type' => 'reply',
            'tweet' => $tweet,
            'image' => $image,
            'mention' => $this->extractMentions($tweet),
        ]);

        $tweetId = (int) $this->db->lastInsertId();
        $this->syncHashtags($tweetId, $tweet);

        return $tweetId;
    }

    public function toggleLike(int $tweetId, int $userId): bool
    {
        $check = $this->db->prepare(
            'SELECT id
             FROM tweets_like
             WHERE tweets_id = :tweet_id
               AND user_id = :user_id
             LIMIT 1'
        );

        $check->execute([
            'tweet_id' => $tweetId,
            'user_id' => $userId,
        ]);

        $existingId = $check->fetchColumn();

        if ($existingId) {
            $delete = $this->db->prepare('DELETE FROM tweets_like WHERE id = :id');
            $delete->execute(['id' => (int) $existingId]);
            return false;
        }

        $insert = $this->db->prepare(
            'INSERT INTO tweets_like (tweets_id, user_id, created_at)
             VALUES (:tweet_id, :user_id, NOW())'
        );

        $insert->execute([
            'tweet_id' => $tweetId,
            'user_id' => $userId,
        ]);

        return true;
    }

    public function likesCount(int $tweetId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM tweets_like
             WHERE tweets_id = :tweet_id'
        );

        $stmt->execute(['tweet_id' => $tweetId]);
        return (int) $stmt->fetchColumn();
    }

    public function toggleRepost(int $userId, int $tweetId): bool
    {
        $check = $this->db->prepare(
            'SELECT id, is_deleted
             FROM tweets
             WHERE user_id = :user_id
               AND parent_id = :parent_id
               AND type = :type
             LIMIT 1'
        );

        $check->execute([
            'user_id' => $userId,
            'parent_id' => $tweetId,
            'type' => 'retweet',
        ]);

        $existing = $check->fetch();

        if ($existing) {
            if ((int) $existing['is_deleted'] === 0) {
                $delete = $this->db->prepare(
                    'UPDATE tweets
                     SET is_deleted = 1
                     WHERE id = :id
                       AND user_id = :user_id'
                );

                $delete->execute([
                    'id' => (int) $existing['id'],
                    'user_id' => $userId,
                ]);

                return false;
            }

            $restore = $this->db->prepare(
                'UPDATE tweets
                 SET is_deleted = 0,
                     created_at = NOW()
                 WHERE id = :id
                   AND user_id = :user_id'
            );

            $restore->execute([
                'id' => (int) $existing['id'],
                'user_id' => $userId,
            ]);

            return true;
        }

        $insert = $this->db->prepare(
            'INSERT INTO tweets (user_id, parent_id, type, tweet, image, mention, is_deleted, created_at)
             VALUES (:user_id, :parent_id, :type, :tweet, NULL, NULL, 0, NOW())'
        );

        $insert->execute([
            'user_id' => $userId,
            'parent_id' => $tweetId,
            'type' => 'retweet',
            'tweet' => '',
        ]);

        return true;
    }

    public function deleteOwned(int $tweetId, int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE tweets
             SET is_deleted = 1
             WHERE id = :tweet_id
               AND user_id = :owner_id'
        );

        $stmt->execute([
            'tweet_id' => $tweetId,
            'owner_id' => $userId,
        ]);
    }

    public function deleteOriginalOwned(int $tweetId, int $userId): void
{
    $stmt = $this->db->prepare(
        'UPDATE tweets
         SET is_deleted = 1
         WHERE id = :tweet_id
           AND user_id = :owner_id
           AND type IN (\'original\', \'reply\')'
    );

    $stmt->execute([
        'tweet_id' => $tweetId,
        'owner_id' => $userId,
    ]);

    $cleanup = $this->db->prepare(
        'UPDATE tweets
         SET is_deleted = 1
         WHERE parent_id = :tweet_id
           AND type = \'retweet\''
    );

    $cleanup->execute([
        'tweet_id' => $tweetId,
    ]);
}

public function editOwned(int $tweetId, int $userId, string $tweet): void
{
    $stmt = $this->db->prepare(
        'UPDATE tweets
         SET tweet = :tweet,
             mention = :mention
         WHERE id = :tweet_id
           AND user_id = :owner_id
           AND type IN (\'original\', \'reply\')
           AND is_deleted = 0'
    );

    $stmt->execute([
        'tweet' => $tweet,
        'mention' => $this->extractMentions($tweet),
        'tweet_id' => $tweetId,
        'owner_id' => $userId,
    ]);
}

    public function timelineForUser(int $userId): array
{
    $sql = $this->baseFeedSql() . '
        WHERE t.is_deleted = 0
          AND (
                (
                    t.type = \'original\'
                    AND (
                        u.is_private = 0
                        OR t.user_id = :viewer_original_id
                        OR t.user_id IN (
                            SELECT followed_user_id
                            FROM follows
                            WHERE following_user_id = :viewer_original_following_id
                        )
                    )
                )
                OR
                (
                    t.type = \'retweet\'
                    AND (
                        t.user_id = :viewer_repost_id
                        OR t.user_id IN (
                            SELECT followed_user_id
                            FROM follows
                            WHERE following_user_id = :viewer_repost_following_id
                        )
                    )
                )
              )
        ORDER BY t.created_at DESC
        LIMIT 50';

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
        'liked_viewer_id' => $userId,
        'repost_viewer_id' => $userId,
        'viewer_original_id' => $userId,
        'viewer_original_following_id' => $userId,
        'viewer_repost_id' => $userId,
        'viewer_repost_following_id' => $userId,
    ]);

    $rows = $stmt->fetchAll() ?: [];
    $rows = $this->deduplicateFeedRows($rows);

    return $this->attachReplies($rows, $userId);
}

    public function profileTweets(int $profileUserId, int $viewerId): array
    {
        $sql = $this->baseFeedSql() . '
            WHERE t.is_deleted = 0
              AND t.user_id = :profile_user_id
              AND t.type = \'original\'
            ORDER BY t.created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'liked_viewer_id' => $viewerId,
            'repost_viewer_id' => $viewerId,
            'profile_user_id' => $profileUserId,
        ]);

        return $this->attachReplies($stmt->fetchAll() ?: [], $viewerId);
    }

    public function profileReplies(int $profileUserId, int $viewerId): array
    {
        $sql = $this->baseFeedSql() . '
            WHERE t.is_deleted = 0
              AND t.user_id = :profile_reply_user_id
              AND t.type = \'reply\'
            ORDER BY t.created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'liked_viewer_id' => $viewerId,
            'repost_viewer_id' => $viewerId,
            'profile_reply_user_id' => $profileUserId,
        ]);

        return $stmt->fetchAll() ?: [];
    }

    public function profileReposts(int $profileUserId, int $viewerId): array
    {
        $sql = $this->baseFeedSql() . '
            WHERE t.is_deleted = 0
              AND t.user_id = :profile_repost_user_id
              AND t.type = \'retweet\'
            ORDER BY t.created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'liked_viewer_id' => $viewerId,
            'repost_viewer_id' => $viewerId,
            'profile_repost_user_id' => $profileUserId,
        ]);

        return $this->attachReplies($stmt->fetchAll() ?: [], $viewerId);
    }

    public function byHashtag(string $tag, int $viewerId): array
    {
        $tag = trim(ltrim($tag, '#'));

        $sql = $this->baseFeedSql() . '
            INNER JOIN tweets_hastag th ON th.tweets_id = t.id
            INNER JOIN hastag_list hl ON hl.id = th.hastag_list_id
            WHERE t.is_deleted = 0
              AND t.type = \'original\'
              AND hl.name = :tag_name
            ORDER BY t.created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'liked_viewer_id' => $viewerId,
            'repost_viewer_id' => $viewerId,
            'tag_name' => $tag,
        ]);

        return $this->attachReplies($stmt->fetchAll() ?: [], $viewerId);
    }

    private function baseFeedSql(): string
{
    return 'SELECT
                t.id,
                t.user_id,
                t.parent_id,
                t.type,
                t.tweet,
                t.image,
                t.image_short_code,
                t.reply_permission,
                t.created_at,

                u.username,
                u.firstname,
                u.lastname,
                u.icon,

                rt.id AS original_id,
                rt.type AS original_type,
                rt.tweet AS original_tweet,
                rt.image AS original_image,
                rt.image_short_code AS original_image_short_code,
                rt.created_at AS original_created_at,

                ru.id AS original_user_id,
                ru.username AS original_username,
                ru.firstname AS original_firstname,
                ru.lastname AS original_lastname,
                ru.icon AS original_icon,

                pt.id AS parent_tweet_id,
                pt.type AS parent_tweet_type,
                pt.image_short_code AS parent_image_short_code,
                pu.username AS parent_username,

                (
                    SELECT COUNT(*)
                    FROM tweets_like tl
                    WHERE tl.tweets_id = CASE
                        WHEN t.type = \'retweet\' AND t.parent_id IS NOT NULL THEN t.parent_id
                        ELSE t.id
                    END
                ) AS likes_count,

                EXISTS(
                    SELECT 1
                    FROM tweets_like tl2
                    WHERE tl2.tweets_id = CASE
                        WHEN t.type = \'retweet\' AND t.parent_id IS NOT NULL THEN t.parent_id
                        ELSE t.id
                    END
                      AND tl2.user_id = :liked_viewer_id
                ) AS liked_by_me,

                EXISTS(
                    SELECT 1
                    FROM tweets tr
                    WHERE tr.parent_id = CASE
                        WHEN t.type = \'retweet\' AND t.parent_id IS NOT NULL THEN t.parent_id
                        ELSE t.id
                    END
                      AND tr.type = \'retweet\'
                      AND tr.user_id = :repost_viewer_id
                      AND tr.is_deleted = 0
                ) AS is_reposted_by_me
            FROM tweets t
            INNER JOIN users u ON u.id = t.user_id
            LEFT JOIN tweets rt
                ON rt.id = t.parent_id
               AND t.type = \'retweet\'
               AND rt.is_deleted = 0
            LEFT JOIN users ru ON ru.id = rt.user_id
            LEFT JOIN tweets pt
                ON pt.id = t.parent_id
               AND pt.is_deleted = 0
            LEFT JOIN users pu ON pu.id = pt.user_id';
}

    private function deduplicateFeedRows(array $rows): array
{
    $grouped = [];

    foreach ($rows as $row) {
        if (($row['type'] ?? '') === 'retweet' && empty($row['original_id'])) {
            continue;
        }

        $targetId = (int) (
            (($row['type'] ?? '') === 'retweet' && !empty($row['original_id']))
                ? $row['original_id']
                : $row['id']
        );

        if ($targetId <= 0) {
            continue;
        }

        if (!isset($grouped[$targetId])) {
            $row['reposters'] = [];

            if (($row['type'] ?? '') === 'retweet') {
                $row['reposters'][] = [
                    'user_id' => (int) $row['user_id'],
                    'firstname' => (string) ($row['firstname'] ?? ''),
                    'lastname' => (string) ($row['lastname'] ?? ''),
                    'username' => (string) ($row['username'] ?? ''),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                ];
            }

            $grouped[$targetId] = $row;
            continue;
        }

        $current = $grouped[$targetId];

        $currentIsRetweet = (($current['type'] ?? '') === 'retweet');
        $rowIsRetweet = (($row['type'] ?? '') === 'retweet');

        if ($rowIsRetweet) {
            $alreadyPresent = false;

            foreach (($current['reposters'] ?? []) as $reposter) {
                if ((int) $reposter['user_id'] === (int) $row['user_id']) {
                    $alreadyPresent = true;
                    break;
                }
            }

            if (!$alreadyPresent) {
                $current['reposters'][] = [
                    'user_id' => (int) $row['user_id'],
                    'firstname' => (string) ($row['firstname'] ?? ''),
                    'lastname' => (string) ($row['lastname'] ?? ''),
                    'username' => (string) ($row['username'] ?? ''),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                ];
            }
        }

        if ($rowIsRetweet && !$currentIsRetweet) {
            $row['reposters'] = $current['reposters'] ?? [];
            $grouped[$targetId] = $row;
            continue;
        }

        if ($rowIsRetweet === $currentIsRetweet) {
            $rowDate = strtotime((string) ($row['created_at'] ?? '')) ?: 0;
            $currentDate = strtotime((string) ($current['created_at'] ?? '')) ?: 0;

            if ($rowDate > $currentDate) {
                $row['reposters'] = $current['reposters'] ?? [];
                $grouped[$targetId] = $row;
            } else {
                $current['reposters'] = $current['reposters'] ?? [];
                $grouped[$targetId] = $current;
            }
        } else {
            $current['reposters'] = $current['reposters'] ?? [];
            $grouped[$targetId] = $current;
        }
    }

    $deduped = array_values($grouped);

    usort($deduped, function (array $a, array $b): int {
        $aDate = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
        $bDate = strtotime((string) ($b['created_at'] ?? '')) ?: 0;
        return $bDate <=> $aDate;
    });

    return $deduped;
}

    private function attachReplies(array $tweets, int $viewerId): array
{
    if (!$tweets) {
        return [];
    }

    foreach ($tweets as &$tweet) {
    $targetId = (int) (
        (($tweet['type'] ?? '') === 'retweet' && !empty($tweet['original_id']))
            ? $tweet['original_id']
            : $tweet['id']
    );

    $tweet['target_tweet_id'] = $targetId;
    $tweet['replies'] = $this->replyTreeForTweet($targetId, $viewerId);
    $tweet['can_reply'] = $this->canUserReplyToTweet($targetId, $viewerId);
    $tweet['root_tweet_id'] = (($tweet['type'] ?? '') === 'reply')
        ? $this->rootTweetIdForThread((int) $tweet['id'])
        : $targetId;
}
unset($tweet);

    return $tweets;
}

    private function fetchRepliesGrouped(array $tweetIds, int $viewerId): array
    {
        if (!$tweetIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($tweetIds), '?'));

        $sql = "SELECT
                    r.id,
                    r.user_id,
                    r.parent_id,
                    r.type,
                    r.tweet,
                    r.image,
                    r.created_at,
                    u.username,
                    u.firstname,
                    u.lastname,
                    u.icon,
                    (
                        SELECT COUNT(*)
                        FROM tweets_like tl
                        WHERE tl.tweets_id = r.id
                    ) AS likes_count,
                    EXISTS(
                        SELECT 1
                        FROM tweets_like tl2
                        WHERE tl2.tweets_id = r.id
                          AND tl2.user_id = ?
                    ) AS liked_by_me
                FROM tweets r
                INNER JOIN users u ON u.id = r.user_id
                WHERE r.is_deleted = 0
                  AND r.type = 'reply'
                  AND r.parent_id IN ($placeholders)
                ORDER BY r.created_at ASC";

        $params = array_merge([$viewerId], $tweetIds);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll() ?: [];
        $grouped = [];

        foreach ($rows as $row) {
            $parentId = (int) $row['parent_id'];
            $grouped[$parentId][] = $row;
        }

        return $grouped;
    }

    private function extractMentions(string $tweet): ?string
    {
        preg_match_all('/@([A-Za-z0-9_]+)/', $tweet, $matches);
        $mentions = $matches[1] ?? [];

        return $mentions ? implode(',', array_unique($mentions)) : null;
    }

    private function syncHashtags(int $tweetId, string $tweet): void
    {
        preg_match_all('/#([A-Za-z0-9_]+)/', $tweet, $matches);
        $hashtags = array_values(array_unique($matches[1] ?? []));

        if (!$hashtags) {
            return;
        }

        foreach ($hashtags as $tag) {
            $find = $this->db->prepare(
                'SELECT id
                 FROM hastag_list
                 WHERE name = :name
                 LIMIT 1'
            );
            $find->execute(['name' => $tag]);
            $hashtagId = (int) ($find->fetchColumn() ?: 0);

            if (!$hashtagId) {
                $insertTag = $this->db->prepare(
                    'INSERT INTO hastag_list (name, created_at)
                     VALUES (:name, NOW())'
                );
                $insertTag->execute(['name' => $tag]);
                $hashtagId = (int) $this->db->lastInsertId();
            }

            $link = $this->db->prepare(
                'INSERT INTO tweets_hastag (tweets_id, hastag_list_id, created_at)
                 VALUES (:tweet_id, :hashtag_id, NOW())'
            );

            $link->execute([
                'tweet_id' => $tweetId,
                'hashtag_id' => $hashtagId,
            ]);
        }
    }

    public function searchHashtagsByPrefix(string $q): array
{
    $stmt = $this->db->prepare(
        'SELECT name
         FROM hastag_list
         WHERE name LIKE :q
         ORDER BY name ASC
         LIMIT 10'
    );

    $stmt->execute([
        'q' => $q . '%',
    ]);

    return $stmt->fetchAll() ?: [];
}

public function findTweetById(int $tweetId): ?array
{
    $stmt = $this->db->prepare(
        'SELECT *
         FROM tweets
         WHERE id = :id
           AND is_deleted = 0
         LIMIT 1'
    );

    $stmt->execute(['id' => $tweetId]);
    return $stmt->fetch() ?: null;
}

public function canUserReplyToTweet(int $tweetId, int $viewerId): bool
{
    $tweet = $this->findTweetById($tweetId);
    if (!$tweet || $viewerId <= 0) {
        return false;
    }

    if ((int) $tweet['user_id'] === $viewerId) {
        return true;
    }

    $permission = (string) ($tweet['reply_permission'] ?? 'everyone');

    if ($permission === 'disabled') {
        return false;
    }

    if ($permission === 'everyone') {
        return true;
    }

    if ($permission === 'following') {
        $stmt = $this->db->prepare(
            'SELECT 1
             FROM follows
             WHERE following_user_id = :tweet_owner_id
               AND followed_user_id = :viewer_id
             LIMIT 1'
        );

        $stmt->execute([
            'tweet_owner_id' => (int) $tweet['user_id'],
            'viewer_id' => $viewerId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    if ($permission === 'mentioned') {
        $stmt = $this->db->prepare(
            'SELECT username
             FROM users
             WHERE id = :id
             LIMIT 1'
        );

        $stmt->execute(['id' => $viewerId]);
        $username = $stmt->fetchColumn();

        if (!$username) {
            return false;
        }

        $mentions = array_filter(array_map('trim', explode(',', (string) ($tweet['mention'] ?? ''))));
        return in_array((string) $username, $mentions, true);
    }

    return false;
}

public function createDraft(int $userId, string $body, ?string $image, string $replyPermission): int
{
    $stmt = $this->db->prepare(
        'INSERT INTO tweet_drafts (user_id, body, image, reply_permission, created_at, updated_at)
         VALUES (:user_id, :body, :image, :reply_permission, NOW(), NOW())'
    );

    $stmt->execute([
        'user_id' => $userId,
        'body' => $body,
        'image' => $image,
        'reply_permission' => $replyPermission,
    ]);

    return (int) $this->db->lastInsertId();
}

public function draftsForUser(int $userId): array
{
    $stmt = $this->db->prepare(
        'SELECT *
         FROM tweet_drafts
         WHERE user_id = :user_id
         ORDER BY updated_at DESC'
    );

    $stmt->execute(['user_id' => $userId]);

    return $stmt->fetchAll() ?: [];
}

public function findDraftById(int $draftId, int $userId): ?array
{
    $stmt = $this->db->prepare(
        'SELECT *
         FROM tweet_drafts
         WHERE id = :id
           AND user_id = :user_id
         LIMIT 1'
    );

    $stmt->execute([
        'id' => $draftId,
        'user_id' => $userId,
    ]);

    return $stmt->fetch() ?: null;
}

public function deleteDraft(int $draftId, int $userId): void
{
    $stmt = $this->db->prepare(
        'DELETE FROM tweet_drafts
         WHERE id = :id
           AND user_id = :user_id'
    );

    $stmt->execute([
        'id' => $draftId,
        'user_id' => $userId,
    ]);
}

public function tweetByIdForViewer(int $tweetId, int $viewerId): ?array
{
    $sql = $this->baseFeedSql() . '
        WHERE t.id = :tweet_id
          AND t.is_deleted = 0
        LIMIT 1';

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
        'liked_viewer_id' => $viewerId,
        'repost_viewer_id' => $viewerId,
        'tweet_id' => $tweetId,
    ]);

    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $row['target_tweet_id'] = (int) $row['id'];
    $row['can_reply'] = $this->canUserReplyToTweet((int) $row['id'], $viewerId);
    $row['replies'] = $this->replyTreeForTweet((int) $row['id'], $viewerId);

    return $row;
}

public function followersOfUser(int $userId): array
{
    $stmt = $this->db->prepare(
        'SELECT following_user_id
         FROM follows
         WHERE followed_user_id = :user_id'
    );

    $stmt->execute(['user_id' => $userId]);

    return array_map(
        fn(array $row): int => (int) $row['following_user_id'],
        $stmt->fetchAll() ?: []
    );
}

public function mentionedUserIdsInTweet(string $tweet): array
{
    preg_match_all('/@([A-Za-z0-9_]+)/', $tweet, $matches);
    $usernames = array_values(array_unique($matches[1] ?? []));

    if (!$usernames) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($usernames), '?'));

    $stmt = $this->db->prepare(
        "SELECT id
         FROM users
         WHERE username IN ($placeholders)
           AND is_deleted = 0"
    );

    $stmt->execute($usernames);

    return array_map(
        fn(array $row): int => (int) $row['id'],
        $stmt->fetchAll() ?: []
    );
}

public function rootTweetIdForThread(int $tweetId): int
{
    $currentId = $tweetId;

    while (true) {
        $stmt = $this->db->prepare(
            'SELECT id, parent_id, type
             FROM tweets
             WHERE id = :id
               AND is_deleted = 0
             LIMIT 1'
        );

        $stmt->execute(['id' => $currentId]);
        $row = $stmt->fetch();

        if (!$row) {
            return $tweetId;
        }

        if (($row['type'] ?? '') !== 'reply' || empty($row['parent_id'])) {
            return (int) $row['id'];
        }

        $currentId = (int) $row['parent_id'];
    }
}

public function replyTreeForTweet(int $tweetId, int $viewerId): array
{
    $flatReplies = $this->fetchAllRepliesForThread($tweetId, $viewerId);

    if (!$flatReplies) {
        return [];
    }

    return $this->buildReplyTree($flatReplies, $tweetId, $viewerId);
}

private function fetchAllRepliesForThread(int $tweetId, int $viewerId): array
{
    $stmt = $this->db->prepare(
        "WITH RECURSIVE reply_thread AS (
            SELECT
                r.id,
                r.user_id,
                r.parent_id,
                r.type,
                r.tweet,
                r.image,
                r.created_at,
                u.username,
                u.firstname,
                u.lastname,
                u.icon,
                (
                    SELECT COUNT(*)
                    FROM tweets_like tl
                    WHERE tl.tweets_id = r.id
                ) AS likes_count,
                EXISTS(
                    SELECT 1
                    FROM tweets_like tl2
                    WHERE tl2.tweets_id = r.id
                      AND tl2.user_id = :viewer_id
                ) AS liked_by_me
            FROM tweets r
            INNER JOIN users u ON u.id = r.user_id
            WHERE r.is_deleted = 0
              AND r.type = 'reply'
              AND r.parent_id = :tweet_id

            UNION ALL

            SELECT
                child.id,
                child.user_id,
                child.parent_id,
                child.type,
                child.tweet,
                child.image,
                child.created_at,
                u2.username,
                u2.firstname,
                u2.lastname,
                u2.icon,
                (
                    SELECT COUNT(*)
                    FROM tweets_like tl
                    WHERE tl.tweets_id = child.id
                ) AS likes_count,
                EXISTS(
                    SELECT 1
                    FROM tweets_like tl2
                    WHERE tl2.tweets_id = child.id
                      AND tl2.user_id = :viewer_id_recursive
                ) AS liked_by_me
            FROM tweets child
            INNER JOIN users u2 ON u2.id = child.user_id
            INNER JOIN reply_thread rt ON rt.id = child.parent_id
            WHERE child.is_deleted = 0
              AND child.type = 'reply'
        )
        SELECT *
        FROM reply_thread
        ORDER BY created_at ASC"
    );

    $stmt->execute([
        'viewer_id' => $viewerId,
        'tweet_id' => $tweetId,
        'viewer_id_recursive' => $viewerId,
    ]);

    return $stmt->fetchAll() ?: [];
}

private function buildReplyTree(array $flatReplies, int $rootTweetId, int $viewerId): array
{
    $grouped = [];

    foreach ($flatReplies as $reply) {
        $parentId = (int) $reply['parent_id'];
        $reply['children'] = [];
        $reply['can_reply'] = $this->canUserReplyToTweet((int) $reply['id'], $viewerId);
        $reply['root_tweet_id'] = $rootTweetId;
        $grouped[$parentId][] = $reply;
    }

    $buildBranch = function (int $parentId) use (&$buildBranch, &$grouped): array {
        $children = $grouped[$parentId] ?? [];

        foreach ($children as &$child) {
            $child['children'] = $buildBranch((int) $child['id']);
        }
        unset($child);

        return $children;
    };

    return $buildBranch($rootTweetId);
}
}