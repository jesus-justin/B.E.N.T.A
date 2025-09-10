<?php
/**
 * Middleware Class for B.E.N.T.A
 * Business Expense and Net Transaction Analyzer
 *
 * This class provides middleware functionality for input sanitization,
 * validation, authentication, and other common request processing tasks.
 *
 * Features:
 * - Input sanitization and validation
 * - Authentication middleware
 * - CSRF protection middleware
 * - Request size validation
 * - Content type validation
 * - CORS handling
 *
 * @author B.E.N.T.A Development Team
 * @version 1.0
 * @since 2024
 */

require_once 'auth.php';
require_once 'rate_limiter.php';
require_once 'logger.php';

class Middleware {
    private $auth;
    private $rateLimiter;
    private $logger;
    private $maxRequestSize = 1048576; // 1MB default
    private $allowedOrigins = ['*']; // Allow all by default

    /**
     * Constructor - Initialize dependencies
     */
    public function __construct() {
        $this->auth = new Auth();
        $this->rateLimiter = new RateLimiter();
        $this->logger = new Logger();
    }

    /**
     * Sanitize input data
     *
     * @param mixed $input Input to sanitize
     * @param string $type Type of sanitization (string, email, url, etc.)
     * @return mixed Sanitized input
     */
    public function sanitize($input, $type = 'string') {
        if (is_array($input)) {
            return array_map(function($item) use ($type) {
                return $this->sanitize($item, $type);
            }, $input);
        }

        if ($input === null || $input === '') {
            return $input;
        }

        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'html':
                return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            case 'string':
            default:
                return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
        }
    }

    /**
     * Validate input data
     *
     * @param mixed $input Input to validate
     * @param string $type Type of validation
     * @param array $options Additional validation options
     * @return bool True if valid
     */
    public function validate($input, $type, $options = []) {
        if ($input === null || $input === '') {
            return isset($options['required']) ? false : true;
        }

        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_VALIDATE_EMAIL) !== false;
            case 'url':
                return filter_var($input, FILTER_VALIDATE_URL) !== false;
            case 'int':
                $validated = filter_var($input, FILTER_VALIDATE_INT);
                if ($validated === false) return false;
                if (isset($options['min']) && $validated < $options['min']) return false;
                if (isset($options['max']) && $validated > $options['max']) return false;
                return true;
            case 'float':
                $validated = filter_var($input, FILTER_VALIDATE_FLOAT);
                if ($validated === false) return false;
                if (isset($options['min']) && $validated < $options['min']) return false;
                if (isset($options['max']) && $validated > $options['max']) return false;
                return true;
            case 'string':
                $length = strlen($input);
                if (isset($options['min_length']) && $length < $options['min_length']) return false;
                if (isset($options['max_length']) && $length > $options['max_length']) return false;
                if (isset($options['pattern']) && !preg_match($options['pattern'], $input)) return false;
                return true;
            case 'date':
                $format = $options['format'] ?? 'Y-m-d';
                $d = DateTime::createFromFormat($format, $input);
                return $d && $d->format($format) === $input;
            case 'enum':
                return isset($options['values']) && in_array($input, $options['values']);
            default:
                return true;
        }
    }

    /**
     * Sanitize and validate request data
     *
     * @param array $rules Validation rules
     * @param array $data Data to validate (defaults to $_POST or parsed JSON)
     * @return array ['valid' => bool, 'data' => array, 'errors' => array]
     */
    public function sanitizeAndValidate($rules, $data = null) {
        if ($data === null) {
            $data = $_POST;
            // Try to parse JSON body
            $jsonData = json_decode(file_get_contents('php://input'), true);
            if ($jsonData) {
                $data = array_merge($data, $jsonData);
            }
        }

        $sanitized = [];
        $errors = [];
        $valid = true;

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;

            // Sanitize
            $sanitizeType = $rule['sanitize'] ?? 'string';
            $sanitized[$field] = $this->sanitize($value, $sanitizeType);

            // Validate
            if (isset($rule['validate'])) {
                $validateType = $rule['validate'];
                $validateOptions = $rule['options'] ?? [];

                if (!$this->validate($sanitized[$field], $validateType, $validateOptions)) {
                    $valid = false;
                    $errors[$field] = $rule['error_message'] ?? "Invalid value for $field";
                }
            }

            // Check required fields
            if (isset($rule['required']) && $rule['required'] && empty($sanitized[$field])) {
                $valid = false;
                $errors[$field] = $rule['error_message'] ?? "$field is required";
            }
        }

        return [
            'valid' => $valid,
            'data' => $sanitized,
            'errors' => $errors
        ];
    }

    /**
     * Authentication middleware
     */
    public function authenticate() {
        if (!$this->auth->isLoggedIn()) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Authentication required',
                'error_code' => 'AUTH_REQUIRED'
            ]);
            exit;
        }

        $user = $this->auth->getCurrentUser();
        if (!$user) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid user session',
                'error_code' => 'INVALID_SESSION'
            ]);
            exit;
        }

        return $user;
    }

    /**
     * CSRF protection middleware
     */
    public function csrfProtect() {
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
        if (!$csrfToken || !$this->auth->validateCSRFToken($csrfToken)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid security token',
                'error_code' => 'CSRF_INVALID'
            ]);
            exit;
        }
    }

    /**
     * Rate limiting middleware
     */
    public function rateLimit($action = 'api_general', $useUserId = false) {
        if ($useUserId && isset($_SESSION['user_id'])) {
            $result = $this->rateLimiter->checkUserLimit($_SESSION['user_id'], $action);
        } else {
            $result = $this->rateLimiter->checkIpLimit($action);
        }

        if (!$result['allowed']) {
            http_response_code(429);
            header('Retry-After: ' . $result['retry_after']);
            echo json_encode([
                'success' => false,
                'message' => 'Rate limit exceeded. Please try again later.',
                'error_code' => 'RATE_LIMIT_EXCEEDED',
                'retry_after' => $result['retry_after'],
                'reset_time' => $result['reset_time']
            ]);
            exit;
        }
    }

    /**
     * Request size validation middleware
     */
    public function validateRequestSize($maxSize = null) {
        $maxSize = $maxSize ?? $this->maxRequestSize;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
            $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;
            if ($contentLength > $maxSize) {
                http_response_code(413);
                echo json_encode([
                    'success' => false,
                    'message' => 'Request too large',
                    'error_code' => 'REQUEST_TOO_LARGE',
                    'max_size' => $maxSize
                ]);
                exit;
            }
        }
    }

    /**
     * Content type validation middleware
     */
    public function validateContentType($allowedTypes = ['application/json', 'application/x-www-form-urlencoded']) {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        // Extract main content type (remove charset, etc.)
        $contentType = explode(';', $contentType)[0];

        if (!in_array($contentType, $allowedTypes)) {
            http_response_code(415);
            echo json_encode([
                'success' => false,
                'message' => 'Unsupported content type',
                'error_code' => 'UNSUPPORTED_CONTENT_TYPE',
                'allowed_types' => $allowedTypes
            ]);
            exit;
        }
    }

    /**
     * CORS handling middleware
     */
    public function handleCors() {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array('*', $this->allowedOrigins) || in_array($origin, $this->allowedOrigins)) {
            header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');

        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    /**
     * Security headers middleware
     */
    public function securityHeaders() {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Request logging middleware
     */
    public function logRequest($userId = null) {
        $this->logger->info('API Request', [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ], $userId);
    }

    /**
     * Error handling middleware
     */
    public function handleErrors() {
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            $this->logger->error('PHP Error', [
                'error_number' => $errno,
                'error_string' => $errstr,
                'error_file' => $errfile,
                'error_line' => $errline
            ]);

            if (error_reporting() & $errno) {
                throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
            }
        });

        set_exception_handler(function($exception) {
            $this->logger->error('Uncaught Exception', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ]);

            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Internal server error',
                'error_code' => 'INTERNAL_ERROR'
            ]);
            exit;
        });
    }

    /**
     * Set maximum request size
     */
    public function setMaxRequestSize($size) {
        $this->maxRequestSize = $size;
    }

    /**
     * Set allowed CORS origins
     */
    public function setAllowedOrigins($origins) {
        $this->allowedOrigins = $origins;
    }
}
?>
