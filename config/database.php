<?php
/**
 * Configuration de la base de données IDEM
 * Ce fichier garde la même structure que l'ancien pour éviter tout impact sur le reste du projet.
 * Tu peux changer DB_HOST, DB_NAME, DB_USER, DB_PASS ici uniquement.
 */

// ==========================
//  CONFIGURATION
// ==========================
define('DB_HOST', 'localhost'); // Hôte MySQL
define('DB_NAME', 'idem_db');   // Nom de la base
define('DB_USER', 'root');      // Utilisateur MySQL
define('DB_PASS', '');          // Mot de passe MySQL
define('DB_CHARSET', 'utf8mb4');// Encodage

// Options PDO (inchangées pour compatibilité)
define('PDO_OPTIONS', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,          // Gestion des erreurs
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     // Mode de récupération par défaut
    PDO::ATTR_EMULATE_PREPARES => false,                  // Désactiver l'émulation des requêtes préparées
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE " . DB_CHARSET . "_unicode_ci"
]);

/**
 * Initialise la connexion PDO
 * @return PDO
 */
function initDatabase() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, PDO_OPTIONS);
        } catch (PDOException $e) {
            if (defined('DEVELOPMENT') && DEVELOPMENT) {
                die("Erreur de connexion à la base de données : " . $e->getMessage());
            } else {
                error_log("Database connection error: " . $e->getMessage());
                die("Erreur de connexion à la base de données. Veuillez réessayer plus tard.");
            }
        }
    }
    return $pdo;
}

/**
 * Exécute une requête préparée
 */
function executeQuery($query, $params = []) {
    $pdo = initDatabase();
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $paramKey = is_numeric($key) ? $key + 1 : $key;
        $stmt->bindValue($paramKey, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt;
}

/**
 * Récupère une seule ligne
 */
function fetchOne($query, $params = []) {
    $stmt = executeQuery($query, $params);
    return $stmt->fetch();
}

/**
 * Récupère toutes les lignes
 */
function fetchAll($query, $params = []) {
    $stmt = executeQuery($query, $params);
    return $stmt->fetchAll();
}

/**
 * Insère une ligne dans une table
 */
function insert($table, $data) {
    $pdo = initDatabase();
    $columns = implode(', ', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));
    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    $stmt = $pdo->prepare($sql);
    foreach ($data as $key => $value) {
        $stmt->bindValue(":$key", $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return $pdo->lastInsertId();
}

/**
 * Met à jour des lignes dans une table
 */
function update($table, $data, $where, $params = []) {
    $pdo = initDatabase();
    $set = implode(', ', array_map(fn($key) => "$key = :$key", array_keys($data)));
    $sql = "UPDATE $table SET $set WHERE $where";
    $stmt = $pdo->prepare($sql);
    foreach ($data as $key => $value) {
        $stmt->bindValue(":$key", $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt;
}

/**
 * Supprime des lignes dans une table
 */
function delete($table, $where, $params = []) {
    $pdo = initDatabase();
    $sql = "DELETE FROM $table WHERE $where";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt;
}

/**
 * Dernier ID inséré
 */
function getLastInsertId() {
    $pdo = initDatabase();
    return $pdo->lastInsertId();
}

/**
 * Transaction : début
 */
function beginTransaction() {
    $pdo = initDatabase();
    return $pdo->beginTransaction();
}

/**
 * Transaction : valider
 */
function commit() {
    $pdo = initDatabase();
    return $pdo->commit();
}

/**
 * Transaction : annuler
 */
function rollback() {
    $pdo = initDatabase();
    return $pdo->rollback();
}
?>