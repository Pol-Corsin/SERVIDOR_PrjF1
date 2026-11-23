<?php /* Autor: Pol Corsin — PT04 osu! Scores */ ?>
<?php
/**
 * Camp de formulari accessible.
 * Entrada: $id, $label, $type (text | password | select), $name (opcional, = $id), $value (MAI per a contrasenyes),
 *          $autocomplete, $inputmode, $maxlength, $hint, $error, $required (bool), $options (només select: valor => text).
 * - <label for> sempre visible.
 * - Ajuda i error enllaçats amb aria-describedby; aria-invalid="true" quan hi ha error.
 * - Les contrasenyes no es tornen a emplenar mai.
 */
$name = $name ?? $id;
$type = $type ?? 'text';
$isPassword = $type === 'password';
$describedBy = trim((!empty($hint) ? $id . '-hint ' : '') . (!empty($error) ? $id . '-error' : ''));
$attrs = ($describedBy !== '' ? ' aria-describedby="' . e($describedBy) . '"' : '')
       . (!empty($error) ? ' aria-invalid="true"' : '')
       . (!empty($required) ? ' required' : '')
       . (!empty($autocomplete) ? ' autocomplete="' . e($autocomplete) . '"' : '')
       . (!empty($inputmode) ? ' inputmode="' . e($inputmode) . '"' : '')
       . (!empty($maxlength) ? ' maxlength="' . (int) $maxlength . '"' : '');
?>
<div class="field">
    <label for="<?= e($id) ?>"><?= e($label) ?><?php if (!empty($required)): ?> <span class="req">(obligatori)</span><?php endif; ?></label>
    <?php if (!empty($hint)): ?><p class="hint" id="<?= e($id) ?>-hint"><?= e($hint) ?></p><?php endif; ?>
    <?php if ($type === 'select'): ?>
        <select class="input" id="<?= e($id) ?>" name="<?= e($name) ?>"<?= $attrs ?>>
            <?php foreach ($options as $optValue => $optLabel): ?>
                <option value="<?= e($optValue) ?>"<?= (string) ($value ?? '') === (string) $optValue ? ' selected' : '' ?>><?= e($optLabel) ?></option>
            <?php endforeach; ?>
        </select>
    <?php else: ?>
        <div<?= $isPassword ? ' class="password-wrap"' : '' ?>>
            <input class="input" id="<?= e($id) ?>" name="<?= e($name) ?>" type="<?= e($type) ?>"
                   <?= $isPassword ? '' : 'value="' . e($value ?? '') . '"' ?><?= $attrs ?>>
            <?php if ($isPassword): ?>
                <button type="button" class="password-toggle" data-password-toggle aria-controls="<?= e($id) ?>" aria-pressed="false" hidden>Mostra</button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?><p class="field-error" id="<?= e($id) ?>-error"><?= e($error) ?></p><?php endif; ?>
</div>
<?php unset($id, $label, $type, $name, $value, $autocomplete, $inputmode, $maxlength, $hint, $error, $required, $options, $isPassword, $describedBy, $attrs); ?>
