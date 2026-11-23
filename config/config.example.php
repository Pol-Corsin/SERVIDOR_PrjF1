<?php
/**
 * Autor: Pol Corsin — PT04 osu! Scores
 * Plantilla de configuració. Copia aquest fitxer com a config/config.php i ajusta'l.
 * config/config.php NO es puja a Git (vegeu .gitignore).
 *
 * Valors per defecte de XAMPP: usuari "root" i contrasenya buida.
 */
return [
    'db' => [
        'host'     => 'localhost',
        'name'     => 'pt04_pol_corsin',   // mateix nom que a database/pt04_pol_corsin.sql
        'user'     => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],
    'session' => [
        'name'            => 'OSUSCORESID',
        'inactivity_time' => 45 * 60,      // 45 minuts d'inactivitat (mínim exigit: 40)
    ],
];
