<?php /* Autor: Pol Corsin — PT04 osu! Scores */ ?>
<div class="page confirm">
    <nav class="breadcrumb" aria-label="Camí de navegació">
        <ol>
            <li><a href="<?= e(url('/my-scores')) ?>">Les meves scores</a></li>
            <li aria-current="page">Elimina</li>
        </ol>
    </nav>

    <h1>Vols eliminar aquesta score?</h1>
    <?php
    $type = 'error';
    $heading = 'Aquesta acció no es pot desfer.';
    $message = 'La score «' . $score['beatmap_title'] . '» s\'eliminarà de manera definitiva.';
    require ROOT_PATH . '/app/Views/partials/alert.php';
    ?>

    <?php $cardHeading = 'h2'; require ROOT_PATH . '/app/Views/partials/score-card.php'; ?>

    <form method="post" action="<?= e(url('/scores/' . (int) $score['id'] . '/delete')) ?>" class="form-actions">
        <?= csrfField() ?>
        <button type="submit" class="btn btn--danger">Sí, elimina la score</button>
        <a class="btn" href="<?= e(url('/scores/' . (int) $score['id'])) ?>">No, torna enrere</a>
    </form>
</div>
