<?php /* Autor: Pol Corsin — PT04 osu! Scores */ ?>
<div class="page page--form">
    <nav class="breadcrumb" aria-label="Camí de navegació">
        <ol>
            <li><a href="<?= e(url('/my-scores')) ?>">Les meves scores</a></li>
            <li aria-current="page">Edita</li>
        </ol>
    </nav>
    <h1>Edita la score</h1>
    <p class="lead"><?= e($score['beatmap_title']) ?></p>

    <?php
    $formAction = url('/scores/' . (int) $score['id'] . '/edit');
    $submitLabel = 'Desa els canvis';
    $cancelHref = url('/scores/' . (int) $score['id']);
    require ROOT_PATH . '/app/Views/partials/score-form.php';
    ?>

    <p class="section"><a class="btn btn--danger" href="<?= e(url('/scores/' . (int) $score['id'] . '/delete')) ?>">Elimina aquesta score</a></p>
</div>
