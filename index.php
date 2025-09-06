<?php
/**
 * Main Dashboard for B.E.N.T.A
 * Business Expense and Net Transaction Analyzer
 */

session_start();

require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth = new Auth();
$functions = new Functions();

// Check authentication
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = $auth->getCurrentUser();
$userId = $user['id'];
$settings = $functions->getUserSettings($userId);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - B.E.N.T.A</title>
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
                <li class="active"><a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="transactions.php"><i class="fas fa-exchange-alt"></i> Transactions</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="#" onclick="logout()"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <header class="page-header">
                <h1>Dashboard</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($user['username']); ?>!</span>
                </div>
            </header>

            <!-- Financial Overview Cards -->
            <div class="stats-grid">
                <div class="stat-card income-card">
                    <div class="stat-icon">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                    <div class="stat-content">
                        <h3 id="totalIncome">₱0.00</h3>
                        <p>Total Income</p>
                    </div>
                </div>

                <div class="stat-card expense-card">
                    <div class="stat-icon">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                    <div class="stat-content">
                        <h3 id="totalExpenses">₱0.00</h3>
                        <p>Total Expenses</p>
                    </div>
                </div>

                <div class="stat-card net-card">
                    <div class="stat-icon">
                        <i class="fas fa-balance-scale"></i>
                    </div>
                    <div class="stat-content">
                        <h3 id="netIncome">₱0.00</h3>
                        <p>Net Income</p>
                    </div>
                </div>

                <div class="stat-card transaction-card">
                    <div class="stat-icon">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="stat-content">
                        <h3 id="totalTransactions">0</h3>
                        <p>Total Transactions</p>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="recent-transactions">
                <div class="section-header">
                    <h2>Recent Transactions</h2>
                    <a href="transactions.php" class="btn btn-secondary">View All</a>
                </div>
                <div class="transaction-list" id="recentTransactions">
                    <div class="loading">Loading transactions...</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h2>Quick Actions</h2>
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="showAddTransactionModal()">
                        <i class="fas fa-plus"></i> Add Transaction
                    </button>
                    <a href="reports.php" class="btn btn-secondary">
                        <i class="fas fa-chart-bar"></i> View Reports
                    </a>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Transaction Modal -->
    <div id="transactionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Transaction</h3>
                <span class="modal-close" onclick="closeModal()">&times;</span>
            </div>
            <form id="transactionForm">
                <div class="form-group">
                    <label for="transactionAmount">Amount</label>
                    <input type="number" id="transactionAmount" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="transactionDescription">Description</label>
                    <input type="text" id="transactionDescription" required>
                </div>
                <div class="form-group">
                    <label for="transactionCategory">Category</label>
                    <select id="transactionCategory" required>
                        <option value="">Select Category</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="transactionDate">Date</label>
                    <input type="date" id="transactionDate" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="transactionType">Type</label>
                    <select id="transactionType" required>
                        <option value="income">Income</option>
                        <option value="expense">Expense</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Add Transaction</button>
            </form>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script src="assets/js/animations.js"></script>
    <script>
        // Load dashboard data
        async function loadDashboardData() {
            try {
                const response = await fetch('api/reports.php?type=summary');
                const result = await response.json();

                if (result.success) {
                    const data = result.data;
                    document.getElementById('totalIncome').textContent = data.formatted_income;
                    document.getElementById('totalExpenses').textContent = data.formatted_expenses;
                    document.getElementById('netIncome').textContent = data.formatted_net;
                    document.getElementById('totalTransactions').textContent = data.total_transactions;

                    // Update card colors based on net income
                    const netCard = document.querySelector('.net-card');
                    if (data.net_income > 0) {
                        netCard.classList.add('positive');
                    } else if (data.net_income < 0) {
                        netCard.classList.add('negative');
                    }
                }
            } catch (error) {
                console.error('Error loading dashboard data:', error);
            }
        }

        // Load recent transactions
        async function loadRecentTransactions() {
            try {
                const response = await fetch('api/transactions.php?limit=5');
                const result = await response.json();

                if (result.success) {
                    const container = document.getElementById('recentTransactions');
                    if (result.data.length === 0) {
                        container.innerHTML = '<div class="no-data">No transactions found. <a href="#" onclick="showAddTransactionModal()">Add your first transaction</a></div>';
                        return;
                    }

                    let html = '';
                    result.data.forEach(transaction => {
                        const typeClass = transaction.type === 'income' ? 'income' : 'expense';
                        const typeIcon = transaction.type === 'income' ? 'arrow-up' : 'arrow-down';

                        html += `
                            <div class="transaction-item ${typeClass}">
                                <div class="transaction-info">
                                    <h4>${transaction.description}</h4>
                                    <p>${transaction.category_name} • ${transaction.formatted_date}</p>
                                </div>
                                <div class="transaction-amount">
                                    <i class="fas fa-${typeIcon}"></i>
                                    ${transaction.formatted_amount}
                                </div>
                            </div>
                        `;
                    });
                    container.innerHTML = html;
                }
            } catch (error) {
                console.error('Error loading recent transactions:', error);
            }
        }

        // Load categories for transaction form
        async function loadCategories() {
            try {
                const response = await fetch('api/categories.php');
                const result = await response.json();

                if (result.success) {
                    const select = document.getElementById('transactionCategory');
                    result.data.forEach(category => {
                        const option = document.createElement('option');
                        option.value = category.id;
                        option.textContent = category.name;
                        select.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error loading categories:', error);
            }
        }

        // Show add transaction modal
        function showAddTransactionModal() {
            document.getElementById('transactionModal').style.display = 'block';
        }

        // Close modal
        function closeModal() {
            document.getElementById('transactionModal').style.display = 'none';
            document.getElementById('transactionForm').reset();
        }

        // Handle transaction form submission
        document.getElementById('transactionForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const data = {
                amount: document.getElementById('transactionAmount').value,
                description: document.getElementById('transactionDescription').value,
                category_id: document.getElementById('transactionCategory').value,
                date: document.getElementById('transactionDate').value,
                type: document.getElementById('transactionType').value
            };

            try {
                const response = await fetch('api/transactions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    closeModal();
                    loadDashboardData();
                    loadRecentTransactions();
                    showMessage('Transaction added successfully!', 'success');
                } else {
                    showMessage(result.message, 'error');
                }
            } catch (error) {
                showMessage('An error occurred. Please try again.', 'error');
            }
        });

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

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            loadDashboardData();
            loadRecentTransactions();
            loadCategories();
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('transactionModal');
            if (event.target === modal) {
                closeModal();
            }
        };
    </script>
</body>
</html>
