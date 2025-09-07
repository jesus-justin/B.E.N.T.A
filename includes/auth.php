<?php
/**
 * Authentication Functions for B.E.N.T.A
 * Business Expense and Net Transaction Analyzer
 *
 * Enhanced with security features:
 * - CSRF protection
 * - Rate limiting
 * - Account lockout
 * - Input sanitization
 * - Session security
 * - Security logging
 */

require_once 'config/db.php';

class Auth {
    private $db;
    private $conn;
    private $maxLoginAttempts = 5;
    private $lockoutTime = 900; // 15 minutes
    private $csrfTokenLength = 32;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();

        // Ensure session is started securely
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_secure' => isset($_SERVER['HTTPS']),
                'cookie_samesite' => 'Strict'
            ]);
        }

        // Regenerate session ID periodically for security
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } elseif (time() - $_SESSION['created'] > 1800) { // 30 minutes
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }

    // Hash password
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    // Verify password
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    // Register new user with enhanced validation
    public function register($username, $email, $password) {
        try {
            // Sanitize and validate inputs
            $username = $this->sanitizeInput($username);
            $email = $this->sanitizeInput($email);

            // Validate inputs
            if (!$this->validateUsername($username)) {
                return ['success' => false, 'message' => 'Username must be 3-50 characters, alphanumeric only'];
            }

            if (!$this->validateEmail($email)) {
                return ['success' => false, 'message' => 'Please enter a valid email address'];
            }

            if (!$this->validatePassword($password)) {
                return ['success' => false, 'message' => 'Password must be at least 8 characters with uppercase, lowercase, and number'];
            }

            // Check if user already exists
            $stmt = $this->conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);

            if ($stmt->rowCount() > 0) {
                $this->logSecurityEvent('registration_attempt_existing_user', $username);
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

            $this->logSecurityEvent('registration_success', $username);

            return ['success' => true, 'message' => 'Registration successful', 'user_id' => $userId];

        } catch(PDOException $e) {
            $this->logSecurityEvent('registration_error', $username, $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
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

    // Validate username format
    public function validateUsername($username) {
        // 3-50 characters, alphanumeric and underscores only
        return preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username);
    }

    // Generate CSRF token
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes($this->csrfTokenLength / 2));
        }
        return $_SESSION['csrf_token'];
    }

    // Validate CSRF token
    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    // Check if account is locked
    private function isAccountLocked($username) {
        try {
            $stmt = $this->conn->prepare("SELECT locked_until FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);

            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch();
                if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                    return true;
                }
            }
            return false;
        } catch(PDOException $e) {
            return false;
        }
    }

    // Lock account after failed attempts
    private function lockAccount($username) {
        try {
            $lockTime = date('Y-m-d H:i:s', time() + $this->lockoutTime);
            $stmt = $this->conn->prepare("UPDATE users SET locked_until = ? WHERE username = ? OR email = ?");
            $stmt->execute([$lockTime, $username, $username]);
        } catch(PDOException $e) {
            // Log error but don't expose to user
        }
    }

    // Log security events
    private function logSecurityEvent($event, $username, $details = '') {
        // In a production environment, this would write to a secure log file
        // For now, we'll use error_log for demonstration
        $logMessage = sprintf(
            "[%s] Security Event: %s | User: %s | Details: %s | IP: %s",
            date('Y-m-d H:i:s'),
            $event,
            $username,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );
        error_log($logMessage);
    }

    // Enhanced login with rate limiting and lockout
    public function loginEnhanced($username, $password, $csrfToken = null) {
        try {
            // Validate CSRF token if provided
            if ($csrfToken && !$this->validateCSRFToken($csrfToken)) {
                $this->logSecurityEvent('csrf_validation_failed', $username);
                return ['success' => false, 'message' => 'Security validation failed'];
            }

            // Sanitize input
            $username = $this->sanitizeInput($username);

            // Check if account is locked
            if ($this->isAccountLocked($username)) {
                $this->logSecurityEvent('login_attempt_locked_account', $username);
                return ['success' => false, 'message' => 'Account is temporarily locked due to too many failed attempts'];
            }

            // Get user
            $stmt = $this->conn->prepare("SELECT id, username, email, password, login_attempts FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);

            if ($stmt->rowCount() == 0) {
                $this->logSecurityEvent('login_attempt_invalid_username', $username);
                return ['success' => false, 'message' => 'Invalid username or password'];
            }

            $user = $stmt->fetch();

            // Check password
            if (!$this->verifyPassword($password, $user['password'])) {
                // Increment login attempts
                $attempts = ($user['login_attempts'] ?? 0) + 1;
                $stmt = $this->conn->prepare("UPDATE users SET login_attempts = ? WHERE id = ?");
                $stmt->execute([$attempts, $user['id']]);

                // Lock account if max attempts reached
                if ($attempts >= $this->maxLoginAttempts) {
                    $this->lockAccount($username);
                    $this->logSecurityEvent('account_locked', $username);
                    return ['success' => false, 'message' => 'Account locked due to too many failed attempts'];
                }

                $this->logSecurityEvent('login_failed_wrong_password', $username);
                return ['success' => false, 'message' => 'Invalid username or password'];
            }

            // Reset login attempts on successful login
            $stmt = $this->conn->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?");
            $stmt->execute([$user['id']]);

            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['login_time'] = time();

            $this->logSecurityEvent('login_success', $username);

            return ['success' => true, 'message' => 'Login successful', 'user' => $user];

        } catch(PDOException $e) {
            $this->logSecurityEvent('login_error', $username, $e->getMessage());
            return ['success' => false, 'message' => 'Login failed. Please try again.'];
        }
    }
}
?>
