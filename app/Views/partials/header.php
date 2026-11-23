<?php /* Autor: Pol Corsin — PT04 osu! Scores */ ?>
<?php
// Capçalera comuna. Marca la pàgina actual amb aria-current="page".
$current = requestPath();
$isActive = fn(string $path): bool => $path === '/'
    ? $current === '/'
    : ($current === $path || str_starts_with($current, $path . '/'));
$links = ['/' => 'Inici', '/scores' => 'Scores'];
if (isLoggedIn()) {
    $links['/my-scores'] = 'Les meves scores';
}
?>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(url('/')) ?>">
            <svg viewBox="0 0 64 64" aria-hidden="true" focusable="false">
                <circle cx="32" cy="32" r="28" fill="none" stroke="#ffd43b" stroke-width="5"/>
                <circle cx="32" cy="32" r="16" fill="#ff5ca8"/>
            </svg>
            osu!<span> scores</span>
        </a>

        <button type="button" class="btn btn--sm nav-toggle" aria-expanded="false" aria-controls="menu-principal">
            <span aria-hidden="true">☰</span> <span class="nav-toggle__label">Obre el menú</span>
        </button>

        <div class="nav" id="menu-principal">
            <nav aria-label="Navegació principal">
                <ul class="nav-list">
                    <?php foreach ($links as $path => $label): ?>
                        <li><a href="<?= e(url($path)) ?>"<?= $isActive($path) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <div class="nav-actions">
                <!-- Conmutador de tema: botó amb aria-pressed (premut = mode fosc). Es mostra només si hi ha JS. -->
                <button type="button" class="btn btn--sm theme-toggle" data-theme-toggle aria-pressed="false" hidden>
                    <svg class="icon-moon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5a8.5 8.5 0 1 0 11 11z" fill="currentColor"/></svg>
                    <span>Mode fosc</span>
                </button>
                <?php if (isLoggedIn()): ?>
                    <span class="who">
                        <span class="avatar avatar--sm tone-<?= e(avatarTone(currentUsername())) ?>" aria-hidden="true"><?= e(mb_strtoupper(mb_substr(currentUsername(), 0, 1))) ?></span>
                        <span><span class="visually-hidden">Sessió iniciada com a </span><?= e(currentUsername()) ?></span>
                    </span>
                    <form method="post" action="<?= e(url('/logout')) ?>">
                        <?= csrfField() ?>
                        <button type="submit" class="btn btn--sm">Tanca la sessió</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn--sm" href="<?= e(url('/login')) ?>"<?= $isActive('/login') ? ' aria-current="page"' : '' ?>>Inicia sessió</a>
                    <a class="btn btn--sm btn--primary" href="<?= e(url('/register')) ?>"<?= $isActive('/register') ? ' aria-current="page"' : '' ?>>Registra't</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>
