<?php /* Autor: Pol Corsin — PT04 osu! Scores */ ?>
<?php
/**
 * Missatge d'estat reutilitzable.
 * Entrada: $type ('success' | 'error' | 'info'), $heading (títol curt), $message (text),
 *          $items (opcional: llista d'errors).
 * Errors: role="alert" (s'anuncia a l'instant). Èxit/info: role="status" (educat).
 * Cada tipus té icona + text, mai només color (WCAG 1.4.1).
 */
$icons = ['success' => '✔', 'error' => '⚠', 'info' => 'ℹ'];
$role = $type === 'error' ? 'alert' : 'status';
?>
<div class="alert alert--<?= e($type) ?>" role="<?= e($role) ?>"<?= isset($alertId) ? ' id="' . e($alertId) . '" tabindex="-1"' : '' ?>>
    <span class="alert__icon" aria-hidden="true"><?= $icons[$type] ?? 'ℹ' ?></span>
    <div class="alert__body">
        <strong class="alert__title"><?= e($heading) ?></strong>
        <?php if (!empty($message)): ?><p><?= e($message) ?></p><?php endif; ?>
        <?php if (!empty($items)): ?>
            <ul>
                <?php foreach ($items as $href => $text): ?>
                    <li><a href="#<?= e($href) ?>"><?= e($text) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
<?php unset($type, $heading, $message, $items, $alertId, $icons, $role); ?>
