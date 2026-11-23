<?php /* Autor: Pol Corsin — PT04 osu! Scores */ ?>
<?php
/**
 * Targeta d'una score (4 panells: beatmap, dificultat, resultat, jugador).
 * Entrada: $score (fila de la BD). Opcionals: $cardFeatured (bool), $cardShowActions (bool).
 * Tota dada es mostra escapada amb e(). El rang es mostra amb LLETRA (no només color).
 * El títol és un enllaç; el seu ::after cobreix la targeta perquè tota sigui clicable.
 */
$cardRank = $score['rank_result'];
$cardTone = avatarTone($score['username']);
$cardFeatured = $cardFeatured ?? false;
$cardShowActions = $cardShowActions ?? false;
$cardHeading = $cardHeading ?? 'h2';   // nivell d'encapçalament segons la pàgina (sense saltar nivells)
?>
<article class="score<?= $cardFeatured ? ' score--featured' : '' ?>">
    <div class="score__panel score__map">
        <<?= $cardHeading ?> class="score__title"><a href="<?= e(url('/scores/' . (int) $score['id'])) ?>"><?= e($score['beatmap_title']) ?></a></<?= $cardHeading ?>>
        <p class="score__by">de <b><?= e($score['artist']) ?></b></p>
        <p class="score__mapper">Mapper: <b><?= e($score['mapper'] !== '' ? $score['mapper'] : 'desconegut') ?></b></p>
    </div>

    <div class="score__panel score__diff">
        <div class="score__diff-top">
            <p class="stars"><span aria-hidden="true">★</span><?= e(formatStars($score['star_rating'])) ?><span class="visually-hidden"> estrelles</span></p>
            <p class="score__diff-name"><?= e($score['difficulty_name']) ?></p>
        </div>
        <dl class="diff-stats">
            <div><dt>CS</dt><dd><?= e(formatTrim($score['cs'])) ?></dd></div>
            <div><dt>AR</dt><dd><?= e(formatTrim($score['ar'])) ?></dd></div>
            <div><dt>BPM</dt><dd><?= e($score['bpm']) ?></dd></div>
        </dl>
    </div>

    <div class="score__panel score__result">
        <div class="score__result-top">
            <p class="rank rank-<?= e(strtolower($cardRank)) ?>"><span class="visually-hidden">Rang </span><?= e($cardRank) ?></p>
            <div class="score__numbers">
                <p class="acc"><span class="visually-hidden">Precisió </span><?= e(formatAccuracy($score['accuracy'])) ?></p>
                <p class="points"><span class="visually-hidden">Score </span><?= e(formatScore($score['score'])) ?></p>
            </div>
        </div>
        <ul class="hits">
            <li class="hit hit--300"><?= e(formatScore($score['count_300'])) ?> <span class="hit__l"><span aria-hidden="true">300</span><span class="visually-hidden">cops de 300</span></span></li>
            <li class="hit hit--100"><?= e(formatScore($score['count_100'])) ?> <span class="hit__l"><span aria-hidden="true">100</span><span class="visually-hidden">cops de 100</span></span></li>
            <li class="hit hit--50"><?= e(formatScore($score['count_50'])) ?> <span class="hit__l"><span aria-hidden="true">50</span><span class="visually-hidden">cops de 50</span></span></li>
            <li class="hit hit--miss"><?= e(formatScore($score['count_miss'])) ?> <span class="hit__l"><span aria-hidden="true">✕</span><span class="visually-hidden">fallades</span></span></li>
            <li class="hit hit--pp"><?= e(formatPp($score['pp'])) ?> <span class="hit__l">pp</span></li>
            <li class="hit hit--plain"><?= e(formatScore($score['max_combo'])) ?>x <span class="hit__l">combo</span></li>
        </ul>
    </div>

    <div class="score__panel score__player">
        <p class="avatar tone-<?= e($cardTone) ?>" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($score['username'], 0, 1))) ?></p>
        <div class="score__player-text">
            <p class="score__player-name"><span class="visually-hidden">Jugador: </span><?= e($score['username']) ?></p>
            <p class="score__player-meta">#<?= e($score['player_rank']) ?> per PP total</p>
            <p class="score__player-meta">Publicada el <time datetime="<?= e(date('Y-m-d', strtotime($score['created_at']))) ?>"><?= e(formatDate($score['created_at'])) ?></time></p>
        </div>
    </div>

    <?php if ($cardShowActions || (int) $score['is_published'] === 0): ?>
        <div class="score__actions">
            <?php if ((int) $score['is_published'] === 0): ?><p class="badge-draft">No publicada: només la veus tu</p><?php else: ?><span></span><?php endif; ?>
            <?php if ($cardShowActions): ?>
                <div class="score__actions-group">
                    <a class="btn btn--sm" href="<?= e(url('/scores/' . (int) $score['id'] . '/edit')) ?>">Edita <span class="visually-hidden">la score <?= e($score['beatmap_title']) ?></span></a>
                    <a class="btn btn--sm btn--danger" href="<?= e(url('/scores/' . (int) $score['id'] . '/delete')) ?>">Elimina <span class="visually-hidden">la score <?= e($score['beatmap_title']) ?></span></a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</article>
<?php unset($cardRank, $cardTone, $cardFeatured, $cardShowActions, $cardHeading); ?>
