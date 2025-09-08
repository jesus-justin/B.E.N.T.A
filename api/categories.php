 <?php
/**
 * Categories API for B.E.N.T.A
 * Business Expense and Net Transaction Analyzer
 *
 * This API provides CRUD operations for managing transaction categories.
 * All endpoints require authentication and validate user ownership.
 *
 * Security features:
 * - Authentication required for all endpoints
 * - CSRF protection for state-changing operations
 * - Input validation and sanitization
 * - User ownership validation
 * - Rate limiting considerations
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

require_once 'includes/auth.php';

$auth = new Auth();

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
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
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
        handleGetCategories($userId, $auth);
        break;
    case 'POST':
        handleCreateCategory($userId, $auth);
        break;
    case 'PUT':
        handleUpdateCategory($userId, $auth);
        break;
    case 'DELETE':
        handleDeleteCategory($userId, $auth);
        break;
    default:
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'HTTP method not allowed',
            'error_code' => 'METHOD_NOT_ALLOWED',
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE']
        ]);
        break;
}

/**
 * Handle GET request to retrieve user categories
 *
 * @param int $userId The authenticated user's ID
 * @param Auth $auth The authentication instance for logging
 * @return void Outputs JSON response
 */
function handleGetCategories($userId, $auth) {
    try {
        $db = new Database();
        $conn = $db->getConnection();

        $type = $_GET['type'] ?? null;

        // Validate type parameter if provided
        if ($type && !in_array($type, ['income', 'expense'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid category type. Must be income or expense',
                'error_code' => 'INVALID_TYPE'
            ]);
            return;
        }

        $query = "SELECT id, name, type, created_at FROM categories WHERE user_id = ?";
        $params = [$userId];

        if ($type) {
            $query .= " AND type = ?";
            $params[] = $type;
        }

        $query .= " ORDER BY type, name";

        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $categories = $stmt->fetchAll();

        // Get transaction counts for each category
        foreach ($categories as &$category) {
            $stmt = $conn->prepare("SELECT COUNT(*) as transaction_count FROM transactions WHERE category_id = ? AND user_id = ?");
            $stmt->execute([$category['id'], $userId]);
            $result = $stmt->fetch();
            $category['transaction_count'] = (int)$result['transaction_count'];
        }

        echo json_encode([
            'success' => true,
            'data' => $categories,
            'count' => count($categories),
            'message' => 'Categories retrieved successfully'
        ]);

    } catch(PDOException $e) {
        // Log the error for debugging
        error_log("Database error in handleGetCategories: " . $e->getMessage());

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Unable to retrieve categories. Please try again later.',
            'error_code' => 'DATABASE_ERROR'
        ]);
    }
}

function handleCreateCategory($userId, $auth) {
    try {
        $db = new Database();
        $conn = $db->getConnection();

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $name = $input['name'] ?? '';
        $type = $input['type'] ?? '';

        // Validate input
        if (empty($name) || empty($type)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Name and type are required']);
            return;
        }

        if (!in_array($type, ['income', 'expense'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Type must be income or expense']);
            return;
        }

        // Check if category name already exists for this user
        $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ? AND user_id = ?");
        $stmt->execute([$name, $userId]);

        if ($stmt->rowCount() > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Category name already exists']);
            return;
        }

        // Sanitize input
        $name = $auth->sanitizeInput($name);

        // Insert category
        $stmt = $conn->prepare("INSERT INTO categories (name, type, user_id) VALUES (?, ?, ?)");
        $stmt->execute([$name, $type, $userId]);

        $categoryId = $conn->lastInsertId();

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Category created successfully',
            'category_id' => $categoryId
        ]);

    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleUpdateCategory($userId, $auth) {
    try {
        $db = new Database();
        $conn = $db->getConnection();

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $categoryId = $input['id'] ?? '';
        $name = $input['name'] ?? '';
        $type = $input['type'] ?? '';

        if (empty($categoryId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Category ID is required']);
            return;
        }

        // Check if category belongs to user
        $stmt = $conn->prepare("SELECT id FROM categories WHERE id = ? AND user_id = ?");
        $stmt->execute([$categoryId, $userId]);

        if ($stmt->rowCount() == 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }

        // Build update query
        $updates = [];
        $params = [];

        if (!empty($name)) {
            // Check if new name conflicts with existing category
            $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ? AND user_id = ? AND id != ?");
            $stmt->execute([$name, $userId, $categoryId]);

            if ($stmt->rowCount() > 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Category name already exists']);
                return;
            }

            $updates[] = "name = ?";
            $params[] = $auth->sanitizeInput($name);
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

        $query = "UPDATE categories SET " . implode(', ', $updates) . " WHERE id = ? AND user_id = ?";
        $params[] = $categoryId;
        $params[] = $userId;

        $stmt = $conn->prepare($query);
        $stmt->execute($params);

        echo json_encode([
            'success' => true,
            'message' => 'Category updated successfully'
        ]);

    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleDeleteCategory($userId) {
    try {
        $db = new Database();
        $conn = $db->getConnection();

        $categoryId = $_GET['id'] ?? '';

        if (empty($categoryId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Category ID is required']);
            return;
        }

        // Check if category belongs to user
        $stmt = $conn->prepare("SELECT id FROM categories WHERE id = ? AND user_id = ?");
        $stmt->execute([$categoryId, $userId]);

        if ($stmt->rowCount() == 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }

        // Check if category has transactions
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM transactions WHERE category_id = ?");
        $stmt->execute([$categoryId]);
        $result = $stmt->fetch();

        if ($result['count'] > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Cannot delete category with existing transactions']);
            return;
        }

        // Delete category
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
        $stmt->execute([$categoryId, $userId]);

        echo json_encode([
            'success' => true,
            'message' => 'Category deleted successfully'
        ]);

    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
