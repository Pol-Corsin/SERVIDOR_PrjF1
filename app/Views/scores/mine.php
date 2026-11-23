<?php /* Autor: Pol Corsin — PT04 osu! Scores */ ?>
<div class="page">
    <div class="page-head">
        <h1>Les meves scores</h1>
        <p class="lead">Afegeix, edita o elimina les teves partides. Les scores dels altres jugadors no es poden modificar.</p>
    </div>

    <div class="actions-bar">
        <p class="muted" role="status" style="margin:0"><?= e(count($scores)) ?> <?= count($scores) === 1 ? 'score' : 'scores' ?>.</p>
        <a class="btn btn--primary" href="<?= e(url('/scores/new')) ?>">Afegeix una score</a>
    </div>

    <?php if (empty($scores)): ?>
        <?php
        $heading = 'Encara no tens cap score';
        $message = 'Afegeix la teva primera partida i apareixerà al llistat públic.';
        $actionHref = url('/scores/new');
        $actionLabel = 'Afegeix una score';
        require ROOT_PATH . '/app/Views/partials/empty-state.php';
        ?>
    <?php else: ?>
        <ul class="score-list">
            <?php foreach ($scores as $score): ?>
                <li><?php $cardShowActions = true; require ROOT_PATH . '/app/Views/partials/score-card.php'; ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
