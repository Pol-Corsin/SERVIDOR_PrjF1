<?php /* Autor: Pol Corsin — PT04 osu! Scores */ ?>
<?php
/**
 * Paginació accessible. Entrada: $page, $pages, $pageUrl (funció int => URL).
 * No es mostra si només hi ha una pàgina. La pàgina actual porta aria-current="page".
 */
if ($pages > 1):
    // Finestra de números al voltant de la pàgina actual (sempre primera i última)
    $shown = array_unique(array_filter([1, $page - 1, $page, $page + 1, $pages], fn($n) => $n >= 1 && $n <= $pages));
    sort($shown);
?>
<nav class="pager" aria-label="Paginació">
    <ul>
        <li>
            <?php if ($page > 1): ?><a href="<?= e($pageUrl($page - 1)) ?>" rel="prev">Anterior</a>
            <?php else: ?><span class="pager__gap is-disabled" aria-disabled="true">Anterior</span><?php endif; ?>
        </li>
        <?php $prev = 0; foreach ($shown as $n): ?>
            <?php if ($n - $prev > 1): ?><li><span class="pager__gap" aria-hidden="true">…</span></li><?php endif; ?>
            <li><a href="<?= e($pageUrl($n)) ?>"<?= $n === $page ? ' aria-current="page"' : '' ?>><span class="visually-hidden">Pàgina </span><?= e($n) ?></a></li>
            <?php $prev = $n; ?>
        <?php endforeach; ?>
        <li>
            <?php if ($page < $pages): ?><a href="<?= e($pageUrl($page + 1)) ?>" rel="next">Següent</a>
            <?php else: ?><span class="pager__gap is-disabled" aria-disabled="true">Següent</span><?php endif; ?>
        </li>
    </ul>
    <p class="pager__info">Pàgina <?= e($page) ?> de <?= e($pages) ?>.</p>
</nav>
<?php endif; ?>
