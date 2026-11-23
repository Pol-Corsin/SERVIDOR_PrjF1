-- ============================================================
-- Migració 001 — camps addicionals de les scores (inspirats a la targeta de referència)
-- Autor: Pol Corsin — PT04 osu! Scores
--
-- QUAN S'USA: si JA tens la base de dades pt04_pol_corsin importada (Fase 1) i vols
-- afegir-hi els nous camps SENSE perdre usuaris ni scores. Importa aquest fitxer des de
-- phpMyAdmin (pestanya "Importa") o executa'l a la pestanya SQL amb la BD seleccionada.
--
-- És una migració ADDITIVA i es pot executar més d'una vegada (IF NOT EXISTS, i només
-- omple les 12 scores de demostració que encara no tenen les dades noves).
-- Requereix MariaDB 10.0.2+ (XAMPP l'inclou).
-- Si has importat el fitxer principal pt04_pol_corsin.sql actualitzat, NO cal aquesta migració.
-- ============================================================

USE pt04_pol_corsin;

ALTER TABLE scores
  ADD COLUMN IF NOT EXISTS mapper      VARCHAR(60)   NOT NULL DEFAULT ''   AFTER difficulty_name,
  ADD COLUMN IF NOT EXISTS star_rating DECIMAL(4,2)  NOT NULL DEFAULT 0.00 AFTER mapper,
  ADD COLUMN IF NOT EXISTS bpm         SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER star_rating,
  ADD COLUMN IF NOT EXISTS cs          DECIMAL(3,1)  NOT NULL DEFAULT 0.0  AFTER bpm,
  ADD COLUMN IF NOT EXISTS ar          DECIMAL(3,1)  NOT NULL DEFAULT 0.0  AFTER cs,
  ADD COLUMN IF NOT EXISTS count_300   INT UNSIGNED  NOT NULL DEFAULT 0    AFTER max_combo,
  ADD COLUMN IF NOT EXISTS count_100   INT UNSIGNED  NOT NULL DEFAULT 0    AFTER count_300,
  ADD COLUMN IF NOT EXISTS count_50    INT UNSIGNED  NOT NULL DEFAULT 0    AFTER count_100,
  ADD COLUMN IF NOT EXISTS count_miss  INT UNSIGNED  NOT NULL DEFAULT 0    AFTER count_50;

-- Dades de demostració FICTÍCIES per a les 12 scores de mostra (només si encara estan buides).
UPDATE scores s
JOIN (
  SELECT 1 AS id, 'Nyx_Beat' AS mapper, 6.42 AS star_rating, 184 AS bpm, 4.0 AS cs, 9.3 AS ar, 1862 AS count_300, 27 AS count_100, 7 AS count_50, 0 AS count_miss
  UNION ALL
  SELECT 2, 'Kairo', 5.87, 170, 4.0, 9.0, 1207, 0, 0, 0
  UNION ALL
  SELECT 3, 'lumen', 4.95, 150, 4.2, 8.5, 768, 61, 16, 2
  UNION ALL
  SELECT 4, 'hanaMapper', 3.60, 128, 4.0, 8.0, 439, 61, 15, 3
  UNION ALL
  SELECT 5, 'Nyx_Beat', 6.11, 196, 4.0, 9.2, 967, 27, 7, 1
  UNION ALL
  SELECT 6, 'Kairo', 5.20, 162, 4.0, 8.8, 625, 63, 16, 2
  UNION ALL
  SELECT 7, 'Vesper', 6.98, 210, 4.3, 9.6, 1331, 74, 19, 3
  UNION ALL
  SELECT 8, 'cleanmaps', 2.35, 110, 3.5, 7.0, 286, 58, 15, 5
  UNION ALL
  SELECT 9, 'Vesper', 6.65, 188, 4.0, 9.4, 1296, 14, 3, 0
  UNION ALL
  SELECT 10, 'Starmaker', 4.10, 140, 4.0, 8.2, 362, 59, 15, 4
  UNION ALL
  SELECT 11, 'Starmaker', 1.85, 100, 3.0, 6.0, 115, 62, 15, 9
  UNION ALL
  SELECT 12, 'Kairo', 6.20, 175, 4.0, 9.2, 1108, 0, 0, 0
) v ON v.id = s.id
SET s.mapper = v.mapper, s.star_rating = v.star_rating, s.bpm = v.bpm, s.cs = v.cs, s.ar = v.ar,
    s.count_300 = v.count_300, s.count_100 = v.count_100, s.count_50 = v.count_50, s.count_miss = v.count_miss
WHERE s.mapper = '' AND s.id <= 12;
