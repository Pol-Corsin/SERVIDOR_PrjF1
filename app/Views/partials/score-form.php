<?php /* Autor: Pol Corsin — PT04 osu! Scores */ ?>
<?php
/**
 * Formulari d'una score (el fan servir "Afegeix" i "Edita").
 * Entrada: $formAction (URL), $values (valors a mostrar), $errors (camp => missatge), $formError (error general o null),
 *          $submitLabel (text del botó), $cancelHref (URL de "Cancel·la").
 * El propietari NO és un camp del formulari: surt de la sessió al servidor.
 */
$v = fn(string $k) => $values[$k] ?? '';
// Decimals de la BD ("4.00") es mostren amb coma i sense zeros sobrants; si l'usuari acaba d'enviar el formulari, es respecta el que va escriure
$dec = function (string $k) use ($v) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || $v($k) === '') { return $v($k); }
    return formatTrim($v($k), 2);
};
$rankOptions = ['' => 'Tria un rang'] + array_combine(Score::RANKS, Score::RANKS);
?>
<?php require ROOT_PATH . '/app/Views/partials/error-summary.php'; ?>
<?php if (!empty($formError)): ?>
    <?php $type = 'error'; $heading = $formError; $alertId = 'resum-errors'; require ROOT_PATH . '/app/Views/partials/alert.php'; ?>
<?php endif; ?>

<form class="panel" method="post" action="<?= e($formAction) ?>" novalidate>
    <?= csrfField() ?>

    <fieldset>
        <legend>Beatmap</legend>
        <div class="form-grid">
            <?php
            $id = 'beatmap_title'; $label = 'Títol'; $required = true; $value = $v($id); $error = $errors[$id] ?? null; $maxlength = 150;
            require ROOT_PATH . '/app/Views/partials/field.php';
            $id = 'artist'; $label = 'Artista'; $required = true; $value = $v($id); $error = $errors[$id] ?? null; $maxlength = 100;
            require ROOT_PATH . '/app/Views/partials/field.php';
            $id = 'difficulty_name'; $label = 'Nom de la dificultat'; $required = true; $value = $v($id); $error = $errors[$id] ?? null; $maxlength = 80;
            require ROOT_PATH . '/app/Views/partials/field.php';
            $id = 'mapper'; $label = 'Mapper'; $required = true; $value = $v($id); $error = $errors[$id] ?? null; $maxlength = 60;
            require ROOT_PATH . '/app/Views/partials/field.php';
            ?>
        </div>
    </fieldset>

    <fieldset>
        <legend>Dificultat</legend>
        <div class="form-grid">
            <?php
            $id = 'star_rating'; $label = 'Estrelles'; $required = true; $value = $dec($id); $error = $errors[$id] ?? null; $inputmode = 'decimal'; $hint = 'Entre 0 i 15. Exemple: 5,31';
            require ROOT_PATH . '/app/Views/partials/field.php';
            $id = 'bpm'; $label = 'BPM'; $required = true; $value = $v($id); $error = $errors[$id] ?? null; $inputmode = 'numeric'; $hint = 'Entre 1 i 1000.';
            require ROOT_PATH . '/app/Views/partials/field.php';
            $id = 'cs'; $label = 'CS (mida del cercle)'; $required = true; $value = $dec($id); $error = $errors[$id] ?? null; $inputmode = 'decimal'; $hint = 'Entre 0 i 10.';
            require ROOT_PATH . '/app/Views/partials/field.php';
            $id = 'ar'; $label = 'AR (velocitat d\'aproximació)'; $required = true; $value = $dec($id); $error = $errors[$id] ?? null; $inputmode = 'decimal'; $hint = 'Entre 0 i 10.';
            require ROOT_PATH . '/app/Views/partials/field.php';
            ?>
        </div>
    </fieldset>

    <fieldset>
        <legend>Resultat</legend>
        <div class="form-grid">
            <?php
            $id = 'rank_result'; $label = 'Rang'; $type = 'select'; $options = $rankOptions; $required = true; $value = $v($id); $error = $errors[$id] ?? null;
            require ROOT_PATH . '/app/Views/partials/field.php';
            $id = 'score'; $label = 'Score'; $required = true; $value = $v($id); $error = $errors[$id] ?? null; $inputmode = 'numeric'; $hint = 'Només dígits, sense punts. Exemple: 15568322';
            require ROOT_PATH . '/app/Views/partials/field.php';
            $id = 'accuracy'; $label = 'Precisió (%)'; $required = true; $value = $dec($id); $error = $errors[$id] ?? null; $inputmode = 'decimal'; $hint = 'Entre 0 i 100. Exemple: 98,75';
            require ROOT_PATH . '/app/Views/partials/field.php';
            $id = 'pp'; $label = 'PP'; $required = true; $value = $dec($id); $error = $errors[$id] ?? null; $inputmode = 'decimal'; $hint = 'Exemple: 312,5';
            require ROOT_PATH . '/app/Views/partials/field.php';
            $id = 'max_combo'; $label = 'Combo màxim'; $required = true; $value = $v($id); $error = $errors[$id] ?? null; $inputmode = 'numeric';
            require ROOT_PATH . '/app/Views/partials/field.php';
            ?>
        </div>
    </fieldset>

    <fieldset>
        <legend>Cops</legend>
        <div class="form-grid">
            <?php
            foreach (['count_300' => 'Cops de 300', 'count_100' => 'Cops de 100', 'count_50' => 'Cops de 50', 'count_miss' => 'Fallades'] as $id => $label) {
                $required = true; $value = $v($id); $error = $errors[$id] ?? null; $inputmode = 'numeric';
                require ROOT_PATH . '/app/Views/partials/field.php';
            }
            ?>
        </div>
    </fieldset>

    <div class="check">
        <input type="checkbox" id="is_published" name="is_published" value="1"<?= !empty($values['is_published']) ? ' checked' : '' ?> aria-describedby="is_published-hint">
        <div>
            <label for="is_published">Score publicada</label>
            <p class="hint" id="is_published-hint">Si la desmarques, només la veuràs tu.</p>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn--primary"><?= e($submitLabel) ?></button>
        <a class="btn" href="<?= e($cancelHref) ?>">Cancel·la</a>
    </div>
</form>
<?php unset($v, $dec, $rankOptions, $formAction, $values, $errors, $formError, $submitLabel, $cancelHref); ?>
