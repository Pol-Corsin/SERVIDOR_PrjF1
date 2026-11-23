<?php /* Autor: Pol Corsin — PT04 osu! Scores */ ?>
<?php
// Dades fictícies només per mostrar una targeta d'exemple (no provenen de la BD).
$sample = [
    'id' => 1, 'beatmap_title' => 'Títol d\'exemple', 'artist' => 'Artista', 'mapper' => 'mapper_demo', 'difficulty_name' => 'Insane',
    'star_rating' => 5.31, 'bpm' => 180, 'cs' => 4, 'ar' => 9.2,
    'score' => 9876540, 'accuracy' => 97.42, 'pp' => 312.5, 'max_combo' => 1204,
    'count_300' => 1180, 'count_100' => 22, 'count_50' => 2, 'count_miss' => 1,
    'rank_result' => 'S', 'is_published' => 1, 'created_at' => '2026-11-01 18:00:00', 'username' => 'demo_exemple', 'player_rank' => 1,
];
$colors = [
    'Fons' => '#100d17', 'Targeta' => '#262136', 'Panell interior' => '#181421', 'Vora' => '#7a6fa6',
    'Text' => '#f4f1fb', 'Text secundari' => '#bab1d4', 'Rosa (acció)' => '#ff5ca8', 'Or (focus, rangs)' => '#ffd43b',
    'Enllaç' => '#8ad8ff', 'Èxit' => '#6be28c', 'Error' => '#ff8a8a',
];
?>
<div class="page">
    <div class="page-head">
        <h1>Guia d'estils</h1>
        <p class="lead">Els components de la interfície. Pàgina de revisió del disseny, no forma part del producte.</p>
    </div>

    <section class="section" aria-labelledby="g-colors">
        <h2 id="g-colors">Paleta</h2>
        <ul class="swatches">
            <?php foreach ($colors as $name => $hex): ?>
                <li><i style="background: <?= e($hex) ?>"></i><strong><?= e($name) ?></strong><br><code><?= e($hex) ?></code></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="section" aria-labelledby="g-botons">
        <h2 id="g-botons">Botons i xips</h2>
        <div class="row">
            <button type="button" class="btn btn--primary">Principal</button>
            <button type="button" class="btn">Secundari</button>
            <button type="button" class="btn btn--sm">Petit</button>
            <button type="button" class="btn" disabled>Desactivat</button>
        </div>
        <ul class="chips" style="margin-top:1rem">
            <li><a class="chip" href="#g-botons" aria-current="true">Seleccionat</a></li>
            <li><a class="chip" href="#g-botons">No seleccionat</a></li>
        </ul>
    </section>

    <section class="section" aria-labelledby="g-rangs">
        <h2 id="g-rangs">Rangs</h2>
        <div class="row">
            <?php foreach (Score::RANKS as $r): ?>
                <p class="rank rank-<?= e(strtolower($r)) ?>" style="margin:0"><span class="visually-hidden">Rang </span><?= e($r) ?></p>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="section" aria-labelledby="g-targeta">
        <h2 id="g-targeta">Targeta de score</h2>
        <?php $score = $sample; $cardShowActions = true; $cardHeading = 'h3'; require ROOT_PATH . '/app/Views/partials/score-card.php'; ?>
    </section>

    <section class="section" aria-labelledby="g-alertes">
        <h2 id="g-alertes">Missatges</h2>
        <?php
        $type = 'success'; $heading = 'Score desada correctament.';
        require ROOT_PATH . '/app/Views/partials/alert.php';

        $type = 'info'; $heading = 'La sessió ha caducat per inactivitat.'; $message = 'Torna a iniciar sessió per continuar.';
        require ROOT_PATH . '/app/Views/partials/alert.php';

        $type = 'error'; $heading = 'Hi ha 2 errors al formulari';
        $items = ['sg-user' => 'Aquest nom d\'usuari ja està en ús.', 'sg-pass' => 'La contrasenya ha de tenir almenys 8 caràcters.'];
        require ROOT_PATH . '/app/Views/partials/alert.php';
        ?>
    </section>

    <section class="section" aria-labelledby="g-form">
        <h2 id="g-form">Formulari amb errors</h2>
        <form class="panel" action="<?= e(url('/guia-estils')) ?>" onsubmit="return false">
            <?php
            $id = 'sg-user'; $label = 'Nom d\'usuari'; $required = true; $value = 'pol'; $autocomplete = 'username';
            $hint = 'Entre 3 i 20 caràcters.'; $error = 'Aquest nom d\'usuari ja està en ús. Tria\'n un altre.';
            require ROOT_PATH . '/app/Views/partials/field.php';

            $id = 'sg-pass'; $label = 'Contrasenya'; $type = 'password'; $required = true; $autocomplete = 'new-password';
            $error = 'La contrasenya ha de tenir almenys 8 caràcters.';
            require ROOT_PATH . '/app/Views/partials/field.php';
            ?>
            <button class="btn btn--primary" type="submit">Desa</button>
        </form>
    </section>

    <section class="section" aria-labelledby="g-buit">
        <h2 id="g-buit">Estat buit</h2>
        <?php
        $heading = 'No hi ha scores amb aquest filtre';
        $message = 'Prova un altre rang o torna a veure-les totes.';
        $actionHref = url('/scores'); $actionLabel = 'Mostra totes les scores';
        require ROOT_PATH . '/app/Views/partials/empty-state.php';
        ?>
    </section>
</div>
