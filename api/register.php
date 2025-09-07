<?php
/**
 * Registration API for B.E.N.T.A
 * Business Expense and Net Transaction Analyzer
 *
 * Enhanced with:
 * - CSRF protection
 * - Input validation and sanitization
 * - Security logging
 * - Better error handling
 * - Rate limiting considerations
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../includes/auth.php';

$auth = new Auth();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    // If not JSON, try form data
    if (!$input) {
        $input = $_POST;
    }

    // Validate required fields
    $requiredFields = ['username', 'email', 'password', 'confirm_password'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Field '$field' is required"]);
            exit;
        }
    }

    $username = trim($input['username']);
    $email = trim($input['email']);
    $password = $input['password'];
    $confirmPassword = $input['confirm_password'];
    $csrfToken = $input['csrf_token'] ?? null;

    // Basic input validation
    if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'All fields are required and cannot be empty']);
        exit;
    }

    // Validate CSRF token if provided
    if ($csrfToken && !$auth->validateCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Security validation failed']);
        exit;
    }

    // Validate username format
    if (!$auth->validateUsername($username)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username must be 3-50 characters, alphanumeric only']);
        exit;
    }

    // Validate email format
    if (!$auth->validateEmail($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
        exit;
    }

    // Check password confirmation
    if ($password !== $confirmPassword) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit;
    }

    // Validate password strength
    if (!$auth->validatePassword($password)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Password must be at least 8 characters with uppercase, lowercase, and number'
        ]);
        exit;
    }

    // Additional password security checks
    if (strlen($password) > 128) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password is too long']);
        exit;
    }

    // Check for common weak passwords (basic check)
    $weakPasswords = ['password', '12345678', 'qwerty', 'abc123', 'password123'];
    if (in_array(strtolower($password), $weakPasswords)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please choose a stronger password']);
        exit;
    }

    // Check if password contains username
    if (stripos($password, $username) !== false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password cannot contain your username']);
        exit;
    }

    // Attempt registration
    $result = $auth->register($username, $email, $password);

    if ($result['success']) {
        // Generate new CSRF token for the session
        $newCsrfToken = $auth->generateCSRFToken();

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'user_id' => $result['user_id'],
            'csrf_token' => $newCsrfToken
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $result['message']]);
    }

} catch (Exception $e) {
    // Log the error securely
    error_log('Registration API Error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An internal server error occurred. Please try again later.'
    ]);
}
?>
