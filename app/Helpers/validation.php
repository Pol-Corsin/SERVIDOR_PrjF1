<?php
/**
 * Autor: Pol Corsin — PT04 osu! Scores
 * Validació d'entrada al SERVIDOR. Les funcions retornen missatges d'error en català.
 * (La validació del navegador és només una ajuda; mai és de confiança.)
 */

/** Nom d'usuari: 3-20 caràcters, només lletres (a-z), números i guió baix. Retorna error o null. */
function validateUsername(string $username): ?string
{
    if ($username === '') {
        return 'Escriu un nom d\'usuari.';
    }
    if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $username)) {
        return 'El nom d\'usuari ha de tenir entre 3 i 20 caràcters: lletres sense accents, números i guió baix.';
    }
    return null;
}

/**
 * Força de la contrasenya: mínim 8 caràcters, amb majúscula, minúscula i número.
 * Màxim 72 bytes: bcrypt ignora tot el que passa d'aquesta mida.
 */
function validatePasswordStrength(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'La contrasenya ha de tenir almenys 8 caràcters.';
    }
    if (strlen($password) > 72) {
        return 'La contrasenya no pot superar els 72 caràcters.';
    }
    if (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'La contrasenya ha de tenir una majúscula, una minúscula i un número.';
    }
    return null;
}

/** Text obligatori amb longitud màxima. Retorna [valor netejat, error|null]. */
function validateText(mixed $raw, string $label, int $max): array
{
    $value = trim((string) $raw);
    if ($value === '') {
        return [$value, "$label és obligatori."];
    }
    if (mb_strlen($value) > $max) {
        return [$value, "$label no pot superar els $max caràcters."];
    }
    return [$value, null];
}

/** Enter positiu dins d'un rang. Només dígits. Retorna [valor|null, error|null]. */
function validateInt(mixed $raw, string $label, int $min, int $max): array
{
    $value = trim((string) $raw);
    if (!preg_match('/^\d{1,12}$/', $value)) {
        return [null, "$label ha de ser un nombre enter, només amb dígits."];
    }
    $n = (int) $value;
    if ($n < $min || $n > $max) {
        return [null, "$label ha d'estar entre $min i $max."];
    }
    return [$n, null];
}

/** Decimal (accepta coma o punt) dins d'un rang. Retorna [valor|null, error|null]. */
function validateDecimal(mixed $raw, string $label, float $min, float $max): array
{
    $value = str_replace(',', '.', trim((string) $raw));
    if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $value)) {
        return [null, "$label ha de ser un nombre amb com a màxim 2 decimals (per exemple 98,5)."];
    }
    $n = (float) $value;
    if ($n < $min || $n > $max) {
        return [null, "$label ha d'estar entre " . str_replace('.', ',', (string) $min) . ' i ' . str_replace('.', ',', (string) $max) . '.'];
    }
    return [$n, null];
}

/**
 * Valida el formulari d'edició d'una score. NO accepta id, user_id ni created_at:
 * aquests camps no es poden modificar (el propietari surt sempre de la sessió).
 * Retorna [dades netes, errors per camp].
 */
function validateScoreInput(array $post): array
{
    $clean = [];
    $errors = [];

    $texts = [
        'beatmap_title'   => ['El títol del beatmap', 150],
        'artist'          => ['L\'artista', 100],
        'difficulty_name' => ['El nom de la dificultat', 80],
        'mapper'          => ['El mapper', 60],
    ];
    foreach ($texts as $field => [$label, $max]) {
        [$clean[$field], $err] = validateText($post[$field] ?? '', $label, $max);
        if ($err) { $errors[$field] = $err; }
    }

    $ints = [
        'bpm'        => ['El BPM', 1, 1000],
        'score'      => ['El score', 0, 9999999999],
        'max_combo'  => ['El combo màxim', 0, 100000],
        'count_300'  => ['Els cops de 300', 0, 100000],
        'count_100'  => ['Els cops de 100', 0, 100000],
        'count_50'   => ['Els cops de 50', 0, 100000],
        'count_miss' => ['Les fallades', 0, 100000],
    ];
    foreach ($ints as $field => [$label, $min, $max]) {
        [$clean[$field], $err] = validateInt($post[$field] ?? '', $label, $min, $max);
        if ($err) { $errors[$field] = $err; }
    }

    $decimals = [
        'star_rating' => ['Les estrelles', 0, 15],
        'cs'          => ['El CS', 0, 10],
        'ar'          => ['L\'AR', 0, 10],
        'accuracy'    => ['La precisió', 0, 100],
        'pp'          => ['Els PP', 0, 20000],
    ];
    foreach ($decimals as $field => [$label, $min, $max]) {
        [$clean[$field], $err] = validateDecimal($post[$field] ?? '', $label, $min, $max);
        if ($err) { $errors[$field] = $err; }
    }

    // El rang només pot ser un dels valors permesos (llista blanca)
    $rank = (string) ($post['rank_result'] ?? '');
    if (!in_array($rank, Score::RANKS, true)) {
        $errors['rank_result'] = 'Tria un rang vàlid (SS, S, A, B, C o D).';
    }
    $clean['rank_result'] = $rank;

    // Casella: si no ve marcada, no s'envia
    $clean['is_published'] = isset($post['is_published']) ? 1 : 0;

    return [$clean, $errors];
}
