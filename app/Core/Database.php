<?php
/**
 * Autor: Pol Corsin — PT04 osu! Scores
 * Connexió a MySQL/MariaDB amb PDO. Una sola connexió per petició.
 */
class Database
{
    private static array $config = [];
    private static ?PDO $pdo = null;

    public static function configure(array $config): void
    {
        self::$config = $config;
    }

    /** Retorna la connexió PDO (la crea la primera vegada). */
    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $c = self::$config;
            $dsn = "mysql:host={$c['host']};dbname={$c['name']};charset={$c['charset']}";
            self::$pdo = new PDO($dsn, $c['user'], $c['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // errors com a excepcions
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,                  // consultes preparades reals
            ]);
        }
        return self::$pdo;
    }
}
