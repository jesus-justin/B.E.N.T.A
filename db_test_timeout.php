<?php
echo "Testing database connection with timeout...<br>";
set_time_limit(10); // 10 second timeout

try {
    echo "Attempting to connect...<br>";
    $pdo = new PDO("mysql:host=localhost;dbname=benta_db", "root", "", [
        PDO::ATTR_TIMEOUT => 5,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
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
