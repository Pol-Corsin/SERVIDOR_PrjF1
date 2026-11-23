<?php /* Autor: Pol Corsin — PT04 osu! Scores */ ?>
<div class="container auth">
    <div class="narrow">
        <h1>Crea el teu compte</h1>
        <p class="lead">Uneix-te per poder editar les teves pròpies scores.</p>

        <?php require ROOT_PATH . '/app/Views/partials/error-summary.php'; ?>

        <form class="panel" method="post" action="<?= e(url('/register')) ?>" novalidate>
            <?= csrfField() ?>
            <?php
            $id = 'username'; $label = 'Nom d\'usuari'; $required = true; $autocomplete = 'username'; $maxlength = 20;
            $hint = 'Entre 3 i 20 caràcters: lletres sense accents, números i guió baix.';
            $value = $values['username'] ?? ''; $error = $errors['username'] ?? null;
            require ROOT_PATH . '/app/Views/partials/field.php';

            $id = 'password'; $label = 'Contrasenya'; $type = 'password'; $required = true; $autocomplete = 'new-password';
            $hint = 'Almenys 8 caràcters, amb una majúscula, una minúscula i un número.';
            $error = $errors['password'] ?? null;
            require ROOT_PATH . '/app/Views/partials/field.php';

            $id = 'password_confirm'; $label = 'Repeteix la contrasenya'; $type = 'password'; $required = true; $autocomplete = 'new-password';
            $error = $errors['password_confirm'] ?? null;
            require ROOT_PATH . '/app/Views/partials/field.php';
            ?>
            <button class="btn btn--primary btn--block" type="submit">Crea el compte</button>
            <p class="form-footer">Ja tens compte? <a href="<?= e(url('/login')) ?>">Inicia sessió</a></p>
        </form>
    </div>
</div>
