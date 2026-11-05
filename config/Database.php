<?php
class DB
{
    private string $host = "localhost";
    private string $dbname = "prf1_pol_corsin";
    private string $username = "root";
    private string $password = "";

    public function connect(): PDO
    {
        try {
            $connection = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                $this->username,
                $this->password
            );

            $connection->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );

            return $connection;

        } catch (PDOException $e) {
            die("Database connection error.");
        }
    }
}

?>