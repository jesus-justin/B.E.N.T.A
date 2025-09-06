<?php
/**
 * Registration API for B.E.N.T.A
 * Business Expense and Net Transaction Analyzer
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'includes/auth.php';

$auth = new Auth();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// If not JSON, try form data
if (!$input) {
    $input = $_POST;
}

$username = $input['username'] ?? '';
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';
$confirmPassword = $input['confirm_password'] ?? '';

// Validate input
if (empty($username) || empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if ($password !== $confirmPassword) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
    exit;
}

// Validate email format
if (!$auth->validateEmail($email)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Validate password strength
if (!$auth->validatePassword($password)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number'
    ]);
    exit;
}

// Sanitize input
$username = $auth->sanitizeInput($username);
$email = $auth->sanitizeInput($email);

// Attempt registration
$result = $auth->register($username, $email, $password);

if ($result['success']) {
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => $result['message'],
        'user_id' => $result['user_id']
    ]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $result['message']]);
}
?>
