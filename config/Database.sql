CREATE DATABASE IF NOT EXISTS `prf1_pol_corsin`
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE `prf1_pol_corsin`;

-- Tabla de usuarios
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,

    osu_id BIGINT NULL,
    profile_picture VARCHAR(255) DEFAULT 'default.png',

    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,

    rank INT NULL,
    nation CHAR(2) NULL,
    national_rank INT NULL,

    main_gamemode ENUM(
        'std',
        'taiko',
        'mania',
        'catch'
    ) DEFAULT 'std',

    osu_profile_url VARCHAR(255) NULL,

    reset_token VARCHAR(255) NULL,
    remember_me_token VARCHAR(255) NULL,

    social_provider VARCHAR(50) NULL,

    admin BOOLEAN DEFAULT FALSE,

    reset_token_expiry DATETIME NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de scores
CREATE TABLE scores (
    id INT AUTO_INCREMENT PRIMARY KEY,

    osu_score_id BIGINT NULL,

    user_id INT NOT NULL,

    song_name VARCHAR(255) NOT NULL,
    song_artist VARCHAR(255) NOT NULL,

    map_link VARCHAR(255) NULL,
    mapper_name VARCHAR(100) NULL,

    score_value BIGINT NOT NULL,
    rank_letter VARCHAR(5) NOT NULL,
    score_acc DECIMAL(6,3) NOT NULL,

    num_300 INT DEFAULT 0,
    num_100 INT DEFAULT 0,
    num_50 INT DEFAULT 0,
    num_miss INT DEFAULT 0,

    star_difficulty DECIMAL(5,2) NOT NULL,
    difficulty_name VARCHAR(100) NULL,

    cs DECIMAL(4,2) NULL,
    ar DECIMAL(4,2) NULL,
    bpm DECIMAL(6,2) NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_scores_users
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);

-- Índice para buscar rápidamente las scores de un usuario
CREATE INDEX idx_scores_user_id
ON scores(user_id);
