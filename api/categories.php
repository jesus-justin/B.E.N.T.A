
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
 *
 * Error Handling:
 * - All errors return JSON with success=false, message, and error_code
 * - HTTP status codes: 200 (success), 400 (bad request), 401 (unauth), 403 (forbidden), 404 (not found), 409 (conflict), 500 (server error)
 *
 * @author B.E.N.T.A Development Team
 * @version 1.0
 * @since 2024
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

/**
 * Handle POST request to create a new category
 *
 * @param int $userId The authenticated user's ID
 * @param Auth $auth The authentication instance for input sanitization
 * @return void Outputs JSON response
 */
function handleCreateCategory($userId, $auth) {
    try {
        $db = new Database();
        $conn = $db->getConnection();

        // Parse input data (support both JSON and form data)
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $name = trim($input['name'] ?? '');
        $type = trim($input['type'] ?? '');

        // Validate required fields
        if (empty($name)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Category name is required',
                'error_code' => 'NAME_REQUIRED'
            ]);
            return;
        }

        if (empty($type)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Category type is required',
                'error_code' => 'TYPE_REQUIRED'
            ]);
            return;
        }

        // Validate category type
        if (!in_array($type, ['income', 'expense'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Category type must be either income or expense',
                'error_code' => 'INVALID_TYPE',
                'valid_types' => ['income', 'expense']
            ]);
            return;
        }

        // Validate name length
        if (strlen($name) > 100) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Category name must be 100 characters or less',
                'error_code' => 'NAME_TOO_LONG'
            ]);
            return;
        }

        // Check if category name already exists for this user
        $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ? AND user_id = ?");
        $stmt->execute([$name, $userId]);

        if ($stmt->rowCount() > 0) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'A category with this name already exists',
                'error_code' => 'DUPLICATE_NAME'
            ]);
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
            'data' => [
                'id' => $categoryId,
                'name' => $name,
                'type' => $type
            ]
        ]);

    } catch(PDOException $e) {
        // Log the error for debugging
        error_log("Database error in handleCreateCategory: " . $e->getMessage());

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Unable to create category. Please try again later.',
            'error_code' => 'DATABASE_ERROR'
        ]);
    }
}

/**
 * Handle PUT request to update an existing category
 *
 * @param int $userId The authenticated user's ID
 * @param Auth $auth The authentication instance for input sanitization
 * @return void Outputs JSON response
 */
function handleUpdateCategory($userId, $auth) {
    try {
        $db = new Database();
        $conn = $db->getConnection();

        // Parse input data (support both JSON and form data)
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $categoryId = trim($input['id'] ?? '');
        $name = trim($input['name'] ?? '');
        $type = trim($input['type'] ?? '');

        // Validate category ID
        if (empty($categoryId) || !is_numeric($categoryId)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Valid category ID is required',
                'error_code' => 'INVALID_ID'
            ]);
            return;
        }

        // Check if category exists and belongs to user
        $stmt = $conn->prepare("SELECT id, name, type FROM categories WHERE id = ? AND user_id = ?");
        $stmt->execute([$categoryId, $userId]);
        $existingCategory = $stmt->fetch();

        if (!$existingCategory) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Category not found or access denied',
                'error_code' => 'CATEGORY_NOT_FOUND'
            ]);
            return;
        }

        // Build update query dynamically
        $updates = [];
        $params = [];

        // Handle name update
        if ($name !== '') {
            // Validate name length
            if (strlen($name) > 100) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Category name must be 100 characters or less',
                    'error_code' => 'NAME_TOO_LONG'
                ]);
                return;
            }

            // Check if new name conflicts with existing category (excluding current one)
            $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ? AND user_id = ? AND id != ?");
            $stmt->execute([$name, $userId, $categoryId]);

            if ($stmt->rowCount() > 0) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => 'A category with this name already exists',
                    'error_code' => 'DUPLICATE_NAME'
                ]);
                return;
            }

            $updates[] = "name = ?";
            $params[] = $auth->sanitizeInput($name);
        }

        // Handle type update
        if ($type !== '') {
            if (!in_array($type, ['income', 'expense'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Category type must be either income or expense',
                    'error_code' => 'INVALID_TYPE',
                    'valid_types' => ['income', 'expense']
                ]);
                return;
            }
            $updates[] = "type = ?";
            $params[] = $type;
        }

        // Check if any fields are being updated
        if (empty($updates)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'No valid fields provided for update',
                'error_code' => 'NO_UPDATES'
            ]);
            return;
        }

        // Execute update
        $query = "UPDATE categories SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?";
        $params[] = $categoryId;
        $params[] = $userId;

        $stmt = $conn->prepare($query);
        $stmt->execute($params);

        // Get updated category data
        $stmt = $conn->prepare("SELECT id, name, type, created_at, updated_at FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        $updatedCategory = $stmt->fetch();

        echo json_encode([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => $updatedCategory
        ]);

    } catch(PDOException $e) {
        // Log the error for debugging
        error_log("Database error in handleUpdateCategory: " . $e->getMessage());

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Unable to update category. Please try again later.',
            'error_code' => 'DATABASE_ERROR'
        ]);
    }
}

/**
 * Handle DELETE request to remove a category
 *
 * @param int $userId The authenticated user's ID
 * @param Auth $auth The authentication instance for logging
 * @return void Outputs JSON response
 */
function handleDeleteCategory($userId, $auth) {
    try {
        $db = new Database();
        $conn = $db->getConnection();

        $categoryId = trim($_GET['id'] ?? '');

        // Validate category ID
        if (empty($categoryId) || !is_numeric($categoryId)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Valid category ID is required',
                'error_code' => 'INVALID_ID'
            ]);
            return;
        }

        // Check if category exists and belongs to user
        $stmt = $conn->prepare("SELECT id, name, type FROM categories WHERE id = ? AND user_id = ?");
        $stmt->execute([$categoryId, $userId]);
        $category = $stmt->fetch();

        if (!$category) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Category not found or access denied',
                'error_code' => 'CATEGORY_NOT_FOUND'
            ]);
            return;
        }

        // Check if category has transactions (with user ownership validation)
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM transactions WHERE category_id = ? AND user_id = ?");
        $stmt->execute([$categoryId, $userId]);
        $result = $stmt->fetch();

        if ($result['count'] > 0) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'Cannot delete category that contains transactions. Please reassign or delete the transactions first.',
                'error_code' => 'CATEGORY_HAS_TRANSACTIONS',
                'transaction_count' => (int)$result['count']
            ]);
            return;
        }

        // Store category info for response before deletion
        $categoryName = $category['name'];
        $categoryType = $category['type'];

        // Delete category
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
        $stmt->execute([$categoryId, $userId]);

        // Verify deletion
        if ($stmt->rowCount() === 0) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to delete category',
                'error_code' => 'DELETE_FAILED'
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Category deleted successfully',
            'data' => [
                'id' => $categoryId,
                'name' => $categoryName,
                'type' => $categoryType
            ]
        ]);

    } catch(PDOException $e) {
        // Log the error for debugging
        error_log("Database error in handleDeleteCategory: " . $e->getMessage());

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Unable to delete category. Please try again later.',
            'error_code' => 'DATABASE_ERROR'
        ]);
    }
}
?>
