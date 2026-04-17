<?php

declare(strict_types=1);

namespace App\Models;

final class User extends BaseModel
{
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email AND is_deleted = 0 LIMIT 1');
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id AND is_deleted = 0 LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = :username AND is_deleted = 0 LIMIT 1');
        $stmt->execute(['username' => $username]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, lastname, firstname, email, password, birthdate, icon, banner, github, linkedin, bio, is_private, is_deleted, email_verified, verification_token, reset_password_expires, reset_password_token, created_at, update_at)
             VALUES (:username, :lastname, :firstname, :email, :password, :birthdate, NULL, NULL, NULL, NULL, NULL, 0, 0, 1, NULL, NULL, NULL, NOW(), NOW())'
        );

        $stmt->execute([
            'username' => $data['username'],
            'lastname' => $data['lastname'],
            'firstname' => $data['firstname'],
            'email' => $data['email'],
            'password' => $data['password'],
            'birthdate' => $data['birthdate'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateProfile(int $id, array $data): void
{
    $stmt = $this->db->prepare(
        'UPDATE users
         SET firstname = :firstname,
             lastname = :lastname,
             bio = :bio,
             github = :github,
             linkedin = :linkedin,
             icon = :icon,
             banner = :banner,
             is_private = :is_private,
             allow_dm_from_anyone = :allow_dm_from_anyone,
             update_at = NOW()
         WHERE id = :id'
    );

    $stmt->execute([
        'id' => $id,
        'firstname' => $data['firstname'],
        'lastname' => $data['lastname'],
        'bio' => $data['bio'],
        'github' => $data['github'],
        'linkedin' => $data['linkedin'],
        'icon' => $data['icon'],
        'banner' => $data['banner'],
        'is_private' => !empty($data['is_private']) ? 1 : 0,
        'allow_dm_from_anyone' => !empty($data['allow_dm_from_anyone']) ? 1 : 0,
    ]);
}

    public function search(string $query): array
    {
        $query = trim(mb_strtolower($query));

        if ($query === '') {
            return [];
        }

        if (str_starts_with($query, '@')) {
            $query = substr($query, 1);
        }

        $parts = preg_split('/\s+/', $query) ?: [];
        $parts = array_values(array_filter($parts));

        $sql = 'SELECT id, username, firstname, lastname, icon
                FROM users
                WHERE is_deleted = 0';

        $params = [];

        if (count($parts) === 1) {
            $sql .= ' AND (
                        LOWER(username) LIKE :term_username
                        OR LOWER(firstname) LIKE :term_firstname
                        OR LOWER(lastname) LIKE :term_lastname
                      )
                      ORDER BY username ASC
                      LIMIT 10';

            $term = '%' . $parts[0] . '%';

            $params = [
                'term_username' => $term,
                'term_firstname' => $term,
                'term_lastname' => $term,
            ];
        } else {
            $sql .= ' AND (
                        LOWER(username) LIKE :full_username
                        OR LOWER(firstname) LIKE :full_firstname
                        OR LOWER(lastname) LIKE :full_lastname
                        OR (
                            LOWER(firstname) LIKE :first_part
                            AND LOWER(lastname) LIKE :second_part
                        )
                        OR (
                            LOWER(firstname) LIKE :second_part_alt
                            AND LOWER(lastname) LIKE :first_part_alt
                        )
                      )
                      ORDER BY username ASC
                      LIMIT 10';

            $full = '%' . implode(' ', $parts) . '%';
            $first = '%' . $parts[0] . '%';
            $second = '%' . ($parts[1] ?? $parts[0]) . '%';

            $params = [
                'full_username' => $full,
                'full_firstname' => $full,
                'full_lastname' => $full,
                'first_part' => $first,
                'second_part' => $second,
                'second_part_alt' => $second,
                'first_part_alt' => $first,
            ];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public function followersCount(int $id): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM follows WHERE followed_user_id = :id');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    public function followingCount(int $id): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM follows WHERE following_user_id = :id');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    public function followersList(int $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.username, u.firstname, u.lastname, u.icon
             FROM follows f
             INNER JOIN users u ON u.id = f.following_user_id
             WHERE f.followed_user_id = :id
               AND u.is_deleted = 0
             ORDER BY u.username ASC'
        );

        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll() ?: [];
    }

    public function followingList(int $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.username, u.firstname, u.lastname, u.icon
             FROM follows f
             INNER JOIN users u ON u.id = f.followed_user_id
             WHERE f.following_user_id = :id
               AND u.is_deleted = 0
             ORDER BY u.username ASC'
        );

        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll() ?: [];
    }

    public function searchByUsername(string $q): array
{
    $stmt = $this->db->prepare("
        SELECT id, username, icon
        FROM users
        WHERE username LIKE :q
        LIMIT 10
    ");

    $stmt->execute(['q' => $q . '%']);

    return $stmt->fetchAll() ?: [];
}
}