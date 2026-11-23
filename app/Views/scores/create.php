<?php /* Autor: Pol Corsin — PT04 osu! Scores */ ?>
<div class="page page--form">
    <nav class="breadcrumb" aria-label="Camí de navegació">
        <ol>
            <li><a href="<?= e(url('/my-scores')) ?>">Les meves scores</a></li>
            <li aria-current="page">Afegeix</li>
        </ol>
    </nav>
    <h1>Afegeix una score</h1>
    <p class="lead">La score serà teva: només tu la podràs editar o eliminar.</p>

    <?php
    $formAction = url('/scores/new');
    $submitLabel = 'Afegeix la score';
    $cancelHref = url('/my-scores');
    require ROOT_PATH . '/app/Views/partials/score-form.php';
    ?>
</div>
