<?php
echo "Testing database connection...<br>";

// Simple PDO connection test
try {
    $pdo = new PDO("mysql:host=localhost;dbname=benta_db", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Database connection successful!<br>";

    // Test a simple query
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    echo "✓ Query execution successful: " . $result['test'] . "<br>";

} catch(PDOException $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "<br>";
}

echo "Test completed.";
?>
