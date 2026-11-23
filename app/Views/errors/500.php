<?php /* Autor: Pol Corsin — PT04 osu! Scores */ ?>
<section class="container error-page">
    <span class="code" aria-hidden="true">500</span>
    <h1>Hi ha hagut un error</h1>
    <p class="lead">No s'ha pogut completar la petició. Torna-ho a provar més tard.</p>
    <p class="muted">Si ets tu qui desenvolupa el projecte, comprova que:</p>
    <ul>
        <li>MySQL està engegat al Panell de control de XAMPP.</li>
        <li>Has importat <code>database/pt04_pol_corsin.sql</code> des de phpMyAdmin.</li>
        <li>Les dades de <code>config/config.php</code> són correctes.</li>
    </ul>
    <p class="muted">El detall tècnic és a <code>storage/logs/error.log</code>.</p>
</section>
