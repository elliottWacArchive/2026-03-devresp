# Tweet Académie — Slim + Skeleton + Alpine.js + fetch() + MySQL

Base de projet conforme à ton choix techno : **PHP + Slim + Skeleton + Alpine.js + fetch() + MySQL**.

## Ce que le squelette couvre déjà
- inscription / connexion / déconnexion
- hash mot de passe en `ripemd160` avec le salt demandé
- timeline des comptes suivis + soi-même
- création de tweet avec limite 140 caractères
- hashtags automatiques
- mentions `@pseudo` dans l'affichage
- like / unlike en Ajax
- recherche de profils en Ajax
- follow / unfollow en Ajax
- édition du profil
- messagerie privée simple
- rafraîchissement automatique de la timeline toutes les 15 secondes
- upload image dans les tweets

## Point important sur la base
Ta DBML contient une incohérence technique :
- `users.id` est un `integer`
- `message.from_user_id` et `message.to_user_id` sont en `varchar(255)`
- mais ils sont utilisés comme clés étrangères vers `users.id`

Dans le fichier `common-database.sql`, ces deux colonnes ont été passées en `INT` pour que MySQL accepte les contraintes de clé étrangère.

## Installation
```bash
composer install
cp .env.example .env
```

Ensuite, renseigne tes accès MySQL dans `.env`.

Importe la base :
```bash
mysql -u root -p < common-database.sql
```

Lance le serveur PHP :
```bash
php -S localhost:8000 -t public
```

## Comptes de test
Crée-les via le formulaire d'inscription.

## Routes principales
- `GET /login`
- `GET /register`
- `GET /`
- `GET /profile/{username}`
- `GET /messages`
- `GET /hashtag/{tag}`

## Prochaines améliorations conseillées
- retweet réel
- replies affichées en thread
- liste followers / following
- recherche hashtag dédiée
- suggestions `@pseudo` pendant la frappe dans le textarea
- thème clair/sombre mémorisé
- pagination de timeline
- meilleure validation upload image
- CSRF
