<?php
/**
 * Reports Page for B.E.N.T.A
 * Business Expense and Net Transaction Analyzer
 */

session_start();

require_once 'includes/auth.php';

$auth = new Auth();

// Check authentication
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = $auth->getCurrentUser();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - B.E.N.T.A</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-chart-line"></i> B.E.N.T.A</h2>
            </div>
            <ul class="sidebar-menu">
                <li><a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="transactions.php"><i class="fas fa-exchange-alt"></i> Transactions</a></li>
                <li class="active"><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="#" onclick="logout()"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <header class="page-header">
                <h1>Financial Reports</h1>
            </header>

            <!-- Report Type Selector -->
            <div class="report-selector">
                <div class="report-tabs">
                    <button class="tab-btn active" onclick="showReport('summary')">Summary</button>
                    <button class="tab-btn" onclick="showReport('monthly')">Monthly</button>
                    <button class="tab-btn" onclick="showReport('category')">Categories</button>
                    <button class="tab-btn" onclick="showReport('trend')">Trends</button>
                </div>

                <div class="report-filters">
                    <div class="filter-group" id="dateFilters" style="display: none;">
                        <label for="startDate">From:</label>
                        <input type="date" id="startDate">
                        <label for="endDate">To:</label>
                        <input type="date" id="endDate">
                    </div>
                    <div class="filter-group" id="yearFilter">
                        <label for="reportYear">Year:</label>
                        <select id="reportYear">
                            <?php
                            $currentYear = date('Y');
                            for ($year = $currentYear; $year >= $currentYear - 5; $year--) {
                                echo "<option value='$year'>$year</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="filter-group" id="monthFilter" style="display: none;">
                        <label for="reportMonth">Month:</label>
                        <select id="reportMonth">
                            <?php
                            for ($month = 1; $month <= 12; $month++) {
                                $monthName = date('F', mktime(0, 0, 0, $month, 1));
                                echo "<option value='$month'>$monthName</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button class="btn btn-primary" onclick="generateReport()">
                        <i class="fas fa-chart-bar"></i> Generate Report
                    </button>
                </div>
            </div>

            <!-- Report Content -->
            <div class="report-content" id="reportContent">
                <div class="loading">Select a report type and click Generate Report</div>
            </div>
        </main>
    </div>

    <script src="assets/js/main.js"></script>
    <script src="assets/js/animations.js"></script>
    <script>
        let currentReportType = 'summary';

        // Show report type
        function showReport(type) {
            currentReportType = type;

            // Update active tab
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            // Show/hide filters based on report type
            const dateFilters = document.getElementById('dateFilters');
            const yearFilter = document.getElementById('yearFilter');
            const monthFilter = document.getElementById('monthFilter');

            dateFilters.style.display = (type === 'summary' || type === 'category') ? 'block' : 'none';
            yearFilter.style.display = (type === 'monthly' || type === 'trend') ? 'block' : 'none';
            monthFilter.style.display = type === 'monthly' ? 'block' : 'none';

            // Set default month to current
            if (type === 'monthly') {
                document.getElementById('reportMonth').value = new Date().getMonth() + 1;
            }

            // Clear current report
            document.getElementById('reportContent').innerHTML = '<div class="loading">Click Generate Report to view results</div>';
        }

        // Generate report
        async function generateReport() {
            const contentDiv = document.getElementById('reportContent');
            contentDiv.innerHTML = '<div class="loading">Generating report...</div>';

            try {
                let url = `api/reports.php?type=${currentReportType}`;

                if (currentReportType === 'summary' || currentReportType === 'category') {
                    const startDate = document.getElementById('startDate').value;
                    const endDate = document.getElementById('endDate').value;

                    if (startDate) url += `&start_date=${startDate}`;
                    if (endDate) url += `&end_date=${endDate}`;
                } else if (currentReportType === 'monthly' || currentReportType === 'trend') {
                    const year = document.getElementById('reportYear').value;
                    url += `&year=${year}`;

                    if (currentReportType === 'monthly') {
                        const month = document.getElementById('reportMonth').value;
                        url += `&month=${month}`;
                    }
                }

                const response = await fetch(url);
                const result = await response.json();

                if (result.success) {
                    displayReport(result.data, currentReportType);
                } else {
                    contentDiv.innerHTML = `<div class="error">Error: ${result.message}</div>`;
                }
            } catch (error) {
                contentDiv.innerHTML = '<div class="error">Error generating report</div>';
            }
        }

        // Display report
        function displayReport(data, type) {
            let html = '';

            switch (type) {
                case 'summary':
                    html = generateSummaryReport(data);
                    break;
                case 'monthly':
                    html = generateMonthlyReport(data);
                    break;
                case 'category':
                    html = generateCategoryReport(data);
                    break;
                case 'trend':
                    html = generateTrendReport(data);
                    break;
            }

            document.getElementById('reportContent').innerHTML = html;
        }

        // Generate summary report HTML
        function generateSummaryReport(data) {
            return `
                <div class="report-section">
                    <h2>Financial Summary</h2>
                    <div class="summary-stats">
                        <div class="stat-card">
                            <h3>${data.formatted_income}</h3>
                            <p>Total Income</p>
                        </div>
                        <div class="stat-card">
                            <h3>${data.formatted_expenses}</h3>
                            <p>Total Expenses</p>
                        </div>
                        <div class="stat-card">
                            <h3>${data.formatted_net}</h3>
                            <p>Net Income</p>
                        </div>
                        <div class="stat-card">
                            <h3>${data.total_transactions}</h3>
                            <p>Total Transactions</p>
                        </div>
                    </div>

                    <h3>Recent Transactions</h3>
                    <div class="recent-transactions-list">
                        ${data.recent_transactions.map(t => `
                            <div class="transaction-item">
                                <div class="transaction-info">
                                    <strong>${t.description}</strong>
                                    <span>${t.category_name} • ${t.formatted_date}</span>
                                </div>
                                <div class="transaction-amount ${t.type}">
                                    ${t.formatted_amount}
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        // Generate monthly report HTML
        function generateMonthlyReport(data) {
            const dailyBreakdown = Object.entries(data.daily_breakdown).map(([date, values]) => ({
                date,
                ...values
            }));

            return `
                <div class="report-section">
                    <h2>Monthly Report - ${data.period}</h2>
                    <div class="summary-stats">
                        <div class="stat-card">
                            <h3>${data.formatted_income}</h3>
                            <p>Monthly Income</p>
                        </div>
                        <div class="stat-card">
                            <h3>${data.formatted_expenses}</h3>
                            <p>Monthly Expenses</p>
                        </div>
                        <div class="stat-card">
                            <h3>${data.formatted_net}</h3>
                            <p>Monthly Net</p>
                        </div>
                    </div>

                    <h3>Daily Breakdown</h3>
                    <div class="data-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Income</th>
                                    <th>Expenses</th>
                                    <th>Net</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${dailyBreakdown.map(day => `
                                    <tr>
                                        <td>${new Date(day.date).toLocaleDateString()}</td>
                                        <td class="income">₱${day.income.toFixed(2)}</td>
                                        <td class="expense">₱${day.expense.toFixed(2)}</td>
                                        <td class="${day.net >= 0 ? 'income' : 'expense'}">₱${day.net.toFixed(2)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }

        // Generate category report HTML
        function generateCategoryReport(data) {
            return `
                <div class="report-section">
                    <h2>Category Breakdown</h2>

                    <div class="category-breakdown">
                        <div class="category-section">
                            <h3>Income Categories</h3>
                            <div class="category-list">
                                ${data.income_categories.map(cat => `
                                    <div class="category-item">
                                        <span class="category-name">${cat.name}</span>
                                        <span class="category-amount income">${cat.formatted_total}</span>
                                        <span class="category-count">${cat.count} transactions</span>
                                    </div>
                                `).join('')}
                            </div>
                        </div>

                        <div class="category-section">
                            <h3>Expense Categories</h3>
                            <div class="category-list">
                                ${data.expense_categories.map(cat => `
                                    <div class="category-item">
                                        <span class="category-name">${cat.name}</span>
                                        <span class="category-amount expense">${cat.formatted_total}</span>
                                        <span class="category-count">${cat.count} transactions</span>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        // Generate trend report HTML
        function generateTrendReport(data) {
            return `
                <div class="report-section">
                    <h2>Yearly Trends - ${data.year}</h2>
                    <div class="summary-stats">
                        <div class="stat-card">
                            <h3>${data.formatted_total_income}</h3>
                            <p>Total Income</p>
                        </div>
                        <div class="stat-card">
                            <h3>${data.formatted_total_expenses}</h3>
                            <p>Total Expenses</p>
                        </div>
                        <div class="stat-card">
                            <h3>${data.formatted_total_net}</h3>
                            <p>Total Net</p>
                        </div>
                    </div>

                    <h3>Monthly Trends</h3>
                    <div class="data-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Income</th>
                                    <th>Expenses</th>
                                    <th>Net Income</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.monthly_trends.map(month => `
                                    <tr>
                                        <td>${month.month}</td>
                                        <td class="income">₱${month.income.toFixed(2)}</td>
                                        <td class="expense">₱${month.expenses.toFixed(2)}</td>
                                        <td class="${month.net >= 0 ? 'income' : 'expense'}">₱${month.net.toFixed(2)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }

        // Logout function
        async function logout() {
            try {
                const response = await fetch('api/logout.php', { method: 'POST' });
                const result = await response.json();

                if (result.success) {
                    window.location.href = 'login.php';
                }
            } catch (error) {
                window.location.href = 'login.php';
            }
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Set default dates
            const today = new Date();
            const thirtyDaysAgo = new Date();
            thirtyDaysAgo.setDate(today.getDate() - 30);

            document.getElementById('startDate').value = thirtyDaysAgo.toISOString().split('T')[0];
            document.getElementById('endDate').value = today.toISOString().split('T')[0];
            document.getElementById('reportYear').value = today.getFullYear();
            document.getElementById('reportMonth').value = today.getMonth() + 1;
        });
    </script>
</body>
</html>
