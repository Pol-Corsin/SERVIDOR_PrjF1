-- ============================================================
-- Migració 002 — imatge de fons i URL oficial de les scores
-- Autor: Pol Corsin — PT04 osu! Scores
--
-- QUAN S'USA: si JA tens la base de dades pt04_pol_corsin importada (amb la migració 001
-- o el fitxer principal de la versió anterior) i vols afegir els dos camps nous SENSE perdre
-- usuaris ni scores.
--
-- COM EXECUTAR-LA A phpMyAdmin:
--   1. Obre http://localhost/phpmyadmin i selecciona la base de dades pt04_pol_corsin.
--   2. Pestanya "Importa" -> "Tria un fitxer" -> aquest fitxer -> "Importa".
--      (o bé pestanya "SQL", enganxa el contingut i prem "Executa").
--
-- És ADDITIVA (només afegeix dues columnes opcionals, NULL per defecte) i es pot executar
-- més d'una vegada (IF NOT EXISTS). No toca cap dada existent.
-- Si has importat el fitxer principal pt04_pol_corsin.sql actualitzat, NO cal aquesta migració.
-- Requereix MariaDB 10.0.2+ (XAMPP l'inclou).
-- ============================================================

USE pt04_pol_corsin;

ALTER TABLE scores
  ADD COLUMN IF NOT EXISTS image_path   VARCHAR(255) NULL DEFAULT NULL AFTER rank_result,
  ADD COLUMN IF NOT EXISTS official_url VARCHAR(255) NULL DEFAULT NULL AFTER image_path;

-- image_path   : ruta RELATIVA a public/ de la imatge de fons (p. ex. uploads/scores/<32 hex>.jpg). NULL = sense imatge.
-- official_url : URL HTTPS oficial de la score a osu.ppy.sh. NULL = sense enllaç.
