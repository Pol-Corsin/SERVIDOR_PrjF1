<?php
/**
 * Autor: Pol Corsin — PT04 osu! Scores
 * Pujada SEGURA d'imatges de fons de les scores.
 *
 * Mesures de seguretat:
 *  - El tipus es valida pel CONTINGUT real (finfo + getimagesize), mai per l'extensió ni pel MIME que envia el navegador.
 *  - Només JPEG, PNG i WebP. Màxim 5 MB i 8000x8000 px (evita bombes de descompressió).
 *  - El nom del fitxer és ALEATORI (32 hexadecimals) i l'extensió la posa el servidor segons el tipus detectat:
 *    el nom original mai s'usa (cap recorregut de directoris ni doble extensió possibles).
 *  - Es rebutgen fitxers que contenen etiquetes PHP (<?php, <?=) o <script (defensa addicional contra "políglots").
 *  - La carpeta public/uploads/scores/ té un .htaccess que prohibeix executar o servir res que no sigui una imatge.
 *  - A la BD només es guarda la ruta RELATIVA. Abans de mostrar-la o esborrar-la se'n torna a validar el format.
 *
 * NOTA: aquest PHP de XAMPP no té l'extensió GD activada, així que la imatge NO es recodifica
 * (no es treuen les metadades EXIF). Vegeu docs/BACKEND.md.
 */

const SCORE_IMAGE_MAX_BYTES = 5 * 1024 * 1024;     // 5 MB
const SCORE_IMAGE_MAX_SIDE = 8000;                 // píxels per costat
const SCORE_IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

function scoreImageDir(): string
{
    return ROOT_PATH . '/public/uploads/scores';
}

/** Ruta relativa vàlida? Només "uploads/scores/<32 hex>.(jpg|png|webp)". Evita rutes inventades, ".." i CSS injectat. */
function isSafeScoreImagePath(mixed $path): bool
{
    return is_string($path) && preg_match('#^uploads/scores/[a-f0-9]{32}\.(jpg|png|webp)$#', $path) === 1;
}

/** URL pública de la imatge, o null si la ruta guardada no és vàlida. */
function scoreImageUrl(mixed $path): ?string
{
    return isSafeScoreImagePath($path) ? asset($path) : null;
}

/** Hi ha un fitxer seleccionat al camp? (error 4 = cap fitxer) */
function hasUploadedFile(?array $file): bool
{
    return $file !== null && isset($file['error']) && (int) $file['error'] !== UPLOAD_ERR_NO_FILE;
}

/** Missatge en català per a un codi d'error de pujada de PHP. */
function uploadErrorMessage(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La imatge és massa gran. El màxim és de 5 MB.',
        UPLOAD_ERR_PARTIAL => 'La imatge s\'ha pujat a mitges. Torna-ho a provar.',
        default => 'No s\'ha pogut pujar la imatge. Torna-ho a provar.',
    };
}

/** Converteix "40M", "512K", "1G" a bytes. */
function iniBytes(string $v): int
{
    $v = trim($v);
    $n = (int) $v;
    return match (strtolower(substr($v, -1))) { 'g' => $n * 1024 ** 3, 'm' => $n * 1024 ** 2, 'k' => $n * 1024, default => $n };
}

/** True si la petició ha superat post_max_size (llavors PHP buida $_POST i $_FILES). */
function postExceededLimit(): bool
{
    $len = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $limit = iniBytes((string) ini_get('post_max_size'));
    return $len > 0 && $limit > 0 && $len > $limit && empty($_POST) && empty($_FILES);
}

/**
 * Valida la imatge pujada SENSE desar-la. Retorna [error|null, extensió|null].
 * Si no s'ha seleccionat cap fitxer, retorna [null, null].
 */
function validateScoreImage(?array $file): array
{
    if (!hasUploadedFile($file)) {
        return [null, null];
    }
    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        return [uploadErrorMessage((int) $file['error']), null];
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['No s\'ha pogut pujar la imatge. Torna-ho a provar.', null];
    }
    $size = (int) @filesize($tmp);
    if ($size <= 0) {
        return ['El fitxer és buit.', null];
    }
    if ($size > SCORE_IMAGE_MAX_BYTES) {
        return ['La imatge és massa gran. El màxim és de 5 MB.', null];
    }

    // Tipus REAL pel contingut
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmp);
    $info = @getimagesize($tmp);
    $typeOk = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png', IMAGETYPE_WEBP => 'image/webp'];
    if (!isset(SCORE_IMAGE_TYPES[$mime]) || $info === false || ($typeOk[$info[2]] ?? null) !== $mime) {
        return ['El fitxer no és una imatge vàlida. Només s\'accepten JPEG, PNG i WebP.', null];
    }
    if ($info[0] < 1 || $info[1] < 1 || $info[0] > SCORE_IMAGE_MAX_SIDE || $info[1] > SCORE_IMAGE_MAX_SIDE) {
        return ['La imatge és massa gran en píxels (màxim 8000 x 8000).', null];
    }

    // Defensa addicional: cap codi PHP ni <script dins del fitxer
    $content = (string) file_get_contents($tmp);
    if (stripos($content, '<?php') !== false || strpos($content, '<?=') !== false || stripos($content, '<script') !== false) {
        return ['El fitxer no és una imatge vàlida. Només s\'accepten JPEG, PNG i WebP.', null];
    }
    return [null, SCORE_IMAGE_TYPES[$mime]];
}

/**
 * Valida i DESA la imatge amb un nom aleatori. Retorna [ruta relativa|null, error|null].
 * Cal que validateScoreImage() hagi donat [null, ext] (o es torna a validar aquí).
 */
function storeScoreImage(?array $file): array
{
    [$error, $ext] = validateScoreImage($file);
    if ($error !== null || $ext === null) {
        return [null, $error];
    }
    $dir = scoreImageDir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        error_log('No es pot crear la carpeta d\'imatges: ' . $dir);
        return [null, 'No s\'ha pogut desar la imatge per un error del servidor.'];
    }
    $name = bin2hex(random_bytes(16)) . '.' . $ext;      // nom ALEATORI: l'original no s'usa mai
    $target = $dir . '/' . $name;
    if (!@move_uploaded_file((string) $file['tmp_name'], $target)) {
        error_log('move_uploaded_file ha fallat cap a ' . $target);
        return [null, 'No s\'ha pogut desar la imatge per un error del servidor.'];
    }
    @chmod($target, 0644);
    return ['uploads/scores/' . $name, null];
}

/** Esborra una imatge de score. Només actua si la ruta té el format vàlid i és dins de la carpeta d'imatges. */
function deleteScoreImage(mixed $path): void
{
    if (!isSafeScoreImagePath($path)) {
        return;
    }
    $file = scoreImageDir() . '/' . basename((string) $path);
    $real = realpath($file);
    $dir = realpath(scoreImageDir());
    if ($real !== false && $dir !== false && str_starts_with($real, $dir . DIRECTORY_SEPARATOR) && is_file($real)) {
        if (!@unlink($real)) {
            error_log('No s\'ha pogut esborrar la imatge ' . $real);
        }
    }
}
