<?php
/**
 * Reports API for B.E.N.T.A
 * Business Expense and Net Transaction Analyzer
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
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

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$reportType = $_GET['type'] ?? 'summary';
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

switch ($reportType) {
    case 'summary':
        generateSummaryReport($userId, $functions, $startDate, $endDate);
        break;
    case 'monthly':
        generateMonthlyReport($userId, $functions, $year, $month);
        break;
    case 'category':
        generateCategoryReport($userId, $functions, $startDate, $endDate);
        break;
    case 'trend':
        generateTrendReport($userId, $functions, $year);
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid report type']);
        break;
}

function generateSummaryReport($userId, $functions, $startDate, $endDate) {
    try {
        $income = $functions->getTotalIncome($userId, $startDate, $endDate);
        $expenses = $functions->getTotalExpenses($userId, $startDate, $endDate);
        $netIncome = $income - $expenses;

        $db = new Database();
        $conn = $db->getConnection();

        // Get transaction count
        $query = "SELECT COUNT(*) as total_transactions FROM transactions WHERE user_id = ?";
        $params = [$userId];

        if ($startDate && $endDate) {
            $query .= " AND date BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        }

        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch();

        // Get most recent transactions
        $query = "SELECT t.*, c.name as category_name
                 FROM transactions t
                 JOIN categories c ON t.category_id = c.id
                 WHERE t.user_id = ?";
        $params = [$userId];

        if ($startDate && $endDate) {
            $query .= " AND t.date BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        }

        $query .= " ORDER BY t.date DESC LIMIT 5";

        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $recentTransactions = $stmt->fetchAll();

        // Format recent transactions
        foreach ($recentTransactions as &$transaction) {
            $transaction['formatted_amount'] = $functions->formatCurrency($transaction['amount']);
            $transaction['formatted_date'] = $functions->formatDate($transaction['date']);
        }

        $report = [
            'period' => $startDate && $endDate ? "$startDate to $endDate" : 'All time',
            'total_income' => $income,
            'total_expenses' => $expenses,
            'net_income' => $netIncome,
            'formatted_income' => $functions->formatCurrency($income),
            'formatted_expenses' => $functions->formatCurrency($expenses),
            'formatted_net' => $functions->formatCurrency($netIncome),
            'total_transactions' => $result['total_transactions'],
            'recent_transactions' => $recentTransactions,
            'generated_at' => date('Y-m-d H:i:s')
        ];

        echo json_encode([
            'success' => true,
            'report_type' => 'summary',
            'data' => $report
        ]);

    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function generateMonthlyReport($userId, $functions, $year, $month) {
    try {
        $monthlyData = $functions->getMonthlySummary($userId, $year, $month);

        $db = new Database();
        $conn = $db->getConnection();

        // Get daily breakdown for the month
        $startDate = "$year-$month-01";
        $endDate = date('Y-m-t', strtotime($startDate));

        $query = "SELECT DATE(date) as day, type, SUM(amount) as total
                 FROM transactions
                 WHERE user_id = ? AND date BETWEEN ? AND ?
                 GROUP BY DATE(date), type
                 ORDER BY day";

        $stmt = $conn->prepare($query);
        $stmt->execute([$userId, $startDate, $endDate]);
        $dailyData = $stmt->fetchAll();

        // Organize daily data
        $dailyBreakdown = [];
        foreach ($dailyData as $row) {
            $day = $row['day'];
            if (!isset($dailyBreakdown[$day])) {
                $dailyBreakdown[$day] = ['income' => 0, 'expense' => 0, 'net' => 0];
            }
            $dailyBreakdown[$day][$row['type']] = $row['total'];
            $dailyBreakdown[$day]['net'] = $dailyBreakdown[$day]['income'] - $dailyBreakdown[$day]['expense'];
        }

        $report = [
            'period' => $monthlyData['period'],
            'total_income' => $monthlyData['income'],
            'total_expenses' => $monthlyData['expenses'],
            'net_income' => $monthlyData['net'],
            'formatted_income' => $functions->formatCurrency($monthlyData['income']),
            'formatted_expenses' => $functions->formatCurrency($monthlyData['expenses']),
            'formatted_net' => $functions->formatCurrency($monthlyData['net']),
            'daily_breakdown' => $dailyBreakdown,
            'generated_at' => date('Y-m-d H:i:s')
        ];

        echo json_encode([
            'success' => true,
            'report_type' => 'monthly',
            'data' => $report
        ]);

    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function generateCategoryReport($userId, $functions, $startDate, $endDate) {
    try {
        $incomeCategories = $functions->getCategoryBreakdown($userId, 'income', $startDate, $endDate);
        $expenseCategories = $functions->getCategoryBreakdown($userId, 'expense', $startDate, $endDate);

        // Format amounts
        foreach ($incomeCategories as &$category) {
            $category['formatted_total'] = $functions->formatCurrency($category['total']);
        }

        foreach ($expenseCategories as &$category) {
            $category['formatted_total'] = $functions->formatCurrency($category['total']);
        }

        $totalIncome = array_sum(array_column($incomeCategories, 'total'));
        $totalExpenses = array_sum(array_column($expenseCategories, 'total'));

        $report = [
            'period' => $startDate && $endDate ? "$startDate to $endDate" : 'All time',
            'income_categories' => $incomeCategories,
            'expense_categories' => $expenseCategories,
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'formatted_total_income' => $functions->formatCurrency($totalIncome),
            'formatted_total_expenses' => $functions->formatCurrency($totalExpenses),
            'generated_at' => date('Y-m-d H:i:s')
        ];

        echo json_encode([
            'success' => true,
            'report_type' => 'category',
            'data' => $report
        ]);

    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function generateTrendReport($userId, $functions, $year) {
    try {
        $monthlyTrends = [];

        for ($month = 1; $month <= 12; $month++) {
            $monthlyData = $functions->getMonthlySummary($userId, $year, $month);
            $monthlyTrends[] = [
                'month' => date('F', mktime(0, 0, 0, $month, 1)),
                'month_num' => $month,
                'income' => $monthlyData['income'],
                'expenses' => $monthlyData['expenses'],
                'net' => $monthlyData['net']
            ];
        }

        $report = [
            'year' => $year,
            'monthly_trends' => $monthlyTrends,
            'total_income' => array_sum(array_column($monthlyTrends, 'income')),
            'total_expenses' => array_sum(array_column($monthlyTrends, 'expenses')),
            'total_net' => array_sum(array_column($monthlyTrends, 'net')),
            'generated_at' => date('Y-m-d H:i:s')
        ];

        $report['formatted_total_income'] = $functions->formatCurrency($report['total_income']);
        $report['formatted_total_expenses'] = $functions->formatCurrency($report['total_expenses']);
        $report['formatted_total_net'] = $functions->formatCurrency($report['total_net']);

        echo json_encode([
            'success' => true,
            'report_type' => 'trend',
            'data' => $report
        ]);

    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
