<?php /* Autor: Pol Corsin — PT04 osu! Scores */ ?>
<?php
// URL del llistat conservant ordre i filtre (valors ja validats al controlador). Canviar-los torna a la pàgina 1.
$listUrl = function (string $newSort, ?string $newRank, int $newPage = 1): string {
    $query = [];
    if ($newSort !== 'recent') { $query['sort'] = $newSort; }
    if ($newRank !== null) { $query['rank'] = $newRank; }
    if ($newPage > 1) { $query['page'] = $newPage; }
    return url('/scores') . ($query ? '?' . http_build_query($query) : '');
};
$pageUrl = fn(int $n): string => $listUrl($sort, $rank, $n);
?>
<div class="page">
    <div class="page-head">
        <h1>Totes les scores</h1>
        <p class="lead">Partides publicades pels jugadors.</p>
    </div>

    <div class="toolbar">
        <div>
            <p id="lbl-ordre">Ordena per</p>
            <ul class="chips" aria-labelledby="lbl-ordre">
                <?php foreach (Score::SORTS as $key => $opt): ?>
                    <li><a class="chip" href="<?= e($listUrl($key, $rank)) ?>"<?= $sort === $key ? ' aria-current="true"' : '' ?>><?= e($opt['label']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div>
            <p id="lbl-rang">Filtra per rang</p>
            <ul class="chips" aria-labelledby="lbl-rang">
                <li><a class="chip" href="<?= e($listUrl($sort, null)) ?>"<?= $rank === null ? ' aria-current="true"' : '' ?>>Tots</a></li>
                <?php foreach (Score::RANKS as $r): ?>
                    <li><a class="chip" href="<?= e($listUrl($sort, $r)) ?>"<?= $rank === $r ? ' aria-current="true"' : '' ?>><?= e($r) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <p class="muted" role="status">
        <?= e($total) ?> <?= $total === 1 ? 'score' : 'scores' ?><?= $rank !== null ? ' amb rang ' . e($rank) : '' ?>.
    </p>

    <?php if (empty($scores)): ?>
        <?php
        $heading = 'No hi ha scores amb aquest filtre';
        $message = 'Prova un altre rang o torna a veure-les totes.';
        $actionHref = url('/scores');
        $actionLabel = 'Mostra totes les scores';
        require ROOT_PATH . '/app/Views/partials/empty-state.php';
        ?>
    <?php else: ?>
        <ul class="score-list">
            <?php foreach ($scores as $score): ?>
                <li><?php require ROOT_PATH . '/app/Views/partials/score-card.php'; ?></li>
            <?php endforeach; ?>
        </ul>
        <?php require ROOT_PATH . '/app/Views/partials/pager.php'; ?>
    <?php endif; ?>
</div>
