<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Helpers;
use App\Core\View;
use App\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuthController
{
    public function __construct(private User $users)
    {
    }

    public function showLogin(Request $request, Response $response): Response
    {
        ob_start();
        View::render('auth/login', ['title' => 'Connexion']);
        $response->getBody()->write((string) ob_get_clean());
        return $response;
    }

    public function login(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $user = $this->users->findByEmail(trim((string) ($data['email'] ?? '')));

        if (!$user || $user['password'] !== Helpers::tweetPasswordHash((string) ($data['password'] ?? ''))) {
            $_SESSION['flash_error'] = 'Identifiants invalides.';
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        Auth::login([
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'firstname' => $user['firstname'],
            'lastname' => $user['lastname'],
            'email' => $user['email'],
            'icon' => $user['icon'],
        ]);

        return $response->withHeader('Location', '/')->withStatus(302);
    }

    public function showRegister(Request $request, Response $response): Response
    {
        ob_start();
        View::render('auth/register', ['title' => 'Inscription']);
        $response->getBody()->write((string) ob_get_clean());
        return $response;
    }

    public function register(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();

        $required = ['username', 'lastname', 'firstname', 'email', 'password', 'birthdate'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $_SESSION['flash_error'] = 'Tous les champs obligatoires doivent être remplis.';
                return $response->withHeader('Location', '/register')->withStatus(302);
            }
        }

        if ($this->users->findByEmail((string) $data['email'])) {
            $_SESSION['flash_error'] = 'Cet email existe déjà.';
            return $response->withHeader('Location', '/register')->withStatus(302);
        }

        $id = $this->users->create([
            'username' => trim((string) $data['username']),
            'lastname' => trim((string) $data['lastname']),
            'firstname' => trim((string) $data['firstname']),
            'email' => trim((string) $data['email']),
            'password' => Helpers::tweetPasswordHash((string) $data['password']),
            'birthdate' => (string) $data['birthdate'],
        ]);

        $user = $this->users->findById($id);
        if ($user) {
            Auth::login([
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'firstname' => $user['firstname'],
                'lastname' => $user['lastname'],
                'email' => $user['email'],
                'icon' => $user['icon'],
            ]);
        }

        return $response->withHeader('Location', '/')->withStatus(302);
    }

    public function logout(Request $request, Response $response): Response
    {
        Auth::logout();
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
}
