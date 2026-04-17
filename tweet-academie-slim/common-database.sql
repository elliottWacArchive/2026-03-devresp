CREATE DATABASE IF NOT EXISTS tweet_academie CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tweet_academie;

DROP TABLE IF EXISTS tweets_like;
DROP TABLE IF EXISTS tweets_hastag;
DROP TABLE IF EXISTS hastag_list;
DROP TABLE IF EXISTS message;
DROP TABLE IF EXISTS follows;
DROP TABLE IF EXISTS tweets;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(255) UNIQUE NOT NULL,
  lastname VARCHAR(255) NOT NULL,
  firstname VARCHAR(255) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  birthdate DATE NOT NULL,
  icon TEXT NULL,
  banner TEXT NULL,
  github VARCHAR(255) NULL,
  linkedin VARCHAR(255) NULL,
  bio TEXT NULL,
  is_deleted BOOLEAN NOT NULL DEFAULT 0,
  email_verified BOOLEAN NULL DEFAULT 1,
  verification_token TEXT NULL,
  reset_password_expires TIMESTAMP(3) NULL,
  reset_password_token TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  update_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE tweets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  parent_id INT NULL,
  type ENUM('original', 'retweet', 'reply') NOT NULL DEFAULT 'original',
  tweet TEXT NOT NULL,
  image TEXT NULL,
  mention TEXT NULL,
  is_deleted BOOLEAN NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tweets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_tweets_parent FOREIGN KEY (parent_id) REFERENCES tweets(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE hastag_list (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE tweets_hastag (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tweets_id INT NOT NULL,
  hastag_list_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tweets_hastag_tweet FOREIGN KEY (tweets_id) REFERENCES tweets(id) ON DELETE CASCADE,
  CONSTRAINT fk_tweets_hastag_hashtag FOREIGN KEY (hastag_list_id) REFERENCES hastag_list(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tweets_like (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tweets_id INT NOT NULL,
  user_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tweets_like_tweet FOREIGN KEY (tweets_id) REFERENCES tweets(id) ON DELETE CASCADE,
  CONSTRAINT fk_tweets_like_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT uq_tweets_like UNIQUE (tweets_id, user_id)
) ENGINE=InnoDB;

CREATE TABLE message (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(255) NOT NULL,
  from_user_id INT NOT NULL,
  to_user_id INT NOT NULL,
  message TEXT NOT NULL,
  unread BOOLEAN NOT NULL DEFAULT 1,
  is_deleted BOOLEAN NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_message_from FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_message_to FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE follows (
  following_user_id INT NOT NULL,
  followed_user_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (following_user_id, followed_user_id),
  CONSTRAINT fk_follows_following FOREIGN KEY (following_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_follows_followed FOREIGN KEY (followed_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
