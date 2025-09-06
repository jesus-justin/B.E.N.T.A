<?php
/**
 * Utility Functions for B.E.N.T.A
 * Business Expense and Net Transaction Analyzer
 */

require_once 'config/db.php';

class Functions {
    private $db;
    private $conn;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }

    // Format currency
    public function formatCurrency($amount, $currency = 'PHP') {
        $symbol = $this->getCurrencySymbol($currency);
        return $symbol . number_format($amount, 2);
    }

    // Get currency symbol
    public function getCurrencySymbol($currency) {
        $symbols = [
            'PHP' => '₱',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥'
        ];
        return $symbols[$currency] ?? $currency;
    }

    // Format date
    public function formatDate($date, $format = 'M d, Y') {
        return date($format, strtotime($date));
    }

    // Calculate total income for user
    public function getTotalIncome($userId, $startDate = null, $endDate = null) {
        try {
            $query = "SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'income'";
            $params = [$userId];

            if ($startDate && $endDate) {
                $query .= " AND date BETWEEN ? AND ?";
                $params[] = $startDate;
                $params[] = $endDate;
            }

            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch();

            return $result['total'] ?? 0;
        } catch(PDOException $e) {
            return 0;
        }
    }

    // Calculate total expenses for user
    public function getTotalExpenses($userId, $startDate = null, $endDate = null) {
        try {
            $query = "SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'expense'";
            $params = [$userId];

            if ($startDate && $endDate) {
                $query .= " AND date BETWEEN ? AND ?";
                $params[] = $startDate;
                $params[] = $endDate;
            }

            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch();

            return $result['total'] ?? 0;
        } catch(PDOException $e) {
            return 0;
        }
    }

    // Calculate net income
    public function getNetIncome($userId, $startDate = null, $endDate = null) {
        $income = $this->getTotalIncome($userId, $startDate, $endDate);
        $expenses = $this->getTotalExpenses($userId, $startDate, $endDate);
        return $income - $expenses;
    }

    // Get transactions by category
    public function getTransactionsByCategory($userId, $categoryId, $startDate = null, $endDate = null) {
        try {
            $query = "SELECT * FROM transactions WHERE user_id = ? AND category_id = ?";
            $params = [$userId, $categoryId];

            if ($startDate && $endDate) {
                $query .= " AND date BETWEEN ? AND ?";
                $params[] = $startDate;
                $params[] = $endDate;
            }

            $query .= " ORDER BY date DESC";

            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }

    // Get monthly summary
    public function getMonthlySummary($userId, $year = null, $month = null) {
        if (!$year) $year = date('Y');
        if (!$month) $month = date('m');

        $startDate = "$year-$month-01";
        $endDate = date('Y-m-t', strtotime($startDate));

        return [
            'income' => $this->getTotalIncome($userId, $startDate, $endDate),
            'expenses' => $this->getTotalExpenses($userId, $startDate, $endDate),
            'net' => $this->getNetIncome($userId, $startDate, $endDate),
            'period' => date('F Y', strtotime($startDate))
        ];
    }

    // Get category breakdown
    public function getCategoryBreakdown($userId, $type, $startDate = null, $endDate = null) {
        try {
            $query = "SELECT c.name, SUM(t.amount) as total, COUNT(t.id) as count
                     FROM transactions t
                     JOIN categories c ON t.category_id = c.id
                     WHERE t.user_id = ? AND t.type = ?";
            $params = [$userId, $type];

            if ($startDate && $endDate) {
                $query .= " AND t.date BETWEEN ? AND ?";
                $params[] = $startDate;
                $params[] = $endDate;
            }

            $query .= " GROUP BY c.id, c.name ORDER BY total DESC";

            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }

    // Validate date format
    public function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    // Generate random string
    public function generateRandomString($length = 10) {
        return bin2hex(random_bytes($length / 2));
    }

    // Check if user owns resource
    public function userOwnsResource($userId, $resourceId, $table, $resourceColumn = 'id') {
        try {
            $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM $table WHERE $resourceColumn = ? AND user_id = ?");
            $stmt->execute([$resourceId, $userId]);
            $result = $stmt->fetch();
            return $result['count'] > 0;
        } catch(PDOException $e) {
            return false;
        }
    }

    // Get user settings
    public function getUserSettings($userId) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM settings WHERE user_id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetch();
        } catch(PDOException $e) {
            return null;
        }
    }

    // Update user settings
    public function updateUserSettings($userId, $settings) {
        try {
            $stmt = $this->conn->prepare("UPDATE settings SET business_name = ?, currency = ?, fiscal_year_start = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
            $stmt->execute([
                $settings['business_name'],
                $settings['currency'],
                $settings['fiscal_year_start'],
                $userId
            ]);
            return true;
        } catch(PDOException $e) {
            return false;
        }
    }
}
?>
