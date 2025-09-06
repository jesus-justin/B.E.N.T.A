<?php
/**
 * Settings API for B.E.N.T.A
 * Business Expense and Net Transaction Analyzer
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth = new Auth();
$functions = new Functions();

// Check authentication
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$user = $auth->getCurrentUser();
$userId = $user['id'];
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetSettings($userId, $functions);
        break;
    case 'PUT':
        handleUpdateSettings($userId, $auth, $functions);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
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
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
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
            echo json_encode(['success' => false, 'message' => 'Invalid currency']);
            return;
        }

        // Validate fiscal year start date
        if (!empty($fiscalYearStart) && !$functions->validateDate($fiscalYearStart)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid fiscal year start date']);
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
            echo json_encode(['success' => false, 'message' => 'No valid settings to update']);
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
            echo json_encode(['success' => false, 'message' => 'Failed to update settings']);
        }

    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
