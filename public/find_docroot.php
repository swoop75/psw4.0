<?php
/**
 * Debug script to find Apache document root and paths
 */

echo "<h1>Apache & PHP Configuration Debug</h1>";

echo "<h2>Server Information</h2>";
echo "<strong>Server Software:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "<strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "<strong>Script Filename:</strong> " . $_SERVER['SCRIPT_FILENAME'] . "<br>";
echo "<strong>Request URI:</strong> " . $_SERVER['REQUEST_URI'] . "<br>";
echo "<strong>HTTP Host:</strong> " . $_SERVER['HTTP_HOST'] . "<br>";
echo "<strong>Server Name:</strong> " . $_SERVER['SERVER_NAME'] . "<br>";

echo "<h2>Current Directory</h2>";
echo "<strong>Current Working Directory:</strong> " . getcwd() . "<br>";
echo "<strong>Script Directory:</strong> " . dirname(__FILE__) . "<br>";

echo "<h2>File System Check</h2>";
$paths_to_check = [
    'D:\\',
    'D:\\psw',
    'D:\\github',
    'D:\\github\\psw',
    'D:\\github\\psw\\psw4.0',
    'C:\\xampp\\htdocs',
    'C:\\wamp64\\www',
    'C:\\Apache24\\htdocs'
];

foreach ($paths_to_check as $path) {
    if (is_dir($path)) {
        echo "✅ <strong>$path</strong> exists<br>";
        $files = scandir($path);
        echo "&nbsp;&nbsp;&nbsp;Contents: " . implode(', ', array_slice($files, 2, 5)) . "...<br>";
    } else {
        echo "❌ <strong>$path</strong> does not exist<br>";
    }
}

echo "<h2>URL Construction</h2>";
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . '://' . $host;
echo "<strong>Base URL:</strong> $base_url<br>";

$script_dir = dirname($_SERVER['SCRIPT_NAME']);
if ($script_dir !== '/') {
    $full_url = $base_url . $script_dir;
} else {
    $full_url = $base_url;
}
echo "<strong>Full URL:</strong> $full_url<br>";

echo "<h2>Test File Creation</h2>";
$test_content = '<!DOCTYPE html>
<html>
<head><title>Test</title></head>
<body style="font-family: Arial; padding: 2rem; background: linear-gradient(135deg, #00C896, #1A73E8); color: white; text-align: center;">
<h1>🎉 SUCCESS!</h1>
<p>If you can see this, the path is working correctly!</p>
<p>Current location: ' . __DIR__ . '</p>
</body>
</html>';

$test_file = __DIR__ . '/working_test.html';
if (file_put_contents($test_file, $test_content)) {
    echo "✅ Test file created at: <strong>$test_file</strong><br>";
    echo "📱 Try this URL: <a href='working_test.html' target='_blank'>$full_url/working_test.html</a><br>";
} else {
    echo "❌ Could not create test file<br>";
}

echo "<h2>Next Steps</h2>";
echo "1. Note the Document Root path above<br>";
echo "2. Copy test files to that location<br>";
echo "3. Or adjust your Apache virtual host configuration<br>";
echo "4. Try the 'working_test.html' link above<br>";
?>