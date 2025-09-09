<?php
/**
 * Settings API for B.E.N.T.A
 * Business Expense and Net Transaction Analyzer
 *
 * This API provides user settings management functionality.
 *
 * Security features:
 * - Authentication required for all endpoints
 * - Input validation and sanitization
 * - User ownership validation
 * - Rate limiting considerations
 *
 * Error Handling:
 * - All errors return JSON with success=false, message, and error_code
 * - HTTP status codes: 200 (success), 400 (bad request), 401 (unauth), 500 (server error)
 *
 * @author B.E.N.T.A Development Team
 * @version 1.0
 * @since 2024
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth = new Auth();
$functions = new Functions();

// Check authentication
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required',
        'error_code' => 'AUTH_REQUIRED'
    ]);
    exit;
}

$user = $auth->getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid user session',
        'error_code' => 'INVALID_SESSION'
    ]);
    exit;
}

$userId = $user['id'];
$method = $_SERVER['REQUEST_METHOD'];

// Validate CSRF token for state-changing operations
if (in_array($method, ['PUT'])) {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
    if (!$csrfToken || !$auth->validateCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid security token',
            'error_code' => 'CSRF_INVALID'
        ]);
        exit;
    }
}

switch ($method) {
    case 'GET':
        handleGetSettings($userId, $functions);
        break;
    case 'PUT':
        handleUpdateSettings($userId, $auth, $functions);
        break;
    default:
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'HTTP method not allowed',
            'error_code' => 'METHOD_NOT_ALLOWED',
            'allowed_methods' => ['GET', 'PUT']
        ]);
        exit;
}

function handleGetSettings($userId, $functions) {
    try {
        $settings = $functions->getUserSettings($userId);

        if (!$settings) {
            // Create default settings if none exist
            $db = new Database();
            $conn = $db->getConnection();

            $stmt = $conn->prepare("INSERT INTO settings (user_id, business_name, currency) VALUES (?, 'My Business', 'PHP')");
            $stmt->execute([$userId]);

            $settings = $functions->getUserSettings($userId);
        }

        echo json_encode([
            'success' => true,
            'data' => $settings
        ]);

    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Unable to retrieve settings. Please try again later.',
            'error_code' => 'DATABASE_ERROR'
        ]);
    }
}

function handleUpdateSettings($userId, $auth, $functions) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $businessName = $input['business_name'] ?? '';
        $currency = $input['currency'] ?? '';
        $fiscalYearStart = $input['fiscal_year_start'] ?? '';

        // Validate currency
        $validCurrencies = ['PHP', 'USD', 'EUR', 'GBP', 'JPY'];
        if (!empty($currency) && !in_array($currency, $validCurrencies)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid currency',
                'error_code' => 'INVALID_CURRENCY'
            ]);
            return;
        }

        // Validate fiscal year start date
        if (!empty($fiscalYearStart) && !$functions->validateDate($fiscalYearStart)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid fiscal year start date',
                'error_code' => 'INVALID_FISCAL_YEAR_START'
            ]);
            return;
        }

        // Build settings array
        $settings = [];

        if (!empty($businessName)) {
            $settings['business_name'] = $auth->sanitizeInput($businessName);
        }

        if (!empty($currency)) {
            $settings['currency'] = $currency;
        }

        if (!empty($fiscalYearStart)) {
            $settings['fiscal_year_start'] = $fiscalYearStart;
        }

        if (empty($settings)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'No valid settings to update',
                'error_code' => 'NO_VALID_SETTINGS'
            ]);
            return;
        }

        // Update settings
        $result = $functions->updateUserSettings($userId, $settings);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Settings updated successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update settings',
                'error_code' => 'UPDATE_FAILED'
            ]);
        }

    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Unable to update settings. Please try again later.',
            'error_code' => 'DATABASE_ERROR'
        ]);
    }
}
?>
