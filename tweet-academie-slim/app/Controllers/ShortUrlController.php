<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ShortUrl;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ShortUrlController
{
    public function __construct(private ShortUrl $shortUrls)
    {
    }

    public function openImage(Request $request, Response $response, array $args): Response
    {
        $code = (string) ($args['code'] ?? '');
        $target = $this->shortUrls->findTargetByCode($code);

        if (!$target) {
            $response->getBody()->write('Image introuvable');
            return $response->withStatus(404);
        }

        return $response->withHeader('Location', $target)->withStatus(302);
    }
}