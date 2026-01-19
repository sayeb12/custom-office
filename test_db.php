<?php
// test_db.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing database connection...<br>";

class Database {
    private $host = 'localhost';
    private $db_name = 'dialdyna_custom_office';
    private $username = 'dialdyna_custom_office';
    private $password = 'dialdyna_custom_office';
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "Database connected successfully<br>";
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage() . "<br>";
        }
        return $this->conn;
    }
}

$database = new Database();
$db = $database->getConnection();

if ($db) {
    echo "Database connection is active.";
} else {
    echo "Database connection failed.";
}
?>