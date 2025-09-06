<?php
/**
 * Database Configuration for B.E.N.T.A
 * Business Expense and Net Transaction Analyzer
 */

class Database {
    private $host = "localhost";
    private $db_name = "benta_db";
    private $username = "root";
    private $password = "";
    private $conn;

    // Get database connection
    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }

    // Close database connection
    public function closeConnection() {
        $this->conn = null;
    }

    // Test database connection
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            if ($conn) {
                echo "Database connection successful!";
                $this->closeConnection();
                return true;
            }
        } catch(Exception $e) {
            echo "Database connection failed: " . $e->getMessage();
            return false;
        }
        return false;
    }
}

// Create database instance
$db = new Database();
?>
