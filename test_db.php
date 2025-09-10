<?php
require_once 'config/db.php';

echo "<h1>B.E.N.T.A Database Connection Test</h1>";

// Test database connection
$db = new Database();
$conn = $db->getConnection();

if ($conn) {
    echo "<p style='color: green;'>✓ Database connection successful!</p>";

    // Test if tables exist
    $tables = ['users', 'categories', 'transactions', 'settings'];
    foreach ($tables as $table) {
        try {
            $stmt = $conn->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            if ($stmt->rowCount() > 0) {
                echo "<p style='color: green;'>✓ Table '$table' exists</p>";
            } else {
                echo "<p style='color: red;'>✗ Table '$table' does not exist</p>";
            }
        } catch(PDOException $e) {
            echo "<p style='color: red;'>✗ Error checking table '$table': " . $e->getMessage() . "</p>";
        }
    }

    // Test if we can query users table
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users");
        $stmt->execute();
        $result = $stmt->fetch();
        echo "<p style='color: green;'>✓ Users table query successful. Total users: " . $result['count'] . "</p>";
    } catch(PDOException $e) {
        echo "<p style='color: red;'>✗ Error querying users table: " . $e->getMessage() . "</p>";
    }

} else {
    echo "<p style='color: red;'>✗ Database connection failed!</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>Go to Main Application</a></p>";
echo "<p><a href='login.php'>Go to Login Page</a></p>";
?>
