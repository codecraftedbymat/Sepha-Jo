<?php

class Database
{
    // ---- Paramètres de connexion (valeurs par défaut de XAMPP) -------------
    private $host     = 'localhost';
    private $db_name  = 'sepha_jo';   // nom de votre base
    private $username = 'root';       // utilisateur MySQL par défaut sous XAMPP
    private $password = '';           // mot de passe vide par défaut sous XAMPP
    private $charset  = 'utf8mb4';

    private $conn = null;

    /**
     * Ouvre (ou réutilise) la connexion PDO.
     * @return PDO
     */
    public function connect()
    {
        if ($this->conn !== null) {
            return $this->conn;
        }

        $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";

        $options = [
            // Les erreurs SQL lèvent une exception au lieu d'échouer en silence
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Vraies requêtes préparées côté serveur (protection injection SQL)
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            // En développement : afficher l'erreur. En production, remplacez par
            // un simple message générique + error_log($e->getMessage());
            die('Erreur de connexion à la base de données : ' . $e->getMessage());
        }

        return $this->conn;
    }
}
