<?php /* Autor: Pol Corsin — PT04 osu! Scores */ ?>
<div class="page">
    <nav class="breadcrumb" aria-label="Camí de navegació">
        <ol>
            <li><a href="<?= e(url('/')) ?>">Inici</a></li>
            <li><a href="<?= e(url('/scores')) ?>">Scores</a></li>
            <li aria-current="page"><?= e($score['beatmap_title']) ?></li>
        </ol>
    </nav>

    <h1 class="visually-hidden">Score de <?= e($score['username']) ?> a <?= e($score['beatmap_title']) ?></h1>
    <?php $cardShowActions = $isOwner; require ROOT_PATH . '/app/Views/partials/score-card.php'; ?>

    <div class="detail-extra">
        <section class="panel" aria-labelledby="h-detalls">
            <h2 id="h-detalls">Detalls</h2>
            <dl class="info-list">
                <div><dt>Jugador</dt><dd><?= e($score['username']) ?></dd></div>
                <div><dt>Publicada</dt><dd><time datetime="<?= e(date('Y-m-d', strtotime($score['created_at']))) ?>"><?= e(formatDate($score['created_at'])) ?></time></dd></div>
                <div><dt>Última edició</dt><dd><time datetime="<?= e(date('Y-m-d', strtotime($score['updated_at']))) ?>"><?= e(formatDate($score['updated_at'])) ?></time></dd></div>
            </dl>
        </section>
        <section class="panel" aria-labelledby="h-cops">
            <h2 id="h-cops">Cops</h2>
            <dl class="info-list">
                <div><dt>Cops de 300</dt><dd><?= e(formatScore($score['count_300'])) ?></dd></div>
                <div><dt>Cops de 100</dt><dd><?= e(formatScore($score['count_100'])) ?></dd></div>
                <div><dt>Cops de 50</dt><dd><?= e(formatScore($score['count_50'])) ?></dd></div>
                <div><dt>Fallades</dt><dd><?= e(formatScore($score['count_miss'])) ?></dd></div>
            </dl>
        </section>
    </div>

    <p class="section"><a class="btn" href="<?= e(url('/scores')) ?>">Torna al llistat</a></p>
</div>
