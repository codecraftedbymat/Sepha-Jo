<?php

/**
 * Connexion à la base de données.
 *
 * Le même fichier fonctionne en local (XAMPP) et en ligne (Railway) :
 * si les variables d'environnement de Railway sont présentes, elles sont
 * utilisées ; sinon on retombe sur les réglages XAMPP par défaut.
 */
class Database
{
    private $conn = null;

    public function connect()
    {
        if ($this->conn !== null) {
            return $this->conn;
        }

        // Railway injecte ces variables dès qu'un service MySQL est lié.
        $host   = getenv('MYSQLHOST')     ?: 'localhost';
        $port   = getenv('MYSQLPORT')     ?: '3306';
        $dbName = getenv('MYSQLDATABASE') ?: 'salon';
        $user   = getenv('MYSQLUSER')     ?: 'root';
        $pass   = getenv('MYSQLPASSWORD') ?: '';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";

        try {
            $this->conn = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // En ligne, ne jamais exposer les identifiants dans le message.
            if (getenv('MYSQLHOST')) {
                error_log('Connexion BDD echouee : ' . $e->getMessage());
                http_response_code(500);
                die('Service temporairement indisponible.');
            }
            die('Erreur de connexion a la base de donnees : ' . $e->getMessage());
        }

        return $this->conn;
    }
}
