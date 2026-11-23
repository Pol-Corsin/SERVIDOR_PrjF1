<?php /* Autor: Pol Corsin — PT04 osu! Scores */ ?>
<section class="container error-page">
    <span class="code" aria-hidden="true">403</span>
    <h1>Accés denegat</h1>
    <p class="lead"><?= e($message ?? 'No tens permís per accedir a aquest contingut.') ?></p>
    <a class="btn btn--primary" href="<?= e(url('/')) ?>">Torna a l'inici</a>
</section>
