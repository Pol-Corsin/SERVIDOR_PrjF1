<?php /* Autor: Pol Corsin — PT04 osu! Scores */ ?>
<?php
/**
 * Resum d'errors d'un formulari. Entrada: $errors (camp => missatge), $summaryTitle.
 * Cada error és un enllaç al camp; el JS mou el focus a aquest bloc en carregar la pàgina.
 */
if (!empty($errors)):
    $n = count($errors);
    $type = 'error';
    $heading = $n === 1 ? 'Hi ha 1 error al formulari' : "Hi ha $n errors al formulari";
    $message = null;
    $items = $errors;
    $alertId = 'resum-errors';
    require ROOT_PATH . '/app/Views/partials/alert.php';
endif;
