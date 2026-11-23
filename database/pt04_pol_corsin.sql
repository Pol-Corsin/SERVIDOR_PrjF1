-- ============================================================
-- Projecte: osu! Scores  (PT04)
-- Autor:    Pol Corsin
-- Fitxer:   database/pt04_pol_corsin.sql
--
-- Importar des de phpMyAdmin: pestanya "Importa" > triar aquest fitxer > "Executa".
-- El script crea la base de dades si no existeix, així que NO cal crear-la abans.
-- Es pot tornar a importar: les taules s'esborren i es tornen a crear.
--
-- NOTA: l'enunciat del professor menciona "pt03" en un exemple. Es considera una
-- inconsistència; s'usa "pt04" de manera coherent (aquí i a config/config.php).
--
-- ATENCIÓ: els usuaris "demo_*" són comptes FICTICIS per a proves locals.
-- La contrasenya de demostració NO s'ha d'usar mai per a usuaris reals.
-- ============================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS pt04_pol_corsin
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE pt04_pol_corsin;

-- Esborrem primer scores (depèn de users) i després users.
DROP TABLE IF EXISTS scores;
DROP TABLE IF EXISTS users;

-- ------------------------------------------------------------
-- Taula users
-- ------------------------------------------------------------
CREATE TABLE users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(20)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,          -- sortida de password_hash(); mai text pla
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username)       -- nom d'usuari únic
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Taula scores
-- ------------------------------------------------------------
CREATE TABLE scores (
  id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED  NOT NULL,
  beatmap_title   VARCHAR(150)  NOT NULL,
  artist          VARCHAR(100)  NOT NULL,
  difficulty_name VARCHAR(80)   NOT NULL,
  mapper          VARCHAR(60)   NOT NULL DEFAULT '',
  star_rating     DECIMAL(4,2)  NOT NULL DEFAULT 0.00,
  bpm             SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  cs              DECIMAL(3,1)  NOT NULL DEFAULT 0.0,   -- Circle Size
  ar              DECIMAL(3,1)  NOT NULL DEFAULT 0.0,   -- Approach Rate
  score           BIGINT UNSIGNED NOT NULL,
  accuracy        DECIMAL(5,2)  NOT NULL,        -- 0.00 - 100.00
  pp              DECIMAL(7,2)  NOT NULL DEFAULT 0.00,
  max_combo       INT UNSIGNED  NOT NULL,
  count_300       INT UNSIGNED  NOT NULL DEFAULT 0,
  count_100       INT UNSIGNED  NOT NULL DEFAULT 0,
  count_50        INT UNSIGNED  NOT NULL DEFAULT 0,
  count_miss      INT UNSIGNED  NOT NULL DEFAULT 0,
  rank_result     ENUM('SS','S','A','B','C','D') NOT NULL,
  is_published    TINYINT(1)    NOT NULL DEFAULT 1,
  created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_scores_user_id (user_id),
  KEY idx_scores_published_created (is_published, created_at),
  CONSTRAINT fk_scores_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Dades de demostració: 4 usuaris (contrasenya de proves: Osu!Demo2026)
-- Només es guarda el hash generat amb password_hash().
-- ------------------------------------------------------------
INSERT INTO users (id, username, password_hash) VALUES
  (1, 'demo_aiko',  '$2y$10$LDFqLBHX3MLlFJiOCU3JYO8Hl/nVBG6LSk811tuz2iVtLkIP80eoy'),
  (2, 'demo_ren',   '$2y$10$JAmYcXUyaBVXK.XzLqKSZeXJLNzEB0wZiXclvhpVDif6gjZh0/3qa'),
  (3, 'demo_mika',  '$2y$10$y5QSBYEwYPgkb3pppFJ4J.SR1uMcyn4dJ.g.aWFzRAikSyRb0N45u'),
  (4, 'demo_kaito', '$2y$10$RkXwvnZdXnw1zlbqSYbaGuTf0e9xBTTczAFq8BG.iqWoAlUIxOPl2');

-- ------------------------------------------------------------
-- 12 scores fictícies (títols, artistes, mappers i xifres inventats), 3 per usuari.
-- Els comptadors 300/100/50/miss són coherents amb la precisió.
-- Rangs: SS x2, S x3, A x3, B x2, C x1, D x1. Totes publicades.
-- ------------------------------------------------------------
INSERT INTO scores
  (user_id, beatmap_title, artist, difficulty_name, mapper, star_rating, bpm, cs, ar, score, accuracy, pp, max_combo, count_300, count_100, count_50, count_miss, rank_result, is_published, created_at)
VALUES
  (1, 'Midnight Circuit', 'Aurora Vale', 'Extra', 'Nyx_Beat', 6.42, 184, 4.0, 9.3, 24185320, 98.74, 412.60, 1893, 1862, 27, 7, 0, 'S', 1, '2026-09-02 18:20:00'),
  (1, 'Crystal Pulse', 'Neon Harbor', 'Insane', 'Kairo', 5.87, 170, 4.0, 9.0, 9876540, 100.00, 325.10, 1204, 1207, 0, 0, 0, 'SS', 1, '2026-09-09 20:05:00'),
  (1, 'Sidewalk Echoes', 'Violet Static', 'Hard', 'lumen', 4.95, 150, 4.2, 8.5, 6543210, 93.41, 198.35, 842, 768, 61, 16, 2, 'A', 1, '2026-09-16 17:45:00'),
  (2, 'Paper Lanterns', 'Hana Mori', 'Hard', 'hanaMapper', 3.60, 128, 4.0, 8.0, 3120450, 89.12, 95.80, 512, 439, 61, 15, 3, 'B', 1, '2026-09-21 19:10:00'),
  (2, 'Overclocked Heart', 'Digital Bloom', 'Another', 'Nyx_Beat', 6.11, 196, 4.0, 9.2, 8456320, 97.55, 243.90, 998, 967, 27, 7, 1, 'S', 1, '2026-09-28 21:30:00'),
  (2, 'Glass Horizon', 'Kairo', 'Insane', 'Kairo', 5.20, 162, 4.0, 8.8, 5210980, 91.88, 150.45, 701, 625, 63, 16, 2, 'A', 1, '2026-10-03 16:55:00'),
  (3, 'Thunder Waltz', 'Aria Noir', 'Overdose', 'Vesper', 6.98, 210, 4.3, 9.6, 15678230, 95.20, 301.75, 1421, 1331, 74, 19, 3, 'A', 1, '2026-10-08 22:15:00'),
  (3, 'Quiet Rain', 'Clean Waters', 'Normal', 'cleanmaps', 2.35, 110, 3.5, 7.0, 1450300, 84.63, 48.20, 356, 286, 58, 15, 5, 'C', 1, '2026-10-14 15:40:00'),
  (3, 'Shoot the Moon', 'Fractal Dreamers', 'Expert', 'Vesper', 6.65, 188, 4.0, 9.4, 10234560, 99.12, 356.80, 1310, 1296, 14, 3, 0, 'S', 1, '2026-10-19 20:50:00'),
  (4, 'Neon Pulse', 'Starlight Unit', 'Hard', 'Starmaker', 4.10, 140, 4.0, 8.2, 2780100, 87.30, 72.40, 433, 362, 59, 15, 4, 'B', 1, '2026-10-25 18:05:00'),
  (4, 'Last Waltz', 'Aria Noir', 'Easy', 'Starmaker', 1.85, 100, 3.0, 6.0, 620400, 68.90, 12.60, 189, 115, 62, 15, 9, 'D', 1, '2026-11-02 14:25:00'),
  (4, 'Spectrum Rush', 'Kairo', 'Insane', 'Kairo', 6.20, 175, 4.0, 9.2, 7788990, 100.00, 288.00, 1105, 1108, 0, 0, 0, 'SS', 1, '2026-11-10 19:35:00');
