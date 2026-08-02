<?php

/**
 * Connexion à la base de données.
 *
 * Trois sources sont examinées, dans cet ordre :
 *
 *   1. DATABASE_URL ou MYSQL_URL — une chaîne de connexion complète, de la
 *      forme mysql://utilisateur:motdepasse@hote:port/base. C'est la voie
 *      la plus fiable en ligne : une seule variable à renseigner.
 *   2. Les variables séparées MYSQLHOST, MYSQLPORT, etc.
 *   3. Les réglages XAMPP par défaut, pour le développement local.
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

        [$host, $port, $dbName, $user, $pass] = $this->parametres();

        $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";

        try {
            $this->conn = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            if ($this->enLigne()) {
                // En ligne, ne jamais exposer les identifiants.
                error_log('Connexion BDD echouee : ' . $e->getMessage());
                http_response_code(500);
                die('Service temporairement indisponible.');
            }
            die('Erreur de connexion a la base de donnees : ' . $e->getMessage());
        }

        return $this->conn;
    }

    /**
     * Détermine les paramètres de connexion selon ce qui est disponible.
     *
     * @return array [host, port, base, utilisateur, mot de passe]
     */
    private function parametres(): array
    {
        // 1. Chaîne de connexion complète
        $url = env_var('DATABASE_URL') ?? env_var('MYSQL_URL');

        if ($url !== null) {
            $p = parse_url($url);
            if ($p !== false && isset($p['host'])) {
                return [
                    $p['host'],
                    $p['port'] ?? 3306,
                    isset($p['path']) ? ltrim($p['path'], '/') : 'railway',
                    isset($p['user']) ? urldecode($p['user']) : 'root',
                    isset($p['pass']) ? urldecode($p['pass']) : '',
                ];
            }
        }

        // 2. Variables séparées, puis 3. valeurs locales par défaut
        return [
            env_var('MYSQLHOST')     ?? 'localhost',
            env_var('MYSQLPORT')     ?? '3306',
            env_var('MYSQLDATABASE') ?? 'salon',
            env_var('MYSQLUSER')     ?? 'root',
            env_var('MYSQLPASSWORD') ?? '',
        ];
    }

    /** Vrai si l'on tourne sur un hébergement plutôt qu'en local. */
    private function enLigne(): bool
    {
        return env_var('DATABASE_URL') !== null
            || env_var('MYSQL_URL') !== null
            || env_var('MYSQLHOST') !== null
            || env_var('RAILWAY_ENVIRONMENT') !== null;
    }
}
