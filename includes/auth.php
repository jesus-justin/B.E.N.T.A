<?php
/**
 * Authentication Functions for B.E.N.T.A
 * Business Expense and Net Transaction Analyzer
 */

require_once 'config/db.php';

class Auth {
    private $db;
    private $conn;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }

    // Hash password
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    // Verify password
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    // Register new user
    public function register($username, $email, $password) {
        try {
            // Check if user already exists
            $stmt = $this->conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);

            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Username or email already exists'];
            }

            // Hash password
            $hashedPassword = $this->hashPassword($password);

            // Insert new user
            $stmt = $this->conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $hashedPassword]);

            $userId = $this->conn->lastInsertId();

            // Create default categories for the user
            $this->createDefaultCategories($userId);

            // Create default settings
            $this->createDefaultSettings($userId);

            return ['success' => true, 'message' => 'Registration successful', 'user_id' => $userId];

        } catch(PDOException $e) {
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }

    // Login user
    public function login($username, $password) {
        try {
            $stmt = $this->conn->prepare("SELECT id, username, email, password FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);

            if ($stmt->rowCount() == 0) {
                return ['success' => false, 'message' => 'Invalid username or password'];
            }

            $user = $stmt->fetch();

            if (!$this->verifyPassword($password, $user['password'])) {
                return ['success' => false, 'message' => 'Invalid username or password'];
            }

            // Start session
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];

            return ['success' => true, 'message' => 'Login successful', 'user' => $user];

        } catch(PDOException $e) {
            return ['success' => false, 'message' => 'Login failed: ' . $e->getMessage()];
        }
    }

    // Check if user is logged in
    public function isLoggedIn() {
        session_start();
        return isset($_SESSION['user_id']);
    }

    // Get current user
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }

        try {
            $stmt = $this->conn->prepare("SELECT id, username, email, created_at FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetch();
        } catch(PDOException $e) {
            return null;
        }
    }

    // Logout user
    public function logout() {
        session_start();
        session_destroy();
        return ['success' => true, 'message' => 'Logged out successfully'];
    }

    // Create default categories for new user
    private function createDefaultCategories($userId) {
        $defaultCategories = [
            ['Sales Revenue', 'income'],
            ['Service Income', 'income'],
            ['Other Income', 'income'],
            ['Office Supplies', 'expense'],
            ['Utilities', 'expense'],
            ['Marketing', 'expense'],
            ['Transportation', 'expense'],
            ['Other Expenses', 'expense']
        ];

        $stmt = $this->conn->prepare("INSERT INTO categories (name, type, user_id) VALUES (?, ?, ?)");

        foreach ($defaultCategories as $category) {
            $stmt->execute([$category[0], $category[1], $userId]);
        }
    }

    // Create default settings for new user
    private function createDefaultSettings($userId) {
        $stmt = $this->conn->prepare("INSERT INTO settings (user_id, business_name, currency) VALUES (?, 'My Business', 'PHP')");
        $stmt->execute([$userId]);
    }

    // Validate email format
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    // Validate password strength
    public function validatePassword($password) {
        // At least 8 characters, 1 uppercase, 1 lowercase, 1 number
        return strlen($password) >= 8 &&
               preg_match('/[A-Z]/', $password) &&
               preg_match('/[a-z]/', $password) &&
               preg_match('/[0-9]/', $password);
    }

    // Sanitize input
    public function sanitizeInput($input) {
        return htmlspecialchars(strip_tags(trim($input)));
    }
}
?>
