<?php /* Autor: Pol Corsin — PT04 osu! Scores */ ?>
<div class="container">
    <section class="hero" aria-labelledby="titol-inici">
        <div>
            <h1 id="titol-inici">Les millors scores d'osu!, a un clic</h1>
            <p class="lead">Consulta les partides publicades pels jugadors i compara el score, la precisió i els PP. Amb un compte, pots editar les teves.</p>
            <div class="hero__actions">
                <a class="btn btn--primary" href="<?= e(url('/scores')) ?>">Explora les scores</a>
                <?php if (isLoggedIn()): ?>
                    <a class="btn" href="<?= e(url('/my-scores')) ?>">Les meves scores</a>
                <?php else: ?>
                    <a class="btn" href="<?= e(url('/register')) ?>">Crea un compte</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($featured): ?>
            <div>
                <p class="hero__caption">La score amb més PP</p>
                <?php $score = $featured; $cardFeatured = true; require ROOT_PATH . '/app/Views/partials/score-card.php'; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- Últimes scores: mateixa amplada centrada que el llistat (.page), fora del hero ample -->
<div class="page">
    <section class="section" aria-labelledby="ultimes">
        <div class="section-head">
            <h2 id="ultimes">Últimes scores</h2>
            <a href="<?= e(url('/scores')) ?>">Veure-les totes</a>
        </div>
        <?php if (empty($scores)): ?>
            <?php $heading = 'Encara no hi ha scores'; $message = 'Quan es publiquin puntuacions, apareixeran aquí.'; ?>
            <?php require ROOT_PATH . '/app/Views/partials/empty-state.php'; ?>
        <?php else: ?>
            <ul class="score-list">
                <?php foreach ($scores as $score): ?>
                    <li><?php $cardHeading = 'h3'; require ROOT_PATH . '/app/Views/partials/score-card.php'; ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
