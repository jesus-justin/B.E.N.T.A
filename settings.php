<?php
/**
 * Settings Page for B.E.N.T.A
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
$settings = $functions->getUserSettings($user['id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - B.E.N.T.A</title>
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
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li class="active"><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="#" onclick="logout()"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <header class="page-header">
                <h1>Settings</h1>
            </header>

            <div class="settings-container">
                <!-- Business Settings -->
                <div class="settings-section">
                    <h2><i class="fas fa-building"></i> Business Information</h2>
                    <form id="businessForm" class="settings-form">
                        <div class="form-group">
                            <label for="businessName">Business Name</label>
                            <input type="text" id="businessName" value="<?php echo htmlspecialchars($settings['business_name'] ?? ''); ?>" placeholder="Enter your business name">
                        </div>
                        <div class="form-group">
                            <label for="currency">Currency</label>
                            <select id="currency">
                                <option value="PHP" <?php echo ($settings['currency'] ?? 'PHP') === 'PHP' ? 'selected' : ''; ?>>Philippine Peso (₱)</option>
                                <option value="USD" <?php echo ($settings['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>US Dollar ($)</option>
                                <option value="EUR" <?php echo ($settings['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>Euro (€)</option>
                                <option value="GBP" <?php echo ($settings['currency'] ?? '') === 'GBP' ? 'selected' : ''; ?>>British Pound (£)</option>
                                <option value="JPY" <?php echo ($settings['currency'] ?? '') === 'JPY' ? 'selected' : ''; ?>>Japanese Yen (¥)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fiscalYearStart">Fiscal Year Start</label>
                            <input type="date" id="fiscalYearStart" value="<?php echo $settings['fiscal_year_start'] ?? date('Y-01-01'); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Save Business Settings</button>
                    </form>
                </div>

                <!-- Account Settings -->
                <div class="settings-section">
                    <h2><i class="fas fa-user"></i> Account Information</h2>
                    <div class="account-info">
                        <div class="info-item">
                            <label>Username:</label>
                            <span><?php echo htmlspecialchars($user['username']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Email:</label>
                            <span><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Member Since:</label>
                            <span><?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Categories Management -->
                <div class="settings-section">
                    <h2><i class="fas fa-tags"></i> Categories</h2>
                    <div class="categories-management">
                        <div class="section-header">
                            <h3>Income Categories</h3>
                            <button class="btn btn-secondary" onclick="showAddCategoryModal('income')">
                                <i class="fas fa-plus"></i> Add Income Category
                            </button>
                        </div>
                        <div id="incomeCategories" class="categories-list">
                            <!-- Income categories will be loaded here -->
                        </div>

                        <div class="section-header">
                            <h3>Expense Categories</h3>
                            <button class="btn btn-secondary" onclick="showAddCategoryModal('expense')">
                                <i class="fas fa-plus"></i> Add Expense Category
                            </button>
                        </div>
                        <div id="expenseCategories" class="categories-list">
                            <!-- Expense categories will be loaded here -->
                        </div>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div class="settings-section danger-zone">
                    <h2><i class="fas fa-exclamation-triangle"></i> Danger Zone</h2>
                    <p class="warning-text">These actions are irreversible. Please be certain before proceeding.</p>
                    <div class="danger-actions">
                        <button class="btn btn-danger" onclick="showResetDataModal()">
                            <i class="fas fa-trash-alt"></i> Reset All Data
                        </button>
                        <button class="btn btn-danger" onclick="showDeleteAccountModal()">
                            <i class="fas fa-user-times"></i> Delete Account
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Category Modal -->
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Category</h3>
                <span class="modal-close" onclick="closeModal()">&times;</span>
            </div>
            <form id="categoryForm">
                <input type="hidden" id="categoryType">
                <div class="form-group">
                    <label for="categoryName">Category Name</label>
                    <input type="text" id="categoryName" required>
                </div>
                <button type="submit" class="btn btn-primary">Add Category</button>
            </form>
        </div>
    </div>

    <!-- Reset Data Modal -->
    <div id="resetModal" class="modal">
        <div class="modal-content small">
            <div class="modal-header">
                <h3>Reset All Data</h3>
                <span class="modal-close" onclick="closeResetModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to reset all your data?</p>
                <p class="delete-warning">This will delete all transactions, categories, and settings. This action cannot be undone.</p>
                <div class="form-group">
                    <label for="resetConfirm">Type "RESET" to confirm:</label>
                    <input type="text" id="resetConfirm" required>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger" onclick="confirmReset()">Reset Data</button>
                <button class="btn btn-outline" onclick="closeResetModal()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <div id="deleteAccountModal" class="modal">
        <div class="modal-content small">
            <div class="modal-header">
                <h3>Delete Account</h3>
                <span class="modal-close" onclick="closeDeleteAccountModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete your account?</p>
                <p class="delete-warning">This will permanently delete your account and all associated data. This action cannot be undone.</p>
                <div class="form-group">
                    <label for="deleteConfirm">Type "DELETE" to confirm:</label>
                    <input type="text" id="deleteConfirm" required>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger" onclick="confirmDeleteAccount()">Delete Account</button>
                <button class="btn btn-outline" onclick="closeDeleteAccountModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script src="assets/js/animations.js"></script>
    <script>
        // Load categories
        async function loadCategories() {
            try {
                const response = await fetch('api/categories.php');
                const result = await response.json();

                if (result.success) {
                    const incomeContainer = document.getElementById('incomeCategories');
                    const expenseContainer = document.getElementById('expenseCategories');

                    const incomeCategories = result.data.filter(cat => cat.type === 'income');
                    const expenseCategories = result.data.filter(cat => cat.type === 'expense');

                    incomeContainer.innerHTML = incomeCategories.map(cat => `
                        <div class="category-item">
                            <span class="category-name">${cat.name}</span>
                            <span class="category-count">${cat.transaction_count} transactions</span>
                            <div class="category-actions">
                                <button class="btn btn-sm btn-outline" onclick="editCategory(${cat.id}, '${cat.name}', '${cat.type}')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteCategory(${cat.id}, '${cat.name}')" ${cat.transaction_count > 0 ? 'disabled' : ''}>
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `).join('') || '<p class="no-data">No income categories found</p>';

                    expenseContainer.innerHTML = expenseCategories.map(cat => `
                        <div class="category-item">
                            <span class="category-name">${cat.name}</span>
                            <span class="category-count">${cat.transaction_count} transactions</span>
                            <div class="category-actions">
                                <button class="btn btn-sm btn-outline" onclick="editCategory(${cat.id}, '${cat.name}', '${cat.type}')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteCategory(${cat.id}, '${cat.name}')" ${cat.transaction_count > 0 ? 'disabled' : ''}>
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `).join('') || '<p class="no-data">No expense categories found</p>';
                }
            } catch (error) {
                console.error('Error loading categories:', error);
            }
        }

        // Save business settings
        document.getElementById('businessForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const data = {
                business_name: document.getElementById('businessName').value,
                currency: document.getElementById('currency').value,
                fiscal_year_start: document.getElementById('fiscalYearStart').value
            };

            try {
                const response = await fetch('api/settings.php', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showMessage('Settings saved successfully!', 'success');
                } else {
                    showMessage(result.message, 'error');
                }
            } catch (error) {
                showMessage('An error occurred. Please try again.', 'error');
            }
        });

        // Show add category modal
        function showAddCategoryModal(type) {
            document.getElementById('categoryType').value = type;
            document.getElementById('categoryName').value = '';
            document.getElementById('categoryModal').style.display = 'block';
        }

        // Handle category form submission
        document.getElementById('categoryForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const data = {
                name: document.getElementById('categoryName').value,
                type: document.getElementById('categoryType').value
            };

            try {
                const response = await fetch('api/categories.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    closeModal();
                    loadCategories();
                    showMessage('Category added successfully!', 'success');
                } else {
                    showMessage(result.message, 'error');
                }
            } catch (error) {
                showMessage('An error occurred. Please try again.', 'error');
            }
        });

        // Edit category
        async function editCategory(id, name, type) {
            const newName = prompt('Edit category name:', name);
            if (newName && newName !== name) {
                try {
                    const response = await fetch('api/categories.php', {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            id: id,
                            name: newName,
                            type: type
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        loadCategories();
                        showMessage('Category updated successfully!', 'success');
                    } else {
                        showMessage(result.message, 'error');
                    }
                } catch (error) {
                    showMessage('An error occurred. Please try again.', 'error');
                }
            }
        }

        // Delete category
        async function deleteCategory(id, name) {
            if (confirm(`Are you sure you want to delete the category "${name}"?`)) {
                try {
                    const response = await fetch(`api/categories.php?id=${id}`, {
                        method: 'DELETE'
                    });

                    const result = await response.json();

                    if (result.success) {
                        loadCategories();
                        showMessage('Category deleted successfully!', 'success');
                    } else {
                        showMessage(result.message, 'error');
                    }
                } catch (error) {
                    showMessage('An error occurred. Please try again.', 'error');
                }
            }
        }

        // Modal functions
        function closeModal() {
            document.getElementById('categoryModal').style.display = 'none';
        }

        function showResetDataModal() {
            document.getElementById('resetModal').style.display = 'block';
        }

        function closeResetModal() {
            document.getElementById('resetModal').style.display = 'none';
            document.getElementById('resetConfirm').value = '';
        }

        function showDeleteAccountModal() {
            document.getElementById('deleteAccountModal').style.display = 'block';
        }

        function closeDeleteAccountModal() {
            document.getElementById('deleteAccountModal').style.display = 'none';
            document.getElementById('deleteConfirm').value = '';
        }

        // Confirm reset data
        async function confirmReset() {
            const confirmText = document.getElementById('resetConfirm').value;
            if (confirmText !== 'RESET') {
                showMessage('Please type "RESET" to confirm', 'error');
                return;
            }

            // Implement reset functionality
            showMessage('Reset functionality not implemented yet', 'info');
            closeResetModal();
        }

        // Confirm delete account
        async function confirmDeleteAccount() {
            const confirmText = document.getElementById('deleteConfirm').value;
            if (confirmText !== 'DELETE') {
                showMessage('Please type "DELETE" to confirm', 'error');
                return;
            }

            // Implement delete account functionality
            showMessage('Delete account functionality not implemented yet', 'info');
            closeDeleteAccountModal();
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
            loadCategories();
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            const categoryModal = document.getElementById('categoryModal');
            const resetModal = document.getElementById('resetModal');
            const deleteAccountModal = document.getElementById('deleteAccountModal');

            if (event.target === categoryModal) {
                closeModal();
            }
            if (event.target === resetModal) {
                closeResetModal();
            }
            if (event.target === deleteAccountModal) {
                closeDeleteAccountModal();
            }
        };
    </script>
</body>
</html>
