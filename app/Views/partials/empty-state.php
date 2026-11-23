<?php /* Autor: Pol Corsin — PT04 osu! Scores */ ?>
<?php
/**
 * Estat buit reutilitzable. Entrada: $heading, $message, $actionHref (opcional), $actionLabel (opcional).
 */
?>
<div class="empty">
    <h2><?= e($heading) ?></h2>
    <p><?= e($message) ?></p>
    <?php if (!empty($actionHref)): ?>
        <a class="btn btn--primary" href="<?= e($actionHref) ?>"><?= e($actionLabel) ?></a>
    <?php endif; ?>
</div>
<?php unset($heading, $message, $actionHref, $actionLabel); ?>
