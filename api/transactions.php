<?php
/**
 * Transactions API for B.E.N.T.A
 * Business Expense and Net Transaction Analyzer
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
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
        handleGetTransactions($userId, $functions);
        break;
    case 'POST':
        handleCreateTransaction($userId, $auth, $functions);
        break;
    case 'PUT':
        handleUpdateTransaction($userId, $auth, $functions);
        break;
    case 'DELETE':
        handleDeleteTransaction($userId, $auth, $functions);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}

function handleGetTransactions($userId, $functions) {
    try {
        $db = new Database();
        $conn = $db->getConnection();

        $categoryId = $_GET['category_id'] ?? null;
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;
        $type = $_GET['type'] ?? null;
        $limit = $_GET['limit'] ?? 50;
        $offset = $_GET['offset'] ?? 0;

        $query = "SELECT t.*, c.name as category_name, c.type as category_type
                 FROM transactions t
                 JOIN categories c ON t.category_id = c.id
                 WHERE t.user_id = ?";
        $params = [$userId];

        if ($categoryId) {
            $query .= " AND t.category_id = ?";
            $params[] = $categoryId;
        }

        if ($type) {
            $query .= " AND t.type = ?";
            $params[] = $type;
        }

        if ($startDate && $endDate) {
            $query .= " AND t.date BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        }

        $query .= " ORDER BY t.date DESC, t.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $transactions = $stmt->fetchAll();

        // Format transactions
        foreach ($transactions as &$transaction) {
            $transaction['formatted_amount'] = $functions->formatCurrency($transaction['amount']);
            $transaction['formatted_date'] = $functions->formatDate($transaction['date']);
        }

        echo json_encode([
            'success' => true,
            'data' => $transactions,
            'count' => count($transactions)
        ]);

    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleCreateTransaction($userId, $auth, $functions) {
    try {
        $db = new Database();
        $conn = $db->getConnection();

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $amount = $input['amount'] ?? '';
        $description = $input['description'] ?? '';
        $categoryId = $input['category_id'] ?? '';
        $date = $input['date'] ?? '';
        $type = $input['type'] ?? '';

        // Validate input
        if (empty($amount) || empty($categoryId) || empty($date) || empty($type)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Amount, category, date, and type are required']);
            return;
        }

        if (!is_numeric($amount) || $amount <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Amount must be a positive number']);
            return;
        }

        if (!$functions->validateDate($date)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid date format']);
            return;
        }

        if (!in_array($type, ['income', 'expense'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Type must be income or expense']);
            return;
        }

        // Check if category belongs to user
        if (!$functions->userOwnsResource($userId, $categoryId, 'categories')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid category']);
            return;
        }

        // Sanitize input
        $description = $auth->sanitizeInput($description);

        // Insert transaction
        $stmt = $conn->prepare("INSERT INTO transactions (amount, description, category_id, date, type, user_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$amount, $description, $categoryId, $date, $type, $userId]);

        $transactionId = $conn->lastInsertId();

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Transaction created successfully',
            'transaction_id' => $transactionId
        ]);

    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleUpdateTransaction($userId, $auth, $functions) {
    try {
        $db = new Database();
        $conn = $db->getConnection();

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $transactionId = $input['id'] ?? '';
        $amount = $input['amount'] ?? '';
        $description = $input['description'] ?? '';
        $categoryId = $input['category_id'] ?? '';
        $date = $input['date'] ?? '';
        $type = $input['type'] ?? '';

        if (empty($transactionId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Transaction ID is required']);
            return;
        }

        // Check if transaction belongs to user
        if (!$functions->userOwnsResource($userId, $transactionId, 'transactions')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }

        // Build update query dynamically
        $updates = [];
        $params = [];

        if (!empty($amount)) {
            if (!is_numeric($amount) || $amount <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Amount must be a positive number']);
                return;
            }
            $updates[] = "amount = ?";
            $params[] = $amount;
        }

        if (!empty($description)) {
            $updates[] = "description = ?";
            $params[] = $auth->sanitizeInput($description);
        }

        if (!empty($categoryId)) {
            if (!$functions->userOwnsResource($userId, $categoryId, 'categories')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Invalid category']);
                return;
            }
            $updates[] = "category_id = ?";
            $params[] = $categoryId;
        }

        if (!empty($date)) {
            if (!$functions->validateDate($date)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid date format']);
                return;
            }
            $updates[] = "date = ?";
            $params[] = $date;
        }

        if (!empty($type)) {
            if (!in_array($type, ['income', 'expense'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Type must be income or expense']);
                return;
            }
            $updates[] = "type = ?";
            $params[] = $type;
        }

        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            return;
        }

        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        $query = "UPDATE transactions SET " . implode(', ', $updates) . " WHERE id = ? AND user_id = ?";
        $params[] = $transactionId;
        $params[] = $userId;

        $stmt = $conn->prepare($query);
        $stmt->execute($params);

        echo json_encode([
            'success' => true,
            'message' => 'Transaction updated successfully'
        ]);

    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleDeleteTransaction($userId, $auth, $functions) {
    try {
        $db = new Database();
        $conn = $db->getConnection();

        $transactionId = $_GET['id'] ?? '';

        if (empty($transactionId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Transaction ID is required']);
            return;
        }

        // Check if transaction belongs to user
        if (!$functions->userOwnsResource($userId, $transactionId, 'transactions')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }

        $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
        $stmt->execute([$transactionId, $userId]);

        echo json_encode([
            'success' => true,
            'message' => 'Transaction deleted successfully'
        ]);

    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
