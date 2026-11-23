<?php
/**
 * Autor: Pol Corsin — PT04 osu! Scores
 * Model User: accés a la taula `users`. Només consultes preparades.
 */
class User
{
    public static function findByUsername(string $username): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id, username, password_hash FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function usernameExists(string $username): bool
    {
        $stmt = Database::connection()->prepare('SELECT 1 FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Crea un usuari. Rep el HASH de la contrasenya, mai el text pla.
     * Retorna false si el nom ja existeix (restricció UNIQUE: cobreix dues peticions simultànies).
     */
    public static function create(string $username, string $passwordHash): bool
    {
        try {
            $stmt = Database::connection()->prepare('INSERT INTO users (username, password_hash) VALUES (:u, :h)');
            $stmt->execute([':u' => $username, ':h' => $passwordHash]);
            return true;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') { // violació d'UNIQUE
                return false;
            }
            throw $e;
        }
    }

    public static function updatePasswordHash(int $id, string $hash): void
    {
        $stmt = Database::connection()->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
        $stmt->execute([':h' => $hash, ':id' => $id]);
    }
}
