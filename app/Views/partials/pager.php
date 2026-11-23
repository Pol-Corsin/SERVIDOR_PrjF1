<?php /* Autor: Pol Corsin — PT04 osu! Scores */ ?>
<?php
/**
 * Paginació accessible. Entrada: $page, $pages, $pageUrl (funció int => URL).
 * Controls: Primera, Anterior, números (finestra al voltant de l'actual), Següent i Última.
 * - Els controls no disponibles (p. ex. "Anterior" a la pàgina 1) es mostren desactivats i no són enfocables.
 * - La pàgina actual porta aria-current="page"; sota hi ha el text "Pàgina X de Y".
 * - En mòbil només es veuen Primera, Anterior, la pàgina actual, Següent i Última (vegeu components.css).
 * No es mostra si només hi ha una pàgina.
 */
if ($pages > 1):
    $shown = array_unique(array_filter([$page - 1, $page, $page + 1], fn($n) => $n >= 1 && $n <= $pages));
    sort($shown);
    $controls = [
        ['Primera', 1, $page > 1, 'first'],
        ['Anterior', $page - 1, $page > 1, 'prev'],
    ];
?>
<nav class="pager" aria-label="Paginació">
    <ul>
        <?php foreach ($controls as [$label, $target, $enabled, $rel]): ?>
            <li class="pager__ctl">
                <?php if ($enabled): ?><a href="<?= e($pageUrl($target)) ?>"<?= $rel === 'prev' ? ' rel="prev"' : '' ?>><?= e($label) ?></a>
                <?php else: ?><span class="pager__gap is-disabled" aria-disabled="true"><?= e($label) ?></span><?php endif; ?>
            </li>
        <?php endforeach; ?>

        <?php foreach ($shown as $n): ?>
            <li class="<?= $n === $page ? 'pager__current' : 'pager__num' ?>">
                <a href="<?= e($pageUrl($n)) ?>"<?= $n === $page ? ' aria-current="page"' : '' ?>><span class="visually-hidden">Pàgina </span><?= e($n) ?></a>
            </li>
        <?php endforeach; ?>

        <li class="pager__ctl">
            <?php if ($page < $pages): ?><a href="<?= e($pageUrl($page + 1)) ?>" rel="next">Següent</a>
            <?php else: ?><span class="pager__gap is-disabled" aria-disabled="true">Següent</span><?php endif; ?>
        </li>
        <li class="pager__ctl">
            <?php if ($page < $pages): ?><a href="<?= e($pageUrl($pages)) ?>">Última</a>
            <?php else: ?><span class="pager__gap is-disabled" aria-disabled="true">Última</span><?php endif; ?>
        </li>
    </ul>
    <p class="pager__info">Pàgina <?= e($page) ?> de <?= e($pages) ?>.</p>
</nav>
<?php endif; ?>
<?php unset($shown, $controls); ?>
