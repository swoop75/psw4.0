<?php
/**
 * Debug database connection
 */

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && substr($line, 0, 1) !== '#') {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

echo "=== Debug Database Connection ===\n";
echo "Host: " . ($_ENV['DB_HOST'] ?? 'NOT SET') . "\n";
echo "Username: " . ($_ENV['DB_USERNAME'] ?? 'NOT SET') . "\n";
echo "Password: " . (isset($_ENV['DB_PASSWORD']) ? '[SET - ' . strlen($_ENV['DB_PASSWORD']) . ' chars]' : '[NOT SET]') . "\n";
echo "Foundation DB: " . ($_ENV['DB_FOUNDATION'] ?? 'NOT SET') . "\n\n";

// Test direct PDO connection
try {
    $host = $_ENV['DB_HOST'] ?? '100.117.171.98';
    $username = $_ENV['DB_USERNAME'] ?? 'psw_user';
    $password = $_ENV['DB_PASSWORD'] ?? '';
    $dbname = $_ENV['DB_FOUNDATION'] ?? 'psw_foundation';
    
    echo "Attempting direct PDO connection...\n";
    echo "DSN: mysql:host=$host;dbname=$dbname;charset=utf8mb4\n\n";
    
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    echo "✅ Direct PDO connection successful!\n";
    
    // Test a simple query
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    echo "✅ Test query successful: " . $result['test'] . "\n";
    
} catch (PDOException $e) {
    echo "❌ Direct PDO connection failed: " . $e->getMessage() . "\n";
    echo "Error code: " . $e->getCode() . "\n";
}
?>