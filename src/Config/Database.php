<?php

namespace App\Config;

use PDO;
use PDOException;
use Dotenv\Dotenv;

class Database
{
    private static $instance = null;
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    // El constructor es privado para evitar la creación de nuevas instancias
    private function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        $this->host = $_ENV['DB_HOST'];
        $this->port = $_ENV['DB_PORT'];
        $this->db_name = $_ENV['DB_NAME'];
        $this->username = $_ENV['DB_USER'];
        $this->password = $_ENV['DB_PASS'];
    }

    // Método estático para obtener la instancia única de la clase
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    // Método para obtener la conexión a la base de datos
    public function getConnection()
    {
        if ($this->conn === null) {
            try {
                $dsn = "sqlsrv:Server={$this->host},{$this->port};Database={$this->db_name};TrustServerCertificate=yes";
                $this->conn = new PDO($dsn, $this->username, $this->password);
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                echo "Connection error: " . $e->getMessage();
            }
        }

        return $this->conn;
    }

    // Evitar la clonación de la instancia
    private function __clone() {}

    // Evitar la deserialización de la instancia
    private function __wakeup() {}
}
