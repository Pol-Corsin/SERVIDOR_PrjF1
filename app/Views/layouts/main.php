<?php
/**
 * Autor: Pol Corsin — PT04 osu! Scores
 * Plantilla base. Variables: $title (string) i $content (HTML ja renderitzat de la vista).
 * Els missatges flash (èxit/avís) es mostren aquí, un cop, a sobre del contingut.
 */
$flashes = takeFlashes();
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark light">
    <meta name="theme-color" content="#100d17" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#f5f2fa" media="(prefers-color-scheme: light)">
    <title><?= e($title ?? 'osu! Scores') ?> | osu! Scores</title>
    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="preload" href="<?= e(asset('fonts/nunito-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/pages.css')) ?>">
    <script>
    /* S'executa ABANS de pintar: aplica el tema desat i evita el parpelleig del tema equivocat */
    (function () {
        var d = document.documentElement;
        d.classList.add('js');
        try {
            var t = localStorage.getItem('theme');
            if (t === 'light' || t === 'dark') { d.setAttribute('data-theme', t); }
        } catch (e) { /* localStorage bloquejat: s'usa el tema del sistema */ }
    })();
    </script>
</head>
<body>
    <a class="skip-link" href="#contingut">Salta al contingut</a>
    <?php require ROOT_PATH . '/app/Views/partials/header.php'; ?>
    <main id="contingut" tabindex="-1">
        <?php if ($flashes): ?>
            <div class="page">
                <?php foreach ($flashes as $f): ?>
                    <?php
                    $type = $f['type'];
                    $heading = $f['message'];
                    require ROOT_PATH . '/app/Views/partials/alert.php';
                    ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?= $content ?>
    </main>
    <?php require ROOT_PATH . '/app/Views/partials/footer.php'; ?>
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
