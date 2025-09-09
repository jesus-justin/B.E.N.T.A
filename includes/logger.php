<?php
/**
 * Logging Utility Class for B.E.N.T.A
 * Business Expense and Net Transaction Analyzer
 *
 * This class provides centralized logging functionality for security events,
 * errors, and application activities with configurable log levels and storage.
 *
 * Features:
 * - Multiple log levels (DEBUG, INFO, WARNING, ERROR, CRITICAL)
 * - Security event logging
 * - Database and file logging support
 * - Configurable log retention
 * - Performance monitoring
 *
 * @author B.E.N.T.A Development Team
 * @version 1.0
 * @since 2024
 */

require_once 'config/db.php';

class Logger {
    private $db;
    private $conn;
    private $logLevels = [
        'DEBUG' => 1,
        'INFO' => 2,
        'WARNING' => 3,
        'ERROR' => 4,
        'CRITICAL' => 5
    ];
    private $currentLevel = 'INFO'; // Default log level
    private $logFile = 'logs/benta.log';
    private $maxFileSize = 10485760; // 10MB

    /**
     * Constructor - Initialize database connection and log directory
     */
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();

        // Create logs directory if it doesn't exist
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Create log table if it doesn't exist
        $this->createLogTable();
    }

    /**
     * Create logs table in database
     */
    private function createLogTable() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                level VARCHAR(10) NOT NULL,
                message TEXT NOT NULL,
                context TEXT,
                user_id INT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_level (level),
                INDEX idx_user_id (user_id),
                INDEX idx_created_at (created_at)
            )";
            $this->conn->exec($sql);
        } catch(PDOException $e) {
            // Fallback to file logging if database logging fails
            $this->logToFile('ERROR', 'Failed to create logs table: ' . $e->getMessage());
        }
    }

    /**
     * Log a message with specified level
     *
     * @param string $level Log level (DEBUG, INFO, WARNING, ERROR, CRITICAL)
     * @param string $message Log message
     * @param array $context Additional context data
     * @param int|null $userId User ID if applicable
     */
    public function log($level, $message, $context = [], $userId = null) {
        $level = strtoupper($level);

        // Check if level is valid and should be logged
        if (!isset($this->logLevels[$level]) || $this->logLevels[$level] < $this->logLevels[$this->currentLevel]) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // Prepare context data
        $contextJson = !empty($context) ? json_encode($context) : null;

        // Log to database
        $this->logToDatabase($level, $message, $contextJson, $userId, $ipAddress, $userAgent);

        // Log to file as backup
        $this->logToFile($level, $message, $context, $userId, $ipAddress);
    }

    /**
     * Log to database
     */
    private function logToDatabase($level, $message, $context, $userId, $ipAddress, $userAgent) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO logs (level, message, context, user_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$level, $message, $context, $userId, $ipAddress, $userAgent]);
        } catch(PDOException $e) {
            // If database logging fails, log the failure to file
            $this->logToFile('ERROR', 'Database logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Log to file
     */
    private function logToFile($level, $message, $context = [], $userId = null, $ipAddress = null) {
        try {
            // Rotate log file if it gets too large
            if (file_exists($this->logFile) && filesize($this->logFile) > $this->maxFileSize) {
                $this->rotateLogFile();
            }

            $timestamp = date('Y-m-d H:i:s');
            $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
            $userStr = $userId ? " | User: $userId" : '';
            $ipStr = $ipAddress ? " | IP: $ipAddress" : '';

            $logEntry = "[$timestamp] $level: $message$userStr$ipStr$contextStr" . PHP_EOL;

            file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        } catch(Exception $e) {
            // Last resort - log to PHP error log
            error_log("Logger file write failed: " . $e->getMessage());
        }
    }

    /**
     * Rotate log file when it gets too large
     */
    private function rotateLogFile() {
        $timestamp = date('Y-m-d_H-i-s');
        $rotatedFile = $this->logFile . '.' . $timestamp;

        if (rename($this->logFile, $rotatedFile)) {
            // Compress old log file
            $this->compressLogFile($rotatedFile);
        }
    }

    /**
     * Compress rotated log file
     */
    private function compressLogFile($file) {
        if (function_exists('gzcompress') && file_exists($file)) {
            $content = file_get_contents($file);
            $compressed = gzcompress($content);
            file_put_contents($file . '.gz', $compressed);
            unlink($file); // Remove uncompressed file
        }
    }

    /**
     * Log debug message
     */
    public function debug($message, $context = [], $userId = null) {
        $this->log('DEBUG', $message, $context, $userId);
    }

    /**
     * Log info message
     */
    public function info($message, $context = [], $userId = null) {
        $this->log('INFO', $message, $context, $userId);
    }

    /**
     * Log warning message
     */
    public function warning($message, $context = [], $userId = null) {
        $this->log('WARNING', $message, $context, $userId);
    }

    /**
     * Log error message
     */
    public function error($message, $context = [], $userId = null) {
        $this->log('ERROR', $message, $context, $userId);
    }

    /**
     * Log critical message
     */
    public function critical($message, $context = [], $userId = null) {
        $this->log('CRITICAL', $message, $context, $userId);
    }

    /**
     * Log security event
     */
    public function security($event, $message, $context = [], $userId = null) {
        $context['event_type'] = $event;
        $this->log('WARNING', "SECURITY: $message", $context, $userId);
    }

    /**
     * Get recent logs
     */
    public function getRecentLogs($limit = 100, $level = null) {
        try {
            $query = "SELECT * FROM logs";
            $params = [];

            if ($level) {
                $query .= " WHERE level = ?";
                $params[] = strtoupper($level);
            }

            $query .= " ORDER BY created_at DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }

    /**
     * Clean old logs
     */
    public function cleanOldLogs($days = 30) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->execute([$days]);
            return $stmt->rowCount();
        } catch(PDOException $e) {
            return 0;
        }
    }

    /**
     * Set log level
     */
    public function setLogLevel($level) {
        $level = strtoupper($level);
        if (isset($this->logLevels[$level])) {
            $this->currentLevel = $level;
        }
    }

    /**
     * Get current log level
     */
    public function getLogLevel() {
        return $this->currentLevel;
    }
}
?>
