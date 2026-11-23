<?php /* Autor: Pol Corsin — PT04 osu! Scores */ ?>
<?php
/**
 * Targeta d'una score: UN sol component compacte (sense caixes interiors).
 *
 *   Fila principal : [ títol / artista / mapper / dificultat + CS AR BPM ]      [ rang + score + precisió ]
 *   Fila de dades  : [ cops 300/100/50/miss + PP + combo ]                      [ jugador: avatar, nom, #rang, PP, data ]
 *   Fila d'accions : [ Official score ]                                         [ Edita  Elimina ]   (només si n'hi ha)
 *
 * Entrada: $score (fila de la BD). Opcionals: $cardFeatured (bool), $cardShowActions (bool: només per al propietari), $cardHeading.
 * Tota dada es mostra escapada amb e(). El rang es mostra amb LLETRA (no només color).
 * El títol és un enllaç; el seu ::after cobreix la targeta perquè tota sigui clicable.
 *
 * - Dificultat: la pastilla d'estrelles porta el color de l'escala 0-10 (helper stars.php).
 * - Imatge de fons: només si la ruta guardada té el format vàlid (isSafeScoreImagePath); la targeta
 *   passa a usar els colors del tema fosc perquè el text sigui sempre llegible sobre qualsevol imatge.
 * - Botó "Official score": només si la URL guardada és una URL oficial vàlida d'osu!.
 */
$cardRank = $score['rank_result'];
$cardTone = avatarTone($score['username']);
$cardFeatured = $cardFeatured ?? false;
$cardShowActions = $cardShowActions ?? false;
$cardHeading = $cardHeading ?? 'h2';   // nivell d'encapçalament segons la pàgina (sense saltar nivells)
$cardImage = scoreImageUrl($score['image_path'] ?? null);
$cardOfficial = isOfficialScoreUrl($score['official_url'] ?? null) ? $score['official_url'] : null;
$cardDraft = (int) $score['is_published'] === 0;
// La URL va dins d'un url('...') de CSS: s'escapen els caràcters que podrien trencar-lo
$cardImageCss = $cardImage === null ? '' : str_replace(["'", '"', '(', ')', '\\', ' '], ['%27', '%22', '%28', '%29', '%5C', '%20'], $cardImage);
?>
<article class="score<?= $cardFeatured ? ' score--featured' : '' ?><?= $cardImage !== null ? ' score--has-image' : '' ?>"<?= $cardImage !== null ? ' style="--card-image: url(\'' . e($cardImageCss) . '\')"' : '' ?>>
    <div class="score__main">
        <div class="score__id">
            <<?= $cardHeading ?> class="score__title"><a href="<?= e(url('/scores/' . (int) $score['id'])) ?>"><?= e($score['beatmap_title']) ?></a></<?= $cardHeading ?>>
            <p class="score__credits">
                <span>de <b><?= e($score['artist']) ?></b></span>
                <span>Mapper <b><?= e($score['mapper'] !== '' ? $score['mapper'] : 'desconegut') ?></b></span>
            </p>
            <div class="score__diff">
                <p class="stars" style="<?= e(starStyleAttr($score['star_rating'])) ?>"><span aria-hidden="true">★</span><?= e(formatStars($score['star_rating'])) ?><span class="visually-hidden"> estrelles</span></p>
                <p class="score__diff-name"><?= e($score['difficulty_name']) ?></p>
                <dl class="diff-stats">
                    <div><dt>CS</dt><dd><?= e(formatTrim($score['cs'])) ?></dd></div>
                    <div><dt>AR</dt><dd><?= e(formatTrim($score['ar'])) ?></dd></div>
                    <div><dt>BPM</dt><dd><?= e($score['bpm']) ?></dd></div>
                </dl>
            </div>
        </div>

        <div class="score__result">
            <p class="rank rank-<?= e(strtolower($cardRank)) ?>"><span class="visually-hidden">Rang </span><?= e($cardRank) ?></p>
            <div class="score__numbers">
                <p class="points"><span class="visually-hidden">Score </span><?= e(formatScore($score['score'])) ?></p>
                <p class="acc"><span class="visually-hidden">Precisió </span><?= e(formatAccuracy($score['accuracy'])) ?></p>
            </div>
        </div>
    </div>

    <div class="score__foot">
        <ul class="hits">
            <li class="hit hit--300"><?= e(formatScore($score['count_300'])) ?> <span class="hit__l"><span aria-hidden="true">300</span><span class="visually-hidden">cops de 300</span></span></li>
            <li class="hit hit--100"><?= e(formatScore($score['count_100'])) ?> <span class="hit__l"><span aria-hidden="true">100</span><span class="visually-hidden">cops de 100</span></span></li>
            <li class="hit hit--50"><?= e(formatScore($score['count_50'])) ?> <span class="hit__l"><span aria-hidden="true">50</span><span class="visually-hidden">cops de 50</span></span></li>
            <li class="hit hit--miss"><?= e(formatScore($score['count_miss'])) ?> <span class="hit__l"><span aria-hidden="true">✕</span><span class="visually-hidden">fallades</span></span></li>
            <li class="hit hit--pp"><?= e(formatPp($score['pp'])) ?> <span class="hit__l">pp</span></li>
            <li class="hit hit--plain"><?= e(formatScore($score['max_combo'])) ?>x <span class="hit__l">combo</span></li>
        </ul>

        <div class="score__player">
            <p class="avatar tone-<?= e($cardTone) ?>" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($score['username'], 0, 1))) ?></p>
            <div class="score__player-text">
                <p class="score__player-name"><span class="visually-hidden">Jugador: </span><?= e($score['username']) ?></p>
                <p class="score__player-meta">
                    <span class="player-rank"><span class="visually-hidden">Classificació </span>#<?= e($score['player_rank']) ?></span>
                    <span class="player-pp"><span class="visually-hidden">PP totals </span><?= e(formatPp($score['player_pp'])) ?> pp</span>
                    <span class="player-date"><span class="visually-hidden">Data </span><time datetime="<?= e(date('Y-m-d', strtotime($score['created_at']))) ?>"><?= e(formatDate($score['created_at'])) ?></time></span>
                </p>
            </div>
        </div>
    </div>

    <?php if ($cardShowActions || $cardDraft || $cardOfficial !== null): ?>
        <div class="score__actions">
            <div class="score__actions-group">
                <?php if ($cardOfficial !== null): ?>
                    <a class="btn btn--sm btn--official" href="<?= e($cardOfficial) ?>" target="_blank" rel="noopener noreferrer" lang="en">Official score<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14 3h7v7h-2V6.4l-8.3 8.3-1.4-1.4L17.6 5H14V3zM5 5h6v2H7v10h10v-4h2v6H5V5z" fill="currentColor"/></svg><span class="visually-hidden"> (s'obre en una pestanya nova)</span></a>
                <?php endif; ?>
                <?php if ($cardDraft): ?><p class="badge-draft">No publicada: només la veus tu</p><?php endif; ?>
            </div>
            <?php if ($cardShowActions): ?>
                <div class="score__actions-group">
                    <a class="btn btn--sm" href="<?= e(url('/scores/' . (int) $score['id'] . '/edit')) ?>">Edita <span class="visually-hidden">la score <?= e($score['beatmap_title']) ?></span></a>
                    <a class="btn btn--sm btn--danger" href="<?= e(url('/scores/' . (int) $score['id'] . '/delete')) ?>">Elimina <span class="visually-hidden">la score <?= e($score['beatmap_title']) ?></span></a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</article>
<?php unset($cardRank, $cardTone, $cardFeatured, $cardShowActions, $cardHeading, $cardImage, $cardImageCss, $cardOfficial, $cardDraft); ?>
