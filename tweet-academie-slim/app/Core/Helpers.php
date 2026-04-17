<?php

declare(strict_types=1);

namespace App\Core;

final class Helpers
{
    public static function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function tweetPasswordHash(string $password): string
    {
        return hash('ripemd160', 'vive le projet tweet_academy' . $password);
    }

    public static function formatDate(?string $date): string
    {
        if (!$date) {
            return '';
        }

        return date('d/m/Y H:i', strtotime($date));
    }
}
