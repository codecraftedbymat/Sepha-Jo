<?php

/**
 * Connexion à la base de données.
 *
 * Le même fichier fonctionne en local (XAMPP) et en ligne (Railway) :
 * si les variables d'environnement de Railway sont présentes, elles sont
 * utilisées ; sinon on retombe sur les réglages XAMPP par défaut.
 */
if (!function_exists('env_var')) {
    /**
     * Lit une variable d'environnement, quelle que soit la façon dont le
     * serveur la met à disposition. Renvoie null si elle est absente.
     */
    function env_var(string $cle): ?string
    {
        foreach ([$_SERVER, $_ENV] as $source) {
            if (isset($source[$cle]) && $source[$cle] !== '') {
                return (string) $source[$cle];
            }
        }

        $v = getenv($cle);
        return ($v === false || $v === '') ? null : $v;
    }
}

class Database
{
    private $conn = null;

    public function connect()
    {
        if ($this->conn !== null) {
            return $this->conn;
        }

        // Railway injecte ces variables dès qu'un service MySQL est lié.
        // Selon la configuration du serveur, elles arrivent via getenv(),
        // $_SERVER ou $_ENV : on interroge les trois.
        $host   = env_var('MYSQLHOST')     ?: 'localhost';
        $port   = env_var('MYSQLPORT')     ?: '3306';
        $dbName = env_var('MYSQLDATABASE') ?: 'salon';
        $user   = env_var('MYSQLUSER')     ?: 'root';
        $pass   = env_var('MYSQLPASSWORD') ?: '';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";

        try {
            $this->conn = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // En ligne, ne jamais exposer les identifiants dans le message.
            if (env_var('MYSQLHOST')) {
                error_log('Connexion BDD echouee : ' . $e->getMessage());
                http_response_code(500);
                die('Service temporairement indisponible.');
            }
            die('Erreur de connexion a la base de donnees : ' . $e->getMessage());
        }

        return $this->conn;
    }
}
