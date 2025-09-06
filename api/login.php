<?php
/**
 * Login API for B.E.N.T.A
 * Business Expense and Net Transaction Analyzer
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/auth.php';

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
$password = $input['password'] ?? '';

// Validate input
if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit;
}

// Sanitize input
$username = $auth->sanitizeInput($username);
$password = $auth->sanitizeInput($password);

// Attempt login
$result = $auth->login($username, $password);

if ($result['success']) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $result['message'],
        'user' => [
            'id' => $result['user']['id'],
            'username' => $result['user']['username'],
            'email' => $result['user']['email']
        ]
    ]);
} else {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => $result['message']]);
}
?>
