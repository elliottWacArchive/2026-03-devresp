<?php

declare(strict_types=1);

namespace App\Models;

final class ShortUrl extends BaseModel
{
    public function create(string $targetPath): string
    {
        do {
            $code = $this->generateCode(6);

            $check = $this->db->prepare(
                'SELECT id FROM short_urls WHERE code = :code LIMIT 1'
            );
            $check->execute(['code' => $code]);
        } while ($check->fetch());

        $stmt = $this->db->prepare(
            'INSERT INTO short_urls (code, target_path, created_at)
             VALUES (:code, :target_path, NOW())'
        );

        $stmt->execute([
            'code' => $code,
            'target_path' => $targetPath,
        ]);

        return $code;
    }

    public function findTargetByCode(string $code): ?string
    {
        $stmt = $this->db->prepare(
            'SELECT target_path
             FROM short_urls
             WHERE code = :code
             LIMIT 1'
        );

        $stmt->execute(['code' => $code]);
        $target = $stmt->fetchColumn();

        return $target ? (string) $target : null;
    }

    private function generateCode(int $length): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $result = '';
        $max = strlen($chars) - 1;

        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[random_int(0, $max)];
        }

        return $result;
    }
}