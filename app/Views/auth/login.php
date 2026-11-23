<?php /* Autor: Pol Corsin — PT04 osu! Scores */ ?>
<div class="container auth">
    <div class="narrow">
        <h1>Inicia sessió</h1>
        <p class="lead">Accedeix al teu compte per editar les teves scores.</p>

        <?php if (!empty($error)): ?>
            <?php $type = 'error'; $heading = $error; $alertId = 'resum-errors'; require ROOT_PATH . '/app/Views/partials/alert.php'; ?>
        <?php endif; ?>

        <form class="panel" method="post" action="<?= e(url('/login')) ?>">
            <?= csrfField() ?>
            <?php
            $id = 'username'; $label = 'Nom d\'usuari'; $required = true; $autocomplete = 'username'; $value = $values['username'] ?? '';
            require ROOT_PATH . '/app/Views/partials/field.php';

            $id = 'password'; $label = 'Contrasenya'; $type = 'password'; $required = true; $autocomplete = 'current-password';
            require ROOT_PATH . '/app/Views/partials/field.php';
            ?>
            <button class="btn btn--primary btn--block" type="submit">Inicia sessió</button>
            <p class="form-footer">Encara no tens compte? <a href="<?= e(url('/register')) ?>">Registra't</a></p>
        </form>
    </div>
</div>
