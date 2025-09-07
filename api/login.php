<?php
/**
 * Login API for B.E.N.T.A
 * Business Expense and Net Transaction Analyzer
 *
 * Enhanced with:
 * - Rate limiting and account lockout
 * - CSRF protection
 * - Input validation and sanitization
 * - Security logging
 * - Better error handling
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
    if (!isset($input['username']) || !isset($input['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        exit;
    }

    $username = trim($input['username']);
    $password = $input['password'];
    $csrfToken = $input['csrf_token'] ?? null;

    // Basic input validation
    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username and password cannot be empty']);
        exit;
    }

    // Validate username format
    if (!$auth->validateUsername($username) && !$auth->validateEmail($username)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please enter a valid username or email']);
        exit;
    }

    // Validate password length
    if (strlen($password) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
        exit;
    }

    // Attempt login with enhanced security
    $result = $auth->loginEnhanced($username, $password, $csrfToken);

    if ($result['success']) {
        // Generate new CSRF token for the session
        $newCsrfToken = $auth->generateCSRFToken();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'user' => [
                'id' => $result['user']['id'],
                'username' => $result['user']['username'],
                'email' => $result['user']['email']
            ],
            'csrf_token' => $newCsrfToken
        ]);
    } else {
        // Determine appropriate HTTP status code based on error
        $statusCode = 401; // Unauthorized

        if (strpos($result['message'], 'locked') !== false) {
            $statusCode = 423; // Locked
        } elseif (strpos($result['message'], 'Security validation failed') !== false) {
            $statusCode = 403; // Forbidden
        }

        http_response_code($statusCode);
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ]);
    }

} catch (Exception $e) {
    // Log the error securely
    error_log('Login API Error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An internal server error occurred. Please try again later.'
    ]);
}
?>
