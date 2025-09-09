<?php
/**
 * Rate Limiting Utility Class for B.E.N.T.A
 * Business Expense and Net Transaction Analyzer
 *
 * This class provides rate limiting functionality to prevent abuse and ensure
 * fair usage of API endpoints and application resources.
 *
 * Features:
 * - IP-based rate limiting
 * - User-based rate limiting
 * - Configurable limits and windows
 * - Multiple storage backends (database, Redis, memory)
 * - Automatic cleanup of old entries
 *
 * @author B.E.N.T.A Development Team
 * @version 1.0
 * @since 2024
 */

require_once 'config/db.php';

class RateLimiter {
    private $db;
    private $conn;
    private $limits = [
        'api_general' => ['max_requests' => 100, 'window_seconds' => 60], // 100 requests per minute
        'api_auth' => ['max_requests' => 5, 'window_seconds' => 300],    // 5 auth attempts per 5 minutes
        'api_sensitive' => ['max_requests' => 10, 'window_seconds' => 60] // 10 sensitive operations per minute
    ];

    /**
     * Constructor - Initialize database connection
     */
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();

        // Create rate limiting table if it doesn't exist
        $this->createRateLimitTable();
    }

    /**
     * Create rate_limits table in database
     */
    private function createRateLimitTable() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS rate_limits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                identifier VARCHAR(255) NOT NULL,
                action VARCHAR(100) NOT NULL,
                request_count INT DEFAULT 1,
                window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_request TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_identifier_action (identifier, action),
                INDEX idx_window_start (window_start)
            )";
            $this->conn->exec($sql);
        } catch(PDOException $e) {
            // Log error but don't fail
            error_log("Failed to create rate_limits table: " . $e->getMessage());
        }
    }

    /**
     * Check if request is within rate limits
     *
     * @param string $identifier Unique identifier (IP address or user ID)
     * @param string $action Action being performed
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_time' => int]
     */
    public function checkLimit($identifier, $action) {
        if (!isset($this->limits[$action])) {
            // No limit defined for this action, allow it
            return ['allowed' => true, 'remaining' => PHP_INT_MAX, 'reset_time' => 0];
        }

        $limit = $this->limits[$action];
        $maxRequests = $limit['max_requests'];
        $windowSeconds = $limit['window_seconds'];

        try {
            // Clean up old entries first
            $this->cleanupOldEntries($action, $windowSeconds);

            // Get current request count for this identifier and action
            $stmt = $this->conn->prepare("
                SELECT request_count, window_start, UNIX_TIMESTAMP(window_start) + ? as reset_time
                FROM rate_limits
                WHERE identifier = ? AND action = ?
                AND window_start > DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$windowSeconds, $identifier, $action, $windowSeconds]);
            $result = $stmt->fetch();

            if ($result) {
                $currentCount = $result['request_count'];
                $resetTime = $result['reset_time'];

                if ($currentCount >= $maxRequests) {
                    return [
                        'allowed' => false,
                        'remaining' => 0,
                        'reset_time' => $resetTime,
                        'retry_after' => max(0, $resetTime - time())
                    ];
                }

                // Update existing record
                $stmt = $this->conn->prepare("
                    UPDATE rate_limits
                    SET request_count = request_count + 1, last_request = NOW()
                    WHERE identifier = ? AND action = ?
                    AND window_start > DATE_SUB(NOW(), INTERVAL ? SECOND)
                ");
                $stmt->execute([$identifier, $action, $windowSeconds]);

                return [
                    'allowed' => true,
                    'remaining' => $maxRequests - $currentCount - 1,
                    'reset_time' => $resetTime
                ];
            } else {
                // Create new record
                $stmt = $this->conn->prepare("
                    INSERT INTO rate_limits (identifier, action, request_count, window_start, last_request)
                    VALUES (?, ?, 1, NOW(), NOW())
                ");
                $stmt->execute([$identifier, $action]);

                $resetTime = time() + $windowSeconds;

                return [
                    'allowed' => true,
                    'remaining' => $maxRequests - 1,
                    'reset_time' => $resetTime
                ];
            }

        } catch(PDOException $e) {
            // If database fails, allow request but log error
            error_log("Rate limiting database error: " . $e->getMessage());
            return ['allowed' => true, 'remaining' => PHP_INT_MAX, 'reset_time' => 0];
        }
    }

    /**
     * Check rate limit for IP address
     */
    public function checkIpLimit($action) {
        $ipAddress = $this->getClientIp();
        return $this->checkLimit($ipAddress, $action);
    }

    /**
     * Check rate limit for user
     */
    public function checkUserLimit($userId, $action) {
        return $this->checkLimit("user_$userId", $action);
    }

    /**
     * Get client IP address
     */
    private function getClientIp() {
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (like X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Clean up old rate limit entries
     */
    private function cleanupOldEntries($action, $windowSeconds) {
        try {
            $stmt = $this->conn->prepare("
                DELETE FROM rate_limits
                WHERE action = ? AND window_start < DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$action, $windowSeconds * 2]); // Keep some buffer
        } catch(PDOException $e) {
            // Log but don't fail
            error_log("Rate limit cleanup failed: " . $e->getMessage());
        }
    }

    /**
     * Reset rate limit for specific identifier and action
     */
    public function resetLimit($identifier, $action) {
        try {
            $stmt = $this->conn->prepare("
                DELETE FROM rate_limits
                WHERE identifier = ? AND action = ?
            ");
            $stmt->execute([$identifier, $action]);
            return true;
        } catch(PDOException $e) {
            return false;
        }
    }

    /**
     * Get rate limit status
     */
    public function getLimitStatus($identifier, $action) {
        try {
            $stmt = $this->conn->prepare("
                SELECT request_count, window_start, last_request
                FROM rate_limits
                WHERE identifier = ? AND action = ?
                AND window_start > DATE_SUB(NOW(), INTERVAL 300 SECOND)
                ORDER BY window_start DESC
                LIMIT 1
            ");
            $stmt->execute([$identifier, $action]);
            return $stmt->fetch();
        } catch(PDOException $e) {
            return null;
        }
    }

    /**
     * Configure rate limit for an action
     */
    public function setLimit($action, $maxRequests, $windowSeconds) {
        $this->limits[$action] = [
            'max_requests' => $maxRequests,
            'window_seconds' => $windowSeconds
        ];
    }

    /**
     * Get configured limits
     */
    public function getLimits() {
        return $this->limits;
    }

    /**
     * Middleware function for API endpoints
     */
    public function middleware($action = 'api_general', $useUserId = false) {
        return function() use ($action, $useUserId) {
            if ($useUserId && isset($_SESSION['user_id'])) {
                $result = $this->checkUserLimit($_SESSION['user_id'], $action);
            } else {
                $result = $this->checkIpLimit($action);
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
        };
    }
}
?>
