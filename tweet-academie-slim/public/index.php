<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\FollowController;
use App\Controllers\HomeController;
use App\Controllers\MessageController;
use App\Controllers\NotificationController;
use App\Controllers\ProfileController;
use App\Controllers\ShortUrlController;
use App\Controllers\TweetController;
use App\Middleware\AuthMiddleware;
use App\Models\Follow;
use App\Models\Message;
use App\Models\Notification;
use App\Models\ShortUrl;
use App\Models\Tweet;
use App\Models\User;
use DI\Container;
use Dotenv\Dotenv;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

session_start();

if (file_exists(__DIR__ . '/../.env')) {
    Dotenv::createImmutable(__DIR__ . '/..')->load();
}

$container = new Container();

$dbFactory = require __DIR__ . '/../config/database.php';
$container->set(PDO::class, $dbFactory);

$container->set(User::class, fn() => new User($container->get(PDO::class)));
$container->set(Tweet::class, fn() => new Tweet($container->get(PDO::class)));
$container->set(Follow::class, fn() => new Follow($container->get(PDO::class)));
$container->set(Message::class, fn() => new Message($container->get(PDO::class)));
$container->set(Notification::class, fn() => new Notification($container->get(PDO::class)));
$container->set(ShortUrl::class, fn() => new ShortUrl($container->get(PDO::class)));

$container->set(AuthController::class, fn() => new AuthController(
    $container->get(User::class)
));

$container->set(HomeController::class, fn() => new HomeController(
    $container->get(Tweet::class),
    $container->get(User::class),
    $container->get(Notification::class),
    $container->get(Message::class)
));

$container->set(TweetController::class, fn() => new TweetController(
    $container->get(Tweet::class),
    $container->get(Notification::class),
    $container->get(Message::class),
    $container->get(ShortUrl::class)
));

$container->set(ProfileController::class, fn() => new ProfileController(
    $container->get(User::class),
    $container->get(Tweet::class),
    $container->get(Follow::class),
    $container->get(Message::class),
    $container->get(Notification::class)
));

$container->set(FollowController::class, fn() => new FollowController(
    $container->get(Follow::class),
    $container->get(User::class),
    $container->get(Notification::class)
));

$container->set(MessageController::class, fn() => new MessageController(
    $container->get(Message::class),
    $container->get(User::class),
    $container->get(Follow::class),
    $container->get(Notification::class)
));

$container->set(NotificationController::class, fn() => new NotificationController(
    $container->get(Notification::class),
    $container->get(Message::class)
));

$container->set(ShortUrlController::class, fn() => new ShortUrlController(
    $container->get(ShortUrl::class)
));

$container->set(AuthMiddleware::class, fn() => new AuthMiddleware());

AppFactory::setContainer($container);
$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

(require __DIR__ . '/../routes/web.php')($app, $container);

$app->run();